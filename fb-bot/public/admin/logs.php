<?php

declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';

use App\Auth;
use App\Database;
use App\View;

Auth::require();
header('X-Frame-Options: DENY');

const PER_PAGE = 50;

$levels = ['' => 'সব', 'info' => 'Info', 'warning' => 'Warning', 'error' => 'Error'];
$level = (string) ($_GET['level'] ?? '');
if (!array_key_exists($level, $levels)) {
    $level = '';
}

$channels = array_column(Database::all('SELECT DISTINCT channel FROM logs ORDER BY channel'), 'channel');
$channel = (string) ($_GET['channel'] ?? '');
if (!in_array($channel, $channels, true)) {
    $channel = '';
}
$page = max(1, (int) ($_GET['page'] ?? 1));

$conditions = [];
$params = [];
if ($level !== '') {
    $conditions[] = 'level = ?';
    $params[] = $level;
}
if ($channel !== '') {
    $conditions[] = 'channel = ?';
    $params[] = $channel;
}
$where = $conditions === [] ? '' : 'WHERE ' . implode(' AND ', $conditions);

$rows = Database::all(
    "SELECT * FROM logs $where ORDER BY id DESC LIMIT " . (PER_PAGE + 1) . ' OFFSET ' . (($page - 1) * PER_PAGE),
    $params
);
$hasNext = count($rows) > PER_PAGE;
$rows = array_slice($rows, 0, PER_PAGE);

$filterLink = static function (array $change) use ($level, $channel): string {
    $query = array_filter(array_merge(['level' => $level, 'channel' => $channel], $change), static fn ($v) => $v !== '');
    return '?' . http_build_query($query);
};

View::header('লগ', 'logs.php');
?>
<nav class="filters">
  <?php foreach ($levels as $key => $label): ?>
    <a class="chip<?= $key === $level ? ' active' : '' ?>" href="<?= View::e($filterLink(['level' => $key])) ?>"><?= View::e($label) ?></a>
  <?php endforeach; ?>
</nav>
<?php if ($channels !== []): ?>
  <nav class="filters">
    <a class="chip<?= $channel === '' ? ' active' : '' ?>" href="<?= View::e($filterLink(['channel' => ''])) ?>">সব চ্যানেল</a>
    <?php foreach ($channels as $ch): ?>
      <a class="chip<?= $ch === $channel ? ' active' : '' ?>" href="<?= View::e($filterLink(['channel' => $ch])) ?>"><?= View::e($ch) ?></a>
    <?php endforeach; ?>
  </nav>
<?php endif; ?>

<?php if ($rows === []): ?>
  <div class="card empty">কোনো লগ নেই।</div>
<?php else: ?>
  <div class="table-container">
    <table class="log-table">
      <thead><tr><th>সময়</th><th>লেভেল</th><th>চ্যানেল</th><th>বার্তা</th></tr></thead>
      <tbody>
      <?php foreach ($rows as $log): ?>
        <tr>
          <td class="when"><?= View::localTime($log['created_at']) ?></td>
          <td><span class="badge badge-<?= View::e($log['level']) ?>"><?= View::e($log['level']) ?></span></td>
          <td><?= View::e($log['channel']) ?></td>
          <td class="msg">
            <?= View::e($log['message']) ?>
            <?php if (!empty($log['context'])): ?>
              <details>
                <summary>বিস্তারিত</summary>
                <pre><?= View::e(json_encode(json_decode($log['context'], true), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?: $log['context']) ?></pre>
              </details>
            <?php endif; ?>
          </td>
        </tr>
      <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?= View::pager($page, $hasNext) ?>
<?php endif; ?>
<?php
View::footer();
