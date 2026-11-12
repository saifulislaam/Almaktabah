# AlMaktabah — Islamic Digital Library

A free Islamic digital library platform: anyone can read online, a free account
(email + password, verified with a one-time code) unlocks uploading, downloading,
wishlists, and personalized recommendations. Built in native PHP + MySQL — the only
external code is PHPMailer, vendored directly (no Composer needed to run it).

---

## 1. Requirements

- PHP 8.0+ with extensions: `pdo_mysql`, `gd`, `fileinfo`, `curl`, `openssl`, `json`, `session`, `mbstring`
- MySQL 5.7+ or MariaDB 10.3+
- Apache with `mod_rewrite` and `mod_headers` (or translate `.htaccess` to Nginx — see §6)

## 2. Setup

1. **Upload the files** to your web root (e.g. `public_html/almaktabah`).
2. **Create the database and import the schema:**
   ```bash
   mysql -u root -p -e "CREATE DATABASE almaktabah CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   mysql -u root -p almaktabah < database.sql
   ```
   This seeds 15 Islamic categories, sensible default settings, and one admin account.
3. **Configure the database connection** in `config/database.php`:
   ```php
   define('DB_HOST', 'localhost');
   define('DB_NAME', 'almaktabah');
   define('DB_USER', 'your_db_user');
   define('DB_PASS', 'your_db_password');
   ```
4. **Configure the site** in `config/config.php` — at minimum set `SITE_URL` to your real domain (no trailing slash).
5. **Set folder permissions** so PHP can write uploaded files:
   ```bash
   chmod -R 755 uploads/
   ```
6. **Point your web server's document root** at this folder (or a subfolder, matching `SITE_URL`).

### Local testing without Apache
```bash
php -S localhost:8000
```
(pretty URLs from `.htaccess` won't apply under the built-in server, but query-string
URLs like `book.php?id=1` work fine for testing).

### A note on hosting

