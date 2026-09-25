<?php
require_once __DIR__ . '/config/constants.php';
$user = require_login();
$foodId = filter_input(INPUT_GET, 'food_id', FILTER_VALIDATE_INT);
$direct = (bool)$foodId;

if ($direct) {
    $stmt = db()->prepare("SELECT id AS food_id, title, price, image_name, stock_qty, 1 AS quantity FROM tbl_food WHERE id=? AND active='Yes' AND stock_qty>0 LIMIT 1");
    $stmt->execute([$foodId]);
    $row = $stmt->fetch();
    $items = $row ? [$row] : [];
} else {
    $stmt = db()->prepare("SELECT f.id AS food_id, f.title, f.price, f.image_name, f.stock_qty, c.quantity FROM tbl_cart c JOIN tbl_food f ON f.id=c.food_id WHERE c.user_id=? AND f.active='Yes' AND f.stock_qty>0 ORDER BY c.id");
    $stmt->execute([$user['id']]);
    $items = $stmt->fetchAll();
}

if (!$items) {
    flash('warning', 'There is nothing available to checkout.');
    redirect('cart.php');
}

$subtotal = 0.0;
foreach ($items as $item) {
    $subtotal += (float)$item['price'] * (int)$item['quantity'];
}
$delivery = delivery_fee($subtotal);
$initialTotal = $subtotal + $delivery;

$stmt = db()->prepare('SELECT * FROM tbl_user_address WHERE user_id=? ORDER BY is_default DESC,id DESC');
$stmt->execute([$user['id']]);
$addresses = $stmt->fetchAll();
$defaultAddress = get_default_address((int)$user['id']);

