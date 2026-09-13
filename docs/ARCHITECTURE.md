# HelpDesk — আর্কিটেকচার ডিজাইন

> একটি সম্পূর্ণ, প্রোডাকশন-গ্রেড হেল্পডেস্ক টিকেটিং সিস্টেম।
> **Native PHP 8.2+ · MySQL 8 / MariaDB · Vanilla JS · Bootstrap 5.3 · XAMPP-ready**
> osTicket যা যা করে তার সবটুকু — কিন্তু আধুনিক UI, পরিষ্কার কোডবেস আর পূর্ণ বাংলা সাপোর্ট সহ।

**ডকুমেন্ট ভার্সন:** 1.0 · **স্ট্যাটাস:** ডিজাইন অনুমোদনের অপেক্ষায় · **কোডনেম:** `helpdesk`

---

## ১. লক্ষ্য ও স্কোপ

### ১.১ মূল লক্ষ্য

| # | লক্ষ্য | কেন |
|---|---|---|
| G1 | মাল্টি-চ্যানেল টিকেট গ্রহণ (ওয়েব ফর্ম, ইমেইল, API, এজেন্ট-সৃষ্ট) | গ্রাহক যেখান থেকেই আসুক একই ইনবক্সে জমা হবে |
| G2 | উন্নত **ফরওয়ার্ডিং/ট্রান্সফার** সিস্টেম | ডিপার্টমেন্ট ↔ টিম ↔ এজেন্ট + বাইরের ইমেইলে ফরওয়ার্ড |
| G3 | পূর্ণ **অ্যাডমিন কন্ট্রোল** — RBAC, ডিপার্টমেন্ট, SLA, ফর্ম, টেমপ্লেট | কোড না ছুঁয়ে সব কনফিগার হবে |
| G4 | সুন্দর, ইউজার-ফ্রেন্ডলি UI (Laravel/Filament-ধাঁচের), ডার্ক মোড সহ | osTicket-এর সবচেয়ে বড় দুর্বলতা এটাই |
| G5 | XAMPP-এ শূন্য-কনফিগে চলবে, শেয়ার্ড হোস্টিং/cPanel-এও ডিপ্লয়েবল | কোনো Node build step নেই |
| G6 | দ্বিভাষিক (বাংলা + English) ইন্টারফেস | |

### ১.২ স্কোপে **নেই** (v1)

চ্যাট/লাইভ চ্যাট উইজেট · টেলিফোনি ইন্টিগ্রেশন · বিলিং/ইনভয়েসিং · মোবাইল নেটিভ অ্যাপ · রিয়েল-টাইম WebSocket (পোলিং দিয়ে হবে)

---

## ২. টেকনোলজি স্ট্যাক ও সিদ্ধান্ত

| স্তর | পছন্দ | কারণ |
|---|---|---|
| রানটাইম | **PHP 8.2 – 8.4** (native, কোনো ফুল ফ্রেমওয়ার্ক নয়) | XAMPP/শেয়ার্ড হোস্টিং-এ সরাসরি চলে; typed properties, enums, readonly, match — আধুনিক PHP |
| ডেটাবেস | **MySQL 8.0 / MariaDB 10.6+** (InnoDB, utf8mb4) | XAMPP ডিফল্ট; FULLTEXT সার্চ, JSON কলাম, CTE |
| DB অ্যাক্সেস | **PDO** + নিজস্ব পাতলা QueryBuilder | সব কোয়েরি prepared statement |
| অটোলোড | **Composer PSR-4** | ম্যানুয়াল `require` নেই |
| টেমপ্লেট | Native PHP view + `htmlspecialchars` হেল্পার | আলাদা টেমপ্লেট ইঞ্জিনের ওভারহেড নেই |
| CSS | **Bootstrap 5.3** + কাস্টম ডিজাইন টোকেন লেয়ার | গ্রিড/ফর্ম রেডি, তার উপর নিজস্ব থিম |
| JS | **Vanilla JS (ES Modules)**, কোনো build step নেই | ব্রাউজারই মডিউল লোড করবে |
| আইকন | Bootstrap Icons (লোকাল কপি) | অফলাইনেও চলবে |

### ২.১ Composer নির্ভরতা (যে "ফ্রেমওয়ার্ক" গুলো যোগ করব)

```jsonc
{
  "require": {
    "php": ">=8.2",
    "phpmailer/phpmailer":   "^6.9",   // SMTP আউটবাউন্ড মেইল
    "webklex/php-imap":      "^5.5",   // IMAP ইনবাউন্ড — ext-imap ছাড়াই চলে ★
    "vlucas/phpdotenv":      "^5.6",   // .env কনফিগ
    "monolog/monolog":       "^3.6",   // স্ট্রাকচার্ড লগিং
    "ezyang/htmlpurifier":   "^4.17",  // রিচ-টেক্সট XSS স্যানিটাইজ ★
    "ramsey/uuid":           "^4.7",   // পাবলিক-সেফ আইডি
    "phpoffice/phpspreadsheet": "^2.1",// XLSX রিপোর্ট এক্সপোর্ট
    "robthree/twofactorauth": "^3.0",  // এজেন্ট 2FA (TOTP)
    "symfony/mime":          "^7.0"    // MIME পার্সিং হেল্পার
  },
  "require-dev": {
    "phpunit/phpunit": "^11.0",
    "squizlabs/php_codesniffer": "^3.10"
  }
}
```

★ **গুরুত্বপূর্ণ সিদ্ধান্ত:** `webklex/php-imap` বেছে নেওয়া হয়েছে PHP-র বিল্ট-ইন `ext-imap` এর বদলে, কারণ PHP 8.4-এ `ext-imap` কোর থেকে সরিয়ে PECL-এ নেওয়া হয়েছে। এতে XAMPP-এ `php.ini` ঘাঁটাঘাঁটি ছাড়াই ইমেইল ফেচিং কাজ করবে এবং ভবিষ্যতের PHP ভার্সনেও টিকবে।

### ২.২ ব্রাউজার-সাইড লাইব্রেরি (সব `public/assets/vendor/`-এ লোকাল কপি, CDN নয় — যাতে অফলাইন XAMPP-এ চলে)

| লাইব্রেরি | কাজ |
|---|---|
| Bootstrap 5.3 + Icons | লেআউট, ফর্ম, মডাল, ড্রপডাউন |
| SweetAlert2 | কনফার্মেশন ডায়ালগ, টোস্ট |
| Chart.js 4 | ড্যাশবোর্ড চার্ট |
| Quill 2 | টিকেট রিপ্লাইয়ের রিচ-টেক্সট এডিটর |
| Choices.js | সার্চেবল সিলেক্ট (এজেন্ট/ডিপার্টমেন্ট পিকার) |
| Flatpickr | ডেট/টাইম ফিল্ড |

---

## ৩. উচ্চস্তরের আর্কিটেকচার

