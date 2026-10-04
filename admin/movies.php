<?php
/* =============================================================
   admin/movies.php  -  List all movies, search, delete
   ============================================================= */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

// ---- Handle delete ----
if (isset($_GET['delete']) && (int) $_GET['delete'] > 0) {
    $id = (int) $_GET['delete'];

    $stmt = $pdo->prepare("SELECT poster FROM movies WHERE id = ?");
    $stmt->execute(array($id));
    $movie = $stmt->fetch();

    if ($movie) {
        // shows, seats in booking_details etc. are removed by ON DELETE CASCADE
        $del = $pdo->prepare("DELETE FROM movies WHERE id = ?");
        $del->execute(array($id));

        // Remove the uploaded poster file, but keep the built-in .svg placeholders
        $file = __DIR__ . '/../assets/posters/' . $movie['poster'];
        if (strtolower(pathinfo($file, PATHINFO_EXTENSION)) !== 'svg' && file_exists($file)) {
            @unlink($file);
        }
        flash_set('success', 'Movie "' . $movie['title'] . '" was deleted.');
    }
    redirect(url('admin/movies.php'));
}

// ---- Search + status filter ----
$search = isset($_GET['q']) ? trim($_GET['q']) : '';
$status = isset($_GET['status']) ? $_GET['status'] : 'all';

$sql    = "SELECT m.*,
                  (SELECT COUNT(*) FROM shows s WHERE s.movie_id = m.id AND s.show_date >= CURDATE()) AS upcoming_shows,
                  (SELECT COUNT(*) FROM bookings b JOIN shows s2 ON s2.id = b.show_id
                    WHERE s2.movie_id = m.id AND b.status = 'confirmed') AS bookings
             FROM movies m WHERE 1 = 1";
$params = array();

if ($search !== '') {
    $sql .= " AND (m.title LIKE ? OR m.genre LIKE ? OR m.language LIKE ?)";
    $like = '%' . $search . '%';
    $params = array($like, $like, $like);
}
if ($status === 'now_showing' || $status === 'upcoming') {
    $sql .= " AND m.status = ?";
    $params[] = $status;
}
$sql .= " ORDER BY m.status ASC, m.title ASC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$movies = $stmt->fetchAll();

$page_title = 'Movies';
require __DIR__ . '/../includes/admin_header.php';
?>

<div class="toolbar-admin">
  <form method="get" class="search-box" style="max-width:340px;width:100%">
    <input type="text" name="q" value="<?php echo e($search); ?>" placeholder="Search movies...">
  </form>

  <div class="action-group">
    <a class="btn <?php echo $status === 'all' ? 'btn-gold' : 'btn-ghost'; ?> btn-sm"
       href="<?php echo url('admin/movies.php'); ?>">All</a>
    <a class="btn <?php echo $status === 'now_showing' ? 'btn-gold' : 'btn-ghost'; ?> btn-sm"
       href="<?php echo url('admin/movies.php?status=now_showing'); ?>">Now Showing</a>
    <a class="btn <?php echo $status === 'upcoming' ? 'btn-gold' : 'btn-ghost'; ?> btn-sm"
       href="<?php echo url('admin/movies.php?status=upcoming'); ?>">Upcoming</a>
    <a class="btn btn-purple btn-sm" href="<?php echo url('admin/movie_form.php'); ?>">+ Add Movie</a>
  </div>
</div>

<?php if (empty($movies)): ?>

  <div class="empty-state">
    <div class="big">&#127916;</div>
    <p>No movies found. Try a different search or add a new movie.</p>
  </div>

<?php else: ?>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Poster</th>
          <th>Title</th>
          <th>Genre</th>
          <th>Language</th>
          <th>Duration</th>
          <th>Price</th>
          <th>Shows</th>
          <th>Bookings</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($movies as $i => $m): ?>
          <tr>
            <td class="hint"><?php echo $i + 1; ?></td>
            <td><img class="poster-thumb" src="<?php echo e(poster_url($m['poster'])); ?>" alt=""></td>
            <td><b><?php echo e($m['title']); ?></b></td>
            <td class="hint"><?php echo e($m['genre']); ?></td>
            <td class="hint"><?php echo e($m['language']); ?></td>
            <td class="hint"><?php echo e($m['duration']); ?></td>
            <td class="price"><?php echo money($m['ticket_price']); ?></td>
            <td><?php echo (int) $m['upcoming_shows']; ?></td>
            <td><?php echo (int) $m['bookings']; ?></td>
            <td><span class="status-pill status-<?php echo e($m['status']); ?>"><?php echo e(str_replace('_', ' ', $m['status'])); ?></span></td>
            <td>
              <div class="action-group">
                <a class="btn btn-ghost btn-sm" href="<?php echo url('admin/movie_form.php?id=' . (int) $m['id']); ?>">Edit</a>
                <form method="get" action="<?php echo url('admin/movies.php'); ?>"
                      data-confirm="Delete &quot;<?php echo e($m['title']); ?>&quot;? Its shows and bookings will also be removed.">
                  <input type="hidden" name="delete" value="<?php echo (int) $m['id']; ?>">
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

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
