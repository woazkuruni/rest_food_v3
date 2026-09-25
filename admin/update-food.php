<?php
require_once __DIR__ . '/../config/constants.php';
require_admin();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) redirect('admin/manage-food.php');
$s = db()->prepare('SELECT * FROM tbl_food WHERE id=?');
$s->execute([$id]);
$r = $s->fetch();
if (!$r) {
    flash('error', 'Food not found.');
    redirect('admin/manage-food.php');
}
$cats = db()->query('SELECT id,title FROM tbl_category ORDER BY title')->fetchAll();
if (is_post()) {
    verify_csrf();
    $title = trim((string)($_POST['title'] ?? ''));
    $desc = trim((string)($_POST['description'] ?? ''));
    $price = filter_input(INPUT_POST, 'price', FILTER_VALIDATE_FLOAT);
    $stock = filter_input(INPUT_POST, 'stock_qty', FILTER_VALIDATE_INT);
    $cat = filter_input(INPUT_POST, 'category_id', FILTER_VALIDATE_INT);
    $featured = ($_POST['featured'] ?? 'No') === 'Yes' ? 'Yes' : 'No';
    $active = ($_POST['active'] ?? 'No') === 'Yes' ? 'Yes' : 'No';
    if (mb_strlen($title) < 2 || $price === false || $price <= 0 || $stock === false || $stock < 0 || !$cat) {
        flash('error', 'Please enter valid food details.');
    } else {
        try {
            $image = upload_image($_FILES['image'] ?? [], 'foods', $r['image_name']);
            db()->prepare('UPDATE tbl_food SET title=?,description=?,price=?,image_name=?,category_id=?,stock_qty=?,featured=?,active=? WHERE id=?')->execute([$title, $desc, $price, $image, $cat, $stock, $featured, $active, $id]);
            flash('success', 'Food updated.');
            redirect('admin/manage-food.php');
        } catch (Throwable $e) {
            flash('error', $e->getMessage());
        }
    }
}
$pageTitle = 'Edit Food';
include __DIR__ . '/partial/menu.php'; ?>
<div class="admin-title">
    <h1>Edit Food</h1>
</div>
<div class="panel admin-form">
    <form method="post" enctype="multipart/form-data"><?= csrf_field() ?><div class="field"><label>Title</label><input class="input" name="title" value="<?= e($_POST['title'] ?? $r['title']) ?>" required></div><br>
        <div class="field"><label>Description</label><textarea name="description" rows="4"><?= e($_POST['description'] ?? $r['description']) ?></textarea></div><br>
        <div class="form-grid">
            <div class="field"><label>Price</label><input class="input" type="number" step="0.01" min="1" name="price" value="<?= e($r['price']) ?>" required></div>
            <div class="field"><label>Stock</label><input class="input" type="number" min="0" name="stock_qty" value="<?= (int)$r['stock_qty'] ?>" required></div>
            <div class="field"><label>Category</label><select name="category_id"><?php foreach ($cats as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)$c['id'] === (int)$r['category_id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option><?php endforeach; ?></select></div>
            <div class="field"><label>Featured</label><select name="featured">
                    <option <?= $r['featured'] === 'No' ? 'selected' : '' ?>>No</option>
                    <option <?= $r['featured'] === 'Yes' ? 'selected' : '' ?>>Yes</option>
                </select></div>
            <div class="field"><label>Active</label><select name="active">
                    <option <?= $r['active'] === 'Yes' ? 'selected' : '' ?>>Yes</option>
                    <option <?= $r['active'] === 'No' ? 'selected' : '' ?>>No</option>
                </select></div>
        </div><br><img class="thumb" src="<?= e(food_image($r['image_name'])) ?>" alt=""><br>
        <div class="field"><label>Replace image</label><input class="input" type="file" name="image" accept="image/jpeg,image/png,image/webp"></div><br><button class="btn btn-primary">Save changes</button>
    </form>
</div><?php include __DIR__ . '/partial/footer.php'; ?>