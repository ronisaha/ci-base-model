<?php

include_once "CI_Base_Model.php";

class MY_Model extends CI_Base_Model
{
    protected function get_current_user()
    {
        return $this->session->userdata('user_id');
    }
}