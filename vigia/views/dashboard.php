<?php
$stats = [
    'open' => (int) $pdo->query('SELECT COUNT(*) FROM incidents WHERE status = "open"')->fetchColumn(),
    'today' => (int) $pdo->query('SELECT COUNT(*) FROM incidents WHERE DATE(created_at) = CURDATE()')->fetchColumn(),
    'people' => (int) $pdo->query('SELECT COUNT(*) FROM people')->fetchColumn(),
    'cameras' => (int) $pdo->query('SELECT COUNT(*) FROM cameras WHERE active = 1')->fetchColumn(),
];
$incidents = $pdo->query('SELECT * FROM incidents ORDER BY created_at DESC LIMIT 8')->fetchAll();
$cameras = $pdo->query('SELECT * FROM cameras ORDER BY name')->fetchAll();
$sightings = $pdo->query(
    'SELECT s.*, p.full_name
     FROM sightings s
     JOIN people p ON p.id = s.person_id
     ORDER BY s.created_at DESC
     LIMIT 6'
)->fetchAll();
$watcher = watcher_status();
?>
<header class="topbar">
    <div>
        <span class="eyebrow">Operación en tiempo real</span>
        <h1>Panel de vigilancia</h1>
    </div>
    <div class="actions">
        <form method="post" action="index.php?route=watcher">
            <button class="primary">Activar cámara</button>
        </form>
        <a class="ghost" href="index.php?route=incidents">Ver historial</a>
    </div>
</header>

<?php if (!empty($_SESSION['flash'])): ?>
    <div class="alert spaced"><?= e($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
<?php endif; ?>

<section class="metrics">
    <article><span>Alertas abiertas</span><strong><?= $stats['open'] ?></strong></article>
    <article><span>Eventos hoy</span><strong><?= $stats['today'] ?></strong></article>
    <article><span>Personas registradas</span><strong><?= $stats['people'] ?></strong></article>
    <article><span>Cámaras activas</span><strong><?= $stats['cameras'] ?></strong></article>
</section>

<section class="panel watcher-panel">
    <div>
        <h2>Vigilancia YOLO</h2>
        <p class="muted"><?= e($watcher['message']) ?></p>
        <?php if (!empty($watcher['started_at'])): ?>
            <small>Inicio: <?= e($watcher['started_at']) ?></small>
        <?php endif; ?>
    </div>
    <form method="post" action="index.php?route=watcher">
        <button class="primary">Activar cámara</button>
    </form>
</section>

<section class="panel camera-preview" data-camera-preview>
    <div class="preview-copy">
        <span class="eyebrow">Vista local</span>
        <h2>Cámara en tiempo real</h2>
        <p class="muted">Visualiza la cámara de este computador antes de dejar VigIA monitoreando.</p>
        <div class="preview-controls">
            <select data-camera-select aria-label="Seleccionar cámara"></select>
            <button class="primary" type="button" data-camera-start>Ver cámara</button>
            <button class="ghost" type="button" data-camera-stop disabled>Detener</button>
        </div>
        <small data-camera-message>El navegador pedirá permiso para usar la cámara.</small>
    </div>
    <div class="video-frame">
        <video data-camera-video autoplay playsinline muted></video>
        <span data-camera-empty>Sin vista previa</span>
    </div>
</section>

<section class="grid two">
    <div class="panel">
        <h2>Alertas recientes</h2>
        <div class="timeline">
            <?php foreach ($incidents as $incident): ?>
                <a class="event" href="index.php?route=incidents">
                    <span class="<?= badge_class($incident['severity']) ?>"><?= e($incident['type']) ?></span>
                    <strong><?= e($incident['message']) ?></strong>
                    <small><?= e($incident['camera_name']) ?> - <?= e($incident['created_at']) ?></small>
                </a>
            <?php endforeach; ?>
            <?php if (!$incidents): ?><p class="muted">Sin incidentes registrados.</p><?php endif; ?>
        </div>
    </div>

    <div class="panel">
        <h2>Estado de cámaras</h2>
        <div class="camera-list">
            <?php foreach ($cameras as $camera): ?>
                <div class="camera-row">
                    <div>
                        <strong><?= e($camera['name']) ?></strong>
                        <span><?= e($camera['location']) ?></span>
                    </div>
                    <span class="<?= $camera['active'] ? 'status on' : 'status off' ?>">
                        <?= $camera['active'] ? 'Activa' : 'Pausada' ?>
                    </span>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="panel spaced">
    <h2>Identificaciones recientes</h2>
    <div class="timeline">
        <?php foreach ($sightings as $sighting): ?>
            <a class="event" href="<?= e($sighting['snapshot_path'] ?: 'index.php') ?>" target="_blank">
                <span class="badge soft"><?= e((string) $sighting['confidence']) ?>%</span>
                <strong><?= e($sighting['full_name']) ?></strong>
                <small><?= e($sighting['camera_name']) ?> - <?= e($sighting['created_at']) ?></small>
            </a>
        <?php endforeach; ?>
        <?php if (!$sightings): ?><p class="muted">Sin personas identificadas todavía.</p><?php endif; ?>
    </div>
</section>
