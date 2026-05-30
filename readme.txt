=== DM Account Deletion ===
Contributors: dm
Tags: woocommerce, account deletion, apple app store, gdpr, privacy
Requires at least: 6.4
Tested up to: 6.8
Requires PHP: 8.2
Stable tag: 1.0.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Self-service WooCommerce customer account deletion for iOS/Android WebView apps and Apple App Store account deletion compliance.

== Description ==

DM Account Deletion adds a dedicated Delete Account endpoint to WooCommerce My Account at:

/my-account/delete-account/

The flow lets logged-in customers initiate account deletion without contacting support. It is designed for WooCommerce stores wrapped in iOS or Android WebView apps, while remaining useful on normal WooCommerce websites.

The default deletion mode anonymises customer data and preserves WooCommerce orders where appropriate for legal, tax, fraud-prevention, and accounting needs.

Features include:

* WooCommerce My Account endpoint and menu item.
* Confirmation checkbox and nonce-protected form.
* Immediate logout and session destruction after deletion.
* Built-in success screen.
* WooCommerce admin settings page.
* Anonymise-only and hard-delete modes.
* Optional delayed deletion.
* Optional admin email notification.
* HPOS-compatible order handling through WooCommerce APIs.
* Translation-ready strings.
* Developer actions and filters.

== Installation ==

1. Upload the `dm-account-deletion` folder to `/wp-content/plugins/`.
2. Activate the plugin in WordPress.
3. Go to WooCommerce > Account Deletion.
4. Confirm the plugin is enabled and choose your deletion mode.
5. Visit My Account > Delete Account as a customer.

== Apple App Store Compliance Notes ==

Apple requires apps that support account creation to also offer in-app account deletion. For WooCommerce sites wrapped in WebView apps, this plugin provides a fully self-service customer deletion path inside the My Account area.

The user can:

* Open My Account.
* Select Delete Account.
* Read a clear permanent-deletion warning.
* Confirm the irreversible action.
* Submit the deletion request without email or phone support.
* See a success message after completion.

Use immediate anonymisation mode during App Review or TestFlight demonstrations unless your legal process requires delayed deletion.

== Frequently Asked Questions ==

= Does this delete WooCommerce orders? =

No. Orders are preserved by default. The plugin detaches orders from the customer account using WooCommerce APIs so order records can remain available for accounting and legal requirements.

= What is the safest deletion mode? =

Anonymise only is the safest default for most WooCommerce stores because it removes customer profile data while preserving order records.

= Can I hard delete users? =

Yes. Enable hard delete in WooCommerce > Account Deletion. Orders are detached first, then WordPress user deletion APIs are used.

= Does this support HPOS? =

Yes. The plugin declares HPOS compatibility and uses WooCommerce order APIs rather than direct order table SQL.

= Does this replace legal advice? =

No. Store owners should review their own legal, tax, privacy, and accounting obligations before changing deletion settings.

== Developer Hooks ==

Filters:

* `dm_account_deletion_default_settings`
* `dm_account_deletion_menu_label`
* `dm_account_deletion_warning_text`
* `dm_account_deletion_success_message`
* `dm_account_deletion_redirect_url`
* `dm_account_deletion_mode`
* `dm_account_deletion_allow_admin_self_delete`
* `dm_account_deletion_anonymised_roles`
* `dm_account_deletion_meta_keys_to_delete`

Actions:

* `dm_account_deletion_before_request`
* `dm_account_deletion_before_delete`
* `dm_account_deletion_after_anonymise`
* `dm_account_deletion_after_delete`
* `dm_account_deletion_scheduled`
* `dm_account_deletion_before_logout`
* `dm_account_deletion_after_logout`

== Changelog ==

= 1.0.0 =

* Initial release.

== Upgrade Notice ==

= 1.0.0 =

Initial production-ready release.
