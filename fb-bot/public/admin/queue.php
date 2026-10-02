<?php

declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';

use App\AiModels;
use App\Auth;
use App\CommentActions;
use App\Database;
use App\View;

Auth::require();
header('X-Frame-Options: DENY');

const PER_PAGE = 20;

$tabs = [
    'needs_human' => ['উত্তর দিতে হবে', 'AI উত্তর জানত না, অথবা কাস্টমার মানুষের সাথে কথা বলতে চায়। প্রয়োজনে উত্তর লিখে পাঠান, তারপর "সমাধান হয়েছে" দিন।'],
    'dry_run' => ['অনুমোদনের অপেক্ষায়', 'DRY_RUN মোডে AI এই উত্তরগুলো বানিয়েছে, কিন্তু পোস্ট করেনি। দরকার হলে এডিট করে "অনুমোদন দিয়ে পোস্ট" চাপুন।'],
    'failed' => ['ব্যর্থ', 'কোনো সমস্যার কারণে উত্তর যায়নি। কারণ দেখে "আবার চেষ্টা" চাপুন, অথবা নিজে উত্তর দিন।'],
];

$tab = (string) ($_GET['tab'] ?? '');
if (!isset($tabs[$tab])) {
    $tab = 'needs_human';
}

// Actions use POST, then redirect back (so a refresh never re-posts).
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $commentId = (string) ($_POST['comment_id'] ?? '');
    $action = (string) ($_POST['action'] ?? '');
    $actions = new CommentActions();

    if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) {
        $error = 'সেশন শেষ হয়ে গেছে। পেজ রিফ্রেশ করে আবার চেষ্টা করুন।';
    } else {
        $error = match ($action) {
            'reply' => $actions->reply($commentId, (string) ($_POST['text'] ?? ''), false),
            'approve' => $actions->reply($commentId, (string) ($_POST['text'] ?? ''), true),
            'resolve' => $actions->resolve($commentId),
            'retry' => $actions->retry($commentId),
            default => 'অজানা কাজ।',
        };
    }

    $_SESSION['flash'] = $error === null
        ? ['success', match ($action) {
            'reply', 'approve' => 'উত্তর Facebook-এ পোস্ট হয়েছে।',
            'resolve' => 'সমাধান হিসেবে চিহ্নিত হয়েছে।',
            'retry' => 'আবার চেষ্টার জন্য তালিকায় রাখা হয়েছে। ১–২ মিনিটের মধ্যে worker চালাবে।',
            default => 'হয়েছে।',
        }]
        : ['danger', $error];
    if ($error !== null) {
        // Keep the typed text so it is not lost after a Facebook error.
        $_SESSION['draft'] = [$commentId, (string) ($_POST['text'] ?? '')];
    }
    header('Location: queue.php?tab=' . rawurlencode($tab));
    exit;
}

$flash = $_SESSION['flash'] ?? null;
$draft = $_SESSION['draft'] ?? null;
unset($_SESSION['flash'], $_SESSION['draft']);

$counts = array_fill_keys(array_keys($tabs), 0);
foreach (Database::all("SELECT status, COUNT(*) AS n FROM comments WHERE status IN ('needs_human', 'dry_run', 'failed') GROUP BY status") as $row) {
    $counts[$row['status']] = (int) $row['n'];
}

$page = max(1, (int) ($_GET['page'] ?? 1));
$rows = Database::all(
    'SELECT * FROM comments WHERE status = ? ORDER BY id ASC LIMIT ' . (PER_PAGE + 1) . ' OFFSET ' . (($page - 1) * PER_PAGE),
    [$tab]
);
$hasNext = count($rows) > PER_PAGE;
$rows = array_slice($rows, 0, PER_PAGE);

$aiActions = ['reply' => 'AI: উত্তর', 'handoff' => 'AI: মানুষ লাগবে', 'ignore' => 'AI: উপেক্ষা'];
$csrf = View::e(Auth::csrfToken());

View::header('মানুষ লাগবে', 'queue.php');
?>
<?php if ($flash !== null): ?>
  <div class="alert alert-<?= View::e($flash[0]) ?>"><?= View::e($flash[1]) ?></div>
<?php endif; ?>

