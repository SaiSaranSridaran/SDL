// ============================================================
//  CineVault — booking.js
//  3-step booking flow: Movie → Seats → Confirm & Pay
//  Saves booking to PHP/MySQL; falls back to localStorage.
// ============================================================

// ----- CONSTANTS -----
const ROWS     = ['A','B','C','D','E','F','G'];
const COLS     = 10;
const TAKEN    = ['A2','A3','B5','C1','C8','D4','D5','E7','F2','F9','G3','G4'];
const TIMES    = ['10:30 AM','1:00 PM','3:45 PM','6:15 PM','9:00 PM'];

// ----- STATE -----
let selMovie  = null;
let selDate   = null;
let selTime   = null;
let selSeats  = [];

// ============================================================
//  INIT
// ============================================================
document.addEventListener('DOMContentLoaded', () => {
  renderMovieSelect();
  renderDatePills();
  renderSeatMap();
  checkURLParam();
  prefillUser();
});

function checkURLParam() {
  const id = new URLSearchParams(window.location.search).get('movie');
  if (!id) return;
  const movie = MOVIES.find(m => m.id === id);
  if (movie) {
    selMovie = movie;
    document.getElementById('showtimeSection')?.classList.remove('hidden');
    setTimeout(() => {
      const card = document.querySelector(`.movie-select-card[data-id="${id}"]`);
      if (card) card.classList.add('selected');
    }, 80);
  }
}

function prefillUser() {
  if (!currentUser) return;
  const n = document.getElementById('confirmName');
  const e = document.getElementById('confirmEmail');
  if (n) n.value = currentUser.name  || '';
  if (e) e.value = currentUser.email || '';
}

// ============================================================
//  STEP 1 — Select Movie
// ============================================================
function renderMovieSelect() {
  const grid = document.getElementById('movieSelectGrid');
  if (!grid) return;
  grid.innerHTML = MOVIES.map(m => `
    <div class="movie-select-card" data-id="${m.id}" onclick="pickMovie('${m.id}', this)">
      <div style="font-size:2.4rem;margin-bottom:8px">${m.emoji}</div>
      <h4>${m.title}</h4>
      <p>${m.duration} &nbsp;·&nbsp; ★${m.rating}</p>
      <p style="margin-top:4px;color:var(--gold);font-size:.75rem">₹${m.price} / seat</p>
    </div>`).join('');
}

function pickMovie(id, el) {
  selMovie = MOVIES.find(m => m.id === id);
  document.querySelectorAll('.movie-select-card').forEach(c => c.classList.remove('selected'));
  el.classList.add('selected');
  document.getElementById('showtimeSection')?.classList.remove('hidden');
  selDate = null; selTime = null;
  document.querySelectorAll('.date-pill,.time-pill').forEach(p => p.classList.remove('selected'));
  updateContinueBtn();
}

// ============================================================
//  STEP 1 — Date Picker
// ============================================================
function renderDatePills() {
  const c = document.getElementById('datePills');
  if (!c) return;
  const today = new Date();
  c.innerHTML = Array.from({ length: 7 }, (_, i) => {
    const d = new Date(today);
    d.setDate(today.getDate() + i);
    const label = i === 0 ? 'Today' : i === 1 ? 'Tomorrow'
      : d.toLocaleDateString('en-US', { weekday:'short', month:'short', day:'numeric' });
    const val = d.toISOString().slice(0, 10);
    return `<button class="date-pill" data-val="${val}" onclick="pickDate(this)">${label}</button>`;
  }).join('');
}

function pickDate(el) {
  document.querySelectorAll('.date-pill').forEach(p => p.classList.remove('selected'));
  el.classList.add('selected');
  selDate = el.dataset.val;
  renderTimePills();
}

function renderTimePills() {
  const c = document.getElementById('timePills');
  if (!c) return;
  c.innerHTML = TIMES.map(t => {
    const seats = Math.floor(Math.random() * 18 + 5);
    return `<button class="time-pill" onclick="pickTime(this,'${t}')">
      ${t}<span>${seats} seats left</span>
    </button>`;
  }).join('');
}

function pickTime(el, time) {
  document.querySelectorAll('.time-pill').forEach(p => p.classList.remove('selected'));
  el.classList.add('selected');
  selTime = time;
  updateContinueBtn();
}

function updateContinueBtn() {
  const btn = document.getElementById('continueToSeats');
  if (btn) btn.disabled = !(selMovie && selDate && selTime);
}

// ============================================================
//  STEP 2 — Seat Map
// ============================================================
function renderSeatMap() {
  const map = document.getElementById('seatMap');
  if (!map) return;
  map.innerHTML = ROWS.map(row =>
    `<div class="seat-row">
      <span class="seat-row-label">${row}</span>
      ${Array.from({ length: COLS }, (_, i) => {
        const id     = `${row}${i + 1}`;
        const taken  = TAKEN.includes(id);
        const gap    = i === 4 ? '<div class="seat-gap"></div>' : '';
        return `${gap}<div class="seat${taken ? ' taken' : ''}" data-id="${id}"
          onclick="toggleSeat(this,'${id}',${taken})" title="${id}"></div>`;
      }).join('')}
    </div>`
  ).join('');
}

function toggleSeat(el, id, taken) {
  if (taken) return;
  const idx = selSeats.indexOf(id);
  if (idx > -1) {
    selSeats.splice(idx, 1);
    el.classList.remove('selected');
  } else {
    if (selSeats.length >= 8) { showToast('Max 8 seats per booking.', 'error'); return; }
    selSeats.push(id);
    el.classList.add('selected');
  }
  const disp = document.getElementById('selectedSeatsDisplay');
  if (disp) disp.textContent = selSeats.length ? selSeats.join(', ') : 'None';
  const btn = document.getElementById('continueToConfirm');
  if (btn) btn.disabled = selSeats.length === 0;
}

