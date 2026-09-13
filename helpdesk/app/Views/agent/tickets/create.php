<?php title('নতুন টিকেট'); ?>

<div style="max-width:760px">
    <form method="post" action="<?= e(url('/agent/tickets')) ?>" enctype="multipart/form-data" data-once>
        <?= csrf_field() ?>

        <div class="card-hd">
            <div class="card-hd-head"><h2>গ্রাহকের তথ্য</h2></div>
            <div class="card-hd-body">
                <div class="field">
                    <label for="name">নাম <span class="required">*</span></label>
                    <input class="input-hd" type="text" id="name" name="name" value="<?= e(old('name')) ?>" required>
                    <?php if ($m = error_for('name')): ?><span class="field-error"><?= e($m) ?></span><?php endif; ?>
                </div>
                <div class="field" style="margin-bottom:0">
                    <label for="email">ইমেইল <span class="required">*</span></label>
                    <input class="input-hd" type="email" id="email" name="email" value="<?= e(old('email')) ?>" required>
                    <div class="hint">এই ইমেইলে গ্রাহক থাকলে তার সঙ্গেই টিকেটটি যুক্ত হবে</div>
                    <?php if ($m = error_for('email')): ?><span class="field-error"><?= e($m) ?></span><?php endif; ?>
                </div>
            </div>
        </div>

        <div class="card-hd">
            <div class="card-hd-head"><h2>টিকেট</h2></div>
            <div class="card-hd-body">
                <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(200px,1fr));gap:16px">
                    <div class="field">
                        <label for="dept_id">ডিপার্টমেন্ট <span class="required">*</span></label>
                        <select class="input-hd" id="dept_id" name="dept_id" required>
                            <?php foreach ($departments as $dept): ?>
                                <option value="<?= (int) $dept['id'] ?>" <?= (int) old('dept_id') === (int) $dept['id'] ? 'selected' : '' ?>>
                                    <?= e($dept['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="priority_id">প্রায়োরিটি</label>
                        <select class="input-hd" id="priority_id" name="priority_id">
                            <?php foreach ($priorities as $priority): ?>
                                <option value="<?= (int) $priority['id'] ?>" <?= (int) old('priority_id') === (int) $priority['id'] ? 'selected' : '' ?>>
                                    <?= e($priority['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="field">
                        <label for="topic_id">বিষয়ের ধরন</label>
                        <select class="input-hd" id="topic_id" name="topic_id">
                            <option value="">— নেই —</option>
                            <?php foreach ($topics as $topic): ?>
                                <option value="<?= (int) $topic['id'] ?>" <?= (int) old('topic_id') === (int) $topic['id'] ? 'selected' : '' ?>>
                                    <?= e($topic['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                </div>

                <div class="field">
                    <label for="subject">বিষয় <span class="required">*</span></label>
                    <input class="input-hd" type="text" id="subject" name="subject" value="<?= e(old('subject')) ?>" required maxlength="250">
                    <?php if ($m = error_for('subject')): ?><span class="field-error"><?= e($m) ?></span><?php endif; ?>
                </div>

                <div class="field">
                    <label for="body">বিবরণ <span class="required">*</span></label>
                    <textarea class="input-hd" id="body" name="body" rows="7" required><?= e(old('body')) ?></textarea>
                    <?php if ($m = error_for('body')): ?><span class="field-error"><?= e($m) ?></span><?php endif; ?>
                </div>

                <div class="field" style="margin-bottom:0">
                    <label for="attachments">ফাইল</label>
                    <input class="input-hd" type="file" id="attachments" name="attachments[]" multiple>
                </div>
            </div>
        </div>

        <div style="display:flex;gap:12px;margin-top:20px">
            <button type="submit" class="btn-hd btn-primary-hd" style="width:auto">টিকেট তৈরি করুন</button>
            <a href="<?= e(url('/agent/tickets')) ?>" class="btn-hd btn-ghost-hd" style="width:auto">বাতিল</a>
        </div>
    </form>
</div>
