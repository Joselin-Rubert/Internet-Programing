<?php
/* =============================================================
   includes/admin_header.php
   Sidebar + topbar layout used by every admin page.
   Set $page_title before including this file.
   ============================================================= */
$page_title = isset($page_title) ? $page_title : 'Admin';
$admin_user = current_admin($pdo);

// Highlight the current menu item
$current_file = basename($_SERVER['SCRIPT_NAME']);
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title><?php echo e($page_title); ?> &middot; <?php echo e(SITE_NAME); ?> Admin</title>
<link rel="stylesheet" href="<?php echo url('assets/css/style.css'); ?>">
</head>
<body class="admin-body">

<aside class="admin-sidebar" id="adminSidebar">
  <a class="admin-brand" href="<?php echo url('admin/index.php'); ?>">
    <span class="brand-mark">CV</span>
    <span><?php echo e(SITE_NAME); ?><small>Admin Panel</small></span>
  </a>

  <nav class="admin-nav">
    <a class="<?php echo $current_file === 'index.php' ? 'active' : ''; ?>" href="<?php echo url('admin/index.php'); ?>">Dashboard</a>
    <a class="<?php echo in_array($current_file, array('movies.php','movie_form.php')) ? 'active' : ''; ?>" href="<?php echo url('admin/movies.php'); ?>">Movies</a>
    <a class="<?php echo in_array($current_file, array('theatres.php','theatre_form.php')) ? 'active' : ''; ?>" href="<?php echo url('admin/theatres.php'); ?>">Theatres</a>
    <a class="<?php echo in_array($current_file, array('shows.php','show_form.php')) ? 'active' : ''; ?>" href="<?php echo url('admin/shows.php'); ?>">Shows</a>
    <a class="<?php echo $current_file === 'users.php' ? 'active' : ''; ?>" href="<?php echo url('admin/users.php'); ?>">Users</a>
    <a class="<?php echo $current_file === 'bookings.php' ? 'active' : ''; ?>" href="<?php echo url('admin/bookings.php'); ?>">Bookings</a>
    <a class="logout" href="<?php echo url('admin/logout.php'); ?>">Logout</a>
  </nav>
</aside>

<div class="admin-main">
  <header class="admin-topbar">
    <button class="nav-toggle" id="navToggle" aria-label="Toggle menu">&#9776;</button>
    <h1><?php echo e($page_title); ?></h1>
    <div class="admin-user">
      <?php echo e($admin_user ? $admin_user['username'] : 'admin'); ?>
    </div>
  </header>

  <div class="admin-content">
    <?php flash_show(); ?>
