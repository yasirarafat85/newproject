<?php
use App\Core\View;

title('#' . $ticket['number']);
?>

<div style="max-width:820px;margin:0 auto">
    <a href="<?= e(url('/tickets')) ?>" class="muted" style="font-size:13.5px">← আমার টিকেট</a>

    <div style="display:flex;align-items:flex-start;gap:16px;margin:14px 0 24px;flex-wrap:wrap">
        <div style="flex:1;min-width:240px">
            <h1 style="font-size:23px;margin:0 0 8px"><?= e($ticket['subject']) ?></h1>
            <div class="mono faint" style="font-size:12.5px">
                #<?= e($ticket['number']) ?> · <?= e($ticket['dept_name']) ?> · <?= e(format_date($ticket['created_at'])) ?>
            </div>
        </div>
        <div style="display:flex;gap:8px;flex-wrap:wrap">
            <?= View::partial('partials/ticket_badges', ['name' => $ticket['status_name'], 'color' => $ticket['status_color']]) ?>
            <?= View::partial('partials/ticket_badges', ['kind' => 'priority', 'name' => $ticket['priority_name'], 'color' => $ticket['priority_color']]) ?>
        </div>
    </div>

    <?php foreach ($threads as $thread): ?>
        <?php $fromAgent = $thread['type'] === 'response'; ?>
        <div class="card-hd" style="margin-bottom:14px;<?= $fromAgent ? 'border-color:var(--primary)' : '' ?>">
            <div class="card-hd-head" style="<?= $fromAgent ? 'background:var(--primary-soft)' : '' ?>">
                <span class="avatar sm" style="<?= $fromAgent ? '' : 'background:var(--surface-2);color:var(--text-muted)' ?>">
                    <?= e(App\Core\Str::initials((string) ($fromAgent ? ($thread['agent_name'] ?? 'সহায়তা') : ($thread['user_name'] ?? 'আপনি')))) ?>
                </span>
                <div>
                    <div style="font-size:14px;font-weight:600">
                        <?= e($fromAgent ? (($thread['agent_name'] ?? 'সহায়তা দল') . ' — সহায়তা দল') : ($thread['user_name'] ?? 'আপনি')) ?>
                    </div>
                    <div class="faint" style="font-size:11.5px"><?= e(format_date($thread['created_at'])) ?> · <?= e(time_ago($thread['created_at'])) ?></div>
                </div>
            </div>
            <div class="card-hd-body">
                <div class="rich-text"><?= $thread['body_html'] ?></div>
                <?= View::partial('partials/attachments', ['files' => $attachments[(int) $thread['id']] ?? []]) ?>
            </div>
        </div>
    <?php endforeach; ?>

    <?php if ($canReply): ?>
        <div class="card-hd" style="margin-top:24px">
            <div class="card-hd-head"><h2>উত্তর দিন</h2></div>
            <div class="card-hd-body">
                <form method="post" action="<?= e(url('/tickets/' . $ticket['number'] . '/reply')) ?>"
                      enctype="multipart/form-data" data-once>
                    <?= csrf_field() ?>
                    <div class="field">
                        <label for="body" class="small-label">আপনার বার্তা</label>
                        <textarea class="input-hd" id="body" name="body" rows="5" required
                                  placeholder="নতুন কোনো তথ্য থাকলে লিখুন…"><?= e(old('body')) ?></textarea>
                    </div>
                    <div class="field">
                        <label for="attachments" class="small-label">ফাইল সংযুক্ত করুন</label>
                        <input class="input-hd" type="file" id="attachments" name="attachments[]" multiple>
                    </div>
                    <button type="submit" class="btn-hd btn-primary-hd" style="width:auto">
                        <svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                        উত্তর পাঠান
                    </button>
                </form>
            </div>
        </div>
    <?php else: ?>
        <div class="alert-hd alert-info" style="margin-top:24px">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
            <span>এই টিকেটটি বন্ধ হয়ে গেছে এবং আর উত্তর নেওয়া হচ্ছে না।
                <a href="<?= e(url('/tickets/new')) ?>">নতুন টিকেট খুলুন</a>।</span>
        </div>
    <?php endif; ?>
</div>
