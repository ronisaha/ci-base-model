CI_Base_Model
=============

[![Build Status](https://travis-ci.org/ronisaha/ci-base-model.png?branch=master)](https://travis-ci.org/ronisaha/ci-base-model)

An extension of CodeIgniter's base Model class to remove repetition and increase productivity by providing a couple handy methods. Most of the functionality
of CI_Base_Model taken from following two library
* [http://github.com/jamierumbelow/codeigniter-base-model](http://github.com/jamierumbelow/codeigniter-base-model)
* [https://github.com/phpfour/MY_Model](https://github.com/phpfour/MY_Model)


Key Features
============
* Basic CRUD Functionality
* validation-in-model
* Simple Event Callback system
* Blamable
* Soft-Deletable
* Timestampable
* Easy to use
* Support both CamelCase and underscore version of a function (you can use findAll/find_all both will do the same). As codeigniter's convention the library implemented in underscore version of the functions


Synopsis
--------

```php
class Post_model extends MY_Model { }

$this->load->model('post_model', 'post');

$this->post->get_all();

$this->post->get(1);
$this->post->get_by('title', 'Pigs CAN Fly!');
$this->post->get_many_by('status', 'open');
//or $this->post->getManyBy('status', 'open');

$this->post->insert(array(
    'status' => 'open',
    'title' => "I'm too sexy for my shirt"
));

$this->post->update(1, array( 'status' => 'closed' ));

$this->post->delete(1);
```

Installation/Usage
------------------

Download and copy the MY\_Model.php and CI\_Base\_Model.php file into your _application/core_ folder. CodeIgniter will load and initialise this class automatically for you.

Extend your model classes from `MY_Model` and all the functionality will be baked in automatically. You may wondering why we use two file? Here are the benefits.
* You can implement all your global implementation in MY_Model.
* You can update CI_Base_Model any time. Your oun global implementation will not affected.

**Note:** The **MY\_** prefix is the default prefix used to extend a class in CodeIgniter. If you have modified this in your **_application/config/config.php**, use your prefix as appropriate. and modify the MY_Model class

Naming Conventions
------------------

This class will try to guess the name of the table to use, by finding the plural of the class name.

For instance:

    class Post_model extends MY_Model { }

...will guess a table name of `posts`. It also works with `_m`:

    class Book_m extends MY_Model { }

...will guess `books`.

If you need to set it to something else, you can declare the _$\_table_ instance variable and set it to the table name:

    class Post_model extends MY_Model
    {
        public $_table = 'blogposts';
    }

Some of the CRUD functions will try to guess your primary key ID column. You can overwrite this functionality by setting the _$primary\_key_ instance variable:

    class Post_model extends MY_Model
    {
        public $primary_key = 'post_id';
    }

Callbacks/Observers
-------------------

There are many times when you'll need to alter your model data before it's inserted or returned. This could be adding timestamps, pulling in relationships or deleting dependent rows. The MVC pattern states that these sorts of operations need to go in the model. In order to facilitate this, **CI_Base_Model** contains a series of callbacks/observers -- methods that will be called at certain points.

The default list of observers are as follows:

* $before_create
* $after_create
* $before_update
* $after_update
* $before_get
* $after_get
* $before_delete
* $after_delete

These are instance variables usually defined at the class level. They are arrays of methods on this class to be called at certain points. An example:

```php
class Book_model extends MY_Model
{
    public $before_create = array( 'timestamps' );

    protected function timestamps($book)
    {
        $book['created_at'] = $book['updated_at'] = date('Y-m-d H:i:s');
        return $book;
    }
}
```

**Remember to always always always return the `$row` object you're passed. Each observer overwrites its predecessor's data, sequentially, in the order the observers are defined.**

Observers can also take parameters in their name, much like CodeIgniter's Form Validation library. Parameters are then accessed in `$this->callback_parameters`:

    public $before_create = array( 'data_process(name)' );
    public $before_update = array( 'data_process(date)' );

    protected function data_process($row)
    {
        $row[$this->callback_parameters[0]] = $this->_process($row[$this->callback_parameters[0]]);

        return $row;
    }

Validation
----------

MY_Model uses CodeIgniter's built in form validation to validate data on insert.

You can enable validation by setting the `$validate` instance to the usual form validation library rules array:

    class User_model extends MY_Model
    {
        public $validate = array(
            array( 'field' => 'email',
                   'label' => 'email',
                   'rules' => 'required|valid_email|is_unique[users.email]' ),
            array( 'field' => 'password',
                   'label' => 'password',
                   'rules' => 'required' ),
            array( 'field' => 'password_confirmation',
                   'label' => 'confirm password',
                   'rules' => 'required|matches[password]' ),
        );
    }

Anything valid in the form validation library can be used here. To find out more about the rules array, please [view the library's documentation](http://codeigniter.com/user_guide/libraries/form_validation.html#validationrulesasarray).

With this array set, each call to `insert()` or `update()` will validate the data before allowing  the query to be run. **Unlike the CodeIgniter validation library, this won't validate the POST data, rather, it validates the data passed directly through.**

You can skip the validation with `skip_validation()`:

    $this->user_model->skip_validation();
    $this->user_model->insert(array( 'email' => 'blah' ));

Alternatively, pass through a `TRUE` to `insert()`:

    $this->user_model->insert(array( 'email' => 'blah' ), TRUE);

Under the hood, this calls `validate()`.

Protected Attributes
--------------------

If you're lazy like me, you'll be grabbing the data from the form and throwing it straight into the model. While some of the pitfalls of this can be avoided with validation, it's a very dangerous way of entering data; any attribute on the model (any column in the table) could be modified, including the ID.

To prevent this from happening, MY_Model supports protected attributes. These are columns of data that cannot be modified.

You can set protected attributes with the `$protected_attributes` array:

    class Post_model extends MY_Model
    {
        public $protected_attributes = array( 'id', 'hash' );
    }

Now, when `update` is called, the attributes will automatically be removed from the array, and, thus, protected:

    $this->post_model->update(1, array(
        'id' => 2,
        'hash' => 'aqe3fwrga23fw243fWE',
        'title' => 'A new post'
    ));

    // SQL: INSERT INTO posts (title) VALUES ('A new post')

Relationships
-------------

**MY\_Model** now has support for basic _belongs\_to_ and has\_many relationships. These relationships are easy to define:

    class Post_model extends MY_Model
    {
        public $belongs_to = array( 'author' );
        public $has_many = array( 'comments' );
    }

It will assume that a MY_Model API-compatible model with the singular relationship's name has been defined. By default, this will be `relationship_model`. The above example, for instance, would require two other models:

    class Author_model extends MY_Model { }
    class Comment_model extends MY_Model { }

If you'd like to customise this, you can pass through the model name as a parameter:

    class Post_model extends MY_Model
    {
        public $belongs_to = array( 'author' => array( 'model' => 'author_m' ) );
        public $has_many = array( 'comments' => array( 'model' => 'model_comments' ) );
    }

You can then access your related data using the `with()` method:

    $post = $this->post_model->with('author')
                             ->with('comments')
                             ->get(1);

The related data will be embedded in the returned value from `get`:

    echo $post->author->name;

    foreach ($post->comments as $comment)
    {
        echo $message;
    }

Separate queries will be run to select the data, so where performance is important, a separate JOIN and SELECT call is recommended.

The primary key can also be configured. For _belongs\_to_ calls, the related key is on the current object, not the foreign one. Pseudocode:

    SELECT * FROM authors WHERE id = $post->author_id

...and for a _has\_many_ call:

    SELECT * FROM comments WHERE post_id = $post->id

To change this, use the `primary_key` value when configuring:

    class Post_model extends MY_Model
    {
        public $belongs_to = array( 'author' => array( 'primary_key' => 'post_author_id' ) );
        public $has_many = array( 'comments' => array( 'primary_key' => 'parent_post_id' ) );
    }

Arrays vs Objects
-----------------

By default, MY_Model is setup to return objects using CodeIgniter's QB's `row()` and `result()` methods. If you'd like to use their array counterparts, there are a couple of ways of customising the model.

If you'd like all your calls to use the array methods, you can set the `$return_type` variable to `array`.

    class Book_model extends MY_Model
    {
        protected $return_type = 'array';
    }

If you'd like just your _next_ call to return a specific type, there are two scoping methods you can use:

    $this->book_model->as_array()
                     ->get(1);
    $this->book_model->as_object()
                     ->get_by('column', 'value');

Soft Delete
-----------

By default, the delete mechanism works with an SQL `DELETE` statement. However, you might not want to destroy the data, you might instead want to perform a 'soft delete'.

If you enable soft deleting, the deleted row will be marked as `deleted` rather than actually being removed from the database.

Take, for example, a `Book_model`:

    class Book_model extends MY_Model { }

We can enable soft delete by setting the `$this->deleted_at_key` key:

    class Book_model extends MY_Model
    {
        protected $deleted_at_key = 'deleted_at';
    }

By default, MY_Model expects a `Datetime` or `TIMESTAMP` column named `deleted_at`. If you'd like to customise this, you can set `$deleted_at_key`:

    class Book_model extends MY_Model
    {
        protected $deleted_at_key = 'book_deleted_at';
    }

If you wish to track the deleted by you can set `$deleted_by_key` member,

    class Book_model extends MY_Model
    {
        protected $deleted_at_key = 'book_deleted_at';
        protected $deleted_by_key = 'book_deleted_by';
    }

Now, when you make a call to any of the `get_` methods, a constraint will be added to not withdraw deleted columns:

    => $this->book_model->get_by('user_id', 1);
    -> SELECT * FROM books WHERE user_id = 1 AND deleted < NOW()

If you'd like to include deleted columns, you can use the `with_deleted()` scope:

    => $this->book_model->with_deleted()->get_by('user_id', 1);
    -> SELECT * FROM books WHERE user_id = 1

If you'd like to include only the columns that have been deleted, you can use the `only_deleted()` scope:

    => $this->book_model->only_deleted()->get_by('user_id', 1);
    -> SELECT * FROM books WHERE user_id = 1 AND deleted >= NOW()

You can delete in future!!

    => $this->book_model->delete_at(1, (new \DateTime())->modify('+1 day'));

Blamable
--------
Take, for example, a `Book_model`:

    class Book_model extends MY_Model { }

We can enable blamable by setting the `$this->created_by_key` abd `$this->updated_by_key` key. And you need to implement the get_current_user() function, in the MY_Model

    class Book_model extends MY_Model
    {
        protected $created_by_key = 'created_by';
        protected $updated_by_key = 'updated_by';
    }

    class MY_Model extends CI_Base_Model{
        protected $current_user_id_session_key = 'user_id';
    }

Now, when you make a call to any of the `insert`, `update`, `update_` methods the Model will automatically insert/update the created_by/updated_by entry

    => $this->book_model->insert(array('title' => 'A new book'));
    -> SQL: INSERT INTO books (title, updated_by) VALUES ('A new post', 1) //Assuming current user id is 1


Built-in Observers
-------------------

**CI_Base_Model** contains a few built-in observers. The timestamps (MySQL compatible) `created_at` and `updated_at` are now available as built-in observers:

    class Post_model extends MY_Model
    {
        public $before_create = array( 'created_at', 'updated_at' );
        public $before_update = array( 'updated_at' );
    }

**CI_Base_Model** also contains serialisation observers for serialising and unserializing native PHP objects. This allows you to pass complex structures like arrays and objects into rows and have it be serialised automatically in the background. Call the `serialize` and `unserialize` observers with the column name(s) as a parameter:

    class Event_model extends MY_Model
    {
        public $before_create = array( 'serialize_row(seat_types)' );
        public $before_update = array( 'serialize_row(seat_types)' );
        public $after_get = array( 'unserialize_row(seat_types)' );
    }

Database Connection
-------------------

The class will automatically use the default database connection, and even load it for you if you haven't yet.

You can specify a database connection on a per-model basis by declaring the _$\_db\_group_ instance variable. This is equivalent to calling `$this->db->database($this->_db_group, TRUE)`.

See ["Connecting to your Database"](http://ellislab.com/codeigniter/user-guide/database/connecting.html) for more information.

```php
class Post_model extends MY_Model
{
    public $_db_group = 'group_name';
}
```

Methods
=========

* find_by($field, $value, $fields, $order)  [alias findBy]
* find_by_{$field}($value, $fields, $order) [alias findBy{$field}]
* find_all_by($field, $value, $fields, $order, $start, $limit)  [alias findAllBy]
* find_all_by_{$field}($value, $fields, $order, $start, $limit) [alias findAllBy{$field}]
* find_field_by($field, $value, $fields = '*', $order = NULL)   [alias findFieldBy]
* find_field_by_{$field}($value, $fields = '*', $order = NULL)  [alias findFieldBy{$field}]
* find_all($conditions, $fields, $order, $start, $limit)        [alias findAll]
* find($conditions, $fields, $order)
* field($conditions, $name, $fields, $order)
* get($id)
* get_all()
* get_by()
* get_many(array $primary_values)
* get_many_by()
* find_count($conditions) [alias findCount]
* insert($data, $skip_validation = FALSE)
* insert_many($data, $skip_validation = FALSE, $insert_individual = false)
* update($primary_value, $data, $skip_validation = FALSE)
* update_many($primary_values, $data, $skip_validation = FALSE)
* update_by()
* update_all($data)
* update_batch($data, $where_key)
* on_duplicate_update($data, $update)
* delete($id)
* delete_by() //argument can be in any form supported by $this->db->where();
* delete_many(array $primary_values) [alias deleteMany]
* delete_at($id, $time) [alias deleteAt]
* delete_by_at($condition, $time) [alias deleteByAt]
* delete_many_at(array $primary_values, $time) [alias deleteManyAt]
* execute_query($query) [alias executeQuery]
* order_by($orders) [alias orderBy]
* dropdown() [can be called dropdown($name_field) or dropdown($key_field, $name_field)]
* subscribe($event, $observer, $handler_name)
* is_subscribed($event, $handler_name)


Unit Tests
----------

MY_Model contains a robust set of unit tests to ensure that the system works as planned.

Install the testing framework (PHPUnit) with Composer:

    $ curl -s https://getcomposer.org/installer | php
    $ php composer.phar install

You can then run the tests using the `vendor/bin/phpunit` binary and specify the tests file:

    $ vendor/bin/phpunit


Contributing to CI_Base_Model
------------------------

If you find a bug or want to add a feature to CI_Base_Model, great! In order to make it easier and quicker for me to verify and merge changes in, it would be amazing if you could follow these few basic steps:

1. Fork the project.
2. **Branch out into a new branch. `git checkout -b name_of_new_feature_or_bug`**
3. Make your feature addition or bug fix.
4. **Add tests for it. This is important so I don’t break it in a future version unintentionally.**
5. Commit.
6. Send me a pull request!
