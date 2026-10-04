<?php
/* =============================================================
   user/index.php  -  Home page
   Hero banner, search, genre filter, Now Showing, Upcoming.
   ============================================================= */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

$page_title = 'Home';

// ---- Who is visiting? Used for the welcome greeting in the hero ----
$viewer      = current_user($pdo);
$first_name  = '';
if ($viewer) {
    $name_parts = preg_split('/\s+/', trim($viewer['full_name']), -1, PREG_SPLIT_NO_EMPTY);
    $first_name = $name_parts[0] ?? '';
}

// A brand new account gets "Welcome", everyone else "Welcome back".
$just_registered = !empty($_SESSION['just_registered']);
unset($_SESSION['just_registered']);

// ---- Pull all movies, split by status ----
$stmt = $pdo->query("SELECT * FROM movies ORDER BY status ASC, rating DESC");
$all_movies = $stmt->fetchAll();

$now_showing = array();
$upcoming    = array();
foreach ($all_movies as $m) {
    if ($m['status'] === 'now_showing') {
        $now_showing[] = $m;
    } else {
        $upcoming[] = $m;
    }
}

// ---- Genre list for the filter dropdown ----
$genres = array();
foreach ($all_movies as $m) {
    foreach (explode(',', $m['genre']) as $g) {
        $g = trim($g);
        if ($g !== '') {
            $genres[$g] = $g;
        }
    }
}
ksort($genres);

// ---- Small numbers for the hero strip ----
$total_movies  = count($all_movies);
$total_theatres = (int) $pdo->query("SELECT COUNT(*) FROM theatres WHERE is_active = 1")->fetchColumn();
$total_shows    = (int) $pdo->query("SELECT COUNT(*) FROM shows WHERE show_date >= CURDATE()")->fetchColumn();

/* Reused card markup for both sections */
function render_movie_card($m)
{
    $detail_url = url('user/movie_details.php?id=' . (int) $m['id']);
    $book_url   = url('user/booking.php?movie_id=' . (int) $m['id']);
    $is_now     = ($m['status'] === 'now_showing');
    ?>
    <article class="movie-card"
             data-movie-card
             data-title="<?php echo e($m['title']); ?>"
             data-genre="<?php echo e(trim($m['genre'])); ?>"
             data-info="<?php echo e($m['language'] . ' ' . $m['genre'] . ' ' . $m['duration']); ?>">

      <div class="poster">
        <img src="<?php echo e(poster_url($m['poster'])); ?>" alt="<?php echo e($m['title']); ?> poster" loading="lazy">
        <span class="badge <?php echo $is_now ? '' : 'badge-upcoming'; ?>">
          <?php echo $is_now ? 'Now Showing' : 'Upcoming'; ?>
        </span>
        <span class="rating-pill">&#9733; <?php echo e($m['rating']); ?></span>
        <div class="poster-overlay">
          <a class="btn btn-gold btn-sm" href="<?php echo $is_now ? $book_url : $detail_url; ?>">
            <?php echo $is_now ? 'Book Tickets' : 'View Details'; ?>
          </a>
        </div>
      </div>

      <div class="movie-body">
        <h3 title="<?php echo e($m['title']); ?>"><?php echo e($m['title']); ?></h3>
        <div class="movie-meta">
          <span><?php echo e($m['genre']); ?></span>
          <span><?php echo e($m['language']); ?></span>
          <span><?php echo e($m['duration']); ?></span>
        </div>
        <div class="movie-foot">
          <a class="price" href="<?php echo $detail_url; ?>"><?php echo money($m['ticket_price']); ?> onwards</a>
          <a class="btn btn-ghost btn-sm" href="<?php echo $detail_url; ?>">Details</a>
        </div>
      </div>

    </article>
    <?php
}

require __DIR__ . '/../includes/header.php';
?>

<!-- ===================== HERO BANNER ===================== -->
<section class="hero">
  <span class="hero-eyebrow">Premium Cinema Experience</span>
  <?php if ($viewer): ?>
    <p class="hero-greeting">
      <?php echo $just_registered ? 'Welcome' : 'Welcome back,'; ?>
      <b><?php echo e($first_name); ?></b>
      <span class="hero-greeting-note">
        <?php echo $just_registered ? 'your account is ready.' : 'glad to see you again.'; ?>
      </span>
    </p>
  <?php endif; ?>
  <h1>Your seat to the <span>big screen</span> is waiting.</h1>
  <p>
    Pick a movie, choose your theatre and showtime, grab the perfect seats and pay in seconds.
    No queues, no phone calls.
  </p>
  <div class="hero-actions">
    <a class="btn btn-gold btn-lg" href="#now-showing">Browse Now Showing</a>
    <a class="btn btn-ghost btn-lg" href="#upcoming">Coming Soon</a>
  </div>

  <div class="hero-stats">
    <div><b><?php echo $total_movies; ?></b><span>Movies Listed</span></div>
    <div><b><?php echo $total_theatres; ?></b><span>Theatres</span></div>
    <div><b><?php echo $total_shows; ?></b><span>Shows This Week</span></div>
  </div>
</section>

<!-- ===================== SEARCH + FILTER ===================== -->
<div class="toolbar">
  <div class="search-box">
    <input type="text" id="movieSearch" placeholder="Search by movie name, language or genre...">
  </div>
  <select id="genreFilter" style="max-width:220px">
    <option value="">All genres</option>
    <?php foreach ($genres as $g): ?>
      <option value="<?php echo e($g); ?>"><?php echo e($g); ?></option>
    <?php endforeach; ?>
  </select>
</div>

<!-- ===================== NOW SHOWING ===================== -->
<section class="section" id="now-showing">
  <div class="section-head">
    <h2>Now Showing</h2>
    <span class="chip chip-gold"><?php echo count($now_showing); ?> movies</span>
  </div>

  <div class="movie-grid">
    <?php foreach ($now_showing as $m) { render_movie_card($m); } ?>
    <div class="empty-state" id="noResults" style="display:none">
      <div class="big">&#128269;</div>
      <p>No movies match your search. Try a different keyword or genre.</p>
    </div>
  </div>
</section>

<!-- ===================== UPCOMING ===================== -->
<section class="section" id="upcoming">
  <div class="section-head">
    <h2>Upcoming Movies</h2>
    <span class="chip">Releasing soon</span>
  </div>

  <div class="movie-grid">
    <?php foreach ($upcoming as $m) { render_movie_card($m); } ?>
  </div>
</section>

<?php require __DIR__ . '/../includes/footer.php'; ?>
