// ============================================================
//  Sai Deepak — app.js
//  Toggle USE_PHP_API = true when running on a PHP server
// ============================================================

const USE_PHP_API = true;         // ← set true on PHP/MySQL server
const API_URL     = 'api.php';    // ← path to api.php (set absolute path if needed)

// ===== MOVIE DATA (fallback when PHP API is off) =====
const MOVIES = [
  { id:'stellar-void',  title:'STELLAR VOID',   genre:'sci-fi',  rating:9.1, duration:'2h 28m', price:220, emoji:'🌌', color:'linear-gradient(135deg,#0a0a1a,#1a0533,#0d1f3c)', desc:'A breathtaking journey across collapsed galaxies.' },
  { id:'iron-meridian', title:'IRON MERIDIAN',  genre:'action',  rating:8.4, duration:'2h 05m', price:200, emoji:'⚔️', color:'linear-gradient(135deg,#1a0a00,#3d1a00,#1a0a0a)', desc:'War, steel, and a soldier who refuses to fall.' },
  { id:'pale-echo',     title:'PALE ECHO',      genre:'drama',   rating:8.9, duration:'1h 52m', price:240, emoji:'🎭', color:'linear-gradient(135deg,#0d0d1a,#1a1a2a,#0d1a1a)', desc:'A grieving composer discovers a lost recording.' },
  { id:'blackwood',     title:'BLACKWOOD',      genre:'horror',  rating:7.8, duration:'1h 45m', price:210, emoji:'🌲', color:'linear-gradient(135deg,#050f05,#0a1a0a,#050505)', desc:'Something ancient stirs in the forest at midnight.' },
  { id:'nova-protocol', title:'NOVA PROTOCOL',  genre:'sci-fi',  rating:8.2, duration:'2h 14m', price:205, emoji:'🤖', color:'linear-gradient(135deg,#001a1a,#003333,#001a2a)', desc:'An AI gains consciousness on the eve of war.' },
  { id:'broken-coast',  title:'BROKEN COAST',   genre:'drama',   rating:9.0, duration:'2h 01m', price:195, emoji:'🌊', color:'linear-gradient(135deg,#001a33,#002244,#001133)', desc:'Two estranged siblings reunite after a decade apart.' },
  { id:'thunderstrike', title:'THUNDERSTRIKE',  genre:'action',  rating:7.6, duration:'1h 58m', price:215, emoji:'⚡', color:'linear-gradient(135deg,#1a1400,#2a2000,#1a0a00)', desc:'A rogue agent races against time to stop a heist.' },
  { id:'crimson-dolls', title:'CRIMSON DOLLS',  genre:'horror',  rating:8.1, duration:'1h 40m', price:225, emoji:'🎪', color:'linear-gradient(135deg,#1a0000,#2a0000,#1a0011)', desc:'A haunted carnival returns to a small town.' },
];

// ============================================================
//  PHP API WRAPPER
// ============================================================
async function apiCall(action, data = {}, method = 'POST') {
  const url = method === 'GET'
    ? `${API_URL}?action=${action}&` + new URLSearchParams(data)
    : `${API_URL}?action=${action}`;
  const opts = method === 'GET'
    ? { method:'GET', credentials:'include' }
    : { method:'POST', credentials:'include',
        headers:{'Content-Type':'application/json'},
        body: JSON.stringify(data) };
  const res  = await fetch(url, opts);
  const json = await res.json();
  if (!res.ok) throw new Error(json.error || 'Request failed');
  return json;
}

async function apiPost(action, data = {}) {
  if (!USE_PHP_API) {
    return { _offline: true, error: 'USE_PHP_API is false' };
  }
  try {
    return await apiCall(action, data, 'POST');
  } catch (err) {
    console.error('apiPost error', action, err);
    return { _offline: true, error: err.message || 'Network/API error' };
  }
}

