<?php
/* =============================================================
   user/payment.php  -  SIMULATED payment + final booking insert
   No real payment gateway is used. Card details are never stored.

   The important part is the double-booking protection at the
   bottom of this file: a FOR UPDATE lock on the show row makes
   two people click "Pay" at the same moment safe.
   ============================================================= */
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';
require_once __DIR__ . '/../includes/auth.php';

require_user();

/* ------------------------------------------------------------------
   Helper: work out (and re-check) the seats that were sent over.
   Always recalculate prices on the server - never trust the browser.
------------------------------------------------------------------- */
function load_selection($pdo, $show_id, $seat_ids_string)
{
    $seat_ids = array_filter(array_map('intval', explode(',', (string) $seat_ids_string)));
    if (empty($seat_ids)) {
        return array('error' => 'Please select at least one seat.');
    }
    if (count($seat_ids) > MAX_SEATS_PER_BOOKING) {
        return array('error' => 'You can book at most ' . MAX_SEATS_PER_BOOKING . ' seats.');
    }

    // Load the show (with movie + theatre)
    $stmt = $pdo->prepare(
        "SELECT s.*, m.title, m.poster, m.language, m.duration, m.ticket_price, m.rating,
                m.genre, t.name AS theatre_name, t.screen_name, t.location
           FROM shows s
           JOIN movies  m ON m.id = s.movie_id
           JOIN theatres t ON t.id = s.theatre_id
          WHERE s.id = ?"
    );
    $stmt->execute(array($show_id));
    $show = $stmt->fetch();
    if (!$show) {
        return array('error' => 'That show is no longer available.');
    }

    // Seats must belong to this show's theatre
    $in  = implode(',', array_fill(0, count($seat_ids), '?'));
    $sql = "SELECT * FROM seats WHERE theatre_id = ? AND id IN ($in)";
    $stmt = $pdo->prepare($sql);
    $stmt->execute(array_merge(array((int) $show['theatre_id']), $seat_ids));
    $seats = $stmt->fetchAll();

    if (count($seats) !== count($seat_ids)) {
        return array('error' => 'One or more of those seats does not belong to this theatre.');
    }

    // Seats must still be free
    $booked = get_booked_seat_ids($pdo, $show_id);
    foreach ($seats as $s) {
        if (in_array((int) $s['id'], $booked, true)) {
            return array('error' => 'Seat ' . $s['seat_label'] . ' was just booked by someone else. Please pick again.');
        }
    }

    // Show must not have started yet
    if (strtotime($show['show_date'] . ' ' . $show['show_time']) < time()) {
        return array('error' => 'This show has already started. Please pick a later showtime.');
    }

    // Calculate the price for each seat
    $subtotal = 0.0;
    $items    = array();
    foreach ($seats as $s) {
        $price      = seat_price($show, $s['seat_type']);
        $subtotal  += $price;
        $items[]    = array('seat' => $s, 'price' => $price);
    }

    $fee = CONVENIENCE_FEE;

    return array(
        'show'     => $show,
        'items'    => $items,
        'subtotal' => $subtotal,
        'fee'      => $fee,
        'total'    => $subtotal + $fee,
        'count'    => count($items)
    );
}

