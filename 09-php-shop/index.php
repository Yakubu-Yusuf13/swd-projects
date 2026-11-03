<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$pdo = db();
$categories = $pdo->query('SELECT name, slug FROM categories ORDER BY name')->fetchAll();
$category = isset($_GET['category']) && is_string($_GET['category']) ? trim($_GET['category']) : '';
$search = isset($_GET['q']) && is_string($_GET['q']) ? trim($_GET['q']) : '';
$sort = isset($_GET['sort']) && is_string($_GET['sort']) ? $_GET['sort'] : 'newest';
$sortOptions = [
    'newest' => 'p.created_at DESC, p.id DESC',
    'price_asc' => 'p.price ASC, p.id DESC',
    'price_desc' => 'p.price DESC, p.id DESC',
];

if (!isset($sortOptions[$sort])) {
    $sort = 'newest';
}

$sql = 'SELECT p.id, p.name, p.slug, p.price, p.stock, p.image_url, c.name AS category_name
        FROM products p
        INNER JOIN categories c ON c.id = p.category_id
        WHERE p.is_active = 1';
$parameters = [];

if ($category !== '') {
    $sql .= ' AND c.slug = :category';
    $parameters['category'] = $category;
}

if ($search !== '') {
    $sql .= ' AND (p.name LIKE :search_name OR p.description LIKE :search_description)';
    $parameters['search_name'] = '%' . $search . '%';
    $parameters['search_description'] = '%' . $search . '%';
}

$sql .= ' ORDER BY ' . $sortOptions[$sort];
$statement = $pdo->prepare($sql);
$statement->execute($parameters);
$products = $statement->fetchAll();

$pageTitle = 'Shop';
require __DIR__ . '/partials/header.php';
?>
<section class="hero">
    <p class="eyebrow">Thoughtful picks, everyday prices</p>
    <h1>Find something<br>good for your day.</h1>
    <p class="hero-copy">Shop dependable phones, laptops and useful accessories, picked to make everyday life a little easier.</p>
</section>

<form class="catalog-toolbar" action="<?= e(url()) ?>" method="get">
    <div class="field">
        <label for="category">Category</label>
        <select id="category" name="category">
            <option value="">All categories</option>
            <?php foreach ($categories as $item): ?>
                <option value="<?= e($item['slug']) ?>" <?= $category === $item['slug'] ? 'selected' : '' ?>><?= e($item['name']) ?></option>
            <?php endforeach; ?>
        </select>
    </div>
    <div class="field">
        <label for="q">Search products</label>
        <input id="q" name="q" type="search" value="<?= e($search) ?>" placeholder="Try “wireless” or “laptop”">
    </div>
    <div class="field">
        <label for="sort">Sort by</label>
        <select id="sort" name="sort">
            <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest arrivals</option>
            <option value="price_asc" <?= $sort === 'price_asc' ? 'selected' : '' ?>>Price: low to high</option>
            <option value="price_desc" <?= $sort === 'price_desc' ? 'selected' : '' ?>>Price: high to low</option>
        </select>
    </div>
    <button class="button" type="submit">Show products</button>
</form>

<section id="products" aria-labelledby="results-title">
    <div class="results-heading">
        <h2 id="results-title"><?= $search !== '' ? 'Search results' : 'Shop all products' ?></h2>
        <p><?= count($products) ?> <?= count($products) === 1 ? 'product' : 'products' ?> found</p>
    </div>

    <?php if ($products === []): ?>
        <div class="empty-state">
            <h2>No products found</h2>
            <p>Try a different search or category.</p>
        </div>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($products as $product): ?>
                <article class="product-card">
                    <a class="product-image-wrap" href="<?= e(url('product.php')) ?>?slug=<?= rawurlencode($product['slug']) ?>" aria-label="View <?= e($product['name']) ?>">
                        <img class="product-image" src="<?= e($product['image_url'] ?: 'https://placehold.co/600x400?text=Everyday+Shop') ?>" alt="<?= e($product['name']) ?>" loading="lazy">
                        <?php if ((int) $product['stock'] < 1): ?>
                            <span class="stock-badge">Out of stock</span>
                        <?php endif; ?>
                    </a>
                    <div class="product-info">
                        <p class="product-category"><?= e($product['category_name']) ?></p>
                        <a class="product-name" href="<?= e(url('product.php')) ?>?slug=<?= rawurlencode($product['slug']) ?>"><?= e($product['name']) ?></a>
                        <p class="product-price"><?= e(money($product['price'])) ?></p>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
