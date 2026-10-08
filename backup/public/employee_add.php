<?php
require_once __DIR__.'/../init.php'; require_login();
$err=''; if($_SERVER['REQUEST_METHOD']==='POST'){ $name=trim($_POST['name']); $email=trim($_POST['email']); $pw=trim($_POST['password']); $roles=trim($_POST['roles']); if($name==''||$email==''||$pw=='') $err='All fields required'; if(!$err){ $hash=password_hash($pw,PASSWORD_DEFAULT); $stmt=$pdo->prepare('INSERT INTO users (name,email,password,roles,created_at) VALUES(:n,:e,:p,:r,NOW())'); $stmt->execute([':n'=>$name,':e'=>$email,':p'=>$hash,':r'=>$roles]); header('Location: employees.php'); exit; } }
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Add Employee</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body><?php include 'partials/topnav.php'; ?>
<div class="container mt-3"><h4>Add Employee</h4><?php if($err) echo '<div class="alert alert-danger">'.htmlspecialchars($err).'</div>'; ?>
<form method="post"><div class="row"><div class="col-md-6 mb-2"><label>Name</label><input name="name" class="form-control"></div><div class="col-md-6 mb-2"><label>Email</label><input name="email" class="form-control" type="email"></div><div class="col-md-4 mb-2"><label>Password</label><input name="password" class="form-control" type="password"></div><div class="col-md-4 mb-2"><label>Roles (comma sep)</label><input name="roles" class="form-control" value="field_officer"></div></div><button class="btn btn-primary">Save</button></form></div></body></html>
