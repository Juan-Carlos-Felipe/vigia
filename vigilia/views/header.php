<?php $user = current_user(); ?>
<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?= e($pageTitle) ?></title>
    <link rel="icon" href="vigilia/assets/logo_VigIA.png?v=2">
    <link rel="stylesheet" href="vigilia/assets/styles.css?v=4">
</head>
<body>
<?php if ($user): ?>
    <aside class="sidebar">
        <a class="brand" href="index.php">
            <img class="brand-logo" src="vigilia/assets/logo_VigIA_sidebar_original.png?v=1" alt="VigIA Escolar">
        </a>
        <nav>
            <a href="index.php?route=dashboard">Panel</a>
            <a href="index.php?route=incidents">Alertas</a>
            <?php if ($user['role'] === 'admin'): ?>
                <a href="index.php?route=people">Personas</a>
                <a href="index.php?route=cameras">Cámaras</a>
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
