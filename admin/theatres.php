<?php
/* =============================================================
   admin/theatres.php  -  Manage cinemas
   ============================================================= */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

// ---- Toggle active / inactive ----
if (isset($_GET['toggle']) && (int) $_GET['toggle'] > 0) {
    $stmt = $pdo->prepare("UPDATE theatres SET is_active = 1 - is_active WHERE id = ?");
    $stmt->execute(array((int) $_GET['toggle']));
    flash_set('success', 'Theatre status updated.');
    redirect(url('admin/theatres.php'));
}

// ---- Delete ----
if (isset($_GET['delete']) && (int) $_GET['delete'] > 0) {
    $id  = (int) $_GET['delete'];
    $del = $pdo->prepare("DELETE FROM theatres WHERE id = ?");
    $del->execute(array($id));
    flash_set('success', 'Theatre deleted along with its shows and seats.');
    redirect(url('admin/theatres.php'));
}

$stmt = $pdo->query(
    "SELECT t.*,
            (SELECT COUNT(*) FROM seats s WHERE s.theatre_id = t.id) AS seat_count,
            (SELECT COUNT(*) FROM shows sh WHERE sh.theatre_id = t.id AND sh.show_date >= CURDATE()) AS show_count
       FROM theatres t
      ORDER BY t.name"
);
$theatres = $stmt->fetchAll();

$page_title = 'Theatres';
require __DIR__ . '/../includes/admin_header.php';
?>

<div class="toolbar-admin">
  <p class="hint" style="margin:0">
    Deleting a theatre also deletes its seats and shows. Cancelled bookings keep their history.
  </p>
  <a class="btn btn-purple btn-sm" href="<?php echo url('admin/theatre_form.php'); ?>">+ Add Theatre</a>
</div>

<?php if (empty($theatres)): ?>

  <div class="empty-state">
    <div class="big">&#127969;</div>
    <p>No theatres yet. Add one so that shows can be scheduled.</p>
  </div>

<?php else: ?>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Theatre</th>
          <th>Location</th>
          <th>Screen</th>
          <th>Seats</th>
          <th>Upcoming Shows</th>
          <th>Status</th>
          <th>Actions</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($theatres as $i => $t): ?>
          <tr>
            <td class="hint"><?php echo $i + 1; ?></td>
            <td><b><?php echo e($t['name']); ?></b></td>
            <td class="hint"><?php echo e($t['location']); ?></td>
            <td class="hint"><?php echo e($t['screen_name']); ?></td>
            <td><?php echo (int) $t['seat_count']; ?></td>
            <td><?php echo (int) $t['show_count']; ?></td>
            <td>
              <span class="status-pill status-<?php echo $t['is_active'] ? 'active' : 'inactive'; ?>">
                <?php echo $t['is_active'] ? 'Active' : 'Inactive'; ?>
              </span>
            </td>
            <td>
              <div class="action-group">
                <a class="btn btn-ghost btn-sm" href="<?php echo url('admin/theatre_form.php?id=' . (int) $t['id']); ?>">Edit</a>
                <a class="btn btn-purple btn-sm" href="<?php echo url('admin/theatre_form.php?id=' . (int) $t['id']); ?>">Seats</a>
                <a class="btn btn-ghost btn-sm" href="<?php echo url('admin/theatres.php?toggle=' . (int) $t['id']); ?>">
                  <?php echo $t['is_active'] ? 'Deactivate' : 'Activate'; ?>
                </a>
                <form method="get" action="<?php echo url('admin/theatres.php'); ?>"
                      data-confirm="Delete &quot;<?php echo e($t['name']); ?>&quot;? This also removes its seats and shows.">
                  <input type="hidden" name="delete" value="<?php echo (int) $t['id']; ?>">
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
