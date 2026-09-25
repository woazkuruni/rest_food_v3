<?php
require_once __DIR__ . '/config/constants.php';
$u = require_login();
if (is_post()) {
    verify_csrf();
    $action = (string)($_POST['action'] ?? '');
    if ($action === 'update') {
        $id = filter_input(INPUT_POST, 'cart_id', FILTER_VALIDATE_INT);
        $qty = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT);
        if ($id && $qty && $qty >= 1) {
            $s = db()->prepare('SELECT f.stock_qty FROM tbl_cart c JOIN tbl_food f ON f.id=c.food_id WHERE c.id=? AND c.user_id=?');
            $s->execute([$id, $u['id']]);
            $stock = (int)$s->fetchColumn();
            if ($stock < 1) {
                db()->prepare('DELETE FROM tbl_cart WHERE id=? AND user_id=?')->execute([$id, $u['id']]);
                flash('warning', 'An unavailable item was removed.');
            } else {
                db()->prepare('UPDATE tbl_cart SET quantity=? WHERE id=? AND user_id=?')->execute([min($qty, $stock, 50), $id, $u['id']]);
                flash('success', 'Cart updated.');
            }
        }
    } elseif ($action === 'remove') {
        $id = filter_input(INPUT_POST, 'cart_id', FILTER_VALIDATE_INT);
        if ($id) db()->prepare('DELETE FROM tbl_cart WHERE id=? AND user_id=?')->execute([$id, $u['id']]);
        flash('success', 'Item removed.');
    } elseif ($action === 'clear') {
        db()->prepare('DELETE FROM tbl_cart WHERE user_id=?')->execute([$u['id']]);
        flash('success', 'Cart cleared.');
    }
    redirect('cart.php');
}
$s = db()->prepare("SELECT c.id cart_id,c.quantity,f.id food_id,f.title,f.price,f.image_name,f.stock_qty FROM tbl_cart c JOIN tbl_food f ON f.id=c.food_id WHERE c.user_id=? AND f.active='Yes' ORDER BY c.id DESC");
$s->execute([$u['id']]);
$items = $s->fetchAll();
$subtotal = 0;
foreach ($items as $i) $subtotal += (float)$i['price'] * (int)$i['quantity'];
$delivery = $items ? delivery_fee($subtotal) : 0;
$pageTitle = 'Cart — ' . APP_NAME;
include __DIR__ . '/partials-font/menu.php'; ?>
<section class="page-hero">
    <div class="container">
        <h1>Your Cart</h1>
        <p>Stock and prices are checked again when you place the order.</p>
    </div>
</section>
<section class="section">
    <div class="container"><?php if ($items): ?><div class="table-wrap">
                <table class="table">
                    <thead>
                        <tr>
                            <th>Food</th>
                            <th>Price</th>
                            <th>Quantity</th>
                            <th>Subtotal</th>
                            <th></th>
                        </tr>
                    </thead>
                    <tbody><?php foreach ($items as $i): ?><tr>
                                <td>
                                    <div style="display:flex;align-items:center;gap:12px"><img class="thumb" src="<?= e(food_image($i['image_name'])) ?>" alt="">
                                        <div><strong><?= e($i['title']) ?></strong>
                                            <div class="muted"><?= (int)$i['stock_qty'] ?> available</div>
                                        </div>
                                    </div>
                                </td>
                                <td><?= e(money($i['price'])) ?></td>
                                <td>
                                    <form class="qty-form" method="post"><?= csrf_field() ?><input type="hidden" name="action" value="update"><input type="hidden" name="cart_id" value="<?= (int)$i['cart_id'] ?>"><input class="input" type="number" name="quantity" min="1" max="<?= max(1, (int)$i['stock_qty']) ?>" value="<?= (int)$i['quantity'] ?>"><button class="btn btn-light btn-sm">Update</button></form>
                                </td>
                                <td><strong><?= e(money((float)$i['price'] * (int)$i['quantity'])) ?></strong></td>
                                <td>
                                    <form method="post"><?= csrf_field() ?><input type="hidden" name="action" value="remove"><input type="hidden" name="cart_id" value="<?= (int)$i['cart_id'] ?>"><button class="btn btn-danger btn-sm" data-confirm="Remove this item?">Remove</button></form>
                                </td>
                            </tr><?php endforeach; ?></tbody>
                </table>
            </div><br>
            <div class="panel summary">
                <div class="summary-row"><span>Subtotal</span><strong><?= e(money($subtotal)) ?></strong></div>
                <div class="summary-row"><span>Delivery</span><strong><?= $delivery ? e(money($delivery)) : 'FREE' ?></strong></div>
                <p class="notice">Free delivery from Tk 1,000.</p>
                <div class="summary-row total"><span>Estimated total</span><span><?= e(money($subtotal + $delivery)) ?></span></div><br><a class="btn btn-primary" style="width:100%" href="<?= e(url('order.php')) ?>">Proceed to checkout</a><br><br>
                <form method="post" style="text-align:center"><?= csrf_field() ?><input type="hidden" name="action" value="clear"><button class="btn btn-light btn-sm" data-confirm="Clear your entire cart?">Clear cart</button></form>
            </div><?php else: ?><div class="panel empty">
                <h2>Your cart is empty</h2>
                <p>Add something delicious from the menu.</p><a class="btn btn-primary" href="<?= e(url('foods.php')) ?>">Browse foods</a>
            </div><?php endif; ?>
    </div>
</section><?php include __DIR__ . '/partials-font/footer.php'; ?>