# 🎬 Sai Deepak — Film Booking System

A full-featured cinema ticket booking web app built with HTML, CSS, JavaScript, and PHP.

## 📁 File Structure

```
film-booking/
├── index.html       → Home page (movie listings + hero)
├── booking.html     → 3-step booking flow
├── style.css        → Cinematic dark-theme stylesheet
├── app.js           → Movie data, auth, modals, toast
├── booking.js       → Seat map, step navigation, form logic
└── api.php          → PHP backend API (MySQL)
```

## ✅ Features

### Frontend
- 🎬 Hero section with featured film
- 🎞️ Movie grid with genre filter (All / Action / Drama / Sci-Fi / Horror)
- 🪑 Interactive seat selection map (8 seats max, pre-taken seats shown)
- 📅 Date & showtime picker (7 days rolling)
- 💳 Payment form with card formatting
- 🎟️ Booking confirmation modal
- 👤 User registration & login (localStorage)
- 📋 My Bookings history
- 🍞 Toast notifications

### Backend (PHP)
- `?action=register` — Create user account (bcrypt password)
- `?action=login`    — Authenticate user
- `?action=booking`  — Save booking to MySQL
- `?action=bookings` — Get bookings by email
- `?action=movies`   — Get movie list

## 🚀 Quick Start (Frontend Only)

Just open `index.html` in a browser — no server needed. Auth and bookings use `localStorage`.

## 🛠️ PHP Setup (Full Stack)

### Requirements
- PHP 7.4+
- MySQL 5.7+
- Apache/Nginx (XAMPP, WAMP, LAMP, or similar)

### Steps

1. **Copy files** to your server's web root (e.g. `htdocs/sai_deepak/`)

2. **Create the database** — run the SQL in `api.php` (lines 30–50):
   ```sql
   CREATE DATABASE sai_deepak;
   USE sai_deepak;
   -- Create tables: users, bookings (schema in api.php)
   ```

3. **Update DB credentials** in `api.php`:
   ```php
   $config = [
     'host'   => 'localhost',
     'dbname' => 'cinevault',
     'user'   => 'your_user',
     'pass'   => 'your_password'
   ];
   ```

4. **Enable PHP API** in `app.js`:
   ```js
   const USE_PHP_API = true;
   const API_URL = '/sai_deepak/api.php';
   ```

5. Open `http://localhost/sai_deepak/` in your browser.

## 🎨 Customization

- **Colors**: Edit CSS variables at the top of `style.css`
- **Movies**: Edit the `MOVIES` array in `app.js`
- **Seat layout**: Edit `ROWS`, `COLS`, `TAKEN_SEATS` in `booking.js`
- **Prices**: Update `price` field in the movies data

## 📱 Responsive

Works on mobile, tablet, and desktop.
