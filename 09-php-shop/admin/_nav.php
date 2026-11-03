<nav class="admin-nav" aria-label="Admin navigation">
    <a href="<?= e(url('admin/index.php')) ?>">Dashboard</a>
    <a href="<?= e(url('admin/orders.php')) ?>">Orders</a>
    <a href="<?= e(url('order-lookup.php')) ?>">Customer order lookup</a>
    <form method="post" action="<?= e(url('admin/logout.php')) ?>">
        <?= csrf_field() ?>
        <button class="text-button remove-button" type="submit">Sign out</button>
    </form>
</nav>