The screenshot showed the site running at `refer-to-earn.42web.io` with Chrome flagging
it **"Dangerous"** in the address bar. That's Google Safe Browsing, and it's almost
certainly not about anything in this codebase — free hosts that hand out subdomains
under a shared parent domain (like 42web.io/InfinityFree) periodically get the whole
parent domain or specific past subdomains flagged because of what *other* users hosted
there, and readers who see that red "Dangerous" warning will bounce before ever reaching
the homepage. Worth checking the domain's status directly at
[Google Safe Browsing's transparency report](https://transparencyreport.google.com/safe-browsing/search)
and, if it's flagged, moving to a domain you control rather than a shared free subdomain
before pointing real users at it.

## What changed in this round

- **Fixed the actual bug behind the recurring "search bar in the wrong place" reports:**
  `asset()` (which builds every CSS/JS URL) had a cache-busting parameter that was
  hardcoded to `?v=1` and never changed — so no matter how many times style.css was
  fixed and re-uploaded, browsers that had already loaded the page once kept using
  their cached copy forever, and the fixes never visibly reached you. It's now based
  on each file's actual modification time (`filemtime()`), so every edit automatically
  gets a fresh URL. **Do one hard refresh (Ctrl+Shift+R / Cmd+Shift+R) after deploying
  this version** to clear whatever's currently cached; every deploy after that will
  update automatically.
- On top of that fix, the duplicate mobile search box is gone — there's one search
  entry point on mobile (the header icon, plus a matching sidebar link that opens the
  same bar), and it now has a real closed-by-default state at every screen width
  instead of only inside one breakpoint.
- Found and fixed a real overflow bug in the empty homepage sections (fresh installs
  with 0 books left an ugly gap) — they now show a proper branded empty state.
- Added a language switcher (see below).

## 3. Email delivery (PHPMailer + Gmail SMTP) — required for registration

Registration, email verification, and password reset all depend on outgoing email.
This project vendors [PHPMailer](https://github.com/PHPMailer/PHPMailer) directly
under `libraries/PHPMailer/` (no Composer needed) and sends through Gmail's SMTP server.

1. On the Gmail account you're sending from, turn on **2-Step Verification**
   (required before Google will issue app passwords):
   `https://myaccount.google.com/security`
2. Generate an **App Password**: `https://myaccount.google.com/apppasswords`
   (choose "Mail" as the app). Google gives you a 16-character password — this is
   **not** the account's normal login password, and normal passwords are rejected by
   Gmail's SMTP for security.
3. Open `config/config.php` and set:
   ```php
   define('SMTP_USERNAME', 'almaktabahislamia@gmail.com');
   define('SMTP_PASSWORD', 'your16charapppassword'); // no spaces
   ```
   The project ships with `SMTP_USERNAME` already set to `almaktabahislamia@gmail.com`
   as requested; `SMTP_PASSWORD` is a placeholder — **the app password wasn't provided
   in the request, so this one line needs a real value before registration/OTP emails
   will send.** Until it's set, `register.php`/`forgot-password.php` will still work
   and show a clear "email sending isn't configured yet" message instead of silently
   failing or crashing.
4. Admin → Settings shows a live "Configured / Not configured" status for this.

## 4. Language switcher

A dropdown in the top-right of the header (and mirrored as a plain link in the mobile
sidebar) lets a visitor switch between **English, Arabic, and Urdu** — chosen because
they match the site's actual audience, rather than adding shallow coverage across more
languages. Selecting one:

- Sets a 1-year cookie immediately, so it persists for guests too
- Saves to the account's `language_preference` column for logged-in users, so it follows
  them across devices
- Switches `<html lang dir>` to `rtl` for Arabic/Urdu, which — combined with the RTL
  CSS block at the end of `assets/css/style.css` — mirrors the sidebar, dropdowns,
  breadcrumbs, and directional icons correctly, not just the text

**Scope, honestly:** this translates the site's chrome — header, sidebar, footer, and
the homepage — plus full RTL layout. Deeper content (admin panel, book descriptions
users write, form validation messages, deep settings pages) stays English for now;
`t()` falls back to the English string cleanly wherever a key isn't translated yet,
so nothing breaks or shows blank, it just isn't localized. `languages/en.php`,
`languages/ar.php`, `languages/ur.php` are plain key→string arrays — extending
coverage is a matter of adding more keys there and wrapping more strings in `t()`,
not restructuring anything. The admin login page deliberately ignores the visitor's
language cookie (`$forceLtr = true` in `admin/index.php`) since it's a separate,
untranslated area — letting it inherit a public visitor's RTL preference would mirror
an all-English page, which reads as broken rather than actually localized.

## 5. Authentication model

- **Regular readers:** email + password only, via `register.php` → a 6-digit OTP
  emailed to them (`verify-otp.php`, 10-minute expiry, 5 wrong-attempt cap, 60-second
  resend cooldown) → account activated → signed in automatically. `login.php` is
  email + password (not username), matching "email login only." `forgot-password.php`
  / `reset-password.php` use the same OTP mechanism for account recovery.
- **Admin/sub-admin:** a separate username + password login at `/admin/index.php` —
  intentionally not the public flow, since these are accounts the main admin creates
  directly for a small trusted group rather than the general public. No OTP round-trip
  for these (the main admin is vouching for the account by creating it).

## 6. Admin panel

Sign in at `/admin/index.php` (a separate login from the public email/password flow —
this is intentional, since it's a small trusted group rather than the general public).

- **Username:** `admin`
- **Password:** `admin123`

**Change this password immediately** from Admin → Settings — the dashboard will show a
warning banner until you do. From there the main admin can also create **sub-admins**
with a username/password and a specific set of permissions (manage books, manage users,
manage categories, moderate reviews, view analytics, manage settings).

## 7. What's built

**Public site:** email + password auth with OTP email verification and password reset,
home feed (personalized recommendations / trending / new arrivals / by category / top
rated), full-text search with filters, category and author pages, book detail pages
with online reading (PDF/EPUB/TXT in-browser; other formats show a download prompt, per
spec) plus gated downloads, 5-star ratings and reviews, wishlist, reading history with
resume-where-you-left-off, follow-a-category, profile pages, account settings,
light/dark/auto theme, dynamic sitemap + robots.txt + Schema.org JSON-LD, and a fully
responsive mobile-first layout — a single, clearly-triggered mobile search (header icon,
plus a matching entry in the drawer sidebar using the exact same component so it can't
render as a stray floating box), fluid typography, 44px+ touch targets on touch devices,
no fixed-width elements left that could cause horizontal scroll on narrow screens, and
a real cache-busting scheme so CSS/JS fixes reach browsers reliably. Plus a language
switcher (English/Arabic/Urdu, full RTL layout — see §4).

**Admin dashboard:** live "visitors online now" (heartbeat + polling), stat cards,
Chart.js traffic/category/upload/growth charts, book management with approve / reject /
feature / bulk actions, category & author management, review moderation, user
management (suspend/ban/remove), sub-admin accounts with granular permissions, site
settings (upload limits, maintenance mode, guest reading toggle, live email-config
status), and an activity log. Fully responsive with a collapsible sidebar.

**Recommendation engine** (`classes/Recommendation.php`): a genuine hybrid system —
item-based collaborative filtering (co-occurrence across readers' history), content-based
matching (category/author affinity), trending/popularity, recency decay, and quality
(rating), combined into one weighted score. See that file's docblock for why this is the
practical version of the brief's YouTube-style architecture rather than a literal
deep-learning stack — true embeddings/RL would need a separate Python service, which
isn't realistic inside plain PHP, but the schema here (`reading_history`, `book_views`,
`book_downloads`) already has everything such a service would train on if you build one later.

## 8. Deliberately out of scope (roadmap)

To keep what's shipped solid rather than sprawling and shallow, these weren't built —
each is a reasonable follow-up, not a missing "core" piece:

- Full multi-language UI (Arabic/Urdu translation + RTL layout) — the CSS is structured
  to make this straightforward, but only English ships
- Discussion forums, book clubs, reading challenges, badges
- Donations / premium membership / payment processing (shouldn't be faked without a real,
  PCI-aware payment gateway)
- Two-factor authentication, social login (Google/Facebook/etc.)
- Native mobile app / PWA wrapper
- Virus scanning on uploads (recommend ClamAV at the server level for production)
- True real-time (WebSocket) visitor tracking — the polling heartbeat here is the
  practical equivalent on standard shared PHP hosting

## 9. Security notes

- All passwords (regular users, admin, sub-admin) are hashed with bcrypt — never
  stored or logged in plain text. OTP codes are hashed (sha256) in the database too.
- All queries use PDO prepared statements.
- CSRF tokens on every state-changing form.
- Uploaded files are renamed to random hex strings and stored with `.htaccess` rules
  that block script execution in `/uploads/`, even if someone renames a file to `.php`.
- Login rate-limiting locks an identifier out after 5 failed attempts; OTP entry is
  capped at 5 wrong guesses per code, with a 60-second resend cooldown.
- The forgot-password form always shows the same response whether or not an email is
  registered, so it can't be used to discover which addresses have accounts.
- Before going live, replace the placeholder text in `privacy.php` and `terms.php` with
  real, reviewed policies, and update the `Sitemap:` line in `robots.txt` with your actual domain.

## 10. Project structure

```
config/       Database + site configuration (incl. SMTP credentials)
classes/      Database, Auth, Otp, Mailer, I18n, Book, Category, Author, Review, Wishlist,
              Notification, Recommendation, Search, User, Admin, Upload, Csrf
languages/    en.php, ar.php, ur.php — plain key→string arrays for the I18n class
libraries/    Vendored PHPMailer (3 files, pulled from GitHub — no Composer)
includes/     Shared header/sidebar/footer partials + helper functions
assets/       CSS (style.css, admin.css), JS (main.js, reader.js, admin.js), images
admin/        Admin dashboard (own layout, own auth)
api/          AJAX endpoints (search suggest, wishlist, follow, progress, visitor heartbeat)
uploads/      Book files, covers, avatars (each with a script-blocking .htaccess)
database.sql  Full schema + seed data (categories, settings, admin account)
```

## 11. If you outgrow this

- **Caching:** add Redis for session storage and recommendation candidate caching once
  the catalog gets large.
- **Queueing:** move notification fan-out (currently synchronous) to a queue if you get
  many followers per category, and consider a proper mail queue if OTP volume gets high
  (Gmail SMTP has sending-rate limits — fine for moderate signup volume, not built for
  bulk mail).
- **Search:** MySQL FULLTEXT is solid up to a few hundred thousand books; Elasticsearch/Meilisearch
  is the natural upgrade beyond that.