```
                       ┌──────────────────────────────────────────┐
   গ্রাহক (ব্রাউজার) ───▶│  CLIENT PORTAL      /                    │
                       │  টিকেট খোলা, স্ট্যাটাস দেখা, রিপ্লাই, KB   │
                       └──────────────────────────────────────────┘
                       ┌──────────────────────────────────────────┐
   এজেন্ট ─────────────▶│  AGENT PANEL        /agent               │
                       │  কিউ, টিকেট ভিউ, রিপ্লাই, ট্রান্সফার, নোট │
                       └──────────────────────────────────────────┘
                       ┌──────────────────────────────────────────┐
   অ্যাডমিন ───────────▶│  ADMIN PANEL        /admin               │
                       │  এজেন্ট, ডিপার্টমেন্ট, SLA, ফর্ম, ইমেইল   │
                       └──────────────────────────────────────────┘
                       ┌──────────────────────────────────────────┐
   ৩য় পক্ষ ────────────▶│  REST API           /api/v1              │
                       └──────────────────────────────────────────┘
                                        │
                    ┌───────────────────▼───────────────────┐
                    │   public/index.php  (Front Controller) │
                    │   Router → Middleware → Controller     │
                    └───────────────────┬───────────────────┘
                                        │
           ┌────────────────────────────▼────────────────────────────┐
           │                    SERVICE LAYER                        │
           │  TicketService · TransferService · SlaService           │
           │  FilterEngine · NotificationService · MailService       │
           │  AttachmentService · FormService · AuditService         │
           └────────────────────────────┬────────────────────────────┘
                                        │
           ┌────────────────────────────▼────────────────────────────┐
           │            MODEL LAYER (PDO / QueryBuilder)             │
           └────────────────────────────┬────────────────────────────┘
                                        │
                              ┌─────────▼─────────┐
                              │   MySQL (InnoDB)  │
                              └───────────────────┘

   ┌─────────────────── BACKGROUND (cron / Task Scheduler) ───────────────────┐
   │ fetch_mail.php → IMAP পোল → টিকেট তৈরি/আপডেট                              │
   │ send_queue.php → email_queue → SMTP পাঠানো                                │
   │ sla_check.php  → ওভারডিউ সনাক্ত → এস্কেলেশন + নোটিফিকেশন                   │
   │ cleanup.php    → পুরনো সেশন/লগ/টেম্প ফাইল মুছে ফেলা                        │
   └──────────────────────────────────────────────────────────────────────────┘
```

### ৩.১ রিকোয়েস্ট লাইফসাইকেল

```
HTTP → .htaccess rewrite → public/index.php
  1. bootstrap.php  → .env লোড, autoload, error handler, timezone, DB কানেকশন (lazy)
  2. Session::start()  (secure cookie, SameSite=Lax, regenerate)
  3. Router::dispatch(method, uri)
  4. Middleware চেইন:  auth → role → csrf → ratelimit
  5. Controller::action(Request) → Service → Model
  6. Response  → View::render() | Response::json()
  7. AuditService::flush() · Logger::flush()
```

---

## ৪. ডিরেক্টরি স্ট্রাকচার

```
helpdesk/
├── public/                     ← Apache DocumentRoot (শুধু এই ফোল্ডার পাবলিক)
│   ├── index.php               ← একমাত্র এন্ট্রি পয়েন্ট
│   ├── .htaccess               ← rewrite + security headers
│   └── assets/
│       ├── css/  (tokens.css, app.css, agent.css, client.css)
│       ├── js/   (ES modules: app.js, ticket.js, editor.js, upload.js …)
│       ├── img/
│       └── vendor/ (bootstrap, quill, chartjs, sweetalert2 …)
│
├── app/
│   ├── Core/                   ← নিজস্ব মাইক্রো-ফ্রেমওয়ার্ক
│   │   ├── Router.php          Request.php     Response.php
│   │   ├── View.php            Session.php     Csrf.php
│   │   ├── Database.php        QueryBuilder.php  Model.php
│   │   ├── Auth.php            Validator.php   Config.php
│   │   ├── Mailer.php          Event.php       Logger.php
│   │   └── Paginator.php       RateLimiter.php
│   ├── Controllers/
│   │   ├── Client/   (TicketController, AuthController, KbController)
│   │   ├── Agent/    (DashboardController, TicketController, QueueController)
│   │   ├── Admin/    (AgentController, DepartmentController, SlaController,
│   │   │              FormController, EmailController, SettingController)
│   │   └── Api/V1/   (TicketController, AuthController)
│   ├── Models/       (Ticket, Thread, Agent, User, Department, Team, …)
│   ├── Services/     (TicketService, TransferService, SlaService,
│   │                  FilterEngine, MailFetcher, NotificationService, …)
│   ├── Middleware/   (AuthAgent, AuthClient, RequirePermission, VerifyCsrf, Throttle)
│   ├── Enums/        (TicketState, ThreadType, TransferType, Source)
│   ├── Helpers/      (functions.php — e(), url(), __(), formatDate())
│   └── Views/
│       ├── layouts/  (client.php, agent.php, admin.php, auth.php, mail.php)
│       ├── client/   agent/   admin/   emails/   partials/
│
├── config/           (app.php, database.php, mail.php, permissions.php)
├── database/
│   ├── migrations/   (001_create_core_tables.php … ধারাবাহিক)
│   ├── seeds/        (default_statuses, default_permissions, demo_data)
│   └── schema.sql    (সম্পূর্ণ স্কিমার এক-ফাইল ডাম্প)
├── storage/          ← ওয়েব রুটের বাইরে ★
│   ├── attachments/  (yyyy/mm/ ভিত্তিক, র‍্যান্ডম নাম)
│   ├── logs/  cache/  tmp/  backups/
├── cron/             (run.php, fetch_mail.php, send_queue.php, sla_check.php)
├── lang/             (bn.php, en.php)
├── tests/
├── vendor/
├── .env.example      composer.json      README.md
└── docs/             (ARCHITECTURE.md, API.md, INSTALL.md)
```

★ `storage/` ও `app/` ওয়েব রুটের বাইরে থাকায় অ্যাটাচমেন্ট বা সোর্স কোড সরাসরি URL দিয়ে ডাউনলোড করা যাবে না। ফাইল সবসময় `download.php?uuid=` রুটের মাধ্যমে permission চেক করে সার্ভ হবে।

---

## ৫. ডেটাবেস ডিজাইন

**কনভেনশন:** টেবিল নাম plural snake_case · PK `id BIGINT UNSIGNED AUTO_INCREMENT` · সব টেবিলে `created_at`, প্রযোজ্য হলে `updated_at` · FK-তে `ON DELETE RESTRICT` (ডেটা হারানো ঠেকাতে), lookup-এ `SET NULL` · Charset `utf8mb4_unicode_ci` · সফট ডিলিট `deleted_at` (tickets, agents, users)

### ৫.১ ER ওভারভিউ

```
organizations ──┐
                ├──< users ──────< tickets >────── departments ──< help_topics
                │                   │  │  │              │
                │                   │  │  │              └──< agent_departments >── agents
                │                   │  │  │                                            │
                │                   │  │  └──> priorities                              │
                │                   │  └─────> statuses                          teams >┘
                │                   │                                              │
                │                   ├──< ticket_threads ──< attachments      team_members
                │                   ├──< ticket_transfers        (ফরওয়ার্ডিং লগ)
                │                   ├──< ticket_collaborators
                │                   ├──< ticket_tags >── tags
                │                   ├──< form_entries ──< form_values >── form_fields >── forms
                │                   └──> sla_plans ──> business_hours ──< holidays
                │
roles ──< role_permissions >── permissions          email_accounts ──< email_queue
filters ──< filter_rules / filter_actions           kb_categories ──< kb_articles
```

### ৫.২ মানুষ ও অ্যাক্সেস

