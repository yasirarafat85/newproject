<?php

declare(strict_types=1);

require __DIR__ . '/../../bootstrap.php';

use App\AiModels;
use App\Auth;
use App\Logger;
use App\Settings;
use App\View;

Auth::require();
header('X-Frame-Options: DENY');

$errors = [];
$saved = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!Auth::verifyCsrf($_POST['csrf'] ?? null)) {
        $errors[] = 'সেশন শেষ হয়ে গেছে। পেজ রিফ্রেশ করে আবার চেষ্টা করুন।';
    } else {
        $model = (string) ($_POST['ai_model'] ?? '');
        $effort = (string) ($_POST['ai_effort'] ?? '');
        $tone = trim((string) ($_POST['reply_tone'] ?? ''));
        $contact = trim((string) ($_POST['handoff_contact'] ?? ''));
        $perUser = filter_var($_POST['replies_per_user_per_hour'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 50]]);
        $perDay = filter_var($_POST['max_replies_per_day'] ?? '', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 5000]]);

        if (AiModels::get($model) === null) {
            $errors[] = 'মডেল বাছাই সঠিক নয়।';
        }
        if (!in_array($effort, AiModels::EFFORTS, true)) {
            $errors[] = 'চিন্তার মাত্রা সঠিক নয়।';
        }
        if ($tone === '' || mb_strlen($tone) > 200) {
            $errors[] = 'উত্তরের ধরন ১–২০০ অক্ষরের মধ্যে লিখুন।';
        }
        if (mb_strlen($contact) > 200) {
            $errors[] = 'যোগাযোগের তথ্য ২০০ অক্ষরের বেশি হবে না।';
        }
        if ($perUser === false) {
            $errors[] = 'প্রতি জনের ঘণ্টার সীমা ১ থেকে ৫০-এর মধ্যে দিন।';
        }
        if ($perDay === false) {
            $errors[] = 'দিনের সীমা ১ থেকে ৫০০০-এর মধ্যে দিন।';
        }

        if ($errors === []) {
            $new = [
                'bot_enabled' => isset($_POST['bot_enabled']) ? '1' : '0',
                'dry_run' => isset($_POST['dry_run']) ? '1' : '0',
                'ai_model' => $model,
                'ai_effort' => $effort,
                'reply_tone' => $tone,
                'handoff_contact' => $contact,
                'replies_per_user_per_hour' => (string) $perUser,
                'max_replies_per_day' => (string) $perDay,
            ];
            foreach ($new as $name => $value) {
                Settings::set($name, $value);
            }
            Logger::info('settings', 'Settings changed in admin panel', $new);
            $saved = true;
        }
    }
}

$current = [
    'bot_enabled' => Settings::botEnabled(),
    'dry_run' => Settings::dryRun(),
    'ai_model' => Settings::aiModel(),
    'ai_effort' => Settings::aiEffort(),
    'reply_tone' => Settings::replyTone(),
    'handoff_contact' => Settings::handoffContact(),
    'replies_per_user_per_hour' => Settings::repliesPerUserPerHour(),
    'max_replies_per_day' => Settings::maxRepliesPerDay(),
];
// Keep what the user typed when validation failed.
if ($errors !== []) {
    foreach (['ai_model', 'ai_effort', 'reply_tone', 'handoff_contact', 'replies_per_user_per_hour', 'max_replies_per_day'] as $k) {
        $current[$k] = (string) ($_POST[$k] ?? $current[$k]);
    }
    $current['bot_enabled'] = isset($_POST['bot_enabled']);
    $current['dry_run'] = isset($_POST['dry_run']);
}

$effortLabels = ['low' => 'কম (দ্রুত ও সস্তা)', 'medium' => 'মাঝারি', 'high' => 'বেশি (ধীর ও দামি)'];

View::header('সেটিংস', 'settings.php');
?>
<?php if ($saved): ?><div class="alert alert-success">সেভ হয়েছে। পরের কমেন্ট থেকে নতুন সেটিং কাজ করবে।</div><?php endif; ?>
<?php foreach ($errors as $error): ?><div class="alert alert-danger"><?= View::e($error) ?></div><?php endforeach; ?>

