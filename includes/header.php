<?php
/* =============================================================
   includes/header.php
   Top navigation bar + opening <main> for every user page.
   Set $page_title before including this file.
   ============================================================= */
$page_title = isset($page_title) ? $page_title : SITE_NAME;
$body_attrs = isset($body_attrs) ? $body_attrs : '';
$nav_user   = current_user($pdo);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo e($page_title); ?> &middot; <?php echo e(SITE_NAME); ?></title>
<link rel="stylesheet" href="<?php echo url('assets/css/style.css'); ?>">
</head>
<body <?php echo $body_attrs; ?>>

<header class="site-header">
  <div class="container header-inner">

    <a class="brand" href="<?php echo url('user/index.php'); ?>">
      <span class="brand-mark">CV</span>
      <span class="brand-text"><?php echo e(SITE_NAME); ?></span>
    </a>

    <button class="nav-toggle" id="navToggle" aria-label="Toggle menu">&#9776;</button>

    <nav class="main-nav" id="mainNav">
      <a href="<?php echo url('user/index.php'); ?>">Home</a>
      <a href="<?php echo url('user/index.php'); ?>#now-showing">Now Showing</a>
      <a href="<?php echo url('user/index.php'); ?>#upcoming">Upcoming</a>

      <?php if (is_user_logged_in()): ?>
        <a href="<?php echo url('user/my_bookings.php'); ?>">My Bookings</a>
        <span class="nav-user">
          Hi, <?php echo e($nav_user ? $nav_user['full_name'] : 'Guest'); ?>
        </span>
        <a class="btn btn-ghost btn-sm" href="<?php echo url('user/logout.php'); ?>">Logout</a>
      <?php else: ?>
        <a class="btn btn-ghost btn-sm" href="<?php echo url('user/login.php'); ?>">Login</a>
        <a class="btn btn-gold btn-sm" href="<?php echo url('user/register.php'); ?>">Register</a>
      <?php endif; ?>

      <a class="nav-admin-link" href="<?php echo url('admin/login.php'); ?>">Admin</a>
    </nav>

  </div>
</header>

<main class="page">
<?php flash_show(); ?>
