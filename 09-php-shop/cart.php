<?php
declare(strict_types=1);

require __DIR__ . '/app/cart.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST') {
    if (!csrf_is_valid($_POST['csrf_token'] ?? null)) {
        http_response_code(400);
        set_flash('error', 'Your session could not be verified. Please try again.');
        header('Location: ' . BASE_URL . '/cart.php', true, 303);
        exit;
    }

    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
    $productId = isset($_POST['product_id'])
        ? filter_var($_POST['product_id'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
        : false;

    if ($action === 'clear') {
        cart_clear();
        set_flash('success', 'Your cart has been emptied.');
    } elseif ($productId === false) {
        set_flash('error', 'The selected cart item is invalid.');
    } elseif ($action === 'remove') {
        cart_remove($productId);
        set_flash('success', 'Item removed from your cart.');
    } elseif ($action === 'update') {
        $quantity = isset($_POST['quantity'])
            ? filter_var($_POST['quantity'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]])
            : false;
        if ($quantity === false) {
            set_flash('error', 'Enter a quantity of at least one, or remove the item.');
        } else {
            cart_update($productId, $quantity);
        }
    } else {
        set_flash('error', 'Unknown cart action.');
    }

    header('Location: ' . BASE_URL . '/cart.php', true, 303);
    exit;
}

$items = cart_items();
$total = cart_total();
$pageTitle = 'Your cart';
require __DIR__ . '/partials/header.php';
?>
<section class="cart-page section-wrap">
    <div class="section-heading cart-heading">
        <div>
            <p class="eyebrow">YOUR PICKS, ALL IN ONE PLACE</p>
            <h1>Your cart</h1>
        </div>
        <span class="result-count"><?= cart_count() ?> <?= cart_count() === 1 ? 'item' : 'items' ?></span>
    </div>

    <?php if ($items === []): ?>
        <div class="empty-state">
            <span class="empty-icon" aria-hidden="true">⌑</span>
            <h2>Your cart is waiting</h2>
            <p>When you find something you love, it’ll show up here.</p>
            <a class="button button-dark" href="<?= e(BASE_URL) ?>/">Browse the shop</a>
        </div>
    <?php else: ?>
        <div class="cart-layout">
            <div class="cart-table-wrap">
                <table class="cart-table">
                    <thead>
                        <tr>
                            <th scope="col">Product</th>
                            <th scope="col">Price</th>
                            <th scope="col">Quantity</th>
                            <th scope="col">Total</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($items as $item): ?>
                            <?php $imageUrl = $item['image_url'] ?: 'https://placehold.co/600x400?text=' . rawurlencode($item['name']); ?>
                            <tr>
                                <td class="cart-product-cell">
                                    <a class="cart-product-image" href="<?= e(BASE_URL) ?>/product.php?slug=<?= rawurlencode($item['slug']) ?>">
                                        <img src="<?= e($imageUrl) ?>" alt="">
                                    </a>
                                    <div class="cart-product-info">
                                        <a class="cart-product-name" href="<?= e(BASE_URL) ?>/product.php?slug=<?= rawurlencode($item['slug']) ?>"><?= e($item['name']) ?></a>
                                        <span class="cart-stock"><?= (int) $item['stock'] ?> in stock</span>
                                    </div>
                                </td>
                                <td data-label="Price"><?= e(money($item['price'])) ?></td>
                                <td data-label="Quantity">
                                    <form class="cart-item-form" method="post" action="<?= e(BASE_URL) ?>/cart.php">
                                        <?= csrf_field() ?>
                                        <input type="hidden" name="product_id" value="<?= (int) $item['product_id'] ?>">
                                        <div class="cart-quantity-control">
                                            <input
                                                aria-label="Quantity for <?= e($item['name']) ?>"
                                                name="quantity"
                                                type="number"
                                                min="1"
                                                max="<?= (int) $item['stock'] ?>"
                                                value="<?= (int) $item['quantity'] ?>"
                                                required
                                            >
                                            <button class="cart-action" type="submit" name="action" value="update">Update</button>
                                        </div>
                                        <button class="cart-action cart-remove" type="submit" name="action" value="remove">Remove</button>
                                    </form>
                                </td>
                                <td class="cart-line-total" data-label="Total"><?= e(money($item['line_total'])) ?></td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            </div>

            <aside class="cart-summary">
                <p class="eyebrow">ORDER SUMMARY</p>
                <div class="summary-row">
                    <span>Subtotal</span>
                    <strong><?= e(money($total)) ?></strong>
                </div>
                <p class="summary-note">Shipping and any applicable fees are calculated at checkout.</p>
                <a class="button button-green checkout-button" href="<?= e(BASE_URL) ?>/checkout.php">
                    Proceed to checkout <span aria-hidden="true">→</span>
                </a>
                <a class="continue-shopping" href="<?= e(BASE_URL) ?>/">Continue shopping</a>
                <form method="post" action="<?= e(BASE_URL) ?>/cart.php">
                    <?= csrf_field() ?>
                    <button class="clear-cart" type="submit" name="action" value="clear">Clear cart</button>
                </form>
            </aside>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
