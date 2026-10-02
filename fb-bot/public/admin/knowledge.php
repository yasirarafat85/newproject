<?php

declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';

use App\AiException;
use App\AiModels;
use App\AnthropicAiClient;
use App\Auth;
use App\CommentResponder;
use App\KnowledgeBase;
use App\Logger;
use App\ReplyGenerator;
use App\Settings;
use App\View;

Auth::require();
header('X-Frame-Options: DENY');

$errors = [];
$saved = false;
$test = null;
$testComment = '';
$content = KnowledgeBase::read();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) {
        $errors[] = 'সেশন শেষ হয়ে গেছে। পেজ রিফ্রেশ করে আবার চেষ্টা করুন।';
    } elseif (($_POST['action'] ?? '') === 'save') {
        $content = (string) ($_POST['content'] ?? '');
        try {
            KnowledgeBase::write($content);
            Logger::info('kb', 'Knowledge base updated', ['bytes' => strlen($content)]);
            $saved = true;
        } catch (RuntimeException $e) {
            $errors[] = $e->getMessage() === 'Knowledge base is too long.'
                ? 'লেখা অনেক বড় (সর্বোচ্চ প্রায় ৬০KB)।'
                : 'ফাইল সেভ করা যায়নি। storage ফোল্ডারের permission দেখুন।';
        }
    } elseif (($_POST['action'] ?? '') === 'test') {
        $testComment = trim((string) ($_POST['comment'] ?? ''));
        if ($testComment === '' || mb_strlen($testComment) > 1000) {
            $errors[] = 'টেস্টের জন্য ১–১০০০ অক্ষরের একটা কমেন্ট লিখুন।';
        } elseif (!App\Env::has('AI_API_KEY')) {
            $errors[] = '.env ফাইলে AI_API_KEY সেট করা নেই।';
        } elseif (!class_exists(Anthropic\Client::class)) {
            $errors[] = 'AI লাইব্রেরি ইনস্টল করা নেই। Terminal-এ composer install --no-dev চালান।';
        } elseif (!KnowledgeBase::isFilled()) {
            $errors[] = 'আগে বাম পাশে ব্যবসার তথ্য লিখে সেভ করুন।';
        } else {
            $model = Settings::aiModel();
            try {
                $decision = (new ReplyGenerator(AnthropicAiClient::fromEnv()))->generate($testComment, $model, Settings::aiEffort());
                $r = $decision['response'];
                $cost = AiModels::costMicros($r->model ?: $model, $r);
                CommentResponder::recordAiCall('test', null, $r->model ?: $model, $r, $cost);
                $test = $decision + ['cost' => $cost, 'model' => $r->model ?: $model];
            } catch (AiException $e) {
                Logger::error('kb', 'Test reply failed: ' . $e->getMessage());
                $errors[] = 'AI থেকে উত্তর আনা যায়নি: ' . Logger::scrubString($e->getMessage());
            }
        }
    }
}

$actionLabels = ['reply' => 'উত্তর দেবে', 'handoff' => 'মানুষের কাছে পাঠাবে', 'ignore' => 'উপেক্ষা করবে'];

View::header('Knowledge Base', 'knowledge.php');
?>
<?php if ($saved): ?><div class="alert alert-success">সেভ হয়েছে। পরের কমেন্ট থেকে বট নতুন তথ্য ব্যবহার করবে।</div><?php endif; ?>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= View::e($error) ?></div><?php endforeach; ?>
<?php if (!KnowledgeBase::isFilled()): ?>
  <div class="alert alert-warning">Knowledge Base এখনো খালি। তথ্য না লেখা পর্যন্ত বট কোনো কমেন্টে উত্তর দেবে না।</div>
<?php endif; ?>

<div class="grid-2 kb-grid">
  <section class="card">
    <h3>ব্যবসার তথ্য</h3>
    <p class="muted small">বট শুধু এখানে লেখা তথ্য থেকেই উত্তর দেবে। এখানে যা নেই, সেই প্রশ্ন মানুষের কাছে পাঠাবে। দাম বা অফার বদলালে এখানে আপডেট করুন।</p>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= View::e(Auth::csrfToken()) ?>">
      <input type="hidden" name="action" value="save">
      <textarea name="content" class="kb-editor" rows="24" spellcheck="false"><?= View::e($content) ?></textarea>
      <div class="form-actions">
        <span class="muted small"><?= number_format(mb_strlen($content)) ?> অক্ষর</span>
        <button type="submit" class="btn btn-primary btn-auto"><?= View::icon('check') ?>সেভ করুন</button>
      </div>
    </form>
  </section>

  <section class="card">
    <h3>উত্তর টেস্ট করুন</h3>
    <p class="muted small">একটা নমুনা কমেন্ট লিখুন। বট কী উত্তর দিত সেটা দেখাবে, Facebook-এ কিছু পোস্ট হবে না।
      মডেল: <strong><?= View::e(AiModels::label(Settings::aiModel())) ?></strong></p>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= View::e(Auth::csrfToken()) ?>">
      <input type="hidden" name="action" value="test">
      <textarea name="comment" rows="3" maxlength="1000" placeholder="যেমন: ভাই দাম কত? ঢাকার বাইরে ডেলিভারি হয়?"><?= View::e($testComment) ?></textarea>
      <div class="form-actions">
        <button type="submit" class="btn btn-success btn-auto"><?= View::icon('chat') ?>টেস্ট করুন</button>
      </div>
    </form>

    <?php if ($test !== null): ?>
      <div class="test-result">
        <div class="comment-head">
          <span class="badge badge-ai-<?= View::e($test['action']) ?>"><?= View::e($actionLabels[$test['action']] ?? $test['action']) ?></span>
          <span class="muted small"><?= View::e(AiModels::label($test['model'])) ?> · <?= AiModels::formatCost($test['cost']) ?></span>
        </div>
        <?php if ($test['reply'] !== ''): ?>
          <div class="comment-reply"><?= View::e($test['reply']) ?></div>
        <?php endif; ?>
        <div class="comment-note">কারণ: <?= View::e($test['reason']) ?></div>
      </div>
    <?php endif; ?>
  </section>
</div>
<?php
View::footer();