// ============================================================
//  STATE
// ============================================================
let currentUser  = JSON.parse(localStorage.getItem('cvUser'))     || null;
let bookings     = JSON.parse(localStorage.getItem('cvBookings')) || [];
let activeFilter = 'all';
let moviesCache  = null;

// ============================================================
//  INIT
// ============================================================
document.addEventListener('DOMContentLoaded', async () => {
  updateUserUI();
  const grid = document.getElementById('moviesGrid');
  if (grid) { moviesCache = await loadMovies(); renderMovies(moviesCache); }
  const bl = document.getElementById('bookingsList');
  if (bl) renderMyBookings();
});

// ============================================================
//  LOAD MOVIES (PHP or local)
// ============================================================
async function loadMovies() {
  if (!USE_PHP_API) return MOVIES;
  try {
    const res = await apiCall('movies', {}, 'GET');
    return res.movies.map(m => ({ ...m, color:'linear-gradient(135deg,#0a0a1a,#1a1a2a,#0d0d1a)' }));
  } catch { return MOVIES; }
}

// ============================================================
//  RENDER MOVIES
// ============================================================
function renderMovies(movies) {
  const grid = document.getElementById('moviesGrid');
  if (!grid) return;
  grid.innerHTML = '';
  const filtered = activeFilter === 'all' ? movies : movies.filter(m => m.genre === activeFilter);
  if (!filtered.length) {
    grid.innerHTML = '<p style="color:var(--muted);grid-column:1/-1;text-align:center;padding:40px">No films found.</p>';
    return;
  }
  filtered.forEach((movie, i) => {
    const card = document.createElement('div');
    card.className = 'movie-card';
    card.style.animationDelay = `${i * 0.07}s`;
    card.innerHTML = `
      <div class="movie-thumb" style="background:${movie.color || 'linear-gradient(135deg,#0a0a1a,#1a1a2a)'}">
        <span>${movie.emoji}</span>
        <div class="movie-thumb-overlay">
          <a href="booking.html?movie=${movie.id}" class="btn-primary" style="padding:10px 20px;font-size:0.85rem;">BOOK →</a>
        </div>
      </div>
      <div class="movie-info">
        <div class="movie-title">${movie.title}</div>
        <div class="movie-meta">
          <span class="movie-rating">★ ${movie.rating}</span>
          <span>${movie.duration}</span>
          <span>₹ ${movie.price.toFixed(2)}</span>
        </div>
        <span class="movie-genre-tag">${movie.genre}</span>
      </div>`;
    grid.appendChild(card);
  });
}

function filterMovies(genre, btn) {
  activeFilter = genre;
  document.querySelectorAll('.filter-btn').forEach(b => b.classList.remove('active'));
  btn.classList.add('active');
  renderMovies(moviesCache || MOVIES);
}

// ============================================================
//  MY BOOKINGS
// ============================================================
async function renderMyBookings() {
  const list = document.getElementById('bookingsList');
  if (!list) return;
  let items = bookings;
  if (USE_PHP_API && currentUser) {
    try {
      const res = await apiCall('bookings', { email: currentUser.email }, 'GET');
      items = res.bookings;
    } catch { items = bookings; }
  }
  if (!items.length) {
    list.innerHTML = '<p style="color:var(--muted);text-align:center;padding:40px">No bookings yet. <a href="booking.html" style="color:var(--gold)">Book your first film →</a></p>';
    return;
  }
  list.innerHTML = items.map(b => `
    <div class="booking-item" data-id="${b.id}">
      <div class="booking-item-icon">${b.movie_emoji || b.emoji || '🎬'}</div>
      <div class="booking-item-info">
        <h3>${b.movie_title || b.movie}</h3>
        <p>${b.date} · ${b.showtime || b.time} · Seats: ${b.seats}</p>
        <p style="margin-top:4px;font-size:0.8rem;color:var(--muted)">Booked for: ${b.name}</p>
      </div>
      <div class="booking-badge ${b.status === 'cancelled' ? 'cancelled' : ''}">${(b.status || 'confirmed').toUpperCase()}</div>
      ${USE_PHP_API && b.status !== 'cancelled' ? `<button class="btn-outline" style="margin-left:12px;padding:6px 14px;font-size:0.75rem" onclick="cancelBooking(${b.id})">Cancel</button>` : ''}
    </div>`).join('');
}

