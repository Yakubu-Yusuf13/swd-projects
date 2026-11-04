<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(404);
    exit('Not found');
}

require __DIR__ . '/app/bootstrap.php';

$products = [
    ['phones', 'Samsung Galaxy A55', 'samsung-galaxy-a55', 'A vibrant 6.6-inch display, dependable all-day battery and a versatile camera system in a sleek, durable design.', 579900, 12],
    ['phones', 'Apple iPhone 15', 'apple-iphone-15', 'Capture crisp photos, enjoy a bright Super Retina display and stay connected with the powerful A16 Bionic chip.', 1199900, 8],
    ['phones', 'Xiaomi Redmi Note 13 Pro', 'xiaomi-redmi-note-13-pro', 'A sharp AMOLED display and a high-resolution camera make this a capable everyday phone at a great value.', 469900, 16],
    ['phones', 'TECNO Camon 30', 'tecno-camon-30', 'A stylish phone with a vivid screen, portrait-friendly camera and plenty of power for your daily apps.', 349900, 14],
    ['phones', 'Google Pixel 8a', 'google-pixel-8a', 'Enjoy Google AI features, a brilliant camera and a smooth, clean Android experience in a pocket-friendly phone.', 799900, 0],
    ['laptops', 'Lenovo IdeaPad Slim 3', 'lenovo-ideapad-slim-3', 'A lightweight everyday laptop with a comfortable keyboard, clear display and the performance for study or work.', 729900, 9],
    ['laptops', 'HP Pavilion 15', 'hp-pavilion-15', 'A versatile 15-inch laptop with a spacious screen and reliable performance for home, office and entertainment.', 899900, 6],
    ['laptops', 'Dell Inspiron 14', 'dell-inspiron-14', 'Take your work anywhere with a compact 14-inch design, responsive performance and a crisp Full HD display.', 949900, 7],
    ['laptops', 'Apple MacBook Air M2', 'apple-macbook-air-m2', 'A remarkably thin and quiet laptop with Apple silicon speed, all-day battery life and a beautiful Retina display.', 1899900, 4],
    ['laptops', 'ASUS VivoBook 15', 'asus-vivobook-15', 'A practical, colorful 15-inch laptop that makes everyday productivity, browsing and streaming feel effortless.', 769900, 10],
    ['accessories', 'Anker 20,000mAh Power Bank', 'anker-20-000mah-power-bank', 'Keep your devices charged on the go with generous capacity, fast charging and a travel-friendly design.', 45900, 25],
    ['accessories', 'Logitech M185 Wireless Mouse', 'logitech-m185-wireless-mouse', 'A comfortable, dependable wireless mouse with simple plug-and-play setup and a long-lasting battery.', 14900, 32],
    ['accessories', 'JBL Tune 520BT Headphones', 'jbl-tune-520bt-headphones', 'Enjoy bold JBL sound, wireless freedom and a lightweight foldable design for music wherever you go.', 54900, 18],
    ['accessories', '65W USB-C Fast Charger', '65w-usb-c-fast-charger', 'A compact USB-C wall charger that delivers fast, efficient power for compatible phones, tablets and laptops.', 24900, 30],
    ['accessories', 'UGREEN 6-in-1 USB-C Hub', 'ugreen-6-in-1-usb-c-hub', 'Expand your laptop connectivity with a handy collection of USB, HDMI and card-reader ports in one hub.', 38900, 0],
];

$categoryStatement = $pdo->prepare('SELECT id FROM categories WHERE slug = :slug LIMIT 1');
$insertProduct = $pdo->prepare(
    'INSERT INTO products (category_id, name, slug, description, price, stock, image_url, is_active)
     VALUES (:category_id, :name, :slug, :description, :price, :stock, :image_url, 1)
     ON DUPLICATE KEY UPDATE
        category_id = VALUES(category_id),
        name = VALUES(name),
        description = VALUES(description),
        price = VALUES(price),
        stock = VALUES(stock),
        image_url = VALUES(image_url),
        is_active = 1'
);

try {
    $pdo->beginTransaction();
    $categoryIds = [];

    foreach ($products as [$categorySlug, $name, $slug, $description, $price, $stock]) {
        if (!isset($categoryIds[$categorySlug])) {
            $categoryStatement->execute(['slug' => $categorySlug]);
            $categoryId = $categoryStatement->fetchColumn();
            if ($categoryId === false) {
                throw new RuntimeException('Required category not found: ' . $categorySlug);
            }
            $categoryIds[$categorySlug] = (int) $categoryId;
        }

        $insertProduct->execute([
            'category_id' => $categoryIds[$categorySlug],
            'name' => $name,
            'slug' => $slug,
            'description' => $description,
            'price' => number_format($price, 2, '.', ''),
            'stock' => $stock,
            'image_url' => 'https://placehold.co/600x400?text=' . rawurlencode($name),
        ]);
    }

    $pdo->commit();
    fwrite(STDOUT, 'Seeded ' . count($products) . " products.\n");
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    fwrite(STDERR, 'Seeding failed: ' . $exception->getMessage() . "\n");
    exit(1);
}
