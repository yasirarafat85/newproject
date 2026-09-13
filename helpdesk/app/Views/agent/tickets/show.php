<?php
use App\Core\Auth;
use App\Core\Str;
use App\Core\View;

title('#' . $ticket['number']);

$ticketId  = (int) $ticket['id'];
$isLate    = $ticket['due_at'] !== null
          && strtotime((string) $ticket['due_at']) < time()
          && in_array($ticket['status_state'], ['open', 'paused'], true);
$isMine    = (int) ($ticket['assigned_agent_id'] ?? 0) === (int) Auth::agentId();
$isClosed  = in_array($ticket['status_state'], ['resolved', 'closed', 'archived'], true);
?>

<div class="ticket-head">
    <div style="min-width:0;flex:1">
        <a href="<?= e(url('/agent/tickets')) ?>" class="muted" style="font-size:13px">← কিউতে ফিরুন</a>
        <h1 style="font-size:21px;margin:8px 0 6px"><?= e($ticket['subject']) ?></h1>
        <div class="mono faint" style="font-size:12.5px">
            #<?= e($ticket['number']) ?> · <?= e($ticket['dept_name']) ?> ·
            <?= e(format_date($ticket['created_at'])) ?>
            <?php if ((int) $ticket['reopen_count'] > 0): ?>
                · <?= (int) $ticket['reopen_count'] ?> বার পুনরায় খোলা
            <?php endif; ?>
        </div>
    </div>
    <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap">
        <?= View::partial('partials/ticket_badges', ['kind' => 'priority', 'name' => $ticket['priority_name'], 'color' => $ticket['priority_color']]) ?>
        <?= View::partial('partials/ticket_badges', ['name' => $ticket['status_name'], 'color' => $ticket['status_color']]) ?>
        <?php if ($ticket['due_at'] !== null && !$isClosed): ?>
            <span class="badge-hd" style="background:<?= $isLate ? 'var(--danger-soft);color:var(--danger)' : 'var(--surface-2);color:var(--text-muted)' ?>">
                <svg viewBox="0 0 24 24" style="width:13px;height:13px;stroke:currentColor;fill:none;stroke-width:2">
                    <circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>
                </svg>
                <?= e(time_ago($ticket['due_at'])) ?>
            </span>
        <?php endif; ?>
    </div>
</div>

