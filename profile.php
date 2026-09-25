<?php
require_once __DIR__ . '/config/constants.php';
$user = require_login();

if (is_post()) {
    verify_csrf();
    $action = (string)($_POST['action'] ?? 'profile');

    if ($action === 'profile') {
        $username = trim((string)($_POST['user_name'] ?? ''));
        $name = trim((string)($_POST['full_name'] ?? ''));
        $email = trim((string)($_POST['email'] ?? ''));
        $phone = trim((string)($_POST['phone_number'] ?? ''));
        $address = trim((string)($_POST['address'] ?? ''));

        if (!preg_match('/^[A-Za-z0-9_]{3,30}$/', $username) || mb_strlen($name) < 2 || !filter_var($email, FILTER_VALIDATE_EMAIL) || !preg_match('/^[+0-9][0-9\s-]{7,19}$/', $phone) || mb_strlen($address) < 5) {
            flash('error', 'Please check your username, name, email, phone and address.');
        } else {
            $stmt = db()->prepare('SELECT id FROM tbl_user WHERE (email=? OR user_name=?) AND id<>? LIMIT 1');
            $stmt->execute([$email, $username, $user['id']]);
            if ($stmt->fetch()) {
                flash('error', 'Email or username is already in use.');
            } else {
                try {
                    $image = upload_image($_FILES['image'] ?? [], 'users', $user['image_name']) ?? $user['image_name'];
                    $stmt = db()->prepare('UPDATE tbl_user SET user_name=?,full_name=?,email=?,phone_number=?,address=?,image_name=? WHERE id=?');
                    $stmt->execute([$username, $name, $email, $phone, $address, $image, $user['id']]);
                    flash('success', 'Profile updated successfully.');
                    redirect('profile.php#account');
                } catch (Throwable $e) {
                    flash('error', $e->getMessage());
                }
            }
        }
    } elseif ($action === 'remove_image') {
        $current = basename((string)($user['image_name'] ?? ''));
        if ($current !== '' && $current !== 'default_profile.webp') {
            $path = __DIR__ . '/images/users/' . $current;
            if (is_file($path)) @unlink($path);
        }
        db()->prepare('UPDATE tbl_user SET image_name=? WHERE id=?')->execute(['default_profile.webp', $user['id']]);
        flash('success', 'Profile photo removed.');
        redirect('profile.php#account');
    } elseif ($action === 'password') {
        $current = (string)($_POST['current_password'] ?? '');
        $new = (string)($_POST['new_password'] ?? '');
        $confirm = (string)($_POST['confirm_password'] ?? '');
        $stmt = db()->prepare('SELECT password FROM tbl_user WHERE id=?');
        $stmt->execute([$user['id']]);
        $stored = (string)$stmt->fetchColumn();

        if (!verify_password_and_upgrade($current, $stored, 'tbl_user', (int)$user['id'])) {
            flash('error', 'Current password is incorrect.');
        } elseif (strlen($new) < 8) {
            flash('error', 'New password must be at least 8 characters.');
        } elseif ($new !== $confirm) {
            flash('error', 'New passwords do not match.');
        } else {
            db()->prepare('UPDATE tbl_user SET password=? WHERE id=?')->execute([password_hash($new, PASSWORD_DEFAULT), $user['id']]);
            flash('success', 'Password changed successfully.');
            redirect('profile.php#security');
        }
    } elseif ($action === 'address_add' || $action === 'address_update') {
        $label = trim((string)($_POST['label'] ?? 'Home'));
        $recipient = trim((string)($_POST['recipient_name'] ?? ''));
        $phone = trim((string)($_POST['address_phone'] ?? ''));
        $address = trim((string)($_POST['saved_address'] ?? ''));
        $addressId = filter_input(INPUT_POST, 'address_id', FILTER_VALIDATE_INT);

        if (mb_strlen($recipient) < 2 || mb_strlen($address) < 5 || !preg_match('/^[+0-9][0-9\s-]{7,19}$/', $phone)) {
            flash('error', 'Please enter valid saved-address details.');
        } elseif ($action === 'address_update') {
            if (!$addressId) {
                flash('error', 'Address not found.');
            } else {
                $stmt = db()->prepare('UPDATE tbl_user_address SET label=?,recipient_name=?,phone_number=?,address=? WHERE id=? AND user_id=?');
                $stmt->execute([$label ?: 'Address', $recipient, $phone, $address, $addressId, $user['id']]);
                flash($stmt->rowCount() ? 'success' : 'info', $stmt->rowCount() ? 'Saved address updated.' : 'No address changes were needed.');
            }
            redirect('profile.php#addresses');
        } else {
            $count = db()->prepare('SELECT COUNT(*) FROM tbl_user_address WHERE user_id=?');
            $count->execute([$user['id']]);
            $default = (int)$count->fetchColumn() === 0 ? 1 : 0;
            db()->prepare('INSERT INTO tbl_user_address(user_id,label,recipient_name,phone_number,address,is_default,created_at) VALUES(?,?,?,?,?,?,NOW())')
                ->execute([$user['id'], $label ?: 'Address', $recipient, $phone, $address, $default]);
            flash('success', 'Address saved.');
            redirect('profile.php#addresses');
        }
    } elseif ($action === 'address_default') {
        $id = filter_input(INPUT_POST, 'address_id', FILTER_VALIDATE_INT);
        if ($id) {
            $check = db()->prepare('SELECT id FROM tbl_user_address WHERE id=? AND user_id=?');
            $check->execute([$id, $user['id']]);
            if ($check->fetchColumn()) {
                db()->beginTransaction();
                db()->prepare('UPDATE tbl_user_address SET is_default=0 WHERE user_id=?')->execute([$user['id']]);
                db()->prepare('UPDATE tbl_user_address SET is_default=1 WHERE id=? AND user_id=?')->execute([$id, $user['id']]);
                db()->commit();
                flash('success', 'Default address updated.');
            }
        }
        redirect('profile.php#addresses');
    } elseif ($action === 'address_delete') {
        $id = filter_input(INPUT_POST, 'address_id', FILTER_VALIDATE_INT);
        if ($id) {
            $stmt = db()->prepare('SELECT is_default FROM tbl_user_address WHERE id=? AND user_id=?');
            $stmt->execute([$id, $user['id']]);
            $isDefault = $stmt->fetchColumn();
            if ($isDefault) {
                flash('warning', 'Set another address as default before deleting this one.');
            } else {
                db()->prepare('DELETE FROM tbl_user_address WHERE id=? AND user_id=?')->execute([$id, $user['id']]);
                flash('success', 'Address deleted.');
            }
        }
        redirect('profile.php#addresses');
    }
}

