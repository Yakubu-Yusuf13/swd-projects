<?php
declare(strict_types=1);

require __DIR__ . '/app/cart.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $action = isset($_POST['action']) && is_string($_POST['action']) ? $_POST['action'] : '';
    $id = filter_var($_POST['product_id'] ?? null, FILTER_VALIDATE_INT);

    if ($action === 'update' && $id !== false) {
        $quantity = filter_var($_POST['quantity'] ?? null, FILTER_VALIDATE_INT);
        if ($quantity === false) {
            cart_notice('Enter a valid quantity.', 'error');
        } else {
            cart_update($id, $quantity);
        }
    } elseif ($action === 'remove' && $id !== false) {
        cart_remove($id);
    } elseif ($action === 'clear') {
        cart_clear();
    } elseif ($action === 'checkout') {
        cart_notice('Checkout is not available yet.', 'error');
    } else {
        cart_notice('That cart action is not valid.', 'error');
    }

    header('Location: ' . url('cart.php'), true, 303);
    exit;
}

$items = cart_items();
$total = cart_total();
$notice = $_SESSION['cart_notice'] ?? null;
$noticeType = $_SESSION['cart_notice_type'] ?? 'success';
unset($_SESSION['cart_notice'], $_SESSION['cart_notice_type']);

$pageTitle = 'Your cart';
require __DIR__ . '/partials/header.php';
?>
<section class="cart-page">
    <div class="cart-page-heading">
        <div>
            <p class="eyebrow">Your selection</p>
            <h1>Your shopping cart</h1>
        </div>
        <a class="button button-secondary" href="<?= e(url()) ?>">Continue shopping</a>
    </div>

    <?php if ($notice !== null): ?>
        <p class="notice <?= $noticeType === 'error' ? 'error' : '' ?>" role="status"><?= e($notice) ?></p>
    <?php endif; ?>

    <?php if ($items === []): ?>
        <div class="empty-state cart-empty">
            <h2>Your cart is empty</h2>
            <p>Browse the shop and add something you like.</p>
            <a class="button" href="<?= e(url()) ?>">Explore products</a>
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
                        <th scope="col"><span class="visually-hidden">Actions</span></th>
                    </tr>
                    </thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td data-label="Product">
                                <div class="cart-product">
                                    <a class="cart-product-image" href="<?= e(url('product.php')) ?>?slug=<?= rawurlencode($item['slug']) ?>">
                                        <img src="<?= e($item['image_url'] ?: 'https://placehold.co/600x400?text=Everyday+Shop') ?>" alt="">
                                    </a>
                                    <div>
                                        <a class="cart-product-name" href="<?= e(url('product.php')) ?>?slug=<?= rawurlencode($item['slug']) ?>"><?= e($item['name']) ?></a>
                                        <?php if (!$item['stock_ok']): ?>
                                            <span class="cart-stock-warning">Quantity adjusted to current stock</span>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </td>
                            <td data-label="Price"><?= e(money($item['price'])) ?></td>
                            <td data-label="Quantity">
                                <form class="cart-update-form" method="post" action="<?= e(url('cart.php')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="update">
                                    <input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>">
                                    <input class="quantity-input cart-quantity" type="number" name="quantity" min="1" max="<?= (int) $item['stock'] ?>" value="<?= (int) $item['quantity'] ?>" aria-label="Quantity for <?= e($item['name']) ?>" required>
                                    <button class="text-button" type="submit">Update</button>
                                </form>
                            </td>
                            <td data-label="Total" class="cart-line-total"><?= e(money($item['line_total'])) ?></td>
                            <td data-label="Actions">
                                <form method="post" action="<?= e(url('cart.php')) ?>">
                                    <?= csrf_field() ?>
                                    <input type="hidden" name="action" value="remove">
                                    <input type="hidden" name="product_id" value="<?= (int) $item['id'] ?>">
                                    <button class="text-button remove-button" type="submit">Remove</button>
                                </form>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
                <form class="clear-cart-form" method="post" action="<?= e(url('cart.php')) ?>">
                    <?= csrf_field() ?>
                    <input type="hidden" name="action" value="clear">
                    <button class="text-button remove-button" type="submit">Clear cart</button>
                </form>
            </div>

            <aside class="cart-summary" aria-labelledby="summary-title">
                <h2 id="summary-title">Order summary</h2>
                <div class="summary-row"><span>Subtotal</span><strong><?= e(money($total)) ?></strong></div>
                <p class="summary-note">Shipping and any applicable charges are calculated at checkout.</p>
                <a class="button checkout-button" href="<?= e(url('checkout.php')) ?>">Proceed to checkout</a>
            </aside>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
