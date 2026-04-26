<?php
/**
 * ============================================================
 *  Sai Deepak — api.php  (Full PHP Backend + Admin API)
 *
 *  USER ENDPOINTS:
 *    ?action=register   — Create user account
 *    ?action=login      — Authenticate user
 *    ?action=logout     — Destroy session
 *    ?action=me         — Get logged-in user (session)
 *    ?action=movies     — List all active movies
 *    ?action=movie      — Get single movie (?id=xxx)
 *    ?action=showtimes  — Get showtimes (?movie_id=xxx)
 *    ?action=seats      — Get taken seats
 *    ?action=booking    — Create a booking
 *    ?action=bookings   — List bookings for email
 *    ?action=cancel     — Cancel a booking
 *
 *  ADMIN ENDPOINTS (require admin session):
 *    ?action=admin_login         — Admin login
 *    ?action=admin_logout        — Admin logout
 *    ?action=admin_stats         — Dashboard stats
 *    ?action=admin_movies        — All movies (incl. inactive)
 *    ?action=admin_update_movie  — Update movie fields
 *    ?action=admin_add_movie     — Add new movie
 *    ?action=admin_toggle_movie  — Toggle active/inactive
 *    ?action=admin_all_bookings  — All bookings
 *    ?action=admin_cancel_booking— Cancel any booking
 *    ?action=admin_all_users     — All registered users
 *    ?action=admin_save_home     — Save home page settings
 *    ?action=admin_get_home      — Get home page settings
 * ============================================================
 */