```sql
organizations (id, name, domain, notes, default_dept_id, created_at)
    -- ইমেইল ডোমেইন দিয়ে ইউজার অটো-লিংক হবে (@acme.com → Acme Ltd)

users            -- গ্রাহক / এন্ড ইউজার (ক্লায়েন্ট পোর্টাল)
  (id, uuid, org_id→organizations, name, email UNIQUE, phone,
   password_hash NULL,          -- NULL = গেস্ট, শুধু ইমেইল+টিকেট নম্বরে অ্যাক্সেস
   locale, timezone, status ENUM('active','locked','banned'),
   email_verified_at, last_login_at, created_at, deleted_at)

agents           -- স্টাফ: অ্যাডমিন ও এজেন্ট
  (id, uuid, name, username UNIQUE, email UNIQUE, password_hash,
   avatar_path, signature, mobile,
   role_id→roles,                -- গ্লোবাল ডিফল্ট রোল
   primary_dept_id→departments,
   is_admin TINYINT,             -- সুপার অ্যাডমিন বাইপাস
   max_open_tickets INT NULL,    -- লোড ব্যালান্সিং সীমা
   is_available TINYINT,         -- অটো-অ্যাসাইনে অন্তর্ভুক্ত হবে কি না
   status ENUM('active','locked','disabled'),
   totp_secret NULL, last_login_at, password_changed_at, created_at, deleted_at)

roles            (id, name, description, is_system)
permissions      (id, code UNIQUE, group_name, label)   -- যেমন 'ticket.transfer'
role_permissions (role_id, permission_id)  PK(role_id, permission_id)

agent_departments -- একজন এজেন্ট একাধিক ডিপার্টমেন্টে থাকতে পারে
  (agent_id, dept_id, role_id NULL,   -- ঐ ডিপার্টমেন্টে ভিন্ন রোল দেওয়া যায়
   is_manager TINYINT, alerts_enabled TINYINT)  PK(agent_id, dept_id)

teams        (id, name, lead_agent_id→agents, is_active, notify_lead)
team_members (team_id, agent_id)  PK(team_id, agent_id)
```

### ৫.৩ সাংগঠনিক কাঠামো ও নিয়ম

```sql
departments
  (id, parent_id→departments,      -- নেস্টেড ডিপার্টমেন্ট
   name, signature, email_account_id→email_accounts,
   manager_id→agents, sla_id→sla_plans,
   auto_response TINYINT, is_public TINYINT,   -- পাবলিক = গ্রাহক ফর্মে দেখা যাবে
   assignment_strategy ENUM('manual','round_robin','least_load'),
   sort_order, created_at)

help_topics      -- "সমস্যার ধরন" — গ্রাহক ফর্মের ড্রপডাউন
  (id, parent_id, name, dept_id, priority_id, sla_id, form_id,
   auto_assign_agent_id, auto_assign_team_id,
   is_public, is_active, sort_order)

priorities  (id, name, color, level TINYINT, is_default, sort_order)
            -- Low(1) · Normal(2) · High(3) · Urgent(4) · Emergency(5)

statuses    (id, name, state ENUM('open','paused','resolved','closed','archived'),
             color, icon, is_default, allows_reopen, sort_order)
            -- New · Open · Pending(গ্রাহকের উত্তরের অপেক্ষায়) · On Hold(৩য় পক্ষ)
            -- · Resolved · Closed

sla_plans   (id, name,
             first_response_minutes,      -- প্রথম উত্তরের সময়সীমা
             resolution_minutes,          -- সমাধানের সময়সীমা
             business_hours_id,
             pause_on_pending TINYINT,    -- গ্রাহকের অপেক্ষায় থাকলে ঘড়ি থামবে
             warn_at_percent TINYINT,     -- ৭৫% এ সতর্কতা
             escalate_on_breach TINYINT, escalate_to_dept_id, escalate_to_team_id,
             is_active)

business_hours       (id, name, timezone)
business_hours_slots (id, business_hours_id, day_of_week TINYINT, open_time, close_time)
holidays             (id, business_hours_id, holiday_date, name)
```

### ৫.৪ টিকেট কোর

```sql
tickets
  (id, uuid, number VARCHAR(20) UNIQUE,     -- পাবলিক রেফারেন্স: 250913-0042
   user_id→users,            -- মূল রিকোয়েস্টার
   org_id→organizations,
   dept_id→departments, topic_id→help_topics,
   status_id→statuses, priority_id→priorities, sla_id→sla_plans,
   assigned_agent_id→agents NULL, assigned_team_id→teams NULL,
   source ENUM('web','email','phone','api','agent'),
   subject VARCHAR(255),
   first_response_at, last_message_at, last_response_at,
   due_at,                   -- SLA রেজলিউশন ডেডলাইন (বিজনেস আওয়ার হিসেবে)
   response_due_at,          -- প্রথম উত্তরের ডেডলাইন
   sla_paused_at, sla_paused_seconds INT DEFAULT 0,
   is_overdue TINYINT, is_answered TINYINT, is_locked_by→agents NULL, lock_expires_at,
   reopen_count INT, merged_into_id→tickets NULL,
   closed_at, closed_by→agents, created_by_agent_id NULL,
   ip_address, created_at, updated_at, deleted_at)

  INDEX (status_id, dept_id, assigned_agent_id)   -- কিউ কোয়েরি
  INDEX (user_id, created_at)                     -- গ্রাহকের টিকেট তালিকা
  INDEX (due_at, is_overdue)                      -- SLA ক্রন
  FULLTEXT (subject)

ticket_threads              -- সব বার্তা: গ্রাহকের মেসেজ, এজেন্টের উত্তর, ইন্টার্নাল নোট
  (id, ticket_id, type ENUM('message','response','note','system','forward'),
   agent_id NULL, user_id NULL,        -- যেকোনো একটি
   body_html MEDIUMTEXT, body_text MEDIUMTEXT,
   is_internal TINYINT,                -- নোট = গ্রাহক দেখবে না
   source ENUM('web','email','api','system'),
   email_message_id VARCHAR(255),      -- RFC Message-ID (থ্রেডিং + ডুপ্লিকেট ঠেকানো)
   email_in_reply_to VARCHAR(255),
   recipients JSON,                    -- to/cc যাদের পাঠানো হয়েছে
   ip_address, edited_at, edited_by, created_at)

  INDEX (ticket_id, created_at)
  UNIQUE (email_message_id)            -- একই ইমেইল দুবার ঢুকবে না

attachments
  (id, uuid UNIQUE, ticket_id, thread_id NULL,
   original_name, stored_path,         -- storage/attachments/2026/09/<random>.bin
   mime_type, size_bytes, checksum_sha256,
   is_inline TINYINT, content_id,      -- ইমেইলের ইনলাইন ইমেজ (cid:)
   uploaded_by_type ENUM('agent','user','system'), uploaded_by_id, created_at)

ticket_collaborators       -- CC — একাধিক ব্যক্তি একই টিকেটে
  (id, ticket_id, user_id, type ENUM('cc','bcc'),
   added_by_agent_id, is_active, created_at)  UNIQUE(ticket_id, user_id)

ticket_watchers  (ticket_id, agent_id)   -- এজেন্ট নিজে "ফলো" করছে
tags             (id, name UNIQUE, color)
ticket_tags      (ticket_id, tag_id)  PK(ticket_id, tag_id)
ticket_links     (id, ticket_id, linked_ticket_id, relation ENUM('related','duplicate','blocks'))
```

### ৫.৫ ফরওয়ার্ডিং / ট্রান্সফার (বিস্তারিত §৭-এ)

