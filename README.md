# GP Bill Tracker 📱

কোম্পানি-ভিত্তিক মোবাইল বিল ট্র্যাকিং অ্যাপ — বিল পরিশোধ, ব্যালেন্স ম্যানেজমেন্ট আর কোম্পানি খরচের রিপোর্ট।
আগে চলত **Google Apps Script + Google Sheets**-এ; এখন **Neon (Postgres) + Vercel**-এ migrate করা।

## আর্কিটেকচার

| স্তর | প্রযুক্তি |
|---|---|
| Frontend | স্ট্যাটিক `index.html` (Vanilla JS, XLSX + SweetAlert2 CDN) |
| Backend | Vercel serverless functions (`/api/*.js`) |
| Database | Neon — serverless Postgres |

Frontend `fetch('/api/...')` দিয়ে backend-এ কথা বলে। "শিট" = একটি `company + period` (যেমন `IBN_SINA_March-2026`)।

## API endpoints

| Endpoint | Method | কাজ | Auth |
|---|---|---|---|
| `/api/sheets` | GET | সব company_period তালিকা | — |
| `/api/data?sheet=` | GET | নির্দিষ্ট শিটের বিল রো | — |
| `/api/complete` | POST | বিল "Completed" মার্ক (`{id}`) | — |
| `/api/deduct` | POST | ব্যালেন্স থেকে বাদ + খরচ ট্র্যাক | — |
| `/api/balance` | GET / POST | ব্যালেন্স দেখা / সেট (POST=admin) | POST: admin |
| `/api/spending` | GET / POST | কোম্পানি খরচ / রিসেট | POST: admin |
| `/api/import` | POST | XLSX/CSV রো ইমপোর্ট | admin |
| `/api/login` | POST | অ্যাডমিন পাসওয়ার্ড যাচাই | — |

Admin write-endpoint গুলো `x-admin-pass` হেডার দিয়ে সুরক্ষিত (মান = `ADMIN_PASSWORD` env)।

## ডেটাবেস (Neon)

টেবিল: `bills`, `config` (ব্যালেন্স), `company_spending`। স্কিমা: [`db/schema.sql`](db/schema.sql)।
অ্যাপ প্রথমবার চললে টেবিলগুলো নিজে থেকেই তৈরি হয়ে যায় (`ensureSchema`), তাই ম্যানুয়ালি SQL চালানো বাধ্যতামূলক নয়।

## ডিপ্লয়মেন্ট

### ১. Neon
1. [neon.com](https://neon.com/) → নতুন Project
2. **Connection string** কপি করুন (`postgresql://...`)

### ২. Vercel
1. [vercel.com](https://vercel.com/) → এই GitHub রিপো **Import**
2. **Settings → Environment Variables**:
   - `DATABASE_URL` = Neon connection string
   - `ADMIN_PASSWORD` = আপনার পছন্দের পাসওয়ার্ড
3. **Deploy**

লোকাল ডেভে `.env.example` দেখুন (`vercel dev` চালালে)।

## পুরনো ডেটা মাইগ্রেশন

Google Sheet → CSV/XLSX Export → অ্যাপে **Admin → ফাইল আপলোড** দিয়ে ইমপোর্ট। কলাম ক্রম: `Sl.No., Mobile No., Name, Dept., Bill, Status`।

## নিরাপত্তা

- ডেটাবেস credential কখনো git-এ রাখবেন না — শুধু Vercel env-এ।
- `ADMIN_PASSWORD` একটি শক্ত মান দিন (ডিফল্ট `admin123` উৎপাদনে ব্যবহার করবেন না)।
