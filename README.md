# MegaCards Australia storefront

Static storefront prototype for Japanese Pokemon, ONE PIECE, and Yu-Gi-Oh! boxes aimed at Australian collectors.

## Run locally

Open `index.html` directly, or start any static HTTP server in this directory.

For a production-like local preview with PHP installed:

```bash
php -S 127.0.0.1:8787 -t .
```

Then open `http://127.0.0.1:8787/index.html`. The frontend is portable and does not contain machine-specific paths or credentials.

Production requests enter through `index.php` and `faq.php`. `access_gate.php` checks the visitor country from the server-side IP address, caches only the country result under `data/geo_cache`, and requires a password-backed 12-hour session for access from Japan. Direct requests for the public HTML files are redirected through the PHP gate by `.htaccess`.

The storefront includes an accessible three-slide hero rotation and a member-registration dialog. `member_register.php` validates and rate-limits registrations, then stores member records with one-way password hashes in the web-blocked `data/members.json` file.

## Visual system

The storefront uses the white MCJ Collector Showroom layer in `white-showroom.css` on top of the shared `brand-theme.css` tokens, with progressive motion enhancements in `brand-motion.js`. The selected implementation reference is stored at `design/mcj-white-showroom-reference.png`; production brand artwork and optimized showroom illustrations are stored under `assets/brand`.

The visual layer is portable: it uses repository-relative asset paths, standard web fonts with fallbacks, CSS breakpoints, and `prefers-reduced-motion`. No machine-specific paths or credentials are required.

Fonts are loaded from Google Fonts and interface icons use the pinned Phosphor Icons Web 2.1.2 stylesheet from jsDelivr with subresource integrity checking. The production content security policy explicitly permits that pinned icon stylesheet and its font files. If either service is unavailable, the store remains usable with system fonts, visible labels, and critical button fallbacks. No build step is required.

## Product data

Product names, one-box reference prices, and product imagery were checked against SNKRDUNK item pages on 8 October 2026. Product images are stored locally under `assets/products` and served from MegaCards. AUD estimates use A$1 = ¥110.04.

For Pokemon boxes, use only the image attached to the standard sealed-box listing. Never use an image from a listing whose Japanese title begins with `【シュリンクなし】`, even when the package artwork looks the same. The six current Pokemon assets were verified byte-for-byte against their standard-box listing images on 8 October 2026.

## Shipping

The cart requests live Australia shipping quotes from the existing Ship&co integration. Product weight and dimensions, packaging weight, and carton padding are maintained at `shipping-admin.html` and stored in `data/shipping_products.json` through `shipping_products.php`.

The cart also estimates the Australian low-value import treatment from the product subtotal. Orders at or below A$1,000 AUD show the general ABF low-value treatment; orders above the threshold show the Import Declaration warning and JAEPA eligibility note.

## Payments

Checkout supports Wise payment links and PayPal invoices. PayPal orders include the existing 5% transaction fee, itemised before the order is submitted. Both methods are invoiced in JPY and reuse the production order and confirmation-email endpoints.

The Australia-focused customer FAQ is served through `faq.php` and covers ordering, shipping and customs, Japanese product details, and support.
