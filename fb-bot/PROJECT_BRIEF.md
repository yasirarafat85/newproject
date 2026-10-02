# Project Brief: Facebook Page AI Automation

Source of truth for the project. Updated from the original PDF brief after
agreeing on a new order of work: **comment auto-reply first**, Messenger inbox
and image posting later.

## How to work with me
- Explain things in Bengali. Code, comments and commit messages in English.
- One phase at a time: short plan → approval → build → exact test steps.
- For steps outside the code (Meta dashboard, cPanel, pasting keys), give click-by-click instructions.
- Check current official docs for Graph API / AI provider details; don't rely on memory.
- Ask when a "Fill in" item is empty. Never invent business facts.
- Keep it simple. No framework, no extra services unless agreed.

## Scope now: comment auto-reply for one Page (my own)
1. Someone comments on a Page post → webhook (`feed` field, `item: comment`, `verb: add`) → saved as a job.
2. Cron worker generates a reply from `kb/knowledge.md` and posts it as a reply under that comment.
3. Unknown answer → polite handoff reply, comment marked `needs_human`, shown in the admin panel.
4. `DRY_RUN=true` first: replies are only logged and shown in the panel.

Rules:
- Never reply to the Page's own comments (prevents loops). Ignore edits and deletions.
- One reply per comment (dedupe by comment ID; Facebook retries deliveries).
- Answer only from the knowledge base. Never invent prices, dates, discounts or policies.
- Bengali by default, short and polite; reply in the commenter's language.
- Per-user hourly limit and a daily Page limit (from `.env`).
- Comment text is untrusted input: it can only ever produce a reply, nothing else.

## Admin panel (Bengali, mobile friendly, password protected)
Dashboard (stats + system checks + recent logs), Comments list, Needs-human queue with manual reply,
Knowledge base editor, Settings (bot on/off, DRY_RUN, limits, tone), Logs.
Secrets (tokens, keys, password hash) stay in `.env` and are never shown in the panel.

## Tech stack
- PHP 8.2+, no framework, cURL for HTTP
- SQLite via PDO; schema kept portable for a later move to MySQL
- `.env` for secrets and settings, committed `.env.example`
- Text AI: Claude API via the official Anthropic PHP SDK (composer). Model, effort, DRY_RUN, tone and limits are switchable in the admin panel; API key only in `.env`
- Hosting: shared cPanel, subdomain document root = `fb-bot/public`, cron worker, deploy with cPanel Git Version Control

## Webhook and reliability rules
- GET verification handshake and POST delivery.
- Verify `X-Hub-Signature-256` with the App Secret on every POST; reject on failure.
- Return 200 quickly; slow work happens in the cron worker.
- Log every inbound event, outbound call and error to SQLite. Never log tokens or keys.

## Security rules
- Only `public/` is web reachable. `.env`, database, logs are outside it (plus deny rules as a safety net).
- `.gitignore` covers `.env`, the database and `public/media/`.
- Admin login: bcrypt hash in `.env`, session cookie httponly/samesite, CSRF tokens, lockout after 5 failures in 15 minutes.

## Meta access note
Comment webhooks are only delivered to apps in **Live** mode (needs the privacy policy URL, `public/privacy.php`).
Whether Standard Access is enough for my own Page is verified in the Phase 1 test.
Messenger replies to the public need `pages_messaging` Advanced Access (App Review), which is why the inbox comes later.

## Phases
0. **Scaffold + admin login.** Structure, `.env.example`, migrations, setup and password scripts, dashboard with system checks and cron heartbeat, privacy page, README. Test: log in on the subdomain, all checks green, cron interval visible.
1. **Webhook + Meta App Live.** Verification, signature check, `feed` event logging, Logs page. Test: a comment from another account appears in the panel log.
2. **AI comment reply.** Worker + job queue with retries, limits, Claude API with structured output, DRY_RUN, settings page (model switch), KB editor with live test, cost tracking. Test: a known question gets a correct reply, exactly once.
3. **Handoff queue (done).** Needs-human queue with manual reply from the panel, approve-and-post for DRY_RUN replies. Test: an unknown question shows up in the queue and can be answered from the panel.
4. *Later:* Messenger inbox replies (after App Review), then image generation → owner approval → publish (from the original brief).

## Fill in before starting
- Page name and Page ID:
- Production subdomain (HTTPS):
- Knowledge base content (products, prices, how to order, delivery, payment, contact, hours):
- Handoff contact shown to people:
- Reply tone:
- Limits: replies per user per hour, max replies per day:
