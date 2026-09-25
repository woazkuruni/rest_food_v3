<?php
require_once __DIR__ . '/../config/constants.php';
$pageTitle = 'Orders';
$status = trim((string)($_GET['status'] ?? ''));
$pay = trim((string)($_GET['payment'] ?? ''));
$where = [];
$params = [];
if (in_array($status, valid_order_statuses(), true)) {
    $where[] = 'o.status=?';
    $params[] = $status;
}
if (in_array($pay, ['Pending', 'Submitted', 'Paid', 'Failed', 'Refunded'], true)) {
    $where[] = 'o.payment_status=?';
    $params[] = $pay;
}
$sql = 'SELECT o.*,u.full_name,u.email,(SELECT COUNT(*) FROM tbl_order_item oi WHERE oi.order_id=o.id) item_count FROM tbl_order o JOIN tbl_user u ON u.id=o.user_id' . ($where ? ' WHERE ' . implode(' AND ', $where) : '') . ' ORDER BY o.id DESC';
$s = db()->prepare($sql);
$s->execute($params);
$rows = $s->fetchAll();
include __DIR__ . '/partial/menu.php'; ?>
<div class="admin-title">
    <div>
        <h1>Orders</h1>
        <p class="muted">Delivery progress and manual payment verification.</p>
    </div>
    <form class="actions"><select name="status">
            <option value="">All statuses</option><?php foreach (valid_order_statuses() as $x): ?><option <?= $status === $x ? 'selected' : '' ?>><?= e($x) ?></option><?php endforeach; ?>
        </select><select name="payment">
            <option value="">All payments</option><?php foreach (['Pending', 'Submitted', 'Paid', 'Failed', 'Refunded'] as $x): ?><option <?= $pay === $x ? 'selected' : '' ?>><?= e($x) ?></option><?php endforeach; ?>
        </select><button class="btn btn-light">Filter</button></form>
</div>
<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>Order</th>
                <th>Customer</th>
                <th>Items</th>
                <th>Total</th>
                <th>Payment</th>
                <th>Status</th>
                <th>Date</th>
                <th></th>
            </tr>
        </thead>
        <tbody><?php foreach ($rows as $r): ?><tr>
                    <td>#<?= 1000 + (int)$r['id'] ?></td>
                    <td><strong><?= e($r['full_name']) ?></strong>
                        <div class="muted"><?= e($r['email']) ?></div>
                    </td>
                    <td><?= (int)$r['item_count'] ?></td>
                    <td><?= e(money($r['total'])) ?></td>
                    <td><?= e(payment_label($r['payment_method'])) ?><br><span class="status <?= e(status_class($r['payment_status'])) ?>"><?= e($r['payment_status']) ?></span></td>
                    <td><span class="status <?= e(status_class($r['status'])) ?>"><?= e($r['status']) ?></span></td>
                    <td><?= e(date('M d, Y h:i A', strtotime($r['order_date']))) ?></td>
                    <td><a class="btn btn-light btn-sm" href="<?= e(url('admin/update-orders.php?id=' . $r['id'])) ?>">View / Update</a></td>
                </tr><?php endforeach; ?></tbody>
    </table>
</div><?php include __DIR__ . '/partial/footer.php'; ?>