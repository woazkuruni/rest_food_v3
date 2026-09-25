<?php
require_once __DIR__ . '/../config/constants.php';
if (auth_admin()) {
    redirect('admin/index.php');
}
if (is_post()) {
    verify_csrf();
    $username = trim((string)($_POST['username'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $stmt = db()->prepare('SELECT * FROM tbl_admin WHERE username=? LIMIT 1');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    if ($admin && verify_password_and_upgrade($password, (string)$admin['password'], 'tbl_admin', (int)$admin['id'])) {
        login_admin($admin);
        flash('success', 'Admin login successful.');
        redirect('admin/index.php');
    }
    flash('error', 'Username or password is incorrect.');
}
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title>Admin Login — <?= e(APP_NAME) ?></title>
    <link rel="stylesheet" href="<?= e(url('css/admin-v3.css')) ?>">
</head>

<body>
    <div class="login-shell">
        <div class="form-card" style="width:min(460px,100%);margin:0">
            <h1>Admin Portal</h1>
            <p class="muted">Sign in to manage the restaurant.</p><?php render_flashes(); ?><form method="post"><?= csrf_field() ?><div class="field"><label>Username</label><input class="input" name="username" required autocomplete="username"></div><br>
                <div class="field"><label>Password</label><input class="input" type="password" name="password" required autocomplete="current-password"></div><br><button class="btn btn-primary" style="width:100%">Login</button>
            </form>
            <p class="notice">Default fresh-install admin: <strong>admin</strong> / <strong>Admin@123</strong></p>
        </div>
    </div>
</body>

</html>