// ============================================================
//  STEP 3 — Order Summary
// ============================================================
function buildConfirmCard() {
  const card = document.getElementById('confirmationCard');
  if (!card || !selMovie) return;
  const total = (selSeats.length * selMovie.price).toFixed(2);
  card.innerHTML = `
    <h3>${selMovie.emoji} ${selMovie.title}</h3>
    <div class="confirm-row"><span>Date</span><span>${selDate}</span></div>
    <div class="confirm-row"><span>Showtime</span><span>${selTime}</span></div>
    <div class="confirm-row"><span>Seats (${selSeats.length})</span><span>${selSeats.join(', ')}</span></div>
    <div class="confirm-row"><span>Price per seat</span><span>₹${selMovie.price}</span></div>
    <div class="confirm-row confirm-total"><span>Total</span><span>₹${total}</span></div>`;
  prefillUser();
}

// ============================================================
//  STEP NAVIGATION
// ============================================================
function requireLogin() {
  if (!currentUser) {
    showToast('Please sign in to continue.', 'error');
    openModal('loginModal');
    return false;
  }
  return true;
}

function goToStep(n) {
  if (!requireLogin() && n > 1) return;

  if (n === 2) {
    if (!selMovie)  { showToast('Please select a film.',     'error'); return; }
    if (!selDate)   { showToast('Please select a date.',     'error'); return; }
    if (!selTime)   { showToast('Please select a showtime.', 'error'); return; }
  }
  if (n === 3 && selSeats.length === 0) { showToast('Select at least one seat.', 'error'); return; }

  [1,2,3].forEach(i => {
    document.getElementById(`step${i}`)?.classList.add('hidden');
    const ind = document.getElementById(`step${i}ind`);
    if (ind) { ind.classList.remove('active','done'); if (i < n) ind.classList.add('done'); }
  });

  document.getElementById(`step${n}`)?.classList.remove('hidden');
  document.getElementById(`step${n}ind`)?.classList.add('active');
  if (n === 3) buildConfirmCard();
  window.scrollTo({ top: 0, behavior: 'smooth' });
}

// ============================================================
//  SUBMIT — PHP first, localStorage fallback
// ============================================================
async function submitBooking() {
  if (!currentUser) {
    showToast('You must sign in before submitting booking.', 'error');
    openModal('loginModal');
    return;
  }

  const name  = document.getElementById('confirmName')?.value.trim();
  const email = document.getElementById('confirmEmail')?.value.trim();
  const card  = document.getElementById('cardNumber')?.value.replace(/\s/g,'');
  const exp   = document.getElementById('cardExpiry')?.value.trim();
  const cvv   = document.getElementById('cardCVV')?.value.trim();

  if (!name || !email)     { showToast('Name and email are required.',     'error'); return; }
  if (!card || card.length < 16) { showToast('Enter a valid 16-digit card number.', 'error'); return; }
  if (!exp)                { showToast('Enter card expiry.',               'error'); return; }
  if (!cvv || cvv.length < 3)   { showToast('Enter a valid CVV.',         'error'); return; }

  const total   = (selSeats.length * selMovie.price).toFixed(2);
  const payload = {
    movie_id:    selMovie.id,
    movie_title: selMovie.title,
    movie_emoji: selMovie.emoji,
    date:        selDate,
    showtime:    selTime,
    seats:       selSeats.join(','),
    name,
    email,
    total,
    user_id:     currentUser?.id || null,
  };

  // Show spinner on button
  const payBtn = document.querySelector('.booking-step:not(.hidden) .btn-primary[onclick="submitBooking()"]');
  if (payBtn) { payBtn.disabled = true; payBtn.textContent = 'Processing…'; }

  const data = await apiPost('booking', payload);

  if (payBtn) { payBtn.disabled = false; payBtn.textContent = 'PAY & CONFIRM 🎬'; }

  if (!data._offline && data.success) {
    showSuccessModal(payload, data.booking_id);
  } else {
    // localStorage fallback
    const all = JSON.parse(localStorage.getItem('cvBookings')) || [];
    const localBooking = { ...payload, id: Date.now(), time: selTime };
    all.unshift(localBooking);
    localStorage.setItem('cvBookings', JSON.stringify(all));
    showSuccessModal(payload, localBooking.id);
    if (data._offline) console.info('Booking saved locally (PHP unavailable).');
  }
}

function showSuccessModal(b, id) {
  const details = document.getElementById('successDetails');
  if (details) {
    details.innerHTML = `
      <p style="color:var(--muted);margin-bottom:6px">${b.movie_emoji} <strong style="color:var(--white)">${b.movie_title}</strong></p>
      <p style="color:var(--muted);margin-bottom:6px">${b.date} &nbsp;·&nbsp; ${b.showtime}</p>
      <p style="color:var(--muted);margin-bottom:16px">Seats: <strong style="color:var(--white)">${b.seats}</strong></p>
      <p style="font-family:var(--font-display);font-size:1.6rem;letter-spacing:2px;color:var(--gold)">Total Paid: ₹${b.total}</p>
      <p style="color:var(--muted);font-size:.8rem;margin-top:8px">Booking #${id}</p>`;
  }
  openModal('successModal');
}

// ============================================================
//  CARD FORMATTER
// ============================================================
function formatCard(input) {
  const v = input.value.replace(/\D/g,'').substring(0,16);
  input.value = v.match(/.{1,4}/g)?.join(' ') || v;
}
