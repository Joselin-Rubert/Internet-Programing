<?php
/* =============================================================
   admin/movie_form.php  -  Add a new movie OR edit an existing one
   (movie_form.php            -> add)
   (movie_form.php?id=4       -> edit)
   Also handles the poster image upload.
   ============================================================= */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$movie_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$is_edit  = ($movie_id > 0);

// Default (blank) values for the "add" case
$movie = array(
    'title' => '', 'description' => '', 'duration' => '', 'language' => '',
    'genre' => '', 'rating' => '7.0', 'release_date' => date('Y-m-d'),
    'ticket_price' => '150.00', 'poster' => 'default-poster.svg', 'status' => 'now_showing'
);

if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM movies WHERE id = ?");
    $stmt->execute(array($movie_id));
    $found = $stmt->fetch();
    if (!$found) {
        flash_set('error', 'Movie not found.');
        redirect(url('admin/movies.php'));
    }
    $movie = array_merge($movie, $found);
}

$errors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $movie['title']        = trim($_POST['title'] ?? '');
    $movie['description']  = trim($_POST['description'] ?? '');
    $movie['duration']     = trim($_POST['duration'] ?? '');
    $movie['language']     = trim($_POST['language'] ?? '');
    $movie['genre']        = trim($_POST['genre'] ?? '');
    $movie['rating']       = (float) ($_POST['rating'] ?? 0);
    $movie['release_date'] = $_POST['release_date'] ?? '';
    $movie['ticket_price'] = (float) ($_POST['ticket_price'] ?? 0);
    $movie['status']       = ($_POST['status'] === 'upcoming') ? 'upcoming' : 'now_showing';

    // ---------- validation ----------
    if ($movie['title'] === '') {
        $errors[] = 'Movie title is required.';
    } elseif (strlen($movie['title']) > 150) {
        $errors[] = 'Title must be under 150 characters.';
    }
    if ($movie['description'] === '') {
        $errors[] = 'Please write a short description.';
    }
    if ($movie['duration'] === '') {
        $errors[] = 'Duration is required (for example 2h 16m).';
    }
    if ($movie['language'] === '') {
        $errors[] = 'Language is required.';
    }
    if ($movie['genre'] === '') {
        $errors[] = 'Genre is required.';
    }
    if ($movie['rating'] < 0 || $movie['rating'] > 10) {
        $errors[] = 'Rating must be between 0 and 10.';
    }
    if ($movie['release_date'] === '') {
        $errors[] = 'Release date is required.';
    }
    if ($movie['ticket_price'] <= 0) {
        $errors[] = 'Ticket price must be greater than zero.';
    }

    // ---------- poster upload ----------
    $new_poster = '';
    if (!empty($_FILES['poster']['name']) && $_FILES['poster']['error'] === UPLOAD_ERR_OK) {

        $upload_dir = __DIR__ . '/../assets/posters/';

        // Only allow real image files
        $allowed_ext = array('jpg', 'jpeg', 'png', 'gif', 'webp');
        $ext = strtolower(pathinfo($_FILES['poster']['name'], PATHINFO_EXTENSION));

        if (!in_array($ext, $allowed_ext, true)) {
            $errors[] = 'Poster must be a JPG, PNG, GIF or WEBP image.';
        } elseif ($_FILES['poster']['size'] > 2 * 1024 * 1024) {
            $errors[] = 'Poster image must be smaller than 2 MB.';
        } else {
            // Build a safe, unique file name
            $safe_name = preg_replace('/[^a-z0-9_-]/i', '-', pathinfo($_FILES['poster']['name'], PATHINFO_FILENAME));
            $file_name = strtolower($safe_name . '-' . time() . '.' . $ext);

            if (move_uploaded_file($_FILES['poster']['tmp_name'], $upload_dir . $file_name)) {
                $new_poster = $file_name;
            } else {
                $errors[] = 'Sorry, the poster could not be uploaded.';
            }
        }
    } elseif (isset($_FILES['poster']) && $_FILES['poster']['error'] === UPLOAD_ERR_INI_SIZE) {
        $errors[] = 'That image is too large for the server.';
    }

    // ---------- save ----------
    if (count($errors) === 0) {

        $poster = $new_poster !== '' ? $new_poster : $movie['poster'];

        if ($is_edit) {
            $stmt = $pdo->prepare(
                "UPDATE movies
                    SET title = ?, description = ?, duration = ?, language = ?, genre = ?,
                        rating = ?, release_date = ?, ticket_price = ?, poster = ?, status = ?
                  WHERE id = ?"
            );
            $stmt->execute(array(
                $movie['title'], $movie['description'], $movie['duration'], $movie['language'],
                $movie['genre'], $movie['rating'], $movie['release_date'], $movie['ticket_price'],
                $poster, $movie['status'], $movie_id
            ));
            flash_set('success', 'Movie "' . $movie['title'] . '" was updated.');
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO movies
                    (title, description, duration, language, genre, rating, release_date, ticket_price, poster, status)
                 VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)"
            );
            $stmt->execute(array(
                $movie['title'], $movie['description'], $movie['duration'], $movie['language'],
                $movie['genre'], $movie['rating'], $movie['release_date'], $movie['ticket_price'],
                $poster, $movie['status']
            ));
            $movie_id = (int) $pdo->lastInsertId();
            flash_set('success', 'Movie "' . $movie['title'] . '" was added successfully.');
        }

        // ---- Create demo shows so the new movie can be booked straight away ----
        if (!$is_edit && $movie['status'] === 'now_showing') {
            create_sample_shows($pdo, $movie_id);
            flash_set('success', 'Movie added. Sample shows for the next 7 days were also created.');
        }

        redirect(url('admin/movies.php'));
    }
}

