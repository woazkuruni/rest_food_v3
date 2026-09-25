<?php
require_once __DIR__ . '/../config/constants.php';
require_admin();
if (is_post()) {
    verify_csrf();
    $action = (string)($_POST['action'] ?? 'add');
    if ($action === 'add') {
        $code = strtoupper(trim((string)($_POST['code'] ?? '')));
        $type = in_array($_POST['discount_type'] ?? '', ['Percentage', 'Fixed'], true) ? $_POST['discount_type'] : 'Fixed';
        $value = filter_input(INPUT_POST, 'discount_value', FILTER_VALIDATE_FLOAT);
        $min = filter_input(INPUT_POST, 'min_order', FILTER_VALIDATE_FLOAT);
        $maxRaw = trim((string)($_POST['max_discount'] ?? ''));
        $max = $maxRaw === '' ? null : (float)$maxRaw;
        $limitRaw = trim((string)($_POST['usage_limit'] ?? ''));
        $limit = $limitRaw === '' ? null : (int)$limitRaw;
        $start = str_replace('T', ' ', (string)($_POST['starts_at'] ?? ''));
        $end = str_replace('T', ' ', (string)($_POST['ends_at'] ?? ''));
        if (!preg_match('/^[A-Z0-9_-]{3,20}$/', $code) || $value === false || $value <= 0 || ($type === 'Percentage' && $value > 100) || $min === false || strtotime($start) === false || strtotime($end) === false || strtotime($end) <= strtotime($start)) {
            flash('error', 'Please enter valid coupon details.');
        } else {
            try {
                db()->prepare("INSERT INTO tbl_coupon(code,discount_type,discount_value,min_order,max_discount,starts_at,ends_at,usage_limit,active,created_at) VALUES(?,?,?,?,?,?,?,?, 'Yes',NOW())")->execute([$code, $type, $value, $min, $max, date('Y-m-d H:i:s', strtotime($start)), date('Y-m-d H:i:s', strtotime($end)), $limit]);
                flash('success', 'Coupon created.');
            } catch (Throwable $e) {
                flash('error', 'Coupon code may already exist.');
            }
        }
    } elseif ($action === 'toggle') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id) db()->prepare("UPDATE tbl_coupon SET active=IF(active='Yes','No','Yes') WHERE id=?")->execute([$id]);
        flash('success', 'Coupon status changed.');
    } elseif ($action === 'delete') {
        $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
        if ($id) {
            try {
                db()->prepare('DELETE FROM tbl_coupon WHERE id=?')->execute([$id]);
                flash('success', 'Coupon deleted.');
            } catch (Throwable $e) {
                flash('warning', 'Used coupons are kept for order history. Disable it instead.');
            }
        }
    }
    redirect('admin/manage-coupon.php');
}
$rows = db()->query('SELECT * FROM tbl_coupon ORDER BY id DESC')->fetchAll();
$pageTitle = 'Coupons';
include __DIR__ . '/partial/menu.php'; ?>
<div class="admin-title">
    <div>
        <h1>Coupons</h1>
        <p class="muted">Create controlled percentage or fixed discounts.</p>
    </div>
</div>
<div class="panel">
    <h2>Create coupon</h2>
    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="add">
        <div class="form-grid">
            <div class="field"><label>Code</label><input class="input" name="code" placeholder="SAVE20" required></div>
            <div class="field"><label>Type</label><select name="discount_type">
                    <option>Percentage</option>
                    <option>Fixed</option>
                </select></div>
            <div class="field"><label>Value</label><input class="input" type="number" step="0.01" name="discount_value" required></div>
            <div class="field"><label>Minimum order</label><input class="input" type="number" step="0.01" name="min_order" value="0" required></div>
            <div class="field"><label>Max discount (optional)</label><input class="input" type="number" step="0.01" name="max_discount"></div>
            <div class="field"><label>Usage limit (optional)</label><input class="input" type="number" name="usage_limit"></div>
            <div class="field"><label>Starts</label><input class="input" type="datetime-local" name="starts_at" value="<?= e(date('Y-m-d\TH:i')) ?>" required></div>
            <div class="field"><label>Ends</label><input class="input" type="datetime-local" name="ends_at" value="<?= e(date('Y-m-d\TH:i', strtotime('+30 days'))) ?>" required></div>
        </div><br><button class="btn btn-primary">Create coupon</button>
    </form>
</div><br>
<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>Code</th>
                <th>Discount</th>
                <th>Minimum</th>
                <th>Usage</th>
                <th>Validity</th>
                <th>Status</th>
                <th></th>
            </tr>
        </thead>
        <tbody><?php foreach ($rows as $r): ?><tr>
                    <td><strong><?= e($r['code']) ?></strong></td>
                    <td><?= $r['discount_type'] === 'Percentage' ? e($r['discount_value']) . '%' : e(money($r['discount_value'])) ?></td>
                    <td><?= e(money($r['min_order'])) ?></td>
                    <td><?= (int)$r['used_count'] ?> / <?= $r['usage_limit'] === null ? '∞' : (int)$r['usage_limit'] ?></td>
                    <td><?= e(date('M d, Y', strtotime($r['starts_at']))) ?> → <?= e(date('M d, Y', strtotime($r['ends_at']))) ?></td>
                    <td><?= e($r['active']) ?></td>
                    <td>
                        <div class="actions">
                            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="toggle"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-light btn-sm">Toggle</button></form>
                            <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="delete"><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-danger btn-sm" data-confirm="Delete this unused coupon?">Delete</button></form>
                        </div>
                    </td>
                </tr><?php endforeach; ?></tbody>
    </table>
</div><?php include __DIR__ . '/partial/footer.php'; ?>