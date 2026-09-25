<?php
require_once __DIR__ . '/config/constants.php';
$u = require_login();
if (!is_post()) redirect('foods.php');
verify_csrf();
$id = filter_input(INPUT_POST, 'food_id', FILTER_VALIDATE_INT);
$qty = filter_input(INPUT_POST, 'quantity', FILTER_VALIDATE_INT) ?: 1;
$qty = max(1, min(50, $qty));
if (!$id) {
    flash('error', 'Invalid food selection.');
    redirect('foods.php');
}
$s = db()->prepare("SELECT id,stock_qty FROM tbl_food WHERE id=? AND active='Yes' LIMIT 1");
$s->execute([$id]);
$f = $s->fetch();
if (!$f || (int)$f['stock_qty'] < 1) {
    flash('error', 'This food is currently unavailable.');
    redirect('foods.php');
}
$s = db()->prepare('SELECT quantity FROM tbl_cart WHERE user_id=? AND food_id=?');
$s->execute([$u['id'], $id]);
$existing = (int)($s->fetchColumn() ?: 0);
$new = min((int)$f['stock_qty'], $existing + $qty);
db()->prepare('INSERT INTO tbl_cart(user_id,food_id,quantity,created_at) VALUES(?,?,?,NOW()) ON DUPLICATE KEY UPDATE quantity=VALUES(quantity)')->execute([$u['id'], $id, $new]);
flash('success', 'Food added to your cart.');
safe_back('cart.php');
