<?php
/**
 * An extension of CodeIgniter's base Model class to remove repetition and increase productivity by providing
 * a couple handy methods(powered by CI's query builder), validation-in-model support, event callbacks and more.
 *
 * @author  Jamie Rumbelow <http://jamierumbelow.net>
 * @author  Md Emran Hasan <phpfour@gmail.com>
 * @author  Roni Kumar Saha <roni.cse@gmail.com>
 * @version 2.1
 *
 * @link    http://github.com/jamierumbelow/codeigniter-base-model
 * @link    https://github.com/phpfour/MY_Model
 * @link    https://github.com/ronisaha/ci-base-model
 *
 */

class CI_Base_Model extends CI_Model
{

    /* --------------------------------------------------------------
     * VARIABLES
     * ------------------------------------------------------------ */
    /**
     * This model's default database table. Automatically
     * guessed by pluralising the model name.
     */
    protected $_table;

    /**
     * The database connection object. Will be set to the default
     * connection. This allows individual models to use different DBs
     * without overwriting CI's global $this->db connection.
     */
    protected $_database;

    protected $_database_group = null;
    protected static $_connection_cache = array();

    /**
     * This model's default primary key or unique identifier.
     * Used by the get(), update() and delete() functions.
     */
    protected $primary_key = NULL;

    /**
     * Support for soft deletes and this model's 'deleted' key
     */
    protected $deleted_at_key = 'deleted_at';
    protected $deleted_by_key = 'deleted_by';
    protected $_temporary_with_deleted = FALSE;
    protected $_temporary_only_deleted = FALSE;
    private $soft_delete = NULL;

    /**
     * Support for Timestampable
     */
    protected $timestampable;
    protected $created_at_key = 'created_at';
    protected $updated_at_key = 'updated_at';

    /**
     * Support for Blamable
     */
    protected $blamable;
    protected $created_by_key = 'created_by';
    protected $updated_by_key = 'updated_by';


    /**
     * The various default callbacks available to the model. Each are
     * simple lists of method names (methods will be run on $this).
     */
    protected $before_create = array();
    protected $after_create = array();
    protected $before_update = array();
    protected $after_update = array();
    protected $before_get = array();
    protected $after_get = array();
    protected $before_delete = array();
    protected $after_delete = array();

    protected $event_listeners = array(
        'before_create' => array(),
        'after_create' => array(),
        'before_update' => array(),
        'after_update' => array(),
        'before_get' => array(),
        'after_get' => array(),
        'before_delete' => array(),
        'after_delete' => array(),
    );

    protected $callback_parameters = array();

    /**
     * Protected, non-modifiable attributes
     */
    protected $protected_attributes = array();

    /**
     * Relationship arrays. Use flat strings for defaults or string
     * => array to customise the class name and primary key
     */
    protected $belongs_to = array();
    protected $has_many = array();

    protected $_with = array();

    /**
     * An array of validation rules. This needs to be the same format
     * as validation rules passed to the Form_validation library.
     */
    protected $validate = array();

    /**
     * Optionally skip the validation. Used in conjunction with
     * skip_validation() to skip data validation for any future calls.
     */
    protected $skip_validation = FALSE;

    /**
     * By default we return our results as objects. If we need to override
     * this, we can, or, we could use the `as_array()` and `as_object()` scopes.
     */
    protected $return_type = 'object';
    protected $_temporary_return_type = NULL;

    /**
     * @var array list of fields
     */
    protected $_fields = array();

    /**
     * @var int returned number of rows of a query
     */
    protected $num_rows = NULL;

    private $_base_model_instance = NULL;

    /* --------------------------------------------------------------
     * GENERIC METHODS
     * ------------------------------------------------------------ */

    /**
     * Initialise the model, tie into the CodeIgniter superobject
     */
    public function __construct()
    {
        parent::__construct();

        $this->load->helper('inflector');

        $this->_initialize_event_listeners();
        $this->_initialize_schema();

        $this->_temporary_return_type = $this->return_type;
    }


    /**
     * Initialize the schema for special use cases
     * and try our best to guess the table name, primary_key
     * and the blamable, timestampable, softDeletable status
     */
    protected function _initialize_schema()
    {
        $this->set_database($this->_database_group);

        $this->_fetch_table();
        $this->_fetch_primary_key();

        if($this->primary_key == null && $this->is_base_model_instance()) {
            return;
        }

        $this->_fields = $this->get_fields();

        $this->_guess_is_soft_deletable();
        $this->_guess_is_blamable();
        $this->_guess_is_timestampable();

    }

    /**
     * Initialize all default listeners
     */
    protected function _initialize_event_listeners()
    {
        foreach($this->event_listeners as $event_listener => $e)
        {
            if(isset($this->$event_listener) && !empty($this->$event_listener)){
                foreach($this->$event_listener as $event){
                    $this->subscribe($event_listener, $event);
                }
            }
        }

        $this->subscribe('before_update', 'protect_attributes', TRUE);
    }

