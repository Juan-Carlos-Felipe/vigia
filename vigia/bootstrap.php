<?php

declare(strict_types=1);

session_start();

define('VIGIA_DB_HOST', getenv('VIGIA_DB_HOST') ?: '127.0.0.1');
define('VIGIA_DB_NAME', getenv('VIGIA_DB_NAME') ?: 'vigia');
define('VIGIA_DB_USER', getenv('VIGIA_DB_USER') ?: 'root');
define('VIGIA_DB_PASS', getenv('VIGIA_DB_PASS') ?: '');

date_default_timezone_set('America/Santiago');

try {
    $pdo = new PDO(
        'mysql:host=' . VIGIA_DB_HOST . ';dbname=' . VIGIA_DB_NAME . ';charset=utf8mb4',
        VIGIA_DB_USER,
        VIGIA_DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );
} catch (PDOException $e) {
    http_response_code(500);
    echo '<h1>VigIA no pudo conectar con MySQL</h1>';
    echo '<p>Importa <code>vigia/database.sql</code> en phpMyAdmin y revisa las credenciales en <code>vigia/bootstrap.php</code>.</p>';
    exit;
}

try {
    ensure_schema($pdo);
} catch (PDOException $e) {
    http_response_code(500);
    error_log('VigIA schema error: ' . $e->getMessage());
    echo '<h1>VigIA no pudo preparar la base de datos</h1>';
    echo '<p>La conexión a MySQL funciona, pero falta importar o crear la estructura de tablas.</p>';
    echo '<pre>' . htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8') . '</pre>';
    exit;
}

function ensure_schema(PDO $pdo): void
{
    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS users (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            email VARCHAR(160) NOT NULL UNIQUE,
            role ENUM("admin", "inspector") NOT NULL DEFAULT "inspector",
            password_hash VARCHAR(255) NOT NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            last_seen_alert_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS people (
            id INT AUTO_INCREMENT PRIMARY KEY,
            full_name VARCHAR(160) NOT NULL,
            course VARCHAR(80) NULL,
            guardian_phone VARCHAR(40) NULL,
            photo_path VARCHAR(255) NULL,
            notes TEXT NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS cameras (
            id INT AUTO_INCREMENT PRIMARY KEY,
            name VARCHAR(120) NOT NULL,
            source VARCHAR(255) NOT NULL,
            location VARCHAR(160) NULL,
            active TINYINT(1) NOT NULL DEFAULT 1,
            last_heartbeat DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            UNIQUE KEY uq_cameras_name (name)
        )'
    );

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS incidents (
            id INT AUTO_INCREMENT PRIMARY KEY,
            camera_id INT NULL,
            camera_name VARCHAR(120) NOT NULL,
            type ENUM("fall", "fight") NOT NULL,
            severity ENUM("medium", "high", "critical") NOT NULL DEFAULT "high",
            message VARCHAR(255) NOT NULL,
            snapshot_path VARCHAR(255) NULL,
            confidence DECIMAL(5, 2) NOT NULL DEFAULT 0,
            status ENUM("open", "resolved") NOT NULL DEFAULT "open",
            resolved_by INT NULL,
            resolved_at DATETIME NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_incidents_status_created (status, created_at),
            CONSTRAINT fk_incidents_camera FOREIGN KEY (camera_id) REFERENCES cameras(id) ON DELETE SET NULL,
            CONSTRAINT fk_incidents_user FOREIGN KEY (resolved_by) REFERENCES users(id) ON DELETE SET NULL
        )'
    );

    $stmt = $pdo->prepare(
        'SELECT COUNT(*)
         FROM INFORMATION_SCHEMA.COLUMNS
         WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = "people" AND COLUMN_NAME = "photo_path"'
    );
    $stmt->execute();

    if ((int) $stmt->fetchColumn() === 0) {
        $pdo->exec('ALTER TABLE people ADD COLUMN photo_path VARCHAR(255) NULL AFTER guardian_phone');
    }

    $pdo->exec(
        'CREATE TABLE IF NOT EXISTS sightings (
            id INT AUTO_INCREMENT PRIMARY KEY,
            person_id INT NOT NULL,
            camera_id INT NULL,
            camera_name VARCHAR(120) NOT NULL,
            confidence DECIMAL(5, 2) NOT NULL DEFAULT 0,
            snapshot_path VARCHAR(255) NULL,
            created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_sightings_created (created_at),
            CONSTRAINT fk_sightings_person FOREIGN KEY (person_id) REFERENCES people(id) ON DELETE CASCADE,
            CONSTRAINT fk_sightings_camera FOREIGN KEY (camera_id) REFERENCES cameras(id) ON DELETE SET NULL
        )'
    );

    $pdo->exec(
        'INSERT IGNORE INTO users (name, email, role, password_hash)
         VALUES
            ("Administrador VigIA", "admin@vigia.local", "admin", "$2y$10$QeIdg8bpOCBJlaJiRILS8ezPLRnp4n8/kP9aSG3FRozThw0gkPB2O"),
            ("Inspector General", "inspector@vigia.local", "inspector", "$2y$10$kHWguIhcfX9tLJ8fluB0Au6ZGVwyZI2F/LTl./Hmt5nkwWWwwbbGi")'
    );

    $pdo->exec(
        'INSERT IGNORE INTO cameras (name, source, location)
         VALUES ("Cámara Patio Central", "0", "Patio central")'
    );
}

