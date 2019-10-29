<?php
defined('BASEPATH') or exit('No direct script access allowed');

class Upload_mdl extends CI_Model
{

    public function __construct()
    {
        parent::__construct();
    }

    public function get(){
        return $this->db->from('tbl_upload')->get()->result();
    }

    public function insert($list)
    {
        $this->db->insert_batch('tbl_upload', $list);
    }
}