<div class="ticket-grid">

    <!-- ===== বাম: প্রপার্টি ===== -->
    <aside class="ticket-side">
        <div class="card-hd">
            <div class="card-hd-head"><h3>গ্রাহক</h3></div>
            <div class="card-hd-body">
                <div style="display:flex;align-items:center;gap:10px;margin-bottom:14px">
                    <span class="avatar"><?= e(Str::initials((string) $ticket['user_name'])) ?></span>
                    <div style="min-width:0">
                        <div style="font-weight:600;font-size:14px"><?= e($ticket['user_name']) ?></div>
                        <div class="faint" style="font-size:12px;overflow:hidden;text-overflow:ellipsis"><?= e($ticket['user_email']) ?></div>
                    </div>
                </div>
                <?php if (($ticket['user_phone'] ?? '') !== ''): ?>
                    <div class="prop-row"><span>ফোন</span><b><?= e($ticket['user_phone']) ?></b></div>
                <?php endif; ?>
                <div class="prop-row"><span>উৎস</span><b><?= e(match ($ticket['source']) {
                    'email' => 'ইমেইল', 'phone' => 'ফোন', 'api' => 'API', 'agent' => 'এজেন্ট', default => 'ওয়েব',
                }) ?></b></div>

                <?php if ($userTickets !== []): ?>
                    <div class="small-label" style="margin:16px 0 8px">আগের টিকেট</div>
                    <?php foreach ($userTickets as $other): ?>
                        <a href="<?= e(url('/agent/tickets/' . $other['id'])) ?>"
                           style="display:block;font-size:13px;padding:5px 0;border-bottom:1px solid var(--border)">
                            <?= e(Str::limit((string) $other['subject'], 40)) ?>
                            <span class="faint mono" style="font-size:11px">#<?= e($other['number']) ?></span>
                        </a>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>

        <div class="card-hd">
            <div class="card-hd-head"><h3>প্রপার্টি</h3></div>
            <div class="card-hd-body">
                <div class="prop-row"><span>ডিপার্টমেন্ট</span><b><?= e($ticket['dept_name']) ?></b></div>
                <div class="prop-row"><span>দায়িত্বে</span><b><?= e($ticket['agent_name'] ?? 'কেউ নয়') ?></b></div>
                <div class="prop-row"><span>তৈরি</span><b><?= e(time_ago($ticket['created_at'])) ?></b></div>
                <div class="prop-row"><span>শেষ বার্তা</span><b><?= e(time_ago($ticket['last_message_at'])) ?></b></div>
                <div class="prop-row">
                    <span>প্রথম উত্তর</span>
                    <b><?= $ticket['first_response_at'] === null ? 'এখনো হয়নি' : e(time_ago($ticket['first_response_at'])) ?></b>
                </div>

                <?php if (Auth::can('ticket.change_priority', $ticket)): ?>
                    <form method="post" action="<?= e(url('/agent/tickets/' . $ticketId . '/priority')) ?>" style="margin-top:14px">
                        <?= csrf_field() ?>
                        <label class="small-label" for="priority_id">প্রায়োরিটি বদলান</label>
                        <select class="input-hd" id="priority_id" name="priority_id" onchange="this.form.submit()" style="padding:8px 10px;font-size:13.5px">
                            <?php foreach ($priorities as $priority): ?>
                                <option value="<?= (int) $priority['id'] ?>" <?= (int) $ticket['priority_id'] === (int) $priority['id'] ? 'selected' : '' ?>>
                                    <?= e($priority['name']) ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                    </form>
                <?php endif; ?>
            </div>
        </div>
    </aside>

    <!-- ===== মাঝ: কথোপকথন ===== -->
    <section class="ticket-thread">
        <?php foreach ($threads as $thread): ?>
            <?php
            $type = $thread['type'];
            $author = $thread['agent_name'] ?? $thread['user_name'] ?? 'সিস্টেম';
            ?>

            <?php if ($type === 'system'): ?>
                <div class="thread-system">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                    <span><?= strip_tags($thread['body_html']) ?></span>
                    <span class="faint" style="margin-left:auto;white-space:nowrap"><?= e(time_ago($thread['created_at'])) ?></span>
                </div>
                <?php continue; ?>
            <?php endif; ?>

            <div class="card-hd thread-card thread-<?= e($type) ?>">
                <div class="card-hd-head">
                    <span class="avatar sm"><?= e(Str::initials((string) $author)) ?></span>
                    <div style="min-width:0">
                        <div style="font-size:13.5px;font-weight:600">
                            <?= e($author) ?>
                            <?php if ($type === 'note'): ?>
                                <span class="badge-hd" style="background:var(--warning-soft);color:var(--warning);margin-left:6px">🔒 ইন্টার্নাল নোট</span>
                            <?php elseif ($type === 'forward'): ?>
                                <span class="badge-hd" style="background:var(--info-soft);color:var(--info);margin-left:6px">ফরওয়ার্ড</span>
                            <?php elseif ($type === 'message'): ?>
                                <span class="faint" style="font-weight:400;font-size:12px">— গ্রাহক</span>
                            <?php endif; ?>
                        </div>
                        <div class="faint" style="font-size:11.5px"><?= e(format_date($thread['created_at'])) ?></div>
                    </div>
                </div>
                <div class="card-hd-body">
                    <div class="rich-text"><?= $thread['body_html'] ?></div>
                    <?= View::partial('partials/attachments', ['files' => $attachments[(int) $thread['id']] ?? []]) ?>
                </div>
            </div>
        <?php endforeach; ?>

        <?php if (Auth::can('ticket.reply', $ticket) || Auth::can('ticket.note', $ticket)): ?>
            <div class="card-hd" id="composer">
                <div class="card-hd-head" style="gap:0;padding:0">
                    <button type="button" class="composer-tab active" data-pane="reply">উত্তর দিন</button>
                    <?php if (Auth::can('ticket.note', $ticket)): ?>
                        <button type="button" class="composer-tab" data-pane="note">ইন্টার্নাল নোট</button>
                    <?php endif; ?>
                </div>

                <?php if (Auth::can('ticket.reply', $ticket)): ?>
                <div class="card-hd-body composer-pane" data-pane="reply">
                    <form method="post" action="<?= e(url('/agent/tickets/' . $ticketId . '/reply')) ?>"
                          enctype="multipart/form-data" data-once>
                        <?= csrf_field() ?>

                        <?php if ($canned !== []): ?>
                            <div class="field">
                                <label class="small-label" for="canned-picker">তৈরি উত্তর বসান</label>
                                <select class="input-hd" id="canned-picker" data-canned-for="reply-body" style="padding:8px 10px;font-size:13.5px">
                                    <option value="">— বেছে নিন —</option>
                                    <?php foreach ($canned as $item): ?>
                                        <option value="<?= e($item['body_html']) ?>"><?= e($item['title']) ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                        <?php endif; ?>

                        <div class="field">
                            <textarea class="input-hd" id="reply-body" name="body" rows="6" required
                                      placeholder="গ্রাহককে যা লিখতে চান…"></textarea>
                        </div>
                        <div class="field">
                            <label class="small-label" for="reply-files">ফাইল সংযুক্ত করুন</label>
                            <input class="input-hd" type="file" id="reply-files" name="attachments[]" multiple>
                        </div>
                        <div style="display:flex;gap:10px;align-items:center;flex-wrap:wrap">
                            <label class="small-label" for="reply-status" style="margin:0">পাঠানোর পর স্ট্যাটাস</label>
                            <select class="input-hd" id="reply-status" name="status_id" style="width:auto;padding:8px 10px;font-size:13.5px">
                                <?php foreach ($statuses as $status): ?>
                                    <?php if ($status['state'] === 'archived') { continue; } ?>
                                    <option value="<?= (int) $status['id'] ?>" <?= $status['state'] === 'paused' && $status['sort_order'] == 3 ? 'selected' : '' ?>>
                                        <?= e($status['name']) ?>
                                    </option>
                                <?php endforeach; ?>
                            </select>
                            <button type="submit" class="btn-hd btn-primary-hd" style="width:auto;margin-left:auto">
                                <svg viewBox="0 0 24 24"><line x1="22" y1="2" x2="11" y2="13"/><polygon points="22 2 15 22 11 13 2 9 22 2"/></svg>
                                উত্তর পাঠান
                            </button>
                        </div>
                    </form>
                </div>
                <?php endif; ?>

                <?php if (Auth::can('ticket.note', $ticket)): ?>
                <div class="card-hd-body composer-pane" data-pane="note" hidden>
                    <form method="post" action="<?= e(url('/agent/tickets/' . $ticketId . '/note')) ?>"
                          enctype="multipart/form-data" data-once>
                        <?= csrf_field() ?>
                        <div class="alert-hd alert-warning" style="margin-bottom:14px">
                            <svg viewBox="0 0 24 24"><rect x="3" y="11" width="18" height="11" rx="2"/><path d="M7 11V7a5 5 0 0110 0v4"/></svg>
                            <span>নোট শুধু এজেন্টরাই দেখবেন — গ্রাহকের কাছে যাবে না।</span>
                        </div>
                        <div class="field">
                            <textarea class="input-hd" id="note-body" name="body" rows="5" required
                                      placeholder="সহকর্মীদের জন্য নোট…"></textarea>
                        </div>
                        <div class="field">
                            <label class="small-label" for="note-files">ফাইল সংযুক্ত করুন</label>
                            <input class="input-hd" type="file" id="note-files" name="attachments[]" multiple>
                        </div>
                        <button type="submit" class="btn-hd btn-warning-hd" style="width:auto">নোট সংরক্ষণ করুন</button>
                    </form>
                </div>
                <?php endif; ?>
            </div>
        <?php endif; ?>
    </section>

    <!-- ===== ডান: অ্যাকশন ===== -->
    <aside class="ticket-side">
        <div class="card-hd">
            <div class="card-hd-head"><h3>অ্যাকশন</h3></div>
            <div class="card-hd-body" style="display:flex;flex-direction:column;gap:10px">
                <?php if (!$isMine && Auth::can('ticket.reply', $ticket)): ?>
                    <form method="post" action="<?= e(url('/agent/tickets/' . $ticketId . '/claim')) ?>">
                        <?= csrf_field() ?>
                        <button type="submit" class="btn-hd btn-success-hd btn-block">
                            <svg viewBox="0 0 24 24"><polyline points="20 6 9 17 4 12"/></svg>
                            আমি নিচ্ছি
                        </button>
                    </form>
                <?php endif; ?>

                <?php foreach ($statuses as $status): ?>
                    <?php
                    // যে স্ট্যাটাসে ইতিমধ্যে আছে, সেটির বোতাম দেখানোর মানে নেই
                    if ((int) $ticket['status_id'] === (int) $status['id'] || $status['state'] === 'archived') {
                        continue;
                    }
                    if (!in_array($status['state'], ['resolved', 'closed'], true)) {
                        continue;
                    }
                    if (!Auth::can('ticket.close', $ticket)) {
                        continue;
                    }
                    ?>
                    <form method="post" action="<?= e(url('/agent/tickets/' . $ticketId . '/status')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="status_id" value="<?= (int) $status['id'] ?>">
                        <button type="submit" class="btn-hd btn-ghost-hd btn-block"><?= e($status['name']) ?> হিসেবে চিহ্নিত করুন</button>
                    </form>
                <?php endforeach; ?>

                <?php if ($isClosed && Auth::can('ticket.reopen', $ticket)): ?>
                    <form method="post" action="<?= e(url('/agent/tickets/' . $ticketId . '/status')) ?>">
                        <?= csrf_field() ?>
                        <input type="hidden" name="status_id" value="<?= (int) App\Services\TicketService::statusIdForState('open') ?>">
                        <button type="submit" class="btn-hd btn-info-hd btn-block">আবার খুলুন</button>
                    </form>
                <?php endif; ?>

                <?php if (Auth::can('ticket.transfer', $ticket)): ?>
                    <button type="button" class="btn-hd btn-ghost-hd btn-block" disabled
                            title="P3-এ যুক্ত হবে">ট্রান্সফার / ফরওয়ার্ড</button>
                <?php endif; ?>
            </div>
        </div>

        <div class="card-hd">
            <div class="card-hd-head"><h3>টাইমলাইন</h3></div>
            <div class="card-hd-body">
                <?php if ($transfers === []): ?>
                    <p class="muted" style="font-size:13px;margin:0">এখনো কোনো হস্তান্তর হয়নি।</p>
                <?php else: ?>
                    <?php foreach ($transfers as $transfer): ?>
                        <div class="prop-row" style="align-items:flex-start">
                            <span style="font-size:12.5px">
                                <?= e(match ($transfer['transfer_type']) {
                                    'department' => 'ডিপার্টমেন্ট বদল',
                                    'agent'      => 'এজেন্ট বদল',
                                    'team'       => 'টিমে দেওয়া',
                                    'escalation' => 'এস্কেলেশন',
                                    'claim'      => 'দায়িত্ব নেওয়া',
                                    default      => 'ছেড়ে দেওয়া',
                                }) ?>
                                <?php if ((int) $transfer['is_automatic'] === 1): ?>
                                    <span class="faint">(স্বয়ংক্রিয়)</span>
                                <?php endif; ?>
                            </span>
                            <b style="font-size:11.5px;text-align:right">
                                <?= e($transfer['by_name'] ?? 'সিস্টেম') ?><br>
                                <span class="faint"><?= e(time_ago($transfer['created_at'])) ?></span>
                            </b>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </aside>
</div>

<script type="module" src="<?= e(asset('js/modules/ticket-view.js')) ?>"></script>
