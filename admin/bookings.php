<?php
/* =============================================================
   admin/bookings.php  -  Every booking, with filters
   Uses the v_booking_report view created in the SQL file.
   ============================================================= */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$status = isset($_GET['status']) ? $_GET['status'] : 'all';
$search = isset($_GET['q'])      ? trim($_GET['q'])     : '';

$sql    = "SELECT * FROM v_booking_report WHERE 1 = 1";
$params = array();

if ($status === 'confirmed' || $status === 'cancelled') {
    $sql .= " AND status = ?";
    $params[] = $status;
}
if ($search !== '') {
    $sql .= " AND (booking_code LIKE ? OR customer LIKE ? OR email LIKE ? OR movie LIKE ?)";
    $like = '%' . $search . '%';
    $params = array_merge($params, array($like, $like, $like, $like));
}
$sql .= " ORDER BY booked_at DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$bookings = $stmt->fetchAll();

// Totals for the current filter
$total   = count($bookings);
$sum     = 0.0;
$seats   = 0;
$cancels = 0;
foreach ($bookings as $b) {
    $sum += (float) $b['total_amount'];
    $seats += (int) $b['seats_count'];
    if ($b['status'] === 'cancelled') {
        $cancels++;
    }
}

$page_title = 'Bookings';
require __DIR__ . '/../includes/admin_header.php';
?>

<div class="stat-grid">
  <div class="stat gold">
    <span>Bookings in this view</span>
    <b><?php echo $total; ?></b>
  </div>
  <div class="stat green">
    <span>Value in this view</span>
    <b><?php echo money($sum); ?></b>
  </div>
  <div class="stat purple">
    <span>Seats</span>
    <b><?php echo $seats; ?></b>
  </div>
  <div class="stat">
    <span>Cancelled</span>
    <b><?php echo $cancels; ?></b>
  </div>
</div>

<div class="toolbar-admin">
  <form method="get" class="action-group">
    <input type="hidden" name="status" value="<?php echo e($status); ?>">
    <div class="search-box" style="min-width:260px">
      <input type="text" name="q" value="<?php echo e($search); ?>" placeholder="Booking ID, customer, movie...">
    </div>
    <button type="submit" class="btn btn-purple btn-sm">Search</button>
    <?php if ($search !== ''): ?>
      <a class="btn btn-ghost btn-sm" href="<?php echo url('admin/bookings.php'); ?>">Clear</a>
    <?php endif; ?>
  </form>

  <div class="action-group">
    <?php
    $tabs = array('all' => 'All', 'confirmed' => 'Confirmed', 'cancelled' => 'Cancelled');
    foreach ($tabs as $key => $label):
    ?>
      <a class="btn <?php echo $status === $key ? 'btn-gold' : 'btn-ghost'; ?> btn-sm"
         href="<?php echo url('admin/bookings.php?status=' . $key); ?>"><?php echo e($label); ?></a>
    <?php endforeach; ?>
  </div>
</div>

<?php if (empty($bookings)): ?>

  <div class="empty-state">
    <div class="big">&#128229;</div>
    <p>No bookings found for this filter.</p>
  </div>

<?php else: ?>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>Booking ID</th>
          <th>Movie</th>
          <th>Customer</th>
          <th>Theatre</th>
          <th>Date &amp; Time</th>
          <th>Seats</th>
          <th>Amount</th>
          <th>Status</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($bookings as $b): ?>
          <tr>
            <td class="booking-code"><?php echo e($b['booking_code']); ?></td>
            <td>
              <div style="display:flex;align-items:center;gap:.7rem">
                <img class="poster-thumb" src="<?php echo e(poster_url($b['poster'])); ?>" alt="">
                <b><?php echo e($b['movie']); ?></b>
              </div>
            </td>
            <td>
              <?php echo e($b['customer']); ?><br>
              <span class="hint"><?php echo e($b['email']); ?></span>
            </td>
            <td class="hint"><?php echo e($b['theatre']); ?></td>
            <td class="hint">
              <?php echo e(date('d M Y', strtotime($b['show_date']))); ?><br>
              <?php echo e(date('g:i A', strtotime($b['show_time']))); ?>
            </td>
            <td><?php echo (int) $b['seats_count']; ?></td>
            <td class="price"><?php echo money($b['total_amount']); ?></td>
            <td><span class="status-pill status-<?php echo e($b['status']); ?>"><?php echo e(ucfirst($b['status'])); ?></span></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

<?php endif; ?>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