<nav class="filters">
  <?php foreach ($tabs as $key => [$label]): ?>
    <a class="chip<?= $key === $tab ? ' active' : '' ?>" href="?tab=<?= $key ?>"><?= View::e($label) ?> <span class="count"><?= $counts[$key] ?></span></a>
  <?php endforeach; ?>
</nav>
<p class="muted small queue-help"><?= View::e($tabs[$tab][1]) ?></p>

<?php if ($rows === []): ?>
  <div class="card empty">এখানে কিছু নেই। 🎉</div>
<?php else: ?>
  <div class="comment-list">
    <?php foreach ($rows as $c): ?>
      <?php
        $id = (string) $c['comment_id'];
        $prefill = $draft !== null && $draft[0] === $id ? $draft[1] : ($tab === 'dry_run' ? (string) $c['reply_text'] : '');
      ?>
      <article class="comment-card" id="c-<?= View::e($id) ?>">
        <div class="comment-head">
          <span class="comment-author"><?= View::e($c['from_name'] ?: 'অজানা ব্যক্তি') ?></span>
          <span class="comment-time"><?= View::localTime($c['created_at']) ?></span>
          <?php if ($link = View::facebookLink($c)): ?>
            <a href="<?= View::e($link) ?>" target="_blank" rel="noopener noreferrer">Facebook-এ দেখুন ↗</a>
          <?php endif; ?>
        </div>
        <div class="comment-body"><?= View::e($c['message']) ?></div>

        <?php if ($tab === 'needs_human' && !empty($c['reply_text'])): ?>
          <div class="comment-reply"><span class="muted small">ইতিমধ্যে পোস্ট হওয়া উত্তর:</span><br><?= View::e($c['reply_text']) ?></div>
        <?php endif; ?>

        <?php if (!empty($c['ai_action']) || !empty($c['note'])): ?>
          <div class="comment-meta">
            <?php if (!empty($c['ai_action'])): ?>
              <span class="badge badge-ai-<?= View::e($c['ai_action']) ?>"><?= View::e($aiActions[$c['ai_action']] ?? $c['ai_action']) ?></span>
              <span><?= View::e(AiModels::label($c['ai_model'])) ?></span>
            <?php endif; ?>
            <?php if (!empty($c['note'])): ?><span><?= View::e($c['note']) ?></span><?php endif; ?>
          </div>
        <?php endif; ?>

        <form method="post" class="queue-form">
          <input type="hidden" name="csrf" value="<?= $csrf ?>">
          <input type="hidden" name="comment_id" value="<?= View::e($id) ?>">
          <label class="sr-only" for="t-<?= View::e($id) ?>">উত্তর</label>
          <textarea id="t-<?= View::e($id) ?>" name="text" rows="3" maxlength="<?= CommentActions::MAX_REPLY_CHARS ?>"
            placeholder="<?= $tab === 'needs_human' ? 'কাস্টমারকে উত্তর লিখুন (পেজের নামে পোস্ট হবে)…' : 'উত্তর লিখুন…' ?>"><?= View::e($prefill) ?></textarea>
          <div class="queue-actions">
            <?php if ($tab === 'dry_run'): ?>
              <button type="submit" name="action" value="approve" class="btn btn-success btn-auto"><?= View::icon('check') ?>অনুমোদন দিয়ে পোস্ট</button>
            <?php else: ?>
              <button type="submit" name="action" value="reply" class="btn btn-primary btn-auto"><?= View::icon('chat') ?>উত্তর পোস্ট করুন</button>
            <?php endif; ?>
            <?php if ($tab === 'failed'): ?>
              <button type="submit" name="action" value="retry" class="btn-ghost" formnovalidate>আবার চেষ্টা</button>
            <?php endif; ?>
            <button type="submit" name="action" value="resolve" class="btn-ghost" formnovalidate>
              <?= $tab === 'dry_run' ? 'পোস্ট না করে বাদ দিন' : 'সমাধান হয়েছে' ?>
            </button>
          </div>
        </form>
      </article>
    <?php endforeach; ?>
  </div>
  <?= View::pager($page, $hasNext) ?>
<?php endif; ?>
<script>
  // Disable buttons after the first click so a form is never sent twice.
  document.querySelectorAll('.queue-form').forEach(function (form) {
    form.addEventListener('submit', function () {
      setTimeout(function () { form.querySelectorAll('button').forEach(function (b) { b.disabled = true; }); }, 0);
    });
  });
</script>
<?php
View::footer();
