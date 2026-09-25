<?php
require_once __DIR__ . '/config/constants.php';
$q = trim((string)($_GET['q'] ?? ''));
$category = filter_input(INPUT_GET, 'category', FILTER_VALIDATE_INT) ?: 0;
$min = filter_input(INPUT_GET, 'min', FILTER_VALIDATE_FLOAT);
$max = filter_input(INPUT_GET, 'max', FILTER_VALIDATE_FLOAT);
$sort = (string)($_GET['sort'] ?? 'newest');
$page = max(1, (int)($_GET['page'] ?? 1));
$perPage = 8;
$where = ["f.active='Yes'"];
$params = [];
if ($q !== '') {
    $where[] = '(f.title LIKE ? OR f.description LIKE ?)';
    $params[] = '%' . $q . '%';
    $params[] = '%' . $q . '%';
}
if ($category) {
    $where[] = 'f.category_id=?';
    $params[] = $category;
}
if ($min !== false && $min !== null) {
    $where[] = 'f.price>=?';
    $params[] = $min;
}
if ($max !== false && $max !== null) {
    $where[] = 'f.price<=?';
    $params[] = $max;
}
$order = match ($sort) {
    'price_low' => 'f.price ASC',
    'price_high' => 'f.price DESC',
    'rating' => 'rating DESC, review_count DESC',
    default => 'f.id DESC'
};
$whereSql = implode(' AND ', $where);
$count = db()->prepare("SELECT COUNT(*) FROM tbl_food f WHERE $whereSql");
$count->execute($params);
$total = (int)$count->fetchColumn();
$pages = max(1, (int)ceil($total / $perPage));
$page = min($page, $pages);
$offset = ($page - 1) * $perPage;
$sql = "SELECT f.*,c.title category_title,COALESCE(AVG(r.rating),0) rating,COUNT(r.id) review_count FROM tbl_food f JOIN tbl_category c ON c.id=f.category_id LEFT JOIN tbl_review r ON r.food_id=f.id AND r.status='Published' WHERE $whereSql GROUP BY f.id ORDER BY $order LIMIT $perPage OFFSET $offset";
$stmt = db()->prepare($sql);
$stmt->execute($params);
$foods = $stmt->fetchAll();
$categories = db()->query("SELECT id,title FROM tbl_category WHERE active='Yes' ORDER BY title")->fetchAll();
$pageTitle = 'Food Menu — ' . APP_NAME;
include __DIR__ . '/partials-font/menu.php'; ?>
<section class="page-hero">
    <div class="container">
        <h1>Food Menu</h1>
        <p>Search, filter, sort and find exactly what you want.</p>
    </div>
</section>
<section class="section">
    <div class="container">
        <form class="panel filter-bar" method="get">
            <div class="field filter-search"><label>Search</label><input class="input" name="q" value="<?= e($q) ?>" placeholder="Food name or description"></div>
            <div class="field"><label>Category</label><select name="category">
                    <option value="">All</option><?php foreach ($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= $category === (int)$c['id'] ? 'selected' : '' ?>><?= e($c['title']) ?></option><?php endforeach; ?>
                </select></div>
            <div class="field"><label>Min price</label><input class="input" type="number" name="min" min="0" value="<?= e($_GET['min'] ?? '') ?>"></div>
            <div class="field"><label>Max price</label><input class="input" type="number" name="max" min="0" value="<?= e($_GET['max'] ?? '') ?>"></div>
            <div class="field"><label>Sort</label><select name="sort">
                    <option value="newest" <?= $sort === 'newest' ? 'selected' : '' ?>>Newest</option>
                    <option value="rating" <?= $sort === 'rating' ? 'selected' : '' ?>>Top rated</option>
                    <option value="price_low" <?= $sort === 'price_low' ? 'selected' : '' ?>>Price low → high</option>
                    <option value="price_high" <?= $sort === 'price_high' ? 'selected' : '' ?>>Price high → low</option>
                </select></div><button class="btn btn-primary">Apply</button>
        </form><br>
        <div class="section-head">
            <div>
                <h2><?= $total ?> item<?= $total === 1 ? '' : 's' ?></h2>
                <p>Only currently active menu items are shown.</p>
            </div><?php if ($q || $category || isset($_GET['min']) || isset($_GET['max'])): ?><a class="btn btn-light" href="<?= e(url('foods.php')) ?>">Clear filters</a><?php endif; ?>
        </div>
        <div class="grid grid-4"><?php foreach ($foods as $f): ?><article class="food-card">
                    <div class="food-image-wrap"><a href="<?= e(url('food-details.php?id=' . $f['id'])) ?>"><img src="<?= e(food_image($f['image_name'])) ?>" alt="<?= e($f['title']) ?>"></a><span class="stock-badge <?= (int)$f['stock_qty'] === 0 ? 'out' : ((int)$f['stock_qty'] <= 5 ? 'low' : '') ?>"><?= (int)$f['stock_qty'] > 0 ? (int)$f['stock_qty'] . ' in stock' : 'Sold out' ?></span><?php if (auth_user()): ?><form action="<?= e(url('toggle-wishlist.php')) ?>" method="post"><?= csrf_field() ?><input type="hidden" name="food_id" value="<?= (int)$f['id'] ?>"><button class="wish-btn <?= is_wishlisted((int)$f['id']) ? 'active' : '' ?>">♥</button></form><?php endif; ?></div>
                    <div class="food-card-body"><span class="chip muted"><?= e($f['category_title']) ?></span>
                        <div class="food-meta">
                            <h3><a href="<?= e(url('food-details.php?id=' . $f['id'])) ?>"><?= e($f['title']) ?></a></h3><span class="rating">★ <?= number_format((float)$f['rating'], 1) ?></span>
                        </div>
                        <p class="muted"><?= e(mb_strimwidth((string)$f['description'], 0, 82, '…')) ?></p>
                        <div class="price"><?= e(money($f['price'])) ?></div>
                        <div class="food-actions">
                            <form action="<?= e(url('add-cart.php')) ?>" method="post"><?= csrf_field() ?><input type="hidden" name="food_id" value="<?= (int)$f['id'] ?>"><button class="btn btn-primary" <?= (int)$f['stock_qty'] < 1 ? 'disabled' : '' ?>>Add to cart</button></form><a class="btn btn-light" href="<?= e(url('food-details.php?id=' . $f['id'])) ?>">Details</a>
                        </div>
                    </div>
                </article><?php endforeach; ?></div><?php if (!$foods): ?><div class="empty panel">
                <h2>No matching food found</h2>
                <p>Try changing the search or filters.</p>
            </div><?php endif; ?><?php if ($pages > 1): ?><div class="pagination"><?php for ($i = 1; $i <= $pages; $i++): $qs = $_GET;
                                                                                    $qs['page'] = $i; ?><a class="<?= $i === $page ? 'current' : '' ?>" href="?<?= e(http_build_query($qs)) ?>"><?= $i ?></a><?php endfor; ?></div><?php endif; ?>
    </div>
</section><?php include __DIR__ . '/partials-font/footer.php'; ?>