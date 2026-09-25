<?php
require_once __DIR__ . '/config/constants.php';
if (auth_user()) redirect('profile.php');
if (is_post()) {
    verify_csrf();
    $username = trim((string)($_POST['user_name'] ?? ''));
    $name = trim((string)($_POST['name'] ?? ''));
    $email = trim((string)($_POST['email'] ?? ''));
    $phone = trim((string)($_POST['contact'] ?? ''));
    $address = trim((string)($_POST['address'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    $confirm = (string)($_POST['c_password'] ?? '');
    $errors = [];
    if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username)) $errors[] = 'Username must be 3–30 characters using letters, numbers or underscore.';
    if (mb_strlen($name) < 2) $errors[] = 'Please enter your full name.';
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email.';
    if (!preg_match('/^[+0-9][0-9\s-]{7,19}$/', $phone)) $errors[] = 'Please enter a valid phone number.';
    if (mb_strlen($address) < 5) $errors[] = 'Please enter your delivery address.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters.';
    if ($password !== $confirm) $errors[] = 'Passwords do not match.';
    $s = db()->prepare('SELECT id FROM tbl_user WHERE email=? OR user_name=? LIMIT 1');
    $s->execute([$email, $username]);
    if ($s->fetch()) $errors[] = 'That email or username is already registered.';
    if (!$errors) {
        try {
            db()->beginTransaction();
            $image = upload_image($_FILES['image'] ?? [], 'users', 'default_profile.webp') ?? 'default_profile.webp';
            $s = db()->prepare("INSERT INTO tbl_user(user_name,full_name,phone_number,email,address,password,image_name,account_status,created_at) VALUES(?,?,?,?,?,?,?,'Active',NOW())");
            $s->execute([$username, $name, $phone, $email, $address, password_hash($password, PASSWORD_DEFAULT), $image]);
            $id = (int)db()->lastInsertId();
            $s = db()->prepare('INSERT INTO tbl_user_address(user_id,label,recipient_name,phone_number,address,is_default,created_at) VALUES(?,?,?,?,?,1,NOW())');
            $s->execute([$id, 'Home', $name, $phone, $address]);
            db()->commit();
            login_customer(['id' => $id]);
            flash('success', 'Account created successfully.');
            redirect('profile.php');
        } catch (Throwable $e) {
            if (db()->inTransaction()) db()->rollBack();
            $errors[] = APP_ENV === 'local' ? $e->getMessage() : 'Registration failed. Please try again.';
        }
    }
    foreach ($errors as $er) flash('error', $er);
}
$pageTitle = 'Create Account — ' . APP_NAME;
include __DIR__ . '/partials-font/menu.php'; ?>
<div class="container">
    <div class="form-card"><span class="eyebrow">Join Velora Food</span>
        <h1>Create account</h1>
        <p class="muted">Your first address is saved automatically as Home.</p>
        <form method="post" enctype="multipart/form-data"><?= csrf_field() ?><div class="form-grid">
                <div class="field"><label>Username</label><input class="input" name="user_name" value="<?= e($_POST['user_name'] ?? '') ?>" required></div>
                <div class="field"><label>Full name</label><input class="input" name="name" value="<?= e($_POST['name'] ?? '') ?>" required></div>
                <div class="field"><label>Email</label><input class="input" type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required></div>
                <div class="field"><label>Phone</label><input class="input" name="contact" value="<?= e($_POST['contact'] ?? '') ?>" placeholder="+8801XXXXXXXXX" required></div>
                <div class="field full"><label>Delivery address</label><textarea name="address" rows="3" required><?= e($_POST['address'] ?? '') ?></textarea></div>
                <div class="field full"><label>Profile image (optional)</label><input class="input" type="file" name="image" accept="image/jpeg,image/png,image/webp"></div>
                <div class="field"><label>Password</label><input class="input" type="password" name="password" minlength="8" required></div>
                <div class="field"><label>Confirm password</label><input class="input" type="password" name="c_password" minlength="8" required></div>
            </div><br><button class="btn btn-primary" style="width:100%">Create account</button></form>
        <p class="notice">Already registered? <a href="<?= e(url('login.php')) ?>" style="color:var(--primary);font-weight:850">Login</a>.</p>
    </div>
</div><?php include __DIR__ . '/partials-font/footer.php'; ?>