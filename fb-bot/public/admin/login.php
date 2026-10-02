<?php

declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';

use App\Auth;
use App\Env;
use App\View;

Auth::startSession();
header('X-Frame-Options: DENY');

if (Auth::check()) {
    header('Location: index.php');
    exit;
}

$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) {
        $error = 'সেশন শেষ হয়ে গেছে। পেজটা রিফ্রেশ করে আবার চেষ্টা করুন।';
    } else {
        $error = Auth::attempt((string) ($_POST['password'] ?? ''), (string) ($_SERVER['REMOTE_ADDR'] ?? 'unknown'));
        if ($error === null) {
            header('Location: index.php');
            exit;
        }
    }
}

$app = View::e(Env::get('APP_NAME', 'FB Page Bot'));
?>
<!doctype html>
<html lang="bn">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<meta name="robots" content="noindex, nofollow">
<title>লগইন · <?= $app ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="../assets/admin.css">
<script src="../assets/theme.js"></script>
</head>
<body class="auth-page">
  <div class="auth-card">
    <div class="brand center"><span class="brand-dot"></span><?= $app ?></div>
    <h2>অ্যাডমিন প্যানেলে লগইন</h2>
    <p class="muted">শুধু পেজের মালিকের জন্য।</p>

    <?php if (!Auth::isConfigured()): ?>
      <div class="alert alert-warning">
        পাসওয়ার্ড এখনো সেট করা হয়নি। cPanel Terminal-এ <code>php bin/hash-password.php</code> চালিয়ে
        যে লাইনটা আসবে সেটা <code>.env</code> ফাইলে বসান।
      </div>
    <?php endif; ?>

    <?php if ($error !== null): ?>
      <div class="alert alert-danger"><?= View::e($error) ?></div>
    <?php endif; ?>

    <form method="post" autocomplete="off">
      <input type="hidden" name="csrf" value="<?= View::e(Auth::csrfToken()) ?>">
      <label for="password">পাসওয়ার্ড</label>
      <input type="password" id="password" name="password" required autofocus>
      <button type="submit" class="btn btn-primary"><?= View::icon('lock') ?>লগইন</button>
    </form>
  </div>
</body>
</html>
