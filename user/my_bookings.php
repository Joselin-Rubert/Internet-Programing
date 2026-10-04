<?php
/* =============================================================
   user/my_bookings.php  -  Booking history for the logged-in user
   ============================================================= */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_user();

// Optional filter: ?status=confirmed|cancelled|all
$filter = isset($_GET['status']) ? $_GET['status'] : 'all';

$sql = "SELECT b.*, m.title, m.poster, m.language,
               t.name AS theatre_name, t.screen_name,
               s.show_date, s.show_time
          FROM bookings b
          JOIN shows    s ON s.id = b.show_id
          JOIN movies   m ON m.id = s.movie_id
          JOIN theatres t ON t.id = s.theatre_id
         WHERE b.user_id = ?";
$params = array($_SESSION['user_id']);

if ($filter === 'confirmed' || $filter === 'cancelled') {
    $sql .= " AND b.status = ?";
    $params[] = $filter;
}
$sql .= " ORDER BY b.booked_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// Seats of every booking, in one go
$seat_map = array();
if (!empty($bookings)) {
    $ids = array();
    foreach ($bookings as $b) {
        $ids[] = (int) $b['id'];
    }
    $in = implode(',', array_fill(0, count($ids), '?'));
    $s2 = $pdo->prepare("SELECT booking_id, seat_label FROM booking_details WHERE booking_id IN ($in) ORDER BY seat_label");
    $s2->execute($ids);
    foreach ($s2->fetchAll() as $row) {
        $seat_map[$row['booking_id']][] = $row['seat_label'];
    }
}

// Small totals for the summary strip
$total_bookings = count($bookings);
$total_spent    = 0.0;
$upcoming_count = 0;
foreach ($bookings as $b) {
    if ($b['status'] === 'confirmed') {
        $total_spent += (float) $b['total_amount'];
        if (strtotime($b['show_date'] . ' ' . $b['show_time']) > time()) {
            $upcoming_count++;
        }
    }
}

$page_title = 'My Bookings';
require __DIR__ . '/../includes/header.php';
?>

<section class="section" style="padding-top:0">
  <div class="section-head">
    <h2>My Bookings</h2>
    <div class="action-group">
      <?php
      $tabs = array('all' => 'All', 'confirmed' => 'Confirmed', 'cancelled' => 'Cancelled');
      foreach ($tabs as $key => $label):
      ?>
        <a class="btn <?php echo $filter === $key ? 'btn-gold' : 'btn-ghost'; ?> btn-sm"
           href="<?php echo url('user/my_bookings.php?status=' . $key); ?>"><?php echo e($label); ?></a>
      <?php endforeach; ?>
    </div>
  </div>
</section>

<div class="stat-grid">
  <div class="stat gold">
    <span>Total Bookings</span>
    <b><?php echo $total_bookings; ?></b>
  </div>
  <div class="stat green">
    <span>Upcoming Shows</span>
    <b><?php echo $upcoming_count; ?></b>
  </div>
  <div class="stat purple">
    <span>Total Spent</span>
    <b><?php echo money($total_spent); ?></b>
  </div>
</div>

<?php if (empty($bookings)): ?>

  <div class="empty-state">
    <div class="big">&#127903;</div>
    <p>You have not booked any tickets yet.</p>
    <a class="btn btn-gold" style="margin-top:1rem" href="<?php echo url('user/index.php'); ?>">Browse Movies</a>
  </div>

<?php else: ?>

  <div class="booking-cards">
    <?php foreach ($bookings as $b): ?>
      <?php
      $seats     = isset($seat_map[$b['id']]) ? $seat_map[$b['id']] : array();
      $can_cancel = is_cancellable($b, $b);   // $b already holds show_date, show_time and status
      ?>
      <article class="booking-card <?php echo $b['status'] === 'cancelled' ? 'cancelled' : ''; ?>">

        <img class="poster-thumb" src="<?php echo e(poster_url($b['poster'])); ?>" alt="<?php echo e($b['title']); ?>">

        <div>
          <h3><?php echo e($b['title']); ?></h3>
          <p class="booking-code"><?php echo e($b['booking_code']); ?></p>

          <div class="booking-info">
            <span>&#127978; <?php echo e($b['theatre_name']); ?></span>
            <span>&#128197; <?php echo e(date('d M Y', strtotime($b['show_date']))); ?></span>
            <span>&#128337; <?php echo e(date('g:i A', strtotime($b['show_time']))); ?></span>
            <span>
              &#128190; Seats:
              <b style="color:var(--gold)"><?php echo e(implode(', ', $seats)); ?></b>
            </span>
            <span><?php echo e($b['language']); ?></span>
          </div>

          <?php if ($b['status'] === 'cancelled' && $b['cancelled_at']): ?>
            <p class="hint" style="color:#fca5a5;margin-top:.4rem">
              Cancelled on <?php echo e(date('d M Y, g:i A', strtotime($b['cancelled_at']))); ?>
            </p>
          <?php endif; ?>
        </div>

        <div class="booking-side">
          <span class="status-pill status-<?php echo e($b['status']); ?>"><?php echo e(ucfirst($b['status'])); ?></span>
          <span class="booking-amount"><?php echo money($b['total_amount']); ?></span>

          <?php if ($b['status'] === 'confirmed'): ?>
            <a class="btn btn-ghost btn-sm" href="<?php echo url('user/confirmation.php?code=' . urlencode($b['booking_code'])); ?>">
              View Ticket
            </a>
          <?php endif; ?>

          <?php if ($can_cancel): ?>
            <form method="post" action="<?php echo url('user/cancel_booking.php'); ?>"
                  data-confirm="Cancel this booking? The seats will be released for others.">
              <input type="hidden" name="booking_id" value="<?php echo (int) $b['id']; ?>">
              <button type="submit" class="btn btn-danger btn-sm">Cancel Booking</button>
            </form>
          <?php elseif ($b['status'] === 'confirmed'): ?>
            <span class="hint">Cannot cancel &mdash; less than <?php echo BOOKING_CANCEL_HOURS; ?> hrs to showtime</span>
          <?php endif; ?>
        </div>

      </article>
    <?php endforeach; ?>
  </div>

<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
