<?php

declare(strict_types=1);

require __DIR__ . '/../bootstrap.php';

use App\Env;
use App\View;

// Public privacy policy and data deletion page. Meta requires this URL
// before an app can be switched to Live mode.

$page = View::e(Env::get('PAGE_NAME', Env::get('APP_NAME', 'Our Facebook Page')));
$email = Env::get('PRIVACY_CONTACT_EMAIL');
$contact = $email !== ''
    ? '<a href="mailto:' . View::e($email) . '">' . View::e($email) . '</a>'
    : View::e('the Page inbox');
$updated = date('F Y', (int) filemtime(__FILE__));
?>
<!doctype html>
<html lang="bn">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Privacy Policy · <?= $page ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&family=Plus+Jakarta+Sans:wght@600;700;800&family=Hind+Siliguri:wght@400;500;600;700&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/admin.css">
<script src="assets/theme.js"></script>
<style>
  .doc { max-width: 760px; margin: 0 auto; padding: 48px 16px; }
  .doc .card { margin-bottom: 24px; }
  .doc h1 { margin-bottom: 8px; }
  .doc ul { padding-left: 20px; }
</style>
</head>
<body>
<main class="doc">
  <h1>Privacy Policy</h1>
  <p class="muted"><?= $page ?> · Last updated <?= View::e($updated) ?></p>

  <section class="card">
    <h2>English</h2>
    <p>This service is an automated assistant for the Facebook Page <strong><?= $page ?></strong>.
       It replies to comments on the Page's posts using a knowledge base written by the Page owner.</p>
    <h3>What we receive</h3>
    <ul>
      <li>The text of comments posted on our Page's posts.</li>
      <li>The commenter's public name and Page-scoped ID, as sent by Facebook.</li>
      <li>The ID and time of the comment and the post it belongs to.</li>
    </ul>
    <h3>How we use it</h3>
    <ul>
      <li>To write and post a reply to the comment.</li>
      <li>To stop abuse (rate limits) and to let a human team member follow up.</li>
    </ul>
    <p>Comment text is sent to an AI text service only to generate the reply. We do not sell or share
       this data for advertising, and we do not use it for any other purpose.</p>
    <h3>Storage</h3>
    <p>Data is stored in a private database on our own server, which is not publicly accessible,
       and is kept only as long as needed to handle the conversation and keep basic records.</p>
    <h3>Data deletion</h3>
    <p>To have your data deleted, contact us at <?= $contact ?> with your Facebook name and a link to your comment.
       We will delete the stored data and confirm once it is done.</p>
  </section>

  <section class="card">
    <h2>বাংলা</h2>
    <p>এই সার্ভিসটি <strong><?= $page ?></strong> ফেসবুক পেজের একটি স্বয়ংক্রিয় সহকারী। পেজের পোস্টে করা
       কমেন্টের উত্তর দেয়, পেজ-মালিকের লেখা তথ্যভাণ্ডার থেকে।</p>
    <h3>আমরা যা পাই</h3>
    <ul>
      <li>আমাদের পেজের পোস্টে করা কমেন্টের লেখা।</li>
      <li>কমেন্টকারীর পাবলিক নাম ও Facebook-এর দেওয়া পেজ-ভিত্তিক আইডি।</li>
      <li>কমেন্ট ও পোস্টের আইডি এবং সময়।</li>
    </ul>
    <h3>যেভাবে ব্যবহার করি</h3>
    <ul>
      <li>কমেন্টের উত্তর তৈরি করে পোস্ট করতে।</li>
      <li>অপব্যবহার ঠেকাতে এবং দরকার হলে আমাদের টিমের কেউ যেন যোগাযোগ করতে পারে।</li>
    </ul>
    <p>উত্তর তৈরির জন্য কমেন্টের লেখা একটি AI সার্ভিসে পাঠানো হয়। এই তথ্য বিক্রি বা বিজ্ঞাপনের কাজে ব্যবহার করা হয় না।</p>
    <h3>সংরক্ষণ</h3>
    <p>তথ্য আমাদের নিজস্ব সার্ভারের একটি প্রাইভেট ডাটাবেসে থাকে, যা পাবলিকভাবে দেখা যায় না, এবং প্রয়োজনের বেশি সময় রাখা হয় না।</p>
    <h3>তথ্য মুছে ফেলা</h3>
    <p>আপনার তথ্য মুছতে চাইলে <?= $contact ?>-এ আপনার ফেসবুক নাম ও কমেন্টের লিংক পাঠান। আমরা তথ্য মুছে আপনাকে জানাব।</p>
  </section>
</main>
</body>
</html>
