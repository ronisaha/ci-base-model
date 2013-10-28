<?php
/**
 * An extension of CodeIgniter's base Model class to remove repetition and increase productivity by providing
 * a couple handy methods(powered by CI's query builder), validation-in-model support, event callbacks and more.
 *
 * @author  Jamie Rumbelow <http://jamierumbelow.net>
 * @author  Md Emran Hasan <phpfour@gmail.com>
 * @author  Roni Kumar Saha <roni.cse@gmail.com>
 * @version 2.0
 *
 * @link    http://github.com/jamierumbelow/codeigniter-base-model
 * @link    https://github.com/phpfour/MY_Model
 * @link    https://github.com/ronisaha/ci-base-model
 *
 */

/**
 * database.php is a fakeified CodeIgniter query builder
 */

class MY_Model_Mock_DB
{
    /**
     * CI_DB
     */
    public static $prefix = '';
    public function select() { }
    public function where() { }
    public function where_in() { }
    public function get() { }
    public function from() { }
    public function insert() { }
    public function insert_id() { }
    public function set() { }
    public function update() { }
    public function delete() { }
    public function order_by() { }
    public function limit() { }
    public function count_all_results() { }
    public function count_all() { }
    public function truncate() { }
    public function query() { }
    public function num_rows() { }
    public function affected_rows() { }
    public function insert_batch() { }
    public function update_batch() { }
    public function dbprefix($t = '') { return self::$prefix . $t; }
    public function escape($v) {return $v; }
    public function list_fields() {return array(); }
    public function last_query() {return 'the_last_query'; }
    public function insert_string() {return 'the_insert_string'; }

    /**
     * CI_DB_Result
     */
    public function row() { }
    public function result() { }
    public function row_array() { }
    public function result_array() { }
}