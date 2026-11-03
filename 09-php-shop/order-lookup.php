<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$reference = '';
$email = '';
$order = false;
$items = [];
$error = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $reference = isset($_POST['reference']) && is_string($_POST['reference']) ? strtoupper(trim($_POST['reference'])) : '';
    $email = isset($_POST['email']) && is_string($_POST['email']) ? trim($_POST['email']) : '';

    if ($reference === '' || strlen($reference) > 20 || filter_var($email, FILTER_VALIDATE_EMAIL) === false || strlen($email) > 160) {
        $error = 'Enter a valid order reference and email address.';
    } else {
        $statement = db()->prepare(
            'SELECT id, reference, customer_name, total, status, created_at
             FROM orders
             WHERE reference = :reference AND email = :email
             LIMIT 1'
        );
        $statement->execute(['reference' => $reference, 'email' => $email]);
        $order = $statement->fetch();

        if ($order) {
            $itemStatement = db()->prepare(
                'SELECT product_name, unit_price, quantity
                 FROM order_items
                 WHERE order_id = :order_id
                 ORDER BY product_name'
            );
            $itemStatement->execute(['order_id' => $order['id']]);
            $items = $itemStatement->fetchAll();
        } else {
            $error = 'We could not find an order matching that reference and email.';
        }
    }
}

$pageTitle = 'Order lookup';
require __DIR__ . '/partials/header.php';
?>
<section class="lookup-page">
    <div class="checkout-heading">
        <p class="eyebrow">Order tracking</p>
        <h1>Look up your order</h1>
        <p>Enter the order reference from your confirmation and the email used at checkout.</p>
    </div>

    <form class="lookup-form" method="post" action="<?= e(url('order-lookup.php')) ?>">
        <?= csrf_field() ?>
        <div class="field">
            <label for="reference">Order reference</label>
            <input id="reference" name="reference" type="text" maxlength="20" placeholder="ORD-20261103-7F3K" value="<?= e($reference) ?>" required>
        </div>
        <div class="field">
            <label for="email">Checkout email</label>
            <input id="email" name="email" type="email" maxlength="160" autocomplete="email" value="<?= e($email) ?>" required>
        </div>
        <button class="button" type="submit">Find order</button>
    </form>

    <?php if ($error !== null): ?><p class="notice error" role="alert"><?= e($error) ?></p><?php endif; ?>

    <?php if ($order): ?>
        <section class="lookup-result" aria-labelledby="lookup-result-title">
            <div class="lookup-result-heading">
                <div><p class="eyebrow">Order status</p><h2 id="lookup-result-title"><?= e($order['reference']) ?></h2></div>
                <span class="status-pill status-<?= e($order['status']) ?>"><?= e(ucfirst($order['status'])) ?></span>
            </div>
            <p class="lookup-date">Placed <?= e(date('M j, Y', strtotime($order['created_at']))) ?></p>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead><tr><th scope="col">Product</th><th scope="col">Unit price</th><th scope="col">Qty</th><th scope="col">Line total</th></tr></thead>
                    <tbody>
                    <?php foreach ($items as $item): ?>
                        <tr>
                            <td data-label="Product"><?= e($item['product_name']) ?></td>
                            <td data-label="Unit price"><?= e(money($item['unit_price'])) ?></td>
                            <td data-label="Qty"><?= (int) $item['quantity'] ?></td>
                            <td data-label="Line total"><?= e(money((float) $item['unit_price'] * (int) $item['quantity'])) ?></td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                    <tfoot><tr><th colspan="3" scope="row">Order total</th><th><?= e(money($order['total'])) ?></th></tr></tfoot>
                </table>
            </div>
        </section>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
