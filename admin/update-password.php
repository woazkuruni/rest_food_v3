<?php
require_once __DIR__ . '/../config/constants.php';
require_admin();
$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    redirect('admin/manage-admin.php');
}
$stmt = db()->prepare('SELECT id,full_name FROM tbl_admin WHERE id=?');
$stmt->execute([$id]);
$row = $stmt->fetch();
if (!$row) {
    flash('error', 'Admin not found.');
    redirect('admin/manage-admin.php');
}
if (is_post()) {
    verify_csrf();
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['confirm_password'] ?? '');
    if (strlen($password) < 8) flash('error', 'Password must be at least 8 characters.');
    elseif ($password !== $confirm) flash('error', 'Passwords do not match.');
    else {
        $stmt = db()->prepare('UPDATE tbl_admin SET password=? WHERE id=?');
        $stmt->execute([password_hash($password, PASSWORD_DEFAULT), $id]);
        flash('success', 'Password updated.');
        redirect('admin/manage-admin.php');
    }
}
$pageTitle = 'Change Admin Password';
include __DIR__ . '/partial/menu.php'; ?>
<div class="admin-title">
    <h1>Change Password</h1>
</div>
<div class="panel admin-form">
    <p class="muted">Account: <?= e($row['full_name']) ?></p>
    <form method="post"><?= csrf_field() ?><div class="field"><label>New password</label><input class="input" type="password" name="password" minlength="8" required></div><br>
        <div class="field"><label>Confirm password</label><input class="input" type="password" name="confirm_password" minlength="8" required></div><br><button class="btn btn-primary">Update password</button>
    </form>
</div><?php include __DIR__ . '/partial/footer.php'; ?>