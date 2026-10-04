<?php
/* =============================================================
   user/register.php  -  Create a new account
   ============================================================= */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

if (is_user_logged_in()) {
    redirect(url('user/index.php'));
}

$errors = array();
$name   = '';
$email  = '';
$phone  = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $name  = trim($_POST['full_name'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');
    $pass  = $_POST['password'] ?? '';
    $pass2 = $_POST['confirm_password'] ?? '';

    // ---------- server side validation ----------
    if ($name === '') {
        $errors[] = 'Please enter your full name.';
    } elseif (strlen($name) < 3) {
        $errors[] = 'Your name must be at least 3 characters long.';
    }

    if ($email === '') {
        $errors[] = 'Please enter your email address.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'That email address does not look valid.';
    } else {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE email = ?");
        $stmt->execute(array($email));
        if ($stmt->fetch()) {
            $errors[] = 'An account with this email already exists. Try logging in.';
        }
    }

    if ($phone !== '' && !preg_match('/^[0-9]{10}$/', $phone)) {
        $errors[] = 'Phone number must be exactly 10 digits.';
    }

    if ($pass === '') {
        $errors[] = 'Please choose a password.';
    } elseif (strlen($pass) < 6) {
        $errors[] = 'Password must be at least 6 characters long.';
    }

    if ($pass !== $pass2) {
        $errors[] = 'The two passwords do not match.';
    }

    if (empty($_POST['agree'])) {
        $errors[] = 'Please accept the terms to continue.';
    }

    // ---------- insert with a hashed password ----------
    if (count($errors) === 0) {
        $stmt = $pdo->prepare(
            "INSERT INTO users (full_name, email, phone, password) VALUES (?, ?, ?, ?)"
        );
        $stmt->execute(array($name, $email, $phone, password_hash($pass, PASSWORD_DEFAULT)));

        // Log the new user straight in
        $_SESSION['user_id'] = (int) $pdo->lastInsertId();
        $_SESSION['just_registered'] = true;   // home page says "Welcome", not "Welcome back"
        flash_set('success', 'Welcome to ' . SITE_NAME . ', ' . $name . '! Your account is ready.');
        redirect(url('user/index.php'));
    }
}

$page_title = 'Register';
require __DIR__ . '/../includes/header.php';
?>

<div class="auth-wrap">
  <div class="auth-card">
    <h1>Create your account</h1>
    <p class="sub">It takes less than a minute to get started.</p>

    <?php if ($errors): ?>
      <div class="alert alert-error">
        <?php foreach ($errors as $err): ?>
          <div><?php echo e($err); ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="post" action="" data-validate-form novalidate>

      <div class="field">
        <label for="full_name">Full Name</label>
        <input type="text" id="full_name" name="full_name" value="<?php echo e($name); ?>"
               placeholder="e.g. Rahul Sharma" data-validate="required" required>
      </div>

      <div class="field">
        <label for="email">Email Address</label>
        <input type="email" id="email" name="email" value="<?php echo e($email); ?>"
               placeholder="you@example.com" data-validate="required" required>
      </div>

      <div class="field">
        <label for="phone">Phone Number <span class="hint">(optional)</span></label>
        <input type="tel" id="phone" name="phone" value="<?php echo e($phone); ?>"
               placeholder="10 digit mobile number" maxlength="10">
        <p class="hint">Used only for booking updates.</p>
      </div>

      <div class="form-row">
        <div class="field">
          <label for="password">Password</label>
          <input type="password" id="password" name="password" placeholder="Minimum 6 characters"
                 data-validate="required" required>
        </div>
        <div class="field">
          <label for="confirm_password">Confirm Password</label>
          <input type="password" id="confirm_password" name="confirm_password" placeholder="Type it again"
                 data-validate="required" required>
        </div>
      </div>

      <div class="field">
        <label class="checkbox">
          <input type="checkbox" name="agree" value="1" <?php echo !empty($_POST['agree']) ? 'checked' : ''; ?>>
          <span>I agree to the terms and conditions</span>
        </label>
      </div>

      <button type="submit" class="btn btn-gold btn-block btn-lg">Create Account</button>
    </form>

    <p class="foot-note">
      Already registered? <a href="<?php echo url('user/login.php'); ?>">Login here</a>
    </p>
  </div>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
