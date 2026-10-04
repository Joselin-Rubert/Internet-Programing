<?php
/* =============================================================
   admin/show_form.php  -  Add / edit one showtime
   ============================================================= */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$show_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$is_edit = ($show_id > 0);

$show = array(
    'movie_id' => '', 'theatre_id' => '',
    'show_date' => date('Y-m-d'), 'show_time' => '18:00'
);

if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM shows WHERE id = ?");
    $stmt->execute(array($show_id));
    $found = $stmt->fetch();
    if (!$found) {
        flash_set('error', 'Showtime not found.');
        redirect(url('admin/shows.php'));
    }
    // the <input type="time"> field needs HH:MM, the database stores HH:MM:SS
    $show = array_merge($show, $found);
    $show['show_time'] = substr($found['show_time'], 0, 5);
}

$movies   = $pdo->query("SELECT id, title FROM movies ORDER BY status, title")->fetchAll();
$theatres = $pdo->query("SELECT id, name FROM theatres ORDER BY name")->fetchAll();

$errors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $show['movie_id']   = (int) ($_POST['movie_id'] ?? 0);
    $show['theatre_id'] = (int) ($_POST['theatre_id'] ?? 0);
    $show['show_date']  = $_POST['show_date'] ?? '';
    $show['show_time']  = $_POST['show_time'] ?? '';

    if ($show['movie_id'] <= 0) {
        $errors[] = 'Please choose a movie.';
    }
    if ($show['theatre_id'] <= 0) {
        $errors[] = 'Please choose a theatre.';
    }
    if ($show['show_date'] === '') {
        $errors[] = 'Please choose a date.';
    }
    if ($show['show_time'] === '') {
        $errors[] = 'Please choose a time.';
    }

    if (count($errors) === 0) {
        try {
            if ($is_edit) {
                $stmt = $pdo->prepare(
                    "UPDATE shows SET movie_id = ?, theatre_id = ?, show_date = ?, show_time = ? WHERE id = ?"
                );
                $stmt->execute(array(
                    $show['movie_id'], $show['theatre_id'],
                    $show['show_date'], $show['show_time'] . ':00', $show_id
                ));
                flash_set('success', 'Showtime updated.');
            } else {
                $stmt = $pdo->prepare(
                    "INSERT INTO shows (movie_id, theatre_id, show_date, show_time) VALUES (?, ?, ?, ?)"
                );
                $stmt->execute(array(
                    $show['movie_id'], $show['theatre_id'],
                    $show['show_date'], $show['show_time'] . ':00'
                ));
                flash_set('success', 'Showtime added.');
            }
            redirect(url('admin/shows.php?date=' . $show['show_date']));
        } catch (PDOException $e) {
            // 23000 = duplicate key, the same show already exists
            if ($e->getCode() == '23000') {
                $errors[] = 'That movie already has a show at this theatre, date and time.';
            } else {
                $errors[] = 'The showtime could not be saved.';
            }
        }
    }
}

$page_title = $is_edit ? 'Edit Showtime' : 'Add Showtime';
require __DIR__ . '/../includes/admin_header.php';
?>

<div class="toolbar-admin">
  <a class="btn btn-ghost btn-sm" href="<?php echo url('admin/shows.php'); ?>">&larr; Back to Showtimes</a>
</div>

<div class="card" style="max-width:720px">
  <h3><?php echo $is_edit ? 'Edit Showtime' : 'Add a New Showtime'; ?></h3>

  <?php if ($errors): ?>
    <div class="alert alert-error">
      <?php foreach ($errors as $err): ?>
        <div><?php echo e($err); ?></div>
      <?php endforeach; ?>
    </div>
  <?php endif; ?>

  <form method="post" action="" data-validate-form novalidate>
    <div class="field">
      <label for="movie_id">Movie *</label>
      <select id="movie_id" name="movie_id" required>
        <option value="">-- Select a movie --</option>
        <?php foreach ($movies as $m): ?>
          <option value="<?php echo (int) $m['id']; ?>"
            <?php echo (int) $show['movie_id'] === (int) $m['id'] ? 'selected' : ''; ?>>
            <?php echo e($m['title']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="field">
      <label for="theatre_id">Theatre *</label>
      <select id="theatre_id" name="theatre_id" required>
        <option value="">-- Select a theatre --</option>
        <?php foreach ($theatres as $t): ?>
          <option value="<?php echo (int) $t['id']; ?>"
            <?php echo (int) $show['theatre_id'] === (int) $t['id'] ? 'selected' : ''; ?>>
            <?php echo e($t['name']); ?>
          </option>
        <?php endforeach; ?>
      </select>
    </div>

    <div class="form-row">
      <div class="field">
        <label for="show_date">Date *</label>
        <input type="date" id="show_date" name="show_date" value="<?php echo e($show['show_date']); ?>" required>
      </div>
      <div class="field">
        <label for="show_time">Time *</label>
        <input type="time" id="show_time" name="show_time" value="<?php echo e($show['show_time']); ?>" required>
      </div>
    </div>

    <button type="submit" class="btn btn-purple btn-block btn-lg">
      <?php echo $is_edit ? 'Update Showtime' : 'Add Showtime'; ?>
    </button>
  </form>
</div>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
