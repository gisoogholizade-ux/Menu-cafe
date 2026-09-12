<?php
$config = require __DIR__ . '/../config.php';
date_default_timezone_set($config['timezone'] ?? 'Asia/Tehran');

$dataDir = __DIR__ . '/../data';
if (!is_dir($dataDir)) {
    mkdir($dataDir, 0775, true);
}
$dbPath = $dataDir . '/menu.sqlite';

$pdo = new PDO('sqlite:' . $dbPath, null, null, [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
]);
$pdo->exec('PRAGMA foreign_keys = ON');

$pdo->exec("CREATE TABLE IF NOT EXISTS categories (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name TEXT NOT NULL,
    sort_order INTEGER NOT NULL DEFAULT 0,
    active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS products (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    name_fa TEXT NOT NULL,
    name_en TEXT,
    description TEXT,
    price INTEGER NOT NULL DEFAULT 0,
    image_url TEXT,
    category_id INTEGER,
    tags TEXT,
    available INTEGER NOT NULL DEFAULT 1,
    featured INTEGER NOT NULL DEFAULT 0,
    created_at TEXT NOT NULL,
    updated_at TEXT NOT NULL,
    FOREIGN KEY(category_id) REFERENCES categories(id) ON DELETE SET NULL
)");

$pdo->exec("CREATE TABLE IF NOT EXISTS admins (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    username TEXT UNIQUE NOT NULL,
    password_hash TEXT NOT NULL,
    active INTEGER NOT NULL DEFAULT 1,
    created_at TEXT NOT NULL
)");

function nowIso(): string {
    return date('c');
}

function seedDatabase(PDO $pdo): void {
    $count = (int)$pdo->query('SELECT COUNT(*) FROM categories')->fetchColumn();
    if ($count > 0) return;

    $now = nowIso();
    $cats = [
        ['قهوه گرم', 1], ['قهوه سرد', 2], ['نوشیدنی', 3],
        ['چای و دمنوش', 4], ['کیک و دسر', 5], ['اسنک', 6]
    ];
    $stmt = $pdo->prepare('INSERT INTO categories(name, sort_order, active, created_at, updated_at) VALUES(?,?,?,?,?)');
    foreach ($cats as $c) $stmt->execute([$c[0], $c[1], 1, $now, $now]);

    $catMap = [];
    foreach ($pdo->query('SELECT id, name FROM categories') as $row) $catMap[$row['name']] = $row['id'];

    $items = [
        ['اسپرسو','Espresso','شات اسپرسوی غلیظ با عطر شکلات تلخ و کارامل',95000,'قهوه گرم','پرفروش',1,1],
        ['آمریکانو','Americano','اسپرسو با آب داغ؛ سبک، خوش‌عطر و متعادل',110000,'قهوه گرم','',1,0],
        ['کاپوچینو','Cappuccino','اسپرسو، شیر بخار داده‌شده و فوم مخملی',145000,'قهوه گرم','پیشنهاد کافه',1,1],
        ['لاته','Caffè Latte','اسپرسو با شیر گرم و بافت نرم و کرمی',155000,'قهوه گرم','پرفروش',1,0],
        ['موکا','Mocha','اسپرسو، شکلات و شیر؛ متعادل و دلنشین',175000,'قهوه گرم','',1,0],
        ['آیس لاته','Iced Latte','اسپرسو دوبل، شیر سرد و یخ',165000,'قهوه سرد','پرفروش',1,1],
        ['آیس آمریکانو','Iced Americano','اسپرسو روی یخ و آب سرد؛ خنک و پرانرژی',125000,'قهوه سرد','بدون شکر',1,0],
        ['هات چاکلت','Hot Chocolate','شکلات غلیظ با شیر گرم و بافت لطیف',170000,'نوشیدنی','',1,0],
        ['چای ماسالا','Masala Chai','چای ادویه‌ای گرم با شیر و رایحه دارچین',160000,'چای و دمنوش','پیشنهاد کافه',1,0],
        ['چیزکیک','Cheesecake','چیزکیک کرمی با پایه بیسکویتی تازه',185000,'کیک و دسر','پرفروش',1,1],
        ['براونی','Brownie','براونی شکلاتی مرطوب با مغز گردو',145000,'کیک و دسر','جدید',1,0],
        ['کروسان','Croissant','کروسان کره‌ای تازه با بافت لایه‌لایه',135000,'اسنک','جدید',0,0]
    ];
    $stmt = $pdo->prepare('INSERT INTO products(name_fa,name_en,description,price,image_url,category_id,tags,available,featured,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?,?,?,?)');
    foreach ($items as $it) {
        $stmt->execute([$it[0],$it[1],$it[2],$it[3],'',$catMap[$it[4]],$it[5],$it[6],$it[7],$now,$now]);
    }
}

seedDatabase($pdo);

$adminCount = (int)$pdo->query('SELECT COUNT(*) FROM admins')->fetchColumn();
if ($adminCount === 0) {
    $stmt = $pdo->prepare('INSERT INTO admins(username,password_hash,active,created_at) VALUES(?,?,1,?)');
    $stmt->execute(['admin', password_hash('ChangeMe123!', PASSWORD_DEFAULT), nowIso()]);
}

return $pdo;