if (is_post()) {
    verify_csrf();
    $addressId = filter_input(INPUT_POST, 'address_id', FILTER_VALIDATE_INT);
    $deliveryAddress = trim((string)($_POST['delivery_address'] ?? ''));
    $phone = trim((string)($_POST['phone'] ?? ''));

    if ($addressId) {
        $stmt = db()->prepare('SELECT * FROM tbl_user_address WHERE id=? AND user_id=?');
        $stmt->execute([$addressId, $user['id']]);
        if ($saved = $stmt->fetch()) {
            $deliveryAddress = $saved['address'];
            $phone = $saved['phone_number'];
        }
    }

    $method = (string)($_POST['payment_method'] ?? 'cod');
    $allowedMethods = ['cod'];
    if (mobile_payment_enabled('bkash')) $allowedMethods[] = 'bkash';
    if (mobile_payment_enabled('nagad')) $allowedMethods[] = 'nagad';
    if (sslcommerz_enabled()) $allowedMethods[] = 'sslcommerz';
    if (!in_array($method, $allowedMethods, true)) $method = 'cod';

    $paymentPhone = trim((string)($_POST['payment_phone'] ?? ''));
    $paymentReference = trim((string)($_POST['payment_reference'] ?? ''));
    $couponCode = strtoupper(trim((string)($_POST['coupon_code'] ?? '')));
    $quotedCoupon = strtoupper(trim((string)($_POST['quoted_coupon'] ?? '')));
    $quotedTotal = is_numeric($_POST['quoted_total'] ?? null) ? (float)$_POST['quoted_total'] : -1;

    if (mb_strlen($deliveryAddress) < 5 || !preg_match('/^[+0-9][0-9\s-]{7,19}$/', $phone)) {
        flash('error', 'Please provide a valid delivery address and phone number.');
    } elseif (in_array($method, ['bkash', 'nagad'], true) && (!preg_match('/^[+0-9][0-9\s-]{7,19}$/', $paymentPhone) || mb_strlen($paymentReference) < 5)) {
        flash('error', 'For bKash/Nagad, enter the sender phone and transaction ID.');
    } else {
        try {
            db()->beginTransaction();

            if ($direct) {
                $stmt = db()->prepare("SELECT id AS food_id, title, price, image_name, stock_qty, 1 AS quantity FROM tbl_food WHERE id=? AND active='Yes' FOR UPDATE");
                $stmt->execute([$foodId]);
                $freshRow = $stmt->fetch();
                $freshItems = $freshRow ? [$freshRow] : [];
            } else {
                $stmt = db()->prepare("SELECT f.id AS food_id, f.title, f.price, f.image_name, f.stock_qty, c.quantity FROM tbl_cart c JOIN tbl_food f ON f.id=c.food_id WHERE c.user_id=? AND f.active='Yes' FOR UPDATE");
                $stmt->execute([$user['id']]);
                $freshItems = $stmt->fetchAll();
            }

            if (!$freshItems) throw new RuntimeException('No orderable items found.');

            $freshSubtotal = 0.0;
            foreach ($freshItems as $item) {
                if ((int)$item['quantity'] > (int)$item['stock_qty']) {
                    throw new RuntimeException($item['title'] . ' does not have enough stock.');
                }
                $freshSubtotal += (float)$item['price'] * (int)$item['quantity'];
            }

            $freshDelivery = delivery_fee($freshSubtotal);
            $coupon = null;
            $discount = 0.0;
            if ($couponCode !== '') {
                [$coupon, $couponError] = validate_coupon($couponCode, $freshSubtotal, (int)$user['id']);
                if (!$coupon) throw new RuntimeException($couponError);
                $discount = coupon_discount($coupon, $freshSubtotal);
                $couponCode = (string)$coupon['code'];
            }
            $total = max(0, $freshSubtotal - $discount + $freshDelivery);

            if (in_array($method, ['bkash', 'nagad'], true)) {
                if ($quotedCoupon !== $couponCode || $quotedTotal < 0 || abs($quotedTotal - $total) > 0.01) {
                    throw new RuntimeException('Your payable amount changed. Apply the coupon/recalculate total before submitting mobile payment.');
                }
            }

            $paymentStatus = in_array($method, ['bkash', 'nagad'], true) ? 'Submitted' : 'Pending';

            $stmt = db()->prepare('INSERT INTO tbl_order (user_id,subtotal,discount,coupon_code,delivery_fee,total,payment_method,payment_status,payment_phone,payment_reference,delivery_address,phone_number,order_date,status) VALUES (?,?,?,?,?,?,?,?,?,?,?,?,NOW(),?)');
            $stmt->execute([
                $user['id'], $freshSubtotal, $discount, $coupon['code'] ?? null,
                $freshDelivery, $total, $method, $paymentStatus,
                in_array($method, ['bkash', 'nagad'], true) ? $paymentPhone : null,
                in_array($method, ['bkash', 'nagad'], true) ? $paymentReference : null,
                $deliveryAddress, $phone, 'Ordered'
            ]);
            $orderId = (int)db()->lastInsertId();

            $itemStmt = db()->prepare('INSERT INTO tbl_order_item (order_id,food_id,food_name,price,quantity,subtotal) VALUES (?,?,?,?,?,?)');
            $stockStmt = db()->prepare('UPDATE tbl_food SET stock_qty=stock_qty-? WHERE id=? AND stock_qty>=?');
            foreach ($freshItems as $item) {
                $line = (float)$item['price'] * (int)$item['quantity'];
                $itemStmt->execute([$orderId, $item['food_id'], $item['title'], $item['price'], $item['quantity'], $line]);
                $stockStmt->execute([$item['quantity'], $item['food_id'], $item['quantity']]);
                if ($stockStmt->rowCount() !== 1) throw new RuntimeException('Stock changed while ordering. Please try again.');
            }

            if ($coupon) {
                db()->prepare('INSERT INTO tbl_coupon_usage (coupon_id,user_id,order_id,used_at) VALUES (?,?,?,NOW())')->execute([$coupon['id'], $user['id'], $orderId]);
                db()->prepare('UPDATE tbl_coupon SET used_count=used_count+1 WHERE id=?')->execute([$coupon['id']]);
            }
            if (!$direct) {
                db()->prepare('DELETE FROM tbl_cart WHERE user_id=?')->execute([$user['id']]);
            }

            if ($method === 'sslcommerz') {
                $gateway = sslcommerz_create_session([
                    'id' => $orderId,
                    'total' => $total,
                    'delivery_address' => $deliveryAddress,
                    'phone_number' => $phone,
                ], $user);
                db()->prepare('UPDATE tbl_order SET payment_reference=?,gateway_session=? WHERE id=?')->execute([$gateway['tran_id'], $gateway['sessionkey'], $orderId]);
                db()->commit();
                header('Location: ' . $gateway['gateway_url']);
                exit;
            }

            db()->commit();
            if (in_array($method, ['bkash', 'nagad'], true)) {
                flash('success', 'Order #' . (1000 + $orderId) . ' placed. Your payment is waiting for admin verification.');
            } else {
                flash('success', 'Order #' . (1000 + $orderId) . ' placed successfully.');
            }
            redirect('order-details.php?id=' . $orderId);
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            flash('error', APP_ENV === 'local' ? $e->getMessage() : 'Could not place your order. Please try again.');
        }
    }
}

