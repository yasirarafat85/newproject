<?php
use App\Core\Auth;
use App\Core\View;

title('নতুন টিকেট');
$isGuest = !Auth::isClient();
?>

<div style="max-width:720px;margin:0 auto">
    <h1 style="font-size:26px;margin-bottom:6px">নতুন টিকেট খুলুন</h1>
    <p class="muted" style="margin-bottom:28px">যত বিস্তারিত লিখবেন, তত দ্রুত সমাধান পাবেন।</p>

    <form method="post" action="<?= e(url('/tickets')) ?>" enctype="multipart/form-data" data-once>
        <?= csrf_field() ?>

        <div class="card-hd">
            <div class="card-hd-body">
                <?php if ($isGuest): ?>
                    <div class="field">
                        <label for="name">আপনার নাম <span class="required">*</span></label>
                        <input class="input-hd <?= error_for('name') ? 'is-invalid' : '' ?>" type="text" id="name" name="name"
                               value="<?= e(old('name')) ?>" required autocomplete="name">
                        <?php if ($m = error_for('name')): ?><span class="field-error"><?= e($m) ?></span><?php endif; ?>
                    </div>
                    <div class="field">
                        <label for="email">ইমেইল <span class="required">*</span></label>
                        <input class="input-hd <?= error_for('email') ? 'is-invalid' : '' ?>" type="email" id="email" name="email"
                               value="<?= e(old('email')) ?>" required autocomplete="email">
                        <div class="hint">টিকেটের অগ্রগতি এই ঠিকানাতেই জানানো হবে</div>
                        <?php if ($m = error_for('email')): ?><span class="field-error"><?= e($m) ?></span><?php endif; ?>
                    </div>
                <?php endif; ?>

                <?php if ($topics !== []): ?>
                    <div class="field">
                        <label for="topic_id">বিষয়ের ধরন</label>
                        <select class="input-hd" id="topic_id" name="topic_id">
                            <option value="">— বেছে নিন —</option>
                            <?php foreach ($topics as $topic): ?>
                                <option value="<?= (int) $topic['id'] ?>"
                                    <?= (int) old('topic_id', $selectedTopic) === (int) $topic['id'] ? 'selected' : '' ?>>
                                    <?= e($topic['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <div class="hint">সঠিক ধরন বাছলে টিকেটটি দ্রুত সঠিক বিভাগে পৌঁছায়</div>
                    </div>
                <?php endif; ?>

                <div class="field">
                    <label for="subject">বিষয় <span class="required">*</span></label>
                    <input class="input-hd <?= error_for('subject') ? 'is-invalid' : '' ?>" type="text" id="subject" name="subject"
                           value="<?= e(old('subject')) ?>" required maxlength="250"
                           placeholder="এক লাইনে সমস্যাটি লিখুন">
                    <?php if ($m = error_for('subject')): ?><span class="field-error"><?= e($m) ?></span><?php endif; ?>
                </div>

                <div class="field">
                    <label for="body">বিস্তারিত বিবরণ <span class="required">*</span></label>
                    <textarea class="input-hd <?= error_for('body') ? 'is-invalid' : '' ?>" id="body" name="body"
                              required rows="8" placeholder="কী হয়েছে, কখন হয়েছে, কী কী চেষ্টা করেছেন…"><?= e(old('body')) ?></textarea>
                    <?php if ($m = error_for('body')): ?><span class="field-error"><?= e($m) ?></span><?php endif; ?>
                </div>

                <div class="field" style="margin-bottom:0">
                    <label for="attachments">ফাইল সংযুক্ত করুন <span class="faint">(ঐচ্ছিক)</span></label>
                    <input class="input-hd" type="file" id="attachments" name="attachments[]" multiple>
                    <div class="hint">স্ক্রিনশট থাকলে সমস্যা বুঝতে সুবিধা হয় · সর্বোচ্চ <?= e($maxUpload) ?> প্রতি ফাইল</div>
                </div>
            </div>
        </div>

        <div style="display:flex;gap:12px;margin-top:24px;flex-wrap:wrap">
            <button type="submit" class="btn-hd btn-primary-hd" style="width:auto">
                <svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                টিকেট জমা দিন
            </button>
            <a href="<?= e(url('/')) ?>" class="btn-hd btn-ghost-hd" style="width:auto">বাতিল</a>
        </div>
    </form>
</div>
