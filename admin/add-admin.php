<?php
require_once __DIR__ . '/../config/constants.php';
require_admin();
if (is_post()) {
    verify_csrf();
    $name = trim((string)($_POST['full_name'] ?? ''));
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if (mb_strlen($name) < 2 || mb_strlen($username) < 3 || strlen($password) < 8) {
        flash('error', 'Use a valid name, username and password of at least 8 characters.');
    } else {
        $stmt = db()->prepare('SELECT id FROM tbl_admin WHERE username=?');
        $stmt->execute([$username]);
        if ($stmt->fetch()) {
            flash('error', 'Username already exists.');
        } else {
            $stmt = db()->prepare('INSERT INTO tbl_admin(full_name,username,password,created_at) VALUES(?,?,?,NOW())');
            $stmt->execute([$name, $username, password_hash($password, PASSWORD_DEFAULT)]);
            flash('success', 'Admin added.');
            redirect('admin/manage-admin.php');
        }
    }
}
$pageTitle = 'Add Administrator';
include __DIR__ . '/partial/menu.php'; ?>
<div class="admin-title">
    <h1>Add Administrator</h1>
</div>
<div class="panel admin-form">
    <form method="post"><?= csrf_field() ?><div class="field"><label>Full name</label><input class="input" name="full_name" value="<?= e($_POST['full_name'] ?? '') ?>" required></div><br>
        <div class="field"><label>Username</label><input class="input" name="username" value="<?= e($_POST['username'] ?? '') ?>" required></div><br>
        <div class="field"><label>Password</label><input class="input" type="password" name="password" minlength="8" required></div><br><button class="btn btn-primary">Add admin</button>
    </form>
</div><?php include __DIR__ . '/partial/footer.php'; ?>