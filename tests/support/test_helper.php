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
 * test_helper.php is the bootstrap file for our tests - it loads up an 
 * appropriate faux-CodeIgniter environment for our tests to run in.
 */

require_once 'vendor/autoload.php';

require_once 'tests/support/database.php';


/**
 * Fake the CodeIgniter base model!
 */
class CI_Model
{
    public function __construct()
    {
        $this->load = new CI_Loader();

        // Pretend CI has a loaded DB already.
        $this->db = new MY_Model_Mock_DB();
        $this->config = new CI_Config();
    }

    public function __get($method) { }
}

/**
 * The loads happen in the constructor (before we can mock anything out),
 * so instead we'll fakeify the Loader
 */
class CI_Loader
{
    public function __call($method, $params = array()) {}
}

/**
 * ...but relationships load models, so fake that
 */
class MY_Model_Mock_Loader
{
    public function model($name, $assigned_name = '') { }
}

/**
 * We also need to fake the inflector
 */
function singular($name)
{
    return 'comment';
}

function plural($name)
{
    return 'records';
}

function camelize($str)
{
    $str = 'x'.strtolower(trim($str));
    $str = ucwords(preg_replace('/[\s_]+/', ' ', $str));
    return substr(str_replace(' ', '', $str), 1);
}

/**
 * Let our tests know about our callbacks
 */

class MY_Model_Test_Exception extends Exception
{
    public $passed_object = FALSE;

    public function __construct($passed_object, $message = '')
    {
        parent::__construct($message);
        $this->passed_object = $passed_object;
    }
}

class Callback_Test_Exception extends MY_Model_Test_Exception
{
    public function __construct($passed_object)
    {
        parent::__construct($passed_object, 'Callback is being successfully thrown');
    }
}

class CI_Config
{
    public function item()
    {
        return 'MY_';
    }
}
