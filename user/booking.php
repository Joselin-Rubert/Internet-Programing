<?php
/* =============================================================
   user/booking.php  -  Theatre + date + showtime + SEAT SELECTION
   URL forms:
     booking.php?movie_id=3        -> pick theatre, date and showtime
     booking.php?show_id=12        -> seats for that show
   ============================================================= */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_user();

$movie_id = isset($_GET['movie_id']) ? (int) $_GET['movie_id'] : 0;
$show_id  = isset($_GET['show_id'])  ? (int) $_GET['show_id']  : 0;

$show = null;

if ($show_id > 0) {
    // ---- Load the chosen show together with its movie and theatre ----
    $stmt = $pdo->prepare(
        "SELECT s.*, m.title, m.poster, m.language, m.duration, m.ticket_price, m.rating,
                t.name AS theatre_name, t.location, t.screen_name
           FROM shows s
           JOIN movies  m ON m.id = s.movie_id
           JOIN theatres t ON t.id = s.theatre_id
          WHERE s.id = ?"
    );
    $stmt->execute(array($show_id));
    $show = $stmt->fetch();

    if (!$show) {
        flash_set('error', 'That show is no longer available.');
        redirect(url('user/index.php'));
    }
    $movie_id = (int) $show['movie_id'];
}

// ---- Load the movie ----
$stmt = $pdo->prepare("SELECT * FROM movies WHERE id = ?");
$stmt->execute(array($movie_id));
$movie = $stmt->fetch();

if (!$movie || $movie['status'] !== 'now_showing') {
    flash_set('error', 'Tickets are only available for movies that are currently showing.');
    redirect(url('user/index.php'));
}

// ---- All shows for this movie, next 7 days ----
$stmt = $pdo->prepare(
    "SELECT s.*, t.name AS theatre_name, t.location, t.screen_name,
            (SELECT COUNT(*) FROM booking_details bd
               JOIN bookings b ON b.id = bd.booking_id
              WHERE b.show_id = s.id AND b.status = 'confirmed'
                AND b.payment_status = 'paid' AND bd.is_cancelled = 0) AS seats_taken
       FROM shows s
       JOIN theatres t ON t.id = s.theatre_id
      WHERE s.movie_id = ? AND s.show_date >= CURDATE()
      ORDER BY s.show_date, t.name, s.show_time"
);
$stmt->execute(array($movie_id));
$all_shows = $stmt->fetchAll();

// Split into: shows grouped by theatre, and by date
$by_theatre = array();
$by_date    = array();
foreach ($all_shows as $s) {
    $by_theatre[$s['theatre_id']][] = $s;
    $by_date[$s['show_date']][]    = $s;
}

$dates = show_date_options(7);

// If the visitor has not chosen a show yet, jump straight to the first one
if (!$show && !empty($all_shows)) {
    redirect(url('user/booking.php?show_id=' . (int) $all_shows[0]['id']));
}

if (!$show) {
    flash_set('error', 'No shows are scheduled for this movie at the moment.');
    redirect(url('user/movie_details.php?id=' . $movie_id));
}

// ---- Seats for the selected theatre ----
$stmt = $pdo->prepare("SELECT * FROM seats WHERE theatre_id = ? ORDER BY seat_row, seat_number");
$stmt->execute(array($show['theatre_id']));
$seats = $stmt->fetchAll();

// Which seats are already taken for THIS show?
$booked_ids = get_booked_seat_ids($pdo, $show_id);

// Group the seats row by row so we can print a proper seat map
$seat_rows = array();
foreach ($seats as $s) {
    $seat_rows[$s['seat_row']][] = $s;
}
ksort($seat_rows);

$page_title  = 'Book Tickets - ' . $movie['title'];
$body_attrs  = 'data-max-seats="' . MAX_SEATS_PER_BOOKING . '" data-fee="' . CONVENIENCE_FEE . '"';
require __DIR__ . '/../includes/header.php';
?>