/* Small helper: give a brand new movie some shows to book */
function create_sample_shows($pdo, $movie_id)
{
    $times = array('10:30:00', '13:10:00', '16:00:00', '18:45:00', '21:30:00');

    $theatres = $pdo->query("SELECT id FROM theatres WHERE is_active = 1")->fetchAll();

    $stmt = $pdo->prepare(
        "INSERT IGNORE INTO shows (movie_id, theatre_id, show_date, show_time) VALUES (?, ?, ?, ?)"
    );

    for ($d = 0; $d < 7; $d++) {
        foreach ($theatres as $t) {
            foreach ($times as $time) {
                $stmt->execute(array($movie_id, $t['id'], date('Y-m-d', strtotime("+$d day")), $time));
            }
        }
    }
}

$page_title = $is_edit ? 'Edit Movie' : 'Add Movie';
require __DIR__ . '/../includes/admin_header.php';
?>

<div class="toolbar-admin">
  <a class="btn btn-ghost btn-sm" href="<?php echo url('admin/movies.php'); ?>">&larr; Back to Movies</a>
</div>

<div class="card" style="max-width:920px">

  <?php if ($errors): ?>
    <div class="alert alert-error">
      <?php foreach ($errors as $err): ?>
        <div><?php echo e($err); ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form method="post" action="" enctype="multipart/form-data" data-validate-form novalidate>

    <div class="form-row">
      <div class="field">
        <label for="title">Movie Title *</label>
        <input type="text" id="title" name="title" value="<?php echo e($movie['title']); ?>"
               placeholder="e.g. Midnight Protocol" data-validate="required" required>
      </div>
      <div class="field">
        <label for="duration">Duration *</label>
        <input type="text" id="duration" name="duration" value="<?php echo e($movie['duration']); ?>"
               placeholder="e.g. 2h 16m" data-validate="required" required>
      </div>
    </div>

    <div class="field">
      <label for="description">Description *</label>
      <textarea id="description" name="description"
                placeholder="A short plot summary shown on the movie details page."><?php echo e($movie['description']); ?></textarea>
    </div>

    <div class="form-row">
      <div class="field">
        <label for="language">Language *</label>
        <input type="text" id="language" name="language" value="<?php echo e($movie['language']); ?>"
               placeholder="English / Hindi / Tamil" data-validate="required" required>
      </div>
      <div class="field">
        <label for="genre">Genre *</label>
        <input type="text" id="genre" name="genre" value="<?php echo e($movie['genre']); ?>"
               placeholder="Action, Thriller (separate with a comma)" data-validate="required" required>
        <p class="hint">Used by the genre filter on the home page.</p>
      </div>
      <div class="field">
        <label for="rating">Rating (0 - 10)</label>
        <input type="number" id="rating" name="rating" value="<?php echo e($movie['rating']); ?>"
               step="0.1" min="0" max="10" data-validate="required">
      </div>
    </div>

    <div class="form-row">
      <div class="field">
        <label for="release_date">Release Date *</label>
        <input type="date" id="release_date" name="release_date" value="<?php echo e($movie['release_date']); ?>" required>
      </div>
      <div class="field">
        <label for="ticket_price">Ticket Price (<?php echo CURRENCY; ?>) *</label>
        <input type="number" id="ticket_price" name="ticket_price" value="<?php echo e($movie['ticket_price']); ?>"
               step="1" min="1" data-validate="required" required>
        <p class="hint">Premium seats add <?php echo money(PREMIUM_SURCHARGE); ?> extra.</p>
      </div>
      <div class="field">
        <label for="status">Status *</label>
        <select id="status" name="status">
          <option value="now_showing" <?php echo $movie['status'] === 'now_showing' ? 'selected' : ''; ?>>Now Showing</option>
          <option value="upcoming"    <?php echo $movie['status'] === 'upcoming'    ? 'selected' : ''; ?>>Upcoming</option>
        </select>
      </div>
    </div>

    <div class="field">
      <label for="poster">Movie Poster</label>
      <input type="file" id="poster" name="poster" accept=".jpg,.jpeg,.png,.gif,.webp,image/*">
      <p class="hint">JPG / PNG / GIF / WEBP, max 2 MB. Leave empty to keep the current poster.</p>
    </div>

    <div style="display:flex;gap:1.2rem;align-items:flex-start;margin:1.2rem 0">
      <img src="<?php echo e(poster_url($movie['poster'])); ?>" alt=""
           style="width:110px;border-radius:10px;border:1px solid var(--line)">
      <p class="hint">
        Current poster.<br>
        If you leave the file field empty the existing poster (one of the built-in
        <code>.svg</code> images) stays as it is.
      </p>
    </div>

    <button type="submit" class="btn btn-purple btn-lg">
      <?php echo $is_edit ? 'Update Movie' : 'Add Movie'; ?>
    </button>
  </form>
</div>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
