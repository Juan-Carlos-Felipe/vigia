<?php $cameras = $pdo->query('SELECT * FROM cameras ORDER BY name')->fetchAll(); ?>
<header class="topbar">
    <div>
        <span class="eyebrow">Fuentes de video</span>
        <h1>Camaras</h1>
    </div>
    <form method="post" action="index.php?route=watcher">
        <button class="primary">Activar camara</button>
    </form>
</header>

<section class="panel camera-preview spaced" data-camera-preview>
    <div class="preview-copy">
        <span class="eyebrow">Prueba local</span>
        <h2>Vista previa de camara</h2>
        <p class="muted">Usa esta vista para confirmar que la camara del computador funciona en el navegador.</p>
        <div class="preview-controls">
            <select data-camera-select aria-label="Seleccionar camara"></select>
            <button class="primary" type="button" data-camera-start>Ver camara</button>
            <button class="ghost" type="button" data-camera-stop disabled>Detener</button>
        </div>
        <small data-camera-message>Permite el acceso cuando el navegador lo solicite.</small>
    </div>
    <div class="video-frame">
        <video data-camera-video autoplay playsinline muted></video>
        <span data-camera-empty>Sin vista previa</span>
    </div>
</section>

<section class="grid two">
    <form method="post" action="index.php?route=cameras" class="panel form">
        <h2>Nueva camara</h2>
        <label>Nombre <input name="name" required></label>
        <label>Fuente <input name="source" value="0" required></label>
        <label>Ubicacion <input name="location"></label>
        <label class="check"><input name="active" type="checkbox" checked> Activa</label>
        <button class="primary">Guardar camara</button>
        <small>Fuente puede set 0, 1, una URL RTSP o un archivo de video.</small>
    </form>

    <div class="panel">
        <h2>Listado</h2>
        <div class="stack">
            <?php foreach ($cameras as $camera): ?>
                <div class="item-row">
                    <div>
                        <strong><?= e($camera['name']) ?></strong>
                        <span><?= e($camera['source']) ?> - <?= e($camera['location']) ?></span>
                    </div>
                    <div class="row-actions">
                        <form method="post" action="index.php?route=cameras">
                            <input type="hidden" name="action" value="toggle">
                            <input type="hidden" name="id" value="<?= (int) $camera['id'] ?>">
                            <button class="small"><?= $camera['active'] ? 'Pausar' : 'Activar' ?></button>
                        </form>
                        <form method="post" action="index.php?route=cameras">
                            <input type="hidden" name="action" value="delete">
                            <input type="hidden" name="id" value="<?= (int) $camera['id'] ?>">
                            <button class="small danger">Eliminar</button>
                        </form>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
