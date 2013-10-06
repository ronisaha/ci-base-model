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

class Soft_delete_modelCI extends CI_MY_Model
{
	protected $deleted_at_key = 'deleted_at';

    public function __construct($deleted_at = 'deleted_at'){
        $this->deleted_at_key = $deleted_at;
        array_push($this->_fields, $deleted_at);

        parent::__construct();
    }
}