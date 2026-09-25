<?php
require_once __DIR__ . '/../config/constants.php';
require_admin();
if (is_post()) {
    verify_csrf();
    $id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
    $action = (string)($_POST['action'] ?? 'toggle');
    if ($id && $action === 'delete') {
        db()->prepare('DELETE FROM tbl_review WHERE id=?')->execute([$id]);
        flash('success', 'Review deleted.');
    } elseif ($id) {
        db()->prepare("UPDATE tbl_review SET status=IF(status='Published','Hidden','Published') WHERE id=?")->execute([$id]);
        flash('success', 'Review visibility updated.');
    }
    redirect('admin/manage-review.php');
}
$rows = db()->query('SELECT r.*,u.full_name,f.title food_title FROM tbl_review r JOIN tbl_user u ON u.id=r.user_id JOIN tbl_food f ON f.id=r.food_id ORDER BY r.id DESC')->fetchAll();
$pageTitle = 'Reviews';
include __DIR__ . '/partial/menu.php'; ?>
<div class="admin-title">
    <div>
        <h1>Reviews</h1>
        <p class="muted">Moderate customer ratings and comments.</p>
    </div>
</div>
<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>Customer</th>
                <th>Food</th>
                <th>Rating</th>
                <th>Review</th>
                <th>Status</th>
                <th>Date</th>
                <th></th>
            </tr>
        </thead>
        <tbody><?php foreach ($rows as $r): ?><tr>
                    <td><?= e($r['full_name']) ?></td>
                    <td><?= e($r['food_title']) ?></td>
                    <td><span class="rating"><?= e(rating_stars((float)$r['rating'])) ?></span></td>
                    <td style="max-width:340px"><?= e(mb_strimwidth((string)$r['review_text'], 0, 120, '…')) ?></td>
                    <td><?= e($r['status']) ?></td>
                    <td><?= e(date('M d, Y', strtotime($r['created_at']))) ?></td>
                    <td>
                        <div class="actions">
                            <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="action" value="toggle"><button class="btn btn-light btn-sm"><?= $r['status'] === 'Published' ? 'Hide' : 'Publish' ?></button></form>
                            <form method="post"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><input type="hidden" name="action" value="delete"><button class="btn btn-danger btn-sm" data-confirm="Delete this review permanently?">Delete</button></form>
                        </div>
                    </td>
                </tr><?php endforeach; ?></tbody>
    </table>
</div><?php include __DIR__ . '/partial/footer.php'; ?>