<?php
require_once __DIR__ . '/../config/constants.php';
$user = auth_user();
$current = basename($_SERVER['PHP_SELF'] ?? '');
?>
<!DOCTYPE html>
<html lang="en">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width,initial-scale=1">
    <title><?= e($pageTitle ?? APP_NAME) ?></title>
    <meta name="description" content="Fresh meals, secure ordering and fast delivery from Velora Food.">
    <link rel="stylesheet" href="<?= e(url('css/v3.css')) ?>">
</head>

<body>
    <header class="site-header">
        <div class="container nav-wrap"><a class="brand" href="<?= e(url()) ?>" aria-label="Velora Food home"><img src="<?= e(url('images/velora.png')) ?>" alt="Velora Food"></a><button class="nav-toggle" data-nav-toggle aria-label="Toggle navigation">☰</button>
            <nav class="nav" data-nav>
                <a class="<?= $current === 'index.php' ? 'active' : '' ?>" href="<?= e(url()) ?>">Home</a><a href="<?= e(url('foods.php')) ?>">Menu</a><a href="<?= e(url('categories.php')) ?>">Categories</a>
                <?php if ($user): ?><a href="<?= e(url('wishlist.php')) ?>">Wishlist <span class="mini-badge"><?= wishlist_count() ?></span></a><a href="<?= e(url('my_order.php')) ?>">Orders</a><a href="<?= e(url('profile.php')) ?>">Profile</a><a class="cart-pill" href="<?= e(url('cart.php')) ?>">Cart <span class="badge"><?= cart_count() ?></span></a><a href="<?= e(url('logout.php')) ?>">Logout</a><?php else: ?><a href="<?= e(url('login.php')) ?>">Login</a><a class="btn btn-primary btn-sm" href="<?= e(url('register.php')) ?>">Create account</a><?php endif; ?>
            </nav>
        </div>
    </header><?php render_flashes(); ?>