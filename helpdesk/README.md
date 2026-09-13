# HelpDesk 🎫

সম্পূর্ণ বাংলা হেল্পডেস্ক টিকেটিং সিস্টেম — **নেটিভ PHP 8.2+ · MySQL · Vanilla JS**।
কোনো ফ্রেমওয়ার্ক নেই, কোনো build step নেই — XAMPP-এ ফাইল কপি করলেই চলে।

> আর্কিটেকচারের পূর্ণ বিবরণ: [`../docs/ARCHITECTURE.md`](../docs/ARCHITECTURE.md)

---

## বর্তমান অবস্থা

| ফেজ | কাজ | অবস্থা |
|---|---|---|
| **P0** | কোর ফ্রেমওয়ার্ক · ডেটাবেস স্কিমা · ইনস্টলার · অথেনটিকেশন · ড্যাশবোর্ড | ✅ সম্পন্ন |
| P1 | টিকেট CRUD · ক্লায়েন্ট পোর্টাল · এজেন্ট কিউ · থ্রেড · অ্যাটাচমেন্ট | ⏳ পরবর্তী |
| P2 | ডিপার্টমেন্ট · টিম · এজেন্ট · রোল · অ্যাডমিন প্যানেল | ⏳ |
| P3 | ট্রান্সফার / ফরওয়ার্ডিং · অটো-অ্যাসাইনমেন্ট | ⏳ |
| P4 | ইমেইল পাইপলাইন (IMAP ফেচ · SMTP কিউ) | ⏳ |
| P5–P8 | SLA · কাস্টম ফর্ম · নলেজ বেস · REST API | ⏳ |

---

## ইনস্টলেশন (XAMPP)

### ১. কোড রাখুন
`helpdesk/` ফোল্ডারটি `C:\xampp\htdocs\` এ কপি করুন।

### ২. Virtual Host (সুপারিশকৃত)

`C:\xampp\apache\conf\extra\httpd-vhosts.conf`-এ যোগ করুন:

```apache
<VirtualHost *:80>
    ServerName helpdesk.local
    DocumentRoot "C:/xampp/htdocs/helpdesk/public"
    <Directory "C:/xampp/htdocs/helpdesk/public">
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

`C:\Windows\System32\drivers\etc\hosts`-এ যোগ করুন:

```
127.0.0.1  helpdesk.local
```

Apache রিস্টার্ট দিন।

> **Virtual Host ছাড়াও চলবে** — সরাসরি `http://localhost/helpdesk/public/` খুলুন।
> রাউটার নিজে থেকেই সাব-ডিরেক্টরি বুঝে নেয়।

### ৩. php.ini

`C:\xampp\php\php.ini`-এ এগুলো চালু আছে কি না দেখুন (শুরুর সেমিকোলন সরিয়ে দিন):

```ini
extension=pdo_mysql
extension=mbstring
extension=openssl
extension=fileinfo
extension=gd
extension=zip
extension=curl

upload_max_filesize = 25M
post_max_size = 30M
max_execution_time = 120
memory_limit = 256M
```

> `extension=imap` **লাগবে না** — ইমেইল ফেচিং বিশুদ্ধ-PHP লাইব্রেরি দিয়ে হবে,
> কারণ PHP 8.4-এ `ext-imap` কোর থেকে সরিয়ে ফেলা হয়েছে।

### ৪. ব্রাউজারে ইনস্টলার চালান

`http://helpdesk.local/` খুলুন। তিন ধাপে ইনস্টল হবে:

1. **সার্ভার প্রস্তুতি** — সাতটি পরীক্ষা
2. **ডেটাবেস** — ডেটাবেস না থাকলে নিজেই তৈরি হবে, ৫১টি টেবিল ও ডিফল্ট ডেটা বসবে
3. **অ্যাডমিন অ্যাকাউন্ট** — সুপার অ্যাডমিন তৈরি, `.env` লেখা হবে

ইনস্টলের পর `/install` রুট নিজে থেকেই বন্ধ হয়ে যায়।

### ৫. Composer (ঐচ্ছিক — P4-এর আগে দরকার নেই)

```bash
composer install
```

Composer ছাড়াও অ্যাপ চলে, কারণ নিজস্ব PSR-4 অটোলোডার আছে। শুধু ইমেইল
পাঠানো/আনার ফিচারগুলোর জন্য PHPMailer ও php-imap লাগবে।

---

## ডেভেলপমেন্ট

```bash
# টেস্ট চালান (MySQL সার্ভার লাগে না)
php tests/run.php

# XAMPP ছাড়াই দ্রুত চালাতে
cp .env.example .env
php -S 127.0.0.1:8000 -t public
```

### টেস্ট কী যাচাই করে

- **`tests/core_test.php`** — QueryBuilder-এর SQL তৈরি ও ইনজেকশন প্রতিরোধ,
  Validator, Hash, Str, Env, Config
- **`tests/schema_test.php`** — ৫১টি টেবিলের ৮২টি ফরেন কী সত্যিই বিদ্যমান
  টেবিল ও কলামে যাচ্ছে কি না, টেবিল তৈরির ক্রম ঠিক আছে কি না,
  কনস্ট্রেইন্টের নাম অনন্য কি না

---

## ফোল্ডার কাঠামো

```
helpdesk/
├── public/          ← Apache DocumentRoot (একমাত্র পাবলিক ফোল্ডার)
│   ├── index.php    ফ্রন্ট কন্ট্রোলার
│   └── assets/      css · js · vendor
├── app/
│   ├── Core/        Router · Database · QueryBuilder · Auth · Validator · View …
│   ├── Controllers/ Client/ · Agent/ · Admin/
│   ├── Services/    Installer · AuditService
│   ├── Middleware/  VerifyCsrf · AuthAgent · RequireAdmin · Throttle …
│   └── Views/       layouts/ · partials/ · client/ · agent/ · install/
├── config/          app · database · permissions
├── database/        schema.sql (৫১ টেবিল)
├── storage/         attachments · logs · cache   ← ওয়েব রুটের বাইরে
├── routes/web.php
├── tests/
└── cron/            (P4-এ যোগ হবে)
```

---

## নিরাপত্তা

| ব্যবস্থা | বাস্তবায়ন |
|---|---|
| পাসওয়ার্ড | Argon2id (fallback bcrypt cost 12), লগইনে স্বয়ংক্রিয় rehash |
| সেশন | HttpOnly · SameSite=Lax · লগইনে `session_regenerate_id(true)` · নিষ্ক্রিয়তায় টাইমআউট |
| CSRF | সব POST/PUT/DELETE-এ বাধ্যতামূলক, `hash_equals()` |
| SQL ইনজেকশন | ১০০% prepared statement; টেবিল/কলাম/অপারেটর হোয়াইটলিস্টেড |
| XSS | সব আউটপুটে `e()` হেল্পার |
| ব্রুট ফোর্স | `login_attempts` — IP ও অ্যাকাউন্ট দুটোতেই সীমা |
| ফাইল | `storage/` ওয়েব রুটের বাইরে, র‍্যান্ডম নাম, permission যাচাই করে সার্ভ |
| অডিট | সব সংবেদনশীল কাজ `activity_log`-এ |

`.env` কখনো git-এ যাবে না (`.gitignore`-এ আছে)। প্রোডাকশনে `APP_DEBUG=false` রাখুন।

---

## লাইসেন্স

MIT
