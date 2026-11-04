<?php
$flash = take_flash();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f6f7f4">
    <title><?= e($pageTitle ?? 'Shop') ?> | SWD Shop</title>
    <link rel="stylesheet" href="<?= e(BASE_URL) ?>/assets/style.css">
</head>
<body>
<header class="site-header">
    <div class="header-inner">
        <a class="brand" href="<?= e(BASE_URL) ?>" aria-label="SWD Shop home">
            <span class="brand-mark">S</span>
            <span>SWD<span class="brand-light"> Shop</span></span>
        </a>
        <nav class="main-nav" aria-label="Main navigation">
            <a href="<?= e(BASE_URL) ?>">Shop</a>
            <a class="cart-badge" href="<?= e(BASE_URL) ?>/cart.php" aria-label="Cart with <?= cart_count() ?> items">
                Cart <span class="cart-count"><?= cart_count() ?></span>
            </a>
        </nav>
    </div>
</header>
<?php if ($flash !== null): ?>
    <div class="flash-wrap">
        <p class="flash flash-<?= e($flash['type'] ?? 'success') ?>" role="status"><?= e($flash['message'] ?? '') ?></p>
    </div>
<?php endif; ?>
<main class="page-content">
