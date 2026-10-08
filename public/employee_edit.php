<?php
require_once __DIR__.'/../init.php'; require_login();
$id=intval($_GET['id']??0); if($id<=0) { header('Location: employees.php'); exit; }
$stmt=$pdo->prepare('SELECT * FROM users WHERE id=:id'); $stmt->execute([':id'=>$id]); $u=$stmt->fetch();
$err=''; if($_SERVER['REQUEST_METHOD']==='POST'){ $name=trim($_POST['name']); $email=trim($_POST['email']); $pw=trim($_POST['password']); $roles=trim($_POST['roles']); if($name==''||$email=='') $err='Name and email required'; if(!$err){ if($pw!=''){ $hash=password_hash($pw,PASSWORD_DEFAULT); $stmt=$pdo->prepare('UPDATE users SET name=:n,email=:e,password=:p,roles=:r WHERE id=:id'); $stmt->execute([':n'=>$name,':e'=>$email,':p'=>$hash,':r'=>$roles,':id'=>$id]); } else { $stmt=$pdo->prepare('UPDATE users SET name=:n,email=:e,roles=:r WHERE id=:id'); $stmt->execute([':n'=>$name,':e'=>$email,':r'=>$roles,':id'=>$id]); } header('Location: employees.php'); exit; } }
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Edit Employee</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body><?php include 'partials/topnav.php'; ?>
<div class="container mt-3"><h4>Edit Employee</h4><?php if($err) echo '<div class="alert alert-danger">'.htmlspecialchars($err).'</div>'; ?>
<form method="post"><div class="row"><div class="col-md-6 mb-2"><label>Name</label><input name="name" value="<?php echo htmlspecialchars($u['name']); ?>" class="form-control"></div><div class="col-md-6 mb-2"><label>Email</label><input name="email" value="<?php echo htmlspecialchars($u['email']); ?>" class="form-control" type="email"></div><div class="col-md-4 mb-2"><label>New Password (leave blank to keep)</label><input name="password" class="form-control" type="password"></div><div class="col-md-4 mb-2"><label>Roles (comma sep)</label><input name="roles" value="<?php echo htmlspecialchars($u['roles']); ?>" class="form-control"></div></div><button class="btn btn-primary">Save</button></form></div></body></html>
