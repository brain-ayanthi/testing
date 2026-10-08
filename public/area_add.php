<?php
require_once __DIR__.'/../init.php'; require_login();
$err=''; if($_SERVER['REQUEST_METHOD']==='POST'){ $name=trim($_POST['name']); $code=trim($_POST['code']); if($name=='') $err='Name required'; if(!$err){ $stmt=$pdo->prepare('INSERT INTO areas (name,code,created_at) VALUES(:n,:c,NOW())'); $stmt->execute([':n'=>$name,':c'=>$code]); header('Location: areas.php'); exit; } }
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Add Area</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body><?php include 'partials/topnav.php'; ?>
<div class="container mt-3"><h4>Add Area</h4><?php if($err) echo '<div class="alert alert-danger">'.htmlspecialchars($err).'</div>'; ?>
<form method="post"><div class="row"><div class="col-md-6 mb-2"><label>Name</label><input name="name" class="form-control"></div><div class="col-md-6 mb-2"><label>Code</label><input name="code" class="form-control"></div></div><button class="btn btn-primary">Save</button></form>
</div></body></html>
