<?php
/* =============================================================
   admin/index.php  -  Dashboard with statistics
   ============================================================= */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

/* ---------- Headline numbers ---------- */
$total_users     = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$total_movies    = (int) $pdo->query("SELECT COUNT(*) FROM movies")->fetchColumn();
$now_showing     = (int) $pdo->query("SELECT COUNT(*) FROM movies WHERE status = 'now_showing'")->fetchColumn();
$total_theatres  = (int) $pdo->query("SELECT COUNT(*) FROM theatres WHERE is_active = 1")->fetchColumn();
$total_shows     = (int) $pdo->query("SELECT COUNT(*) FROM shows WHERE show_date >= CURDATE()")->fetchColumn();
$total_bookings  = (int) $pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
$total_cancelled = (int) $pdo->query("SELECT COUNT(*) FROM bookings WHERE status = 'cancelled'")->fetchColumn();

// Revenue only counts confirmed + paid bookings
$revenue = (float) $pdo->query(
    "SELECT COALESCE(SUM(total_amount), 0) FROM bookings WHERE status = 'confirmed' AND payment_status = 'paid'"
)->fetchColumn();

// Revenue lost because of cancellations
$refunded = (float) $pdo->query(
    "SELECT COALESCE(SUM(total_amount), 0) FROM bookings WHERE status = 'cancelled'"
)->fetchColumn();

/* ---------- Bookings per day, last 7 days (simple bar chart) ---------- */
$stmt = $pdo->prepare(
    "SELECT DATE(booked_at) AS d, COUNT(*) AS c
       FROM bookings
      WHERE booked_at >= DATE_SUB(CURDATE(), INTERVAL 6 DAY)
      GROUP BY DATE(booked_at)"
);
$stmt->execute();
$chart = array();
foreach ($stmt->fetchAll() as $row) {
    $chart[$row['d']] = (int) $row['c'];
}
$chart_data = array();
for ($i = 6; $i >= 0; $i--) {
    $day = date('Y-m-d', strtotime("-$i day"));
    $chart_data[] = array(
        'label' => date('D', strtotime($day)),
        'count' => isset($chart[$day]) ? $chart[$day] : 0
    );
}
$chart_max = 1;
foreach ($chart_data as $c) {
    if ($c['count'] > $chart_max) { $chart_max = $c['count']; }
}

/* ---------- Top earning movies ---------- */
$stmt = $pdo->query(
    "SELECT m.title, m.poster,
            COUNT(b.id) AS bookings,
            COALESCE(SUM(b.total_amount), 0) AS revenue
       FROM bookings b
       JOIN shows  s ON s.id = b.show_id
       JOIN movies m ON m.id = s.movie_id
      WHERE b.status = 'confirmed'
      GROUP BY m.id
      ORDER BY revenue DESC
      LIMIT 5"
);
$top_movies = $stmt->fetchAll();

/* ---------- Recent bookings ---------- */
$stmt = $pdo->query(
    "SELECT b.booking_code, b.total_amount, b.status, b.booked_at, b.seats_count,
            u.full_name, m.title
       FROM bookings b
       JOIN users  u ON u.id = b.user_id
       JOIN shows  s ON s.id = b.show_id
       JOIN movies m ON m.id = s.movie_id
      ORDER BY b.booked_at DESC
      LIMIT 8"
);
$recent = $stmt->fetchAll();

$page_title = 'Dashboard';
require __DIR__ . '/../includes/admin_header.php';
?>

<!-- ============ STAT CARDS ============ -->
<div class="stat-grid">
  <div class="stat gold">
    <span>Total Bookings</span>
    <b><?php echo $total_bookings; ?></b>
  </div>
  <div class="stat green">
    <span>Revenue (Confirmed)</span>
    <b><?php echo money($revenue); ?></b>
  </div>
  <div class="stat purple">
    <span>Registered Users</span>
    <b><?php echo $total_users; ?></b>
  </div>
  <div class="stat">
    <span>Movies Listed</span>
    <b><?php echo $total_movies; ?></b>
  </div>
  <div class="stat">
    <span>Now Showing</span>
    <b><?php echo $now_showing; ?></b>
  </div>
  <div class="stat">
    <span>Active Theatres</span>
    <b><?php echo $total_theatres; ?></b>
  </div>
  <div class="stat">
    <span>Upcoming Shows</span>
    <b><?php echo $total_shows; ?></b>
  </div>
  <div class="stat">
    <span>Cancelled</span>
    <b><?php echo $total_cancelled; ?></b>
  </div>
