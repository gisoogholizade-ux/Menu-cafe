<?php
session_start();
$config = require __DIR__ . '/../config.php';
$pdo = require __DIR__ . '/../lib/db.php';

function redirectAdmin(string $suffix=''): void { header('Location: index.php' . $suffix); exit; }
function e($v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function csrf(): string { if (empty($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(24)); return $_SESSION['csrf']; }
function verifyCsrf(): void { if (!isset($_POST['csrf']) || !hash_equals($_SESSION['csrf'] ?? '', $_POST['csrf'])) { http_response_code(419); exit('درخواست نامعتبر است. صفحه را تازه کنید.'); } }
function isLoggedIn(): bool { return !empty($_SESSION['admin_id']); }

$error = '';
$notice = $_SESSION['notice'] ?? '';
unset($_SESSION['notice']);

if (isset($_GET['logout'])) {
    session_unset(); session_destroy(); redirectAdmin();
}

if (!isLoggedIn() && $_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'login') {
    verifyCsrf();
    $username = trim($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $stmt = $pdo->prepare('SELECT * FROM admins WHERE username=? AND active=1 LIMIT 1');
    $stmt->execute([$username]);
    $admin = $stmt->fetch();
    if ($admin && password_verify($password, $admin['password_hash'])) {
        session_regenerate_id(true);
        $_SESSION['admin_id'] = $admin['id'];
        $_SESSION['admin_username'] = $admin['username'];
        redirectAdmin();
    }
    $error = 'نام کاربری یا رمز عبور درست نیست.';
}

if (!isLoggedIn()):
?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><title>ورود مدیریت</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="../assets/admin.css"></head><body class="admin-body login-body"><main class="login-card"><div class="admin-logo">赤</div><p class="overline">CAFÉ CONTROL</p><h1>ورود مدیریت منو</h1><p class="muted">برای ویرایش آیتم‌ها، قیمت‌ها و موجودی وارد شوید.</p><?php if($error): ?><div class="alert danger"><?= e($error) ?></div><?php endif; ?><form method="post" class="stack"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="login"><label>نام کاربری<input name="username" autocomplete="username" required placeholder="admin"></label><label>رمز عبور<input name="password" type="password" autocomplete="current-password" required placeholder="••••••••"></label><button class="primary" type="submit">ورود به داشبورد</button></form><a class="back-link" href="../index.php">← مشاهده منوی کافه</a></main></body></html>
<?php exit; endif;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verifyCsrf();
    $action = $_POST['action'] ?? '';
    try {
        if ($action === 'save_product') {
            $id = (int)($_POST['id'] ?? 0);
            $nameFa = trim($_POST['name_fa'] ?? '');
            $nameEn = trim($_POST['name_en'] ?? '');
            $description = trim($_POST['description'] ?? '');
            $price = max(0, (int)preg_replace('/\D+/', '', $_POST['price'] ?? '0'));
            $categoryId = (int)($_POST['category_id'] ?? 0);
            $tags = trim($_POST['tags'] ?? '');
            $available = isset($_POST['available']) ? 1 : 0;
            $featured = isset($_POST['featured']) ? 1 : 0;
            if ($nameFa === '' || $categoryId < 1 || $price < 1) throw new RuntimeException('نام محصول، دسته‌بندی و قیمت الزامی هستند.');

            $imageUrl = trim($_POST['existing_image'] ?? '');
            if (!empty($_FILES['image']['name'])) {
                if ($_FILES['image']['error'] !== UPLOAD_ERR_OK) throw new RuntimeException('آپلود تصویر انجام نشد.');
                $max = ((int)$config['max_upload_mb']) * 1024 * 1024;
                if ($_FILES['image']['size'] > $max) throw new RuntimeException('حجم تصویر بیشتر از حد مجاز است.');
                $finfo = new finfo(FILEINFO_MIME_TYPE);
                $mime = $finfo->file($_FILES['image']['tmp_name']);
                $types = ['image/jpeg'=>'jpg','image/png'=>'png','image/webp'=>'webp'];
                if (!isset($types[$mime])) throw new RuntimeException('فقط JPG، PNG و WEBP مجاز است.');
                $uploadDir = __DIR__ . '/../uploads';
                if (!is_dir($uploadDir)) mkdir($uploadDir, 0775, true);
                $fileName = bin2hex(random_bytes(10)) . '.' . $types[$mime];
                if (!move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . '/' . $fileName)) throw new RuntimeException('ذخیره تصویر انجام نشد.');
                $imageUrl = 'uploads/' . $fileName;
            }
            $now = date('c');
            if ($id > 0) {
                $stmt = $pdo->prepare('UPDATE products SET name_fa=?,name_en=?,description=?,price=?,image_url=?,category_id=?,tags=?,available=?,featured=?,updated_at=? WHERE id=?');
                $stmt->execute([$nameFa,$nameEn,$description,$price,$imageUrl,$categoryId,$tags,$available,$featured,$now,$id]);
                $_SESSION['notice'] = 'محصول با موفقیت ویرایش شد.';
            } else {
                $stmt = $pdo->prepare('INSERT INTO products(name_fa,name_en,description,price,image_url,category_id,tags,available,featured,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
                $stmt->execute([$nameFa,$nameEn,$description,$price,$imageUrl,$categoryId,$tags,$available,$featured,$now,$now]);
                $_SESSION['notice'] = 'محصول جدید اضافه شد.';
            }
            redirectAdmin('#products');
        }
        if ($action === 'delete_product') {
            $id = (int)($_POST['id'] ?? 0);
            $stmt = $pdo->prepare('DELETE FROM products WHERE id=?'); $stmt->execute([$id]);
            $_SESSION['notice'] = 'محصول حذف شد.'; redirectAdmin('#products');
        }
        if ($action === 'toggle_product') {
            $id = (int)($_POST['id'] ?? 0);
            $pdo->prepare('UPDATE products SET available=CASE available WHEN 1 THEN 0 ELSE 1 END, updated_at=? WHERE id=?')->execute([date('c'),$id]);
            $_SESSION['notice'] = 'وضعیت موجودی تغییر کرد.'; redirectAdmin('#products');
        }
        if ($action === 'save_category') {
            $id = (int)($_POST['id'] ?? 0); $name = trim($_POST['name'] ?? ''); $sort = (int)($_POST['sort_order'] ?? 0); $active = isset($_POST['active']) ? 1 : 0;
            if ($name === '') throw new RuntimeException('نام دسته‌بندی الزامی است.');
            $now = date('c');
            if ($id) $pdo->prepare('UPDATE categories SET name=?,sort_order=?,active=?,updated_at=? WHERE id=?')->execute([$name,$sort,$active,$now,$id]);
            else $pdo->prepare('INSERT INTO categories(name,sort_order,active,created_at,updated_at) VALUES(?,?,?,?,?)')->execute([$name,$sort,$active,$now,$now]);
            $_SESSION['notice'] = 'دسته‌بندی ذخیره شد.'; redirectAdmin('#categories');
        }
        if ($action === 'delete_category') {
            $id = (int)($_POST['id'] ?? 0); $pdo->prepare('DELETE FROM categories WHERE id=?')->execute([$id]); $_SESSION['notice'] = 'دسته‌بندی حذف شد.'; redirectAdmin('#categories');
        }
        if ($action === 'change_password') {
            $current = $_POST['current_password'] ?? ''; $new = $_POST['new_password'] ?? '';
            $stmt = $pdo->prepare('SELECT password_hash FROM admins WHERE id=?'); $stmt->execute([$_SESSION['admin_id']]); $row = $stmt->fetch();
            if (!$row || !password_verify($current,$row['password_hash'])) throw new RuntimeException('رمز فعلی اشتباه است.');
            if (strlen($new) < 8) throw new RuntimeException('رمز جدید حداقل ۸ کاراکتر باشد.');
            $pdo->prepare('UPDATE admins SET password_hash=? WHERE id=?')->execute([password_hash($new,PASSWORD_DEFAULT),$_SESSION['admin_id']]);
            $_SESSION['notice'] = 'رمز عبور تغییر کرد.'; redirectAdmin('#security');
        }
    } catch (Throwable $ex) { $error = $ex->getMessage(); }
}

$categories = $pdo->query('SELECT * FROM categories ORDER BY sort_order,id')->fetchAll();
$products = $pdo->query('SELECT p.*,c.name category_name FROM products p LEFT JOIN categories c ON c.id=p.category_id ORDER BY p.id DESC')->fetchAll();
$total = count($products); $available = count(array_filter($products, fn($p)=>(int)$p['available']===1)); $unavailable=$total-$available;
$editProduct = null; if(isset($_GET['edit'])) { $stmt=$pdo->prepare('SELECT * FROM products WHERE id=?');$stmt->execute([(int)$_GET['edit']]);$editProduct=$stmt->fetch() ?: null; }
$scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
$basePath = rtrim(dirname(dirname($_SERVER['SCRIPT_NAME'])), '/\\');
$menuUrl = $config['menu_url'] ?: $scheme . '://' . ($_SERVER['HTTP_HOST'] ?? 'localhost') . $basePath . '/';
?>
<!doctype html><html lang="fa" dir="rtl"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1,viewport-fit=cover"><meta name="theme-color" content="#080808"><title>مدیریت منوی کافه</title><link rel="preconnect" href="https://fonts.googleapis.com"><link rel="preconnect" href="https://fonts.gstatic.com" crossorigin><link href="https://fonts.googleapis.com/css2?family=Vazirmatn:wght@400;500;600;700;800&display=swap" rel="stylesheet"><link rel="stylesheet" href="../assets/admin.css"></head><body class="admin-body">
<div class="admin-shell"><aside class="sidebar"><div class="side-brand"><span>赤</span><div><strong><?= e($config['app_name']) ?></strong><small>پنل مدیریت</small></div></div><nav><a href="#overview">نمای کلی</a><a href="#products">محصولات</a><a href="#product-form">افزودن محصول</a><a href="#categories">دسته‌بندی‌ها</a><a href="#qr">QR منو</a><a href="#security">امنیت</a></nav><div class="side-bottom"><a href="../index.php" target="_blank">مشاهده منو ↗</a><a href="?logout=1">خروج</a></div></aside>
<main class="admin-main"><header class="admin-top"><div><p class="overline">MENU CONTROL</p><h1>مدیریت منوی دیجیتال</h1></div><a class="ghost" href="../index.php" target="_blank">مشاهده منو</a></header>
<?php if($notice): ?><div class="alert success"><?= e($notice) ?></div><?php endif; ?><?php if($error): ?><div class="alert danger"><?= e($error) ?></div><?php endif; ?>
<section id="overview"><div class="stat-grid"><article><small>کل آیتم‌ها</small><strong><?= $total ?></strong></article><article><small>موجود</small><strong><?= $available ?></strong></article><article><small>ناموجود</small><strong><?= $unavailable ?></strong></article><article><small>دسته‌بندی</small><strong><?= count($categories) ?></strong></article></div></section>
<section class="panel" id="product-form"><div class="panel-head"><div><p class="overline">PRODUCT EDITOR</p><h2><?= $editProduct ? 'ویرایش محصول' : 'افزودن محصول' ?></h2></div><?php if($editProduct): ?><a class="ghost" href="index.php#product-form">انصراف</a><?php endif; ?></div><form method="post" enctype="multipart/form-data" class="form-grid"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="save_product"><input type="hidden" name="id" value="<?= (int)($editProduct['id']??0) ?>"><input type="hidden" name="existing_image" value="<?= e($editProduct['image_url']??'') ?>"><label>نام محصول *<input name="name_fa" required value="<?= e($editProduct['name_fa']??'') ?>" placeholder="مثلاً آیس لاته"></label><label>نام انگلیسی<input name="name_en" value="<?= e($editProduct['name_en']??'') ?>" placeholder="Iced Latte" dir="ltr"></label><label>دسته‌بندی *<select name="category_id" required><option value="">انتخاب کنید</option><?php foreach($categories as $c): ?><option value="<?= (int)$c['id'] ?>" <?= (int)($editProduct['category_id']??0)===(int)$c['id']?'selected':'' ?>><?= e($c['name']) ?></option><?php endforeach; ?></select></label><label>قیمت (تومان) *<input name="price" inputmode="numeric" required value="<?= e($editProduct['price']??'') ?>" placeholder="165000"></label><label class="full">توضیحات<textarea name="description" rows="3" placeholder="توضیح کوتاه و خوش‌خوان..."><?= e($editProduct['description']??'') ?></textarea></label><label>برچسب‌ها<input name="tags" value="<?= e($editProduct['tags']??'') ?>" placeholder="پرفروش, جدید"></label><label>تصویر<input id="imageInput" type="file" name="image" accept="image/jpeg,image/png,image/webp"><small>حداکثر <?= (int)$config['max_upload_mb'] ?>MB</small></label><div class="image-preview" id="imagePreview"><?php if(!empty($editProduct['image_url'])): ?><img src="../<?= e($editProduct['image_url']) ?>" alt="پیش‌نمایش"><?php else: ?><span>پیش‌نمایش تصویر</span><?php endif; ?></div><div class="checks"><label><input type="checkbox" name="available" <?= !isset($editProduct['available']) || $editProduct['available']?'checked':'' ?>> موجود</label><label><input type="checkbox" name="featured" <?= !empty($editProduct['featured'])?'checked':'' ?>> ویژه</label></div><div class="form-actions full"><button class="primary" type="submit">ذخیره تغییرات</button><?php if($editProduct): ?><a class="ghost" href="index.php#products">انصراف</a><?php endif; ?></div></form></section>
<section class="panel" id="products"><div class="panel-head"><div><p class="overline">MENU ITEMS</p><h2>محصولات</h2></div><a class="primary small" href="#product-form">+ محصول جدید</a></div><div class="table-wrap"><table><thead><tr><th>محصول</th><th>دسته</th><th>قیمت</th><th>وضعیت</th><th>عملیات</th></tr></thead><tbody><?php foreach($products as $p): ?><tr><td><strong><?= e($p['name_fa']) ?></strong><small><?= e($p['name_en']) ?></small></td><td><?= e($p['category_name']??'—') ?></td><td><?= number_format((int)$p['price']) ?></td><td><span class="state <?= $p['available']?'on':'off' ?>"><?= $p['available']?'موجود':'ناموجود' ?></span></td><td><div class="actions"><a href="?edit=<?= (int)$p['id'] ?>#product-form">ویرایش</a><form method="post"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="toggle_product"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button type="submit">تغییر موجودی</button></form><form method="post" onsubmit="return confirm('این محصول حذف شود؟')"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="delete_product"><input type="hidden" name="id" value="<?= (int)$p['id'] ?>"><button class="danger-link" type="submit">حذف</button></form></div></td></tr><?php endforeach; ?></tbody></table></div></section>
<section class="panel" id="categories"><div class="panel-head"><div><p class="overline">CATEGORIES</p><h2>دسته‌بندی‌ها</h2></div></div><form method="post" class="category-add"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="save_category"><input name="name" required placeholder="نام دسته جدید"><input name="sort_order" type="number" value="<?= count($categories)+1 ?>" aria-label="ترتیب"><label class="inline-check"><input type="checkbox" name="active" checked> فعال</label><button class="primary small" type="submit">افزودن</button></form><div class="category-list"><?php foreach($categories as $c): ?><form method="post" class="category-row"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="save_category"><input type="hidden" name="id" value="<?= (int)$c['id'] ?>"><input name="name" value="<?= e($c['name']) ?>" required><input name="sort_order" type="number" value="<?= (int)$c['sort_order'] ?>"><label><input type="checkbox" name="active" <?= $c['active']?'checked':'' ?>> فعال</label><button type="submit">ذخیره</button></form><?php endforeach; ?></div></section>
<section class="panel qr-panel" id="qr"><div class="panel-head"><div><p class="overline">QR MENU</p><h2>QR منوی عمومی</h2></div></div><div class="qr-grid"><div id="qrcode" class="qr-box"></div><div><label>آدرس منو<input id="menuUrl" value="<?= e($menuUrl) ?>" readonly dir="ltr"></label><div class="form-actions"><button class="primary small" id="copyUrl" type="button">کپی لینک</button><button class="ghost" id="downloadQr" type="button">دانلود QR</button></div><p class="muted">برای چاپ روی میزها از همین QR استفاده کنید. بعد از انتقال دامنه، آدرس نهایی را در config.php تنظیم کنید.</p></div></div></section>
<section class="panel" id="security"><div class="panel-head"><div><p class="overline">SECURITY</p><h2>تغییر رمز مدیریت</h2></div></div><form method="post" class="security-form"><input type="hidden" name="csrf" value="<?= e(csrf()) ?>"><input type="hidden" name="action" value="change_password"><label>رمز فعلی<input type="password" name="current_password" required></label><label>رمز جدید<input type="password" name="new_password" minlength="8" required></label><button class="primary small" type="submit">تغییر رمز</button></form></section>
</main></div><script src="https://cdnjs.cloudflare.com/ajax/libs/qrcodejs/1.0.0/qrcode.min.js" integrity="sha512-CNgIRecGo7nphbeZ04Sc13ka07Jtx5E1S4o+Z3m+2NoYoA9T6BZSNHgMfuuevbd/mUScLGtwV9r0zHD4Q4W+Zw==" crossorigin="anonymous" referrerpolicy="no-referrer"></script><script src="../assets/admin.js"></script></body></html>
