<?php
/* =============================================================
   user/movie_details.php
   Full description, duration, language, rating and showtimes.
   ============================================================= */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$movie_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;

// ---- Load the movie (prepared statement) ----
$stmt = $pdo->prepare("SELECT * FROM movies WHERE id = ?");
$stmt->execute(array($movie_id));
$movie = $stmt->fetch();

if (!$movie) {
    flash_set('error', 'Sorry, that movie could not be found.');
    redirect(url('user/index.php'));
}

$is_now = ($movie['status'] === 'now_showing');

// ---- Showtimes for the next 7 days, grouped by theatre ----
$shows_by_day = array();
if ($is_now) {
    $stmt = $pdo->prepare(
        "SELECT s.*, t.name AS theatre_name, t.location, t.screen_name,
                (SELECT COUNT(*) FROM booking_details bd
                   JOIN bookings b ON b.id = bd.booking_id
                  WHERE b.show_id = s.id AND b.status = 'confirmed'
                    AND b.payment_status = 'paid' AND bd.is_cancelled = 0) AS seats_taken
           FROM shows s
           JOIN theatres t ON t.id = s.theatre_id
           WHERE s.movie_id = ?
             AND s.show_date >= CURDATE()
           ORDER BY s.show_date, s.show_time"
    );
    $stmt->execute(array($movie_id));

    foreach ($stmt->fetchAll() as $show) {
        $shows_by_day[$show['show_date']][] = $show;
    }
}

$dates = show_date_options(7);
$page_title = $movie['title'];
require __DIR__ . '/../includes/header.php';
?>

<section class="section" style="padding-top:0">
  <p class="hint" style="margin-bottom:1rem">
    <a href="<?php echo url('user/index.php'); ?>">Home</a> /
    <?php echo e($is_now ? 'Now Showing' : 'Upcoming'); ?> /
    <span style="color:var(--gold)"><?php echo e($movie['title']); ?></span>
  </p>

  <div class="details-grid">

    <!-- Poster -->
    <div class="details-poster">
      <img src="<?php echo e(poster_url($movie['poster'])); ?>" alt="<?php echo e($movie['title']); ?> poster">
    </div>

    <!-- Details -->
    <div>
      <div class="chips">
        <span class="badge <?php echo $is_now ? '' : 'badge-upcoming'; ?>" style="position:static">
          <?php echo $is_now ? 'Now Showing' : 'Upcoming'; ?>
        </span>
        <span class="chip">&#9733; <?php echo e($movie['rating']); ?>/10</span>
        <span class="chip chip-gold"><?php echo e($movie['genre']); ?></span>
      </div>

      <h1 class="details-title"><?php echo e($movie['title']); ?></h1>
      <p class="desc"><?php echo e($movie['description']); ?></p>

      <div class="facts">
        <div class="fact">
          <span>Duration</span>
          <b><?php echo e($movie['duration']); ?></b>
        </div>
        <div class="fact">
          <span>Language</span>
          <b><?php echo e($movie['language']); ?></b>
        </div>
        <div class="fact">
          <span>Genre</span>
          <b><?php echo e($movie['genre']); ?></b>
        </div>
        <div class="fact">
          <span>Release Date</span>
          <b><?php echo date('d M Y', strtotime($movie['release_date'])); ?></b>
        </div>
        <div class="fact">
          <span>Ticket Price</span>
          <b><?php echo money($movie['ticket_price']); ?></b>
        </div>
        <div class="fact">
          <span>Rating</span>
          <b>&#9733; <?php echo e($movie['rating']); ?></b>
        </div>
      </div>

      <?php if ($is_now): ?>
        <a class="btn btn-gold btn-lg" href="<?php echo url('user/booking.php?movie_id=' . (int) $movie['id']); ?>">
          Book Tickets Now
        </a>
      <?php else: ?>
        <div class="panel" style="margin-top:1rem">
          <h3>Coming Soon</h3>
          <p class="desc" style="margin:0">
            Bookings open a few days before release. Add it to your wishlist and check back soon!
          </p>
        </div>
      <?php endif; ?>
    </div>

  </div>
</section>

<?php if ($is_now && $shows_by_day): ?>
  <section class="section">
    <div class="section-head">
      <h2>Showtimes</h2>
      <span class="chip chip-gold"><?php echo count($shows_by_day); ?> days available</span>
    </div>

    <?php foreach ($dates as $d): ?>
      <?php if (!isset($shows_by_day[$d['value']])) { continue; } ?>
      <div class="panel">
        <h3><?php echo e($d['label']); ?> <span class="hint" style="font-weight:400">&mdash; <?php echo e($d['sub']); ?></span></h3>

        <?php
        $by_theatre = array();
        foreach ($shows_by_day[$d['value']] as $s) {
            $by_theatre[$s['theatre_name']][] = $s;
        }
        ?>
        <?php foreach ($by_theatre as $tname => $shows): ?>
          <p class="hint" style="margin:.6rem 0 .3rem">
            &#127978; <b style="color:var(--gold)"><?php echo e($tname); ?></b>
            <?php echo e($shows[0]['screen_name'] . ' - ' . $shows[0]['location']); ?>
          </p>
          <div class="showtime-grid">
            <?php foreach ($shows as $s): ?>
              <?php
              $left = 50 - (int) $s['seats_taken'];
              $full = ($left <= 0);
              ?>
              <a href="<?php echo $full ? '#' : url('user/booking.php?show_id=' . (int) $s['id']); ?>"
                 class="showtime-btn <?php echo $full ? 'is-disabled' : ''; ?>"
                 <?php echo $full ? 'onclick="return false;" title="House full"' : ''; ?>>
                <?php echo date('g:i A', strtotime($s['show_time'])); ?>
                <small><?php echo $full ? 'House Full' : $left . ' seats left'; ?></small>
              </a>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endforeach; ?>
  </section>
<?php elseif ($is_now): ?>
  <section class="section">
    <div class="empty-state">
      <div class="big">&#128165;</div>
      <p>No shows are scheduled for this movie right now. Please check back soon.</p>
    </div>
  </section>
<?php endif; ?>

<?php require __DIR__ . '/../includes/footer.php'; ?>
