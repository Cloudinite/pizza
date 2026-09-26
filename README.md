# Pizza Slice Pezinok – web, online orders & admin app

Website with online ordering for **Pizza Slice Pezinok**, plus an admin app for the phone.
It's plain PHP + MariaDB with no frameworks or build step, made for **WebSupport** shared hosting.

| | |
|---|---|
| **Website** (`/`) | White, phone-first site with the logo, today's special, the price boards (like the in-store TV menu), opening hours, contact, Facebook & Instagram. |
| **Menu & ordering** (`/menu`) | Every pizza slice and drink with an *Add* button. Pizzas open a sheet with **toppings** (extra cheese, ham, olives…). The cart sits at the bottom of the screen. |
| **Checkout** (`/kosik`) | Pickup **or delivery** (address, fee, minimum order), time slot, name, phone, note, pay cash/card on pickup. Online card payment is shown as *coming soon* (not functional, by design). |
| **Order tracking** (`/objednavka/…`) | The customer gets a private link, valid for **24 h**, with a live timeline: Prijatá → Pripravujeme → Pripravená → Na ceste → Doručená (pickup: … → Na vyzdvihnutie → Vyzdvihnutá). A "Sledovať stav" bar on the website takes them back to it. |
| **Admin app** (`/admin/`) | Installable on Android and iPhone. New orders appear on their own, with a sound, vibration and a notification. Each order shows the price, address (with a **Navigate** button), a tap-to-call phone number, and the pizzas with their toppings. One big button moves the order to its next step, all the way to **Delivered / Done**. |
| **Menu management** (`/admin/menu.php`) | Add, edit or delete items. Set prices, descriptions and photos, and switch items to *sold out* with one tap. Add the current **specialty**, edit toppings and their prices, and manage categories. |
| **Look** | Light or dark mode (or follow the phone), chosen per device under *Nastavenia → Vzhľad aplikácie* or with the ☀/🌙 button in the top bar. |
| **Settings** (`/admin/settings.php`) | Opening hours (default daily 10:00–21:00), a pause switch for online orders, a banner message, delivery fee and area, address, phone, social links, company details for the GDPR page, and password. |
| **Legal** | `/ochrana-osobnych-udajov` (GDPR privacy policy) and `/cookies` (cookie policy). Only strictly necessary cookies are used, so no consent banner is needed. |

The starting menu comes from the in-store price boards: 10 pizzas at 2,50 € per slice and 6 drinks at 2,20 €.
**Pizza descriptions, topping prices, the example special "Quattro formaggi" and the delivery fee (1,50 €, min. 10 €) are placeholders.** Adjust them in the admin app.

---

## Deploying to WebSupport

Requirements: PHP **8.1 or newer** (8.3 recommended) and MariaDB. Both are standard on WebSupport hosting.

1. **Create a database.** WebSupport admin → *Databázy* → new MariaDB database. Note the host, database name, user and password.
2. **Turn on SSL.** WebSupport admin → your domain → *SSL* → free Let's Encrypt certificate. The site redirects to HTTPS on its own.
3. **Set the PHP version** to 8.3 in the WebSupport admin, if it isn't already.
4. **Upload the files.** Upload the **contents of the `public/` folder** (not the folder itself) to the domain's web root over FTP/SFTP. On WebSupport that's usually the `web` folder. Include the hidden `.htaccess` files.
5. **Run the installer.** Open `https://your-domain.sk/install/` and fill in the database details and an admin name and password. The installer:
   - writes `app/config.php` with a freshly generated encryption key,
   - creates the tables and loads the menu,
   - creates your admin account and then locks itself.

   You can delete the `install` folder afterwards.
6. **Fill in your details.** Log in at `/admin/` → **Nastavenia**. Add the street address, phone, Google Maps link and company details (name, IČO, registered office; they appear in the privacy policy). **Check the Facebook link**: it's preset to `facebook.com/pizzaslicepk`, which is a guess.
7. **Check the menu.** Go to **Menu** and fix pizza descriptions and topping prices, then replace or delete the example special.

If the installer can't write `app/config.php` (unusual file permissions), it shows you the file contents. Create the file yourself over FTP. You can also import `app/sql/schema.sql` and `app/sql/seed.sql` in phpMyAdmin and fill in `app/config.sample.php` → `app/config.php`.

### Installing the admin app on the phone
- **Android (Chrome):** open `https://your-domain.sk/admin/`, log in, then use the menu **⋮ → Inštalovať aplikáciu** (or the *Nainštalovať aplikáciu* button).
- **iPhone (Safari):** open the same address, tap **Share → Pridať na plochu**.
- In the app, tap **🔔 Zapnúť zvuk a upozornenia** once. On a kitchen tablet, also turn on **Displej nezhasína** so the screen stays on.

