<?php
/* =============================================================
   config/db.php
   Database connection + a few global settings.
   Every page in this project starts by including this file.
   ============================================================= */

// ---------- Database settings (change these to match your XAMPP) ----------
define('DB_HOST', '127.0.0.1');
define('DB_NAME', 'mv_verify');
define('DB_USER', 'root');       // default XAMPP MySQL user
define('DB_PASS', '');           // default XAMPP MySQL password is empty

// ---------- Site settings ----------
define('SITE_NAME', 'CineVerse');
define('CURRENCY', '&#8377;');   // Indian Rupee symbol
define('CONVENIENCE_FEE', 30.00);      // flat fee added per booking
define('PREMIUM_SURCHARGE', 80.00);    // extra for premium seats
define('MAX_SEATS_PER_BOOKING', 10);
define('BOOKING_CANCEL_HOURS', 4);     // cancel allowed until 4 hrs before show

// ---------- Work out the base URL of the project ----------
// Every link in the project is written relative to the PROJECT ROOT --
// url('assets/css/style.css'), url('user/login.php'), url('admin/index.php')
// -- no matter which folder the current page happens to live in. So
// BASE_URL must always point at the project root, never at the folder of
// the current script, otherwise pages inside user/ and admin/ end up with
// doubled paths such as /cineverse/user/assets/css/style.css.
$projectRoot = str_replace('\\', '/', realpath(dirname(__DIR__)));

// How many folders below the project root does the current script sit?
// Measured on disk (not by comparing strings) so it still works when the
// project is reached through a junction or symlink, because realpath()
// resolves the link on both sides of the comparison.
$depth      = 0;
$scriptDirFs = realpath(dirname($_SERVER['SCRIPT_FILENAME'] ?? ''));
if ($scriptDirFs !== false) {
    $scriptDirFs = str_replace('\\', '/', $scriptDirFs);
    if (strpos($scriptDirFs, $projectRoot) === 0) {
        $below = trim(substr($scriptDirFs, strlen($projectRoot)), '/');
        $depth = ($below === '') ? 0 : count(explode('/', $below));
    }
}

// Now drop that many folders off the front of the script's URL folder.
$urlParts = array_values(array_filter(
    explode('/', trim(str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? '/')), '/')),
    'strlen'
));
if ($depth > 0) {
    $urlParts = array_slice($urlParts, 0, count($urlParts) - $depth);
}
$basePath = implode('/', $urlParts);
define('BASE_URL', $basePath === '' ? '/' : '/' . $basePath . '/');

// ---------- Start the session (used for login) ----------
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ---------- PDO connection ----------
// try { ... } catch { ... }  is the short form of try/catch available from PHP 8.0
try {
    $pdo = new PDO(
        "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4",
        DB_USER,
        DB_PASS,
        array(
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION, // throw errors
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,       // rows as arrays
            PDO::ATTR_EMULATE_PREPARES   => false                   // real prepared statements
        )
    );
} catch (PDOException $e) {
    die(
        '<div style="font-family:Arial;background:#0b0b12;color:#eee;padding:40px;text-align:center">'
        . '<h2>Database connection failed</h2>'
        . '<p>Make sure Apache and MySQL are running in the XAMPP Control Panel,</p>'
        . '<p>and that you imported <code>database/movie_booking.sql</code> into phpMyAdmin.</p>'
        . '<p style="color:#f5c451">Error: ' . htmlspecialchars($e->getMessage()) . '</p>'
        . '</div>'
    );
}
