<?php
/* =============================================================
   user/confirmation.php  -  Printable ticket with booking ID
   Opened with ?code=MVB-XXXXXX
   ============================================================= */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_user();

$code  = isset($_GET['code']) ? trim($_GET['code']) : '';
$user  = current_user($pdo);

$stmt = $pdo->prepare(
    "SELECT b.*,
            m.title, m.poster, m.language, m.duration, m.genre,
            t.name AS theatre_name, t.screen_name, t.location,
            s.show_date, s.show_time
       FROM bookings b
       JOIN shows    s ON s.id = b.show_id
       JOIN movies   m ON m.id = s.movie_id
       JOIN theatres t ON t.id = s.theatre_id
      WHERE b.booking_code = ? AND b.user_id = ?"
);
$stmt->execute(array($code, $_SESSION['user_id']));
$booking = $stmt->fetch();

if (!$booking) {
    flash_set('error', 'Booking not found.');
    redirect(url('user/my_bookings.php'));
}

// The seats for this booking
$stmt = $pdo->prepare(
    "SELECT * FROM booking_details WHERE booking_id = ? ORDER BY seat_label"
);
$stmt->execute(array($booking['id']));
$seat_rows = $stmt->fetchAll();

$page_title = 'Booking Confirmed';
require __DIR__ . '/../includes/header.php';
?>

<div class="center" style="margin-bottom:1.6rem">
  <div class="success-mark">&#10003;</div>
  <h1 style="margin-top:1rem">Your tickets are booked!</h1>
  <p class="hint">A confirmation has been saved under <b style="color:var(--gold)">My Bookings</b>.</p>
</div>

<article class="ticket">

  <div class="ticket-top">
    <div>
      <h2><?php echo e(SITE_NAME); ?></h2>
      <p>e-Ticket &middot; Booking ID <strong><?php echo e($booking['booking_code']); ?></strong></p>
    </div>
    <span class="status-pill status-<?php echo e($booking['status']); ?>">
      <?php echo e(ucfirst($booking['status'])); ?>
    </span>
  </div>

  <div class="ticket-body">

    <div class="ticket-grid">
      <div>
        <span>Movie</span>
        <b><?php echo e($booking['title']); ?></b>
      </div>
      <div>
        <span>Language</span>
        <b><?php echo e($booking['language']); ?></b>
      </div>
      <div>
        <span>Duration</span>
        <b><?php echo e($booking['duration']); ?></b>
      </div>
      <div>
        <span>Theatre</span>
        <b><?php echo e($booking['theatre_name']); ?></b>
      </div>
      <div>
        <span>Screen</span>
        <b><?php echo e($booking['screen_name']); ?></b>
      </div>
      <div>
        <span>Date &amp; Time</span>
        <b><?php echo e(date('D, d M Y', strtotime($booking['show_date']))); ?>,
           <?php echo e(date('g:i A', strtotime($booking['show_time']))); ?></b>
      </div>
      <div>
        <span>Seats</span>
        <b><?php echo e($booking['seats_count']); ?></b>
      </div>
      <div>
        <span>Payment</span>
        <b><?php echo e($booking['payment_method']); ?> (<?php echo e($booking['payment_status']); ?>)</b>
      </div>
    </div>

    <div class="ticket-seats">
      <span class="hint" style="text-transform:uppercase;letter-spacing:1px">Your seats</span>
      <div class="seat-tags" style="margin-top:.5rem">
        <?php foreach ($seat_rows as $s): ?>
          <span class="seat-tag"><?php echo e($s['seat_label']); ?></span>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="perforation"></div>

    <div class="summary-row"><span>Ticket subtotal</span><b><?php echo money($booking['subtotal']); ?></b></div>
    <div class="summary-row"><span>Convenience fee</span><b><?php echo money($booking['convenience_fee']); ?></b></div>
    <div class="summary-total">
      <span>Total paid</span>
      <b><?php echo money($booking['total_amount']); ?></b>
    </div>

    <div class="barcode">
      <?php for ($i = 0; $i < 60; $i++): ?>
        <span style="height:<?php echo ($i % 5 === 0) ? '100%' : (($i % 3 === 0) ? '70%' : '45%'); ?>"></span>
      <?php endfor; ?>
    </div>
    <p class="center hint"><?php echo e($booking['booking_code']); ?></p>

    <p class="center hint" style="margin-top:1rem">
      Booked on <?php echo e(date('d M Y, g:i A', strtotime($booking['booked_at']))); ?>
      &middot; Booked by <?php echo e($user ? $user['full_name'] : ''); ?>
    </p>

  </div>
</article>

<div class="center no-print" style="margin-top:1.8rem;display:flex;gap:.8rem;justify-content:center;flex-wrap:wrap">
  <button class="btn btn-gold" id="printTicket" type="button">Print Ticket</button>
  <a class="btn btn-ghost" href="<?php echo url('user/my_bookings.php'); ?>">My Bookings</a>
  <a class="btn btn-purple" href="<?php echo url('user/index.php'); ?>">Book Another Movie</a>
</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
