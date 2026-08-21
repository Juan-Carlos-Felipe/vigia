<?php
$people = $pdo->query('SELECT * FROM people ORDER BY full_name')->fetchAll();
$sightings = $pdo->query(
    'SELECT s.*, p.full_name
     FROM sightings s
     JOIN people p ON p.id = s.person_id
     ORDER BY s.created_at DESC
     LIMIT 8'
)->fetchAll();
?>
<header class="topbar">
    <div>
        <span class="eyebrow">Registro administrativo</span>
        <h1>Personas</h1>
    </div>
</header>

<section class="grid two">
    <form method="post" action="index.php?route=people" class="panel form" enctype="multipart/form-data">
        <h2>Nueva persona</h2>
        <label>Nombre completo <input name="full_name" required></label>
        <label>Curso o grupo <input name="course"></label>
        <label>Telefono apoderado <input name="guardian_phone"></label>
        <label>Foto de referencia <input name="person_photo" type="file" accept="image/*"></label>
        <input type="hidden" name="captured_photo" data-person-captured-photo>
        <div class="capture-box" data-person-capture>
            <video data-person-video autoplay playsinline muted></video>
            <canvas data-person-canvas hidden></canvas>
            <img data-person-photo-preview alt="Foto capturada" hidden>
            <div class="row-actions">
                <button class="small" type="button" data-person-start>Usar webcam</button>
                <button class="small" type="button" data-person-shot disabled>Capturar foto</button>
            </div>
            <small data-person-message>Usa una foto frontal y bien iluminada para mejorar la identificacion.</small>
        </div>
        <label>Notas <textarea name="notes" rows="4"></textarea></label>
        <button class="primary">Registrar</button>
    </form>

    <div class="panel">
        <h2>Registro</h2>
        <div class="stack">
            <?php foreach ($people as $person): ?>
                <div class="item-row">
                    <?php if ($person['photo_path']): ?>
                        <img class="avatar" src="<?= e($person['photo_path']) ?>" alt="">
                    <?php endif; ?>
                    <div>
                        <strong><?= e($person['full_name']) ?></strong>
                        <span><?= e($person['course']) ?> - <?= e($person['guardian_phone']) ?></span>
                    </div>
                    <form method="post" action="index.php?route=people">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $person['id'] ?>">
                        <button class="small danger">Eliminar</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<section class="panel spaced">
    <h2>Identificaciones recientes</h2>
    <div class="timeline">
        <?php foreach ($sightings as $sighting): ?>
            <a class="event" href="<?= e($sighting['snapshot_path'] ?: 'index.php?route=people') ?>" target="_blank">
                <span class="badge soft"><?= e((string) $sighting['confidence']) ?>%</span>
                <strong><?= e($sighting['full_name']) ?></strong>
                <small><?= e($sighting['camera_name']) ?> - <?= e($sighting['created_at']) ?></small>
            </a>
        <?php endforeach; ?>
        <?php if (!$sightings): ?><p class="muted">Aun no hay identificaciones.</p><?php endif; ?>
    </div>
</section>
