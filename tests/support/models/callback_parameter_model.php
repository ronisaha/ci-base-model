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
 * callback_parameter_model.php contains a test model that defines a callback
 * with embedded parameters
 */

class Callback_parameter_modelCI extends CI_MY_Model
{
	public $callback = array('some_callback(some_param,another_param)');

	public function some_method()
	{
		$this->trigger('callback');
	}

	protected function some_callback()
	{
		throw new Callback_Test_Exception($this->callback_parameters);
	}
}