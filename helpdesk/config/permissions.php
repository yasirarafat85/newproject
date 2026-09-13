<?php
declare(strict_types=1);

/**
 * সিস্টেমের সব পারমিশন কোড ও ডিফল্ট রোল।
 * ইনস্টলার এখান থেকেই permissions / roles / role_permissions সিড করে।
 */
return [
    'groups' => [
        'ticket' => [
            'label' => 'টিকেট',
            'codes' => [
                'ticket.view_own'         => 'শুধু নিজের অ্যাসাইনড টিকেট দেখা',
                'ticket.view_dept'        => 'নিজের ডিপার্টমেন্টের টিকেট দেখা',
                'ticket.view_all'         => 'সব টিকেট দেখা',
                'ticket.create'           => 'টিকেট তৈরি',
                'ticket.edit'             => 'টিকেটের তথ্য সম্পাদনা',
                'ticket.reply'            => 'গ্রাহককে উত্তর',
                'ticket.note'             => 'ইন্টার্নাল নোট',
                'ticket.assign'           => 'এজেন্ট / টিম অ্যাসাইন',
                'ticket.transfer'         => 'ডিপার্টমেন্ট ট্রান্সফার',
                'ticket.forward_external' => 'বাইরের ইমেইলে ফরওয়ার্ড',
                'ticket.change_priority'  => 'প্রায়োরিটি বদল',
                'ticket.close'            => 'টিকেট ক্লোজ',
                'ticket.reopen'           => 'টিকেট পুনরায় খোলা',
                'ticket.merge'            => 'টিকেট মার্জ',
                'ticket.delete'           => 'টিকেট মুছে ফেলা',
                'ticket.mass_action'      => 'একসাথে একাধিক টিকেটে কাজ',
                'ticket.export'           => 'টিকেট এক্সপোর্ট',
            ],
        ],
        'user' => [
            'label' => 'গ্রাহক',
            'codes' => [
                'user.view'       => 'গ্রাহক তালিকা দেখা',
                'user.create'     => 'গ্রাহক তৈরি',
                'user.edit'       => 'গ্রাহক সম্পাদনা',
                'user.delete'     => 'গ্রাহক মুছে ফেলা',
                'user.manage_org' => 'অর্গানাইজেশন ব্যবস্থাপনা',
            ],
        ],
        'kb' => [
            'label' => 'নলেজ বেস',
            'codes' => [
                'kb.view'    => 'আর্টিকল দেখা',
                'kb.create'  => 'আর্টিকল তৈরি',
                'kb.edit'    => 'আর্টিকল সম্পাদনা',
                'kb.publish' => 'আর্টিকল প্রকাশ',
                'kb.delete'  => 'আর্টিকল মুছে ফেলা',
            ],
        ],
        'admin' => [
            'label' => 'অ্যাডমিন',
            'codes' => [
                'admin.agents'      => 'এজেন্ট ব্যবস্থাপনা',
                'admin.roles'       => 'রোল ও পারমিশন',
                'admin.departments' => 'ডিপার্টমেন্ট',
                'admin.teams'       => 'টিম',
                'admin.topics'      => 'হেল্প টপিক',
                'admin.sla'         => 'SLA ও বিজনেস আওয়ার',
                'admin.forms'       => 'কাস্টম ফর্ম',
                'admin.filters'     => 'টিকেট ফিল্টার ও এস্কেলেশন',
                'admin.email'       => 'ইমেইল অ্যাকাউন্ট',
                'admin.templates'   => 'ইমেইল টেমপ্লেট',
                'admin.settings'    => 'সিস্টেম সেটিংস',
                'admin.logs'        => 'অ্যাক্টিভিটি লগ',
                'admin.backup'      => 'ব্যাকআপ',
                'admin.api_keys'    => 'API কী',
            ],
        ],
        'report' => [
            'label' => 'রিপোর্ট',
            'codes' => [
                'report.dashboard'  => 'ড্যাশবোর্ড পরিসংখ্যান',
                'report.agent_stats' => 'এজেন্ট পারফরম্যান্স',
                'report.dept_stats'  => 'ডিপার্টমেন্ট রিপোর্ট',
                'report.export'      => 'রিপোর্ট এক্সপোর্ট',
            ],
        ],
    ],

    'roles' => [
        'Super Admin' => [
            'description' => 'সবকিছুতে পূর্ণ অ্যাক্সেস',
            'permissions' => '*',
        ],
        'Department Manager' => [
            'description' => 'নিজ ডিপার্টমেন্টের সব টিকেট, ট্রান্সফার ও রিপোর্ট',
            'permissions' => [
                'ticket.view_dept', 'ticket.create', 'ticket.edit', 'ticket.reply', 'ticket.note',
                'ticket.assign', 'ticket.transfer', 'ticket.forward_external', 'ticket.change_priority',
                'ticket.close', 'ticket.reopen', 'ticket.merge', 'ticket.mass_action', 'ticket.export',
                'user.view', 'user.create', 'user.edit',
                'kb.view', 'kb.create', 'kb.edit', 'kb.publish',
                'report.dashboard', 'report.agent_stats', 'report.dept_stats', 'report.export',
            ],
        ],
        'Senior Agent' => [
            'description' => 'ট্রান্সফার ও ক্লোজ করতে পারেন, কনফিগ নয়',
            'permissions' => [
                'ticket.view_dept', 'ticket.create', 'ticket.edit', 'ticket.reply', 'ticket.note',
                'ticket.assign', 'ticket.transfer', 'ticket.forward_external', 'ticket.change_priority',
                'ticket.close', 'ticket.reopen',
                'user.view', 'user.edit',
                'kb.view', 'kb.create', 'kb.edit',
                'report.dashboard',
            ],
        ],
        'Agent' => [
            'description' => 'ডিপার্টমেন্টের টিকেটে উত্তর ও নোট',
            'permissions' => [
                'ticket.view_dept', 'ticket.create', 'ticket.reply', 'ticket.note', 'ticket.close',
                'user.view', 'kb.view', 'report.dashboard',
            ],
        ],
        'Limited Agent' => [
            'description' => 'শুধু নিজের অ্যাসাইনড টিকেট',
            'permissions' => [
                'ticket.view_own', 'ticket.reply', 'ticket.note', 'kb.view',
            ],
        ],
        'Read Only' => [
            'description' => 'দেখা যাবে, বদলানো যাবে না',
            'permissions' => ['ticket.view_dept', 'user.view', 'kb.view', 'report.dashboard'],
        ],
    ],
];
