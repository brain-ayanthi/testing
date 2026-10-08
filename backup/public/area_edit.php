<?php
require_once __DIR__.'/../init.php'; require_login();
$id=intval($_GET['id']??0); if($id<=0){ header('Location: areas.php'); exit; }
$stmt=$pdo->prepare('SELECT * FROM areas WHERE id=:id'); $stmt->execute([':id'=>$id]); $row=$stmt->fetch();
$err=''; if($_SERVER['REQUEST_METHOD']==='POST'){ $name=trim($_POST['name']); $code=trim($_POST['code']); if($name=='') $err='Name required'; if(!$err){ $stmt=$pdo->prepare('UPDATE areas SET name=:n,code=:c WHERE id=:id'); $stmt->execute([':n'=>$name,':c'=>$code,':id'=>$id]); header('Location: areas.php'); exit; } }
?>
<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Edit Area</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet"></head>
<body><?php include 'partials/topnav.php'; ?>
<div class="container mt-3"><h4>Edit Area</h4><?php if($err) echo '<div class="alert alert-danger">'.htmlspecialchars($err).'</div>'; ?>
<form method="post"><div class="row"><div class="col-md-6 mb-2"><label>Name</label><input name="name" class="form-control" value="<?php echo htmlspecialchars($row['name']); ?>"></div><div class="col-md-6 mb-2"><label>Code</label><input name="code" class="form-control" value="<?php echo htmlspecialchars($row['code']); ?>"></div></div><button class="btn btn-primary">Save</button></form>
</div></body></html>
