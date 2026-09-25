<?php
require_once __DIR__.'/../config/constants.php';
$pageTitle='Administrators';
$admins=db()->query('SELECT id,full_name,username FROM tbl_admin ORDER BY id')->fetchAll();
include __DIR__.'/partial/menu.php';
?>
<div class="admin-title"><div><h1>Administrators</h1><p class="muted">Manage admin accounts securely.</p></div><a class="btn btn-primary" href="<?= e(url('admin/add-admin.php')) ?>">Add admin</a></div>
<div class="table-wrap"><table class="table"><thead><tr><th>#</th><th>Name</th><th>Username</th><th>Actions</th></tr></thead><tbody><?php foreach($admins as $a):?><tr><td><?= (int)$a['id'] ?></td><td><?= e($a['full_name']) ?></td><td><?= e($a['username']) ?></td><td class="actions"><a class="btn btn-light btn-sm" href="<?= e(url('admin/update-admin.php?id='.$a['id'])) ?>">Edit</a><a class="btn btn-light btn-sm" href="<?= e(url('admin/update-password.php?id='.$a['id'])) ?>">Password</a><form class="inline-form" method="post" action="<?= e(url('admin/delete-admin.php')) ?>"><?= csrf_field() ?><input type="hidden" name="id" value="<?= (int)$a['id'] ?>"><button class="btn btn-danger btn-sm" data-confirm="Delete this admin?">Delete</button></form></td></tr><?php endforeach;?></tbody></table></div>
<?php include __DIR__.'/partial/footer.php'; ?>
