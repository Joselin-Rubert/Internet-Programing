</main>

<footer class="site-footer">
  <div class="container footer-inner">
    <div>
      <p class="footer-brand"><?php echo e(SITE_NAME); ?></p>
      <p class="footer-muted">Simple Online Movie Ticket Booking System &mdash; a student project.</p>
    </div>
    <div class="footer-links">
      <a href="<?php echo url('user/index.php'); ?>">Movies</a>
      <a href="<?php echo url('user/my_bookings.php'); ?>">My Bookings</a>
      <a href="<?php echo url('admin/login.php'); ?>">Admin Panel</a>
    </div>
  </div>
  <p class="footer-bottom">&copy; <?php echo date('Y'); ?> <?php echo e(SITE_NAME); ?>. Payment is simulated &mdash; no real money is charged.</p>
</footer>

<script src="<?php echo url('assets/js/main.js'); ?>"></script>
</body>
</html>
