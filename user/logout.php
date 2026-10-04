<?php
/* =============================================================
   user/logout.php  -  End the user session
   ============================================================= */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

// Remove only the user key, then send them back to the login page
unset($_SESSION['user_id']);
session_regenerate_id(true);

flash_set('success', 'You have been logged out successfully.');
redirect(url('user/login.php'));
