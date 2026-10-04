<?php
/* =============================================================
   admin/theatre_form.php  -  Add / edit a theatre + seat map
   ============================================================= */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_admin();

$theatre_id = isset($_GET['id']) ? (int) $_GET['id'] : 0;
$is_edit   = ($theatre_id > 0);

$theatre = array(
    'name' => '', 'location' => '', 'screen_name' => 'Screen 1', 'is_active' => 1
);

if ($is_edit) {
    $stmt = $pdo->prepare("SELECT * FROM theatres WHERE id = ?");
    $stmt->execute(array($theatre_id));
    $found = $stmt->fetch();
    if (!$found) {
        flash_set('error', 'Theatre not found.');
        redirect(url('admin/theatres.php'));
    }
    $theatre = array_merge($theatre, $found);
}

$errors = array();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    $theatre['name']        = trim($_POST['name'] ?? '');
    $theatre['location']    = trim($_POST['location'] ?? '');
    $theatre['screen_name'] = trim($_POST['screen_name'] ?? '');
    $theatre['is_active']   = isset($_POST['is_active']) ? 1 : 0;

    $rebuild_seats = isset($_POST['rebuild_seats']);

    if ($theatre['name'] === '') {
        $errors[] = 'Theatre name is required.';
    }
    if ($theatre['screen_name'] === '') {
        $errors[] = 'Screen name is required.';
    }

    if (count($errors) === 0) {

        if ($is_edit) {
            $stmt = $pdo->prepare(
                "UPDATE theatres SET name = ?, location = ?, screen_name = ?, is_active = ? WHERE id = ?"
            );
            $stmt->execute(array(
                $theatre['name'], $theatre['location'],
                $theatre['screen_name'], $theatre['is_active'], $theatre_id
            ));
            flash_set('success', 'Theatre updated.');
        } else {
            $stmt = $pdo->prepare(
                "INSERT INTO theatres (name, location, screen_name, is_active) VALUES (?, ?, ?, ?)"
            );
            $stmt->execute(array(
                $theatre['name'], $theatre['location'],
                $theatre['screen_name'], $theatre['is_active']
            ));
            $theatre_id = (int) $pdo->lastInsertId();
            $is_edit    = true;

            // Every new theatre gets a seat map straight away
            generate_seats($pdo, $theatre_id);
            flash_set('success', 'Theatre added with 50 seats (2 regular rows + 3 premium rows).');
        }

        // Optionally wipe the seat map and build it again
        if ($rebuild_seats && $theatre_id > 0) {
            $cnt = (int) $pdo->query(
                "SELECT COUNT(*) FROM booking_details bd
                   JOIN bookings b ON b.id = bd.booking_id
                   JOIN shows    s ON s.id = b.show_id
                  WHERE s.theatre_id = " . $theatre_id
            )->fetchColumn();

            if ($cnt > 0) {
                flash_set('error', 'Seats cannot be rebuilt because this theatre already has bookings. '
                    . 'Deleting a theatre deletes its bookings too.');
            } else {
                $del = $pdo->prepare("DELETE FROM seats WHERE theatre_id = ?");
                $del->execute(array($theatre_id));
                generate_seats($pdo, $theatre_id);
                flash_set('success', 'Seat map rebuilt (50 seats).');
            }
        }

        redirect(url('admin/theatres.php'));
    }
}

// ---- Seat map preview ----
$seat_rows = array();
if ($theatre_id > 0) {
    $stmt = $pdo->prepare("SELECT * FROM seats WHERE theatre_id = ? ORDER BY seat_row, seat_number");
    $stmt->execute(array($theatre_id));
    $seat_total = 0;
    foreach ($stmt->fetchAll() as $s) {
        $seat_rows[$s['seat_row']][] = $s;
        $seat_total++;
    }
    ksort($seat_rows);
}

$page_title = $is_edit ? 'Edit Theatre' : 'Add Theatre';
require __DIR__ . '/../includes/admin_header.php';
?>

<div class="toolbar-admin">
  <a class="btn btn-ghost btn-sm" href="<?php echo url('admin/theatres.php'); ?>">&larr; Back to Theatres</a>
</div>

<div class="grid-2">

  <div class="card">
    <h3><?php echo $is_edit ? 'Theatre Details' : 'Add a New Theatre'; ?></h3>

    <?php if ($errors): ?>
      <div class="alert alert-error">
        <?php foreach ($errors as $err): ?>
          <div><?php echo e($err); ?></div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

    <form method="post" action="" data-validate-form novalidate>
      <div class="field">
        <label for="name">Theatre Name *</label>
        <input type="text" id="name" name="name" value="<?php echo e($theatre['name']); ?>"
               placeholder="e.g. CineVerse Grand" data-validate="required" required>
      </div>

      <div class="field">
        <label for="location">Location</label>
        <input type="text" id="location" name="location" value="<?php echo e($theatre['location']); ?>"
               placeholder="e.g. MG Road, Bengaluru">
      </div>

      <div class="field">
        <label for="screen_name">Screen Name *</label>
        <input type="text" id="screen_name" name="screen_name" value="<?php echo e($theatre['screen_name']); ?>"
               placeholder="e.g. Screen 1 - IMAX" data-validate="required" required>
      </div>

      <div class="field">
        <label class="checkbox">
          <input type="checkbox" name="is_active" value="1" <?php echo $theatre['is_active'] ? 'checked' : ''; ?>>
          <span>Active (visible to users)</span>
        </label>
      </div>

      <?php if ($is_edit): ?>
        <div class="field">
          <label class="checkbox">
            <input type="checkbox" name="rebuild_seats" value="1">
            <span>Rebuild the seat map (rows A-E, 10 seats each)</span>
          </label>
          <p class="hint">Only possible when this theatre has no bookings yet.</p>
        </div>
      <?php endif; ?>

      <button type="submit" class="btn btn-purple btn-block">
        <?php echo $is_edit ? 'Update Theatre' : 'Add Theatre'; ?>
      </button>
    </form>
  </div>

  <!-- Seat map preview -->
  <div class="card">
    <h3>Seat Map</h3>
    <?php if (empty($seat_rows)): ?>
      <p class="hint">
        No seats yet. A new theatre automatically gets 50 seats
        (rows A and B regular, rows C, D and E premium).
      </p>
    <?php else: ?>
      <div class="screen" style="margin:1rem 0 1.4rem">
        <div class="screen-bar"></div>
        <div class="screen-label">Screen This Way</div>
      </div>
      <div class="seat-map" style="display:block;text-align:center">
        <?php foreach ($seat_rows as $row_label => $row_seats): ?>
          <div class="seat-row" style="justify-content:center">
            <span class="seat-row-label"><?php echo e($row_label); ?></span>
            <?php foreach ($row_seats as $seat): ?>
              <span class="seat seat-<?php echo e($seat['seat_type']); ?>"
                    style="cursor:default"
                    title="<?php echo e($seat['seat_label'] . ' - ' . ucfirst($seat['seat_type'])); ?>">
                <?php echo (int) $seat['seat_number']; ?>
              </span>
            <?php endforeach; ?>
          </div>
        <?php endforeach; ?>
      </div>
      <div class="seat-legend">
        <span class="legend-item"><span class="legend-swatch legend-regular"></span> Regular</span>
        <span class="legend-item"><span class="legend-swatch legend-premium"></span> Premium</span>
      </div>
      <p class="hint" style="margin-top:.8rem"><?php echo $seat_total; ?> seats in total.</p>
    <?php endif; ?>
  </div>

</div>

<?php require __DIR__ . '/../includes/admin_footer.php'; ?>
