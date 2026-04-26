// ============================================================
//  Sai Deepak — app.js
//  Toggle USE_PHP_API = true when running on a PHP server
// ============================================================

const USE_PHP_API = true;         // ← set true on PHP/MySQL server
const API_URL     = 'api.php';    // ← path to api.php

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
    ? { method: 'GET', credentials: 'include' }
    : { method: 'POST', credentials: 'include',
        headers: { 'Content-Type': 'application/json' },
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
  if (grid) {
    moviesCache = await loadMovies();
    await loadRatings();
    renderMovies(moviesCache);
  }
  const bl = document.getElementById('bookingsList');
  if (bl && currentUser) renderMyBookings();
});

// ============================================================
//  LOAD MOVIES (PHP or local)
// ============================================================
async function loadMovies() {
  if (!USE_PHP_API) return MOVIES;
  try {
    const res = await apiCall('movies', {}, 'GET');
    return res.movies.map(m => ({
      ...m,
      color: 'linear-gradient(135deg,#0a0a1a,#1a1a2a,#0d0d1a)',
      price: parseFloat(m.price),
      rating: parseFloat(m.rating),
    }));
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
    const userRating      = getUserRating(movie.id);
    const userRatingText  = userRating ? `★ ${userRating.stars}/5` : 'Rate';
    const userRatingStyle = userRating ? 'color:var(--gold);' : 'color:var(--muted);';
    const avgRating       = getAverageRating(movie.id);
    const ratingCount     = getRatingCount(movie.id);
    const displayRating   = avgRating !== null ? avgRating : movie.rating;
    const ratingLabel     = ratingCount > 0
      ? `★ ${displayRating} <span style="font-size:0.7rem;color:var(--muted)">(${ratingCount} rating${ratingCount > 1 ? 's' : ''})</span>`
      : `★ ${displayRating}`;
    const rateBtn = hasBookedMovie(movie.id)
      ? `<button class="btn-outline" style="padding:5px 12px;font-size:0.75rem;border-color:var(--gold);${userRatingStyle}" onclick="openRatingModal('${movie.id}')">${userRatingText}</button>`
      : '';
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
          <span class="movie-rating">${ratingLabel}</span>
          <span>${movie.duration}</span>
          <span>₹ ${parseFloat(movie.price).toFixed(0)}</span>
        </div>
        <div style="margin-top:8px;display:flex;justify-content:space-between;align-items:center;">
          <span class="movie-genre-tag">${movie.genre}</span>
          ${rateBtn}
        </div>
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

  list.innerHTML = '<p style="color:var(--muted);padding:20px 0;">Loading bookings…</p>';

  let items = bookings;
  if (USE_PHP_API && currentUser) {
    try {
      const res = await apiCall('bookings', { email: currentUser.email }, 'GET');
      items = res.bookings || [];
    } catch {
      items = bookings;
    }
  }

  if (!items.length) {
    list.innerHTML = '<p style="color:var(--muted);text-align:center;padding:40px">No bookings yet. <a href="booking.html" style="color:var(--gold)">Book your first film →</a></p>';
    return;
  }

  list.innerHTML = items.map(b => {
    // Support both PHP fields (movie_title, showtime) and localStorage fields (movie, time)
    const title    = b.movie_title || b.movie || 'Unknown Film';
    const showtime = b.showtime    || b.time  || '';
    const emoji    = b.movie_emoji || b.emoji || '🎬';
    const status   = (b.status || 'confirmed').toLowerCase();
    return `
    <div class="booking-item" data-id="${b.id}">
      <div class="booking-item-icon">${emoji}</div>
      <div class="booking-item-info">
        <h3>${title}</h3>
        <p>${b.date || ''} · ${showtime} · Seats: ${b.seats || ''}</p>
        <p style="margin-top:4px;font-size:0.8rem;color:var(--muted)">Booked for: ${b.name || ''}</p>
      </div>
      <div class="booking-badge ${status === 'cancelled' ? 'cancelled' : ''}">${status.toUpperCase()}</div>
      ${USE_PHP_API && status !== 'cancelled'
        ? `<button class="btn-outline" style="margin-left:12px;padding:6px 14px;font-size:0.75rem" onclick="cancelBooking(${b.id})">Cancel</button>`
        : ''}
    </div>`;
  }).join('');
}

async function cancelBooking(id) {
  if (!confirm('Cancel this booking?')) return;
  try {
    await apiCall('cancel', { booking_id: id });
    showToast('Booking cancelled.', 'success');
    renderMyBookings();
  } catch(e) { showToast(e.message, 'error'); }
}

// ============================================================
//  EMAIL VALIDATION
// ============================================================
function isValidEmail(email) {
  const regex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
  return regex.test(email);
}

// ============================================================
//  MOVIE RATINGS  — DB-backed, only booked users can rate
// ============================================================
let currentRatingMovieId = null;
let ratingsCache = { averages: {}, user_ratings: {}, booked_movies: [] };

/** Load all rating data from DB (or localStorage fallback) */
async function loadRatings() {
  if (!currentUser) { ratingsCache = { averages: {}, user_ratings: {}, booked_movies: [] }; return; }

  if (USE_PHP_API) {
    try {
      const res = await apiCall('get_ratings', { user_id: currentUser.id }, 'GET');
      ratingsCache = {
        averages:      res.averages      || {},
        user_ratings:  res.user_ratings  || {},
        booked_movies: res.booked_movies || [],
      };
      return;
    } catch (e) { console.warn('ratings API failed, using localStorage', e); }
  }

  // localStorage fallback
  const all      = JSON.parse(localStorage.getItem('cvRatings'))  || [];
  const myBooked = JSON.parse(localStorage.getItem('cvBookings')) || [];

  const averages     = {};
  const user_ratings = {};
  const booked_movies = [...new Set(myBooked.filter(b => b.status !== 'cancelled').map(b => b.movie_id))];

  all.forEach(r => {
    if (!averages[r.movieId]) averages[r.movieId] = { sum: 0, total: 0 };
    averages[r.movieId].sum   += r.stars;
    averages[r.movieId].total += 1;
    if (r.userId === (currentUser.id || currentUser.email)) {
      user_ratings[r.movieId] = { stars: r.stars, review: r.review };
    }
  });
  // Convert sum→avg
  Object.keys(averages).forEach(id => {
    const a = averages[id];
    averages[id] = { avg: parseFloat((a.sum / a.total).toFixed(1)), total: a.total };
  });

  ratingsCache = { averages, user_ratings, booked_movies };
}

function hasBookedMovie(movieId) {
  return ratingsCache.booked_movies.includes(movieId);
}

function getUserRating(movieId) {
  return ratingsCache.user_ratings[movieId] || null;
}

function getAverageRating(movieId) {
  return ratingsCache.averages[movieId]?.avg ?? null;
}

function getRatingCount(movieId) {
  return ratingsCache.averages[movieId]?.total ?? 0;
}

function openRatingModal(movieId) {
  if (!currentUser) {
    showToast('Please sign in to rate movies.', 'error');
    openModal('loginModal');
    return;
  }
  if (!hasBookedMovie(movieId)) {
    showToast('You can only rate movies you have booked.', 'error');
    return;
  }

  const movie = moviesCache?.find(m => m.id === movieId) || MOVIES.find(m => m.id === movieId);
  if (!movie) return;

  currentRatingMovieId = movieId;
  document.getElementById('ratingMovieTitle').textContent = movie.title.toUpperCase();
  document.getElementById('ratingMovieEmoji').textContent = movie.emoji || '🎬';

  // Pre-fill existing rating
  const existing = getUserRating(movieId);
  const starsEl  = document.getElementById('ratingStars');
  starsEl.innerHTML = [1,2,3,4,5].map(n => `<span onclick="setRating(${n})">☆</span>`).join('');

  if (existing) {
    document.getElementById('ratingValue').value   = existing.stars;
    document.getElementById('ratingReview').value  = existing.review || '';
    document.getElementById('ratingText').textContent = existing.stars + ' out of 5 stars';
    starsEl.querySelectorAll('span').forEach((s, i) => { s.textContent = i < existing.stars ? '★' : '☆'; });
  } else {
    document.getElementById('ratingValue').value  = '0';
    document.getElementById('ratingReview').value = '';
    document.getElementById('ratingText').textContent = 'Click stars to rate';
  }

  openModal('ratingModal');
}

function setRating(stars) {
  document.getElementById('ratingValue').value = stars;
  document.getElementById('ratingStars').querySelectorAll('span').forEach((s, i) => {
    s.textContent = i < stars ? '★' : '☆';
  });
  document.getElementById('ratingText').textContent = stars + ' out of 5 stars';
}

async function submitRating() {
  const stars  = parseInt(document.getElementById('ratingValue').value);
  const review = document.getElementById('ratingReview').value.trim();

  if (stars === 0) { showToast('Please select a rating.', 'error'); return; }

  if (USE_PHP_API) {
    try {
      const res = await apiPost('add_rating', {
        user_id:  currentUser.id,
        movie_id: currentRatingMovieId,
        stars,
        review,
      });
      if (res._offline) throw new Error('offline');
      // Update local cache with fresh avg
      ratingsCache.averages[currentRatingMovieId]     = { avg: res.avg, total: res.total };
      ratingsCache.user_ratings[currentRatingMovieId] = { stars, review };
    } catch (e) {
      if (!e.message?.includes('offline')) { showToast(e.message || 'Failed to save rating.', 'error'); return; }
      // fallback to localStorage
      _saveRatingLocally(stars, review);
    }
  } else {
    _saveRatingLocally(stars, review);
  }

  showToast('Rating submitted!', 'success');
  closeModal('ratingModal');
  renderMovies(moviesCache || MOVIES);
}

function _saveRatingLocally(stars, review) {
  const key    = currentUser.id || currentUser.email;
  let ratings  = JSON.parse(localStorage.getItem('cvRatings')) || [];
  const idx    = ratings.findIndex(r => r.movieId === currentRatingMovieId && r.userId === key);
  const entry  = { movieId: currentRatingMovieId, userId: key, stars, review, timestamp: new Date().toISOString() };
  if (idx > -1) ratings[idx] = entry; else ratings.push(entry);
  localStorage.setItem('cvRatings', JSON.stringify(ratings));
  // Rebuild cache from localStorage
  ratingsCache.user_ratings[currentRatingMovieId] = { stars, review };
  const all   = ratings.filter(r => r.movieId === currentRatingMovieId);
  const avg   = parseFloat((all.reduce((s, r) => s + r.stars, 0) / all.length).toFixed(1));
  ratingsCache.averages[currentRatingMovieId] = { avg, total: all.length };
}

// ============================================================
//  AUTH
// ============================================================
async function handleLogin(e) {
  e.preventDefault();
  const email    = document.getElementById('loginEmail').value.trim();
  const password = document.getElementById('loginPassword').value;
  
  // Validate email format
  if (!isValidEmail(email)) {
    showToast('Invalid email format. Please enter a valid email address.', 'error');
    return;
  }
  if (USE_PHP_API) {
    try {
      const res = await apiCall('login', { email, password });
      currentUser = res.user;
      localStorage.setItem('cvUser', JSON.stringify(res.user));
      updateUserUI();
      closeModal('loginModal');
      showToast(`Welcome back, ${res.user.name}!`, 'success');
    } catch(e) { showToast(e.message, 'error'); }
  } else {
    const users = JSON.parse(localStorage.getItem('cvUsers')) || [];
    const user  = users.find(u => u.email === email && u.password === password);
    if (user) {
      currentUser = user;
      localStorage.setItem('cvUser', JSON.stringify(user));
      updateUserUI();
      closeModal('loginModal');
      showToast(`Welcome back, ${user.name}!`, 'success');
    } else { showToast('Invalid credentials. Try registering first.', 'error'); }
  }
}

async function handleRegister(e) {
  e.preventDefault();
  const name     = document.getElementById('regName').value.trim();
  const email    = document.getElementById('regEmail').value.trim();
  const password = document.getElementById('regPassword').value;

  // Validate email format
  if (!isValidEmail(email)) {
    showToast('Invalid email format. Please enter a valid email address.', 'error');
    return;
  }

  if (password.length < 6) { showToast('Password must be at least 6 characters.', 'error'); return; }

  if (USE_PHP_API) {
    try {
      const res = await apiCall('register', { name, email, password });
      currentUser = res.user;
      localStorage.setItem('cvUser', JSON.stringify(res.user));
      updateUserUI();
      closeModal('registerModal');
      showToast(`Welcome, ${name}!`, 'success');
    } catch(e) { showToast(e.message, 'error'); }
  } else {
    const users = JSON.parse(localStorage.getItem('cvUsers')) || [];
    if (users.find(u => u.email === email)) { showToast('Email already registered.', 'error'); return; }
    const user = { name, email, password, id: Date.now() };
    users.push(user);
    localStorage.setItem('cvUsers', JSON.stringify(users));
    currentUser = user;
    localStorage.setItem('cvUser', JSON.stringify(user));
    updateUserUI();
    closeModal('registerModal');
    showToast(`Account created! Welcome, ${name}!`, 'success');
  }
}

function updateUserUI() {
  const el = document.getElementById('userGreeting');
  if (el) el.textContent = currentUser ? currentUser.name : 'Guest';
  // Reload ratings so Rate button reflects booked status immediately
  loadRatings().then(() => renderMovies(moviesCache || MOVIES));
}

// ============================================================
//  MODALS & TOAST
// ============================================================
function openModal(id)          { document.getElementById(id)?.classList.remove('hidden'); }
function closeModal(id)         { document.getElementById(id)?.classList.add('hidden'); }
function switchModal(from, to)  { closeModal(from); openModal(to); }

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