$user = auth_user();
$stmt = db()->prepare("SELECT COUNT(*) total_orders, COALESCE(SUM(CASE WHEN status<>'Cancelled' THEN total ELSE 0 END),0) total_spent, COALESCE(SUM(status='Delivered'),0) delivered_orders FROM tbl_order WHERE user_id=?");
$stmt->execute([$user['id']]);
$stats = $stmt->fetch();

$stmt = db()->prepare('SELECT * FROM tbl_user_address WHERE user_id=? ORDER BY is_default DESC,id DESC');
$stmt->execute([$user['id']]);
$addresses = $stmt->fetchAll();

$wishlistTotal = wishlist_count();
$addressTotal = count($addresses);
$profileFields = [$user['full_name'], $user['user_name'], $user['email'], $user['phone_number'], $user['address']];
$completed = 0;
foreach ($profileFields as $value) if (trim((string)$value) !== '') $completed++;
if (!empty($user['image_name']) && $user['image_name'] !== 'default_profile.webp') $completed++;
$profileCompletion = (int)round(($completed / 6) * 100);
$memberSince = !empty($user['created_at']) ? date('M Y', strtotime($user['created_at'])) : 'Member';

$pageTitle = 'My Profile — ' . APP_NAME;
include __DIR__ . '/partials-font/menu.php';
?>
<section class="profile-cover">
    <div class="container">
        <div class="profile-cover-inner">
            <div><span class="eyebrow">Customer account</span>
                <h1>My Profile</h1>
                <p>Manage your personal information, delivery addresses and account security from one place.</p>
            </div>
            <div class="profile-cover-actions"><a class="btn btn-light" href="<?= e(url('my_order.php')) ?>">My orders</a><a class="btn btn-primary" href="<?= e(url('foods.php')) ?>">Order food</a></div>
        </div>
    </div>
