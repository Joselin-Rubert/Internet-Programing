<?php
/* =============================================================
   admin/logout.php
   ============================================================= */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

unset($_SESSION['admin_id']);
session_regenerate_id(true);

flash_set('success', 'Admin logged out.');
redirect(url('admin/login.php'));