</div>

<div class="grid-2" style="margin-bottom:1.6rem">

  <!-- ============ 7 DAY BOOKING CHART ============ -->
  <div class="card">
    <h3>Bookings in the last 7 days</h3>
    <?php if (array_sum(array_column($chart_data, 'count')) === 0): ?>
      <p class="hint">No bookings recorded yet.</p>
    <?php else: ?>
      <div class="bar-chart">
        <?php foreach ($chart_data as $c): ?>
          <div class="bar-col">
            <b><?php echo $c['count']; ?></b>
            <div class="bar" style="height:<?php echo round(($c['count'] / $chart_max) * 100); ?>%"></div>
            <small><?php echo e($c['label']); ?></small>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>

  <!-- ============ REVENUE SUMMARY ============ -->
  <div class="card">
    <h3>Revenue Summary</h3>
    <div class="summary-row"><span>Confirmed bookings</span><b><?php echo money($revenue); ?></b></div>
    <div class="summary-row"><span>Amount on cancelled bookings</span><b style="color:#fca5a5"><?php echo money($refunded); ?></b></div>
    <div class="summary-row">
      <span>Average ticket value</span>
      <b>
        <?php
        echo money($total_bookings > 0 ? $revenue / $total_bookings : 0);
        ?>
      </b>
    </div>
    <div class="summary-row">
      <span>Seats sold</span>
      <b>
        <?php
        echo (int) $pdo->query(
            "SELECT COALESCE(SUM(seats_count), 0) FROM bookings WHERE status = 'confirmed'"
        )->fetchColumn();
        ?>
      </b>
    </div>
    <div class="summary-row" style="border:0">
      <span>Success rate</span>
      <b>
        <?php
        $done = $total_bookings - $total_cancelled;
        echo ($total_bookings > 0 ? round(($done / $total_bookings) * 100) : 0) . '%';
        ?>
      </b>
    </div>
  </div>

</div>

<!-- ============ TOP MOVIES ============ -->
<div class="card" style="margin-bottom:1.6rem">
  <h3>Top Earning Movies</h3>
  <?php if (empty($top_movies)): ?>
    <p class="hint">No confirmed bookings yet.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>#</th>
            <th>Movie</th>
            <th>Bookings</th>
            <th>Revenue</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($top_movies as $i => $m): ?>
            <tr>
              <td><?php echo $i + 1; ?></td>
              <td>
                <div style="display:flex;align-items:center;gap:.7rem">
                  <img class="poster-thumb" src="<?php echo e(poster_url($m['poster'])); ?>" alt="">
                  <b><?php echo e($m['title']); ?></b>
                </div>
              </td>
              <td><?php echo (int) $m['bookings']; ?></td>
              <td class="price"><?php echo money($m['revenue']); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<!-- ============ RECENT BOOKINGS ============ -->
<div class="card">
  <div class="toolbar-admin">
    <h3 style="margin:0">Recent Bookings</h3>
    <a class="btn btn-ghost btn-sm" href="<?php echo url('admin/bookings.php'); ?>">View All</a>
  </div>

  <?php if (empty($recent)): ?>
    <p class="hint">Nothing booked yet. Book a ticket from the user side to see it appear here.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Booking ID</th>
            <th>Customer</th>
            <th>Movie</th>
            <th>Seats</th>
            <th>Amount</th>
            <th>Status</th>
            <th>Booked On</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($recent as $r): ?>
            <tr>
              <td class="booking-code"><?php echo e($r['booking_code']); ?></td>
              <td><?php echo e($r['full_name']); ?></td>
              <td><?php echo e($r['title']); ?></td>
              <td><?php echo (int) $r['seats_count']; ?></td>
              <td class="price"><?php echo money($r['total_amount']); ?></td>
              <td><span class="status-pill status-<?php echo e($r['status']); ?>"><?php echo e(ucfirst($r['status'])); ?></span></td>
              <td class="hint"><?php echo e(date('d M Y, g:i A', strtotime($r['booked_at']))); ?></td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