```sql
ticket_transfers            -- অভ্যন্তরীণ হস্তান্তরের সম্পূর্ণ অডিট ট্রেইল
  (id, ticket_id,
   transfer_type ENUM('department','agent','team','escalation','claim','release'),
   from_dept_id, to_dept_id, from_agent_id, to_agent_id, from_team_id, to_team_id,
   reason VARCHAR(255), note TEXT,
   by_agent_id→agents NULL,         -- NULL = সিস্টেম/অটোমেশন
   is_automatic TINYINT,
   sla_action ENUM('keep','reset','extend'), old_due_at, new_due_at,
   created_at)
  INDEX (ticket_id, created_at)

ticket_external_forwards    -- সিস্টেমের বাইরে ইমেইলে ফরওয়ার্ড (ভেন্ডর/৩য় পক্ষ)
  (id, ticket_id, thread_id,
   to_email, cc_emails, subject, body_html,
   include_attachments TINYINT, include_history TINYINT,
   reply_tracking_token VARCHAR(64) UNIQUE,  -- উত্তর এলে টিকেটে ফিরে আসবে ★
   by_agent_id, status ENUM('queued','sent','failed'), error, sent_at, created_at)

escalation_rules
  (id, name, dept_id NULL, is_active, execution_order,
   condition_type ENUM('sla_breach','sla_warning','age','unassigned','no_response','priority'),
   condition_value INT,              -- যেমন age → মিনিট
   match_priority_id NULL, match_status_id NULL,
   action_type ENUM('transfer_dept','assign_team','assign_agent','raise_priority',
                    'notify_manager','add_tag'),
   action_value BIGINT, notify_emails, created_at)
```

### ৫.৬ অটোমেশন ও কনটেন্ট

```sql
filters              -- ইনকামিং টিকেটের অটো-রাউটিং (osTicket-এর Ticket Filter)
  (id, name, execution_order, match_type ENUM('all','any'),
   target ENUM('any','web','email','api'), stop_on_match, is_active)
filter_rules
  (id, filter_id, field ENUM('from_email','from_name','subject','body','to_email',
                             'header','ip','org_domain'),
   operator ENUM('equal','not_equal','contains','not_contains','starts_with',
                 'ends_with','regex'),
   value VARCHAR(500))
filter_actions
  (id, filter_id, action ENUM('set_dept','set_topic','set_priority','set_sla',
                              'assign_agent','assign_team','set_status','add_tag',
                              'reject','disable_autoresponse','forward_email'),
   value VARCHAR(255))

canned_responses (id, dept_id NULL, title, body_html, attachments_json,
                  is_active, created_by, created_at)
email_templates  (id, group_code, code, locale, subject, body_html, is_active)
                 -- ticket.created · ticket.reply · ticket.assigned · ticket.transferred
                 -- · ticket.overdue · agent.reset_password · user.welcome …
```

### ৫.৭ ইমেইল পাইপলাইন

```sql
email_accounts
  (id, name, email UNIQUE, dept_id,
   imap_host, imap_port, imap_encryption ENUM('none','ssl','tls'),
   imap_username, imap_password_enc,           -- AES-256-GCM এনক্রিপ্টেড ★
   imap_folder, fetch_enabled, fetch_interval_minutes, delete_after_fetch,
   smtp_host, smtp_port, smtp_encryption, smtp_username, smtp_password_enc,
   from_name, is_default, last_fetch_at, last_error, created_at)

email_queue
  (id, to_email, cc_emails, bcc_emails, from_account_id,
   subject, body_html, body_text, attachments_json,
   ticket_id, thread_id, template_code,
   priority TINYINT, attempts TINYINT, max_attempts TINYINT DEFAULT 3,
   status ENUM('pending','sending','sent','failed'), last_error,
   scheduled_at, sent_at, created_at)
  INDEX (status, scheduled_at)

email_log  (id, message_id UNIQUE, direction ENUM('in','out'),
            ticket_id, from_email, subject, received_at)
```

### ৫.৮ কাস্টম ফর্ম ইঞ্জিন

```sql
forms        (id, name, title, instructions, type ENUM('ticket','user','org'), is_active)
form_fields  (id, form_id, field_key, label, hint, type, config JSON,
              -- type: text|textarea|select|multiselect|checkbox|radio|date|datetime
              --       |number|email|phone|file|section|divider
              -- config: {options:[], min, max, rows, placeholder, regex}
              is_required, is_private,          -- private = শুধু এজেন্ট দেখবে
              visibility ENUM('all','agent_only','internal'),
              sort_order, is_active)
form_entries (id, form_id, object_type ENUM('ticket','user','org'), object_id, created_at)
form_values  (id, entry_id, field_id, value TEXT, value_json JSON)
  INDEX (entry_id), INDEX (field_id, value(100))   -- কাস্টম ফিল্ডে সার্চ
```

### ৫.৯ নলেজ বেস, রিপোর্ট ও সিস্টেম

```sql
kb_categories (id, parent_id, name, slug UNIQUE, description, is_public, sort_order)
kb_articles   (id, category_id, title, slug UNIQUE, body_html, excerpt,
               is_public, is_featured, views, helpful_yes, helpful_no,
               author_id→agents, published_at, created_at, updated_at)
  FULLTEXT (title, body_html)          -- "সাবমিটের আগে সাজেশন" ফিচার
kb_article_topics (article_id, topic_id)

settings      (setting_key PRIMARY, setting_value TEXT, group_name, value_type)
activity_log  (id, actor_type ENUM('agent','user','system'), actor_id,
               action VARCHAR(64),        -- ticket.transferred, agent.login …
               object_type, object_id, description, meta JSON,
               ip_address, user_agent, created_at)
  INDEX (object_type, object_id, created_at), INDEX (actor_type, actor_id)

notifications (id, agent_id, type, title, body, url, ticket_id, is_read, created_at)
saved_queues  (id, agent_id NULL,          -- NULL = সবার জন্য শেয়ার্ড কিউ
               name, criteria JSON, columns JSON, sort_by, sort_dir, sort_order)
sessions      (id VARCHAR(128) PK, actor_type, actor_id, ip_address, user_agent,
               payload, last_activity)
login_attempts(id, identifier, ip_address, success, created_at)   -- ব্রুট-ফোর্স লক
password_resets(id, actor_type, email, token_hash, expires_at, used_at)
api_keys      (id, name, key_hash, ip_whitelist, scopes JSON, is_active,
               last_used_at, created_at)
cron_locks    (job_name PK, locked_at, locked_by, last_run_at, last_status, last_output)
```

---

## ৬. টিকেট লাইফসাইকেল (স্টেট মেশিন)

```
                    ┌─────────┐
   ওয়েব/ইমেইল/API ─▶│   NEW   │  (অ্যাসাইন হয়নি)
                    └────┬────┘
             assign/claim │
                    ┌────▼────┐◀──────── reopen (গ্রাহক উত্তর দিলে) ─────┐
              ┌────▶│  OPEN   │                                        │
              │     └──┬───┬──┘                                        │
   গ্রাহক উত্তর দিল │   │   │ এজেন্ট উত্তর দিল                            │
              │        │   └──────────▶┌──────────┐                    │
              └────────┼───────────────│ PENDING  │ (গ্রাহকের অপেক্ষায়)  │
                       │               └─────┬────┘  SLA ঘড়ি ⏸          │
        ৩য় পক্ষের অপেক্ষায়│                    │                         │
                  ┌────▼─────┐               │                         │
                  │ ON HOLD  │ SLA ঘড়ি ⏸     │                         │
                  └────┬─────┘               │                         │
                       └────────┬────────────┘                         │
                          ┌─────▼──────┐                               │
                          │  RESOLVED  │───── X দিন পর অটো ──┐          │
                          └─────┬──────┘                     │          │
                                │ এজেন্ট ক্লোজ                │          │
                          ┌─────▼──────┐                     │          │
                          │   CLOSED   │◀────────────────────┘          │
                          └─────┬──────┘                                │
                                └──── reopen উইন্ডো (ডিফল্ট ৭ দিন) ──────┘
                          ┌────────────┐
                          │  ARCHIVED  │  (৯০ দিন পর, রিড-অনলি)
                          └────────────┘
```