$postedCoupon = strtoupper(trim((string)($_POST['coupon_code'] ?? '')));
$displayDiscount = 0.0;
$displayCoupon = '';
if ($postedCoupon !== '') {
    [$previewCoupon] = validate_coupon($postedCoupon, $subtotal, (int)$user['id']);
    if ($previewCoupon) {
        $displayCoupon = (string)$previewCoupon['code'];
        $displayDiscount = coupon_discount($previewCoupon, $subtotal);
    }
}
$displayTotal = max(0, $subtotal - $displayDiscount + $delivery);

$pageTitle = 'Checkout — ' . APP_NAME;
include __DIR__ . '/partials-font/menu.php';
?>
<section class="page-hero checkout-hero"><div class="container"><div class="page-hero-row"><div><span class="eyebrow">Secure checkout</span><h1>Complete your order</h1><p>Delivery, coupon and payment — all totals are rechecked on the server before your order is saved.</p></div><div class="checkout-trust"><span>🔒 Secure forms</span><span>✓ Server-verified total</span><span>↻ Order tracking</span></div></div></div></section>
<section class="section checkout-section"><div class="container checkout-grid checkout-grid-wide">
<div class="checkout-main">
<form method="post" id="checkout-form" data-checkout-form data-quote-url="<?= e(url('checkout-quote.php')) ?>">
<?= csrf_field() ?>
<input type="hidden" name="food_id" value="<?= $direct ? (int)$foodId : '' ?>">
<input type="hidden" name="quoted_total" value="<?= e(number_format($displayTotal, 2, '.', '')) ?>" data-quoted-total>
<input type="hidden" name="quoted_coupon" value="<?= e($displayCoupon) ?>" data-quoted-coupon>

<div class="panel checkout-card">
<div class="checkout-card-head"><div class="step-number">1</div><div><h2>Delivery details</h2><p class="muted">Choose a saved address or enter a different delivery location.</p></div></div>
<?php if ($addresses): ?>
<div class="field"><label>Saved address</label><select name="address_id"><option value="">Enter manually</option><?php foreach ($addresses as $address): ?><option value="<?= (int)$address['id'] ?>" <?= $address['is_default'] ? 'selected' : '' ?>><?= e($address['label'] . ' — ' . $address['address']) ?></option><?php endforeach; ?></select></div><br>
<?php endif; ?>
<div class="field"><label>Delivery address</label><textarea name="delivery_address" rows="3" required><?= e($_POST['delivery_address'] ?? ($defaultAddress['address'] ?? $user['address'])) ?></textarea></div><br>
<div class="field"><label>Phone</label><input class="input" name="phone" value="<?= e($_POST['phone'] ?? ($defaultAddress['phone_number'] ?? $user['phone_number'])) ?>" required></div>
</div>

<div class="panel checkout-card">
<div class="checkout-card-head"><div class="step-number">2</div><div><h2>Coupon</h2><p class="muted">Apply a coupon to preview the exact payable amount before payment.</p></div></div>
<div class="coupon-entry"><input class="input" name="coupon_code" data-coupon-input value="<?= e($_POST['coupon_code'] ?? '') ?>" placeholder="WELCOME10"><button class="btn btn-light" type="button" data-coupon-apply>Apply coupon</button></div>
<div class="quote-message <?= $displayCoupon ? 'success' : '' ?>" data-quote-message><?= $displayCoupon ? e($displayCoupon . ' applied.') : 'You can continue without a coupon.' ?></div>
</div>

