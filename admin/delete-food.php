<?php
require_once __DIR__ . '/../config/constants.php';
require_admin();
if (!is_post()) redirect('admin/manage-food.php');
verify_csrf();
$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if (!$id) redirect('admin/manage-food.php');
$stmt = db()->prepare('SELECT image_name FROM tbl_food WHERE id=?');
$stmt->execute([$id]);
$image = $stmt->fetchColumn();
try {
    $stmt = db()->prepare('DELETE FROM tbl_food WHERE id=?');
    $stmt->execute([$id]);
    if ($image) {
        $p = __DIR__ . '/../images/foods/' . basename((string)$image);
        if (is_file($p)) @unlink($p);
    }
    flash('success', 'Food deleted.');
} catch (Throwable $e) {
    flash('error', 'This food is referenced by an existing order and cannot be deleted. Set it inactive instead.');
}
redirect('admin/manage-food.php');
