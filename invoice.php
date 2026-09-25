<?php
require_once __DIR__ . '/config/constants.php';
$u = require_login();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
$s = db()->prepare('SELECT o.*,u.full_name,u.email FROM tbl_order o JOIN tbl_user u ON u.id=o.user_id WHERE o.id=? AND o.user_id=?');
$s->execute([$id, $u['id']]);
$o = $s->fetch();
if (!$o) {
    flash('error', 'Invoice not found.');
    redirect('my_order.php');
}
$s = db()->prepare('SELECT * FROM tbl_order_item WHERE order_id=?');
$s->execute([$id]);
$items = $s->fetchAll();
$pageTitle = 'Invoice #' . (1000 + $id);
include __DIR__ . '/partials-font/menu.php'; ?>
<section class="section">
    <div class="container">
        <div class="invoice">
            <div class="invoice-head">
                <div>
                    <h1>Velora.FOOD</h1>
                    <p class="muted">Restaurant Order Invoice</p>
                </div>
                <div><strong>Invoice #<?= 1000 + $id ?></strong><br><?= e(date('M d, Y', strtotime($o['order_date']))) ?></div>
            </div>
            <div class="two-column">
                <div><strong>Billed to</strong>
                    <p><?= e($o['full_name']) ?><br><?= e($o['email']) ?><br><?= e($o['phone_number']) ?></p>
                </div>
                <div><strong>Delivery to</strong>
                    <p><?= e($o['delivery_address']) ?></p>
                </div>
            </div>
            <div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Item</th>
                            <th>Qty</th>
                            <th>Price</th>
                            <th>Total</th>
                        </tr>
                    </thead>
                    <tbody><?php foreach ($items as $i): ?><tr>
                                <td><?= e($i['food_name']) ?></td>
                                <td><?= (int)$i['quantity'] ?></td>
                                <td><?= e(money($i['price'])) ?></td>
                                <td><?= e(money($i['subtotal'])) ?></td>
                            </tr><?php endforeach; ?></tbody>
                </table>
            </div><br>
            <div class="summary" style="margin-left:auto">
                <div class="summary-row"><span>Subtotal</span><strong><?= e(money($o['subtotal'])) ?></strong></div>
                <div class="summary-row"><span>Discount</span><strong>-<?= e(money($o['discount'])) ?></strong></div>
                <div class="summary-row"><span>Delivery</span><strong><?= e(money($o['delivery_fee'])) ?></strong></div>
                <div class="summary-row total"><span>Total</span><span><?= e(money($o['total'])) ?></span></div>
            </div>
            <p><strong>Payment:</strong> <?= e(payment_label($o['payment_method'])) ?> • <?= e($o['payment_status']) ?><br><strong>Order status:</strong> <?= e($o['status']) ?></p>
            <div class="print-hide"><button class="btn btn-primary" onclick="window.print()">Print invoice</button> <a class="btn btn-light" href="<?= e(url('order-details.php?id=' . $id)) ?>">Back to order</a></div>
        </div>
    </div>
</section><?php include __DIR__ . '/partials-font/footer.php'; ?>