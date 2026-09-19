<?php
declare(strict_types=1);

namespace App\Controllers\Admin;

use App\Controllers\Controller;
use App\Core\QueryBuilder;
use App\Core\Request;
use App\Core\Response;
use App\Services\AuditService;

/**
 * সিস্টেম সেটিংস — settings টেবিলের কী/মান জোড়াগুলো।
 *
 * ফর্ম ফিল্ডের ধরন value_type থেকে আসে, তাই নতুন সেটিং যোগ করলে
 * এখানে কোড বদলানোর দরকার পড়ে না।
 */
final class SettingController extends Controller
{
    /** গ্রুপের নাম ও ব্যাখ্যা — settings টেবিলে শুধু কোড থাকে। */
    private const GROUPS = [
        'general'  => ['label' => 'সাধারণ', 'hint' => 'প্রতিষ্ঠানের নাম ও পোর্টালের পরিচয়'],
        'ticket'   => ['label' => 'টিকেট', 'hint' => 'টিকেট খোলা, বন্ধ হওয়া ও তালিকার নিয়ম'],
        'security' => ['label' => 'নিরাপত্তা', 'hint' => 'অপব্যবহার ঠেকানো ও অ্যাকাউন্ট সুরক্ষা'],
    ];

    /** প্রতিটি সেটিংয়ের পাঠযোগ্য নাম ও সাহায্য-বার্তা। */
    private const LABELS = [
        'company_name'             => ['প্রতিষ্ঠানের নাম', 'পোর্টাল ও ইমেইলে দেখা যাবে'],
        'support_email'            => ['সাপোর্ট ইমেইল', 'গ্রাহকের সঙ্গে যোগাযোগের ঠিকানা'],
        'portal_tagline'           => ['পোর্টালের ট্যাগলাইন', 'হোম পাতার বড় লেখার নিচে'],
        'allow_guest_tickets'      => ['অ্যাকাউন্ট ছাড়া টিকেট খোলা যাবে', 'বন্ধ করলে আগে লগইন করতে হবে'],
        'allow_registration'       => ['নতুন গ্রাহক নিবন্ধন খোলা', 'বন্ধ করলে শুধু এজেন্টই অ্যাকাউন্ট বানাতে পারবেন'],
        'notify_client_on_transfer'=> ['ট্রান্সফারে গ্রাহককে জানানো', 'সাধারণত বন্ধ রাখাই ভালো — এটি অভ্যন্তরীণ বিষয়'],
        'default_page_size'        => ['প্রতি পাতায় টিকেট', 'এজেন্ট কিউতে'],
        'auto_close_resolved_days' => ['সমাধানের কত দিন পর ক্লোজ', 'P5-এ ক্রন এটি ব্যবহার করবে'],
        'reopen_window_days'       => ['ক্লোজের কত দিন পর্যন্ত reopen', 'এর পর গ্রাহককে নতুন টিকেট খুলতে হবে'],
        'max_attachment_mb'        => ['সর্বোচ্চ ফাইল আকার (MB)', '.env-এর UPLOAD_MAX_MB এর চেয়ে বড় হতে পারবে না'],
        'guest_tickets_per_hour'   => ['ঘণ্টায় গেস্ট টিকেট সীমা', 'প্রতি IP-তে'],
        'require_agent_2fa'        => ['এজেন্টদের 2FA বাধ্যতামূলক', 'P8-এ কার্যকর হবে'],
    ];

    public function index(Request $request): Response
    {
        $this->authorize('admin.settings');

        $settings = QueryBuilder::table('settings')->orderBy('group_name')->orderBy('setting_key')->get();

        $grouped = [];
        foreach ($settings as $setting) {
            $group = (string) $setting['group_name'];
            $key = (string) $setting['setting_key'];

            $grouped[$group][] = $setting + [
                'label' => self::LABELS[$key][0] ?? $key,
                'hint'  => self::LABELS[$key][1] ?? '',
            ];
        }

        return $this->view('admin/settings/index', [
            'grouped' => $grouped,
            'groups'  => self::GROUPS,
        ], 'layouts/agent');
    }

    public function update(Request $request): Response
    {
        $this->authorize('admin.settings');

        $submitted = (array) $request->raw('settings', []);
        $changed = 0;

        foreach (QueryBuilder::table('settings')->get() as $setting) {
            $key = (string) $setting['setting_key'];

            // চেকবক্স আনচেক করলে ব্রাউজার কিছুই পাঠায় না, তাই bool আলাদাভাবে সামলাই
            $value = $setting['value_type'] === 'bool'
                ? (isset($submitted[$key]) ? '1' : '0')
                : (array_key_exists($key, $submitted) ? trim((string) $submitted[$key]) : null);

            if ($value === null || $value === (string) $setting['setting_value']) {
                continue;
            }

            $value = $this->castValue($value, (string) $setting['value_type']);

            QueryBuilder::table('settings')->where('setting_key', $key)->update([
                'setting_value' => $value,
                'updated_at'    => now(),
            ]);

            $changed++;
        }

        AuditService::log('settings.updated', 'settings', null, "{$changed}টি সেটিং বদলানো হয়েছে");
        flash('success', $changed === 0 ? 'কিছু বদলায়নি।' : "{$changed}টি সেটিং সংরক্ষণ করা হয়েছে।");

        return $this->redirect('/admin/settings');
    }

    /** ধরন অনুযায়ী মান স্বাভাবিক করা — সংখ্যার ঘরে যেন লেখা না ঢোকে। */
    private function castValue(string $value, string $type): string
    {
        return match ($type) {
            'int'  => (string) max(0, (int) $value),
            'bool' => $value === '1' ? '1' : '0',
            default => mb_substr($value, 0, 1000),
        };
    }
}