**নিয়মাবলি**
- `New → Open` হয় যখন কোনো এজেন্ট অ্যাসাইন হয় বা টিকেট খোলে (claim)।
- এজেন্ট উত্তর দিলে → `Pending` (`is_answered=1`), গ্রাহক উত্তর দিলে → `Open` (`is_answered=0`)।
- `Pending`/`On Hold`-এ SLA ঘড়ি থামে যদি `sla_plans.pause_on_pending=1` — থামার সময় `sla_paused_at` সেট হয়, চালু হলে ব্যবধান `sla_paused_seconds`-এ যোগ হয়।
- `Closed` টিকেটে গ্রাহকের ইমেইল এলে reopen উইন্ডোর ভেতরে হলে reopen, বাইরে হলে নতুন টিকেট (পুরনোটির লিংকসহ)।
- প্রতিটি স্টেট পরিবর্তন `ticket_threads`-এ `type='system'` এন্ট্রি + `activity_log` তৈরি করে।

---

## ৭. ফরওয়ার্ডিং / ট্রান্সফার সিস্টেম ★

এটি সিস্টেমের সবচেয়ে গুরুত্বপূর্ণ ফিচার, তাই আলাদা করে ডিজাইন করা হলো। চার ধরনের হস্তান্তর:

### ৭.১ চার ধরনের ফরওয়ার্ড

| # | ধরন | কী হয় | কে পারে |
|---|---|---|---|
| **A** | **ডিপার্টমেন্ট ট্রান্সফার** | টিকেট Sales → Technical যায়। অ্যাসাইনমেন্ট ক্লিয়ার হয়, নতুন ডিপার্টমেন্টের SLA প্রযোজ্য হয় | `ticket.transfer` |
| **B** | **এজেন্ট অ্যাসাইন/রি-অ্যাসাইন** | নির্দিষ্ট এজেন্টের হাতে যায়; আগের এজেন্ট watcher হিসেবে থাকতে পারে | `ticket.assign` |
| **C** | **টিম অ্যাসাইন** | পুরো টিমের কিউতে যায়, যে আগে claim করবে সে পাবে | `ticket.assign` |
| **D** | **এক্সটার্নাল ফরওয়ার্ড** | সিস্টেমের বাইরে ইমেইলে পাঠানো (ভেন্ডর/পার্টনার), উত্তর ট্র্যাকিং টোকেন দিয়ে টিকেটে ফিরে আসে | `ticket.forward_external` |

### ৭.২ ট্রান্সফার ফ্লো (TransferService)

```
TransferService::transfer(Ticket $t, TransferRequest $r, Agent $by)
 ├─ 1. অনুমতি যাচাই — $by-এর ঐ টিকেটে অ্যাক্সেস আছে? গন্তব্য ডিপার্টমেন্টে পাঠানোর অধিকার আছে?
 ├─ 2. ভ্যালিডেশন — গন্তব্য সক্রিয়? লক্ষ্য এজেন্ট ঐ ডিপার্টমেন্টের সদস্য? নিজের কাছেই পাঠাচ্ছে না তো?
 ├─ 3. লক — SELECT … FOR UPDATE (দুই এজেন্ট একসাথে ট্রান্সফার করলে রেস কন্ডিশন ঠেকাতে)
 ├─ 4. পুরনো অবস্থা সংরক্ষণ (from_dept, from_agent, from_team, old_due_at)
 ├─ 5. টিকেট আপডেট — dept/agent/team, প্রয়োজনে status → 'Open'
 ├─ 6. SLA সিদ্ধান্ত:
 │      keep   → due_at অপরিবর্তিত (ডিফল্ট, গ্রাহকের দৃষ্টিতে ন্যায্য)
 │      reset  → নতুন ডিপার্টমেন্টের SLA দিয়ে due_at পুনর্গণনা (ম্যানেজার-অনুমোদিত)
 │      extend → নির্দিষ্ট সময় যোগ
 ├─ 7. ticket_transfers-এ অডিট রো লেখা
 ├─ 8. ticket_threads-এ system এন্ট্রি:
 │      "Technical Support ডিপার্টমেন্টে স্থানান্তর করেছেন করিম — কারণ: হার্ডওয়্যার ইস্যু"
 ├─ 9. নোটিফিকেশন:
 │      → নতুন এজেন্ট/টিম (ইন-অ্যাপ + ইমেইল)
 │      → নতুন ডিপার্টমেন্ট ম্যানেজার (যদি আনঅ্যাসাইনড থাকে)
 │      → আগের এজেন্ট ("আপনার টিকেট সরানো হয়েছে")
 │      → গ্রাহক (ঐচ্ছিক — Admin সেটিংসে নিয়ন্ত্রিত; ডিফল্ট বন্ধ, কারণ অভ্যন্তরীণ বিষয়)
 ├─ 10. Event::fire('ticket.transferred', …)  → প্লাগইন/ওয়েবহুক হুক পয়েন্ট
 └─ 11. COMMIT · activity_log
```

### ৭.৩ এক্সটার্নাল ফরওয়ার্ড ও রিপ্লাই-ট্র্যাকিং

বাইরের কাউকে ফরওয়ার্ড করলে তার উত্তর যাতে হারিয়ে না যায়:

```
১. এজেন্ট "Forward" চাপে → প্রাপক, বার্তা, কোন থ্রেড/অ্যাটাচমেন্ট যাবে বাছে
২. একটি ইউনিক টোকেন তৈরি হয়:  fw_<ticket_id>_<random32>
৩. আউটগোয়িং ইমেইলে বসে:
     Reply-To: support+fw_42_a1b2c3…@company.com     (প্লাস-অ্যাড্রেসিং)
     X-HelpDesk-Token: fw_42_a1b2c3…
     Subject: [#250913-0042] মূল বিষয়
৪. ভেন্ডর উত্তর দিলে MailFetcher টোকেন খুঁজে পায় (Reply-To / হেডার / সাবজেক্ট — এই ক্রমে)
৫. উত্তরটি ঐ টিকেটে `type='note'` (ইন্টার্নাল) হিসেবে যোগ হয় — গ্রাহক দেখবে না
৬. অ্যাসাইনড এজেন্ট নোটিফিকেশন পায়
```

> প্লাস-অ্যাড্রেসিং কাজ না করলে fallback: সাবজেক্টের `[#নম্বর]` ট্যাগ + `X-HelpDesk-Token` হেডার + `In-Reply-To` চেইন।

### ৭.৪ অটো-অ্যাসাইনমেন্ট কৌশল

`departments.assignment_strategy` দিয়ে নিয়ন্ত্রিত:

| কৌশল | যুক্তি |
|---|---|
| `manual` | কেউ claim না করা পর্যন্ত আনঅ্যাসাইনড কিউতে থাকে |
| `round_robin` | ডিপার্টমেন্টের available এজেন্টদের মধ্যে ঘুরিয়ে দেওয়া (`last_assigned_at` ক্রমে) |
| `least_load` | যার open টিকেট সবচেয়ে কম তাকে; `max_open_tickets` সীমা মানা হয় |

### ৭.৫ স্বয়ংক্রিয় এস্কেলেশন

`cron/sla_check.php` প্রতি ৫ মিনিটে `escalation_rules` চালায়:

```
ঘটনা: Urgent টিকেট ৩০ মিনিট আনঅ্যাসাইনড
  → condition: unassigned AND priority=Urgent AND age>30
  → action: assign_team = "Tier 2" + notify_manager
  → ticket_transfers (is_automatic=1, by_agent_id=NULL) লেখা হয়
  → system thread: "স্বয়ংক্রিয়ভাবে Tier 2 টিমে এস্কেলেট করা হয়েছে (SLA নিয়ম)"
```

