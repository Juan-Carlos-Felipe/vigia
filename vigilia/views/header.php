<?php $user = current_user(); ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="stylesheet" href="vigilia/assets/styles.css">
</head>
<body>
<?php if ($user): ?>
    <aside class="sidebar">
        <a class="brand" href="index.php">
            <span class="brand-mark">V</span>
            <span>VIGILIA</span>
        </a>
        <nav>
            <a href="index.php?route=dashboard">Panel</a>
            <a href="index.php?route=incidents">Alertas</a>
            <?php if ($user['role'] === 'admin'): ?>
                <a href="index.php?route=people">Personas</a>
                <a href="index.php?route=cameras">Camaras</a>
                <a href="index.php?route=users">Usuarios</a>
            <?php endif; ?>
        </nav>
        <div class="session">
            <strong><?= e($user['name']) ?></strong>
            <span><?= e($user['role']) ?></span>
            <a href="index.php?logout=1">Salir</a>
        </div>
    </aside>
    <main class="shell">
        <div id="toast" class="toast" hidden></div>
<?php else: ?>
    <main class="login-shell">
<?php endif; ?>
