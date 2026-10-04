<?php
/* =============================================================
   admin/shows.php  -  Manage showtimes (movie + theatre + date + time)
   ============================================================= */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

// ---- Delete a show ----
if (isset($_GET['delete']) && (int) $_GET['delete'] > 0) {
    $id  = (int) $_GET['delete'];
    $del = $pdo->prepare("DELETE FROM shows WHERE id = ?");
    $del->execute(array($id));
    flash_set('success', 'Showtime deleted.');
    redirect(url('admin/shows.php'));
}

// ---- Bulk add a whole day of shows ----
if (isset($_POST['bulk_add'])) {
    $movie_id   = (int) ($_POST['bulk_movie'] ?? 0);
    $theatre_id = (int) ($_POST['bulk_theatre'] ?? 0);
    $from       = $_POST['bulk_from'] ?? '';
    $days       = max(1, min(30, (int) ($_POST['bulk_days'] ?? 7)));
    $time_list  = trim($_POST['bulk_times'] ?? '10:30:00, 13:10:00, 16:00:00, 18:45:00, 21:30:00');

    if ($movie_id <= 0 || $theatre_id <= 0 || $from === '') {
        flash_set('error', 'Please choose a movie, a theatre and a start date.');
    } else {
        $times = array_filter(array_map('trim', explode(',', $time_list)));
        $stmt  = $pdo->prepare(
            "INSERT IGNORE INTO shows (movie_id, theatre_id, show_date, show_time) VALUES (?, ?, ?, ?)"
        );

        $added = 0;
        for ($d = 0; $d < $days; $d++) {
            $date = date('Y-m-d', strtotime($from . " +$d day"));
            foreach ($times as $t) {
                $clean = date('H:i:s', strtotime($t));
                if ($clean === false || $clean === '') {
                    continue;
                }
                $stmt->execute(array($movie_id, $theatre_id, $date, $clean));
                $added += $stmt->rowCount();
            }
        }
        flash_set('success', $added . ' showtime(s) added.');
    }
    redirect(url('admin/shows.php'));
}

$movies   = $pdo->query("SELECT id, title FROM movies WHERE status = 'now_showing' ORDER BY title")->fetchAll();
$theatres = $pdo->query("SELECT id, name FROM theatres WHERE is_active = 1 ORDER BY name")->fetchAll();

// ---- List shows, filter by date ----
$filter_date = isset($_GET['date']) ? $_GET['date'] : date('Y-m-d');

$stmt = $pdo->prepare(
    "SELECT s.*, m.title, m.ticket_price, t.name AS theatre_name,
            (SELECT COUNT(*) FROM booking_details bd
               JOIN bookings b ON b.id = bd.booking_id
              WHERE b.show_id = s.id AND b.status = 'confirmed'
                AND b.payment_status = 'paid' AND bd.is_cancelled = 0) AS seats_taken
       FROM shows s
       JOIN movies  m ON m.id = s.movie_id
       JOIN theatres t ON t.id = s.theatre_id
      WHERE s.show_date = ?
      ORDER BY m.title, s.show_time"
);
$stmt->execute(array($filter_date));
$shows = $stmt->fetchAll();

$page_title = 'Showtimes';
require __DIR__ . '/../includes/admin_header.php';
?>

<!-- ============ DATE NAVIGATION ============ -->
<div class="toolbar-admin">
  <div class="action-group">
    <?php
    $days = array();
    for ($i = -2; $i <= 4; $i++) {
        $days[$i] = date('Y-m-d', strtotime("$i day"));
    }
    foreach ($days as $i => $d):
    ?>
      <a class="btn <?php echo $filter_date === $d ? 'btn-gold' : 'btn-ghost'; ?> btn-sm"
         href="<?php echo url('admin/shows.php?date=' . $d); ?>">
        <?php echo $i === 0 ? 'Today' : ($i === 1 ? 'Tomorrow' : date('D d M', strtotime($d))); ?>
      </a>
    <?php endforeach; ?>
  </div>

  <form method="get" class="action-group">
    <input type="date" name="date" value="<?php echo e($filter_date); ?>" style="width:auto">
    <button type="submit" class="btn btn-purple btn-sm">Go</button>
  </form>
