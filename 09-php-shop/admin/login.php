<?php
declare(strict_types=1);

require __DIR__ . '/_auth.php';

if (admin_is_authenticated()) {
    header('Location: ' . url('admin/index.php'), true, 303);
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $password = $_POST['password'] ?? '';
    $passwordHash = getenv('ADMIN_PASSWORD_HASH');

    if (!is_string($password) || strlen($password) > 1024 || !is_string($passwordHash) || $passwordHash === '') {
        error_log('Admin login unavailable: ADMIN_PASSWORD_HASH is not configured.');
        $error = 'Admin login is not configured. Set ADMIN_PASSWORD_HASH in the server environment.';
    } elseif (password_verify($password, $passwordHash)) {
        session_regenerate_id(true);
        $_SESSION['is_admin'] = true;
        header('Location: ' . url('admin/index.php'), true, 303);
        exit;
    } else {
        $error = 'The password was not correct.';
    }
}

$pageTitle = 'Admin login';
require __DIR__ . '/../partials/header.php';
?>
<section class="admin-login-page">
    <form class="admin-login-card" method="post" action="<?= e(url('admin/login.php')) ?>">
        <?= csrf_field() ?>
        <p class="eyebrow">Store management</p>
        <h1>Admin sign in</h1>
        <p>Enter the store admin password to continue.</p>
        <?php if ($error !== null): ?>
            <p class="notice error" role="alert"><?= e($error) ?></p>
        <?php endif; ?>
        <div class="field">
            <label for="password">Password</label>
            <input id="password" name="password" type="password" autocomplete="current-password" required>
        </div>
        <button class="button" type="submit">Sign in</button>
    </form>
</section>
<?php require __DIR__ . '/../partials/footer.php'; ?>
