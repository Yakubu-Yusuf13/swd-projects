<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$slug = isset($_GET['slug']) && is_string($_GET['slug']) ? trim($_GET['slug']) : '';
$statement = db()->prepare(
    'SELECT p.id, p.name, p.slug, p.description, p.price, p.stock, p.image_url, c.name AS category_name
     FROM products p
     INNER JOIN categories c ON c.id = p.category_id
     WHERE p.slug = :slug AND p.is_active = 1
     LIMIT 1'
);
$statement->execute(['slug' => $slug]);
$product = $statement->fetch();

if (!$product) {
    http_response_code(404);
}

$notice = $_SESSION['cart_notice'] ?? null;
$noticeType = $_SESSION['cart_notice_type'] ?? 'success';
unset($_SESSION['cart_notice'], $_SESSION['cart_notice_type']);

if ($product && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT);
    $cart = $_SESSION['cart'] ?? [];
    $alreadyInCart = (int) ($cart[(int) $product['id']] ?? 0);

    if ($quantity === false || $quantity < 1) {
        $notice = 'Choose a valid quantity.';
        $noticeType = 'error';
    } elseif ((int) $product['stock'] < 1) {
        $notice = 'This product is currently out of stock.';
        $noticeType = 'error';
    } elseif ($alreadyInCart + $quantity > (int) $product['stock']) {
        $available = max(0, (int) $product['stock'] - $alreadyInCart);
        $notice = $available > 0
            ? "Only {$available} more can be added to your cart."
            : 'Your cart already has all available stock of this product.';
        $noticeType = 'error';
    } else {
        $_SESSION['cart'][(int) $product['id']] = $alreadyInCart + $quantity;
        $notice = $product['name'] . ' added to your cart.';
        $noticeType = 'success';
    }

    $_SESSION['cart_notice'] = $notice;
    $_SESSION['cart_notice_type'] = $noticeType;
    header('Location: ' . url('product.php') . '?slug=' . rawurlencode($product['slug']), true, 303);
    exit;
}

$pageTitle = $product ? $product['name'] : 'Product not found';
require __DIR__ . '/partials/header.php';
?>
<?php if (!$product): ?>
    <div class="empty-state" style="margin: 48px 0">
        <h1>Product not found</h1>
        <p>This product may be unavailable or the link may have changed.</p>
        <p><a class="button" href="<?= e(url()) ?>">Back to shop</a></p>
    </div>
<?php else: ?>
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <a href="<?= e(url()) ?>">Shop</a><span aria-hidden="true">/</span><span><?= e($product['category_name']) ?></span>
    </nav>
    <article class="product-detail">
        <div class="detail-image-wrap">
            <img class="detail-image" src="<?= e($product['image_url'] ?: 'https://placehold.co/600x400?text=Everyday+Shop') ?>" alt="<?= e($product['name']) ?>">
        </div>
        <div class="detail-copy">
            <p class="eyebrow"><?= e($product['category_name']) ?></p>
            <h1><?= e($product['name']) ?></h1>
            <p class="detail-price"><?= e(money($product['price'])) ?></p>
            <p class="detail-description"><?= e($product['description']) ?></p>
            <?php if ((int) $product['stock'] > 0): ?>
                <p class="stock-status"><?= (int) $product['stock'] ?> in stock</p>
            <?php else: ?>
                <p class="stock-status out">Out of stock</p>
            <?php endif; ?>

            <?php if ($notice !== null): ?>
                <p class="notice <?= $noticeType === 'error' ? 'error' : '' ?>" role="status"><?= e($notice) ?></p>
            <?php endif; ?>

            <form class="add-form" method="post" action="<?= e(url('product.php')) ?>?slug=<?= rawurlencode($product['slug']) ?>">
                <?= csrf_field() ?>
                <div class="field quantity-field">
                    <label for="quantity">Quantity</label>
                    <input class="quantity-input" id="quantity" name="quantity" type="number" min="1" max="<?= (int) $product['stock'] ?>" value="1" required <?= (int) $product['stock'] < 1 ? 'disabled' : '' ?>>
                </div>
                <button class="button" type="submit" <?= (int) $product['stock'] < 1 ? 'disabled' : '' ?>>Add to cart</button>
                <a class="button button-secondary" href="<?= e(url()) ?>">Continue shopping</a>
            </form>
        </div>
    </article>
<?php endif; ?>
<?php require __DIR__ . '/partials/footer.php'; ?>
