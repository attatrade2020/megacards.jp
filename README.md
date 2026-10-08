# MegaCards Australia storefront

Static storefront prototype for Japanese Pokemon, ONE PIECE, and Yu-Gi-Oh! boxes aimed at Australian collectors.

## Run locally

Open `index.html` directly, or start any static HTTP server in this directory.

## Product data

Product names, one-box reference prices, and product imagery were checked against the linked SNKRDUNK item pages on 8 October 2026. AUD estimates use A$1 = ¥110.04. The source link for each item is shown on its product card.

## Shipping

The cart requests live Australia shipping quotes from the existing Ship&co integration. Product weight and dimensions, packaging weight, and carton padding are maintained at `shipping-admin.html` and stored in `data/shipping_products.json` through `shipping_products.php`.

The Australia-focused customer FAQ is available at `faq.html` and covers ordering, shipping, GST and customs, Japanese product details, and support.
