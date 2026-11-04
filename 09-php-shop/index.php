<?php
declare(strict_types=1);

require __DIR__ . '/app/bootstrap.php';

$pageTitle = 'Shop';
$categorySlug = isset($_GET['category']) && is_string($_GET['category'])
    ? trim($_GET['category'])
    : '';
$search = isset($_GET['q']) && is_string($_GET['q'])
    ? trim($_GET['q'])
    : '';
$requestedSort = isset($_GET['sort']) && is_string($_GET['sort'])
    ? $_GET['sort']
    : 'newest';

$sortOptions = [
    'newest' => 'Newest',
    'price_asc' => 'Price: low to high',
    'price_desc' => 'Price: high to low',
];
$sort = array_key_exists($requestedSort, $sortOptions) ? $requestedSort : 'newest';
$orderBy = [
    'newest' => 'p.created_at DESC, p.id DESC',
    'price_asc' => 'p.price ASC, p.id DESC',
    'price_desc' => 'p.price DESC, p.id DESC',
][$sort];

$categoryStatement = $pdo->prepare('SELECT id, name, slug FROM categories ORDER BY name');
$categoryStatement->execute();
$categories = $categoryStatement->fetchAll();

$conditions = ['p.is_active = :active'];
$parameters = ['active' => 1];
if ($categorySlug !== '') {
    $conditions[] = 'c.slug = :category_slug';
    $parameters['category_slug'] = $categorySlug;
}
if ($search !== '') {
    $conditions[] = '(p.name LIKE :name_search OR p.description LIKE :description_search)';
    $parameters['name_search'] = '%' . $search . '%';
    $parameters['description_search'] = '%' . $search . '%';
}

$sql = 'SELECT p.id, p.name, p.slug, p.description, p.price, p.stock, p.image_url,
               c.name AS category_name, c.slug AS category_slug
        FROM products p
        INNER JOIN categories c ON c.id = p.category_id
        WHERE ' . implode(' AND ', $conditions) . '
        ORDER BY ' . $orderBy;
$productStatement = $pdo->prepare($sql);
$productStatement->execute($parameters);
$products = $productStatement->fetchAll();

require __DIR__ . '/partials/header.php';
?>
<section class="hero">
    <div class="hero-copy">
        <p class="eyebrow">THE EVERYDAY TECH EDIT</p>
        <h1>Good tech.<br><span>Better days.</span></h1>
        <p>Discover thoughtfully picked phones, laptops and accessories for work, play and everything in between.</p>
        <a class="button button-dark" href="#products">Explore the collection <span aria-hidden="true">↓</span></a>
    </div>
    <div class="hero-art" aria-hidden="true">
        <div class="hero-orbit orbit-one"></div>
        <div class="hero-orbit orbit-two"></div>
        <div class="hero-device">
            <div class="device-screen"></div>
            <div class="device-camera"></div>
        </div>
        <span class="hero-spark spark-one">✳</span>
        <span class="hero-spark spark-two">✦</span>
        <span class="hero-note">Made for<br>your everyday</span>
    </div>
</section>

<section class="catalog section-wrap" id="products">
    <div class="section-heading">
        <div>
            <p class="eyebrow">FIND YOUR NEXT FAVORITE</p>
            <h2>Shop the collection</h2>
        </div>
        <p class="result-count"><?= count($products) ?> <?= count($products) === 1 ? 'product' : 'products' ?></p>
    </div>

    <form class="filters" action="<?= e(BASE_URL) ?>/" method="get">
        <label class="search-field">
            <span class="sr-only">Search products</span>
            <span class="search-icon" aria-hidden="true">⌕</span>
            <input type="search" name="q" value="<?= e($search) ?>" placeholder="Search products...">
        </label>
        <label class="select-field">
            <span class="sr-only">Category</span>
            <select name="category">
                <option value="">All categories</option>
                <?php foreach ($categories as $category): ?>
                    <option value="<?= e($category['slug']) ?>" <?= $categorySlug === $category['slug'] ? 'selected' : '' ?>>
                        <?= e($category['name']) ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </label>
        <label class="select-field sort-field">
            <span class="sr-only">Sort products</span>
            <select name="sort">
                <?php foreach ($sortOptions as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $sort === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <button class="button button-green filter-button" type="submit">Apply filters</button>
    </form>

    <?php if ($products === []): ?>
        <div class="empty-state">
            <span class="empty-icon" aria-hidden="true">⌕</span>
            <h3>No products found</h3>
            <p>Try a different search or category to find what you’re looking for.</p>
            <a class="text-link" href="<?= e(BASE_URL) ?>/">Clear filters <span aria-hidden="true">→</span></a>
        </div>
    <?php else: ?>
        <div class="product-grid">
            <?php foreach ($products as $product): ?>
                <?php
                $imageUrl = $product['image_url'] ?: 'https://placehold.co/600x400?text=' . rawurlencode($product['name']);
                ?>
                <article class="product-card">
                    <a class="product-image" href="<?= e(BASE_URL) ?>/product.php?slug=<?= rawurlencode($product['slug']) ?>">
                        <img src="<?= e($imageUrl) ?>" alt="<?= e($product['name']) ?>" loading="lazy">
                        <?php if ((int) $product['stock'] < 1): ?>
                            <span class="stock-badge">Out of stock</span>
                        <?php endif; ?>
                    </a>
                    <div class="product-info">
                        <p class="product-category"><?= e($product['category_name']) ?></p>
                        <h3><a href="<?= e(BASE_URL) ?>/product.php?slug=<?= rawurlencode($product['slug']) ?>"><?= e($product['name']) ?></a></h3>
                        <div class="product-card-bottom">
                            <span class="product-price"><?= e(money($product['price'])) ?></span>
                            <a class="view-link" href="<?= e(BASE_URL) ?>/product.php?slug=<?= rawurlencode($product['slug']) ?>" aria-label="View <?= e($product['name']) ?>">View <span aria-hidden="true">↗</span></a>
                        </div>
                    </div>
                </article>
            <?php endforeach; ?>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
