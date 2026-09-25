<?php
require_once __DIR__ . '/config/constants.php';

$mode = (string)($_GET['mode'] ?? 'success');
$tranId = trim((string)($_POST['tran_id'] ?? ''));
$orderId = 0;
if ($tranId !== '') {
    $stmt = db()->prepare("SELECT id FROM tbl_order WHERE payment_method='sslcommerz' AND payment_reference=? LIMIT 1");
    $stmt->execute([$tranId]);
    $orderId = (int)($stmt->fetchColumn() ?: 0);
}

if (!$orderId) {
    http_response_code(400);
    exit('Order could not be identified.');
}

if (in_array($mode, ['fail', 'cancel'], true)) {
    try {
        restore_order_reservations($orderId, 'Failed', $mode === 'cancel' ? 'Customer cancelled online payment.' : 'Online payment failed.');
    } catch (Throwable $e) {
        http_response_code(500);
        exit('Unable to update failed payment.');
    }
    if ($mode === 'cancel') {
        flash('warning', 'Online payment was cancelled and the order reservation was released.');
    } else {
        flash('error', 'Online payment failed and the order reservation was released.');
    }
    $user = auth_user();
    if ($user) redirect('my_order.php');
    redirect('login.php');
}

$valId = trim((string)($_POST['val_id'] ?? ''));
if ($valId === '') {
    http_response_code(400);
    exit('Missing payment validation ID.');
}

try {
    $validation = sslcommerz_validate($valId);
    $stmt = db()->prepare("SELECT * FROM tbl_order WHERE id=? AND payment_method='sslcommerz' LIMIT 1");
    $stmt->execute([$orderId]);
    $order = $stmt->fetch();
    if (!$order) throw new RuntimeException('Order not found.');

    $status = (string)($validation['status'] ?? '');
    $validStatus = in_array($status, ['VALID', 'VALIDATED'], true);
    $sameTran = hash_equals((string)$order['payment_reference'], (string)($validation['tran_id'] ?? ''));
    $sameAmount = abs((float)$order['total'] - (float)($validation['amount'] ?? -1)) < 0.01;
    $sameCurrency = strtoupper((string)($validation['currency'] ?? '')) === 'BDT';
    if (!$validStatus || !$sameTran || !$sameAmount || !$sameCurrency) {
        throw new RuntimeException('Payment validation mismatch.');
    }

    $paymentStatus = ((int)($validation['risk_level'] ?? 0) === 1) ? 'Submitted' : 'Paid';
    db()->prepare('UPDATE tbl_order SET payment_status=? WHERE id=? AND status<>\'Cancelled\'')->execute([$paymentStatus, $orderId]);

    if ($mode === 'ipn') {
        header('Content-Type: text/plain');
        exit('OK');
    }

    flash($paymentStatus === 'Paid' ? 'success' : 'warning', $paymentStatus === 'Paid' ? 'Online payment verified successfully.' : 'Payment received but flagged for manual verification.');
    $user = auth_user();
    if ($user && (int)$order['user_id'] === (int)$user['id']) redirect('order-details.php?id=' . $orderId);
    redirect('login.php');
} catch (Throwable $e) {
    if ($mode === 'ipn') {
        http_response_code(400);
        exit('INVALID');
    }
    flash('error', APP_ENV === 'local' ? $e->getMessage() : 'Online payment could not be verified.');
    $user = auth_user();
    if ($user) redirect('order-details.php?id=' . $orderId);
    redirect('login.php');
}
