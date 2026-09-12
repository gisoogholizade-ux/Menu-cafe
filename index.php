<?php
$config = require __DIR__ . '/config.php';
$pdo = require __DIR__ . '/lib/db.php';
$categories = $pdo->query('SELECT * FROM categories WHERE active=1 ORDER BY sort_order, id')->fetchAll();
$products = $pdo->query('SELECT p.*, c.name AS category_name FROM products p LEFT JOIN categories c ON c.id=p.category_id ORDER BY p.featured DESC, p.id DESC')->fetchAll();

function faNum($value): string {
    return strtr((string)$value, ['0'=>'۰','1'=>'۱','2'=>'۲','3'=>'۳','4'=>'۴','5'=>'۵','6'=>'۶','7'=>'۷','8'=>'۸','9'=>'۹']);
}
function priceFa($value): string {
    return faNum(number_format((int)$value));
}
?>
<!doctype html>
<html lang="fa" dir="rtl">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover">
  <meta name="theme-color" content="#080808">
  <meta name="description" content="منوی دیجیتال <?= htmlspecialchars($config['app_name']) ?>">
  <title><?= htmlspecialchars($config['app_name']) ?> | منوی دیجیتال</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="assets/style.css?v=1">
</head>
<body>
  <div class="ambient ambient-one"></div><div class="ambient ambient-two"></div>
  <main class="app-shell">
    <header class="cafe-header">
      <div class="brand-row">
        <div class="logo-mark" aria-hidden="true"><span></span></div>
        <div class="brand-copy">
          <p class="eyebrow">DIGITAL MENU</p>
          <h1><?= htmlspecialchars($config['app_name']) ?></h1>
          <p class="tagline"><?= htmlspecialchars($config['tagline']) ?></p>
        </div>
        <div class="status-pill"><i></i><span>باز هستیم</span></div>
      </div>
    </header>

    <section class="search-wrap" aria-label="جست‌وجوی منو">
      <svg viewBox="0 0 24 24" aria-hidden="true"><path d="m21 21-4.35-4.35m2.35-5.65A8 8 0 1 1 3 11a8 8 0 0 1 16 0Z"/></svg>
      <input id="searchInput" type="search" inputmode="search" autocomplete="off" placeholder="دنبال چی می‌گردی؟" aria-label="جست‌وجوی آیتم‌های منو">
      <button id="clearSearch" class="clear-search" type="button" aria-label="پاک کردن جست‌وجو">×</button>
    </section>

    <nav class="category-sticky" aria-label="دسته‌بندی‌ها">
      <div class="category-scroll" id="categoryBar">
        <button class="category-chip active" data-category="all" type="button">همه</button>
        <?php foreach ($categories as $cat): ?>
          <button class="category-chip" data-category="<?= (int)$cat['id'] ?>" type="button"><?= htmlspecialchars($cat['name']) ?></button>
        <?php endforeach; ?>
      </div>
    </nav>

    <section class="menu-head">
      <div><p>منوی امروز</p><h2>چی میل داری؟</h2></div>
      <span id="resultCount"><?= faNum(count($products)) ?> آیتم</span>
    </section>

    <section class="menu-grid" id="menuGrid" aria-live="polite">
      <?php foreach ($products as $p):
        $tags = array_values(array_filter(array_map('trim', explode(',', (string)$p['tags'])))); ?>
        <article class="menu-card <?= !$p['available'] ? 'is-unavailable' : '' ?>" tabindex="0"
          data-id="<?= (int)$p['id'] ?>" data-category="<?= (int)$p['category_id'] ?>"
          data-name="<?= htmlspecialchars(mb_strtolower($p['name_fa'].' '.$p['name_en'])) ?>"
          data-json='<?= htmlspecialchars(json_encode([
              'nameFa'=>$p['name_fa'],'nameEn'=>$p['name_en'],'description'=>$p['description'],
              'price'=>priceFa($p['price']).' '.$config['currency'],'image'=>$p['image_url'],
              'available'=>(bool)$p['available'],'tags'=>$tags,'category'=>$p['category_name']
          ], JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8') ?>'>
          <div class="product-image-wrap">
            <?php if ($p['image_url']): ?>
              <img src="<?= htmlspecialchars($p['image_url']) ?>" alt="<?= htmlspecialchars($p['name_fa']) ?>" loading="lazy">
            <?php else: ?>
              <div class="image-fallback"><span class="cloud-line"></span><b>赤</b></div>
            <?php endif; ?>
            <?php if (!$p['available']): ?><span class="stock-badge">فعلاً موجود نیست</span><?php endif; ?>
            <?php if ($p['featured'] && $p['available']): ?><span class="featured-dot">ویژه</span><?php endif; ?>
          </div>
          <div class="product-info">
            <div class="product-title-row">
              <div><h3><?= htmlspecialchars($p['name_fa']) ?></h3><?php if($p['name_en']): ?><p><?= htmlspecialchars($p['name_en']) ?></p><?php endif; ?></div>
              <strong><?= priceFa($p['price']) ?><small><?= htmlspecialchars($config['currency']) ?></small></strong>
            </div>
            <p class="description"><?= htmlspecialchars($p['description']) ?></p>
            <?php if ($tags): ?><div class="tag-row"><?php foreach($tags as $tag): ?><span><?= htmlspecialchars($tag) ?></span><?php endforeach; ?></div><?php endif; ?>
          </div>
        </article>
      <?php endforeach; ?>
    </section>

    <div class="empty-state" id="emptyState" hidden>
      <div class="empty-icon">☾</div><h3>چیزی با این اسم پیدا نکردیم.</h3><p>یه عبارت دیگه امتحان کن یا دسته‌بندی رو عوض کن.</p>
    </div>

    <footer class="site-footer"><span class="mini-cloud"></span><p>با عشق برای لحظه‌های خوب شما</p></footer>
  </main>

  <div class="sheet-backdrop" id="sheetBackdrop" hidden></div>
  <section class="product-sheet" id="productSheet" role="dialog" aria-modal="true" aria-labelledby="sheetTitle" hidden>
    <div class="sheet-handle"></div>
    <button class="sheet-close" id="sheetClose" type="button" aria-label="بستن">×</button>
    <div class="sheet-media" id="sheetMedia"></div>
    <div class="sheet-body">
      <span class="sheet-category" id="sheetCategory"></span>
      <h2 id="sheetTitle"></h2><p class="sheet-en" id="sheetEn"></p>
      <p class="sheet-description" id="sheetDescription"></p>
      <div class="sheet-tags" id="sheetTags"></div>
      <div class="sheet-bottom"><strong id="sheetPrice"></strong><span id="sheetAvailability"></span></div>
    </div>
  </section>

  <script src="assets/app.js?v=1"></script>
</body>
</html>