    /* --------------------------------------------------------------
     * CRUD INTERFACE
     * ------------------------------------------------------------ */

    /**
     * Fetch a single record based on the primary key. Returns an object.
     */
    public function get($primary_value)
    {
        return $this->get_by($this->primary_key, $primary_value);
    }

    /**
     * Fetch a single record based on an arbitrary WHERE call. Can be
     * any valid value to $this->_database->where().
     */
    public function get_by()
    {
        $where = func_get_args();

        $this->apply_soft_delete_filter();

        $this->_set_where($where);

        $this->trigger('before_get');

        $this->limit(1);

        $result = $this->_database->get($this->_table);

        $this->num_rows = count((array)$result);

        $row = $result->{$this->_get_return_type_method()}();
        $this->_temporary_return_type = $this->return_type;

        $row = $this->trigger('after_get', $row);

        $this->_with = array();
        return $row;
    }

    /**
     * Fetch an array of records based on an array of primary values.
     */
    public function get_many($values)
    {
        $this->apply_soft_delete_filter();

        $this->_database->where_in($this->primary_key, $values);

        return $this->get_all();
    }

    /**
     * Fetch an array of records based on an arbitrary WHERE call.
     */
    public function get_many_by()
    {
        $where = func_get_args();

        $this->apply_soft_delete_filter();

        $this->_set_where($where);

        return $this->get_all();
    }

    /**
     * Fetch all the records in the table. Can be used as a generic call
     * to $this->_database->get() with scoped methods.
     */
    public function get_all()
    {
        $this->trigger('before_get');

        $this->apply_soft_delete_filter();

        $result = $this->_database->get($this->_table)
            ->{$this->_get_return_type_method(true)}();
        $this->_temporary_return_type = $this->return_type;

        $this->num_rows = count($result);

        foreach ($result as $key => &$row)
        {
            $row = $this->trigger('after_get', $row, ($key == count($result) - 1));
        }

        $this->_with = array();
        return $result;
    }

    /**
     * @param $methodName
     * @param $args
     *
     * @return mixed
     */
    public function __call($methodName, $args)
    {
        $watch = array('find_by', 'find_all_by', 'find_field_by', 'findBy', 'findAllBy', 'findFieldBy');

        foreach ($watch as $found) {
            if ($methodName == $found) {
                break;
            }

            if (stristr($methodName, $found)) {
                $field = $this->underscore_from_camel_case(ltrim(str_replace($found, '', $methodName), '_'));
                $method = $this->underscore_from_camel_case($found);
                return $this->$method($field, $args);
            }
        }

        $method = self::underscore_from_camel_case($methodName);

        if (method_exists($this, $method)) {
            return call_user_func_array(array($this, $method), $args);
        }

        return $this->_handle_exception($methodName);

    }

    public static function underscore_from_camel_case($str) {
        $str[0] = strtolower($str[0]);
        $func = create_function('$c', 'return "_" . strtolower($c[1]);');
        return preg_replace_callback('/([A-Z])/', $func, $str);
    }

    /**
     * Returns a property value based on its name.
     * Do not call this method. This is a PHP magic method that we override
     * to allow using the following syntax to read a property or obtain event handlers:
     * <pre>
     * $value=$model->propertyName; [will be called $this->get_property_name()]
     * $value=$model->property_name; [will be called $this->get_property_name()]
     * $value=$model->load; [will be called $controller->load]
     * </pre>
     *
     * @param string $name the property name
     *
     * @return mixed the property value
     * @see __set
     */
    public function __get($name)
    {
        $getter = 'get' . $name;
        $getter2 = 'get_' . $name;
        $getter3 = 'get_' . self::underscore_from_camel_case($name);
        if (method_exists($this, $getter))
            return $this->$getter();
        elseif (method_exists($this, $getter2)){
            return $this->$getter2();
        }
        elseif (method_exists($this, $getter3)){
            return $this->$getter3();
        }

        return parent::__get($name);
    }


    /**
     * @param        $field
     * @param        $value
     * @param string $fields
     * @param null   $order
     *
     * @return bool
     */
    public function find_by($field, $value, $fields = '*', $order = NULL)
    {
        $arg_list = array();
        if (is_array($value)) {
            $arg_list = $value;
            $value    = $arg_list[0];
        }
        $fields = isset($arg_list[1]) ? $arg_list[1] : $fields;
        $order  = isset($arg_list[2]) ? $arg_list[2] : $order;

        $where = array($field => $value);
        return $this->find($where, $fields, $order);
    }

