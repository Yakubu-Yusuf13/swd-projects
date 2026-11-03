<?php
require_once __DIR__ . '/../app/cart.php';

$pageTitle = $pageTitle ?? 'Everyday Shop';
$cartCount = cart_count();
?>
<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#f7f8fa">
    <title><?= e($pageTitle) ?> | Everyday Shop</title>
    <link rel="stylesheet" href="<?= e(url('assets/style.css')) ?>">
</head>
<body>
<header class="site-header">
    <div class="header-inner">
        <a class="brand" href="<?= e(url()) ?>" aria-label="Everyday Shop home">
            <span class="brand-mark">e</span>
            <span>everyday<span class="brand-accent">.</span></span>
        </a>
        <nav class="main-nav" aria-label="Main navigation">
            <a href="<?= e(url()) ?>">Shop</a>
            <a href="<?= e(url('order-lookup.php')) ?>">Track order</a>
            <a class="cart-link" href="<?= e(url('cart.php')) ?>">
                Cart <span class="cart-badge" aria-label="<?= $cartCount ?> items"><?= $cartCount ?></span>
            </a>
        </nav>
    </div>
</header>
<main class="page-shell">
