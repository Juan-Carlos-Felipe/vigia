<?php $users = $pdo->query('SELECT * FROM users WHERE active = 1 ORDER BY role, name')->fetchAll(); ?>
<header class="topbar">
    <div>
        <span class="eyebrow">Roles de respuesta</span>
        <h1>Usuarios</h1>
    </div>
</header>

<section class="grid two">
    <form method="post" action="index.php?route=users" class="panel form">
        <h2>Nuevo usuario</h2>
        <label>Nombre <input name="name" required></label>
        <label>Email <input name="email" type="email" required></label>
        <label>Rol
            <select name="role">
                <option value="inspector">Inspector o enfermeria</option>
                <option value="admin">Administrador</option>
            </select>
        </label>
        <label>Clave <input name="password" type="password" required></label>
        <button class="primary">Crear usuario</button>
    </form>

    <div class="panel">
        <h2>Equipo</h2>
        <div class="stack">
            <?php foreach ($users as $item): ?>
                <div class="item-row">
                    <div>
                        <strong><?= e($item['name']) ?></strong>
                        <span><?= e($item['email']) ?> · <?= e($item['role']) ?></span>
                    </div>
                    <form method="post" action="index.php?route=users">
                        <input type="hidden" name="action" value="delete">
                        <input type="hidden" name="id" value="<?= (int) $item['id'] ?>">
                        <button class="small danger">Desactivar</button>
                    </form>
                </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>
