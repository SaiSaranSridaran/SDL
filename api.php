<?php
/**
 * ============================================================
 *  Sai Deepak — api.php  (Full PHP Backend)
 *  MySQL-backed REST API for the film booking system.
 *
 *  ENDPOINTS (POST body or GET query):
 *    ?action=register   — Create user account
 *    ?action=login      — Authenticate user
 *    ?action=logout     — Destroy session
 *    ?action=me         — Get logged-in user (session)
 *    ?action=movies     — List all movies
 *    ?action=movie      — Get single movie (?id=xxx)
 *    ?action=showtimes  — Get showtimes for a movie (?movie_id=xxx)
 *    ?action=seats      — Get taken seats (?movie_id=xxx&date=xxx&showtime=xxx)
 *    ?action=booking    — Create a booking
 *    ?action=bookings   — List bookings for an email (?email=xxx)
 *    ?action=cancel     — Cancel a booking (POST {booking_id})
 *
 *  SETUP:
 *    1.  Copy these files to your web-server root (e.g. htdocs/cinevault/)
 *    2.  Create the database and run the SQL block below once
 *    3.  Update $cfg with your MySQL credentials
 *    4.  Open http://localhost/cinevault/ in your browser
 * ============================================================
 */

// ── Session (before any output) ──────────────────────────────
if (session_status() === PHP_SESSION_NONE) {
    // FIX: Ensure session cookie is sent correctly for same-site requests.
    // SameSite=Lax allows cookies to be sent with fetch() credentials:include on same origin.
    session_set_cookie_params([
        'lifetime' => 86400 * 7,
        'path'     => '/',
        'secure'   => false,   // set true on HTTPS in production
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ── Headers ──────────────────────────────────────────────────
header('Content-Type: application/json; charset=utf-8');

// FIX: wildcard '*' blocks cookies when credentials:include is used.
// Reflect the actual requesting origin instead.
$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed_origins = [
    'http://localhost',
    'http://127.0.0.1',
    'http://localhost:8080',
    'http://127.0.0.1:8080',
    // Add your production domain here, e.g. 'https://yourdomain.com'
];
if (in_array($origin, $allowed_origins, true) || empty($origin)) {
    header('Access-Control-Allow-Origin: ' . ($origin ?: '*'));
} else {
    header('Access-Control-Allow-Origin: ' . $allowed_origins[0]);
}
header('Access-Control-Allow-Methods: GET, POST, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Content-Type, Authorization, X-Requested-With');
header('Access-Control-Allow-Credentials: true');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

// ============================================================
//  DATABASE CONFIG  — edit these
// ============================================================
$cfg = [
    'host'    => 'localhost',
    'dbname'  => 'sai_deepak',
    'user'    => 'root',
    'pass'    => '',          // your MySQL password
    'charset' => 'utf8mb4',
];

// ============================================================
//  SQL SCHEMA  — run once in phpMyAdmin or MySQL CLI
// ============================================================
/*
CREATE DATABASE IF NOT EXISTS sai_deepak CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
USE sai_deepak;

CREATE TABLE IF NOT EXISTS users (
    id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    name       VARCHAR(120)  NOT NULL,
    email      VARCHAR(180)  NOT NULL UNIQUE,
    password   VARCHAR(255)  NOT NULL,
    created_at DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS movies (
    id          VARCHAR(80)   NOT NULL PRIMARY KEY,
    title       VARCHAR(150)  NOT NULL,
    genre       VARCHAR(50)   NOT NULL,
    rating      DECIMAL(3,1)  NOT NULL DEFAULT 0,
    duration    VARCHAR(30)   NOT NULL,
    price       DECIMAL(6,2)  NOT NULL DEFAULT 12.00,
    emoji       VARCHAR(10)   NOT NULL DEFAULT '🎬',
    description TEXT,
    active      TINYINT(1)    NOT NULL DEFAULT 1,
    created_at  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS showtimes (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    movie_id    VARCHAR(80)  NOT NULL,
    show_time   VARCHAR(20)  NOT NULL,
    FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS bookings (
    id          INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id     INT UNSIGNED,
    movie_id    VARCHAR(80)  NOT NULL,
    movie_title VARCHAR(150) NOT NULL,
    movie_emoji VARCHAR(10),
    date        DATE         NOT NULL,
    showtime    VARCHAR(20)  NOT NULL,
    seats       TEXT         NOT NULL,
    name        VARCHAR(150) NOT NULL,
    email       VARCHAR(180) NOT NULL,
    total       DECIMAL(8,2) NOT NULL,
    status      ENUM('confirmed','cancelled') NOT NULL DEFAULT 'confirmed',
    created_at  DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE SET NULL
) ENGINE=InnoDB;

-- Seed movies
INSERT IGNORE INTO movies (id, title, genre, rating, duration, price, emoji, description) VALUES
('stellar-void',  'STELLAR VOID',   'sci-fi',  9.1, '2h 28m', 14.00, '🌌', 'A breathtaking journey across collapsed galaxies.'),
('iron-meridian', 'IRON MERIDIAN',  'action',  8.4, '2h 05m', 12.00, '⚔️',  'War, steel, and a soldier who refuses to fall.'),
('pale-echo',     'PALE ECHO',      'drama',   8.9, '1h 52m', 11.00, '🎭', 'A grieving composer discovers a lost recording.'),
('blackwood',     'BLACKWOOD',      'horror',  7.8, '1h 45m', 11.00, '🌲', 'Something ancient stirs in the forest at midnight.'),
('nova-protocol', 'NOVA PROTOCOL',  'sci-fi',  8.2, '2h 14m', 13.00, '🤖', 'An AI gains consciousness on the eve of war.'),
('broken-coast',  'BROKEN COAST',   'drama',   9.0, '2h 01m', 12.00, '🌊', 'Two estranged siblings reunite after a decade apart.'),
('thunderstrike', 'THUNDERSTRIKE',  'action',  7.6, '1h 58m', 12.00, '⚡', 'A rogue agent races against time to stop a heist.'),
('crimson-dolls', 'CRIMSON DOLLS',  'horror',  8.1, '1h 40m', 11.00, '�', 'A haunted carnival returns to a small town.');

-- Seed showtimes for each movie
INSERT IGNORE INTO showtimes (movie_id, show_time)
SELECT id, t.show_time FROM movies
CROSS JOIN (
    SELECT '10:30 AM' AS show_time UNION ALL
    SELECT '1:00 PM'  UNION ALL
    SELECT '3:45 PM'  UNION ALL
    SELECT '6:15 PM'  UNION ALL
    SELECT '9:00 PM'
) t;
*/

// ============================================================
//  HELPERS
// ============================================================

/** Return PDO connection (singleton). */
function db(): PDO {
    static $pdo = null;
    if ($pdo) return $pdo;
    global $cfg;
    try {
        $dsn = "mysql:host={$cfg['host']};dbname={$cfg['dbname']};charset={$cfg['charset']}";
        $pdo = new PDO($dsn, $cfg['user'], $cfg['pass'], [
            PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES   => false,
        ]);
    } catch (PDOException $e) {
        respond(500, ['error' => 'Database connection failed: ' . $e->getMessage()]);
    }
    return $pdo;
}

/** Send JSON response and exit. */
function respond(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

/** Decode JSON body safely. */
function body(): array {
    $raw = file_get_contents('php://input');
    return json_decode($raw ?: '{}', true) ?? [];
}

/** Validate required fields; respond with 400 if missing. */
function requireFields(array $data, array $fields): void {
    foreach ($fields as $f) {
        if (!isset($data[$f]) || trim((string)$data[$f]) === '') {
            respond(400, ['error' => "Missing required field: $f"]);
        }
    }
}

/** Generate a signed JWT-style token (simple HMAC, not full JWT). */
function makeToken(int $userId): string {
    $secret  = 'cv_secret_change_me_2025'; // change in production
    $payload = base64_encode(json_encode(['uid' => $userId, 'exp' => time() + 86400 * 7]));
    $sig     = hash_hmac('sha256', $payload, $secret);
    return "$payload.$sig";
}

/**
 * Sanitise string for safe DB storage.
 * IMPORTANT: Do NOT use htmlspecialchars() here — that encodes for HTML output.
 * Storing HTML-encoded text in the DB causes corrupted display (e.g. O&#039;Brien).
 */
function clean(string $s): string {
    return strip_tags(trim($s));
}

// ============================================================
//  ROUTER
// ============================================================
$action = $_GET['action'] ?? body()['action'] ?? '';

match ($action) {
    'register'  => handleRegister(),
    'login'     => handleLogin(),
    'logout'    => handleLogout(),
    'me'        => handleMe(),
    'movies'    => handleMovies(),
    'movie'     => handleMovie(),
    'showtimes' => handleShowtimes(),
    'seats'     => handleSeats(),
    'booking'   => handleBooking(),
    'bookings'  => handleBookings(),
    'cancel'    => handleCancel(),
    default     => respond(200, [
        'status'    => 'Sai Deepak API',
        'version'   => '2.0',
        'endpoints' => ['register','login','logout','me','movies','movie',
                        'showtimes','seats','booking','bookings','cancel'],
    ]),
};

// ============================================================
//  REGISTER
// ============================================================
function handleRegister(): void {
    $d = body();
    requireFields($d, ['name','email','password']);

    $name  = clean($d['name']);
    $email = filter_var(trim($d['email']), FILTER_SANITIZE_EMAIL);
    $pass  = $d['password'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respond(400, ['error' => 'Invalid email address.']);
    }
    if (strlen($pass) < 6) {
        respond(400, ['error' => 'Password must be at least 6 characters.']);
    }

    $pdo = db();

    // Duplicate check
    $chk = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $chk->execute([$email]);
    if ($chk->fetch()) {
        respond(409, ['error' => 'Email already registered.']);
    }

    $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
    $ins  = $pdo->prepare('INSERT INTO users (name, email, password) VALUES (?, ?, ?)');
    $ins->execute([$name, $email, $hash]);
    $id   = (int)$pdo->lastInsertId();

    $_SESSION['user_id'] = $id;

    respond(201, [
        'success' => true,
        'message' => 'Account created successfully.',
        'user'    => ['id' => $id, 'name' => $name, 'email' => $email],
        'token'   => makeToken($id),
    ]);
}

// ============================================================
//  LOGIN
// ============================================================
function handleLogin(): void {
    $d = body();
    requireFields($d, ['email','password']);

    $email = filter_var(trim($d['email']), FILTER_SANITIZE_EMAIL);
    $pass  = $d['password'];

    $pdo  = db();
    $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? LIMIT 1');
    $stmt->execute([$email]);
    $user = $stmt->fetch();

    if (!$user || !password_verify($pass, $user['password'])) {
        respond(401, ['error' => 'Invalid email or password.']);
    }

    // Upgrade hash if needed
    if (password_needs_rehash($user['password'], PASSWORD_BCRYPT, ['cost' => 12])) {
        $new = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
        $pdo->prepare('UPDATE users SET password = ? WHERE id = ?')->execute([$new, $user['id']]);
    }

    $_SESSION['user_id'] = (int)$user['id'];

    respond(200, [
        'success' => true,
        'user'    => ['id' => (int)$user['id'], 'name' => $user['name'], 'email' => $user['email']],
        'token'   => makeToken((int)$user['id']),
    ]);
}

// ============================================================
//  LOGOUT
// ============================================================
function handleLogout(): void {
    session_destroy();
    respond(200, ['success' => true, 'message' => 'Signed out.']);
}

// ============================================================
//  ME (session-based auth check)
// ============================================================
function handleMe(): void {
    if (empty($_SESSION['user_id'])) {
        respond(401, ['error' => 'Not authenticated.']);
    }
    $stmt = db()->prepare('SELECT id, name, email, created_at FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) respond(404, ['error' => 'User not found.']);
    respond(200, ['success' => true, 'user' => $user]);
}

// ============================================================
//  MOVIES
// ============================================================
function handleMovies(): void {
    $genre  = clean($_GET['genre'] ?? '');
    $search = clean($_GET['search'] ?? '');
    $sql    = 'SELECT * FROM movies WHERE active = 1';
    $params = [];

    if ($genre)  { $sql .= ' AND genre = ?';               $params[] = $genre; }
    if ($search) { $sql .= ' AND (title LIKE ? OR genre LIKE ?)';
                   $params[] = "%$search%"; $params[] = "%$search%"; }

    $sql .= ' ORDER BY rating DESC';
    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $movies = $stmt->fetchAll();

    // Cast numeric types
    foreach ($movies as &$m) {
        $m['rating'] = (float)$m['rating'];
        $m['price']  = (float)$m['price'];
    }
    respond(200, ['success' => true, 'movies' => $movies]);
}

// ============================================================
//  SINGLE MOVIE
// ============================================================
function handleMovie(): void {
    $id = clean($_GET['id'] ?? body()['id'] ?? '');
    if (!$id) respond(400, ['error' => 'Movie id required.']);

    $stmt = db()->prepare('SELECT * FROM movies WHERE id = ? AND active = 1');
    $stmt->execute([$id]);
    $movie = $stmt->fetch();
    if (!$movie) respond(404, ['error' => 'Movie not found.']);

    $movie['rating'] = (float)$movie['rating'];
    $movie['price']  = (float)$movie['price'];
    respond(200, ['success' => true, 'movie' => $movie]);
}

// ============================================================
//  SHOWTIMES
// ============================================================
function handleShowtimes(): void {
    $movie_id = clean($_GET['movie_id'] ?? body()['movie_id'] ?? '');
    if (!$movie_id) respond(400, ['error' => 'movie_id required.']);

    $stmt = db()->prepare('SELECT show_time FROM showtimes WHERE movie_id = ? ORDER BY id');
    $stmt->execute([$movie_id]);
    respond(200, ['success' => true, 'showtimes' => $stmt->fetchAll(PDO::FETCH_COLUMN)]);
}

// ============================================================
//  SEATS  (taken seats for movie+date+showtime)
// ============================================================
function handleSeats(): void {
    $p = array_merge($_GET, body());
    requireFields($p, ['movie_id','date','showtime']);

    $stmt = db()->prepare(
        "SELECT seats FROM bookings
         WHERE movie_id = ? AND date = ? AND showtime = ? AND status = 'confirmed'"
    );
    $stmt->execute([clean($p['movie_id']), clean($p['date']), clean($p['showtime'])]);

    $taken = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $row) {
        foreach (explode(',', $row) as $s) {
            $s = trim($s);
            if ($s) $taken[] = $s;
        }
    }

    respond(200, ['success' => true, 'taken_seats' => array_unique($taken)]);
}

// ============================================================
//  CREATE BOOKING
// ============================================================
function handleBooking(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
        respond(405, ['error' => 'POST required.']);
    }

    $d = body();
    requireFields($d, ['movie_id','movie_title','date','showtime','seats','name','email','total']);

    $movie_id    = clean($d['movie_id']);
    $movie_title = clean($d['movie_title']);
    $movie_emoji = clean($d['movie_emoji'] ?? '🎬');
    $date        = clean($d['date']);
    $showtime    = clean($d['showtime']);
    $seats_raw   = is_array($d['seats']) ? implode(',', $d['seats']) : clean($d['seats']);
    $name        = clean($d['name']);
    $email       = filter_var(trim($d['email']), FILTER_SANITIZE_EMAIL);
    $total       = (float)$d['total'];
    $user_id     = !empty($d['user_id']) ? (int)$d['user_id'] : null;

    // Validate
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respond(400, ['error' => 'Invalid email address.']);
    }
    if ($total <= 0) {
        respond(400, ['error' => 'Invalid total amount.']);
    }

    // Check movie exists
    $chk = db()->prepare('SELECT id FROM movies WHERE id = ?');
    $chk->execute([$movie_id]);
    if (!$chk->fetch()) {
        respond(404, ['error' => 'Movie not found.']);
    }

    // Check seat conflicts
    $conflict = db()->prepare(
        "SELECT seats FROM bookings
         WHERE movie_id = ? AND date = ? AND showtime = ? AND status = 'confirmed'"
    );
    $conflict->execute([$movie_id, $date, $showtime]);
    $taken = [];
    foreach ($conflict->fetchAll(PDO::FETCH_COLUMN) as $row) {
        foreach (explode(',', $row) as $s) { $taken[] = trim($s); }
    }
    $requested = array_map('trim', explode(',', $seats_raw));
    $clashes   = array_intersect($requested, $taken);
    if ($clashes) {
        respond(409, [
            'error'   => 'Some seats are already taken.',
            'clashes' => array_values($clashes),
        ]);
    }

    // Insert
    $ins = db()->prepare(
        'INSERT INTO bookings
         (user_id, movie_id, movie_title, movie_emoji, date, showtime, seats, name, email, total)
         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)'
    );
    $ins->execute([$user_id, $movie_id, $movie_title, $movie_emoji,
                   $date, $showtime, $seats_raw, $name, $email, $total]);
    $bookingId = (int)db()->lastInsertId();

    respond(201, [
        'success'    => true,
        'message'    => 'Booking confirmed!',
        'booking_id' => $bookingId,
    ]);
}

// ============================================================
//  LIST BOOKINGS by email
// ============================================================
function handleBookings(): void {
    $email = filter_var(
        trim($_GET['email'] ?? body()['email'] ?? ''),
        FILTER_SANITIZE_EMAIL
    );
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        respond(400, ['error' => 'Valid email required.']);
    }

    $stmt = db()->prepare(
        "SELECT id, movie_id, movie_title, movie_emoji, date, showtime,
                seats, name, email, total, status, created_at
         FROM bookings
         WHERE email = ?
         ORDER BY created_at DESC"
    );
    $stmt->execute([$email]);
    $bookings = $stmt->fetchAll();

    foreach ($bookings as &$b) {
        $b['id']    = (int)$b['id'];
        $b['total'] = (float)$b['total'];
    }

    respond(200, ['success' => true, 'bookings' => $bookings]);
}

// ============================================================
//  CANCEL BOOKING
// ============================================================
function handleCancel(): void {
    $d  = body();
    $id = (int)($d['booking_id'] ?? $_GET['booking_id'] ?? 0);
    if (!$id) respond(400, ['error' => 'booking_id required.']);

    $stmt = db()->prepare("UPDATE bookings SET status = 'cancelled' WHERE id = ?");
    $stmt->execute([$id]);

    if ($stmt->rowCount() === 0) {
        respond(404, ['error' => 'Booking not found.']);
    }
    respond(200, ['success' => true, 'message' => 'Booking cancelled.']);
}