    /**
     * @param        $field
     * @param        $value
     * @param string $fields
     * @param null   $order
     * @param int    $start
     * @param null   $limit
     *
     * @return mixed
     */
    public function find_all_by($field, $value, $fields = '*', $order = NULL, $start = 0, $limit = NULL)
    {
        $arg_list = array();
        if (is_array($value)) {
            $arg_list = $value;
            $value    = $arg_list[0];
        }
        $fields = isset($arg_list[1]) ? $arg_list[1] : $fields;
        $order  = isset($arg_list[2]) ? $arg_list[2] : $order;
        $start  = isset($arg_list[3]) ? $arg_list[3] : $start;
        $limit  = isset($arg_list[4]) ? $arg_list[4] : $limit;

        $where = array($field => $value);
        return $this->find_all($where, $fields, $order, $start, $limit);
    }

    /**
     *
     * @param        $field
     * @param        $value
     * @param string $fields
     * @param null   $order
     *
     * @return mixed
     */
    public function find_field_by($field, $value, $fields = '*', $order = NULL)
    {
        $arg_list = array();

        if (is_array($value)) {
            $arg_list = $value;
            $value = $arg_list[0];
        }

        $fields = isset($arg_list[1]) ? $arg_list[1] : $fields;
        $order = isset($arg_list[2]) ? $arg_list[2] : $order;
        $where = array($field => $value);

        return $this->field($where, $fields, $fields, $order);
    }

    /**
     * Insert a new row into the table. $data should be an associative array
     * of data to be inserted. Returns newly created ID.
     * @param $data
     * @return bool
     */
    public function insert($data)
    {
        if (false !== $data = $this->_do_pre_create($data)) {
            $this->_database->insert($this->_table, $data);
            $insert_id = $this->_database->insert_id();

            $this->trigger('after_create', $insert_id);

            return $insert_id;
        }

        return false;
    }

    /**
     * Insert multiple rows into the table. Returns an array of multiple IDs.
     * @param $data
     * @param bool $insert_individual
     * @return array
     */
    public function insert_many($data, $insert_individual = false)
    {
        if($insert_individual){
            return $this->_insert_individual($data);
        }

        return $this->_insert_batch($data);
    }

    private function _insert_individual($data)
    {
        $ids = array();

        foreach ($data as $key => $row)
        {
            if(FALSE !== $row = $this->_do_pre_create($row)) {
                $ids[] = $this->insert($row);
            }
        }

        return $ids;
    }

    private function _insert_batch($data)
    {
        $_data = array();
        foreach ($data as $key => $row)
        {
            if(FALSE !== $row = $this->_do_pre_create($row)){
                $_data[$key] = $row;
            }
        }

        return $this->_database->insert_batch($this->_table, $_data);
    }

    /**
     * Updated a record based on the primary value.
     * @param $primary_value
     * @param $data
     * @return bool
     */
    public function update($primary_value, $data)
    {
        $data = $this->_do_pre_update($data);

        if ($data !== FALSE)
        {
            $result = $this->_database->where($this->primary_key, $primary_value)
                ->set($data)
                ->update($this->_table);

            $this->trigger('after_update', array($data, $result));

            return $result;
        }
        else
        {
            return FALSE;
        }
    }

    /**
     * Update many records, based on an array of primary values.
     * @param $primary_values
     * @param $data
     * @return bool
     */
    public function update_many($primary_values, $data)
    {
        $data = $this->_do_pre_update($data);

        if ($data !== FALSE)
        {
            $result = $this->_database->where_in($this->primary_key, $primary_values)
                ->set($data)
                ->update($this->_table);

            $this->trigger('after_update', array($data, $result));

            return $result;
        }

        return FALSE;
    }

    /**
     * Updated a record based on an arbitrary WHERE clause.
     */
    public function update_by()
    {
        $args = func_get_args();
        $data = array_pop($args);

        $data = $this->_do_pre_update($data);

        if ($data !== FALSE)
        {
            $this->_set_where($args);
            return $this->_update($data);
        }

        return FALSE;
    }

    /**
     * Update all records
     * @param $data
     * @return mixed
     */
    public function update_all($data)
    {
        $data = $this->_do_pre_update($data);
        return $this->_update($data);
    }

    /**
     * Update all records
     * @param $data
     * @param $where_key
     * @return
     */
    public function update_batch($data, $where_key)
    {
        $_data = array();

        foreach ($data as $key => $row) {

            if (false !== $row = $this->_do_pre_update($row)) {
                $_data[$key] = $row;
            }
        }

        return $this->_database->update_batch($this->_table, $_data, $where_key);
    }



    /**
     * @param null $data
     * @param null $update
     *
     * @return bool
     */
    public function on_duplicate_update($data = NULL, $update = NULL)
    {
        if (is_null($data)) {
            return FALSE;
        }

        if (is_null($update)) {
            $update = $data;
        }

        $sql = $this->_duplicate_insert_sql($data, $update);

        return $this->execute_query($sql);
    }

