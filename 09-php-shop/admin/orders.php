<?php
declare(strict_types=1);

require __DIR__ . '/_auth.php';
admin_require_login();

$statuses = ['pending', 'paid', 'shipped', 'delivered', 'cancelled'];
$status = isset($_GET['status']) && is_string($_GET['status']) ? $_GET['status'] : '';
$from = isset($_GET['from']) && is_string($_GET['from']) ? trim($_GET['from']) : '';
$to = isset($_GET['to']) && is_string($_GET['to']) ? trim($_GET['to']) : '';
$filterErrors = [];
$conditions = [];
$parameters = [];

if ($status !== '' && !in_array($status, $statuses, true)) {
    $filterErrors[] = 'Choose a valid order status.';
    $status = '';
} elseif ($status !== '') {
    $conditions[] = 'o.status = :status';
    $parameters['status'] = $status;
}

$validDate = static function (string $date): bool {
    $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $date);
    return $parsed !== false && $parsed->format('Y-m-d') === $date;
};

if ($from !== '' && !$validDate($from)) {
    $filterErrors[] = 'Enter a valid start date.';
    $from = '';
}
if ($to !== '' && !$validDate($to)) {
    $filterErrors[] = 'Enter a valid end date.';
    $to = '';
}
if ($from !== '' && $to !== '' && $from > $to) {
    $filterErrors[] = 'The start date must be on or before the end date.';
}
if ($from !== '') {
    $conditions[] = 'o.created_at >= :date_from';
    $parameters['date_from'] = $from . ' 00:00:00';
}
if ($to !== '') {
    $conditions[] = 'o.created_at < DATE_ADD(:date_to, INTERVAL 1 DAY)';
    $parameters['date_to'] = $to;
}

$sql = 'SELECT o.id, o.reference, o.customer_name, o.email, o.total, o.status, o.created_at,
               COALESCE(SUM(oi.quantity), 0) AS item_count
        FROM orders o
        LEFT JOIN order_items oi ON oi.order_id = o.id';
if ($conditions !== []) {
    $sql .= ' WHERE ' . implode(' AND ', $conditions);
}
$sql .= ' GROUP BY o.id, o.reference, o.customer_name, o.email, o.total, o.status, o.created_at
          ORDER BY o.created_at DESC, o.id DESC';

$statement = db()->prepare($sql);
$statement->execute($parameters);
$orders = $statement->fetchAll();

$notice = $_SESSION['admin_notice'] ?? null;
unset($_SESSION['admin_notice']);

$pageTitle = 'Orders';
require __DIR__ . '/../partials/header.php';
require __DIR__ . '/_nav.php';
?>
<section class="admin-page">
    <div class="admin-page-heading">
        <div>
            <p class="eyebrow">Store management</p>
            <h1>Orders</h1>
        </div>
        <span class="admin-result-count"><?= count($orders) ?> orders</span>
    </div>

    <?php if ($notice !== null): ?><p class="notice" role="status"><?= e($notice) ?></p><?php endif; ?>
    <?php foreach ($filterErrors as $filterError): ?><p class="notice error" role="alert"><?= e($filterError) ?></p><?php endforeach; ?>

    <form class="admin-filter-form" method="get" action="<?= e(url('admin/orders.php')) ?>">
        <div class="field">
            <label for="status">Status</label>
            <select id="status" name="status">
                <option value="">All statuses</option>
                <?php foreach ($statuses as $option): ?>
                    <option value="<?= e($option) ?>" <?= $status === $option ? 'selected' : '' ?>><?= e(admin_status_label($option)) ?></option>
                <?php endforeach; ?>
            </select>
        </div>
        <div class="field">
            <label for="from">From date</label>
            <input id="from" name="from" type="date" value="<?= e($from) ?>">
        </div>
        <div class="field">
            <label for="to">To date</label>
            <input id="to" name="to" type="date" value="<?= e($to) ?>">
        </div>
        <button class="button" type="submit">Apply filters</button>
        <a class="button button-secondary" href="<?= e(url('admin/orders.php')) ?>">Clear</a>
    </form>

    <?php if ($orders === []): ?>
        <div class="empty-state"><h2>No orders found</h2><p>Try changing the status or date filters.</p></div>
    <?php else: ?>
        <div class="admin-table-wrap">
            <table class="admin-table">
                <thead>
                <tr><th scope="col">Reference</th><th scope="col">Customer</th><th scope="col">Date</th><th scope="col">Items</th><th scope="col">Total</th><th scope="col">Status</th><th scope="col"><span class="visually-hidden">Details</span></th></tr>
                </thead>
                <tbody>
                <?php foreach ($orders as $order): ?>
                    <tr>
                        <td data-label="Reference"><strong><?= e($order['reference']) ?></strong></td>
                        <td data-label="Customer"><?= e($order['customer_name']) ?><span class="admin-cell-subtitle"><?= e($order['email']) ?></span></td>
                        <td data-label="Date"><?= e(date('M j, Y H:i', strtotime($order['created_at']))) ?></td>
                        <td data-label="Items"><?= (int) $order['item_count'] ?></td>
                        <td data-label="Total"><?= e(money($order['total'])) ?></td>
                        <td data-label="Status"><span class="status-pill status-<?= e($order['status']) ?>"><?= e(admin_status_label($order['status'])) ?></span></td>
                        <td data-label="Details"><a class="text-button" href="<?= e(url('admin/order.php')) ?>?id=<?= (int) $order['id'] ?>">View order</a></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/../partials/footer.php'; ?>