/* ------------------------------------------------------------------
   Step 1 - show the payment form
------------------------------------------------------------------- */
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || isset($_POST['action'])) {
    $show_id  = isset($_POST['show_id'])  ? (int) $_POST['show_id']  : 0;
    $seat_ids = isset($_POST['seat_ids']) ? $_POST['seat_ids']       : '';

    if ($show_id <= 0) {
        redirect(url('user/index.php'));
    }

    $sel = load_selection($pdo, $show_id, $seat_ids);

    if (isset($sel['error'])) {
        flash_set('error', $sel['error']);
        redirect(url('user/booking.php?show_id=' . $show_id));
    }

    $show        = $sel['show'];
    $items       = $sel['items'];
    $subtotal    = $sel['subtotal'];
    $total       = $sel['total'];
    $page_title  = 'Payment';
    require __DIR__ . '/../includes/header.php';
    ?>

    <div class="steps">
      <div class="step done"><b>1. Movie</b><?php echo e($show['title']); ?></div>
      <div class="step done"><b>2. Show</b><?php echo e($show['theatre_name']); ?></div>
      <div class="step done"><b>3. Seats</b><?php echo $sel['count']; ?> selected</div>
      <div class="step active"><b>4. Payment</b>Simulated checkout</div>
    </div>

    <div class="booking-layout">
      <div class="panel">
        <h3>Choose a Payment Method</h3>

        <form method="post" action="" id="payForm" data-validate-form novalidate>
          <input type="hidden" name="action" value="pay">
          <input type="hidden" name="show_id" value="<?php echo (int) $show['id']; ?>">
          <input type="hidden" name="seat_ids" value="<?php echo e(implode(',', array_map(function ($i) { return (int) $i['seat']['id']; }, $items))); ?>">

          <div class="pay-methods">
            <label class="pay-option selected" data-method="Card">
              <input type="radio" name="method_choice" value="Card" checked>
              <span class="ico">&#128179;</span> Credit / Debit Card
            </label>
            <label class="pay-option" data-method="UPI">
              <input type="radio" name="method_choice" value="UPI">
              <span class="ico">&#128241;</span> UPI
            </label>
            <label class="pay-option" data-method="Wallet">
              <input type="radio" name="method_choice" value="Wallet">
              <span class="ico">&#128188;</span> Wallet
            </label>
            <label class="pay-option" data-method="Cash">
              <input type="radio" name="method_choice" value="Cash">
              <span class="ico">&#128176;</span> Pay at Counter
            </label>
          </div>

          <input type="hidden" name="payment_method" id="paymentMethod" value="Card">

          <!-- Card details (only shown for the Card option) -->
          <div id="cardFields" style="margin-top:1.4rem">
            <div class="field">
              <label for="card_name">Name on Card</label>
              <input type="text" id="card_name" name="card_name" placeholder="As printed on the card" data-validate="required">
            </div>
            <div class="field">
              <label for="card_number">Card Number</label>
              <input type="text" id="card_number" name="card_number" placeholder="16 digit card number"
                     maxlength="16" inputmode="numeric" data-validate="required">
              <p class="hint">This is a simulation. Do not enter a real card number.</p>
            </div>
            <div class="form-row">
              <div class="field">
                <label for="card_expiry">Expiry (MM/YY)</label>
                <input type="text" id="card_expiry" name="card_expiry" placeholder="12/28" maxlength="5" data-validate="required">
              </div>
              <div class="field">
                <label for="card_cvv">CVV</label>
                <input type="password" id="card_cvv" name="card_cvv" placeholder="123" maxlength="4" data-validate="required">
              </div>
            </div>
          </div>

          <div class="alert alert-success" style="margin-top:1rem">
            <b>Simulated payment:</b> any details are accepted and nothing is charged or stored.
          </div>

          <button type="submit" class="btn btn-gold btn-block btn-lg">
            Pay <?php echo money($total); ?> &amp; Confirm Booking
          </button>
        </form>
      </div>

      <!-- Order summary -->
      <aside class="summary">
        <div class="summary-head">Order Summary</div>
        <div class="summary-body">
          <p style="font-weight:700;margin-bottom:.2rem"><?php echo e($show['title']); ?></p>
          <p class="hint" style="margin-bottom:1rem">
            <?php echo e($show['theatre_name']); ?><br>
            <?php echo e(show_datetime($show['show_date'], $show['show_time']));
            ?>
          </p>

          <?php foreach ($items as $i): ?>
            <div class="summary-row">
              <span>Seat <?php echo e($i['seat']['seat_label']); ?>
                <em style="font-style:normal;text-transform:capitalize">(<?php echo e($i['seat']['seat_type']); ?>)</em>
              </span>
              <b><?php echo money($i['price']); ?></b>
            </div>
          <?php endforeach; ?>

          <div class="summary-row" style="border:0"><span>Convenience fee</span><b><?php echo money(CONVENIENCE_FEE); ?></b></div>

          <div class="summary-total">
            <span>Total Payable</span>
            <b><?php echo money($total); ?></b>
          </div>

          <a class="btn btn-ghost btn-block btn-sm" href="<?php echo url('user/booking.php?show_id=' . (int) $show['id']); ?>">
            Change seats
          </a>
        </div>
      </aside>
    </div>

    <script>
      // Hide the card fields when a non-card method is picked
      document.querySelectorAll('.pay-option').forEach(function (opt) {
        opt.addEventListener('click', function () {
          var isCard = opt.getAttribute('data-method') === 'Card';
          document.getElementById('cardFields').style.display = isCard ? '' : 'none';
          document.querySelectorAll('#cardFields [data-validate]').forEach(function (f) {
            f.dataset.validate = isCard ? 'required' : '';
          });
        });
      });
    </script>

    <?php
    require __DIR__ . '/../includes/footer.php';
    exit;
}

