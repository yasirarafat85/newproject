# FB Page Bot

Facebook Page-এর কমেন্টে AI দিয়ে স্বয়ংক্রিয় উত্তর দেওয়ার বট, সাথে বাংলা অ্যাডমিন প্যানেল।
PHP 8.2+ (কোনো framework নেই), SQLite, shared cPanel হোস্টিংয়ে চলে।

পুরো পরিকল্পনা আর নিয়ম: [`PROJECT_BRIEF.md`](PROJECT_BRIEF.md)

## ফোল্ডার

```
public/          ওয়েব রুট — সাবডোমেইন শুধু এই ফোল্ডারে পয়েন্ট করবে
  admin/         অ্যাডমিন প্যানেল
  webhook.php    Facebook webhook (Phase 1)
  privacy.php    Privacy Policy (Meta App Live করতে লাগবে)
  media/         পরে ছবি রাখার জায়গা
src/             মূল কোড
database/        ডাটাবেস migration
worker/run.php   cron দিয়ে প্রতি মিনিটে চলে
bin/             setup ও পাসওয়ার্ড স্ক্রিপ্ট
kb/knowledge.md  বট শুধু এখান থেকেই উত্তর দেবে
storage/         SQLite ডাটাবেস ও লগ (ওয়েব থেকে খোলা যায় না)
tests/run.php    নিজে নিজে চেক
```

## cPanel-এ সেটআপ (Phase 0)

### ১. কোড সার্ভারে আনা — Git Version Control
1. cPanel → **Git™ Version Control** → **Create**।
2. **Clone URL**: `https://github.com/yasirarafat85/newproject.git`
   - রিপো private হলে HTTPS দিয়ে কাজ করবে না। তখন cPanel → **SSH Access** → key বানিয়ে সেটার public key GitHub রিপো → Settings → **Deploy keys**-এ যোগ করুন, আর Clone URL দিন `git@github.com:yasirarafat85/newproject.git`।
3. **Repository Path**: `repos/newproject` (public_html-এর **বাইরে**)।
4. Create চাপুন। তারপর **Manage** → **Basic Information** থেকে ব্রাঞ্চ বাছাই করুন (এখন `ccr-82f3d756-xicqdb`, merge হলে `main`)।
5. পরে আপডেট আনতে: **Manage** → **Pull or Deploy** → **Update from Remote**।

### ২. সাবডোমেইনকে `public` ফোল্ডারে পয়েন্ট করা
cPanel → **Domains** → আপনার সাবডোমেইন → **Manage** → **Document Root** দিন:
```
repos/newproject/fb-bot/public
```
> ⚠️ কখনো `fb-bot` ফোল্ডারে পয়েন্ট করবেন না, শুধু `fb-bot/public`।

### ৩. PHP ভার্সন
cPanel → **MultiPHP Manager** → সাবডোমেইন বাছাই করে **PHP 8.2** বা বেশি দিন।

### ৪. Terminal-এ setup
cPanel → **Terminal**:
```bash
cd ~/repos/newproject/fb-bot
php -v                       # 8.2 বা বেশি দেখাতে হবে
php bin/setup.php            # .env আর ডাটাবেস তৈরি করবে
php bin/hash-password.php    # অ্যাডমিন প্যানেলের পাসওয়ার্ড সেট
php tests/run.php            # "All tests passed." দেখাবে
```
`php -v` যদি 8.2-এর কম দেখায়, তাহলে `ls /usr/local/bin/ | grep php` চালিয়ে দেখুন `ea-php82` বা `ea-php83` আছে কিনা, আর `php`-এর জায়গায় সেটা ব্যবহার করুন (যেমন `/usr/local/bin/ea-php82 bin/setup.php`)।

তারপর `.env` এডিট করুন (cPanel **File Manager** → `repos/newproject/fb-bot/.env` → Edit):
- `APP_URL` = `https://আপনার-সাবডোমেইন`
- `PAGE_NAME` = পেজের নাম
- `PRIVACY_CONTACT_EMAIL` = যোগাযোগের ইমেইল

### ৫. Cron (worker)
cPanel → **Cron Jobs** → **Add New Cron Job**:
- Common Settings: **Once Per Minute** (`* * * * *`)
- Command (`USER` বদলে আপনার cPanel ইউজারনেম দিন, `php`-এর পাথ ধাপ ৪ অনুযায়ী):
```
/usr/local/bin/php /home/USER/repos/newproject/fb-bot/worker/run.php >/dev/null 2>&1
```
হোস্ট যদি প্রতি মিনিটে চালাতে না দেয়, অ্যাডমিন প্যানেলের "Worker (cron)" সারিতে আসল সময় দেখা যাবে।

## Phase 0 টেস্ট
1. `https://আপনার-সাবডোমেইন/` খুলুন → লগইন পেজ আসবে।
2. পাসওয়ার্ড দিয়ে ঢুকুন → **সিস্টেম চেক**-এর সব সারিতে সবুজ বিন্দু।
3. ২–৩ মিনিট পরে রিফ্রেশ করুন → "Worker (cron)" সারিতে "প্রায় প্রতি **60** সেকেন্ডে চলছে" দেখাবে। (৩০০ বা ৯০০ দেখালে হোস্ট প্রতি মিনিটে cron চালাতে দেয় না।)
4. `https://আপনার-সাবডোমেইন/privacy.php` খুললে Privacy Policy দেখা যাবে।
5. নিরাপত্তা চেক — এই দুটো লিংক খুললে **কিছু দেখাবে না** (403 বা 404 আসবে):
   - `https://আপনার-সাবডোমেইন/.env`
   - `https://আপনার-সাবডোমেইন/storage/app.sqlite`