if (session_status() === PHP_SESSION_NONE) {
    session_set_cookie_params([
        'lifetime' => 86400 * 7,
        'path'     => '/',
        'secure'   => false,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

header('Content-Type: application/json; charset=utf-8');

$origin = $_SERVER['HTTP_ORIGIN'] ?? '';
$allowed_origins = [
    'http://localhost',
    'http://127.0.0.1',
    'http://localhost:8080',
    'http://127.0.0.1:8080',
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
    'pass'    => '',
    'charset' => 'utf8mb4',
];

// ============================================================
//  ADMIN CREDENTIALS — change these in production!
// ============================================================
$ADMIN_USER = 'admin';
$ADMIN_PASS = 'admin123';

// ============================================================
//  HELPERS
// ============================================================
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

function respond(int $code, array $data): void {
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT);
    exit;
}

function body(): array {
    $raw = file_get_contents('php://input');
    return json_decode($raw ?: '{}', true) ?? [];
}

function requireFields(array $data, array $fields): void {
    foreach ($fields as $f) {
        if (!isset($data[$f]) || trim((string)$data[$f]) === '') {
            respond(400, ['error' => "Missing required field: $f"]);
        }
    }
}

function makeToken(int $userId): string {
    $secret  = 'cv_secret_change_me_2025';
    $payload = base64_encode(json_encode(['uid' => $userId, 'exp' => time() + 86400 * 7]));
    $sig     = hash_hmac('sha256', $payload, $secret);
    return "$payload.$sig";
}

function clean(string $s): string {
    return strip_tags(trim($s));
}

/** Guard: abort with 403 if admin is not logged in */
function requireAdmin(): void {
    if (empty($_SESSION['admin_logged_in'])) {
        respond(403, ['error' => 'Admin authentication required.']);
    }
}

/** Ensure home_settings table exists */
function ensureHomeSettingsTable(): void {
    db()->exec("
        CREATE TABLE IF NOT EXISTS home_settings (
            `key`   VARCHAR(100) NOT NULL PRIMARY KEY,
            `value` TEXT         NOT NULL,
            updated_at DATETIME  NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB
    ");
}

// ============================================================
//  ROUTER
// ============================================================
$action = $_GET['action'] ?? body()['action'] ?? '';

match ($action) {
    // User endpoints
    'register'             => handleRegister(),
    'login'                => handleLogin(),
    'logout'               => handleLogout(),
    'me'                   => handleMe(),
    'movies'               => handleMovies(),
    'movie'                => handleMovie(),
    'showtimes'            => handleShowtimes(),
    'seats'                => handleSeats(),
    'booking'              => handleBooking(),
    'bookings'             => handleBookings(),
    'cancel'               => handleCancel(),
    'add_rating'           => handleAddRating(),
    'get_ratings'          => handleGetRatings(),
    'my_rated_movies'      => handleMyRatedMovies(),
    // Admin endpoints
    'admin_login'          => handleAdminLogin(),
    'admin_logout'         => handleAdminLogout(),
    'admin_stats'          => handleAdminStats(),
    'admin_movies'         => handleAdminMovies(),
    'admin_update_movie'   => handleAdminUpdateMovie(),
    'admin_add_movie'      => handleAdminAddMovie(),
    'admin_toggle_movie'   => handleAdminToggleMovie(),
    'admin_all_bookings'   => handleAdminAllBookings(),
    'admin_cancel_booking' => handleAdminCancelBooking(),
    'admin_all_users'      => handleAdminAllUsers(),
    'admin_save_home'      => handleAdminSaveHome(),
    'admin_get_home'       => handleAdminGetHome(),
    default                => respond(200, [
        'status'  => 'Sai Deepak API',
        'version' => '3.0',
    ]),
};

// ============================================================
//  USER: REGISTER
// ============================================================
function handleRegister(): void {
    $d = body();
    requireFields($d, ['name','email','password']);

    $name  = clean($d['name']);
    $email = filter_var(trim($d['email']), FILTER_SANITIZE_EMAIL);
    $pass  = $d['password'];

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) respond(400, ['error' => 'Invalid email.']);
    if (strlen($pass) < 6) respond(400, ['error' => 'Password must be at least 6 characters.']);

    $pdo = db();
    $chk = $pdo->prepare('SELECT id FROM users WHERE email = ?');
    $chk->execute([$email]);
    if ($chk->fetch()) respond(409, ['error' => 'Email already registered.']);

    $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
    $ins  = $pdo->prepare('INSERT INTO users (name, email, password) VALUES (?, ?, ?)');
    $ins->execute([$name, $email, $hash]);
    $id = (int)$pdo->lastInsertId();

    $_SESSION['user_id'] = $id;
    respond(201, [
        'success' => true,
        'user'    => ['id' => $id, 'name' => $name, 'email' => $email],
        'token'   => makeToken($id),
    ]);
}

// ============================================================
//  USER: LOGIN
// ============================================================
function handleLogin(): void {
    $d     = body();
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
//  USER: LOGOUT
// ============================================================
function handleLogout(): void {
    session_destroy();
    respond(200, ['success' => true]);
}

// ============================================================
//  USER: ME
// ============================================================
function handleMe(): void {
    if (empty($_SESSION['user_id'])) respond(401, ['error' => 'Not authenticated.']);
    $stmt = db()->prepare('SELECT id, name, email, created_at FROM users WHERE id = ?');
    $stmt->execute([$_SESSION['user_id']]);
    $user = $stmt->fetch();
    if (!$user) respond(404, ['error' => 'User not found.']);
    respond(200, ['success' => true, 'user' => $user]);
}

// ============================================================
//  USER: MOVIES LIST
// ============================================================
function handleMovies(): void {
    $genre  = clean($_GET['genre']  ?? '');
    $search = clean($_GET['search'] ?? '');
    $sql    = 'SELECT * FROM movies WHERE active = 1';
    $params = [];

    if ($genre)  { $sql .= ' AND genre = ?'; $params[] = $genre; }
    if ($search) {
        $sql .= ' AND (title LIKE ? OR genre LIKE ?)';
        $params[] = "%$search%"; $params[] = "%$search%";
    }
    $sql .= ' ORDER BY rating DESC';

    $stmt = db()->prepare($sql);
    $stmt->execute($params);
    $movies = $stmt->fetchAll();

    foreach ($movies as &$m) {
        $m['rating'] = (float)$m['rating'];
        $m['price']  = (float)$m['price'];
    }
    respond(200, ['success' => true, 'movies' => $movies]);
}

// ============================================================
//  USER: SINGLE MOVIE
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
//  USER: SHOWTIMES
// ============================================================
function handleShowtimes(): void {
    $movie_id = clean($_GET['movie_id'] ?? body()['movie_id'] ?? '');
    if (!$movie_id) respond(400, ['error' => 'movie_id required.']);

    $stmt = db()->prepare('SELECT show_time FROM showtimes WHERE movie_id = ? ORDER BY id');
    $stmt->execute([$movie_id]);
    respond(200, ['success' => true, 'showtimes' => $stmt->fetchAll(PDO::FETCH_COLUMN)]);
}

// ============================================================
//  USER: TAKEN SEATS
// ============================================================
function handleSeats(): void {
    $p = array_merge($_GET, body());
    requireFields($p, ['movie_id','date','showtime']);

    $stmt = db()->prepare(
        "SELECT seats FROM bookings
         WHERE movie_id=? AND date=? AND showtime=? AND status='confirmed'"
    );
    $stmt->execute([clean($p['movie_id']), clean($p['date']), clean($p['showtime'])]);

    $taken = [];
    foreach ($stmt->fetchAll(PDO::FETCH_COLUMN) as $row) {
        foreach (explode(',', $row) as $s) { $s = trim($s); if ($s) $taken[] = $s; }
    }
    respond(200, ['success' => true, 'taken_seats' => array_unique($taken)]);
}

// ============================================================
//  USER: CREATE BOOKING
// ============================================================
function handleBooking(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(405, ['error' => 'POST required.']);

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

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) respond(400, ['error' => 'Invalid email.']);
    if ($total <= 0) respond(400, ['error' => 'Invalid total amount.']);

    $chk = db()->prepare('SELECT id FROM movies WHERE id = ?');
    $chk->execute([$movie_id]);
    if (!$chk->fetch()) respond(404, ['error' => 'Movie not found.']);

    $conflict = db()->prepare(
        "SELECT seats FROM bookings WHERE movie_id=? AND date=? AND showtime=? AND status='confirmed'"
    );
    $conflict->execute([$movie_id, $date, $showtime]);
    $taken = [];
    foreach ($conflict->fetchAll(PDO::FETCH_COLUMN) as $row) {
        foreach (explode(',', $row) as $s) { $taken[] = trim($s); }
    }
    $requested = array_map('trim', explode(',', $seats_raw));
    $clashes   = array_intersect($requested, $taken);
    if ($clashes) respond(409, ['error' => 'Some seats are already taken.', 'clashes' => array_values($clashes)]);

    $ins = db()->prepare(
        'INSERT INTO bookings (user_id,movie_id,movie_title,movie_emoji,date,showtime,seats,name,email,total)
         VALUES (?,?,?,?,?,?,?,?,?,?)'
    );
    $ins->execute([$user_id,$movie_id,$movie_title,$movie_emoji,$date,$showtime,$seats_raw,$name,$email,$total]);
    $bookingId = (int)db()->lastInsertId();

    respond(201, ['success' => true, 'message' => 'Booking confirmed!', 'booking_id' => $bookingId]);
}

// ============================================================
//  USER: LIST BOOKINGS
// ============================================================
function handleBookings(): void {
    $email = filter_var(trim($_GET['email'] ?? body()['email'] ?? ''), FILTER_SANITIZE_EMAIL);
    if (!$email || !filter_var($email, FILTER_VALIDATE_EMAIL)) respond(400, ['error' => 'Valid email required.']);

    $stmt = db()->prepare(
        "SELECT id,movie_id,movie_title,movie_emoji,date,showtime,seats,name,email,total,status,created_at
         FROM bookings WHERE email=? ORDER BY created_at DESC"
    );
    $stmt->execute([$email]);
    $bookings = $stmt->fetchAll();

    foreach ($bookings as &$b) { $b['id'] = (int)$b['id']; $b['total'] = (float)$b['total']; }
    respond(200, ['success' => true, 'bookings' => $bookings]);
}

// ============================================================
//  USER: CANCEL BOOKING
// ============================================================
function handleCancel(): void {
    $d  = body();
    $id = (int)($d['booking_id'] ?? $_GET['booking_id'] ?? 0);
    if (!$id) respond(400, ['error' => 'booking_id required.']);

    $stmt = db()->prepare("UPDATE bookings SET status='cancelled' WHERE id=?");
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) respond(404, ['error' => 'Booking not found.']);
    respond(200, ['success' => true, 'message' => 'Booking cancelled.']);
}

// ============================================================
//  USER: ADD / UPDATE RATING  (only if user has booked the movie)
// ============================================================
function handleAddRating(): void {
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(405, ['error' => 'POST required.']);

    $d = body();
    requireFields($d, ['user_id','movie_id','stars']);

    $user_id  = (int)$d['user_id'];
    $movie_id = clean($d['movie_id']);
    $stars    = (int)$d['stars'];
    $review   = clean($d['review'] ?? '');

    if ($stars < 1 || $stars > 5) respond(400, ['error' => 'Stars must be between 1 and 5.']);

    $pdo = db();

    // Ensure ratings table exists
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS ratings (
            id         INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id    INT UNSIGNED NOT NULL,
            movie_id   VARCHAR(80)  NOT NULL,
            stars      TINYINT      NOT NULL,
            review     TEXT,
            created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            UNIQUE KEY uq_user_movie (user_id, movie_id),
            FOREIGN KEY (user_id)  REFERENCES users(id)  ON DELETE CASCADE,
            FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE
        ) ENGINE=InnoDB
    ");

    // Check user has confirmed booking for this movie
    $booked = $pdo->prepare(
        "SELECT id FROM bookings WHERE user_id = ? AND movie_id = ? AND status = 'confirmed' LIMIT 1"
    );
    $booked->execute([$user_id, $movie_id]);
    if (!$booked->fetch()) {
        respond(403, ['error' => 'You can only rate movies you have booked.']);
    }

    // Upsert rating
    $stmt = $pdo->prepare("
        INSERT INTO ratings (user_id, movie_id, stars, review)
        VALUES (?, ?, ?, ?)
        ON DUPLICATE KEY UPDATE stars = VALUES(stars), review = VALUES(review), updated_at = NOW()
    ");
    $stmt->execute([$user_id, $movie_id, $stars, $review]);

    // Return updated avg for this movie
    $avg = $pdo->prepare("SELECT ROUND(AVG(stars),1) as avg, COUNT(*) as total FROM ratings WHERE movie_id = ?");
    $avg->execute([$movie_id]);
    $stat = $avg->fetch();

    respond(200, [
        'success' => true,
        'message' => 'Rating saved.',
        'avg'     => (float)$stat['avg'],
        'total'   => (int)$stat['total'],
    ]);
}

// ============================================================
//  USER: GET RATINGS for all movies (avg + user's own)
// ============================================================
function handleGetRatings(): void {
    $user_id  = (int)($_GET['user_id'] ?? 0);
    $movie_id = clean($_GET['movie_id'] ?? '');

    $pdo = db();

    // Ensure table exists silently
    $pdo->exec("CREATE TABLE IF NOT EXISTS ratings (
        id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
        user_id INT UNSIGNED NOT NULL,
        movie_id VARCHAR(80) NOT NULL,
        stars TINYINT NOT NULL,
        review TEXT,
        created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
        updated_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        UNIQUE KEY uq_user_movie (user_id, movie_id),
        FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE CASCADE,
        FOREIGN KEY (movie_id) REFERENCES movies(id) ON DELETE CASCADE
    ) ENGINE=InnoDB");

    // Averages per movie
    $avgs = $pdo->query("SELECT movie_id, ROUND(AVG(stars),1) as avg, COUNT(*) as total FROM ratings GROUP BY movie_id")->fetchAll();
    $avgMap = [];
    foreach ($avgs as $a) $avgMap[$a['movie_id']] = ['avg' => (float)$a['avg'], 'total' => (int)$a['total']];

    // This user's ratings
    $userRatings = [];
    if ($user_id) {
        $stmt = $pdo->prepare("SELECT movie_id, stars, review FROM ratings WHERE user_id = ?");
        $stmt->execute([$user_id]);
        foreach ($stmt->fetchAll() as $r) {
            $userRatings[$r['movie_id']] = ['stars' => (int)$r['stars'], 'review' => $r['review']];
        }
    }

    // Which movies has this user booked (confirmed)?
    $bookedMovies = [];
    if ($user_id) {
        $stmt = $pdo->prepare("SELECT DISTINCT movie_id FROM bookings WHERE user_id = ? AND status = 'confirmed'");
        $stmt->execute([$user_id]);
        $bookedMovies = $stmt->fetchAll(PDO::FETCH_COLUMN);
    }

    respond(200, [
        'success'      => true,
        'averages'     => $avgMap,
        'user_ratings' => $userRatings,
        'booked_movies'=> $bookedMovies,
    ]);
}

// ============================================================
//  USER: MY RATED MOVIES
// ============================================================
function handleMyRatedMovies(): void {
    $user_id = (int)($_GET['user_id'] ?? 0);
    if (!$user_id) respond(400, ['error' => 'user_id required.']);

    $stmt = db()->prepare("SELECT movie_id, stars, review, updated_at FROM ratings WHERE user_id = ? ORDER BY updated_at DESC");
    $stmt->execute([$user_id]);
    respond(200, ['success' => true, 'ratings' => $stmt->fetchAll()]);
}

// ============================================================
//  ADMIN: LOGIN
// ============================================================
function handleAdminLogin(): void {
    global $ADMIN_USER, $ADMIN_PASS;
    $d    = body();
    $user = clean($d['username'] ?? '');
    $pass = $d['password'] ?? '';

    if ($user === $ADMIN_USER && $pass === $ADMIN_PASS) {
        $_SESSION['admin_logged_in'] = true;
        respond(200, ['success' => true, 'message' => 'Admin login successful.']);
        return;
    }
    respond(401, ['error' => 'Invalid admin credentials.']);
}

// ============================================================
//  ADMIN: LOGOUT
// ============================================================
function handleAdminLogout(): void {
    unset($_SESSION['admin_logged_in']);
    respond(200, ['success' => true]);
}

// ============================================================
//  ADMIN: DASHBOARD STATS
// ============================================================
function handleAdminStats(): void {
    requireAdmin();
    $pdo = db();

    $totalBookings  = (int)$pdo->query("SELECT COUNT(*) FROM bookings")->fetchColumn();
    $totalUsers     = (int)$pdo->query("SELECT COUNT(*) FROM users")->fetchColumn();
    $totalMovies    = (int)$pdo->query("SELECT COUNT(*) FROM movies WHERE active=1")->fetchColumn();
    $totalRevenue   = (float)$pdo->query("SELECT COALESCE(SUM(total),0) FROM bookings WHERE status='confirmed'")->fetchColumn();
    $todayBookings  = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE DATE(created_at)=CURDATE()")->fetchColumn();
    $cancelledCount = (int)$pdo->query("SELECT COUNT(*) FROM bookings WHERE status='cancelled'")->fetchColumn();

    // Recent 5 bookings for activity
    $recent = $pdo->query(
        "SELECT b.id, b.movie_title, b.name, b.total, b.status, b.created_at
         FROM bookings b ORDER BY b.created_at DESC LIMIT 5"
    )->fetchAll();

    respond(200, [
        'success'         => true,
        'total_bookings'  => $totalBookings,
        'total_users'     => $totalUsers,
        'total_movies'    => $totalMovies,
        'total_revenue'   => $totalRevenue,
        'today_bookings'  => $todayBookings,
        'cancelled_count' => $cancelledCount,
        'recent_bookings' => $recent,
    ]);
}

// ============================================================
//  ADMIN: ALL MOVIES (incl. inactive)
// ============================================================
function handleAdminMovies(): void {
    requireAdmin();
    $stmt = db()->query("SELECT * FROM movies ORDER BY created_at DESC");
    $movies = $stmt->fetchAll();
    foreach ($movies as &$m) {
        $m['rating'] = (float)$m['rating'];
        $m['price']  = (float)$m['price'];
        $m['active'] = (int)$m['active'];
    }
    respond(200, ['success' => true, 'movies' => $movies]);
}

// ============================================================
//  ADMIN: UPDATE MOVIE
// ============================================================
function handleAdminUpdateMovie(): void {
    requireAdmin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(405, ['error' => 'POST required.']);

    $d = body();
    requireFields($d, ['id']);

    $id = clean($d['id']);

    // Check movie exists
    $chk = db()->prepare('SELECT id FROM movies WHERE id=?');
    $chk->execute([$id]);
    if (!$chk->fetch()) respond(404, ['error' => 'Movie not found.']);

    // Build dynamic update
    $allowed = ['title','genre','rating','duration','price','emoji','description','active'];
    $sets    = [];
    $params  = [];

    foreach ($allowed as $field) {
        if (isset($d[$field])) {
            $sets[]   = "`$field` = ?";
            $params[] = in_array($field, ['rating','price','active'])
                ? ($field === 'active' ? (int)$d[$field] : (float)$d[$field])
                : clean((string)$d[$field]);
        }
    }

    if (empty($sets)) respond(400, ['error' => 'No fields to update.']);

    $params[] = $id;
    $sql = "UPDATE movies SET " . implode(', ', $sets) . " WHERE id=?";
    db()->prepare($sql)->execute($params);

    // Fetch updated
    $stmt = db()->prepare('SELECT * FROM movies WHERE id=?');
    $stmt->execute([$id]);
    $movie = $stmt->fetch();
    $movie['rating'] = (float)$movie['rating'];
    $movie['price']  = (float)$movie['price'];

    respond(200, ['success' => true, 'message' => 'Movie updated.', 'movie' => $movie]);
}

// ============================================================
//  ADMIN: ADD MOVIE
// ============================================================
function handleAdminAddMovie(): void {
    requireAdmin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(405, ['error' => 'POST required.']);

    $d = body();
    requireFields($d, ['id','title','genre','rating','duration','price','emoji']);

    $id = clean($d['id']);

    // Check duplicate
    $chk = db()->prepare('SELECT id FROM movies WHERE id=?');
    $chk->execute([$id]);
    if ($chk->fetch()) respond(409, ['error' => 'Movie with this ID already exists.']);

    $ins = db()->prepare(
        'INSERT INTO movies (id,title,genre,rating,duration,price,emoji,description,active)
         VALUES (?,?,?,?,?,?,?,?,1)'
    );
    $ins->execute([
        $id,
        clean($d['title']),
        clean($d['genre']),
        (float)$d['rating'],
        clean($d['duration']),
        (float)$d['price'],
        clean($d['emoji']),
        clean($d['description'] ?? ''),
    ]);

    // Auto-add default showtimes
    $times = ['10:30 AM','1:00 PM','3:45 PM','6:15 PM','9:00 PM'];
    $st    = db()->prepare('INSERT INTO showtimes (movie_id, show_time) VALUES (?,?)');
    foreach ($times as $t) $st->execute([$id, $t]);

    respond(201, ['success' => true, 'message' => 'Movie added successfully.']);
}

// ============================================================
//  ADMIN: TOGGLE MOVIE ACTIVE / INACTIVE
// ============================================================
function handleAdminToggleMovie(): void {
    requireAdmin();
    $d  = body();
    $id = clean($d['id'] ?? '');
    if (!$id) respond(400, ['error' => 'Movie id required.']);

    $stmt = db()->prepare('SELECT active FROM movies WHERE id=?');
    $stmt->execute([$id]);
    $m = $stmt->fetch();
    if (!$m) respond(404, ['error' => 'Movie not found.']);

    $newActive = $m['active'] ? 0 : 1;
    db()->prepare('UPDATE movies SET active=? WHERE id=?')->execute([$newActive, $id]);
    respond(200, ['success' => true, 'active' => $newActive, 'message' => $newActive ? 'Movie activated.' : 'Movie deactivated.']);
}

// ============================================================
//  ADMIN: ALL BOOKINGS
// ============================================================
function handleAdminAllBookings(): void {
    requireAdmin();
    $stmt = db()->query(
        "SELECT b.*, u.name as user_name
         FROM bookings b
         LEFT JOIN users u ON b.user_id = u.id
         ORDER BY b.created_at DESC"
    );
    $bookings = $stmt->fetchAll();
    foreach ($bookings as &$b) {
        $b['id']    = (int)$b['id'];
        $b['total'] = (float)$b['total'];
    }
    respond(200, ['success' => true, 'bookings' => $bookings]);
}

// ============================================================
//  ADMIN: CANCEL ANY BOOKING
// ============================================================
function handleAdminCancelBooking(): void {
    requireAdmin();
    $d  = body();
    $id = (int)($d['booking_id'] ?? 0);
    if (!$id) respond(400, ['error' => 'booking_id required.']);

    $stmt = db()->prepare("UPDATE bookings SET status='cancelled' WHERE id=?");
    $stmt->execute([$id]);
    if ($stmt->rowCount() === 0) respond(404, ['error' => 'Booking not found.']);
    respond(200, ['success' => true, 'message' => 'Booking cancelled.']);
}

// ============================================================
//  ADMIN: ALL USERS
// ============================================================
function handleAdminAllUsers(): void {
    requireAdmin();
    $users = db()->query(
        "SELECT u.id, u.name, u.email, u.created_at,
                COUNT(b.id) as booking_count
         FROM users u
         LEFT JOIN bookings b ON b.email = u.email
         GROUP BY u.id
         ORDER BY u.created_at DESC"
    )->fetchAll();

    foreach ($users as &$u) { $u['booking_count'] = (int)$u['booking_count']; }
    respond(200, ['success' => true, 'users' => $users]);
}

// ============================================================
//  ADMIN: SAVE HOME PAGE SETTINGS
// ============================================================
function handleAdminSaveHome(): void {
    requireAdmin();
    if ($_SERVER['REQUEST_METHOD'] !== 'POST') respond(405, ['error' => 'POST required.']);

    ensureHomeSettingsTable();
    $d   = body();
    $pdo = db();

    $allowed = [
        'hero_sub_label','hero_title1','hero_title2','hero_desc',
        'hero_rating','hero_duration','hero_genre','hero_movie_id',
        'site_name','site_name_gold','footer_text'
    ];

    $stmt = $pdo->prepare("INSERT INTO home_settings (`key`,`value`) VALUES (?,?) ON DUPLICATE KEY UPDATE `value`=?, updated_at=NOW()");
    foreach ($allowed as $key) {
        if (isset($d[$key])) {
            $val = clean((string)$d[$key]);
            $stmt->execute([$key, $val, $val]);
        }
    }

    respond(200, ['success' => true, 'message' => 'Home settings saved.']);
}

// ============================================================
//  ADMIN: GET HOME PAGE SETTINGS
// ============================================================
function handleAdminGetHome(): void {
    requireAdmin();
    ensureHomeSettingsTable();

    $rows = db()->query("SELECT `key`, `value` FROM home_settings")->fetchAll();
    $settings = [];
    foreach ($rows as $r) { $settings[$r['key']] = $r['value']; }
    respond(200, ['success' => true, 'settings' => $settings]);
}
