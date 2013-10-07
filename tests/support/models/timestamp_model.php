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

class Timestamp_model extends CI_MY_Model
{
	protected $created_at_key = 'created_at';
	protected $updated_at_key = 'updated_at';

    public function __construct(){
        array_push($this->_fields, $this->created_at_key);
        array_push($this->_fields, $this->updated_at_key);
        parent::__construct();
    }
}