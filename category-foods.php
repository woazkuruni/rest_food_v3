<?php require_once __DIR__ . '/config/constants.php';
$id = filter_input(INPUT_GET, 'category_id', FILTER_VALIDATE_INT) ?: filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
redirect('foods.php' . ($id ? '?category=' . $id : ''));
