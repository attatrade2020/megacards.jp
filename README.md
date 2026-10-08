# MegaCards Australia storefront

Static storefront prototype for Japanese Pokemon, ONE PIECE, and Yu-Gi-Oh! boxes aimed at Australian collectors.

## Run locally

Open `index.html` directly, or start any static HTTP server in this directory.

Production requests enter through `index.php` and `faq.php`. `access_gate.php` checks the visitor country from the server-side IP address, caches only the country result under `data/geo_cache`, and requires a password-backed 12-hour session for access from Japan. Direct requests for the public HTML files are redirected through the PHP gate by `.htaccess`.

The storefront includes an accessible three-slide hero rotation and a member-registration dialog. `member_register.php` validates and rate-limits registrations, then stores member records with one-way password hashes in the web-blocked `data/members.json` file.

## Product data

Product names, one-box reference prices, and product imagery were checked against SNKRDUNK item pages on 8 October 2026. Product images are stored locally under `assets/products` and served from MegaCards. AUD estimates use A$1 = ¥110.04.

## Shipping

The cart requests live Australia shipping quotes from the existing Ship&co integration. Product weight and dimensions, packaging weight, and carton padding are maintained at `shipping-admin.html` and stored in `data/shipping_products.json` through `shipping_products.php`.

The cart also estimates the Australian low-value import treatment from the product subtotal. Orders at or below A$1,000 AUD show the general ABF low-value treatment; orders above the threshold show the Import Declaration warning and JAEPA eligibility note.

## Payments

Checkout supports Wise payment links and PayPal invoices. PayPal orders include the existing 5% transaction fee, itemised before the order is submitted. Both methods are invoiced in JPY and reuse the production order and confirmation-email endpoints.

The Australia-focused customer FAQ is served through `faq.php` and covers ordering, shipping, GST and customs, Japanese product details, and support.
