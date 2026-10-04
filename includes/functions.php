<?php
/* =============================================================
   includes/functions.php
   Small helper functions used across the whole project.
   ============================================================= */

/** Escape text before printing it inside HTML (prevents XSS). */
function e($text)
{
    return htmlspecialchars((string) $text, ENT_QUOTES, 'UTF-8');
}

/** Redirect the browser to another page and stop the script. */
function redirect($url)
{
    header('Location: ' . $url);
    exit;
}

/** Show a one-time flash message, then clear it. */
function flash_set($type, $message)
{
    $_SESSION['flash'] = array('type' => $type, 'message' => $message);
}

function flash_get()
{
    if (!isset($_SESSION['flash'])) {
        return null;
    }
    $flash = $_SESSION['flash'];
    unset($_SESSION['flash']);
    return $flash;
}

/** Print the flash message as HTML (if one exists). */
function flash_show()
{
    $flash = flash_get();
    if (!$flash) {
        return;
    }
    $class = ($flash['type'] === 'success') ? 'alert-success' : 'alert-error';
    echo '<div class="alert ' . $class . '">' . e($flash['message']) . '</div>';
}

/** Format 150.00 -> ₹150 (matches the CURRENCY constant). */
function money($amount)
{
    return CURRENCY . number_format((float) $amount, 0);
}

/** Work out the full "absolute" URL of a file inside the project. */
function url($path = '')
{
    // BASE_URL is calculated in config/db.php, so this works from
    // any folder (index.php, user/, admin/) without hard-coding paths.
    return BASE_URL . ltrim($path, '/');
}

/** Poster path - falls back to a local placeholder if the file is missing. */
function poster_url($poster)
{
    $poster = trim((string) $poster);
    if ($poster === '' || !file_exists(__DIR__ . '/../assets/posters/' . $poster)) {
        return url('assets/posters/default-poster.svg');
    }
    return url('assets/posters/' . rawurlencode($poster));
}

/** Turn a SQL datetime (date + time) into "2h 10m". */
function show_datetime($date, $time)
{
    return date('D, d M Y', strtotime($date)) . ' &middot; ' . date('g:i A', strtotime($time));
}

/** Date used on the seat picker (today .. +6 days). */
function show_date_options($days = 7)
{
    $out = array();
    for ($i = 0; $i < $days; $i++) {
        $ts        = strtotime("+$i day");
        $out[$i] = array(
            'value'  => date('Y-m-d', $ts),
            'label'  => ($i === 0 ? 'Today' : ($i === 1 ? 'Tomorrow' : date('D, d M', $ts))),
            'sub'    => date('d M Y', $ts)
        );
    }
    return $out;
}

/**
 * A seat is only blocked when there is a CONFIRMED booking
 * that has not been cancelled and holds the seat for this show.
 */
function get_booked_seat_ids($pdo, $show_id)
{
    $sql = "SELECT bd.seat_id
            FROM booking_details bd
            JOIN bookings b ON b.id = bd.booking_id
            WHERE b.show_id = ?
              AND b.status = 'confirmed'
              AND b.payment_status = 'paid'
              AND bd.is_cancelled = 0";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array($show_id));
    return array_map('intval', $stmt->fetchAll(PDO::FETCH_COLUMN));
}

/** Price of a single seat: base ticket price + premium surcharge. */
function seat_price($movie, $seat_type)
{
    $price = (float) $movie['ticket_price'];
    if ($seat_type === 'premium') {
        $price += PREMIUM_SURCHARGE;
    }
    return $price;
}

/** Build a short readable booking code, e.g. MVB-7K3QD9. */
function make_booking_code()
{
    return 'MVB-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 6));
}

/** Can this booking still be cancelled by the user? */
function is_cancellable($booking, $show)
{
    if ($booking['status'] !== 'confirmed') {
        return false;
    }
    $show_time = strtotime($show['show_date'] . ' ' . $show['show_time']);
    return ($show_time - time()) > (BOOKING_CANCEL_HOURS * 3600);
}

/**
 * Build the seat map for a theatre: rows A..E, 10 seats each.
 * Rows A and B are regular, rows C, D and E are premium.
 */
function generate_seats($pdo, $theatre_id)
{
    $stmt = $pdo->prepare(
        "INSERT INTO seats (theatre_id, seat_row, seat_number, seat_label, seat_type)
         VALUES (?, ?, ?, ?, ?)"
    );
    for ($row = 0; $row < 5; $row++) {
        $label  = chr(65 + $row);       // 0 -> A, 1 -> B ...
        $type   = ($row < 2) ? 'regular' : 'premium';
        for ($num = 1; $num <= 10; $num++) {
            $stmt->execute(array($theatre_id, $label, $num, $label . $num, $type));
        }
    }
}
