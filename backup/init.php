<?php
// init.php
require_once __DIR__.'/db.php';
date_default_timezone_set('Asia/Colombo');
// Roles
const ROLE_ADMIN = 'admin';
const ROLE_MANAGER = 'manager';
const ROLE_ACCOUNTANT = 'accountant';
const ROLE_FIELD = 'field_officer';
const ROLE_TEAM = 'collector';

function is_logged_in() {
    return !empty($_SESSION['user']);
}
function require_login(){
    if(!is_logged_in()){
        header('Location: '.BASE_URL.'/login.php'); exit;
    }
}
function has_role($role){
    if(!is_logged_in()) return false;
    return in_array($role, explode(',', $_SESSION['user']['roles']));
}