function current_user(): ?array
{
    return $_SESSION['user'] ?? null;
}

function current_user_id(): int
{
    return (int) ($_SESSION['user']['id'] ?? 0);
}

function require_login(): void
{
    if (!current_user()) {
        http_response_code(401);
        exit('No autorizado');
    }
}

function require_role(array $roles): void
{
    require_login();

    if (!in_array(current_user()['role'], $roles, true)) {
        http_response_code(403);
        exit('Acceso restringido');
    }
}

function e(?string $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
}

function redirect(string $route): void
{
    header('Location: index.php?route=' . urlencode($route));
    exit;
}

function badge_class(string $severity): string
{
    if ($severity === 'critical') {
        return 'badge critical';
    }

    if ($severity === 'high') {
        return 'badge high';
    }

    return 'badge soft';
}

function save_person_photo(array $files, ?string $capturedPhoto): ?string
{
    $storage = __DIR__ . '/storage/people';
    if (!is_dir($storage)) {
        mkdir($storage, 0775, true);
    }

    $jpegPrefix = 'data:image/jpeg;base64,';
    if ($capturedPhoto && substr($capturedPhoto, 0, strlen($jpegPrefix)) === $jpegPrefix) {
        $raw = base64_decode(substr($capturedPhoto, strlen($jpegPrefix)), true);
        if ($raw !== false) {
            $filename = 'person_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.jpg';
            file_put_contents($storage . '/' . $filename, $raw);
            return 'vigia/storage/people/' . $filename;
        }
    }

    if (!empty($files['person_photo']['tmp_name']) && is_uploaded_file($files['person_photo']['tmp_name'])) {
        $extension = strtolower(pathinfo((string) $files['person_photo']['name'], PATHINFO_EXTENSION));
        if (!in_array($extension, ['jpg', 'jpeg', 'png', 'webp'], true)) {
            return null;
        }

        $filename = 'person_' . date('Ymd_His') . '_' . bin2hex(random_bytes(3)) . '.' . $extension;
        move_uploaded_file($files['person_photo']['tmp_name'], $storage . '/' . $filename);
        return 'vigia/storage/people/' . $filename;
    }

    return null;
}

function watcher_status(): array
{
    $statusPath = __DIR__ . '/storage/watcher-status.json';
    if (!is_file($statusPath)) {
        return ['running' => false, 'message' => 'Vigilancia no iniciada desde el panel.'];
    }

    $status = json_decode((string) file_get_contents($statusPath), true);
    if (!is_array($status)) {
        return ['running' => false, 'message' => 'Estado de vigilancia no disponible.'];
    }

    return $status + ['running' => false, 'message' => 'Estado de vigilancia no disponible.'];
}

function start_watcher(): void
{
    $root = dirname(__DIR__);
    $script = $root . DIRECTORY_SEPARATOR . 'scripts' . DIRECTORY_SEPARATOR . 'vigia_watch.py';
    $storage = __DIR__ . DIRECTORY_SEPARATOR . 'storage';
    $log = $storage . DIRECTORY_SEPARATOR . 'watcher.log';
    $statusPath = $storage . DIRECTORY_SEPARATOR . 'watcher-status.json';
    $python = getenv('VIGIA_PYTHON') ?: 'python';

    if (!is_dir($storage)) {
        mkdir($storage, 0775, true);
    }

    if (PHP_OS_FAMILY === 'Windows') {
        $command = sprintf(
            'start "" /B %s %s >> %s 2>&1',
            escapeshellarg($python),
            escapeshellarg($script),
            escapeshellarg($log)
        );
        pclose(popen($command, 'r'));
    } else {
        $command = sprintf(
            '%s %s >> %s 2>&1 &',
            escapeshellcmd($python),
            escapeshellarg($script),
            escapeshellarg($log)
        );
        exec($command);
    }

    file_put_contents($statusPath, json_encode([
        'running' => true,
        'message' => 'Vigilancia YOLO iniciada. Revisa watcher.log si la cámara no abre.',
        'started_at' => date('Y-m-d H:i:s'),
    ], JSON_PRETTY_PRINT));
}