    /**
     * @param      $values
     * @param null $update
     *
     * @return string
     */
    protected function _duplicate_insert_sql($values, $update)
    {
        $updateStr = array();
        $keyStr    = array();
        $valStr    = array();

        $values = $this->trigger('before_create', $values);
        $update = $this->trigger('before_update', $update);

        foreach ($values as $key => $val) {
            $keyStr[] = $key;
            $valStr[] = $this->_database->escape($val);
        }

        foreach ($update as $key => $val) {
            $updateStr[] = $key . " = '{$val}'";
        }

        $sql = "INSERT INTO `" . $this->_database->dbprefix($this->_table) . "` (" . implode(', ', $keyStr) . ") ";
        $sql .= "VALUES (" . implode(', ', $valStr) . ") ";
        $sql .= "ON DUPLICATE KEY UPDATE " . implode(", ", $updateStr);

        return $sql;
    }

    /**
     * @param $condition
     * @param string $time
     * @return bool|mixed|void
     */
    protected function _delete($condition, $time = 'NOW()')
    {
        $this->trigger('before_delete', $condition);

        if ($this->soft_delete) {
            $escape = $time != 'NOW()';

            if(!is_string($time)){
                $time = $this->get_mysql_time($time);
            }

            $this->_database->set($this->deleted_at_key, $time, $escape);
            $result = $this->_database->update($this->_table);
        } else {
            $result = $this->_database->delete($this->_table);
        }

        return  $this->trigger('after_delete', $result);
    }

    protected function get_mysql_time($time)
    {
        if($time instanceof DateTime){
            return $time->format('Y-m-d H:i:s');
        }

        return date('Y-m-d H:i:s', $time);
    }

    protected function prevent_if_not_soft_deletable()
    {
        if (!$this->soft_delete || !isset($this->deleted_at_key) || empty($this->deleted_at_key)) {
            throw new Exception('This model does not setup properly to use soft delete');
        }
    }


    /**
     * Delete a row from the table by the primary value
     */
    public function delete($id, $time = 'NOW()')
    {
        $this->_database->where($this->primary_key, $id);

        return $this->_delete($id, $time);
    }


    /**
     * Alias for delete
     *
     * @param $id
     * @param $time
     * @return bool|mixed|void
     */
    public function delete_at($id, $time)
    {
        $this->prevent_if_not_soft_deletable();

        return $this->delete($id, $time);
    }

    /**
     * Alias for delete_by
     *
     * @param $condition
     * @param $time
     * @return bool|mixed|void
     */
    public function delete_by_at($condition, $time)
    {
        $this->prevent_if_not_soft_deletable();
        $this->_set_where($condition);

        return $this->_delete($condition, $time);
    }

    /**
     * Alias for delete_many
     *
     * @param $primary_values
     * @param $time
     * @return bool|mixed|void
     */
    public function delete_many_at($primary_values, $time)
    {
        $this->prevent_if_not_soft_deletable();

        return $this->delete_many($primary_values, $time);
    }

    /**
     * Delete a row from the database table by an arbitrary WHERE clause
     */
    public function delete_by()
    {
        $where = func_get_args();
        $this->_set_where($where);

        return $this->_delete($where);
    }

    /**
     * Delete many rows from the database table by multiple primary values
     */
    public function delete_many($primary_values, $time='NOW()')
    {
        $this->_database->where_in($this->primary_key, $primary_values);

        return $this->_delete($primary_values, $time);
    }


    /**
     * Truncates the table
     */
    public function truncate()
    {
        $result = $this->_database->truncate($this->_table);

        return $result;
    }

    /* --------------------------------------------------------------
     * RELATIONSHIPS
     * ------------------------------------------------------------ */

    public function with($relationship)
    {
        $this->_with[] = $relationship;

        if (!$this->is_subscribed('after_get', 'relate'))
        {
            $this->subscribe('after_get', 'relate');
        }

        return $this;
    }

    public function relate($row)
    {
        if (empty($row))
        {
            return $row;
        }

        foreach ($this->belongs_to as $key => $value)
        {
            if (is_string($value))
            {
                $relationship = $value;
                $options = array( 'primary_key' => $value . '_id', 'model' => $value . '_model' );
            }
            else
            {
                $relationship = $key;
                $options = $value;
            }

            if (in_array($relationship, $this->_with))
            {
                $this->load->model($options['model'], $relationship . '_model');

                if (is_object($row))
                {
                    $row->{$relationship} = $this->{$relationship . '_model'}->get($row->{$options['primary_key']});
                }
                else
                {
                    $row[$relationship] = $this->{$relationship . '_model'}->get($row[$options['primary_key']]);
                }
            }
        }

        foreach ($this->has_many as $key => $value)
        {
            if (is_string($value))
            {
                $relationship = $value;
                $options = array( 'primary_key' => singular($this->_table) . '_id', 'model' => singular($value) . '_model' );
            }
            else
            {
                $relationship = $key;
                $options = $value;
            }

            if (in_array($relationship, $this->_with))
            {
                $this->load->model($options['model'], $relationship . '_model');

                if (is_object($row))
                {
                    $row->{$relationship} = $this->{$relationship . '_model'}->get_many_by($options['primary_key'], $row->{$this->primary_key});
                }
                else
                {
                    $row[$relationship] = $this->{$relationship . '_model'}->get_many_by($options['primary_key'], $row[$this->primary_key]);
                }
            }
        }

        return $row;
    }

