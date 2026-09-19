<?php
/** বাইরের ইমেইলে ফরওয়ার্ড — উত্তর ট্র্যাকিং টোকেন সহ। */
use App\Core\View;

$ticketId = (int) $ticket['id'];
?>
<dialog id="forward-modal" class="hd-modal">
    <form method="post" action="<?= e(url('/agent/tickets/' . $ticketId . '/forward')) ?>" data-once>
        <?= csrf_field() ?>

        <div class="hd-modal-head">
            <h3>বাইরে ফরওয়ার্ড করুন</h3>
            <button type="button" class="icon-btn" data-close-modal aria-label="বন্ধ করুন">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <div class="hd-modal-body">
            <div class="alert-hd alert-info">
                <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="16" x2="12" y2="12"/><line x1="12" y1="8" x2="12.01" y2="8"/></svg>
                <span>প্রাপকের উত্তর স্বয়ংক্রিয়ভাবে এই টিকেটে <strong>ইন্টার্নাল নোট</strong> হিসেবে ফিরে আসবে —
                      গ্রাহক তা দেখবেন না।</span>
            </div>

            <div class="field">
                <label for="forward-to">প্রাপকের ইমেইল <span class="required">*</span></label>
                <input class="input-hd" type="email" id="forward-to" name="to_email" required placeholder="vendor@example.com">
            </div>

            <div class="field">
                <label for="forward-cc">অনুলিপি <span class="faint">(কমা দিয়ে আলাদা করুন)</span></label>
                <input class="input-hd" type="text" id="forward-cc" name="cc_emails" placeholder="a@example.com, b@example.com">
            </div>

            <div class="field">
                <label for="forward-body">বার্তা <span class="required">*</span></label>
                <textarea class="input-hd" id="forward-body" name="body" rows="5" required
                          placeholder="প্রাপককে কী জানাতে চান…"></textarea>
            </div>

            <?= View::partial('partials/toggle', [
                'name' => 'include_history', 'id' => 'fwd_history',
                'label' => 'আগের কথোপকথন যুক্ত করুন',
                'hint' => 'গ্রাহক ও এজেন্টের বার্তাগুলো (ইন্টার্নাল নোট নয়)',
            ]) ?>
            <?= View::partial('partials/toggle', [
                'name' => 'include_attachments', 'id' => 'fwd_files',
                'label' => 'সংযুক্ত ফাইলগুলোও পাঠান',
            ]) ?>
        </div>

        <div class="hd-modal-foot">
            <button type="button" class="btn-hd btn-ghost-hd" data-close-modal style="width:auto">বাতিল</button>
            <button type="submit" class="btn-hd btn-info-hd" style="width:auto">
                <svg viewBox="0 0 24 24"><polyline points="15 17 20 12 15 7"/><path d="M4 18v-2a4 4 0 014-4h12"/></svg>
                ফরওয়ার্ড করুন
            </button>
        </div>
    </form>
</dialog>
