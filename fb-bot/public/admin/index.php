<?php

declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';

use App\AiModels;
use App\Auth;
use App\Database;
use App\Env;
use App\Health;
use App\Settings;
use App\View;

Auth::require();
header('X-Frame-Options: DENY');

$checks = Health::checks();

// Start of "today" in the Page's timezone, converted to UTC for querying.
$tz = new DateTimeZone(Env::get('APP_TIMEZONE', 'Asia/Dhaka'));
$todayUtc = (new DateTimeImmutable('today', $tz))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');

$count = static function (string $sql, array $params = []): int {
    try {
        return (int) (Database::one($sql, $params)['n'] ?? 0);
    } catch (Throwable) {
        return 0;
    }
};

$stats = [
    ['blue', 'chat', $count('SELECT COUNT(*) AS n FROM comments WHERE created_at >= ?', [$todayUtc]), 'আজকের কমেন্ট'],
    ['green', 'check', $count("SELECT COUNT(*) AS n FROM comments WHERE status = 'replied' AND updated_at >= ?", [$todayUtc]), 'আজ উত্তর দেওয়া হয়েছে'],
    ['orange', 'user', $count("SELECT COUNT(*) AS n FROM comments WHERE status = 'needs_human'"), 'মানুষের উত্তর লাগবে'],
    ['red', 'alert', $count("SELECT COUNT(*) AS n FROM logs WHERE level = 'error' AND created_at >= ?", [$todayUtc]), 'আজকের এরর'],
];

$monthUtc = (new DateTimeImmutable('first day of this month midnight', $tz))->setTimezone(new DateTimeZone('UTC'))->format('Y-m-d H:i:s');
$sumCost = static function (string $since): int {
    try {
        return (int) (Database::one('SELECT COALESCE(SUM(cost_micros), 0) AS n FROM ai_calls WHERE created_at >= ?', [$since])['n'] ?? 0);
    } catch (Throwable) {
        return 0;
    }
};
$costToday = $sumCost($todayUtc);
$costMonth = $sumCost($monthUtc);

$botOn = false;
$recentLogs = [];
try {
    $botOn = Settings::botEnabled();
    $recentLogs = Database::all('SELECT level, channel, message, created_at FROM logs ORDER BY id DESC LIMIT 8');
} catch (Throwable) {
}
$dryRun = Settings::dryRun();

View::header('ড্যাশবোর্ড', 'index.php');
?>
<div class="pill-row">
  <span class="pill <?= $botOn ? 'pill-success' : 'pill-danger' ?>">বট: <?= $botOn ? 'চালু' : 'বন্ধ' ?></span>
  <span class="pill <?= $dryRun ? 'pill-warning' : 'pill-success' ?>">
    <?= $dryRun ? 'DRY_RUN: শুধু লগ হবে, Facebook-এ পোস্ট হবে না' : 'লাইভ: Facebook-এ উত্তর যাবে' ?>
  </span>
  <span class="pill">মডেল: <?= View::e(AiModels::label(Settings::aiModel())) ?></span>
  <?php if (Env::has('PAGE_NAME')): ?><span class="pill">পেজ: <?= View::e(Env::get('PAGE_NAME')) ?></span><?php endif; ?>
</div>

<section class="stats-grid">
  <?php foreach ($stats as [$color, $icon, $value, $label]): ?>
    <div class="stat-card">
      <div class="stat-icon <?= $color ?>"><?= View::icon($icon) ?></div>
      <div>
        <div class="stat-value"><?= number_format($value) ?></div>
        <div class="stat-label"><?= $label ?></div>
      </div>
    </div>
  <?php endforeach; ?>
  <div class="stat-card">
    <div class="stat-icon purple"><svg viewBox="0 0 24 24"><line x1="12" y1="1" x2="12" y2="23"/><path d="M17 5H9.5a3.5 3.5 0 000 7h5a3.5 3.5 0 010 7H6"/></svg></div>
    <div>
      <div class="stat-value"><?= AiModels::formatCost($costMonth) ?></div>
      <div class="stat-label">AI খরচ এই মাসে · আজ <?= AiModels::formatCost($costToday) ?></div>
    </div>
  </div>
</section>

<?php $appUrl = rtrim(Env::get('APP_URL'), '/'); ?>
<section class="card connect-card">
  <h3>Facebook সংযোগের তথ্য</h3>
  <p class="muted">Meta dashboard-এ Webhook সেট করার সময় এগুলো কপি করে বসাবেন।</p>
  <dl class="kv">
    <dt>Callback URL</dt><dd><code><?= View::e($appUrl . '/webhook.php') ?></code></dd>
    <dt>Verify token</dt><dd><code><?= View::e(Env::get('WEBHOOK_VERIFY_TOKEN', '— php bin/setup.php চালান —')) ?></code></dd>
    <dt>Privacy Policy URL</dt><dd><code><?= View::e($appUrl . '/privacy.php') ?></code></dd>
  </dl>
</section>

<div class="grid-2">
  <section class="card">
    <h3>সিস্টেম চেক</h3>
    <div class="table-container flat">
      <table>
        <thead><tr><th>বিষয়</th><th>অবস্থা</th></tr></thead>
        <tbody>
        <?php foreach ($checks as $check): ?>
          <tr>
            <td><?= View::e($check['label']) ?></td>
            <td>
              <span class="dot <?= $check['ok'] ? 'ok' : 'bad' ?>"></span>
              <?= View::e($check['detail']) ?>
            </td>
          </tr>
        <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </section>

  <section class="card">
    <h3>সাম্প্রতিক লগ</h3>
    <?php if ($recentLogs === []): ?>
      <p class="muted">এখনো কোনো লগ নেই।</p>
    <?php else: ?>
      <ul class="log-list">
        <?php foreach ($recentLogs as $log): ?>
          <li>
            <span class="badge badge-<?= View::e($log['level']) ?>"><?= View::e($log['level']) ?></span>
            <span class="log-msg"><?= View::e($log['channel']) ?> · <?= View::e($log['message']) ?></span>
            <span class="log-time"><?= View::localTime($log['created_at']) ?></span>
          </li>
        <?php endforeach; ?>
      </ul>
    <?php endif; ?>
  </section>
</div>
<?php
View::footer();