<div class="panel checkout-card">
<div class="checkout-card-head"><div class="step-number">3</div><div><h2>Payment method</h2><p class="muted">Choose how you want to pay. Mobile payments are verified by the administrator before being marked Paid.</p></div></div>
<div class="payment-options" data-payment-options>
<label class="payment-option selected" data-payment-option>
<input type="radio" name="payment_method" value="cod" checked data-payment-method data-number="">
<span class="payment-option-icon">💵</span><span class="payment-option-body"><strong>Cash on Delivery</strong><small>Pay when your order arrives</small></span><span class="payment-check">✓</span>
</label>
<?php if (mobile_payment_enabled('bkash')): ?>
<label class="payment-option" data-payment-option>
<input type="radio" name="payment_method" value="bkash" data-payment-method data-number="<?= e(payment_number('bkash')) ?>">
<span class="payment-option-icon payment-bkash">b</span><span class="payment-option-body"><strong>bKash</strong><small>Manual merchant payment + transaction verification</small></span><span class="payment-check">✓</span>
</label>
<?php endif; ?>
<?php if (mobile_payment_enabled('nagad')): ?>
<label class="payment-option" data-payment-option>
<input type="radio" name="payment_method" value="nagad" data-payment-method data-number="<?= e(payment_number('nagad')) ?>">
<span class="payment-option-icon payment-nagad">N</span><span class="payment-option-body"><strong>Nagad</strong><small>Manual merchant payment + transaction verification</small></span><span class="payment-check">✓</span>
</label>
<?php endif; ?>
<?php if (sslcommerz_enabled()): ?>
<label class="payment-option" data-payment-option>
<input type="radio" name="payment_method" value="sslcommerz" data-payment-method data-number="">
<span class="payment-option-icon">🔐</span><span class="payment-option-body"><strong>Online Gateway</strong><small>SSLCOMMERZ — card, bank and supported mobile options</small></span><span class="payment-check">✓</span>
</label>
<?php endif; ?>
</div>

<div class="payment-box mobile-payment-box" data-mobile-payment hidden>
<div class="payment-instruction-head"><div><span class="payment-kicker">Manual mobile payment</span><h3>Pay <span data-pay-total><?= e(money($displayTotal)) ?></span></h3></div><span class="status status-warning">Verification required</span></div>
<div class="merchant-number-row"><div><span class="muted">Merchant number</span><strong data-pay-number></strong></div><button class="btn btn-light btn-sm" type="button" data-copy-payment>Copy number</button></div>
<ol class="payment-steps"><li>Send the exact amount shown above to the merchant number.</li><li>Enter the sender phone and transaction ID below.</li><li>Submit the order. Admin will verify the transaction before marking it Paid.</li></ol>
<div class="form-grid"><div class="field"><label>Sender phone</label><input class="input" name="payment_phone" inputmode="tel" placeholder="01XXXXXXXXX"></div><div class="field"><label>Transaction ID</label><input class="input" name="payment_reference" placeholder="e.g. 9AB12CD34E"></div></div>
</div>

<?php if (!mobile_payment_enabled('bkash') && !mobile_payment_enabled('nagad') && !sslcommerz_enabled()): ?>
<div class="payment-config-note"><strong>Currently available:</strong> Cash on Delivery. Online/mobile options automatically appear after merchant credentials are configured in <code>.env</code>.</div>
<?php endif; ?>
</div>

<button class="btn btn-primary checkout-submit" type="submit" data-checkout-submit>Place Cash on Delivery order</button>
<p class="checkout-footnote">By placing the order, you confirm the delivery information and the final server-verified total.</p>
</form>
</div>

<aside class="checkout-side"><div class="panel checkout-summary" data-order-summary>
<div class="section-head compact"><div><h2>Order summary</h2><p><?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?></p></div></div>
<div class="checkout-items"><?php foreach ($items as $item): ?><div class="checkout-item"><img class="thumb" src="<?= e(food_image($item['image_name'])) ?>" alt=""><div class="checkout-item-info"><strong><?= e($item['title']) ?></strong><span class="muted"><?= (int)$item['quantity'] ?> × <?= e(money($item['price'])) ?></span></div><strong><?= e(money((float)$item['price'] * (int)$item['quantity'])) ?></strong></div><?php endforeach; ?></div>
<div class="summary-row"><span>Subtotal</span><strong data-summary-subtotal><?= e(money($subtotal)) ?></strong></div>
<div class="summary-row"><span>Delivery</span><strong data-summary-delivery><?= $delivery ? e(money($delivery)) : 'FREE' ?></strong></div>
<div class="summary-row discount-row" <?= $displayDiscount > 0 ? '' : 'hidden' ?> data-summary-discount-row><span>Discount</span><strong data-summary-discount><?= $displayDiscount > 0 ? '-' . e(money($displayDiscount)) : e(money(0)) ?></strong></div>
<div class="summary-row total"><span>Total</span><span data-summary-total><?= e(money($displayTotal)) ?></span></div>
<div class="summary-security"><span>🔒</span><div><strong>Total is protected</strong><p>Price, stock, coupon and delivery fee are validated again on the server.</p></div></div>
</div></aside>
</div></section>
<?php include __DIR__ . '/partials-font/footer.php'; ?>
