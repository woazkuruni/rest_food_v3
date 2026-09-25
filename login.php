<?php
require_once __DIR__ . '/config/constants.php';
if (auth_user()) redirect('profile.php');
if (is_post()) {
    verify_csrf();
    $email = trim((string)($_POST['email'] ?? ''));
    $password = (string)($_POST['password'] ?? '');
    if (!filter_var($email, FILTER_VALIDATE_EMAIL) || $password === '') {
        flash('error', 'Please enter a valid email and password.');
    } else {
        $s = db()->prepare('SELECT * FROM tbl_user WHERE email=? LIMIT 1');
        $s->execute([$email]);
        $u = $s->fetch();
        if ($u && $u['account_status'] === 'Blocked') {
            flash('error', 'This customer account is currently blocked. Please contact the restaurant.');
        } elseif ($u && verify_password_and_upgrade($password, (string)$u['password'], 'tbl_user', (int)$u['id'])) {
            login_customer($u);
            flash('success', 'Welcome back, ' . $u['full_name'] . '!');
            redirect('profile.php');
        } else {
            flash('error', 'Email or password is incorrect.');
        }
    }
}
$pageTitle = 'Login — ' . APP_NAME;
include __DIR__ . '/partials-font/menu.php'; ?>
<div class="container">
    <div class="form-card"><span class="eyebrow">Customer account</span>
        <h1>Welcome back</h1>
        <p class="muted">Log in to access wishlist, saved addresses, coupons and order tracking.</p>
        <form method="post"><?= csrf_field() ?><div class="field"><label>Email</label><input class="input" type="email" name="email" value="<?= e($_POST['email'] ?? '') ?>" required autocomplete="email"></div><br>
            <div class="field"><label>Password</label><input class="input" type="password" name="password" required autocomplete="current-password"></div><br><button class="btn btn-primary" type="submit" style="width:100%">Login</button>
        </form>
        <p class="notice">No account? <a href="<?= e(url('register.php')) ?>" style="color:var(--primary);font-weight:850">Create one</a>.</p>
    </div>
</div><?php include __DIR__ . '/partials-font/footer.php'; ?>