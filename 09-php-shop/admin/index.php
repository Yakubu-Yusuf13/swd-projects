<?php
declare(strict_types=1);

require __DIR__ . '/_auth.php';
admin_require_login();

$pdo = db();
$salesStatement = $pdo->query(
    "SELECT COALESCE(SUM(total), 0) AS sales_total
     FROM orders
     WHERE DATE(created_at) = CURRENT_DATE
       AND status IN ('paid', 'shipped', 'delivered')"
);
$todaySales = $salesStatement->fetchColumn();

$statusCounts = array_fill_keys(['pending', 'paid', 'shipped', 'delivered', 'cancelled'], 0);
foreach ($pdo->query('SELECT status, COUNT(*) AS order_count FROM orders GROUP BY status')->fetchAll() as $row) {
    $statusCounts[$row['status']] = (int) $row['order_count'];
}

$topProducts = $pdo->query(
    "SELECT oi.product_name, SUM(oi.quantity) AS quantity_sold
     FROM order_items oi
     INNER JOIN orders o ON o.id = oi.order_id
     WHERE o.status <> 'cancelled'
     GROUP BY oi.product_id, oi.product_name
     ORDER BY quantity_sold DESC, oi.product_name ASC
     LIMIT 5"
)->fetchAll();

$pageTitle = 'Admin dashboard';
require __DIR__ . '/../partials/header.php';
require __DIR__ . '/_nav.php';
?>
<section class="admin-page">
    <div class="admin-page-heading">
        <div>
            <p class="eyebrow">Store overview</p>
            <h1>Dashboard</h1>
        </div>
        <a class="button" href="<?= e(url('admin/orders.php')) ?>">Manage orders</a>
    </div>

    <article class="dashboard-sales">
        <p>Today's sales <span>Paid and fulfilled orders</span></p>
        <strong><?= e(money($todaySales)) ?></strong>
    </article>

    <section class="admin-section" aria-labelledby="status-title">
        <div class="admin-section-heading">
            <h2 id="status-title">Orders by status</h2>
            <a href="<?= e(url('admin/orders.php')) ?>">View all orders</a>
        </div>
        <div class="status-card-grid">
            <?php foreach ($statusCounts as $status => $count): ?>
                <a class="status-card" href="<?= e(url('admin/orders.php')) ?>?status=<?= rawurlencode($status) ?>">
                    <span><?= e(admin_status_label($status)) ?></span>
                    <strong><?= $count ?></strong>
                </a>
            <?php endforeach; ?>
        </div>
    </section>

    <section class="admin-section" aria-labelledby="top-products-title">
        <div class="admin-section-heading">
            <h2 id="top-products-title">Top 5 products</h2>
            <span>By quantity sold (excluding cancelled orders)</span>
        </div>
        <?php if ($topProducts === []): ?>
            <div class="empty-state"><p>No completed sales to show yet.</p></div>
        <?php else: ?>
            <div class="admin-table-wrap">
                <table class="admin-table">
                    <thead><tr><th scope="col">Product</th><th scope="col">Quantity sold</th></tr></thead>
                    <tbody>
                    <?php foreach ($topProducts as $product): ?>
                        <tr><td><?= e($product['product_name']) ?></td><td><?= (int) $product['quantity_sold'] ?></td></tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
    </section>
</section>
<?php require __DIR__ . '/../partials/footer.php'; ?>