</div>

<!-- ============ BULK ADD ============ -->
<div class="card" style="margin-bottom:1.6rem">
  <h3>Add Showtimes in Bulk</h3>
  <p class="hint" style="margin-bottom:1rem">
    Useful for filling a whole week in one go. Duplicates are skipped automatically.
  </p>

  <?php if (empty($movies) || empty($theatres)): ?>
    <p class="hint">You need at least one "Now Showing" movie and one active theatre first.</p>
  <?php else: ?>
    <form method="post" action="">
      <input type="hidden" name="bulk_add" value="1">

      <div class="form-row">
        <div class="field">
          <label for="bulk_movie">Movie</label>
          <select id="bulk_movie" name="bulk_movie">
            <?php foreach ($movies as $m): ?>
              <option value="<?php echo (int) $m['id']; ?>"><?php echo e($m['title']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="bulk_theatre">Theatre</label>
          <select id="bulk_theatre" name="bulk_theatre">
            <?php foreach ($theatres as $t): ?>
              <option value="<?php echo (int) $t['id']; ?>"><?php echo e($t['name']); ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div class="field">
          <label for="bulk_from">Starting Date</label>
          <input type="date" id="bulk_from" name="bulk_from" value="<?php echo date('Y-m-d'); ?>">
        </div>
        <div class="field">
          <label for="bulk_days">Number of Days</label>
          <input type="number" id="bulk_days" name="bulk_days" value="7" min="1" max="30">
        </div>
      </div>

      <div class="field">
        <label for="bulk_times">Showtimes (comma separated)</label>
        <input type="text" id="bulk_times" name="bulk_times" value="10:30, 13:10, 16:00, 18:45, 21:30">
      </div>

      <button type="submit" class="btn btn-purple">Add Showtimes</button>
    </form>
  <?php endif; ?>
</div>

<!-- ============ SHOW LIST ============ -->
<div class="card">
  <div class="toolbar-admin">
    <h3 style="margin:0">
      Shows on <?php echo e(date('D, d M Y', strtotime($filter_date))); ?>
    </h3>
    <a class="btn btn-ghost btn-sm" href="<?php echo url('admin/show_form.php'); ?>">+ Add Single Show</a>
  </div>

  <?php if (empty($shows)): ?>
    <p class="hint">No shows scheduled for this date.</p>
  <?php else: ?>
    <div class="table-wrap">
      <table>
        <thead>
          <tr>
            <th>Time</th>
            <th>Movie</th>
            <th>Theatre</th>
            <th>Ticket Price</th>
            <th>Seats Booked</th>
            <th>Actions</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($shows as $s): ?>
            <?php $taken = (int) $s['seats_taken']; ?>
            <tr>
              <td><b style="color:var(--gold)"><?php echo e(date('g:i A', strtotime($s['show_time']))); ?></b></td>
              <td><?php echo e($s['title']); ?></td>
              <td class="hint"><?php echo e($s['theatre_name']); ?></td>
              <td class="price"><?php echo money($s['ticket_price']); ?></td>
              <td>
                <?php echo $taken; ?> / 50
                <?php if ($taken >= 50): ?>
                  <span class="status-pill status-cancelled">Full</span>
                <?php endif; ?>
              </td>
              <td>
                <div class="action-group">
                  <a class="btn btn-ghost btn-sm" href="<?php echo url('admin/show_form.php?id=' . (int) $s['id']); ?>">Edit</a>
                  <form method="get" action="<?php echo url('admin/shows.php'); ?>"
                        data-confirm="Delete this showtime? Bookings already made for it will also be removed.">
                    <input type="hidden" name="delete" value="<?php echo (int) $s['id']; ?>">
                    <button type="submit" class="btn btn-danger btn-sm">Delete</button>
                  </form>
                </div>
              </td>
            </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  <?php endif; ?>
</div>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
