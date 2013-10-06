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

class Blamable_modelCI extends CI_MY_Model
{
	protected $created_at_by = 'created_by';
    protected $updated_at_by = 'updated_by';
    protected $_table = 'records';

    public function __construct(){
        array_push($this->_fields, $this->created_by_key);
        array_push($this->_fields, $this->updated_by_key);
        parent::__construct();
    }
}