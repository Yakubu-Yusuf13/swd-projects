<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found');
}

require __DIR__ . '/app/bootstrap.php';

$products = [
    ['phones', 'Samsung Galaxy A55', 'A bright, smooth display and dependable all-day performance make this 5G phone a practical everyday upgrade.', 529900, 12],
    ['phones', 'Apple iPhone 15', 'A refined iPhone with a vibrant display, capable camera system and convenient USB-C charging.', 1199900, 8],
    ['phones', 'Xiaomi Redmi Note 13 Pro', 'Enjoy a crisp high-resolution screen, fast charging and a versatile camera in a great-value package.', 429900, 16],
    ['phones', 'TECNO Camon 30', 'Capture everyday moments with a stylish phone designed for clear portraits, smooth browsing and reliable battery life.', 319900, 14],
    ['phones', 'Google Pixel 8a', 'A compact, helpful smartphone with a clean Android experience and smart camera features.', 699900, 0],
    ['laptops', 'Lenovo IdeaPad Slim 3', 'A lightweight everyday laptop suited to study, home office tasks and streaming on the go.', 749900, 9],
    ['laptops', 'HP Pavilion 15', 'A versatile 15-inch laptop with a comfortable keyboard for work, learning and entertainment.', 989900, 6],
    ['laptops', 'Dell Inspiron 14', 'A portable and dependable laptop with a compact display size for getting things done anywhere.', 879900, 7],
    ['laptops', 'Apple MacBook Air M2', 'A slim, quiet laptop with a vivid display and efficient performance for everyday creative work.', 1899900, 4],
    ['laptops', 'ASUS VivoBook 15', 'A practical, full-size notebook for browsing, productivity and keeping up with your daily tasks.', 829900, 10],
    ['accessories', 'Anker 20,000mAh Power Bank', 'Keep your devices topped up while travelling with a high-capacity portable charger.', 54900, 25],
    ['accessories', 'Logitech M185 Wireless Mouse', 'A compact wireless mouse with a simple plug-and-play receiver for home or office.', 18900, 32],
    ['accessories', 'JBL Tune 520BT Headphones', 'Enjoy wireless listening and a comfortable over-ear fit for commutes, calls and downtime.', 64900, 18],
    ['accessories', '65W USB-C Fast Charger', 'A compact USB-C wall charger for powering compatible phones, tablets and laptops.', 27900, 30],
    ['accessories', 'UGREEN 6-in-1 USB-C Hub', 'Add useful ports to your USB-C laptop with a handy hub for displays, storage and accessories.', 45900, 0],
];

$pdo = db();
$pdo->beginTransaction();

try {
    $categoryInsert = $pdo->prepare('INSERT IGNORE INTO categories (name, slug) VALUES (:name, :slug)');
    foreach (['phones' => 'Phones', 'laptops' => 'Laptops', 'accessories' => 'Accessories'] as $slug => $name) {
        $categoryInsert->execute(['name' => $name, 'slug' => $slug]);
    }

    $categoryIds = $pdo->query('SELECT slug, id FROM categories')->fetchAll(PDO::FETCH_KEY_PAIR);
    $findProduct = $pdo->prepare('SELECT id FROM products WHERE slug = :slug');
    $insertProduct = $pdo->prepare(
        'INSERT INTO products (category_id, name, slug, description, price, stock, image_url, is_active)
         VALUES (:category_id, :name, :slug, :description, :price, :stock, :image_url, 1)'
    );
    $updateProduct = $pdo->prepare(
        'UPDATE products
         SET category_id = :category_id, name = :name, description = :description, price = :price,
             stock = :stock, image_url = :image_url, is_active = 1
         WHERE id = :id'
    );

    foreach ($products as [$categorySlug, $name, $description, $price, $stock]) {
        $categoryId = $categoryIds[$categorySlug] ?? null;
        if ($categoryId === null) {
            throw new RuntimeException("Required category is missing: {$categorySlug}");
        }

        $slug = strtolower(trim((string) preg_replace('/[^a-z0-9]+/i', '-', $name), '-'));
        $values = [
            'category_id' => (int) $categoryId,
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'price' => number_format($price / 100, 2, '.', ''),
            'stock' => $stock,
            'image_url' => 'https://placehold.co/600x400?text=' . rawurlencode($name),
        ];

        $findProduct->execute(['slug' => $slug]);
        $existingId = $findProduct->fetchColumn();

        if ($existingId === false) {
            $insertProduct->execute($values);
        } else {
            $updateValues = $values;
            unset($updateValues['slug']);
            $updateValues['id'] = (int) $existingId;
            $updateProduct->execute($updateValues);
        }
    }

    $pdo->commit();
    fwrite(STDOUT, 'Seed complete: ' . count($products) . " products are ready.\n");
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }

    throw $exception;
}