    /* --------------------------------------------------------------
     * UTILITY METHODS
     * ------------------------------------------------------------ */

    /**
     * Retrieve and generate a form_dropdown friendly array
     */
    function dropdown()
    {
        $args = func_get_args();

        if(count($args) == 2)
        {
            list($key, $value) = $args;
        }
        else
        {
            $key = $this->primary_key;
            $value = $args[0];
        }

        $this->trigger('before_dropdown', array( $key, $value ));

        $this->apply_soft_delete_filter();

        $result = $this->_database->select(array($key, $value))
            ->get($this->_table)
            ->result();

        $options = array();

        foreach ($result as $row)
        {
            $options[$row->{$key}] = $row->{$value};
        }

        $options = $this->trigger('after_dropdown', $options);

        return $options;
    }

    /*---------------------------
     *Event Callback functions
     *---------------------------*/
    protected function subscribe($event, $observer, $handler = FALSE)
    {
        if (!isset($this->event_listeners[$event])) {
            $this->event_listeners[$event] = array();
        }

        if (is_string($handler) || (is_string($observer) && !$handler)) {
            $handler = !$handler ? $observer : $handler;
            $this->event_listeners[$event][$handler] = $observer;
            return $this;
        }

        $strategy = $handler ? 'array_unshift' : 'array_push';

        $strategy($this->event_listeners[$event], $observer);

        return $this;
    }

    /**
     * @param mixed $database
     *
     * @return $this
     */
    public function set_database($database = null)
    {
        switch (true) {
            case ($database === null) :
                $this->_database = $this->db;
                break;
            case is_string($database) :
                $this->_database = $this->_load_database_by_group($database);
                break;
            case ($database instanceof CI_DB_driver):
                $this->_database = $database;
                break;
            default :
                $this->_show_error('You have specified an invalid database connection/group.');
        }

        return $this;
    }

    private function _load_database_by_group($group)
    {
        if (!isset(self::$_connection_cache[$group])) {
            self::$_connection_cache[$group] = $this->load->database($group);
        }

        return self::$_connection_cache[$group];
    }

    protected function unsubscribe($event, $handler)
    {
        if (!isset($this->event_listeners[$event][$handler])) {
            unset($this->event_listeners[$event][$handler]);
        }

        return $this;
    }

    protected function is_subscribed($event, $handler)
    {
        return isset($this->event_listeners[$event][$handler]);
    }

    /**
     * Fetch a count of rows based on an arbitrary WHERE call.
     */
    public function count_by()
    {
        $where = func_get_args();
        $this->_set_where($where);
        $this->apply_soft_delete_filter();

        return $this->_database->count_all_results($this->_table);
    }

    /**
     * Fetch a total count of rows, disregarding any previous conditions
     */
    public function count_all()
    {
        $this->apply_soft_delete_filter();

        return $this->_database->count_all($this->_table);
    }

    /**
     * Tell the class to skip the insert validation
     */
    public function skip_validation()
    {
        $this->skip_validation = TRUE;

        return $this;
    }

    /**
     * Tell the class to skip the insert validation
     */
    public function enable_validation()
    {
        $this->skip_validation = FALSE;

        return $this;
    }

    /**
     * Return the next auto increment of the table. Only tested on MySQL.
     */
    public function get_next_id()
    {
        return (int) $this->_database->select('AUTO_INCREMENT')
            ->from('information_schema.TABLES')
            ->where('TABLE_NAME', $this->_database->dbprefix($this->get_table()))
            ->where('TABLE_SCHEMA', $this->_database->database)->get()->row()->AUTO_INCREMENT;
    }

    /**
     * Getter for the table name
     */
    public function get_table()
    {
        return $this->_table;
    }

    /**
     * Getter for the primary key
     */
    public function primary_key()
    {
        return $this->primary_key;
    }

    /**
     * @param $sql
     *
     * @return mixed
     */
    public function execute_query($sql)
    {
        return $this->_database->query($sql);
    }

    /**
     * @return mixed
     */
    public function get_last_query()
    {
        return $this->_database->last_query();
    }

    /**
     * Alias for db->insert_string()
     * @param $data
     *
     * @return mixed
     */
    public function get_insert_string($data)
    {
        return $this->_database->insert_string($this->get_table(), $data);
    }

    /**
     * @return int
     */
    public function get_num_rows()
    {
        return $this->num_rows;
    }

    /**
     * @return null|int
     */
    public function get_insert_id()
    {
        return $this->_database->insert_id();
    }

