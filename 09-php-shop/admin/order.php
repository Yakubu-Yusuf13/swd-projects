<?php
declare(strict_types=1);

require __DIR__ . '/_auth.php';
admin_require_login();

$id = filter_var($_GET['id'] ?? null, FILTER_VALIDATE_INT);
if ($id === false || $id < 1) {
    http_response_code(404);
    $order = false;
} else {
    $orderStatement = db()->prepare('SELECT * FROM orders WHERE id = :id LIMIT 1');
    $orderStatement->execute(['id' => $id]);
    $order = $orderStatement->fetch();
}

if (!$order && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(404);
}

$error = null;
if ($order && $_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $newStatus = $_POST['status'] ?? '';
    $newStatus = is_string($newStatus) ? $newStatus : '';
    $allowedTransitions = admin_status_transitions((string) $order['status']);

    if (!in_array($newStatus, $allowedTransitions, true)) {
        $error = 'That status change is not allowed.';
    } else {
        $pdo = db();
        $transactionStarted = false;

        try {
            $pdo->beginTransaction();
            $transactionStarted = true;

            $lockOrder = $pdo->prepare('SELECT id, status FROM orders WHERE id = ? FOR UPDATE');
            $lockOrder->execute([$id]);
            $lockedOrder = $lockOrder->fetch();
            if (!$lockedOrder) {
                throw new AdminOrderException('Order no longer exists.');
            }

            $lockedTransitions = admin_status_transitions((string) $lockedOrder['status']);
            if (!in_array($newStatus, $lockedTransitions, true)) {
                throw new AdminOrderException('The order status changed in another session. Reload the page and try again.');
            }

            if ($newStatus === 'cancelled') {
                $itemStatement = $pdo->prepare(
                    'SELECT product_id, quantity
                     FROM order_items
                     WHERE order_id = ?
                     ORDER BY product_id
                     FOR UPDATE'
                );
                $itemStatement->execute([$id]);
                $items = $itemStatement->fetchAll();
                $restoreStock = $pdo->prepare('UPDATE products SET stock = stock + ? WHERE id = ?');

                foreach ($items as $item) {
                    $restoreStock->execute([(int) $item['quantity'], (int) $item['product_id']]);
                    if ($restoreStock->rowCount() !== 1) {
                        throw new AdminOrderException('Could not restore stock for every order item.');
                    }
                }
            }

            $updateOrder = $pdo->prepare('UPDATE orders SET status = ? WHERE id = ?');
            $updateOrder->execute([$newStatus, $id]);
            $pdo->commit();
            $transactionStarted = false;
            $_SESSION['admin_notice'] = $newStatus === 'cancelled'
                ? 'Order cancelled and item stock restored.'
                : 'Order status updated to ' . admin_status_label($newStatus) . '.';
            header('Location: ' . url('admin/order.php') . '?id=' . (int) $id, true, 303);
            exit;
        } catch (Throwable $exception) {
            if ($transactionStarted && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($exception instanceof AdminOrderException) {
                $error = $exception->getMessage();
            } else {
                error_log('Admin order update failed: ' . $exception->getMessage());
                $error = 'The order could not be updated. No changes were saved; please try again.';
            }
        }

        $orderStatement = $pdo->prepare('SELECT * FROM orders WHERE id = :id LIMIT 1');
        $orderStatement->execute(['id' => $id]);
        $order = $orderStatement->fetch();
    }
}

$items = [];
if ($order) {
    $itemsStatement = db()->prepare(
        'SELECT product_name, unit_price, quantity
         FROM order_items
         WHERE order_id = :order_id
         ORDER BY product_name'
    );
    $itemsStatement->execute(['order_id' => $id]);
    $items = $itemsStatement->fetchAll();
}

$notice = $_SESSION['admin_notice'] ?? null;
unset($_SESSION['admin_notice']);
$pageTitle = $order ? 'Order ' . $order['reference'] : 'Order not found';
require __DIR__ . '/../partials/header.php';
require __DIR__ . '/_nav.php';
?>
<section class="admin-page">
    <?php if (!$order): ?>
        <div class="empty-state"><h1>Order not found</h1><p>That order does not exist.</p><a class="button" href="<?= e(url('admin/orders.php')) ?>">Back to orders</a></div>
    <?php else: ?>
        <div class="admin-page-heading">
            <div>
                <p class="eyebrow">Order details</p>
                <h1><?= e($order['reference']) ?></h1>
            </div>
            <a class="button button-secondary" href="<?= e(url('admin/orders.php')) ?>">Back to orders</a>
        </div>

        <?php if ($notice !== null): ?><p class="notice" role="status"><?= e($notice) ?></p><?php endif; ?>
        <?php if ($error !== null): ?><p class="notice error" role="alert"><?= e($error) ?></p><?php endif; ?>

        <div class="order-detail-grid">
            <section class="admin-panel">
                <h2>Customer and delivery</h2>
                <dl class="order-meta">
                    <div><dt>Name</dt><dd><?= e($order['customer_name']) ?></dd></div>
                    <div><dt>Email</dt><dd><?= e($order['email']) ?></dd></div>
                    <div><dt>Phone</dt><dd><?= e($order['phone']) ?></dd></div>
                    <div><dt>Address</dt><dd><?= nl2br(e($order['address'])) ?></dd></div>
                    <div><dt>Placed</dt><dd><?= e(date('M j, Y H:i', strtotime($order['created_at']))) ?></dd></div>
                    <div><dt>Status</dt><dd><span class="status-pill status-<?= e($order['status']) ?>"><?= e(admin_status_label($order['status'])) ?></span></dd></div>
                </dl>
            </section>

            <section class="admin-panel">
                <h2>Items</h2>
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

            <section class="admin-panel status-change-panel">
                <h2>Update status</h2>
                <?php $transitions = admin_status_transitions((string) $order['status']); ?>
                <?php if ($transitions === []): ?>
                    <p class="summary-note">This order is <?= e($order['status']) ?> and cannot be changed further.</p>
                <?php else: ?>
                    <form class="status-change-form" method="post" action="<?= e(url('admin/order.php')) ?>?id=<?= (int) $id ?>">
                        <?= csrf_field() ?>
                        <div class="field">
                            <label for="status">Move from <?= e($order['status']) ?> to</label>
                            <select id="status" name="status" required>
                                <?php foreach ($transitions as $transition): ?>
                                    <option value="<?= e($transition) ?>"><?= e(admin_status_label($transition)) ?></option>
                                <?php endforeach; ?>
                            </select>
                        </div>
                        <?php if (in_array('cancelled', $transitions, true)): ?>
                            <p class="summary-note">Cancelling restores the ordered quantities to product stock.</p>
                        <?php endif; ?>
                        <button class="button" type="submit">Save status</button>
                    </form>
                <?php endif; ?>
            </section>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../partials/footer.php'; ?>
