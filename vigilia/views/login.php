<section class="login-card">
    <div>
        <span class="eyebrow">Seguridad escolar inteligente</span>
        <h1>VigIA Escolar</h1>
        <p>Monitoreo 24/7 para detectar caídas y peleas, avisando a inspectoría o enfermería cuando cada segundo cuenta.</p>
    </div>
    <form method="post" action="index.php?route=login" class="panel form">
        <h2>Ingreso</h2>
        <?php if (!empty($_SESSION['flash'])): ?>
            <div class="alert"><?= e($_SESSION['flash']); unset($_SESSION['flash']); ?></div>
        <?php endif; ?>
        <label>Email
            <input name="email" type="email" value="admin@vigilia.local" required>
        </label>
        <label>Clave
            <input name="password" type="password" value="admin123" required>
        </label>
        <button class="primary">Entrar</button>
        <small>Admin: admin@vigilia.local / admin123</small>
        <small>Inspector: inspector@vigilia.local / usuario123</small>
    </form>
</section>
