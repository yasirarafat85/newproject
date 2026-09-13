<?php
/** সবচেয়ে সাধারণ লেআউট — ইনস্টলার ও ভুল পাতার জন্য। */
use App\Core\View;
?>
<!doctype html>
<html lang="bn">
<head><?= View::partial('partials/head', ['title' => $title ?? '', 'appName' => $appName ?? 'HelpDesk']) ?></head>
<body>
    <?= $content ?>
    <script type="module" src="<?= e(asset('js/app.js')) ?>"></script>
</body>
</html>
