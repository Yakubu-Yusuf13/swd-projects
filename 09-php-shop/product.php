<?php
declare(strict_types=1);

require __DIR__ . '/app/cart.php';

$slug = isset($_GET['slug']) && is_string($_GET['slug']) ? trim($_GET['slug']) : '';
$productStatement = $pdo->prepare(
    'SELECT p.id, p.name, p.slug, p.description, p.price, p.stock, p.image_url,
            c.name AS category_name
     FROM products p
     INNER JOIN categories c ON c.id = p.category_id
     WHERE p.slug = :slug AND p.is_active = :active
     LIMIT 1'
);
$productStatement->execute(['slug' => $slug, 'active' => 1]);
$product = $productStatement->fetch();

if (!$product) {
    http_response_code(404);
    $pageTitle = 'Product not found';
    require __DIR__ . '/partials/header.php';
    ?>
    <section class="not-found section-wrap">
        <p class="eyebrow">404 · NOT FOUND</p>
        <h1>That product isn’t here.</h1>
        <p>It may have moved or is no longer available.</p>
        <a class="button button-dark" href="<?= e(BASE_URL) ?>/">Back to the shop</a>
    </section>
    <?php
    require __DIR__ . '/partials/footer.php';
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        set_flash('error', 'Your session could not be verified. Please try adding the item again.');
    } else {
        $quantityValue = $_POST['quantity'] ?? null;
        $quantity = is_scalar($quantityValue)
            ? filter_var($quantityValue, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;

        if ($quantity === false) {
            set_flash('error', 'Choose a valid quantity to add to your cart.');
        } else {
            cart_add((int) $product['id'], $quantity);
        }
    }

    header('Location: ' . BASE_URL . '/product.php?slug=' . rawurlencode($product['slug']), true, 303);
    exit;
}

$pageTitle = $product['name'];
$imageUrl = $product['image_url'] ?: 'https://placehold.co/600x400?text=' . rawurlencode($product['name']);
require __DIR__ . '/partials/header.php';
?>
<section class="product-detail section-wrap">
    <nav class="breadcrumbs" aria-label="Breadcrumb">
        <a href="<?= e(BASE_URL) ?>/">Shop</a>
        <span aria-hidden="true">/</span>
        <span><?= e($product['category_name']) ?></span>
    </nav>
    <div class="detail-layout">
        <div class="detail-image">
            <img src="<?= e($imageUrl) ?>" alt="<?= e($product['name']) ?>">
            <?php if ((int) $product['stock'] < 1): ?>
                <span class="stock-badge">Out of stock</span>
            <?php endif; ?>
        </div>
        <div class="detail-copy">
            <p class="eyebrow"><?= e($product['category_name']) ?></p>
            <h1><?= e($product['name']) ?></h1>
            <p class="detail-price"><?= e(money($product['price'])) ?></p>
            <p class="detail-description"><?= nl2br(e($product['description'])) ?></p>
            <p class="stock-note <?= (int) $product['stock'] < 1 ? 'stock-note-out' : '' ?>">
                <?= (int) $product['stock'] > 0
                    ? (int) $product['stock'] . ' in stock'
                    : 'Currently unavailable' ?>
            </p>
            <form class="add-to-cart-form" method="post" action="<?= e(BASE_URL) ?>/product.php?slug=<?= rawurlencode($product['slug']) ?>">
                <?= csrf_field() ?>
                <label for="quantity">Quantity</label>
                <div class="purchase-controls">
                    <input
                        id="quantity"
                        name="quantity"
                        type="number"
                        value="1"
                        min="1"
                        max="<?= (int) $product['stock'] ?>"
                        required
                        <?= (int) $product['stock'] < 1 ? 'disabled' : '' ?>
                    >
                    <button class="button button-green add-button" type="submit" <?= (int) $product['stock'] < 1 ? 'disabled' : '' ?>>
                        Add to cart <span aria-hidden="true">→</span>
                    </button>
                </div>
            </form>
            <p class="secure-note"><span aria-hidden="true">✓</span> Carefully selected tech, ready for you.</p>
        </div>
    </div>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
