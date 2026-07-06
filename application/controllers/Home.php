<?php
defined('BASEPATH') OR exit('No direct script access allowed');

class Home extends CI_Controller {

    public function index() {
        $frontendIndex = FCPATH . '../frontend/index.html';
        if (file_exists($frontendIndex)) {
            readfile($frontendIndex);
            exit;
        }
        show_404();
    }
}