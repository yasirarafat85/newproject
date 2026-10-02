# Phase 1: Meta App সেটআপ ও Webhook চালু করা

> Meta তাদের dashboard মাঝে মাঝে বদলায়। কোনো বাটনের নাম হুবহু না মিললে কাছাকাছি নামের অপশনটা খুঁজুন।
> আটকে গেলে স্ক্রিনশট পাঠান।

শুরুর আগে নিশ্চিত হোন:
- Phase 0 শেষ হয়েছে। প্যানেলে লগইন করা যায়।
- সার্ভারে নতুন কোড আনা হয়েছে: cPanel → **Git Version Control** → **Manage** → **Pull or Deploy** → **Update from Remote**।
- Terminal-এ একবার `cd ~/repos/newproject/fb-bot && php bin/setup.php` চালিয়েছেন। এতে নতুন সেটিং যোগ হবে, আর Verify token তৈরি হবে।

---

## ধাপ ১: আগে সার্ভার নিজে নিজে টেস্ট করুন (Meta ছাড়াই)

Terminal-এ চালান:
```bash
cd ~/repos/newproject/fb-bot
nano .env        # APP_SECRET-এ আপাতত যেকোনো লেখা দিন, যেমন test123 (ধাপ ৩-এ আসলটা বসাবেন)
php tests/send-fake-comment.php https://আপনার-সাবডোমেইন/webhook.php "দাম কত?"
```
`Response: HTTP 200 EVENT_RECEIVED` দেখালে প্যানেল → **কমেন্ট** পেজে কমেন্টটা দেখা যাবে।
তার মানে আপনার সার্ভার তৈরি। 🎉

---

## ধাপ ২: Meta App তৈরি

1. https://developers.facebook.com এ যান → উপরে **My Apps** → **Create App**।
2. App name দিন (যেমন `My Page Bot`) এবং আপনার ইমেইল দিন → **Next**।
3. **Use case** তালিকা থেকে বাছাই করুন **"Manage everything on your Page"** → **Next**।
4. Business portfolio চাইলে নিজের business বাছাই করুন, অথবা "I don't want to connect a business portfolio yet" → **Next**।
5. শেষে **Create app** চাপুন। Facebook পাসওয়ার্ড চাইলে দিন।

## ধাপ ৩: App ID আর App Secret

1. বাম মেনু → **App settings** → **Basic**।
2. **App ID** কপি করুন।
3. **App secret**-এর পাশে **Show** চাপুন (পাসওয়ার্ড চাইবে) → কপি করুন।
4. সার্ভারের `.env` ফাইলে বসান:
   ```
   APP_ID=কপি করা App ID
   APP_SECRET=কপি করা App Secret
   ```
5. একই **Basic** পেজে নিচে পূরণ করুন:
   - **Privacy Policy URL**: `https://আপনার-সাবডোমেইন/privacy.php`
   - **User data deletion**: "Data deletion instructions URL" বাছাই করে একই লিংক দিন
   - **Category**: Business and Pages (বা কাছাকাছি কিছু)
   - **App icon**: ১০২৪×১০২৪ যেকোনো লোগো
6. **Save changes**।

> App Secret কাউকে দেখাবেন না, কোথাও পেস্ট করবেন না। শুধু `.env`-এ রাখবেন।

## ধাপ ৪: Permission যোগ করা

1. বাম মেনু → **Use cases** → "Manage everything on your Page" → **Customize**।
2. **Permissions** অংশে এগুলোর পাশে **Add** চাপুন (আগে থেকে থাকলে বাদ):
   - `pages_show_list`
   - `pages_read_engagement`
   - `pages_read_user_content`
   - `pages_manage_metadata`
   - `pages_manage_engagement` (Phase 2-এ কমেন্টের উত্তর দিতে লাগবে)

## ধাপ ৫: Webhook সেট করা

