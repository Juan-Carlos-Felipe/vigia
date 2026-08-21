<?php $incidents = $pdo->query('SELECT * FROM incidents ORDER BY created_at DESC LIMIT 80')->fetchAll(); ?>
<header class="topbar">
    <div>
        <span class="eyebrow">Respuesta asistida</span>
        <h1>Alertas e incidentes</h1>
    </div>
</header>

<section class="panel">
    <div class="table-wrap">
        <table>
            <thead>
                <tr>
                    <th>Evento</th>
                    <th>Cámara</th>
                    <th>Mensaje</th>
                    <th>Confianza</th>
                    <th>Captura</th>
                    <th>Estado</th>
                    <th>Fecha</th>
                    <th></th>
                </tr>
            </thead>
            <tbody>
                <?php foreach ($incidents as $incident): ?>
                    <tr>
                        <td><span class="<?= badge_class($incident['severity']) ?>"><?= e($incident['type']) ?></span></td>
                        <td><?= e($incident['camera_name']) ?></td>
                        <td><?= e($incident['message']) ?></td>
                        <td><?= e((string) $incident['confidence']) ?>%</td>
                        <td>
                            <?php if ($incident['snapshot_path']): ?>
                                <a class="ghost compact" href="<?= e($incident['snapshot_path']) ?>" target="_blank">Ver</a>
                            <?php else: ?>
                                <span class="muted">Sin captura</span>
                            <?php endif; ?>
                        </td>
                        <td><?= e($incident['status']) ?></td>
                        <td><?= e($incident['created_at']) ?></td>
                        <td>
                            <?php if ($incident['status'] === 'open'): ?>
                                <form method="post" action="index.php?route=incidents">
                                    <input type="hidden" name="action" value="resolve">
                                    <input type="hidden" name="id" value="<?= (int) $incident['id'] ?>">
                                    <button class="small">Resolver</button>
                                </form>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
            </tbody>
        </table>
    </div>
</section>
