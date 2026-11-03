<?php
declare(strict_types=1);

require __DIR__ . '/app/cart.php';

final class CheckoutException extends RuntimeException
{
}

function checkout_post_value(string $key): string
{
    $value = $_POST[$key] ?? '';
    return is_string($value) ? trim($value) : '';
}

function checkout_money_from_cents(int $cents): string
{
    return intdiv($cents, 100) . '.' . str_pad((string) ($cents % 100), 2, '0', STR_PAD_LEFT);
}

$errors = [];
$customerName = '';
$email = '';
$phone = '';
$address = '';
$checkoutError = null;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();

    $customerName = checkout_post_value('customer_name');
    $email = checkout_post_value('email');
    $phone = checkout_post_value('phone');
    $address = checkout_post_value('address');

    if ($customerName === '' || strlen($customerName) > 120) {
        $errors['customer_name'] = 'Enter your name (up to 120 characters).';
    }

    if (strlen($email) > 160 || filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
        $errors['email'] = 'Enter a valid email address (up to 160 characters).';
    }

    $phoneDigits = preg_replace('/\D/', '', $phone);
    if (strlen($phone) > 20 || preg_match('/^[0-9+().\s-]{7,20}$/D', $phone) !== 1 || strlen((string) $phoneDigits) < 7) {
        $errors['phone'] = 'Enter a valid phone number (up to 20 characters).';
    }

    if ($address === '' || strlen($address) > 2000) {
        $errors['address'] = 'Enter a delivery address (up to 2,000 characters).';
    }

    cart_normalize();
    if ($_SESSION['cart'] === []) {
        $errors['cart'] = 'Your cart is empty. Add a product before checking out.';
    }

    if ($errors === []) {
        $pdo = db();
        $transactionStarted = false;

        try {
            $pdo->beginTransaction();
            $transactionStarted = true;

            $quantities = $_SESSION['cart'];
            ksort($quantities, SORT_NUMERIC);
            $lockProduct = $pdo->prepare(
                'SELECT id, name, price, stock, is_active
                 FROM products
                 WHERE id = ?
                 FOR UPDATE'
            );
            $lockedItems = [];
            $totalCents = 0;
            $maximumTotalCents = 999999999999;

            foreach ($quantities as $productId => $quantity) {
                $lockProduct->execute([(int) $productId]);
                $product = $lockProduct->fetch();

                if (!$product || (int) $product['is_active'] !== 1) {
                    throw new CheckoutException('A product in your cart is no longer available. Please review your cart.');
                }

                $stock = (int) $product['stock'];
                if ($stock < 1) {
                    throw new CheckoutException($product['name'] . ' is now out of stock. Please review your cart.');
                }
                if ($quantity > $stock) {
                    throw new CheckoutException(
                        'Only ' . $stock . ' of ' . $product['name'] . ' remain in stock. Please update your cart and try again.'
                    );
                }

                $priceParts = explode('.', (string) $product['price'], 2);
                $priceCents = ((int) $priceParts[0] * 100)
                    + (int) str_pad($priceParts[1] ?? '0', 2, '0');
                if ($priceCents < 1 || $priceCents > intdiv($maximumTotalCents - $totalCents, $quantity)) {
                    throw new CheckoutException('The order total is too large to process. Please contact the shop.');
                }

                $lineTotalCents = $priceCents * $quantity;
                $totalCents += $lineTotalCents;
                $product['quantity'] = $quantity;
                $product['price_cents'] = $priceCents;
                $lockedItems[] = $product;
            }

            $referenceAlphabet = '23456789ABCDEFGHJKLMNPQRSTUVWXYZ';
            $randomSuffix = '';
            for ($index = 0; $index < 4; $index++) {
                $randomSuffix .= $referenceAlphabet[random_int(0, strlen($referenceAlphabet) - 1)];
            }
            $reference = 'ORD-' . date('Ymd') . '-' . $randomSuffix;

            $insertOrder = $pdo->prepare(
                'INSERT INTO orders (reference, customer_name, email, phone, address, total)
                 VALUES (?, ?, ?, ?, ?, ?)'
            );
            $insertOrder->execute([
                $reference,
                $customerName,
                $email,
                $phone,
                $address,
                checkout_money_from_cents($totalCents),
            ]);
            $orderId = (int) $pdo->lastInsertId();

            $insertItem = $pdo->prepare(
                'INSERT INTO order_items (order_id, product_id, product_name, unit_price, quantity)
                 VALUES (?, ?, ?, ?, ?)'
            );
            $updateStock = $pdo->prepare(
                'UPDATE products SET stock = stock - ? WHERE id = ? AND stock >= ?'
            );

            foreach ($lockedItems as $item) {
                $quantity = (int) $item['quantity'];
                $unitPrice = checkout_money_from_cents((int) $item['price_cents']);
                $insertItem->execute([$orderId, (int) $item['id'], $item['name'], $unitPrice, $quantity]);

                $updateStock->execute([$quantity, (int) $item['id'], $quantity]);
                if ($updateStock->rowCount() !== 1) {
                    throw new CheckoutException('Stock changed while placing your order. Please try again.');
                }
            }

            $pdo->commit();
            $transactionStarted = false;
            $_SESSION['cart'] = [];
            $_SESSION['last_order_reference'] = $reference;
            header('Location: ' . url('order-success.php') . '?ref=' . rawurlencode($reference), true, 303);
            exit;
        } catch (Throwable $exception) {
            if ($transactionStarted && $pdo->inTransaction()) {
                $pdo->rollBack();
            }

            if ($exception instanceof CheckoutException) {
                $checkoutError = $exception->getMessage();
            } else {
                error_log('Checkout failed: ' . $exception->getMessage());
                $checkoutError = 'We could not place your order right now. Your cart is unchanged; please try again.';
            }
        }
    }
}