    /**
     * @return null|mix
     */
    public function get_affected_rows()
    {
        return $this->_database->affected_rows();
    }


    /* --------------------------------------------------------------
     * GLOBAL SCOPES
     * ------------------------------------------------------------ */

    /**
     * Return the next call as an array rather than an object
     */
    public function as_array()
    {
        $this->_temporary_return_type = 'array';
        return $this;
    }

    /**
     * Return the next call as an object rather than an array
     */
    public function as_object()
    {
        $this->_temporary_return_type = 'object';
        return $this;
    }

    /**
     * Don't care about soft deleted rows on the next call
     */
    public function with_deleted()
    {
        $this->_temporary_with_deleted = TRUE;
        return $this;
    }

    /**
     * Only get deleted rows on the next call
     */
    public function only_deleted()
    {
        $this->_temporary_only_deleted = TRUE;
        return $this;
    }

    /* --------------------------------------------------------------
     * OBSERVERS
     * ------------------------------------------------------------ */

    /**
     * MySQL DATETIME created_at and updated_at
     */
    public function created_at($row)
    {
        if (is_object($row))
        {
            $row->{$this->created_at_key} = date('Y-m-d H:i:s');
        }
        else
        {
            $row[$this->created_at_key] = date('Y-m-d H:i:s');
        }

        return $row;
    }

    public function updated_at($row)
    {
        if (is_object($row))
        {
            $row->{$this->updated_at_key} = date('Y-m-d H:i:s');
        }
        else
        {
            $row[$this->updated_at_key] = date('Y-m-d H:i:s');
        }

        return $row;
    }


    public function created_by($row)
    {
        if (is_object($row))
        {
            $row->{$this->created_by_key} = $this->get_current_user();
        }
        else
        {
            $row[$this->created_by_key] = $this->get_current_user();
        }

        return $row;
    }

    public function updated_by($row)
    {
        if (is_object($row))
        {
            $row->{$this->updated_by_key} = $this->get_current_user();
        }
        else
        {
            $row[$this->updated_by_key] = $this->get_current_user();
        }

        return $row;
    }

    public function update_deleted_by($id)
    {
        $this->_database->set($this->deleted_by_key, $this->get_current_user());
        return $id;
    }

    /**
     * Serialises data for you automatically, allowing you to pass
     * through objects and let it handle the serialisation in the background
     */
    public function serialize_row($row)
    {
        foreach ($this->callback_parameters as $column)
        {
            $row[$column] = serialize($row[$column]);
        }

        return $row;
    }

    public function unserialize_row($row)
    {
        foreach ($this->callback_parameters as $column)
        {
            if (is_array($row))
            {
                $row[$column] = unserialize($row[$column]);
            }
            else
            {
                $row->$column = unserialize($row->$column);
            }
        }

        return $row;
    }

    /**
     * Protect attributes by removing them from $row array
     */
    public function protect_attributes($row)
    {
        foreach ($this->protected_attributes as $attr)
        {
            if (is_object($row))
            {
                unset($row->$attr);
            }
            else
            {
                unset($row[$attr]);
            }
        }

        return $row;
    }

    /* --------------------------------------------------------------
     * QUERY BUILDER DIRECT ACCESS METHODS
     * ------------------------------------------------------------ */

    /**
     * A wrapper to $this->_database->order_by()
     *
     * call the ci->db->order_by method as per provided param
     * The param can be string just like default order_by function expect
     * or can be array with set of param!!
     * <pre>
     * $model->order_by('fieldName DESC');
     * or
     * $model->order_by(array('fieldName','DESC'));
     * or
     * $model->order_by(array('fieldName'=>'DESC', 'fieldName2'=>'ASC'));
     * or
     * $model->order_by(array(array('fieldName','DESC'),'fieldName DESC'));
     * </pre>
     *
     * @param $criteria
     * @param string $order
     * @internal param mixed $orders
     *
     * @return bool
     */
    public function order_by($criteria, $order = null)
    {

        if ($criteria == NULL) {
            return $this;
        }

        if (is_array($criteria)) { //Multiple order by provided!
            //check if we got single order by passed as array!!
            if (isset($criteria[1]) && (strtolower($criteria[1]) == 'asc' || strtolower($criteria[1]) == 'desc' || strtolower($criteria[1]) == 'random')) {
                $this->_database->order_by($criteria[0], $criteria[1]);
                return $this;
            }
            foreach ($criteria as $key => $value)
            {
                if(is_array($value)){
                    $this->order_by($value);
                }else{
                    $order_criteria = is_int($key) ? $value : $key;
                    $lower_key = strtolower($value);
                    $order = ($lower_key == 'asc' || $lower_key == 'desc' || $lower_key == 'random') ? $value : null;
                    $this->_database->order_by($order_criteria, $order);
                }
            }

            return $this;
        }

        $this->_database->order_by($criteria, $order); //its a string just call db order_by

        return $this;
    }

