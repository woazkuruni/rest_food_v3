<?php
require_once __DIR__.'/../config/constants.php';
$pageTitle='Categories';$rows=db()->query('SELECT * FROM tbl_category ORDER BY id DESC')->fetchAll();include __DIR__.'/partial/menu.php';
?>
<div class="admin-title"><div><h1>Categories</h1><p class="muted">Organize the menu by category.</p></div><a class="btn btn-primary" href="<?= e(url('admin/add-category.php')) ?>">Add category</a></div>
<div class="table-wrap"><table class="table"><thead><tr><th>#</th><th>Image</th><th>Title</th><th>Featured</th><th>Active</th><th>Actions</th></tr></thead><tbody><?php foreach($rows as $r):?><tr><td><?= (int)$r['id'] ?></td><td><img class="thumb" src="<?= e(category_image($r['image_name'])) ?>" alt=""></td><td><?= e($r['title']) ?></td><td><?= e($r['featured']) ?></td><td><?= e($r['active']) ?></td><td class="actions"><a class="btn btn-light btn-sm" href="<?= e(url('admin/update-category.php?id='.$r['id'])) ?>">Edit</a><form class="inline-form" method="post" action="<?= e(url('admin/delete-category.php')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$r['id'] ?>"><button class="btn btn-danger btn-sm" data-confirm="Delete this category? Foods in it must be reassigned first.">Delete</button></form></td></tr><?php endforeach;?></tbody></table></div>
<?php include __DIR__.'/partial/footer.php';?>