You stay logged in for 30 days. Changing the password logs out every other device.

### Daily flow
1. A customer orders on the website. The app rings within a few seconds, and the Objednávky tab shows a red badge.
2. **Prijať objednávku** → **Pizza je hotová** → (delivery) **🚗 Odoslať – na ceste** → **✓ Doručená – hotovo**.
   For pickup: → **✓ Vyzdvihnutá – hotovo**.
3. The customer's tracking page follows each step automatically.
4. **Vybavené dnes** lists today's finished orders and **História** the last 200. The top of the screen shows today's order count and revenue.
5. When you're too busy, flip the **Prijímame** switch at the top to pause online orders.

---

## Security & privacy
- HTTPS is forced, with HSTS and a strict Content-Security-Policy (no inline scripts, no third-party code). The site also sends `X-Frame-Options`, `nosniff` and a Referrer-Policy.
- The server recalculates every price from the database; the browser's prices are never trusted. If the cart changed after the customer saw the total, the order isn't placed.
- Checkout is protected by a CSRF token, a same-origin check, a honeypot and a minimum form-fill time. It also allows at most 6 orders per 15 minutes from one IP.
- Customer name, phone, e-mail, address and note are **encrypted in the database** (libsodium, with AES-256-GCM as fallback). They're erased automatically after 90 days (configurable). IP addresses are only stored as keyed hashes, for 24 h.
- The tracking link uses a random 128-bit token (only its hash is stored) and expires after 24 h.
- Admin passwords use bcrypt. Login is throttled (10 failures per 15 minutes per IP). Admin sessions are stored in the database as hashed tokens, so shared-hosting session cleanup doesn't log you out. Every admin action needs a CSRF token.
- `app/` (code, config, SQL) is blocked from the web. Uploaded photos are re-encoded by GD (which strips metadata), and nothing in `uploads/` can execute.

The privacy and cookie texts are a solid template for a small Slovak food business, but have them reviewed for your specific company.

## Look & motion
- Warm, low-glare colours: a soft off-white page with warm near-black text instead of pure white/black. It still passes WCAG AA contrast.
- The "Vždy svieže každý deň · Always fresh everyday" strip scrolls left to right under the hero and above the footer. It pauses on hover.
- Smooth motion:
  - page cross-fades
  - cards glide in as you scroll
  - the category bar sticks and follows your position
  - the toppings sheet slides up
  - a slice flies into the cart
  - the tracking page's live step pulses
  - in the admin app, order cards glide into place instead of jumping
- All animations use only transform/opacity and turn off automatically when the phone's *Reduce motion* setting is on.

## Speed, stability & SEO
- About 5 KB of HTML + 7 KB of CSS + 4 KB of JS (gzipped). There's one self-hosted font subset (26 KB, with Slovak diacritics). No frameworks, no third-party requests. Pages render on the server in about 2 ms.
- **The layout never moves.** Every image has fixed dimensions, and the font uses `font-display: optional` with a size-matched fallback font. If the web font is slow, the phone's own font takes up the same space, so text doesn't re-wrap between pages. Buttons keep their size when pressed, and the cart bar, toppings sheet and toasts float over the page. The measured Cumulative Layout Shift is 0 on the menu, checkout and tracking pages.
- Touch targets are at least 44 px. The site has proper `<button>`s with Slovak labels, a skip link and visible focus outlines, and it works without JavaScript too.
- **Crawlable:** all content is rendered on the server. It includes `schema.org` Restaurant and Menu data (JSON-LD), Open Graph tags, canonical URLs, `/sitemap.xml` and `/robots.txt`.

## Known limits
- New-order alerts work while the admin app is open, including in the background on Android. For alerts when the app is fully closed (iPhone lock screen), the next step would be Web Push with VAPID keys.
- There's no e-mail or SMS confirmation. The customer gets the tracking page instead.
- Online card payment is intentionally not connected.

## Local development
```bash
# MariaDB running locally, then:
php -S 127.0.0.1:8080 -t public dev/router.php
# open http://127.0.0.1:8080/install/
```
`dev/router.php` imitates the `.htaccess` rewrites for PHP's built-in server. Don't upload it.

## Project layout
```
public/                 ← upload the contents of this folder to WebSupport
  index.php, menu.php, kosik.php, objednavka.php, cookies.php, ochrana-osobnych-udajov.php
  api/status.php        live order status for the customer's tracking page
  admin/                installable admin app (manifest, service worker, pages, API)
  app/                  PHP libraries, views, SQL (blocked from the web)
  assets/               CSS, JS, font, logo & icons
  install/              one-time installer
  uploads/              menu photos
dev/router.php          local dev router
```
