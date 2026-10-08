<?php
require_once __DIR__.'/../init.php';
require_once __DIR__.'/../auth/auth.php';

$err='';
if($_SERVER['REQUEST_METHOD']==='POST'){
    $email = trim($_POST['email']);
    $pass = $_POST['password'];
    $u = user_by_email($email);
    if($u && verify_password($pass,$u['password'])){
        // set session user
        $_SESSION['user'] = [
            'id'=>$u['id'],'name'=>$u['name'],'email'=>$u['email'],'roles'=>$u['roles']
        ];
        header('Location: '.BASE_URL.'/dashboard.php'); exit;
    }else{
        $err = 'Invalid credentials';
    }
}
?><!doctype html>
<html lang="en">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>TeaFactory - Login</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/css/style.css" rel="stylesheet">
</head>
<body class="bg-light">
<div class="container py-5">
  <div class="row justify-content-center">
    <div class="col-md-5">
      <div class="card shadow-sm">
        <div class="card-body">
          <h4 class="mb-3">TeaFactory Login</h4>
          <?php if($err): ?><div class="alert alert-danger"><?php echo htmlspecialchars($err); ?></div><?php endif; ?>
          <form method="post">
            <div class="mb-2"><label class="form-label">Email</label><input name="email" required class="form-control" type="email" /></div>
            <div class="mb-3"><label class="form-label">Password</label><input name="password" required class="form-control" type="password" /></div>
            <button class="btn btn-primary w-100">Login</button>
          </form>
        </div>
      </div>
      <p class="text-center text-muted mt-2">Sample admin: admin@teafactory.local / Admin@123</p>
    </div>
  </div>
</div>
</body>
</html>