function handle_post(PDO $pdo, string $route): void
{
    if ($route === 'login') {
        $stmt = $pdo->prepare('SELECT * FROM users WHERE email = ? AND active = 1 LIMIT 1');
        $stmt->execute([$_POST['email'] ?? '']);
        $user = $stmt->fetch();

        if ($user && password_verify((string) ($_POST['password'] ?? ''), $user['password_hash'])) {
            $_SESSION['user'] = [
                'id' => (int) $user['id'],
                'name' => $user['name'],
                'email' => $user['email'],
                'role' => $user['role'],
            ];
            redirect('dashboard');
        }

        $_SESSION['flash'] = 'Credenciales incorrectas.';
        redirect('login');
    }

    require_login();

    if ($route === 'watcher') {
        start_watcher();
        $_SESSION['flash'] = 'Vigilancia iniciada. Si es la primera vez, YOLO puede demorar mientras descarga el modelo.';
        redirect('dashboard');
    }

    if ($route === 'people') {
        require_role(['admin']);
        if (($_POST['action'] ?? '') === 'delete') {
            $stmt = $pdo->prepare('DELETE FROM people WHERE id = ?');
            $stmt->execute([(int) $_POST['id']]);
            redirect('people');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO people (full_name, course, guardian_phone, photo_path, notes) VALUES (?, ?, ?, ?, ?)'
        );
        $stmt->execute([
            trim((string) $_POST['full_name']),
            trim((string) $_POST['course']),
            trim((string) $_POST['guardian_phone']),
            save_person_photo($_FILES, $_POST['captured_photo'] ?? null),
            trim((string) $_POST['notes']),
        ]);
        redirect('people');
    }

    if ($route === 'users') {
        require_role(['admin']);
        if (($_POST['action'] ?? '') === 'delete') {
            $stmt = $pdo->prepare('UPDATE users SET active = 0 WHERE id = ? AND id <> ?');
            $stmt->execute([(int) $_POST['id'], current_user_id()]);
            redirect('users');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO users (name, email, role, password_hash) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            trim((string) $_POST['name']),
            trim((string) $_POST['email']),
            $_POST['role'] === 'admin' ? 'admin' : 'inspector',
            password_hash((string) $_POST['password'], PASSWORD_DEFAULT),
        ]);
        redirect('users');
    }

    if ($route === 'cameras') {
        require_role(['admin']);
        if (($_POST['action'] ?? '') === 'delete') {
            $stmt = $pdo->prepare('DELETE FROM cameras WHERE id = ?');
            $stmt->execute([(int) $_POST['id']]);
            redirect('cameras');
        }

        if (($_POST['action'] ?? '') === 'toggle') {
            $stmt = $pdo->prepare('UPDATE cameras SET active = IF(active = 1, 0, 1) WHERE id = ?');
            $stmt->execute([(int) $_POST['id']]);
            redirect('cameras');
        }

        $stmt = $pdo->prepare(
            'INSERT INTO cameras (name, source, location, active) VALUES (?, ?, ?, ?)'
        );
        $stmt->execute([
            trim((string) $_POST['name']),
            trim((string) $_POST['source']),
            trim((string) $_POST['location']),
            isset($_POST['active']) ? 1 : 0,
        ]);
        redirect('cameras');
    }

    if ($route === 'incidents' && ($_POST['action'] ?? '') === 'resolve') {
        $stmt = $pdo->prepare(
            'UPDATE incidents SET status = "resolved", resolved_by = ?, resolved_at = NOW() WHERE id = ?'
        );
        $stmt->execute([current_user_id(), (int) $_POST['id']]);
        redirect('incidents');
    }
}

function latest_notifications(PDO $pdo, int $userId): array
{
    $stmt = $pdo->prepare(
        'SELECT id, type, severity, camera_name, message, snapshot_path, created_at
         FROM incidents
         WHERE status = "open"
         ORDER BY created_at DESC
         LIMIT 12'
    );
    $stmt->execute();

    $touch = $pdo->prepare('UPDATE users SET last_seen_alert_at = NOW() WHERE id = ?');
    $touch->execute([$userId]);

    return $stmt->fetchAll();
}
