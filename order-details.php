<?php
require_once __DIR__ . '/config/constants.php';
$u = require_login();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) redirect('my_order.php');
$stmt = db()->prepare('SELECT * FROM tbl_order WHERE id=? AND user_id=?');
$stmt->execute([$id, $u['id']]);
$o = $stmt->fetch();
if (!$o) {
    flash('error', 'Order not found.');
    redirect('my_order.php');
}
$stmt = db()->prepare('SELECT * FROM tbl_order_item WHERE order_id=? ORDER BY id');
$stmt->execute([$id]);
$items = $stmt->fetchAll();
$pageTitle = 'Order #' . (1000 + $id) . ' — ' . APP_NAME;
include __DIR__ . '/partials-font/menu.php';
?>
<section class="page-hero">
    <div class="container">
        <div class="page-hero-row">
            <div><span class="eyebrow">Order tracking</span>
                <h1>Order #<?= 1000 + $id ?></h1>
                <p><?= e(date('M d, Y • h:i A', strtotime($o['order_date']))) ?></p>
            </div><span class="status <?= e(status_class($o['status'])) ?> order-status-large"><?= e($o['status']) ?></span>
        </div>
    </div>
</section>
<section class="section">
    <div class="container checkout-grid checkout-grid-wide">
        <div class="panel">
            <div class="order-head">
                <div>
                    <h2>Order items</h2>
                    <p class="muted"><?= count($items) ?> item<?= count($items) === 1 ? '' : 's' ?> in this order</p>
                </div>
            </div>
            <?php foreach ($items as $i): ?><div class="summary-row order-item-row"><span><?= e($i['food_name']) ?> × <?= (int)$i['quantity'] ?></span><strong><?= e(money($i['subtotal'])) ?></strong></div><?php endforeach; ?>
            <hr class="sep">
            <div class="summary-row"><span>Subtotal</span><strong><?= e(money($o['subtotal'])) ?></strong></div><?php if ((float)$o['discount'] > 0): ?><div class="summary-row"><span>Discount <?= $o['coupon_code'] ? '(' . e($o['coupon_code']) . ')' : '' ?></span><strong>-<?= e(money($o['discount'])) ?></strong></div><?php endif; ?><div class="summary-row"><span>Delivery</span><strong><?= (float)$o['delivery_fee'] ? e(money($o['delivery_fee'])) : 'FREE' ?></strong></div>
            <div class="summary-row total"><span>Total</span><span><?= e(money($o['total'])) ?></span></div>
        </div>
        <div class="order-detail-side">
            <div class="panel">
                <h2>Delivery</h2>
                <p><?= e($o['delivery_address']) ?></p>
                <p class="muted"><?= e($o['phone_number']) ?></p>
            </div>
            <div class="panel payment-status-card">
                <div class="payment-status-head">
                    <div><span class="section-kicker">Payment</span>
                        <h2><?= e(payment_label($o['payment_method'])) ?></h2>
                    </div><span class="status <?= e(status_class($o['payment_status'])) ?>"><?= e($o['payment_status']) ?></span>
                </div>
                <div class="payment-total-line"><span>Order total</span><strong><?= e(money($o['total'])) ?></strong></div>
                <?php if ($o['payment_reference']): ?><div class="payment-reference"><small><?= $o['payment_method'] === 'sslcommerz' ? 'Gateway transaction' : 'Transaction ID' ?></small><strong><?= e($o['payment_reference']) ?></strong><?php if ($o['payment_method'] !== 'sslcommerz'): ?><span>Sender: <?= e($o['payment_phone']) ?></span><?php endif; ?></div><?php endif; ?>
                <?php if (in_array($o['payment_method'], ['bkash', 'nagad'], true) && $o['payment_status'] === 'Submitted'): ?><div class="payment-pending-note">✓ Your payment details were submitted. The order remains under verification until an administrator confirms the transaction.</div><?php elseif ($o['payment_status'] === 'Paid'): ?><div class="payment-success-note">✓ Payment verified successfully.</div><?php elseif ($o['payment_method'] === 'cod' && $o['payment_status'] === 'Pending'): ?><div class="payment-pending-note">Pay <?= e(money($o['total'])) ?> when the order is delivered.</div><?php endif; ?>
            </div>
            <div class="hero-actions"><a class="btn btn-light" href="<?= e(url('invoice.php?id=' . $id)) ?>">Printable invoice</a><?php if (can_cancel_order($o)): ?><form method="post" action="<?= e(url('cancel-order.php')) ?>"><?= csrf_field() ?><input type="hidden" name="order_id" value="<?= $id ?>"><input type="hidden" name="reason" value="Cancelled by customer"><button class="btn btn-danger" data-confirm="Cancel this order?">Cancel order</button></form><?php endif; ?></div>
        </div>
    </div>
</section>
<?php include __DIR__ . '/partials-font/footer.php'; ?>