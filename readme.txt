=== Sokin Pay ===
Contributors: platasokin
Version: 1.1.6
Tags: woocommerce, payments, payment gateway, credit card, ecommerce
Requires at least: 6.5
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.1.6
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Easily accept payments on your WordPress site via Sokin Pay payment gateway.

== Description ==
This plugin seamlessly integrates with your WooCommerce store, providing a secure and efficient way to process payments. Enable a variety of secure payment options, including credit cards and pay by bank.
Register your business account at [sokin.com](https://sokin.com/business/business-account-signup/).

Sokin is a trading name of Plata Capital Ltd. More information about Sokin’s services and how to sign up can be found on [sokin.com](https://sokin.com).

#### Features
* Hosted checkout for Sokin payment methods enabled on your merchant account.
* Payment-status checks when customers return from checkout.
* Refund requests from the WooCommerce order screen.

#### External services and privacy

A Sokin merchant account with Sokin Checkout / Payment Acceptance enabled is required. Register at [Sokin](https://sokin.com/business/business-account-signup/) and contact your account manager or [Sokin support](https://support.sokin.com/) for API access and sandbox credentials. A paid service agreement may be required; installing this plugin does not create or activate a Sokin account.

The plugin connects to the API URL configured by the merchant:
* At checkout, it sends the customer's name, email, billing address, order amount, currency, and return URL to create a Sokin payment order. A different shipping address is included when provided. The customer is then redirected to the configured Sokin Checkout URL to complete payment.
* When the customer returns to the order-received page, the plugin requests the payment status using the Sokin order identifier.
* When a merchant creates a refund in WooCommerce, the plugin requests the order details and sends the Sokin payment identifier and refund amount to Sokin.

These requests use the merchant's API key. The plugin does not send analytics or telemetry on activation. Customer data is sent to provide the configured payment and refund service.

Production service hosts are `payment-gateway.sokin.com` and `pay.sokin.com`; sandbox hosts are `payment-gateway.uat.sokin.com` and `pay.uat.sokin.com`. If Sokin supplies different endpoints for your account, confirm them with your account manager before use.

* Privacy policy: [Sokin Global Privacy Policy](https://sokin.com/legal/privacy-policy).
* Service terms: [Sokin legal terms and policies](https://sokin.com/legal). Use the terms applicable to your account and region, as provided during merchant onboarding.
* Integration instructions: [Sokin Payment Acceptance plugin guide](https://support.sokin.com/article/payment-acceptance-plugin).

#### Configuration

1. Install and activate WooCommerce, then activate Sokin Pay.
2. Obtain an API key from the Sokin customer portal under Settings > Sokin Checkout API Configuration. Ask your account manager to enable this service if it is unavailable. Sandbox and production require their respective credentials.
3. In WordPress, go to WooCommerce > Settings > Payments > Sokin Pay.
4. Set the customer-facing Title and optional Description, and enter your X API Key. Production Checkout URL and API URL are filled in by default. Both URLs and the API key must belong to the same environment; URLs must use HTTPS.
5. For production, keep the default Checkout URL `https://pay.sokin.com/sokinpay/customerPay` and API URL `https://payment-gateway.sokin.com/api/services/v1`.
6. For sandbox testing, use Checkout URL `https://pay.uat.sokin.com/sokinpay/customerPay` and API URL `https://payment-gateway.uat.sokin.com/api/services/v1`, with sandbox credentials supplied by Sokin.
7. Select Enable Sokin Pay, save changes, and test checkout with Sokin's sandbox test credentials. Confirm successful, declined, and cancelled payment behavior before switching to production credentials and URLs. Test refunds from a WooCommerce order screen using sandbox transactions.

Do not mix sandbox credentials with production URLs. Never enter real card details in a sandbox environment or share your API key publicly.

#### Usage

Customers select Sokin Pay at checkout and complete their payment on Sokin's hosted checkout page. When they return to the store, the plugin requests the payment status and updates the order. It does not provide background webhook synchronization or a dispute-management screen.

View orders and request refunds under WooCommerce > Orders. Handle disputes through the Sokin service or support channels available for your merchant account.

#### Developer hooks

Sokin Pay exposes checkout field extension points through plugin-prefixed hooks:

* `platasokin_credit_card_form_start`
* `platasokin_credit_card_form_end`

If your integration previously used `woocommerce_credit_card_form_start` or `woocommerce_credit_card_form_end` for Sokin Pay checkout customizations, update it to the `platasokin_*` hook names above.

#### Support
For any issues or questions, please contact our support team at [support@sokin.com](mailto:support@sokin.com).

== Installation ==
* It is required to install WooCommerce plugin (https://wordpress.org/plugins/woocommerce) before Sokin Pay installation.
* Upload the Sokin Pay plugin to your WordPress website, activate it, and then configure the API settings.

== Frequently Asked Questions ==
Is WooCommerce plugin mandatory for Sokin Pay plugin?
Yes, Sokin Pay plugin is based on WooCommerce. It uses WooCommerce features and data.

== Changelog ==

= 1.1.6 =
* Released: 2026-11-03
* Allow up to 30 seconds for payment, refund, and order-status requests to Sokin.
* Confirm compatibility with WordPress 7.1 and declare WooCommerce as a required plugin.
* Load Sokin styles only on checkout pages.

= 1.1.5 =
* Released: 2026-06-22
* Fixed an issue where an order could be incorrectly marked as received after a failed or declined payment (for example, Alipay refusals). Failed and declined payments are now correctly marked as failed and the customer is returned to the payment page to try again.

= 1.1.4 =
* Released: 2026-05-04
* Routine maintenance update with WordPress.org plugin directory compliance improvements. No action required for most merchants; legacy gateway settings are migrated automatically on update.
* Heads-up for developers customizing the checkout: the credit card form extension hooks have been renamed from `woocommerce_credit_card_form_start` / `woocommerce_credit_card_form_end` to `platasokin_credit_card_form_start` / `platasokin_credit_card_form_end`. Update any custom integrations to the new hook names.

= 1.1.3 =
* Released: 2025-12-04
* fix: restrict script registration to WooCommerce order screens
* chore: update license to GNU GPL v2 and enhance documentation
* refactor: remove unused parameters from order payload

= 1.1.2 =
* Released: 2025-12-04
* docs: enhance README with detailed manual release instructions and update bump script to derive release notes from git history
* refactor: rename plugin from sokin-woocommerce-plugin to sokin-pay and update related configurations in docker-compose, README, and entrypoint script
* fix: resolved issues highlighted by the wp plugin-checker
* No functional behavior changes; maintenance-only release.

= 1.1.1 =
* Released: 2025-12-03
* Keep all internal WordPress metadata and plugin constants in sync with the tagged version.
* Update enqueued script and style version strings so they match the plugin version.
* Extend the bump script to update all versioned PHP locations automatically.
* No functional behavior changes; maintenance-only release.

= 1.1.0 =
* Released: 2025-12-02
* Updated billing and shipping address payloads to comply with Sokin's new address schema, including ISO numeric country codes.
* Improved shipping address handling to only send shipping details when they differ from the billing address, reducing API validation issues.

= 1.0.5 =
* Released: 2025-11-06
* Enhance SokinPay payment handling on thank you page.

= 1.0.4 =
* Released: 2025-14-23
* Fixed all PHPCS errors to meet WordPress coding standards.
* Applied QIT (Quality Improvement Tool) fixes to improve code quality and maintainability.
* Added extra validation checks to prevent warnings and fatal errors on the WooCommerce Thank You page after payment.

= 1.0.3 =
* Released: 2024-11-26
* Integrated Cart and Checkout Block-Support feature.
* Now, the plugin is compatible with both Classic WooCommerce shortcode checkout and Block checkout transformation.

= 1.0.2 =
* Released: 2024-11-13
* Changed default Order Status from 'Completed' to 'Processing' on success of Payment

= 1.0.1 =
* Released: 2024-09-24
* Checkout page redirection fixed

= 1.0.0 =
* Released: 2024-08-21
* Initial release
