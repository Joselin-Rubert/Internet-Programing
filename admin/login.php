<?php
/* =============================================================
   admin/login.php  -  Admin login (separate from user login)
   Default: admin / admin123
   ============================================================= */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (is_admin_logged_in()) {
    redirect(url('admin/index.php'));
}

$error  = '';
$username = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';

    if ($username === '' || $password === '') {
        $error = 'Please enter both username and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM admins WHERE username = ?");
        $stmt->execute(array($username));
        $admin = $stmt->fetch();

        if ($admin && password_verify($password, $admin['password'])) {
            session_regenerate_id(true);
            $_SESSION['admin_id'] = (int) $admin['id'];
            flash_set('success', 'Welcome, ' . ($admin['full_name'] ?: $admin['username']));
            redirect(url('admin/index.php'));
        }

        $error = 'Invalid admin username or password.';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Admin Login &middot; <?php echo e(SITE_NAME); ?></title>
<link rel="stylesheet" href="<?php echo url('assets/css/style.css'); ?>">
</head>
<body>

<div class="auth-wrap">
  <div class="auth-card">
    <div class="center" style="margin-bottom:1.4rem">
      <span class="brand-mark" style="width:52px;height:52px;font-size:1.2rem;display:inline-grid">CV</span>
      <h1 style="margin-top:.8rem">Admin Login</h1>
      <p class="sub"><?php echo e(SITE_NAME); ?> administration panel</p>
    </div>

    <?php if ($error !== ''): ?>
      <div class="alert alert-error"><?php echo e($error); ?></div>
    <?php endif; ?>
    <?php flash_show(); ?>

    <form method="post" action="" data-validate-form novalidate>
      <div class="field">
        <label for="username">Username</label>
        <input type="text" id="username" name="username" value="<?php echo e($username); ?>"
               placeholder="admin" data-validate="required" required>
      </div>

      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" placeholder="Password"
               data-validate="required" required>
      </div>

      <button type="submit" class="btn btn-purple btn-block btn-lg">Login to Panel</button>
    </form>

    <div class="demo-box">
      <b>Default admin login</b><br>
      Username: <b>admin</b> &nbsp;|&nbsp; Password: <b>admin123</b>
    </div>

    <p class="foot-note"><a href="<?php echo url('user/index.php'); ?>">&larr; Back to the website</a></p>
  </div>
</div>

<script src="<?php echo url('assets/js/main.js'); ?>"></script>
</body>
</html>
