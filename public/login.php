<?php
require_once __DIR__.'/../init.php';
require_once __DIR__.'/../auth/auth.php';

$err = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email']);
    $pass  = $_POST['password'];
    $u = user_by_email($email);
    if ($u && verify_password($pass, $u['password'])) {
        $_SESSION['user'] = [
            'id'    => $u['id'],
            'name'  => $u['name'],
            'email' => $u['email'],
            'roles' => $u['roles']
        ];
        header('Location: ' . BASE_URL . '/dashboard.php');
        exit;
    } else {
        $err = 'Invalid credentials';
    }
}
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width,initial-scale=1">
<title>BrainTeaLeaf Collections Management System - Login</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">

<style>
body {
  margin: 0;
  background: linear-gradient(135deg, rgba(13,110,253,0.15), rgba(255,255,255,0.9)), 
              url('https://cdn.pixabay.com/photo/2016/03/27/19/31/tea-1283247_1280.jpg');
  background-size: cover;
  background-position: center;
  background-repeat: no-repeat;
  background-blend-mode: lighten;
  font-family: "Poppins", sans-serif;
}

.card {
  border-radius: 15px;
  backdrop-filter: blur(8px);
  background: rgba(255,255,255,0.85);
  box-shadow: 0 4px 20px rgba(0,0,0,0.2);
}

h4 {
  color: #0d6efd;
  font-weight: 700;
}

.btn-primary {
  background-color: #0d6efd;
  border: none;
}

.btn-primary:hover {
  background-color: #0b5ed7;
}

.text-muted {
  color: #4a4a4a !important;
}

.form-control {
  border-radius: 8px;
  border: 1px solid #ccc;
}
</style>
</head>
<body>
<div class="container py-5">
  <div class="row justify-content-center " style="height:80vh;">
    <div class="col-md-5">
      <div class="card p-4 shadow-sm">
        <div class="card-body">
          <h4 class="mb-4 text-center">🍃 Brain TeaLeaf Collections System</h4>
          <?php if($err): ?>
            <div class="alert alert-danger text-center"><?php echo htmlspecialchars($err); ?></div>
          <?php endif; ?>
          <form method="post">
            <div class="mb-3">
              <label class="form-label fw-bold text-primary">Email</label>
              <input name="email" required class="form-control" type="email" placeholder="Enter your email" />
            </div>
            <div class="mb-4">
              <label class="form-label fw-bold text-primary">Password</label>
              <input name="password" required class="form-control" type="password" placeholder="Enter your password" />
            </div>
            <button class="btn btn-primary w-100 py-2 fw-bold">Login</button>
          </form>
        </div>
      </div>
      <p class="text-center text-muted mt-3">
        Sample admin: <strong>admin@teafactory.local</strong> / <strong>Admin@123</strong>
      </p>
    </div>
  </div>
</div>
</body>
</html>