<!-- ============ STEP BAR ============ -->
<div class="steps">
  <div class="step active"><b>1. Movie</b><?php echo e($movie['title']); ?></div>
  <div class="step active"><b>2. Show</b><?php echo e($show['theatre_name']); ?></div>
  <div class="step active"><b>3. Seats</b>Choose your seats</div>
  <div class="step"><b>4. Payment</b>Simulated checkout</div>
</div>

<p class="hint" style="margin-bottom:1rem">
  <a href="<?php echo url('user/movie_details.php?id=' . $movie_id); ?>">&larr; Back to movie details</a>
</p>

<div class="booking-layout">

  <!-- ================= LEFT: choices + seat map ================= -->
  <div>

    <!-- ---- Theatre ---- -->
    <div class="panel">
      <h3>1. Select a Theatre</h3>
      <div class="theatre-tabs">
        <?php foreach ($by_theatre as $tid => $shows): ?>
          <?php
          $first_show_in_theatre = $shows[0];
          $is_active = ((int) $tid === (int) $show['theatre_id']);
          // link to a show of this theatre on the currently chosen date if possible
          $link_show = $first_show_in_theatre;
          foreach ($shows as $s) {
              if ($s['show_date'] === $show['show_date']) { $link_show = $s; break; }
          }
          ?>
          <a class="theatre-tab <?php echo $is_active ? 'active' : ''; ?>"
             href="<?php echo url('user/booking.php?show_id=' . (int) $link_show['id']); ?>">
            <?php echo e($first_show_in_theatre['theatre_name']); ?>
            <span class="hint" style="display:block;font-weight:400"><?php echo e($first_show_in_theatre['location']); ?></span>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- ---- Date ---- -->
    <div class="panel">
      <h3>2. Select a Date</h3>
      <div class="date-tabs">
        <?php foreach ($dates as $d): ?>
          <?php
          if (!isset($by_date[$d['value']])) { continue; }
          // find a show on this date at the current theatre
          $target = $show;
          foreach ($by_date[$d['value']] as $s) {
              if ((int) $s['theatre_id'] === (int) $show['theatre_id']) { $target = $s; break; }
          }
          ?>
          <a class="date-tab <?php echo $d['value'] === $show['show_date'] ? 'active' : ''; ?>"
             href="<?php echo url('user/booking.php?show_id=' . (int) $target['id']); ?>">
            <?php echo e($d['label']); ?>
            <small><?php echo e(date('d M', strtotime($d['value']))); ?></small>
          </a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- ---- Showtime ---- -->
    <div class="panel">
      <h3>3. Select a Showtime</h3>
      <?php
      $day_shows = array();
      foreach ($by_date[$show['show_date']] as $s) {
          $day_shows[] = $s;
      }
      ?>
      <div class="showtime-grid">
        <?php foreach ($day_shows as $s): ?>
          <?php
          $left     = 50 - (int) $s['seats_taken'];
          $full     = ($left <= 0);
          $is_active = ((int) $s['id'] === (int) $show['id']);
          ?>
          <a href="<?php echo $full ? '#' : url('user/booking.php?show_id=' . (int) $s['id']); ?>"
             class="showtime-btn <?php echo $is_active ? 'active' : ''; ?> <?php echo $full ? 'is-disabled' : ''; ?>"
             <?php echo $full ? 'onclick="return false;" title="House full"' : ''; ?>>
            <?php echo date('g:i A', strtotime($s['show_time'])); ?>
            <small><?php echo e($s['theatre_name']); ?></small>
            <small><?php echo $full ? 'House Full' : $left . ' seats left'; ?></small>
          </a>
        <?php endforeach; ?>
      </div>
      <p class="hint" style="margin-top:.8rem">
        Showing shows on <b style="color:var(--gold)"><?php echo e(date('D, d M Y', strtotime($show['show_date']))); ?></b>
        across all theatres.
      </p>
    </div>

    <!-- ---- Seat map ---- -->
    <div class="panel">
      <h3>4. Select Your Seats</h3>

      <p class="hint" style="margin-bottom:.6rem">
        <b style="color:var(--gold)"><?php echo e($show['theatre_name']); ?></b>
        &middot; <?php echo e($show['screen_name']); ?>
        &middot; <?php echo e(date('g:i A', strtotime($show['show_time']))); ?>
      </p>

      <div class="screen">
        <div class="screen-bar"></div>
        <div class="screen-label">Screen This Way</div>
      </div>

      <div class="seat-map" id="seatMap">
        <?php foreach ($seat_rows as $row_label => $row_seats): ?>
          <div class="seat-row">
            <span class="seat-row-label"><?php echo e($row_label); ?></span>
            <?php foreach ($row_seats as $seat): ?>
              <?php
              $is_booked = in_array((int) $seat['id'], $booked_ids, true);
              $price     = seat_price($movie, $seat['seat_type']);
              $cls       = 'seat seat-' . $seat['seat_type'] . ($is_booked ? ' booked' : '');
              ?>
              <button type="button"
                      class="<?php echo $cls; ?>"
                      data-seat-id="<?php echo (int) $seat['id']; ?>"
                      data-label="<?php echo e($seat['seat_label']); ?>"
                      data-price="<?php echo number_format($price, 2, '.', ''); ?>"
                      data-type="<?php echo e($seat['seat_type']); ?>"
                      <?php echo $is_booked ? 'disabled title="Already booked"' : 'title="Seat ' . e($seat['seat_label']) . ' - ' . money($price) . '"'; ?>
                      aria-label="Seat <?php echo e($seat['seat_label']); ?>">
                <?php echo (int) $seat['seat_number']; ?>
              </button>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>

      <div class="seat-legend">
        <span class="legend-item"><span class="legend-swatch legend-regular"></span> Regular <?php echo money($movie['ticket_price']); ?></span>
        <span class="legend-item"><span class="legend-swatch legend-premium"></span> Premium <?php echo money(seat_price($movie, 'premium')); ?></span>
        <span class="legend-item"><span class="legend-swatch legend-selected"></span> Selected</span>
        <span class="legend-item"><span class="legend-swatch legend-booked"></span> Booked</span>
      </div>

      <p class="hint" id="seatHint" style="margin-top:1rem;text-align:center"></p>
    </div>

  </div>

  <!-- ================= RIGHT: live summary ================= -->
  <aside class="summary">
    <div class="summary-head">Booking Summary</div>
    <div class="summary-body">

      <div style="display:flex;gap:1rem;margin-bottom:1.1rem">
        <img class="poster-thumb" style="width:64px;height:92px"
             src="<?php echo e(poster_url($movie['poster'])); ?>" alt="">
        <div style="font-size:.9rem">
          <p style="font-weight:700"><?php echo e($movie['title']); ?></p>
          <p class="hint"><?php echo e($movie['language']); ?> &middot; <?php echo e($movie['duration']); ?></p>
          <p class="hint" style="color:var(--gold)"><?php echo e($show['theatre_name']); ?></p>
          <p class="hint"><?php echo e(show_datetime($show['show_date'], $show['show_time'])); ?></p>
        </div>
      </div>

      <div class="summary-row"><span>Seats</span><b id="sumCount">0 seats</b></div>
      <div class="summary-row"><span>Tickets</span><b id="sumSubtotal"><?php echo money(0); ?></b></div>
      <div class="summary-row"><span>Convenience fee</span><b id="sumFee"><?php echo money(0); ?></b></div>

      <div class="seat-tags" id="seatTags"></div>

      <div class="summary-total">
        <span>Total Payable</span>
        <b id="sumTotal"><?php echo money(0); ?></b>
      </div>

      <form method="post" action="<?php echo url('user/payment.php'); ?>">
        <input type="hidden" name="show_id" value="<?php echo (int) $show['id']; ?>">
        <input type="hidden" name="seat_ids" id="seatIds" value="">
        <button type="submit" id="proceedBtn" class="btn btn-gold btn-block btn-lg is-disabled" disabled>
          Proceed to Payment
        </button>
      </form>

      <p class="hint" style="margin-top:.8rem;text-align:center">
        Maximum <?php echo MAX_SEATS_PER_BOOKING; ?> seats per booking.
        Payment is simulated for this project.
      </p>
    </div>
  </aside>

</div>

<?php require __DIR__ . '/../includes/footer.php'; ?>
