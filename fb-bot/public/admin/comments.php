<?php

declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';

use App\Auth;
use App\Database;
use App\View;

Auth::require();
header('X-Frame-Options: DENY');

const PER_PAGE = 30;

$statuses = [
    '' => 'সব',
    'new' => 'নতুন',
    'replied' => 'উত্তর দেওয়া',
    'dry_run' => 'DRY_RUN',
    'needs_human' => 'মানুষ লাগবে',
    'skipped' => 'বাদ',
    'failed' => 'ব্যর্থ',
];

$status = (string) ($_GET['status'] ?? '');
if (!array_key_exists($status, $statuses)) {
    $status = '';
}
$page = max(1, (int) ($_GET['page'] ?? 1));

$counts = [];
foreach (Database::all('SELECT status, COUNT(*) AS n FROM comments GROUP BY status') as $row) {
    $counts[$row['status']] = (int) $row['n'];
}
$counts[''] = array_sum($counts);

$where = $status === '' ? '' : 'WHERE status = ?';
$params = $status === '' ? [] : [$status];
$rows = Database::all(
    "SELECT * FROM comments $where ORDER BY id DESC LIMIT " . (PER_PAGE + 1) . ' OFFSET ' . (($page - 1) * PER_PAGE),
    $params
);
$hasNext = count($rows) > PER_PAGE;
$rows = array_slice($rows, 0, PER_PAGE);

/** Link to the comment on Facebook: post URL + comment_id of the comment. */
function facebookLink(array $c): ?string
{
    if (empty($c['post_id'])) {
        return null;
    }
    $parts = explode('_', (string) $c['comment_id']);
    return 'https://www.facebook.com/' . rawurlencode((string) $c['post_id']) . '?comment_id=' . rawurlencode(end($parts));
}

View::header('কমেন্ট', 'comments.php');
?>
<nav class="filters">
  <?php foreach ($statuses as $key => $label): ?>
    <a class="chip<?= $key === $status ? ' active' : '' ?>" href="?<?= View::e(http_build_query($key === '' ? [] : ['status' => $key])) ?>">
      <?= View::e($label) ?> <span class="count"><?= $counts[$key] ?? 0 ?></span>
    </a>
  <?php endforeach; ?>
</nav>

<?php if ($rows === []): ?>
  <div class="card empty">
    এখনো কোনো কমেন্ট আসেনি।<br>
    পেজের কোনো পোস্টে অন্য অ্যাকাউন্ট থেকে কমেন্ট করে দেখুন।
  </div>
<?php else: ?>
  <div class="comment-list">
    <?php foreach ($rows as $c): ?>
      <article class="comment-card">
        <div class="comment-head">
          <span class="badge badge-<?= View::e($c['status']) ?>"><?= View::e($statuses[$c['status']] ?? $c['status']) ?></span>
          <span class="comment-author"><?= View::e($c['from_name'] ?: 'অজানা ব্যক্তি') ?></span>
          <span class="comment-time"><?= View::localTime($c['created_at']) ?></span>
          <?php if ($link = facebookLink($c)): ?>
            <a href="<?= View::e($link) ?>" target="_blank" rel="noopener noreferrer">Facebook-এ দেখুন ↗</a>
          <?php endif; ?>
        </div>
        <div class="comment-body"><?= View::e($c['message']) ?></div>
        <?php if (!empty($c['reply_text'])): ?>
          <div class="comment-reply"><?= View::e($c['reply_text']) ?></div>
        <?php endif; ?>
        <?php if (!empty($c['note'])): ?>
          <div class="comment-note"><?= View::e($c['note']) ?></div>
        <?php endif; ?>
      </article>
    <?php endforeach; ?>
  </div>
  <?= View::pager($page, $hasNext) ?>
<?php endif; ?>
<?php
View::footer();
