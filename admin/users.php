<?php
/* =============================================================
   admin/users.php  -  Registered users and their booking history
   ============================================================= */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$search = isset($_GET['q']) ? trim($_GET['q']) : '';

$sql = "SELECT u.*,
               (SELECT COUNT(*) FROM bookings b WHERE b.user_id = u.id) AS booking_count,
               (SELECT COALESCE(SUM(b.total_amount), 0) FROM bookings b
                 WHERE b.user_id = u.id AND b.status = 'confirmed') AS total_spent
          FROM users u WHERE 1 = 1";
$params = array();

if ($search !== '') {
    $sql .= " AND (u.full_name LIKE ? OR u.email LIKE ? OR u.phone LIKE ?)";
    $like = '%' . $search . '%';
    $params = array($like, $like, $like);
}
$sql .= " ORDER BY u.id DESC";

$stmt = $pdo->prepare($sql);
$stmt->execute($params);
$users = $stmt->fetchAll();

$total_users = (int) $pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
$new_users   = (int) $pdo->query("SELECT COUNT(*) FROM users WHERE created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)")->fetchColumn();

$page_title = 'Users';
require __DIR__ . '/../includes/admin_header.php';
?>

<div class="stat-grid">
  <div class="stat gold">
    <span>Registered Users</span>
    <b><?php echo $total_users; ?></b>
  </div>
  <div class="stat green">
    <span>Joined in last 7 days</span>
    <b><?php echo $new_users; ?></b>
  </div>
</div>

<div class="toolbar-admin">
  <form method="get" class="search-box" style="max-width:340px;width:100%">
    <input type="text" name="q" value="<?php echo e($search); ?>" placeholder="Search name, email or phone...">
  </form>
  <p class="hint" style="margin:0">Passwords are stored as hashes and are never shown.</p>
</div>

<?php if (empty($users)): ?>

  <div class="empty-state">
    <div class="big">&#128101;</div>
    <p>No users found.</p>
  </div>

<?php else: ?>

  <div class="table-wrap">
    <table>
      <thead>
        <tr>
          <th>#</th>
          <th>Name</th>
          <th>Email</th>
          <th>Phone</th>
          <th>Bookings</th>
          <th>Total Spent</th>
          <th>Joined</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($users as $i => $u): ?>
          <tr>
            <td class="hint"><?php echo (int) $u['id']; ?></td>
            <td><b><?php echo e($u['full_name']); ?></b></td>
            <td class="hint"><?php echo e($u['email']); ?></td>
            <td class="hint"><?php echo e($u['phone']); ?></td>
            <td><?php echo (int) $u['booking_count']; ?></td>
            <td class="price"><?php echo money($u['total_spent']); ?></td>
            <td class="hint"><?php echo e(date('d M Y', strtotime($u['created_at']))); ?></td>
          </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

<?php endif; ?>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