$items = cart_items();
$total = cart_total();
$cartNotice = $_SESSION['cart_notice'] ?? null;
$cartNoticeType = $_SESSION['cart_notice_type'] ?? 'success';
unset($_SESSION['cart_notice'], $_SESSION['cart_notice_type']);
$pageTitle = 'Checkout';
require __DIR__ . '/partials/header.php';
?>
<section class="checkout-page">
    <div class="checkout-heading">
        <p class="eyebrow">Almost yours</p>
        <h1>Checkout</h1>
        <p>Enter your contact and delivery details to place your order.</p>
    </div>

    <?php if ($checkoutError !== null): ?>
        <p class="notice error" role="alert"><?= e($checkoutError) ?></p>
    <?php endif; ?>
    <?php if ($cartNotice !== null): ?>
        <p class="notice <?= $cartNoticeType === 'error' ? 'error' : '' ?>" role="status"><?= e($cartNotice) ?></p>
    <?php endif; ?>
    <?php if (isset($errors['cart'])): ?>
        <p class="notice error" role="alert"><?= e($errors['cart']) ?></p>
    <?php endif; ?>

    <?php if ($items === []): ?>
        <div class="empty-state">
            <h2>Your cart is empty</h2>
            <p>Add products to your cart before checking out.</p>
            <a class="button" href="<?= e(url()) ?>">Explore products</a>
        </div>
    <?php else: ?>
        <div class="checkout-layout">
            <form class="checkout-form" method="post" action="<?= e(url('checkout.php')) ?>">
                <?= csrf_field() ?>
                <h2>Delivery details</h2>

                <div class="field">
                    <label for="customer_name">Full name</label>
                    <input id="customer_name" name="customer_name" type="text" maxlength="120" autocomplete="name" value="<?= e($customerName) ?>" required>
                    <?php if (isset($errors['customer_name'])): ?><span class="field-error"><?= e($errors['customer_name']) ?></span><?php endif; ?>
                </div>

                <div class="field">
                    <label for="email">Email address</label>
                    <input id="email" name="email" type="email" maxlength="160" autocomplete="email" value="<?= e($email) ?>" required>
                    <?php if (isset($errors['email'])): ?><span class="field-error"><?= e($errors['email']) ?></span><?php endif; ?>
                </div>

                <div class="field">
                    <label for="phone">Phone number</label>
                    <input id="phone" name="phone" type="tel" maxlength="20" autocomplete="tel" value="<?= e($phone) ?>" required>
                    <?php if (isset($errors['phone'])): ?><span class="field-error"><?= e($errors['phone']) ?></span><?php endif; ?>
                </div>

                <div class="field">
                    <label for="address">Delivery address</label>
                    <textarea id="address" name="address" rows="4" maxlength="2000" autocomplete="street-address" required><?= e($address) ?></textarea>
                    <?php if (isset($errors['address'])): ?><span class="field-error"><?= e($errors['address']) ?></span><?php endif; ?>
                </div>

                <button class="button checkout-button" type="submit">Place order</button>
            </form>

            <aside class="checkout-summary" aria-labelledby="checkout-summary-title">
                <h2 id="checkout-summary-title">Order summary</h2>
                <ul class="checkout-items">
                    <?php foreach ($items as $item): ?>
                        <li>
                            <span><?= e($item['name']) ?> <span class="checkout-quantity">&times; <?= (int) $item['quantity'] ?></span></span>
                            <strong><?= e(money($item['line_total'])) ?></strong>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <div class="summary-row"><span>Subtotal</span><strong><?= e(money($total)) ?></strong></div>
                <p class="summary-note">Your order total is calculated from current product prices.</p>
            </aside>
        </div>
    <?php endif; ?>
</section>
<?php require __DIR__ . '/partials/footer.php'; ?>
