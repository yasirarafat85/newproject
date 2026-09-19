<?php
/**
 * ট্রান্সফার মডাল — চারটি ট্যাব: ডিপার্টমেন্ট · এজেন্ট · টিম · ছেড়ে দেওয়া।
 * নেটিভ <dialog> ব্যবহার করা হয়েছে, তাই ফোকাস ও Esc ব্রাউজারই সামলায়।
 */
$ticketId = (int) $ticket['id'];
?>
<dialog id="transfer-modal" class="hd-modal">
    <form method="post" action="<?= e(url('/agent/tickets/' . $ticketId . '/transfer')) ?>" data-once>
        <?= csrf_field() ?>

        <div class="hd-modal-head">
            <h3>টিকেট স্থানান্তর</h3>
            <button type="button" class="icon-btn" data-close-modal aria-label="বন্ধ করুন">
                <svg viewBox="0 0 24 24"><line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/></svg>
            </button>
        </div>

        <div class="hd-modal-tabs">
            <button type="button" class="hd-tab active" data-transfer-tab="department">ডিপার্টমেন্ট</button>
            <button type="button" class="hd-tab" data-transfer-tab="agent">এজেন্ট</button>
            <button type="button" class="hd-tab" data-transfer-tab="team">টিম</button>
            <button type="button" class="hd-tab" data-transfer-tab="release">ছেড়ে দিন</button>
        </div>

        <input type="hidden" name="target" id="transfer-target" value="department">

        <div class="hd-modal-body">

            <div class="transfer-pane" data-pane="department">
                <div class="field">
                    <label for="transfer-dept">কোন ডিপার্টমেন্টে</label>
                    <select class="input-hd" id="transfer-dept" name="dept_id">
                        <?php foreach ($allDepartments as $dept): ?>
                            <?php if ((int) $dept['id'] === (int) $ticket['dept_id']) { continue; } ?>
                            <option value="<?= (int) $dept['id'] ?>"><?= e($dept['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="hint">অ্যাসাইনমেন্ট ছেড়ে দেওয়া হবে; গন্তব্যের কৌশল অটো-অ্যাসাইন হলে সঙ্গে সঙ্গে নতুন একজনকে দেওয়া হবে</div>
                </div>

                <div class="field">
                    <label for="transfer-sla">SLA-র ঘড়ি</label>
                    <select class="input-hd" id="transfer-sla" name="sla_action">
                        <option value="keep">অপরিবর্তিত রাখুন — গ্রাহকের অপেক্ষা বাড়বে না</option>
                        <option value="extend">বাড়িয়ে দিন — নতুন দলের জন্য বাড়তি সময়</option>
                        <?php if ($mayResetSla): ?>
                            <option value="reset">নতুন করে শুরু — গন্তব্যের SLA দিয়ে</option>
                        <?php endif; ?>
                    </select>
                    <?php if (!$mayResetSla): ?>
                        <div class="hint">SLA নতুন করে শুরু করার অনুমতি শুধু ম্যানেজার ও অ্যাডমিনের</div>
                    <?php endif; ?>
                </div>
            </div>

            <div class="transfer-pane" data-pane="agent" hidden>
                <div class="field">
                    <label for="transfer-agent">কোন এজেন্টকে</label>
                    <select class="input-hd" id="transfer-agent" name="agent_id">
                        <?php $available = false; ?>
                        <?php foreach ($deptAgents as $agent): ?>
                            <?php if ((int) $agent['id'] === (int) ($ticket['assigned_agent_id'] ?? 0)) { continue; } ?>
                            <?php $available = true; ?>
                            <option value="<?= (int) $agent['id'] ?>">
                                <?= e($agent['name']) ?><?= (int) $agent['is_available'] === 0 ? ' (অটো-অ্যাসাইনে নেই)' : '' ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                    <div class="hint">
                        <?= $available
                            ? 'শুধু এই ডিপার্টমেন্টের সদস্যদের দেখানো হচ্ছে — নইলে তিনি টিকেটটি দেখতেই পাবেন না'
                            : 'এই ডিপার্টমেন্টে অন্য কোনো সক্রিয় এজেন্ট নেই' ?>
                    </div>
                </div>
            </div>

            <div class="transfer-pane" data-pane="team" hidden>
                <div class="field">
                    <label for="transfer-team">কোন টিমে</label>
                    <select class="input-hd" id="transfer-team" name="team_id">
                        <?php foreach ($allTeams as $team): ?>
                            <?php if ((int) $team['id'] === (int) ($ticket['assigned_team_id'] ?? 0)) { continue; } ?>
                            <option value="<?= (int) $team['id'] ?>"><?= e($team['name']) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <div class="hint">ব্যক্তিগত অ্যাসাইনমেন্ট ছেড়ে দেওয়া হবে — টিমের যে কেউ নিতে পারবেন</div>
                </div>
            </div>

            <div class="transfer-pane" data-pane="release" hidden>
                <div class="alert-hd alert-warning" style="margin:0">
                    <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <span>টিকেটটি আবার আনঅ্যাসাইনড কিউতে ফিরে যাবে, ডিপার্টমেন্ট অপরিবর্তিত থাকবে।</span>
                </div>
            </div>

            <div class="field" style="margin-bottom:0">
                <label for="transfer-reason">কারণ <span class="faint">(ঐচ্ছিক, তবে সহকর্মীদের কাজে লাগে)</span></label>
                <input class="input-hd" type="text" id="transfer-reason" name="reason" maxlength="250"
                       placeholder="যেমন: হার্ডওয়্যার সংক্রান্ত, কারিগরি দলের বিষয়">
            </div>
        </div>

        <div class="hd-modal-foot">
            <button type="button" class="btn-hd btn-ghost-hd" data-close-modal style="width:auto">বাতিল</button>
            <button type="submit" class="btn-hd btn-primary-hd" style="width:auto">স্থানান্তর করুন</button>
        </div>
    </form>
</dialog>