</section>

<section class="section profile-section">
    <div class="container">
        <div class="profile-layout">
            <aside class="profile-sidebar">
                <div class="panel profile-summary-card">
                    <div class="avatar-shell"><img class="avatar avatar-lg" id="profile-preview" src="<?= e(user_image($user['image_name'])) ?>" alt="<?= e($user['full_name']) ?>"><span class="avatar-status" title="Active account"></span></div>
                    <h2><?= e($user['full_name']) ?></h2>
                    <p class="profile-handle">@<?= e($user['user_name']) ?></p>
                    <span class="status status-success">Active customer</span>
                    <div class="profile-meta-list">
                        <div><span>✉</span>
                            <div><small>Email</small><strong><?= e($user['email']) ?></strong></div>
                        </div>
                        <div><span>☎</span>
                            <div><small>Phone</small><strong><?= e($user['phone_number']) ?></strong></div>
                        </div>
                        <div><span>◷</span>
                            <div><small>Member since</small><strong><?= e($memberSince) ?></strong></div>
                        </div>
                    </div>
                    <div class="completion-block">
                        <div class="completion-head"><span>Profile completion</span><strong><?= $profileCompletion ?>%</strong></div>
                        <div class="completion-track"><span style="width:<?= $profileCompletion ?>%"></span></div>
                    </div>
                </div>

                <nav class="profile-nav panel">
                    <a href="#overview">Overview <span>→</span></a>
                    <a href="#account">Personal information <span>→</span></a>
                    <a href="#addresses">Delivery addresses <span>→</span></a>
                    <a href="#security">Password & security <span>→</span></a>
                </nav>
            </aside>

            <main class="profile-content">
                <section id="overview" class="profile-stats-grid">
                    <div class="stat-card profile-stat"><span class="profile-stat-icon">🧾</span>
                        <div><strong><?= (int)$stats['total_orders'] ?></strong><small>Total orders</small></div>
                    </div>
                    <div class="stat-card profile-stat"><span class="profile-stat-icon">✓</span>
                        <div><strong><?= (int)$stats['delivered_orders'] ?></strong><small>Delivered</small></div>
                    </div>
                    <div class="stat-card profile-stat"><span class="profile-stat-icon">♥</span>
                        <div><strong><?= $wishlistTotal ?></strong><small>Wishlist</small></div>
                    </div>
                    <div class="stat-card profile-stat"><span class="profile-stat-icon">৳</span>
                        <div><strong><?= e(money($stats['total_spent'])) ?></strong><small>Order value</small></div>
                    </div>
                </section>

                <section class="panel profile-panel" id="account">
                    <div class="profile-section-head">
                        <div><span class="section-kicker">Account details</span>
                            <h2>Personal information</h2>
                            <p>Your current account and contact information.</p>
                        </div>
                        <button class="btn btn-primary btn-sm" type="button" data-profile-edit-toggle aria-expanded="false">✎ Edit profile</button>
                    </div>

                    <div class="profile-view" data-profile-view>
                        <div class="profile-view-photo">
                            <img src="<?= e(user_image($user['image_name'])) ?>" alt="<?= e($user['full_name']) ?>">
                            <div><strong><?= e($user['full_name']) ?></strong><span>@<?= e($user['user_name']) ?></span></div>
                        </div>
                        <div class="profile-info-grid">
                            <div class="profile-info-item"><span class="profile-info-icon">👤</span>
                                <div><small>Full name</small><strong><?= e($user['full_name']) ?></strong></div>
                            </div>
                            <div class="profile-info-item"><span class="profile-info-icon">@</span>
                                <div><small>Username</small><strong><?= e($user['user_name']) ?></strong></div>
                            </div>
                            <div class="profile-info-item"><span class="profile-info-icon">✉</span>
                                <div><small>Email address</small><strong><?= e($user['email']) ?></strong></div>
                            </div>
                            <div class="profile-info-item"><span class="profile-info-icon">☎</span>
                                <div><small>Phone number</small><strong><?= e($user['phone_number']) ?></strong></div>
                            </div>
                            <div class="profile-info-item profile-info-address"><span class="profile-info-icon">⌂</span>
                                <div><small>Primary address</small><strong><?= e($user['address']) ?></strong></div>
                            </div>
                        </div>
                    </div>

                    <div class="profile-edit-wrap" data-profile-edit hidden>
                        <div class="edit-mode-banner">
                            <div><strong>Edit profile</strong><span>Update only the information you want to change.</span></div><button class="btn btn-light btn-sm" type="button" data-profile-edit-cancel>Cancel</button>
                        </div>
                        <form method="post" enctype="multipart/form-data"><?= csrf_field() ?><input type="hidden" name="action" value="profile">
                            <div class="profile-photo-editor">
                                <img src="<?= e(user_image($user['image_name'])) ?>" alt="" data-secondary-preview>
                                <div><strong>Profile photo</strong>
                                    <p class="muted">JPG, PNG or WEBP. Maximum 3 MB.</p><label class="btn btn-light btn-sm" for="profile-image-input">Choose new photo</label><input id="profile-image-input" class="visually-hidden" type="file" name="image" accept="image/jpeg,image/png,image/webp" data-image-input data-preview-target="#profile-preview">
                                </div>
                            </div>
                            <div class="form-grid profile-form-grid">
                                <div class="field"><label>Username</label><input class="input" name="user_name" value="<?= e($user['user_name']) ?>" required><small>Letters, numbers and underscore only.</small></div>
                                <div class="field"><label>Full name</label><input class="input" name="full_name" value="<?= e($user['full_name']) ?>" required></div>
                                <div class="field"><label>Email</label><input class="input" type="email" name="email" value="<?= e($user['email']) ?>" required></div>
                                <div class="field"><label>Phone</label><input class="input" name="phone_number" value="<?= e($user['phone_number']) ?>" required></div>
                                <div class="field full"><label>Primary address</label><textarea name="address" rows="3" required><?= e($user['address']) ?></textarea><small>This is used as your fallback checkout address.</small></div>
                            </div>
                            <div class="form-actions"><button class="btn btn-primary">Save changes</button><button class="btn btn-light" type="button" data-profile-edit-cancel>Cancel</button></div>
                        </form>
                        <?php if (!empty($user['image_name']) && $user['image_name'] !== 'default_profile.webp'): ?>
                            <form method="post" class="profile-remove-photo"><?= csrf_field() ?><input type="hidden" name="action" value="remove_image"><button class="text-button danger" data-confirm="Remove your current profile photo?">Remove current photo</button></form>
                        <?php endif; ?>
                    </div>
                </section>

                <section class="panel profile-panel" id="addresses">
                    <div class="profile-section-head">
                        <div><span class="section-kicker">Delivery</span>
                            <h2>Saved addresses</h2>
                            <p>Save home, office or other delivery locations and choose a default.</p>
                        </div><span class="count-chip"><?= $addressTotal ?> saved</span>
                    </div>

                    <div class="address-grid">
                        <?php if (!$addresses): ?><div class="empty compact-empty">No saved addresses yet. Add your first one below.</div><?php endif; ?>
                        <?php foreach ($addresses as $a): ?>
                            <article class="address-card address-card-v2">
                                <div class="address-top">
                                    <div><span class="address-icon">⌂</span><strong><?= e($a['label']) ?></strong></div><?php if ($a['is_default']): ?><span class="default-tag">Default</span><?php endif; ?>
                                </div>
                                <div class="address-person"><?= e($a['recipient_name']) ?> · <?= e($a['phone_number']) ?></div>
                                <p><?= e($a['address']) ?></p>
                                <div class="address-actions">
                                    <?php if (!$a['is_default']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="address_default"><input type="hidden" name="address_id" value="<?= (int)$a['id'] ?>"><button class="btn btn-light btn-sm">Make default</button></form><?php endif; ?>
                                    <button class="btn btn-light btn-sm" type="button" data-address-edit="address-edit-<?= (int)$a['id'] ?>">Edit</button>
                                    <?php if (!$a['is_default']): ?><form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="address_delete"><input type="hidden" name="address_id" value="<?= (int)$a['id'] ?>"><button class="btn btn-danger btn-sm" data-confirm="Delete this saved address?">Delete</button></form><?php endif; ?>
                                </div>
                                <div class="address-edit-panel" id="address-edit-<?= (int)$a['id'] ?>" hidden>
                                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="address_update"><input type="hidden" name="address_id" value="<?= (int)$a['id'] ?>">
                                        <div class="form-grid">
                                            <div class="field"><label>Label</label><input class="input" name="label" value="<?= e($a['label']) ?>" required></div>
                                            <div class="field"><label>Recipient</label><input class="input" name="recipient_name" value="<?= e($a['recipient_name']) ?>" required></div>
                                            <div class="field"><label>Phone</label><input class="input" name="address_phone" value="<?= e($a['phone_number']) ?>" required></div>
                                            <div class="field full"><label>Address</label><textarea name="saved_address" rows="3" required><?= e($a['address']) ?></textarea></div>
                                        </div>
                                        <div class="form-actions"><button class="btn btn-primary btn-sm">Save address</button><button class="btn btn-light btn-sm" type="button" data-address-edit="address-edit-<?= (int)$a['id'] ?>">Close</button></div>
                                    </form>
                                </div>
                            </article>
                        <?php endforeach; ?>
                    </div>

                    <div class="add-address-box">
                        <div><span class="section-kicker">New location</span>
                            <h3>Add another address</h3>
                        </div>
                        <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="address_add">
                            <div class="form-grid">
                                <div class="field"><label>Label</label><input class="input" name="label" placeholder="Home, Office…"></div>
                                <div class="field"><label>Recipient</label><input class="input" name="recipient_name" value="<?= e($user['full_name']) ?>" required></div>
                                <div class="field"><label>Phone</label><input class="input" name="address_phone" value="<?= e($user['phone_number']) ?>" required></div>
                                <div class="field full"><label>Address</label><textarea name="saved_address" rows="3" required placeholder="House, road, area, city"></textarea></div>
                            </div>
                            <div class="form-actions"><button class="btn btn-light">Save new address</button></div>
                        </form>
                    </div>
                </section>

                <section class="panel profile-panel" id="security">
                    <div class="profile-section-head">
                        <div><span class="section-kicker">Security</span>
                            <h2>Change password</h2>
                            <p>Use at least 8 characters and avoid reusing an old password.</p>
                        </div><span class="security-badge">🔒 Protected</span>
                    </div>
                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="password">
                        <div class="form-grid">
                            <div class="field full"><label>Current password</label>
                                <div class="password-field"><input class="input" type="password" name="current_password" required data-password-input><button type="button" data-toggle-password>Show</button></div>
                            </div>
                            <div class="field"><label>New password</label>
                                <div class="password-field"><input class="input" type="password" name="new_password" minlength="8" required data-password-input><button type="button" data-toggle-password>Show</button></div>
                            </div>
                            <div class="field"><label>Confirm password</label>
                                <div class="password-field"><input class="input" type="password" name="confirm_password" minlength="8" required data-password-input><button type="button" data-toggle-password>Show</button></div>
                            </div>
                        </div>
                        <div class="form-actions"><button class="btn btn-secondary">Update password</button></div>
                    </form>
                </section>
            </main>
        </div>
    </div>
</section>
<?php include __DIR__ . '/partials-font/footer.php'; ?>