---

## ৮. পারমিশন মডেল (RBAC)

**যাচাইয়ের ক্রম:** `is_admin` → রোল পারমিশন → ডিপার্টমেন্ট সদস্যপদ → টিকেট-স্তরের অ্যাক্সেস (অ্যাসাইনি/টিম/watcher)

```php
Auth::can('ticket.transfer', $ticket)   // অবজেক্ট-সচেতন চেক
```

| গ্রুপ | পারমিশন কোড |
|---|---|
| Ticket | `ticket.view_own` · `view_dept` · `view_all` · `create` · `edit` · `reply` · `note` · `assign` · `transfer` · `forward_external` · `close` · `reopen` · `delete` · `merge` · `edit_thread` · `change_priority` · `mass_action` · `export` |
| User | `user.view` · `create` · `edit` · `delete` · `merge` · `manage_org` |
| KB | `kb.view` · `create` · `edit` · `publish` · `delete` |
| Admin | `admin.agents` · `departments` · `teams` · `roles` · `sla` · `forms` · `filters` · `email` · `templates` · `settings` · `logs` · `backup` · `api_keys` |
| Report | `report.dashboard` · `agent_stats` · `dept_stats` · `export` |

**ডিফল্ট সিস্টেম রোল:** `Super Admin` (সব) · `Department Manager` (নিজ ডিপার্টমেন্টের সব + রিপোর্ট) · `Senior Agent` (ট্রান্সফার, ক্লোজ, ডিলিট বাদে) · `Agent` (view_dept, reply, note, claim) · `Limited Agent` (শুধু অ্যাসাইনড টিকেট) · `Read Only`

---

## ৯. ইমেইল পাইপলাইন

### ৯.১ ইনবাউন্ড (`cron/fetch_mail.php`, প্রতি ১–৫ মিনিট)

```
IMAP কানেক্ট (webklex/php-imap) → UNSEEN মেসেজ আনা
  └─ প্রতিটি মেসেজে:
     1. Message-ID দেখে ডুপ্লিকেট চেক (email_log UNIQUE)  → থাকলে বাদ
     2. বাউন্স/অটো-রিপ্লাই সনাক্তকরণ:
        Auto-Submitted, X-Autoreply, Precedence: bulk → বাদ (লুপ ঠেকাতে)
     3. টিকেট খোঁজা — এই ক্রমে:
        a) এক্সটার্নাল ফরওয়ার্ড টোকেন (Reply-To / X-HelpDesk-Token)
        b) In-Reply-To / References → ticket_threads.email_message_id
        c) সাবজেক্টে [#250913-0042]
        d) কিছুই না মিললে → নতুন টিকেট
     4. HTML বডি HTMLPurifier দিয়ে স্যানিটাইজ; প্লেইন-টেক্সট ভার্সন তৈরি
     5. উদ্ধৃতি (quoted reply) ছেঁটে ফেলা — "On … wrote:" মার্কার
     6. অ্যাটাচমেন্ট সেভ (টাইপ/সাইজ ভ্যালিডেশন), ইনলাইন ইমেজ cid: → লোকাল লিংক
     7. নতুন টিকেট হলে → FilterEngine চালানো (ডিপার্টমেন্ট/প্রায়োরিটি/অ্যাসাইনি নির্ধারণ)
     8. থ্রেড তৈরি → নোটিফিকেশন → অটো-রেসপন্স কিউতে
     9. মেসেজ SEEN মার্ক / আর্কাইভ ফোল্ডারে সরানো
```

### ৯.২ আউটবাউন্ড (`cron/send_queue.php`, প্রতি মিনিট)

সব মেইল **সরাসরি নয়, কিউতে** যায় — এতে পেজ লোড আটকায় না আর SMTP ফেইল করলেও হারায় না।

```
email_queue থেকে status='pending' AND scheduled_at<=now(), priority ক্রমে ২৫টি
  → PHPMailer (SMTP, ডিপার্টমেন্টের অ্যাকাউন্ট বা ডিফল্ট)
  → হেডার: Message-ID, In-Reply-To, References, X-HelpDesk-Ticket, List-Unsubscribe
  → সফল → status='sent', email_log(direction='out')
  → ব্যর্থ → attempts++, ব্যাকঅফ (১ম:৫মি, ২য়:৩০মি, ৩য়:২ঘ), ৩ বারে failed + অ্যাডমিন অ্যালার্ট
```

### ৯.৩ লুপ প্রতিরোধ
একই প্রেরক থেকে ১ মিনিটে ৫+ মেইল → থ্রটল · নিজের `from` ঠিকানা থেকে আসা মেইল বাদ · প্রতি টিকেটে দিনে সর্বোচ্চ ১টি অটো-রেসপন্স

---

## ১০. নিরাপত্তা ডিজাইন

| ক্ষেত্র | ব্যবস্থা |
|---|---|
| পাসওয়ার্ড | `password_hash()` — **Argon2id** (fallback bcrypt cost 12), rehash চেক |
| সেশন | HttpOnly · Secure · SameSite=Lax · লগইনে `session_regenerate_id(true)` · DB-ভিত্তিক সেশন · নিষ্ক্রিয়তায় টাইমআউট |
| CSRF | প্রতি সেশনে টোকেন, সব POST/PUT/DELETE-এ বাধ্যতামূলক, `hash_equals()` |
| SQL ইনজেকশন | ১০০% PDO prepared statement; কলাম/ORDER BY নাম শুধু হোয়াইটলিস্ট থেকে |
| XSS | আউটপুটে `e()` হেল্পার; রিচ-টেক্সটে HTMLPurifier (হোয়াইটলিস্ট ট্যাগ, `on*`/`javascript:` নিষিদ্ধ) |
| ফাইল আপলোড | এক্সটেনশন + MIME + ম্যাজিক বাইট যাচাই · র‍্যান্ডম নাম · ওয়েব রুটের বাইরে · সাইজ সীমা · `Content-Disposition: attachment` + `X-Content-Type-Options: nosniff` দিয়ে সার্ভ |
| ব্রুট ফোর্স | `login_attempts` — ৫ ব্যর্থতায় ১৫ মিনিট লক (IP + অ্যাকাউন্ট উভয়ে), অ্যাডমিন অ্যালার্ট |
| রেট লিমিট | গেস্ট টিকেট তৈরি: IP-প্রতি ঘণ্টায় ৫ · API: কী-প্রতি মিনিটে ৬০ · CAPTCHA (গেস্ট ফর্মে, ঐচ্ছিক) |
| IDOR | প্রতিটি টিকেট/অ্যাটাচমেন্ট অ্যাক্সেসে ownership + permission চেক; পাবলিক লিংকে `uuid`, `id` নয় |
| ক্রেডেনশিয়াল | IMAP/SMTP পাসওয়ার্ড AES-256-GCM এনক্রিপ্টেড; কী `.env`-এ (`APP_KEY`), git-এ নয় |
| হেডার | CSP · X-Frame-Options: SAMEORIGIN · Referrer-Policy · HSTS (HTTPS-এ) |
| অডিট | সব সংবেদনশীল কাজ `activity_log`-এ (কে, কী, কখন, কোন IP) |
| 2FA | এজেন্টদের জন্য TOTP (ঐচ্ছিক, অ্যাডমিন বাধ্যতামূলক করতে পারে) |

---

## ১১. UI/UX ডিজাইন সিস্টেম

### ১১.১ ডিজাইন টোকেন (`public/assets/css/tokens.css`)

