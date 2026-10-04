<?php
/* =============================================================
   index.php  -  Just sends visitors to the home page.
   ============================================================= */
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

redirect(url('user/index.php'));
