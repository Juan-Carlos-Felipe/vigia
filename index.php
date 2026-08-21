<?php

declare(strict_types=1);

require __DIR__ . '/vigilia/bootstrap.php';

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

include __DIR__ . '/vigilia/views/header.php';

switch ($route) {
    case 'login':
        include __DIR__ . '/vigilia/views/login.php';
        break;
    case 'people':
        require_role(['admin']);
        include __DIR__ . '/vigilia/views/people.php';
        break;
    case 'users':
        require_role(['admin']);
        include __DIR__ . '/vigilia/views/users.php';
        break;
    case 'cameras':
        require_role(['admin']);
        include __DIR__ . '/vigilia/views/cameras.php';
        break;
    case 'incidents':
        include __DIR__ . '/vigilia/views/incidents.php';
        break;
    default:
        include __DIR__ . '/vigilia/views/dashboard.php';
        break;
}

include __DIR__ . '/vigilia/views/footer.php';