```css
:root{
  --primary:#F05340; --primary-dark:#D43F2D; --primary-light:#FF7B6B;
  --bg:#FFFFFF; --bg-secondary:#F9FAFB; --surface:#FFFFFF;
  --border:#E5E7EB; --text:#111827; --text-muted:#6B7280;
  --success:#10B981; --warning:#F59E0B; --danger:#EF4444; --info:#3B82F6;
  --radius-sm:6px; --radius:10px; --radius-lg:16px;
  --shadow-sm:0 1px 3px rgba(0,0,0,.08); --shadow:0 4px 12px rgba(0,0,0,.10);
}
[data-theme="dark"]{
  --bg:#0F172A; --bg-secondary:#1E293B; --surface:#1E293B;
  --border:#334155; --text:#F1F5F9; --text-muted:#94A3B8;
}
```

**টাইপোগ্রাফি:** বডি `Inter` 15px/1.6 · হেডিং `Plus Jakarta Sans` 700 · বাংলা টেক্সটে `Noto Sans Bengali` fallback
**স্পেসিং:** ৮-এর গুণিতক (8/16/24/32/48) · **বাটন:** গ্রেডিয়েন্ট, radius 10px, hover-এ `translateY(-1px)`
**স্ট্যাট কার্ড:** ইনলাইন SVG আইকন + নরম রঙিন ব্যাকগ্রাউন্ড স্কয়ার (ইমোজি নয়)
**সেমান্টিক রং:** প্রায়োরিটি ও স্ট্যাটাস ব্যাজে শুধু রং নয়, আইকন+টেক্সটও থাকবে (কালার-ব্লাইন্ড অ্যাক্সেসিবিলিটি)

### ১১.২ লেআউট প্যাটার্ন

| প্যানেল | প্যাটার্ন |
|---|---|
| ক্লায়েন্ট পোর্টাল | হেডার + কেন্দ্রীভূত কনটেন্ট (max 960px), পরিষ্কার ও সরল |
| এজেন্ট প্যানেল | **Filament-ধাঁচের** সাইডবার (260px) + টপবার + কনটেন্ট; টিকেট ভিউতে ৩-কলাম |
| অ্যাডমিন প্যানেল | সাইডবার + সেটিংস কার্ড গ্রিড |
| Auth পেজ | কেন্দ্রে কার্ড (max 440px), লোগো উপরে |

### ১১.৩ এজেন্ট টিকেট ভিউ (সবচেয়ে গুরুত্বপূর্ণ স্ক্রিন)

```
┌──────────────────────────────────────────────────────────────────────────┐
│ #250913-0042  প্রিন্টার কাজ করছে না      [🟠 High] [● Open] [⏱ ২ঘ বাকি]  │
│ রিকোয়েস্টার: করিম উদ্দিন · acme.com · ৩টি টিকেট                          │
├────────────────┬──────────────────────────────────┬──────────────────────┤
│ বাম (240px)     │  মাঝ — কথোপকথন থ্রেড              │  ডান (300px)          │
│                │                                  │                      │
│ ▸ প্রপার্টি     │  ┌─ গ্রাহক · ১০:০২ ─────────────┐ │  ▸ অ্যাকশন          │
│   ডিপার্টমেন্ট   │  │ প্রিন্টারে কাগজ আটকে যাচ্ছে… │ │   [উত্তর দিন]        │
│   টপিক          │  │ 📎 photo.jpg                │ │   [ইন্টার্নাল নোট]    │
│   প্রায়োরিটি    │  └─────────────────────────────┘ │   [ট্রান্সফার ▾]     │
│   SLA কাউন্টার  │  ┌─ এজেন্ট · ১০:১৫ ────────────┐ │   [ফরওয়ার্ড ✉]      │
│                │  │ ট্রে ২ খুলে দেখুন…           │ │   [ক্লোজ]           │
│ ▸ কাস্টম ফিল্ড  │  └─────────────────────────────┘ │                      │
│   সিরিয়াল নং   │  ┌─ 🔒 নোট · রহিম · ১১:০০ ─────┐ │  ▸ টাইমলাইন         │
│   ওয়ারেন্টি     │  │ ভেন্ডরকে ফরওয়ার্ড করেছি      │ │   তৈরি হয়েছে         │
│                │  └─────────────────────────────┘ │   Tech-এ ট্রান্সফার   │
│ ▸ ট্যাগ         │  ┌─ ✍ রিপ্লাই এডিটর ───────────┐ │   রহিমকে অ্যাসাইন     │
│ ▸ সংশ্লিষ্ট      │  │ [Quill] [ক্যানড ▾] [📎]     │ │                      │
│                │  │ স্ট্যাটাস: [Pending ▾][পাঠান]│ │  ▸ CC তালিকা        │
└────────────────┴──────────────────────────────────┴──────────────────────┘
```

মোবাইলে: তিন কলাম স্ট্যাক হয়ে ট্যাব হবে — **থ্রেড · প্রপার্টি · অ্যাকশন**

### ১১.৪ স্ক্রিন ইনভেন্টরি

**ক্লায়েন্ট পোর্টাল (৯)** — হোম/KB সার্চ · নতুন টিকেট (ডাইনামিক ফর্ম) · টিকেট চেক (গেস্ট: ইমেইল+নম্বর) · লগইন/রেজিস্টার/পাসওয়ার্ড রিসেট · আমার টিকেট তালিকা · টিকেট বিস্তারিত+রিপ্লাই · প্রোফাইল · KB ক্যাটাগরি/আর্টিকল

**এজেন্ট প্যানেল (১১)** — লগইন (2FA) · ড্যাশবোর্ড (স্ট্যাট + চার্ট + আমার কিউ) · টিকেট কিউ (ফিল্টার, সেভড কিউ, বাল্ক অ্যাকশন) · টিকেট বিস্তারিত · নতুন টিকেট · ইউজার ডিরেক্টরি + প্রোফাইল · অর্গানাইজেশন · KB ম্যানেজমেন্ট · ক্যানড রেসপন্স · আমার প্রোফাইল/সিগনেচার · নোটিফিকেশন

**অ্যাডমিন প্যানেল (১৫)** — ড্যাশবোর্ড/সিস্টেম হেলথ · এজেন্ট · রোল ও পারমিশন · ডিপার্টমেন্ট · টিম · হেল্প টপিক · SLA প্ল্যান · বিজনেস আওয়ার/ছুটি · কাস্টম ফর্ম বিল্ডার (ড্র্যাগ-ড্রপ) · টিকেট ফিল্টার · এস্কেলেশন রুল · ইমেইল অ্যাকাউন্ট + ডায়াগনস্টিক · ইমেইল টেমপ্লেট · সাধারণ সেটিংস · অ্যাক্টিভিটি লগ/ব্যাকআপ · রিপোর্ট

### ১১.৫ ফ্রন্টএন্ড আর্কিটেকচার (Vanilla JS, বিল্ড স্টেপ নেই)

```
assets/js/
├── app.js          — এন্ট্রি: থিম টগল, সাইডবার, টোস্ট, গ্লোবাল ইভেন্ট
├── core/
│   ├── http.js     — fetch র‍্যাপার: CSRF হেডার, এরর হ্যান্ডলিং, JSON
│   ├── dom.js      — $, $$, on(), html() মাইক্রো হেল্পার
│   └── store.js    — ছোট pub/sub স্টেট
├── modules/
│   ├── ticket-view.js   — রিপ্লাই, নোট, ৩০সে পোলিং (নতুন বার্তা এলে ব্যাজ)
│   ├── transfer.js      — ট্রান্সফার মডাল, ক্যাসকেডিং ডিপার্টমেন্ট→এজেন্ট সিলেক্ট
│   ├── queue.js         — ফিল্টার, সিলেকশন, বাল্ক অ্যাকশন, URL সিংক
│   ├── form-builder.js  — অ্যাডমিন ড্র্যাগ-ড্রপ ফর্ম বিল্ডার
│   ├── uploader.js      — ড্র্যাগ-ড্রপ + প্রগ্রেস + প্রিভিউ
│   └── editor.js        — Quill ইনিশিয়ালাইজ, ক্যানড রেসপন্স ইনজেক্ট
```

