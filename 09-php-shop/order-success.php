<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$reference = isset($_GET['ref']) && is_string($_GET['ref']) ? $_GET['ref'] : '';
$lastOrderReference = $_SESSION['last_order_reference'] ?? '';
$order = false;

if ($reference !== '' && is_string($lastOrderReference) && hash_equals($lastOrderReference, $reference)) {
    $statement = db()->prepare(
        'SELECT reference, total, status, created_at
         FROM orders
         WHERE reference = :reference
         LIMIT 1'
    );
    $statement->execute(['reference' => $reference]);
    $order = $statement->fetch();
}

if (!$order) {
    http_response_code(404);
}

$pageTitle = $order ? 'Order confirmed' : 'Order not found';
require __DIR__ . '/partials/header.php';
?>
<section class="order-success">
    <?php if ($order): ?>
        <div class="success-icon" aria-hidden="true">&#10003;</div>
        <p class="eyebrow">Order received</p>
        <h1>Thank you for your order.</h1>
        <p class="success-copy">Your order has been placed successfully. Keep this reference for your records.</p>
        <div class="order-reference">
            <span>Order reference</span>
            <strong><?= e($order['reference']) ?></strong>
        </div>
        <p class="success-total">Order total <strong><?= e(money($order['total'])) ?></strong></p>
        <a class="button" href="<?= e(url()) ?>">Continue shopping</a>
    <?php else: ?>
        <div class="empty-state">
            <h1>Order not found</h1>
            <p>This confirmation is unavailable. If you just placed an order, contact the shop with your confirmation details.</p>
            <a class="button" href="<?= e(url()) ?>">Back to shop</a>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