<form method="post" class="settings-form">
  <input type="hidden" name="csrf" value="<?= View::e(Auth::csrfToken()) ?>">

  <section class="card">
    <h3>বট</h3>
    <label class="toggle">
      <input type="checkbox" name="bot_enabled" <?= $current['bot_enabled'] ? 'checked' : '' ?>>
      <span class="toggle-ui"></span>
      <span><strong>বট চালু</strong><br><span class="muted">বন্ধ থাকলে নতুন কমেন্টে AI কাজ করবে না।</span></span>
    </label>
    <label class="toggle">
      <input type="checkbox" name="dry_run" <?= $current['dry_run'] ? 'checked' : '' ?>>
      <span class="toggle-ui"></span>
      <span><strong>DRY_RUN (পরীক্ষা মোড)</strong><br><span class="muted">চালু থাকলে উত্তর শুধু প্যানেলে দেখাবে, Facebook-এ পোস্ট হবে না।</span></span>
    </label>
  </section>

  <section class="card">
    <h3>AI মডেল</h3>
    <div class="model-options">
      <?php foreach (AiModels::all() as $id => $m): ?>
        <label class="model-option">
          <input type="radio" name="ai_model" value="<?= View::e($id) ?>" <?= $current['ai_model'] === $id ? 'checked' : '' ?>>
          <span class="model-card">
            <strong><?= View::e($m['label']) ?></strong>
            <span class="muted"><?= View::e($m['note']) ?></span>
            <code><?= View::e($id) ?></code>
          </span>
        </label>
      <?php endforeach; ?>
    </div>
    <p class="muted small">দাম প্রতি ১০ লাখ token-এ (input / output)। প্রতিটা উত্তরের আসল খরচ "কমেন্ট" পেজে দেখা যাবে।</p>

    <div class="field">
      <label for="ai_effort">চিন্তার মাত্রা (effort)</label>
      <select id="ai_effort" name="ai_effort">
        <?php foreach ($effortLabels as $value => $label): ?>
          <option value="<?= $value ?>" <?= $current['ai_effort'] === $value ? 'selected' : '' ?>><?= $label ?></option>
        <?php endforeach; ?>
      </select>
      <span class="muted small">কমেন্টের ছোট উত্তরের জন্য "কম" যথেষ্ট। Haiku 4.5-এ এটা প্রযোজ্য নয়।</span>
    </div>
  </section>

  <section class="card">
    <h3>উত্তরের ধরন</h3>
    <div class="field">
      <label for="reply_tone">টোন</label>
      <input type="text" id="reply_tone" name="reply_tone" maxlength="200" value="<?= View::e((string) $current['reply_tone']) ?>">
      <span class="muted small">যেমন: "বন্ধুসুলভ ও ভদ্র, ছোট করে" বা "প্রফেশনাল, আপনি সম্বোধনে"</span>
    </div>
    <div class="field">
      <label for="handoff_contact">মানুষের সাহায্য লাগলে যোগাযোগ</label>
      <input type="text" id="handoff_contact" name="handoff_contact" maxlength="200" value="<?= View::e((string) $current['handoff_contact']) ?>" placeholder="যেমন: WhatsApp 01XXXXXXXXX">
      <span class="muted small">AI উত্তর না জানলে এটা দেখাবে। খালি রাখলে "ইনবক্সে মেসেজ দিন" বলবে।</span>
    </div>
  </section>

  <section class="card">
    <h3>সীমা (খরচ নিয়ন্ত্রণ)</h3>
    <div class="field-row">
      <div class="field">
        <label for="replies_per_user_per_hour">প্রতি জনকে ঘণ্টায় সর্বোচ্চ উত্তর</label>
        <input type="number" id="replies_per_user_per_hour" name="replies_per_user_per_hour" min="1" max="50" value="<?= View::e((string) $current['replies_per_user_per_hour']) ?>">
      </div>
      <div class="field">
        <label for="max_replies_per_day">পেজে দিনে সর্বোচ্চ AI উত্তর</label>
        <input type="number" id="max_replies_per_day" name="max_replies_per_day" min="1" max="5000" value="<?= View::e((string) $current['max_replies_per_day']) ?>">
      </div>
    </div>
  </section>

  <div class="form-actions">
    <button type="submit" class="btn btn-primary"><?= View::icon('check') ?>সেভ করুন</button>
  </div>
</form>
<?php
View::footer();
