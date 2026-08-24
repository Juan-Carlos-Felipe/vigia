<?php

declare(strict_types=1);

require __DIR__ . '/vigia/bootstrap.php';

$route = $_GET['route'] ?? 'dashboard';

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

if ($route === 'api/notifications') {
    require_login();
    header('Content-Type: application/json');
    echo json_encode(latest_notifications($pdo, current_user_id()));
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    handle_post($pdo, $route);
}

if (!current_user() && $route !== 'login') {
    header('Location: index.php?route=login');
    exit;
}

$pageTitle = 'VigIA Escolar';

include __DIR__ . '/vigia/views/header.php';

switch ($route) {
    case 'login':
        include __DIR__ . '/vigia/views/login.php';
        break;
    case 'people':
        require_role(['admin']);
        include __DIR__ . '/vigia/views/people.php';
        break;
    case 'users':
        require_role(['admin']);
        include __DIR__ . '/vigia/views/users.php';
        break;
    case 'cameras':
        require_role(['admin']);
        include __DIR__ . '/vigia/views/cameras.php';
        break;
    case 'incidents':
        include __DIR__ . '/vigia/views/incidents.php';
        break;
    default:
        include __DIR__ . '/vigia/views/dashboard.php';
        break;
}

include __DIR__ . '/vigia/views/footer.php';