1. প্যানেলের **ড্যাশবোর্ড** → "Facebook সংযোগের তথ্য" কার্ড খুলুন। সেখানে **Callback URL** আর **Verify token** আছে।
2. Meta-তে একই Customize পেজে **Webhooks** অংশ খুঁজুন। না পেলে বাম মেনু → **Webhooks**।
3. Object হিসেবে **Page** বাছাই করুন → **Subscribe to this object** (অথবা **Edit subscription**)।
4. **Callback URL** আর **Verify token** প্যানেল থেকে কপি করে বসান → **Verify and save**।
   - সফল হলে প্যানেলের **লগ**-এ দেখাবে "Webhook verified by Meta"।
   - ব্যর্থ হলে: URL-টা `https://` দিয়ে শুরু হচ্ছে কিনা দেখুন, আর ব্রাউজারে `https://আপনার-সাবডোমেইন/webhook.php` খুললে "Forbidden" দেখায় কিনা দেখুন।
5. নিচের field তালিকায় **feed** খুঁজে **Subscribe** চাপুন।

## ধাপ ৬: পেজ যুক্ত করা (Page token)

1. https://developers.facebook.com/tools/explorer খুলুন।
2. ডানদিকে **Meta App**-এ আপনার app বাছাই করুন।
3. **User or Page**-এ **User Token** রাখুন।
4. **Permissions**-এ ধাপ ৪-এর ৫টা permission যোগ করুন।
5. **Generate Access Token** চাপুন। পপআপে **আপনার পেজটা টিক দিন** → Continue → Save।
6. উপরের লম্বা token-টা কপি করুন।
7. cPanel **Terminal**-এ:
   ```bash
   cd ~/repos/newproject/fb-bot
   php bin/connect-page.php
   ```
   token পেস্ট করে Enter দিন। স্ক্রিনে token দেখা যাবে না, এটাই স্বাভাবিক। একাধিক পেজ থাকলে নম্বর দিয়ে বাছাই করুন।
   শেষে দেখাবে: `Done. Page is subscribed to: feed` ✅

এই স্ক্রিপ্ট নিজে থেকেই মেয়াদহীন Page token বানিয়ে `.env`-এ রাখে। পরে কখনো token কাজ না করলে আবার চালাবেন।

## ধাপ ৭: App Live করা

আসল কমেন্টের webhook আসে শুধু **Live** App-এ।
1. App dashboard-এর উপরে **App Mode: Development** টগল, অথবা বাম মেনুর **Publish** খুঁজুন।
2. **Live** করুন। কিছু অসম্পূর্ণ থাকলে Meta তালিকা দেখাবে। সাধারণত সেটা Privacy URL, icon বা category, যা ধাপ ৩-এ করেছেন।

## ধাপ ৮: আসল টেস্ট 🎯

1. **অন্য একটা Facebook অ্যাকাউন্ট** থেকে (বন্ধু বা দ্বিতীয় অ্যাকাউন্ট) আপনার পেজের যেকোনো পোস্টে কমেন্ট করুন।
2. কয়েক সেকেন্ডের মধ্যে প্যানেল → **কমেন্ট**-এ সেটা "নতুন" হিসেবে আসবে।
3. পেজ হিসেবে নিজে কমেন্ট করলে সেটা আসবে "বাদ" হিসেবে। এটা ঠিক আছে, বট নিজের কমেন্টে উত্তর দেবে না।

এই পর্যায়ে বট এখনো উত্তর দেবে না। উত্তর দেওয়ার কাজটা Phase 2-এ।

---

## কিছু না এলে যা দেখবেন

| লক্ষণ | কারণ ও সমাধান |
|---|---|
| লগে "Rejected payload with invalid signature" | `.env`-এর `APP_SECRET` ভুল। ধাপ ৩ আবার দেখুন। |
| লগে কিছুই নেই, "Facebook থেকে শেষ ইভেন্ট" খালি | App Live নয়, `feed` subscribe করা হয়নি, অথবা `connect-page.php` চালানো হয়নি। |
| শুধু আপনার নিজের অ্যাকাউন্টের কমেন্ট আসে, অন্যদেরটা আসে না | Meta অন্যদের ডেটার জন্য Advanced Access চাইছে (App Review)। আমাকে জানান, পরের পথ ঠিক করব। |
| কমেন্ট এসেছে কিন্তু নাম "অজানা ব্যক্তি" | Facebook কমেন্টকারীর তথ্য পাঠায়নি। উত্তর দিতে সমস্যা হবে না। |
| `connect-page.php`-এ "Facebook error" | Graph API Explorer-এ সব permission দিয়ে নতুন token নিয়ে আবার চালান। |
