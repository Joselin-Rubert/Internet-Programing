/* =============================================================
   CineVerse - assets/js/main.js
   Plain JavaScript, no libraries.
   Handles: menu, live search, seat selection, price
   calculation, payment method picker, form validation.
   ============================================================= */

document.addEventListener('DOMContentLoaded', function () {

  /* ---------- 1. Mobile navigation ---------- */
  var navToggle = document.getElementById('navToggle');
  var mainNav   = document.getElementById('mainNav');
  var sidebar   = document.getElementById('adminSidebar');

  if (navToggle) {
    navToggle.addEventListener('click', function () {
      if (mainNav)  { mainNav.classList.toggle('open'); }
      if (sidebar) { sidebar.classList.toggle('open'); }
    });
  }

  // Close the menu if you click anywhere else
  document.addEventListener('click', function (e) {
    if (!mainNav || !navToggle) { return; }
    if (!mainNav.contains(e.target) && !navToggle.contains(e.target)) {
      mainNav.classList.remove('open');
    }
  });


  /* ---------- 2. Live search + genre filter on the home page ---------- */
  var searchInput = document.getElementById('movieSearch');
  var genreSelect = document.getElementById('genreFilter');

  if (searchInput) {
    searchInput.addEventListener('input', filterMovies);
  }
  if (genreSelect) {
    genreSelect.addEventListener('change', filterMovies);
  }

  function filterMovies() {
    var term  = (searchInput ? searchInput.value : '').toLowerCase().trim();
    var genre = genreSelect ? genreSelect.value : '';
    var cards = document.querySelectorAll('[data-movie-card]');
    var shown = 0;

    cards.forEach(function (card) {
      var title = (card.getAttribute('data-title') || '').toLowerCase();
      var info  = (card.getAttribute('data-info') || '').toLowerCase();
      var g     = (card.getAttribute('data-genre') || '');

      var matchesText  = title.indexOf(term) !== -1 || info.indexOf(term) !== -1;
      var matchesGenre = (genre === '' || g === genre);

      var visible = matchesText && matchesGenre;
      card.style.display = visible ? '' : 'none';
      if (visible) { shown++; }
    });

    var empty = document.getElementById('noResults');
    if (empty) { empty.style.display = shown === 0 ? '' : 'none'; }
  }


  /* ---------- 3. Seat selection + live price calculation ---------- */
  var seatArea = document.getElementById('seatMap');
  var MAX_SEATS = parseInt(document.body.getAttribute('data-max-seats') || '10', 10);

  if (seatArea) {
    var seats          = seatArea.querySelectorAll('.seat:not(.booked)');
    var selectedSeats  = [];
    var subtotalEl     = document.getElementById('sumSubtotal');
    var feeEl          = document.getElementById('sumFee');
    var totalEl        = document.getElementById('sumTotal');
    var countEl        = document.getElementById('sumCount');
    var tagsEl         = document.getElementById('seatTags');
    var hiddenInput    = document.getElementById('seatIds');
    var nextBtn        = document.getElementById('proceedBtn');
    var hintEl         = document.getElementById('seatHint');
    var feeValue       = parseFloat(document.body.getAttribute('data-fee') || '0');

    seats.forEach(function (seat) {
      seat.addEventListener('click', function () {
        var id = seat.getAttribute('data-seat-id');

        if (seat.classList.contains('selected')) {
          // click again to deselect
          seat.classList.remove('selected');
          selectedSeats = selectedSeats.filter(function (s) { return s.id !== id; });
        } else {
          if (selectedSeats.length >= MAX_SEATS) {
            showHint('You can book at most ' + MAX_SEATS + ' seats in one booking.', true);
            return;
          }
          seat.classList.add('selected');
          selectedSeats.push({
            id:    id,
            label: seat.getAttribute('data-label'),
            price: parseFloat(seat.getAttribute('data-price')),
            type:  seat.getAttribute('data-type')
          });
        }
        updateSummary();
      });
    });

    function updateSummary() {
      var subtotal = 0;
      for (var i = 0; i < selectedSeats.length; i++) {
        subtotal += selectedSeats[i].price;
      }
      var total = subtotal + (selectedSeats.length > 0 ? feeValue : 0);

      // Seat chips
      tagsEl.innerHTML = '';
      selectedSeats.forEach(function (s) {
        var chip = document.createElement('span');
        chip.className = 'seat-tag';
        chip.textContent = s.label;
        tagsEl.appendChild(chip);
      });
      if (selectedSeats.length === 0) {
        var none = document.createElement('span');
        none.className = 'empty-inline';
        none.textContent = 'No seats selected yet';
        tagsEl.appendChild(none);
      }

      countEl.textContent   = selectedSeats.length + (selectedSeats.length === 1 ? ' seat' : ' seats');
      subtotalEl.textContent = formatMoney(subtotal);
      feeEl.textContent      = formatMoney(selectedSeats.length > 0 ? feeValue : 0);
      totalEl.textContent    = formatMoney(total);

      hiddenInput.value = selectedSeats.map(function (s) { return s.id; }).join(',');
      nextBtn.disabled   = selectedSeats.length === 0;
      nextBtn.classList.toggle('is-disabled', selectedSeats.length === 0);

      if (selectedSeats.length > 0) {
        showHint(selectedSeats.length + ' seat(s) selected. Total ' + formatMoney(total) + '.', false);
      } else {
        showHint('Click on the seats you want to book.', false);
      }
    }

    function showHint(text, isError) {
      if (!hintEl) { return; }
      hintEl.textContent = text;
      hintEl.style.color = isError ? '#fca5a5' : '';
    }

    // Set the initial state (disabled Proceed button, zero totals)
    updateSummary();
  }


  /* ---------- 4. Payment method picker ---------- */
  var payOptions = document.querySelectorAll('.pay-option');
  var payInput   = document.getElementById('paymentMethod');

  payOptions.forEach(function (opt) {
    opt.addEventListener('click', function () {
      payOptions.forEach(function (o) { o.classList.remove('selected'); });
      opt.classList.add('selected');
      opt.querySelector('input').checked = true;
      if (payInput) { payInput.value = opt.getAttribute('data-method'); }
    });
  });


  /* ---------- 5. Simple form validation ---------- */
  // Add required="required" to any input that has a data-validate="required"
  var validateFields = document.querySelectorAll('[data-validate="required"]');
  validateFields.forEach(function (field) {
    field.addEventListener('blur', function () { checkField(field); });
    field.addEventListener('input', function () { clearError(field); });
  });

  function checkField(field) {
    var value = field.value.trim();
    var valid = value !== '';

    // email check
    if (valid && field.type === 'email') {
      valid = /^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(value);
    }
    // phone check (10 digits)
    if (valid && field.type === 'tel') {
      valid = value.replace(/\D/g, '').length === 10;
    }
    // number check
    if (valid && field.type === 'number') {
      valid = parseFloat(value) > 0;
    }

    if (!valid) {
      field.style.borderColor = '#f87171';
    }
    return valid;
  }

  var forms = document.querySelectorAll('form[data-validate-form]');
  forms.forEach(function (form) {
    form.addEventListener('submit', function (e) {
      var firstBad = null;
      form.querySelectorAll('[data-validate="required"]').forEach(function (field) {
        if (!checkField(field)) {
          if (!firstBad) { firstBad = field; }
        }
      });
      if (firstBad) {
        e.preventDefault();
        firstBad.focus();
        showFormError(form, 'Please correct the highlighted field(s) before continuing.');
        firstBad.scrollIntoView({ block: 'center', behavior: 'smooth' });
      } else {
        hideFormError(form);
      }
    });
  });

  // Shows a red banner at the top of a form
  function showFormError(form, message) {
    var box = form.querySelector('.form-error');
    if (!box) {
      box = document.createElement('div');
      box.className = 'alert alert-error form-error';
      form.insertBefore(box, form.firstChild);
    }
    box.textContent = message;
  }

  function hideFormError(form) {
    var box = form.querySelector('.form-error');
    if (box) { box.remove(); }
  }


  /* ---------- 6. Admin: confirm before delete ---------- */
  var deleteForms = document.querySelectorAll('form[data-confirm]');
  deleteForms.forEach(function (form) {
    form.addEventListener('submit', function (e) {
      if (!confirm(form.getAttribute('data-confirm'))) {
        e.preventDefault();
      }
    });
  });


  /* ---------- 7. Small helpers ---------- */
  function formatMoney(amount) {
    return '₹' + Number(amount).toFixed(0);
  }

  // Printing a ticket
  var printBtn = document.getElementById('printTicket');
  if (printBtn) {
    printBtn.addEventListener('click', function () { window.print(); });
  }

  // Auto-dismiss flash messages after a while
  setTimeout(function () {
    document.querySelectorAll('.alert').forEach(function (el) {
      el.style.transition = 'opacity .5s ease';
      el.style.opacity = '0';
      setTimeout(function () { el.remove(); }, 500);
    });
  }, 6000);

});
