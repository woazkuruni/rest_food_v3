<?php require_once __DIR__ . '/config/constants.php';
$q = trim((string)($_GET['search'] ?? $_GET['q'] ?? ''));
redirect('foods.php?q=' . urlencode($q));
