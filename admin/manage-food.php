<?php
require_once __DIR__ . '/../config/constants.php';
$pageTitle = 'Foods & Stock';
$rows = db()->query('SELECT f.*,c.title category_title,(SELECT COALESCE(AVG(r.rating),0) FROM tbl_review r WHERE r.food_id=f.id AND r.status="Published") rating FROM tbl_food f JOIN tbl_category c ON c.id=f.category_id ORDER BY f.id DESC')->fetchAll();
include __DIR__ . '/partial/menu.php'; ?>
<div class="admin-title">
    <div>
        <h1>Foods & Inventory</h1>
        <p class="muted">Manage menu content, price, availability and stock.</p>
    </div><a class="btn btn-primary" href="<?= e(url('admin/add-food.php')) ?>">Add food</a>
</div>
<div class="table-wrap">
    <table class="table">
        <thead>
            <tr>
                <th>Food</th>
                <th>Category</th>
                <th>Price</th>
                <th>Stock</th>
                <th>Rating</th>
                <th>Featured</th>
                <th>Active</th>
                <th></th>
            </tr>
        </thead>
        <tbody><?php foreach ($rows as $r): ?><tr>
                    <td>
                        <div style="display:flex;align-items:center;gap:10px"><img class="thumb" src="<?= e(food_image($r['image_name'])) ?>" alt=""><strong><?= e($r['title']) ?></strong></div>
                    </td>
                    <td><?= e($r['category_title']) ?></td>
                    <td><?= e(money($r['price'])) ?></td>
                    <td class="<?= (int)$r['stock_qty'] <= 5 ? 'low-stock' : '' ?>"><?= (int)$r['stock_qty'] ?></td>
                    <td>★ <?= number_format((float)$r['rating'], 1) ?></td>
                    <td><?= e($r['featured']) ?></td>
                    <td><?= e($r['active']) ?></td>
                    <td>
                        <div class="actions"><a class="btn btn-light btn-sm" href="<?= e(url('admin/update-food.php?id=' . $r['id'])) ?>">Edit</a>
                            <form method="post" action="<?= e(url('admin/delete-food.php')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-danger btn-sm" data-confirm="Delete this food? Historical order items will keep the food name.">Delete</button></form>
                        </div>
                    </td>
                </tr><?php endforeach; ?></tbody>
    </table>
</div><?php include __DIR__ . '/partial/footer.php'; ?>