    /**
     * A wrapper to $this->_database->limit()
     */
    public function limit($limit, $offset = 0)
    {
        $this->_database->limit($limit, $offset);
        return $this;
    }

    /**
     * @param null|string|array $conditions
     * @param string            $fields
     * @param null|string|array $order
     * @param int               $start
     * @param null|int          $limit
     *
     * @return mixed
     */
    public function find_all($conditions = NULL, $fields = '*', $order = NULL, $start = 0, $limit = NULL)
    {
        if ($conditions != NULL) {
            if (is_array($conditions)) {
                $this->_database->where($conditions);
            } else {
                $this->_database->where($conditions, NULL, FALSE);
            }
        }

        if ($fields != NULL) {
            $this->_database->select($fields);
        }

        if ($order != NULL) {
            $this->order_by($order);
        }

        if ($limit != NULL) {
            $this->_database->limit($limit, $start);
        }

        return $this->get_all();
    }


    /**
     * @param null|string|array $conditions
     * @param string            $fields
     * @param null|string|array $order
     *
     * @return bool
     */
    public function find($conditions = NULL, $fields = '*', $order = NULL)
    {
        $data = $this->find_all($conditions, $fields, $order, 0, 1);

        if ($data) {
            return $data[0];
        } else {
            return FALSE;
        }
    }

    /**
     * @param null   $conditions
     * @param        $name
     * @param string $fields
     * @param null   $order
     *
     * @return bool
     */
    public function field($conditions = NULL, $name, $fields = '*', $order = NULL)
    {
        $data = $this->find_all($conditions, $fields, $order, 0, 1);

        if ($data) {
            $row = $data[0];
            if (isset($row[$name])) {
                return $row[$name];
            }
        }

        return FALSE;
    }

    /**
     * Alias of count_by
     * @param null $conditions
     *
     * @return integer
     */
    public function find_count($conditions = NULL)
    {
        return $this->count_by($conditions);
    }

    /* --------------------------------------------------------------
     * INTERNAL METHODS
     * ------------------------------------------------------------ */


    /**
     * Trigger an event and call its observers. Pass through the event name
     * (which looks for an instance variable $this->event_listeners[event_name] or $this->event_name), an array of
     * parameters to pass through and an optional 'last in iteration' boolean
     *
     * @param $event
     * @param bool|array|void|int $data
     * @param bool $last
     * @return bool|mixed
     */
    public function trigger($event, $data = FALSE, $last = TRUE)
    {
        if (isset($this->event_listeners[$event]) && is_array($this->event_listeners[$event])) {
            $data = $this->_trigger_event($this->event_listeners[$event], $data, $last);
        }elseif (isset($this->$event) && is_array($this->$event)){
            $data = $this->_trigger_event($this->$event, $data, $last);
        }

        return $data;
    }

    private function _trigger_event($event_listeners, $data, $last)
    {
        foreach ($event_listeners as $method) {
            if (is_string($method) && strpos($method, '(')) {
                preg_match('/([a-zA-Z0-9\_\-]+)(\(([a-zA-Z0-9\_\-\., ]+)\))?/', $method, $matches);

                $method                    = $matches[1];
                $this->callback_parameters = explode(',', $matches[3]);
            }

            $callable = $this->getCallableFunction($method);

            if (!$callable) {
                $this->callback_parameters = array();
                continue;
            }

            $data = call_user_func_array($callable, array($data, $last));
        }

        return $data;
    }


    /**
     * Get callable as per given method
     *
     * @param $method
     * @return array|bool|callable
     */
    private function getCallableFunction($method)
    {
        if (is_callable($method)) {
            return $method;
        }

        if (is_string($method) && is_callable(array($this, $method))) {
            return array($this, $method);
        }

        return FALSE;
    }

    /**
     * filter data as per delete status
     */
    protected function apply_soft_delete_filter()
    {
        if ($this->soft_delete && $this->_temporary_with_deleted !== TRUE) {
            if($this->_temporary_only_deleted)
            {
                $where = "`{$this->deleted_at_key}` <= NOW()";
            }
            else
            {
                $where = sprintf('(%1$s > NOW() OR %1$s IS NULL OR %1$s = \'0000-00-00 00:00:00\')', $this->deleted_at_key);
            }

            $this->_database->where($where);
        }
    }


    /**
     * Run validation on the passed data
     */
    public function validate($data)
    {
        if($this->skip_validation)
        {
            return $data;
        }

        if(!empty($this->validate))
        {
            foreach($data as $key => $val)
            {
                $_POST[$key] = $val;
            }

            $this->load->library('form_validation');

            if(is_array($this->validate))
            {
                $this->form_validation->set_rules($this->validate);

                if ($this->form_validation->run() === TRUE)
                {
                    return $data;
                }
                else
                {
                    return FALSE;
                }
            }
            else
            {
                if ($this->form_validation->run($this->validate) === TRUE)
                {
                    return $data;
                }
                else
                {
                    return FALSE;
                }
            }
        }
        else
        {
            return $data;
        }
    }

