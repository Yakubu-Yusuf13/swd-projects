<?php
declare(strict_types=1);

require_once __DIR__ . '/bootstrap.php';

function cart_add(int $id, int $qty): void
{
    global $pdo;

    if ($id < 1 || $qty < 1) {
        set_flash('error', 'Choose a valid product and quantity.');
        return;
    }

    $statement = $pdo->prepare('SELECT id, name, stock FROM products WHERE id = :id AND is_active = 1 LIMIT 1');
    $statement->execute(['id' => $id]);
    $product = $statement->fetch();
    if (!$product) {
        set_flash('error', 'This product is no longer available.');
        return;
    }

    $stock = (int) $product['stock'];
    $cart = $_SESSION['cart'] ?? [];
    if (!is_array($cart)) {
        $cart = [];
    }
    $currentQty = isset($cart[$id]) && is_numeric($cart[$id]) ? max(0, (int) $cart[$id]) : 0;

    if ($stock < 1) {
        unset($cart[$id]);
        $_SESSION['cart'] = $cart;
        set_flash('error', $product['name'] . ' is currently out of stock.');
        return;
    }

    $newQty = min($currentQty + $qty, $stock);
    $cart[$id] = $newQty;
    $_SESSION['cart'] = $cart;

    if ($newQty < $currentQty + $qty) {
        set_flash('success', 'Quantity adjusted to ' . $stock . ' available. ' . $product['name'] . ' is in your cart.');
        return;
    }

    set_flash('success', $product['name'] . ' added to your cart.');
}

function cart_update(int $id, int $qty): void
{
    global $pdo;

    if ($id < 1 || $qty < 1) {
        set_flash('error', 'Choose a valid quantity.');
        return;
    }

    $statement = $pdo->prepare('SELECT id, name, stock FROM products WHERE id = :id AND is_active = 1 LIMIT 1');
    $statement->execute(['id' => $id]);
    $product = $statement->fetch();
    if (!$product) {
        cart_remove($id);
        set_flash('error', 'This product is no longer available and was removed from your cart.');
        return;
    }

    $stock = (int) $product['stock'];
    if ($stock < 1) {
        cart_remove($id);
        set_flash('error', $product['name'] . ' is out of stock and was removed from your cart.');
        return;
    }

    $newQty = min($qty, $stock);
    $cart = $_SESSION['cart'] ?? [];
    if (!is_array($cart)) {
        $cart = [];
    }
    $cart[$id] = $newQty;
    $_SESSION['cart'] = $cart;

    if ($newQty < $qty) {
        set_flash('success', 'Quantity for ' . $product['name'] . ' was adjusted to the ' . $stock . ' available.');
        return;
    }

    set_flash('success', 'Cart quantity updated.');
}

function cart_remove(int $id): void
{
    $cart = $_SESSION['cart'] ?? [];
    if (!is_array($cart)) {
        $_SESSION['cart'] = [];
        return;
    }

    unset($cart[$id], $cart[(string) $id]);
    $_SESSION['cart'] = $cart;
}

function cart_clear(): void
{
    $_SESSION['cart'] = [];
}

function cart_items(): array
{
    global $pdo;

    $cart = $_SESSION['cart'] ?? [];
    if (!is_array($cart)) {
        $_SESSION['cart'] = [];
        return [];
    }

    $normalizedCart = [];
    foreach ($cart as $productId => $quantity) {
        $id = filter_var($productId, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $qty = filter_var($quantity, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($id !== false && $qty !== false) {
            $normalizedCart[$id] = $qty;
        }
    }

    if ($normalizedCart === []) {
        $_SESSION['cart'] = [];
        return [];
    }

    $placeholders = implode(',', array_fill(0, count($normalizedCart), '?'));
    $statement = $pdo->prepare(
        'SELECT id, name, slug, price, stock, image_url, is_active
         FROM products
         WHERE id IN (' . $placeholders . ')'
    );
    $statement->execute(array_keys($normalizedCart));
    $productsById = [];
    foreach ($statement->fetchAll() as $product) {
        $productsById[(int) $product['id']] = $product;
    }

    $items = [];
    $notices = [];
    foreach ($normalizedCart as $id => $quantity) {
        $product = $productsById[$id] ?? null;
        if (!$product || (int) $product['is_active'] !== 1) {
            unset($normalizedCart[$id]);
            $notices[] = 'An unavailable product was removed from your cart.';
            continue;
        }

        $stock = (int) $product['stock'];
        if ($stock < 1) {
            unset($normalizedCart[$id]);
            $notices[] = $product['name'] . ' is out of stock and was removed from your cart.';
            continue;
        }

        if ($quantity > $stock) {
            $quantity = $stock;
            $normalizedCart[$id] = $quantity;
            $notices[] = $product['name'] . ' quantity was adjusted to the ' . $stock . ' available.';
        }

        $unitPrice = (string) $product['price'];
        $items[] = [
            'product_id' => $id,
            'name' => $product['name'],
            'slug' => $product['slug'],
            'image_url' => $product['image_url'],
            'price' => $unitPrice,
            'quantity' => $quantity,
            'stock' => $stock,
            'stock_check' => $quantity <= $stock,
            'line_total' => number_format((float) $unitPrice * $quantity, 2, '.', ''),
        ];
    }

    $_SESSION['cart'] = $normalizedCart;
    if ($notices !== []) {
        set_flash('success', implode(' ', array_unique($notices)));
    }

    return $items;
}

function cart_total(): string
{
    $total = 0.0;
    foreach (cart_items() as $item) {
        $total += (float) $item['line_total'];
    }

    return number_format($total, 2, '.', '');
}
