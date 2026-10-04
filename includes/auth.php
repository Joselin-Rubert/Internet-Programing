<?php
/* =============================================================
   includes/auth.php
   Login checks for both the user area and the admin area.
   Include this AFTER config/db.php.
   ============================================================= */

/** Is somebody logged in as a normal user? */
function is_user_logged_in()
{
    return isset($_SESSION['user_id']);
}

/** Is somebody logged in as an admin? */
function is_admin_logged_in()
{
    return isset($_SESSION['admin_id']);
}

/** Send guests to the user login page. */
function require_user()
{
    if (!is_user_logged_in()) {
        flash_set('error', 'Please login to continue.');
        $back = isset($_SERVER['REQUEST_URI']) ? $_SERVER['REQUEST_URI'] : '';
        redirect(url('user/login.php?next=' . urlencode($back)));
    }
}

/** Send guests to the admin login page. */
function require_admin()
{
    if (!is_admin_logged_in()) {
        flash_set('error', 'Admin login required.');
        redirect(url('admin/login.php'));
    }
}

/** Fetch the logged-in user's row (or null). */
function current_user($pdo)
{
    if (!is_user_logged_in()) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT * FROM users WHERE id = ?");
    $stmt->execute(array($_SESSION['user_id']));
    return $stmt->fetch() ?: null;
}

/** Fetch the logged-in admin's row (or null). */
function current_admin($pdo)
{
    if (!is_admin_logged_in()) {
        return null;
    }
    $stmt = $pdo->prepare("SELECT * FROM admins WHERE id = ?");
    $stmt->execute(array($_SESSION['admin_id']));
    return $stmt->fetch() ?: null;
}
