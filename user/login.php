<?php
/* =============================================================
   user/login.php  -  User login
   ============================================================= */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (is_user_logged_in()) {
    redirect(url('user/index.php'));
}

$error = '';
$email = '';
$next  = isset($_GET['next']) ? $_GET['next'] : '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $email = trim($_POST['email'] ?? '');
    $pass  = $_POST['password'] ?? '';

    if ($email === '' || $pass === '') {
        $error = 'Please enter both your email and password.';
    } else {
        $stmt = $pdo->prepare("SELECT * FROM users WHERE email = ?");
        $stmt->execute(array($email));
        $user = $stmt->fetch();

        // password_verify compares the typed password with the stored hash
        if ($user && password_verify($pass, $user['password'])) {
            session_regenerate_id(true);          // safer session
            $_SESSION['user_id'] = (int) $user['id'];
            flash_set('success', 'Welcome back, ' . $user['full_name'] . '!');

            // Send them back to the page they were trying to reach
            $target = $_POST['next'] ?? $next;
            if ($target !== '' && strpos($target, '/') === 0) {
                redirect($target);
            }
            redirect(url('user/index.php'));
        }

        $error = 'Invalid email or password. Please try again.';
    }
}

$page_title = 'Login';
require __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrap">
  <div class="auth-card">
    <h1>Login to your account</h1>
    <p class="sub">Welcome back! Pick up where you left off.</p>

    <?php if ($error !== ''): ?>
      <div class="alert alert-error"><?php echo e($error); ?></div>
    <?php endif; ?>

    <form method="post" action="" data-validate-form novalidate>
      <input type="hidden" name="next" value="<?php echo e($next); ?>">

      <div class="field">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" value="<?php echo e($email); ?>"
               placeholder="you@example.com" data-validate="required" required>
      </div>

      <div class="field">
        <label for="password">Password</label>
        <input type="password" id="password" name="password" placeholder="Your password"
               data-validate="required" required>
      </div>

      <button type="submit" class="btn btn-gold btn-block btn-lg">Login</button>
    </form>

    <p class="foot-note">
      New here? <a href="<?php echo url('user/register.php'); ?>">Create an account</a>
    </p>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
