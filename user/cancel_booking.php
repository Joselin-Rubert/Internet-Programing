<?php
/* =============================================================
   user/cancel_booking.php
   Cancels a booking and frees its seats so somebody else can book
   them. Only the owner of the booking can cancel it.
   ============================================================= */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_user();

$booking_id = isset($_POST['booking_id']) ? (int) $_POST['booking_id'] : 0;

// Only allow cancelling from a POST request
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || $booking_id <= 0) {
    redirect(url('user/my_bookings.php'));
}

// Load the booking, making sure it belongs to this user
$stmt = $pdo->prepare(
    "SELECT b.*, s.show_date, s.show_time
       FROM bookings b
       JOIN shows s ON s.id = b.show_id
      WHERE b.id = ? AND b.user_id = ?"
);
$stmt->execute(array($booking_id, $_SESSION['user_id']));
$booking = $stmt->fetch();

if (!$booking) {
    flash_set('error', 'That booking could not be found.');
    redirect(url('user/my_bookings.php'));
}

if (!is_cancellable($booking, $booking)) {
    flash_set('error', 'This booking can no longer be cancelled. Please contact the theatre.');
    redirect(url('user/my_bookings.php'));
}

try {
    $pdo->beginTransaction();

    // 1) mark the booking as cancelled
    $stmt = $pdo->prepare(
        "UPDATE bookings SET status = 'cancelled', cancelled_at = NOW() WHERE id = ?"
    );
    $stmt->execute(array($booking_id));

    // 2) mark its seats as free (keeps the record, releases the seat)
    $stmt = $pdo->prepare("UPDATE booking_details SET is_cancelled = 1 WHERE booking_id = ?");
    $stmt->execute(array($booking_id));

    $pdo->commit();

    flash_set('success', 'Booking ' . $booking['booking_code'] . ' has been cancelled. Your seats are available again.');

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash_set('error', 'The booking could not be cancelled. Please try again.');
}

redirect(url('user/my_bookings.php'));
