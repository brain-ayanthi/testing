<?php
// auth/auth.php
require_once __DIR__.'/../db.php';

function user_by_email($email){
    global $pdo;
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = :e LIMIT 1');
    $stmt->execute([':e'=>$email]);
    return $stmt->fetch();
}
function verify_password($password, $hash){
    return password_verify($password, $hash);
}

// seed admin if not exists (run when including auth)
$admin = user_by_email('admin@teafactory.local');
if(!$admin){
    $pw = password_hash('Admin@123', PASSWORD_DEFAULT);
    $stmt = $pdo->prepare('INSERT INTO users (name,email,password,roles,created_at) VALUES (:n,:e,:p,:r,NOW())');
    $stmt->execute([':n'=>'Administrator',':e'=>'admin@teafactory.local',':p'=>$pw,':r'=>ROLE_ADMIN]);
}
