<?php
defined('BASEPATH') OR exit('No direct script access allowed');

$config = array(
    'protocol'  => getenv('MAIL_PROTOCOL') ?: 'mail',
    'smtp_host' => getenv('SMTP_HOST') ?: 'localhost',
    'smtp_port' => getenv('SMTP_PORT') ?: '25',
    'smtp_user' => getenv('SMTP_USER') ?: '',
    'smtp_pass' => getenv('SMTP_PASS') ?: '',
    'smtp_crypto' => getenv('SMTP_CRYPTO') ?: '',
    'mailtype'  => 'html',
    'charset'   => 'utf-8',
    'wordwrap'  => TRUE,
    'newline'   => "\r\n"
);