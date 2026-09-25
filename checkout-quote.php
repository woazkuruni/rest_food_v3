<?php
require_once __DIR__ . '/config/constants.php';

header('Content-Type: application/json; charset=utf-8');

$user = auth_user();
if (!$user) {
    http_response_code(401);
    echo json_encode(['ok' => false, 'message' => 'Please log in again.']);
    exit;
}

if (!is_post()) {
    http_response_code(405);
    echo json_encode(['ok' => false, 'message' => 'Method not allowed.']);
    exit;
}

$token = $_POST['csrf_token'] ?? '';
if (!is_string($token) || !hash_equals($_SESSION['csrf_token'] ?? '', $token)) {
    http_response_code(419);
    echo json_encode(['ok' => false, 'message' => 'Your checkout session expired. Refresh the page and try again.']);
    exit;
}

try {
    $foodId = filter_input(INPUT_POST, 'food_id', FILTER_VALIDATE_INT);

    if ($foodId) {
        $stmt = db()->prepare("SELECT id AS food_id,title,price,stock_qty,1 AS quantity FROM tbl_food WHERE id=? AND active='Yes' AND stock_qty>0 LIMIT 1");
        $stmt->execute([$foodId]);
        $row = $stmt->fetch();
        $items = $row ? [$row] : [];
    } else {
        $stmt = db()->prepare("SELECT f.id AS food_id,f.title,f.price,f.stock_qty,c.quantity FROM tbl_cart c JOIN tbl_food f ON f.id=c.food_id WHERE c.user_id=? AND f.active='Yes' AND f.stock_qty>0 ORDER BY c.id");
        $stmt->execute([$user['id']]);
        $items = $stmt->fetchAll();
    }

    if (!$items) {
        throw new RuntimeException('There is nothing available to checkout.');
    }

    $subtotal = 0.0;
    foreach ($items as $item) {
        if ((int)$item['quantity'] > (int)$item['stock_qty']) {
            throw new RuntimeException($item['title'] . ' does not have enough stock.');
        }
        $subtotal += (float)$item['price'] * (int)$item['quantity'];
    }

    $delivery = delivery_fee($subtotal);
    $couponCode = strtoupper(trim((string)($_POST['coupon_code'] ?? '')));
    $discount = 0.0;
    $couponMessage = 'No coupon applied.';

    if ($couponCode !== '') {
        [$coupon, $couponError] = validate_coupon($couponCode, $subtotal, (int)$user['id']);
        if (!$coupon) {
            echo json_encode([
                'ok' => false,
                'message' => $couponError,
                'subtotal' => round($subtotal, 2),
                'delivery' => round($delivery, 2),
                'discount' => 0,
                'total' => round($subtotal + $delivery, 2),
                'formatted' => [
                    'subtotal' => money($subtotal),
                    'delivery' => $delivery > 0 ? money($delivery) : 'FREE',
                    'discount' => money(0),
                    'total' => money($subtotal + $delivery),
                ],
            ]);
            exit;
        }
        $discount = coupon_discount($coupon, $subtotal);
        $couponMessage = $coupon['code'] . ' applied successfully.';
        $couponCode = (string)$coupon['code'];
    }

    $total = max(0, $subtotal - $discount + $delivery);

    echo json_encode([
        'ok' => true,
        'message' => $couponMessage,
        'coupon_code' => $couponCode,
        'subtotal' => round($subtotal, 2),
        'delivery' => round($delivery, 2),
        'discount' => round($discount, 2),
        'total' => round($total, 2),
        'formatted' => [
            'subtotal' => money($subtotal),
            'delivery' => $delivery > 0 ? money($delivery) : 'FREE',
            'discount' => $discount > 0 ? '-' . money($discount) : money(0),
            'total' => money($total),
        ],
    ]);
} catch (Throwable $e) {
    http_response_code(422);
    echo json_encode([
        'ok' => false,
        'message' => APP_ENV === 'local' ? $e->getMessage() : 'Could not calculate the checkout total.',
    ]);
}