/* ------------------------------------------------------------------
   Step 2 - the user pressed "Pay"
------------------------------------------------------------------- */
$show_id     = (int) $_POST['show_id'];
$seat_ids    = $_POST['seat_ids'] ?? '';
$method      = $_POST['payment_method'] ?? 'Card';

// Only allow the methods we listed
$allowed = array('Card', 'UPI', 'Wallet', 'Cash');
if (!in_array($method, $allowed, true)) {
    $method = 'Card';
}

$sel = load_selection($pdo, $show_id, $seat_ids);

if (isset($sel['error'])) {
    flash_set('error', $sel['error']);
    redirect(url('user/booking.php?show_id=' . $show_id));
}

$show  = $sel['show'];
$items = $sel['items'];

try {
    $pdo->beginTransaction();

    /* ---------------------------------------------------------------
       DOUBLE-BOOKING PROTECTION
       Lock the show row so only one booking for this show can be
       created at a time. Everyone else waits until we commit.
    ----------------------------------------------------------------*/
    $lock = $pdo->prepare("SELECT id FROM shows WHERE id = ? FOR UPDATE");
    $lock->execute(array($show_id));

    // Re-check the seats now that we hold the lock
    $still_booked = get_booked_seat_ids($pdo, $show_id);
    foreach ($items as $i) {
        if (in_array((int) $i['seat']['id'], $still_booked, true)) {
            $pdo->rollBack();
            flash_set('error', 'Seat ' . $i['seat']['seat_label'] . ' was just booked by someone else. Please choose again.');
            redirect(url('user/booking.php?show_id=' . $show_id));
        }
    }

    // Find a booking code that is not already used
    do {
        $code = make_booking_code();
        $chk  = $pdo->prepare("SELECT id FROM bookings WHERE booking_code = ?");
        $chk->execute(array($code));
        $taken = (bool) $chk->fetch();
    } while ($taken);

    // 1) main booking row
    $stmt = $pdo->prepare(
        "INSERT INTO bookings
            (booking_code, user_id, show_id, seats_count, subtotal, convenience_fee, total_amount, payment_method, payment_status, status)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'paid', 'confirmed')"
    );
    $stmt->execute(array(
        $code,
        $_SESSION['user_id'],
        $show_id,
        $sel['count'],
        $sel['subtotal'],
        $sel['fee'],
        $sel['total'],
        $method
    ));
    $booking_id = (int) $pdo->lastInsertId();

    // 2) one row per seat
    $seat_stmt = $pdo->prepare(
        "INSERT INTO booking_details (booking_id, seat_id, seat_label, seat_price, is_cancelled)
         VALUES (?, ?, ?, ?, 0)"
    );
    foreach ($items as $i) {
        $seat_stmt->execute(array($booking_id, $i['seat']['id'], $i['seat']['seat_label'], $i['price']));
    }

    $pdo->commit();

    flash_set('success', 'Booking confirmed! Your booking ID is ' . $code . '.');
    redirect(url('user/confirmation.php?code=' . urlencode($code)));

} catch (Exception $e) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    flash_set('error', 'Sorry, the booking could not be completed. Please try again.');
    redirect(url('user/booking.php?show_id=' . $show_id));
}