async function cancelBooking(id) {
  if (!confirm('Cancel this booking?')) return;
  try {
    await apiCall('cancel', { booking_id: id });
    showToast('Booking cancelled.', 'success');
    renderMyBookings();
  } catch(e) { showToast(e.message, 'error'); }
}

function showSection(id) {
  document.getElementById('moviesSection')?.classList.add('hidden');
  document.getElementById(id)?.classList.remove('hidden');
  renderMyBookings();
}

// ============================================================
//  AUTH
// ============================================================
async function handleLogin(e) {
  e.preventDefault();
  const email    = document.getElementById('loginEmail').value.trim();
  const password = document.getElementById('loginPassword').value;
  if (USE_PHP_API) {
    try {
      const res = await apiCall('login', { email, password });
      currentUser = res.user;
      localStorage.setItem('cvUser', JSON.stringify(res.user));
      updateUserUI(); closeModal('loginModal');
      showToast(`Welcome back, ${res.user.name}!`, 'success');
    } catch(e) { showToast(e.message, 'error'); }
  } else {
    const users = JSON.parse(localStorage.getItem('cvUsers')) || [];
    const user  = users.find(u => u.email === email && u.password === password);
    if (user) {
      currentUser = user; localStorage.setItem('cvUser', JSON.stringify(user));
      updateUserUI(); closeModal('loginModal');
      showToast(`Welcome back, ${user.name}!`, 'success');
    } else { showToast('Invalid credentials. Try registering first.', 'error'); }
  }
}

async function handleRegister(e) {
  e.preventDefault();
  const name     = document.getElementById('regName').value.trim();
  const email    = document.getElementById('regEmail').value.trim();
  const password = document.getElementById('regPassword').value;
  if (USE_PHP_API) {
    try {
      const res = await apiCall('register', { name, email, password });
      currentUser = res.user; localStorage.setItem('cvUser', JSON.stringify(res.user));
      updateUserUI(); closeModal('registerModal');
      showToast(`Welcome, ${name}!`, 'success');
    } catch(e) { showToast(e.message, 'error'); }
  } else {
    const users = JSON.parse(localStorage.getItem('cvUsers')) || [];
    if (users.find(u => u.email === email)) { showToast('Email already registered.', 'error'); return; }
    const user = { name, email, password };
    users.push(user); localStorage.setItem('cvUsers', JSON.stringify(users));
    currentUser = user; localStorage.setItem('cvUser', JSON.stringify(user));
    updateUserUI(); closeModal('registerModal');
    showToast(`Account created! Welcome, ${name}!`, 'success');
  }
}

function updateUserUI() {
  const el = document.getElementById('userGreeting');
  if (el && currentUser) el.textContent = currentUser.name;
}

// ============================================================
//  MODALS & TOAST
// ============================================================
function openModal(id)         { document.getElementById(id)?.classList.remove('hidden'); }
function closeModal(id)        { document.getElementById(id)?.classList.add('hidden'); }
function switchModal(from, to) { closeModal(from); openModal(to); }

document.addEventListener('click', e => {
  if (e.target.classList.contains('modal-overlay')) e.target.classList.add('hidden');
});

function showToast(msg, type = '') {
  const t = document.getElementById('toast');
  if (!t) return;
  t.textContent = msg;
  t.className = `toast ${type}`;
  t.classList.remove('hidden');
  clearTimeout(t._timer);
  t._timer = setTimeout(() => t.classList.add('hidden'), 3500);
}
