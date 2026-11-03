<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function cart_notice(string $message, string $type = 'success'): void
{
    $_SESSION['cart_notice'] = $message;
    $_SESSION['cart_notice_type'] = $type;
}

function cart_normalize(): void
{
    $cart = $_SESSION['cart'] ?? [];
    if (!is_array($cart)) {
        $_SESSION['cart'] = [];
        return;
    }

    $normalized = [];
    foreach ($cart as $id => $quantity) {
        $productId = filter_var($id, FILTER_VALIDATE_INT);
        $itemQuantity = filter_var($quantity, FILTER_VALIDATE_INT);
        if ($productId !== false && $productId > 0 && $itemQuantity !== false && $itemQuantity > 0) {
            $normalized[$productId] = $itemQuantity;
        }
    }

    $_SESSION['cart'] = $normalized;
}

function cart_count(): int
{
    cart_normalize();
    return array_sum($_SESSION['cart']);
}

function cart_add(int $id, int $qty): void
{
    cart_normalize();
    if ($id < 1 || $qty < 1) {
        cart_notice('Choose a valid product and quantity.', 'error');
        return;
    }

    $statement = db()->prepare('SELECT name, stock FROM products WHERE id = :id AND is_active = 1 LIMIT 1');
    $statement->execute(['id' => $id]);
    $product = $statement->fetch();
    if (!$product) {
        cart_notice('This product is no longer available.', 'error');
        return;
    }

    $stock = (int) $product['stock'];
    if ($stock < 1) {
        cart_notice('This product is currently out of stock.', 'error');
        return;
    }

    $currentQuantity = (int) ($_SESSION['cart'][$id] ?? 0);
    $newQuantity = min($currentQuantity + $qty, $stock);
    $_SESSION['cart'][$id] = $newQuantity;

    if ($newQuantity < $currentQuantity + $qty) {
        cart_notice("Quantity adjusted to {$newQuantity}; only {$stock} are in stock.", 'error');
        return;
    }

    cart_notice($product['name'] . ' added to your cart.');
}

function cart_update(int $id, int $qty): void
{
    cart_normalize();
    if ($id < 1 || !isset($_SESSION['cart'][$id])) {
        cart_notice('That cart item could not be found.', 'error');
        return;
    }

    if ($qty < 1) {
        cart_notice('Choose a quantity of at least one, or use Remove.', 'error');
        return;
    }

    $statement = db()->prepare('SELECT name, stock FROM products WHERE id = :id AND is_active = 1 LIMIT 1');
    $statement->execute(['id' => $id]);
    $product = $statement->fetch();
    if (!$product || (int) $product['stock'] < 1) {
        unset($_SESSION['cart'][$id]);
        cart_notice('An unavailable or out-of-stock item was removed from your cart.', 'error');
        return;
    }

    $stock = (int) $product['stock'];
    $_SESSION['cart'][$id] = min($qty, $stock);
    if ($qty > $stock) {
        cart_notice("Quantity adjusted to {$stock}; only {$stock} are in stock.", 'error');
        return;
    }

    cart_notice('Cart quantity updated.');
}

function cart_remove(int $id): void
{
    cart_normalize();
    if ($id < 1 || !isset($_SESSION['cart'][$id])) {
        cart_notice('That cart item could not be found.', 'error');
        return;
    }

    unset($_SESSION['cart'][$id]);
    cart_notice('Item removed from your cart.');
}

function cart_clear(): void
{
    $_SESSION['cart'] = [];
    cart_notice('Your cart has been cleared.');
}

function cart_items(): array
{
    cart_normalize();
    if ($_SESSION['cart'] === []) {
        return [];
    }

    $ids = array_keys($_SESSION['cart']);
    $placeholders = implode(', ', array_fill(0, count($ids), '?'));
    $statement = db()->prepare(
        "SELECT p.id, p.name, p.slug, p.price, p.stock, p.image_url
         FROM products p
         WHERE p.is_active = 1 AND p.id IN ({$placeholders})
         ORDER BY p.name"
    );
    $statement->execute($ids);

    $products = [];
    foreach ($statement->fetchAll() as $product) {
        $products[(int) $product['id']] = $product;
    }

    $lines = [];
    $notices = [];
    foreach ($_SESSION['cart'] as $id => $quantity) {
        if (!isset($products[$id])) {
            unset($_SESSION['cart'][$id]);
            $notices[] = 'An unavailable item was removed from your cart.';
            continue;
        }

        $product = $products[$id];
        $stock = (int) $product['stock'];
        if ($stock < 1) {
            unset($_SESSION['cart'][$id]);
            $notices[] = $product['name'] . ' is out of stock and was removed from your cart.';
            continue;
        }

        $stockAdjusted = $quantity > $stock;
        if ($stockAdjusted) {
            $quantity = $stock;
            $_SESSION['cart'][$id] = $stock;
            $notices[] = $product['name'] . " quantity was adjusted to {$stock} to match current stock.";
        }

        $product['quantity'] = $quantity;
        $product['stock_ok'] = !$stockAdjusted;
        $product['line_total'] = number_format((float) $product['price'] * $quantity, 2, '.', '');
        $lines[] = $product;
    }

    if ($notices !== []) {
        cart_notice(implode(' ', array_unique($notices)), 'error');
    }

    return $lines;
}

function cart_total(): string
{
    $total = 0.0;
    foreach (cart_items() as $item) {
        $total += (float) $item['line_total'];
    }

    return number_format($total, 2, '.', '');
}
