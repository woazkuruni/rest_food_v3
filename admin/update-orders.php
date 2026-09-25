<?php
require_once __DIR__ . '/../config/constants.php';
require_admin();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) redirect('admin/manage-order.php');

$stmt = db()->prepare('SELECT o.*,u.full_name,u.email FROM tbl_order o JOIN tbl_user u ON u.id=o.user_id WHERE o.id=?');
$stmt->execute([$id]);
$order = $stmt->fetch();
if (!$order) {
    flash('error', 'Order not found.');
    redirect('admin/manage-order.php');
}
$stmt = db()->prepare('SELECT * FROM tbl_order_item WHERE order_id=?');
$stmt->execute([$id]);
$items = $stmt->fetchAll();

if (is_post()) {
    verify_csrf();
    $status = (string)($_POST['status'] ?? $order['status']);
    $payment = (string)($_POST['payment_status'] ?? $order['payment_status']);
    if (!in_array($status, valid_order_statuses(), true)) $status = $order['status'];
    if (!in_array($payment, ['Pending', 'Submitted', 'Paid', 'Failed', 'Refunded'], true)) $payment = $order['payment_status'];

    try {
        db()->beginTransaction();
        $lock = db()->prepare('SELECT status FROM tbl_order WHERE id=? FOR UPDATE');
        $lock->execute([$id]);
        $currentStatus = (string)$lock->fetchColumn();
        if ($currentStatus === 'Cancelled' && $status !== 'Cancelled') {
            $status = 'Cancelled';
            flash('warning', 'Cancelled orders cannot be reopened automatically. Create a new order if needed.');
        }

        if ($status === 'Cancelled' && $currentStatus !== 'Cancelled') {
            $itemStmt = db()->prepare('SELECT food_id,quantity FROM tbl_order_item WHERE order_id=?');
            $itemStmt->execute([$id]);
            foreach ($itemStmt->fetchAll() as $item) {
                if ($item['food_id']) db()->prepare('UPDATE tbl_food SET stock_qty=stock_qty+? WHERE id=?')->execute([$item['quantity'], $item['food_id']]);
            }
            $couponStmt = db()->prepare('SELECT coupon_id FROM tbl_coupon_usage WHERE order_id=?');
            $couponStmt->execute([$id]);
            if ($couponId = $couponStmt->fetchColumn()) {
                db()->prepare('DELETE FROM tbl_coupon_usage WHERE order_id=?')->execute([$id]);
                db()->prepare('UPDATE tbl_coupon SET used_count=GREATEST(used_count-1,0) WHERE id=?')->execute([$couponId]);
            }
            db()->prepare('UPDATE tbl_order SET status=?,payment_status=?,cancel_reason=COALESCE(cancel_reason,?),cancelled_at=NOW() WHERE id=?')->execute([$status, $payment, 'Cancelled by administrator', $id]);
        } else {
            db()->prepare('UPDATE tbl_order SET status=?,payment_status=? WHERE id=?')->execute([$status, $payment, $id]);
        }
        db()->commit();
        flash('success', 'Order updated.');
    } catch (Throwable $e) {
        if (db()->inTransaction()) db()->rollBack();
        flash('error', APP_ENV === 'local' ? $e->getMessage() : 'Could not update order.');
    }
    redirect('admin/update-orders.php?id=' . $id);
}

$pageTitle = 'Order #' . (1000 + $id);
include __DIR__ . '/partial/menu.php';
?>
<div class="admin-title">
    <div>
        <h1>Order #<?= 1000 + $id ?></h1>
        <p class="muted"><?= e($order['full_name']) ?> • <?= e($order['email']) ?></p>
    </div><a class="btn btn-light" href="<?= e(url('admin/manage-order.php')) ?>">Back to orders</a>
</div>
<div class="checkout-grid">
    <div class="panel">
        <h2>Items</h2><?php foreach ($items as $item): ?><div class="summary-row"><span><?= e($item['food_name']) ?> × <?= (int)$item['quantity'] ?></span><strong><?= e(money($item['subtotal'])) ?></strong></div><?php endforeach; ?>
        <hr class="sep">
        <div class="summary-row"><span>Subtotal</span><strong><?= e(money($order['subtotal'])) ?></strong></div>
        <div class="summary-row"><span>Discount <?= $order['coupon_code'] ? '(' . e($order['coupon_code']) . ')' : '' ?></span><strong>-<?= e(money($order['discount'])) ?></strong></div>
        <div class="summary-row"><span>Delivery</span><strong><?= e(money($order['delivery_fee'])) ?></strong></div>
        <div class="summary-row total"><span>Total</span><span><?= e(money($order['total'])) ?></span></div>
        <hr class="sep"><strong>Delivery</strong>
        <p class="muted"><?= e($order['delivery_address']) ?><br><?= e($order['phone_number']) ?></p><strong>Payment</strong>
        <p><?= e(payment_label($order['payment_method'])) ?> • <?= e($order['payment_status']) ?><?php if ($order['payment_reference']): ?><br><?= $order['payment_method'] === 'sslcommerz' ? 'Gateway transaction' : 'Sender' ?>: <?= e($order['payment_method'] === 'sslcommerz' ? $order['payment_reference'] : $order['payment_phone']) ?><?php if ($order['payment_method'] !== 'sslcommerz'): ?><br>Transaction: <strong><?= e($order['payment_reference']) ?></strong><?php endif; ?><?php endif; ?></p><?php if ($order['cancel_reason']): ?><p class="muted">Cancellation: <?= e($order['cancel_reason']) ?></p><?php endif; ?>
    </div>
    <div class="panel">
        <h2>Update order</h2>
        <form method="post"><?= csrf_field() ?><div class="field"><label>Delivery status</label><select name="status"><?php foreach (valid_order_statuses() as $x): ?><option <?= $order['status'] === $x ? 'selected' : '' ?>><?= e($x) ?></option><?php endforeach; ?></select></div><br>
            <div class="field"><label>Payment status</label><select name="payment_status"><?php foreach (['Pending', 'Submitted', 'Paid', 'Failed', 'Refunded'] as $x): ?><option <?= $order['payment_status'] === $x ? 'selected' : '' ?>><?= e($x) ?></option><?php endforeach; ?></select></div>
            <p class="notice">Verify bKash/Nagad in the merchant account before marking Paid. If cancelling a paid order, record the actual refund separately before marking Refunded.</p><button class="btn btn-primary">Save update</button>
        </form>
    </div>
</div>
<?php include __DIR__ . '/partial/footer.php'; ?>