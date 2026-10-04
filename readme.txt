=== Swift Checkout for WooCommerce ===
Contributors: devmonowar
Tags: woocommerce checkout, one page checkout, direct checkout, buy now button, checkout field editor
Requires at least: 6.8
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html
WC requires at least: 10.0
WC tested up to: 11.1

Turn the default WooCommerce checkout into a fast, distraction-free, high-converting checkout — no code needed.

== Description ==

Swift Checkout replaces the slow cart-then-checkout flow with a single fast page. Send shoppers straight from Add to Cart (or a Buy Now button) to checkout, show the cart inside checkout with AJAX quantity controls, hide the fields you don't need, and auto-apply coupons from a link.

Features in 1.0:

* Skip cart — Add to Cart redirects straight to checkout (global + per-product override)
* Buy Now buttons via shortcode: `[swift_buy_now id="123"]`
* Three checkout layouts — one-column (mobile-first), two-column, and multi-step (Details → Payment)
* Cart inside checkout with AJAX quantity +/- and remove (no page reload)
* Field editor lite — hide, require, and reorder billing/shipping fields
* Toggle order notes, coupon form, and terms
* Auto-apply coupon from URL (e.g. `?swco_coupon=SAVE10`) or a global coupon, with optional minimum subtotal
* Render checkout on any page: `[swift_checkout]` (Gutenberg block + Elementor widget included)
* Simple order bump — offer one product with a checkbox, optional discount %
* Address autocomplete via your own Google Places API key (empty = off)
* Lead opt-in form `[swift_optin]` — leads stay private in wp-admin, no external service
* Landing order section `[swift_landing id="123"]` — product + name/mobile/address form, direct COD order

No tracking and no page-builder lock-in — the shortcode works inside any theme or builder. The only external request is Google Places, and only when you add your own API key.

== Installation ==

1. Make sure WooCommerce is installed and active.
2. Upload `swift-checkout-for-woocommerce` to `/wp-content/plugins/` and activate it.
3. Go to WooCommerce → Settings → Swift Checkout to configure.

== Frequently Asked Questions ==

= How does skip cart work? =
Turn on "Skip cart globally" and Add to Cart buttons redirect to checkout. You can override this per product.

= Does it support the block-based (Cart/Checkout Blocks) checkout? =
Version 1.0 supports the classic shortcode-based checkout. Blocks support is on the roadmap.

= Is it compatible with HPOS (custom order tables)? =
Yes — compatibility is declared from day one and tested with HPOS on and off.

== Screenshots ==

1. Settings — General tab.
2. Settings — Checkout layout tab.
3. One-column checkout layout.
4. Two-column checkout layout.

== Changelog ==

= 1.0.0 =
* Initial release.
