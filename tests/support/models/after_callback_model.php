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
 * after_callback_model.php contains a test model that defines every after callback as a function
 * that throws an exception. We can then catch that in the tests to ensure callbacks work.
 */

class After_callback_model extends CI_MY_Model
{
    protected $after_create = array('test_throw');
    protected $after_update = array('test_throw');
    protected $after_get = array('test_data_callback', 'test_data_callback_two');
    protected $after_delete = array('test_throw');

    protected function test_throw($row)
    {
    	throw new Callback_Test_Exception($row);
    }

    protected function test_data_callback($row)
    {
    	$row['key'] = 'Value';
    	return $row;
    }

    protected function test_data_callback_two($row)
    {
        $row['another_key'] = '123 Value';
        return $row;
    }
}