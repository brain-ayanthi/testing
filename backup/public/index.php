<?php
require_once __DIR__.'/../init.php';
if(is_logged_in()) header('Location: '.BASE_URL.'/dashboard.php'); else header('Location: '.BASE_URL.'/login.php');
exit;