    /**
     * Guess the table name by pluralising the model name
     */
    private function _fetch_table()
    {
        if ($this->_table == NULL)
        {
            $this->_table = plural(preg_replace('/(_m|_model)?$/', '', strtolower(get_class($this))));
        }
    }

    /**
     * Guess the primary key for current table
     */
    protected function _fetch_primary_key()
    {
        if($this->is_base_model_instance()) {
            return;
        }

        if ($this->primary_key == NULL && $this->_database) {
            $this->primary_key = $this->execute_query("SHOW KEYS FROM `" . $this->_database->dbprefix($this->_table) . "` WHERE Key_name = 'PRIMARY'")->row()->Column_name;
        }
    }

    private function is_base_model_instance()
    {
        if($this->_base_model_instance == null){
            $subclass_prefix = $this->config->item('subclass_prefix');
            $this->_base_model_instance = get_class($this) == $subclass_prefix . "Model";
        }

        return $this->_base_model_instance;
    }

    public function get_fields()
    {
        if (empty($this->_fields) && $this->_database) {
            $this->_fields = (array)$this->_database->list_fields($this->_table);
        }

        return $this->_fields;
    }

    protected function isFieldExist($field)
    {
        return in_array($field, $this->get_fields());
    }

    protected function _guess_is_soft_deletable()
    {
        if ($this->soft_delete === NULL) {
            $this->soft_delete = $this->isFieldExist($this->deleted_at_key);
        }

        if (!$this->soft_delete) {
            return;
        }

        if ($this->isFieldExist($this->deleted_by_key) && $this->get_current_user()) {
            $this->subscribe('before_delete', 'update_deleted_by','update_deleted_by');
        }
    }


    protected function _guess_is_blamable()
    {
        if ($this->blamable === NULL) {
            $this->blamable = $this->isFieldExist($this->created_by_key)
                && $this->isFieldExist($this->updated_by_key)
                && $this->get_current_user();
        }

        if ($this->timestampable) {
            $this->subscribe('before_create', 'created_by');
            $this->subscribe('before_create', 'updated_by');
            $this->subscribe('before_update', 'updated_by');
        }
    }

    protected function _guess_is_timestampable()
    {
        if ($this->timestampable === NULL) {
            $this->timestampable = $this->isFieldExist($this->created_at_key)
                && $this->isFieldExist($this->updated_at_key);
        }

        if ($this->timestampable) {
            $this->subscribe('before_create', 'created_at');
            $this->subscribe('before_create', 'updated_at');
            $this->subscribe('before_update', 'updated_at');
        }
    }

    /**
     * Set WHERE parameters, cleverly
     * @param $params
     */
    protected function _set_where($params)
    {
        if (count($params) == 1)
        {
            $this->_database->where($params[0]);
        }
        else if(count($params) == 2)
        {
            $this->_database->where($params[0], $params[1]);
        }
        else if(count($params) == 3)
        {
            $this->_database->where($params[0], $params[1], $params[2]);
        }
        else
        {
            $this->_database->where($params);
        }
    }

    /**
     * Return the method name for the current return type
     * @param bool $multi
     * @return string
     */
    protected function _get_return_type_method($multi = FALSE)
    {
        $method = ($multi) ? 'result' : 'row';
        return $this->_temporary_return_type == 'array' ? $method . '_array' : $method;
    }


    /**
     * you Must implement this function in MY_Model Class to use blamable an deleted_by feature
     */
    protected function get_current_user()
    {
        return false;
    }

    /**
     * @param $data
     * @return mixed
     */
    private function _update($data)
    {
        $result = $this->_database->set($data)
            ->update($this->_table);
        $this->trigger('after_update', array($data, $result));

        return $result;
    }

    /**
     * @param $msg
     */
    private function _show_error($msg)
    {
        if (function_exists('show_error')) {
            show_error($msg);
        }
    }

    /**
     * @param $data
     * @return bool|mixed
     */
    private function _do_pre_update($data)
    {
        $data = $this->validate($data);
        $data = $this->trigger('before_update', $data);

        return $data;
    }

    /**
     * @param $data
     * @return bool|mixed
     */
    private function _do_pre_create($data)
    {
        $data = $this->validate($data);
        $data = $this->trigger('before_create', $data);

        return $data;
    }

    /**
     * @param $methodName
     * @return null
     */
    private function _handle_exception($methodName)
    {
        if (!function_exists('_exception_handler')) {
            return null;
        }

        $trace = debug_backtrace(null, 1);
        $errMsg = 'Undefined method : ' . get_class($this) . "::" . $methodName . " called";
        _exception_handler(E_USER_NOTICE, $errMsg, $trace[0]['file'], $trace[0]['line']);
    }
}