`<script type="module">` — প্রতিটি পেজ শুধু নিজের মডিউল ইমপোর্ট করে। সব DOM আপডেট `textContent`/টেমপ্লেট দিয়ে, কাঁচা `innerHTML` নয়।

---

## ১২. REST API (`/api/v1`)

osTicket-এর বড় দুর্বলতা ছিল শুধু "টিকেট তৈরি" API। এখানে পূর্ণ CRUD থাকবে।

```
Authorization: Bearer <api_key>     |     X-Api-Key: <key>

POST   /api/v1/tickets                 নতুন টিকেট
GET    /api/v1/tickets                 তালিকা (ফিল্টার+পেজিনেশন)
GET    /api/v1/tickets/{number}        বিস্তারিত + থ্রেড
PATCH  /api/v1/tickets/{number}        স্ট্যাটাস/প্রায়োরিটি/ডিপার্টমেন্ট
POST   /api/v1/tickets/{number}/reply     উত্তর
POST   /api/v1/tickets/{number}/note      ইন্টার্নাল নোট
POST   /api/v1/tickets/{number}/transfer  ★ ট্রান্সফার
POST   /api/v1/tickets/{number}/forward   ★ এক্সটার্নাল ফরওয়ার্ড
GET    /api/v1/departments · /agents · /topics · /priorities
GET    /api/v1/stats/summary
```

সব রেসপন্স JSON, সঙ্গতিপূর্ণ খাম: `{ "data": …, "meta": {…}, "error": null }`। HTTP স্ট্যাটাস কোড যথাযথ (201/422/403/429)। ওয়েবহুক: `ticket.created`, `ticket.transferred`, `ticket.closed` ইত্যাদিতে বাইরের URL-এ POST।

---

## ১৩. XAMPP সেটআপ ও ডিপ্লয়মেন্ট

### ১৩.১ লোকাল (XAMPP)

```apache
# httpd-vhosts.conf
<VirtualHost *:80>
    ServerName helpdesk.local
    DocumentRoot "C:/xampp/htdocs/helpdesk/public"
    <Directory "C:/xampp/htdocs/helpdesk/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```
`C:\Windows\System32\drivers\etc\hosts` → `127.0.0.1  helpdesk.local`

**php.ini যা লাগবে:** `extension=pdo_mysql` · `mbstring` · `openssl` · `fileinfo` · `gd` · `zip` · `curl`
(`imap` **লাগবে না** — `webklex/php-imap` বিশুদ্ধ PHP)
**সুপারিশ:** `upload_max_filesize=25M` · `post_max_size=30M` · `max_execution_time=120` · `memory_limit=256M`

**ইনস্টল ধাপ**
```
1. htdocs/helpdesk/ এ কোড রাখুন
2. composer install
3. cp .env.example .env  → DB, APP_URL, APP_KEY সেট করুন
4. ব্রাউজারে /install → স্কিমা তৈরি, সুপার অ্যাডমিন অ্যাকাউন্ট, ডিফল্ট ডেটা সিড
5. ইনস্টলের পর install রুট অটো-লক হবে (.env-এ APP_INSTALLED=true)
```

### ১৩.২ ক্রন (ব্যাকগ্রাউন্ড কাজ)

```bash
# Linux / cPanel
* * * * * php /path/helpdesk/cron/run.php >> /path/storage/logs/cron.log 2>&1
```
```bat
:: Windows Task Scheduler — প্রতি ১ মিনিটে
C:\xampp\php\php.exe C:\xampp\htdocs\helpdesk\cron\run.php
```
`run.php` একটি ডিসপ্যাচার — `cron_locks` টেবিল দেখে প্রতিটি জব তার নিজস্ব ইন্টারভ্যালে চালায় এবং ওভারল্যাপ ঠেকাতে লক নেয়। ক্রন না থাকলে fallback: প্রতি পেজ লোডে ১% সম্ভাবনায় হালকা কিউ প্রসেসিং।

### ১৩.৩ প্রোডাকশন চেকলিস্ট
HTTPS বাধ্যতামূলক · `display_errors=Off` · DocumentRoot অবশ্যই `public/` · `storage/` লিখনযোগ্য কিন্তু ওয়েব-অ্যাক্সেসযোগ্য নয় · দৈনিক DB ব্যাকআপ (`cron/backup.php`) · `.env` পারমিশন 600

---

## ১৪. ডেভেলপমেন্ট রোডম্যাপ

| ফেজ | কাজ | ফলাফল |
|---|---|---|
| **P0** | কোর ফ্রেমওয়ার্ক (Router, DB, View, Auth, Csrf, Validator), মাইগ্রেশন রানার, ডিজাইন টোকেন + লেআউট, ইনস্টলার | চলমান স্কেলেটন, লগইন কাজ করে |
| **P1** | টিকেট CRUD · ক্লায়েন্ট পোর্টাল · এজেন্ট কিউ ও টিকেট ভিউ · থ্রেড · অ্যাটাচমেন্ট | **প্রথম ব্যবহারযোগ্য হেল্পডেস্ক** |
| **P2** | ডিপার্টমেন্ট · টিম · এজেন্ট · রোল/পারমিশন · অ্যাডমিন প্যানেল | পূর্ণ অ্যাডমিন কন্ট্রোল |
| **P3** | ★ ট্রান্সফার/ফরওয়ার্ডিং · কোলাবোরেটর · অটো-অ্যাসাইনমেন্ট · অডিট টাইমলাইন | ফরওয়ার্ডিং সিস্টেম সম্পূর্ণ |
| **P4** | ইমেইল পাইপলাইন (IMAP ফেচ, SMTP কিউ, টেমপ্লেট, থ্রেডিং, এক্সটার্নাল রিপ্লাই ট্র্যাকিং) | ইমেইল→টিকেট চালু |
| **P5** | SLA · বিজনেস আওয়ার · ক্রন · এস্কেলেশন রুল · ওভারডিউ অ্যালার্ট | স্বয়ংক্রিয় জবাবদিহি |
| **P6** | কাস্টম ফর্ম বিল্ডার · টিকেট ফিল্টার · ক্যানড রেসপন্স · ট্যাগ · মার্জ | কোড ছাড়াই কাস্টমাইজেশন |
| **P7** | নলেজ বেস · ফুল-টেক্সট সার্চ · রিপোর্ট ও ড্যাশবোর্ড · XLSX এক্সপোর্ট | |
| **P8** | REST API · ওয়েবহুক · বাংলা/English i18n · 2FA · সিকিউরিটি অডিট · পারফরম্যান্স | v1.0 রিলিজ |

---

## ১৫. কোডিং কনভেনশন

PSR-12 · `declare(strict_types=1)` সব ফাইলে · ক্লাস `PascalCase`, মেথড `camelCase`, DB কলাম `snake_case` · কন্ট্রোলার পাতলা, লজিক সার্ভিসে · সার্ভিস মেথড একটিই কাজ করবে · সব ইউজার ইনপুট `Validator` দিয়ে · এক্সেপশন: `ValidationException`, `AuthorizationException`, `NotFoundException` — গ্লোবাল হ্যান্ডলারে ধরা · প্রতিটি PR-এ মাইগ্রেশন ফাইল থাকলে rollback পথও থাকবে।
