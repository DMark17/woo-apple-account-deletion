# woo-apple-account-deletion

Self-service WooCommerce customer account deletion for WebView apps and **Apple App Store Guideline 5.1.1** compliance.

Works equally well on standard WooCommerce stores as a GDPR-friendly account deletion flow.

| | |
|---|---|
| **Plugin name** | `woo-apple-account-deletion` |
| **Main file** | `dm-account-deletion.php` |
| **Text domain** | `dm-account-deletion` |
| **Domain path** | `/languages` |
| **PHPDoc package** | `DMAccountDeletion` |
| **Repository** | [github.com/DMark17/woo-apple-account-deletion](https://github.com/DMark17/woo-apple-account-deletion) |

The WordPress admin plugin list shows **woo-apple-account-deletion**. The text domain (`dm-account-deletion`) matches the main plugin filename and the recommended install folder — use that folder name so translations load from `/languages` correctly.

---

## The Problem

Apple requires all iOS apps that allow users to create accounts to also provide a way to **delete those accounts from within the app**. If you've wrapped WooCommerce in a native iOS WebView, your app **will be rejected at App Review** without this.

WooCommerce has no built-in account deletion. This plugin adds a complete, self-service deletion flow to the My Account area — no support ticket required.

---

## Features

- **Delete Account endpoint** added to WooCommerce My Account (`/my-account/delete-account/`)
- **Two deletion modes**: anonymise customer data (default, GDPR-friendly) or hard delete the WordPress user
- **Order preservation** — orders are detached from the customer account via WooCommerce APIs, not deleted, so your records stay intact for accounting and legal purposes
- **Delayed deletion** — optionally schedule deletion after a grace period (configurable number of days) instead of acting immediately
- **Built-in success screen** — a clean standalone page shown after deletion, designed to work correctly inside a WKWebView without redirect issues
- **Nonce-protected form** with explicit confirmation checkbox
- **Immediate session destruction** — WooCommerce session, cart, and auth cookies are all cleared on deletion
- **Admin email notifications** on deletion request and completion
- **HPOS compatible** — declares HPOS compatibility and uses WooCommerce order APIs throughout
- **Full settings page** under WooCommerce → Account Deletion
- **Developer-friendly** — 9 filters and 8 action hooks for customisation
- Translation-ready

---

## Requirements

- WordPress 6.4+
- WooCommerce 8.0+
- PHP 8.2+

---

## Installation

**Via WordPress admin:**

1. Download the latest `.zip` from the [Releases](https://github.com/DMark17/woo-apple-account-deletion/releases) page
2. Go to **Plugins → Add New → Upload Plugin**
3. Upload the zip and activate

**Via Git:**

```bash
cd wp-content/plugins
git clone https://github.com/DMark17/woo-apple-account-deletion.git dm-account-deletion
cd dm-account-deletion
```

Install into a folder named **`dm-account-deletion`** so the directory slug matches the text domain (`dm-account-deletion`) and translation files in `/languages` resolve correctly. The plugin appears in admin as **woo-apple-account-deletion**.

Then activate **woo-apple-account-deletion** under **Plugins** in your WordPress admin.

After activation, go to **WooCommerce → Account Deletion** to configure.

> **Important:** After activation, visit **Settings → Permalinks** and click Save to flush rewrite rules and register the new endpoint.

---

## Apple App Review Compliance

This plugin satisfies **App Store Review Guideline 5.1.1**, which requires apps that support account creation to also offer in-app account deletion.

The deletion flow Apple's reviewers check for:

1. Customer opens My Account in the WebView
2. Selects **Delete Account** from the menu
3. Reads a clear warning that the action is permanent
4. Checks a confirmation checkbox
5. Submits — no email or phone support required
6. Sees a success screen confirming deletion

The built-in success screen renders as a standalone page rather than a WooCommerce template redirect, which avoids common WebView session/redirect issues during App Review.

> **Tip:** Use **Anonymise** mode (the default) when demoing during App Review or TestFlight testing, unless your legal process requires delayed deletion.

---

## Settings

All settings are under **WooCommerce → Account Deletion**.

| Setting | Default | Description |
|---|---|---|
| Enable plugin | Yes | Show/hide the Delete Account flow |
| Menu item label | Delete Account | Customise the My Account menu label |
| Warning text | *(default)* | The warning shown on the deletion page |
| Success message | *(default)* | Message shown on the success screen |
| Deletion mode | Anonymise only | Anonymise customer data or hard delete the WP user |
| Redirect URL | *(blank)* | Optional redirect after deletion — leave blank to use the built-in success screen |
| Delayed deletion | Off | Schedule deletion after a grace period instead of acting immediately |
| Delay days | 7 | Number of days before a pending deletion is processed |
| Admin notification | Yes | Email the site admin when a deletion is requested or completed |

---

## Deletion Modes

**Anonymise only (default)**
Removes all personal data from the customer profile (name, email, address, billing/shipping fields, WooCommerce API keys) and replaces with anonymised placeholders. Orders are preserved and detached from the account. Recommended for most stores.

**Hard delete**
Orders are detached first, then the WordPress user record is deleted via `wp_delete_user()`. Use only if your legal or compliance requirements call for full user record removal.

---

## Internationalisation

Strings use the text domain **`dm-account-deletion`**. Translation files (`.po` / `.mo`) belong in:

```
/languages
```

That path is declared in the plugin header as **Domain Path: `/languages`** and loaded via `load_plugin_textdomain()`.

For translators: generate a `.pot` from the `dm-account-deletion` text domain; do not use the GitHub repo slug (`woo-apple-account-deletion`) as the text domain unless you fork and rename it consistently across all source files.

---

## Developer Hooks

Hook and filter prefixes use `dm_account_deletion_`. PHPDoc **`@package DMAccountDeletion`** is used across all plugin PHP files.

**Filters**

| Filter | Description |
|---|---|
| `dm_account_deletion_default_settings` | Modify default settings array |
| `dm_account_deletion_menu_label` | Override the My Account menu label |
| `dm_account_deletion_warning_text` | Override the warning text |
| `dm_account_deletion_success_message` | Override the success message |
| `dm_account_deletion_redirect_url` | Override the post-deletion redirect URL |
| `dm_account_deletion_mode` | Override deletion mode per user (`anonymise` or `hard_delete`) |
| `dm_account_deletion_allow_admin_self_delete` | Allow admin accounts to self-delete (default: false) |
| `dm_account_deletion_anonymised_roles` | Assign roles to the anonymised user record |
| `dm_account_deletion_meta_keys_to_delete` | Extend or reduce the list of user meta keys erased during anonymisation |

**Actions**

| Action | Description |
|---|---|
| `dm_account_deletion_before_request` | Fires before the deletion request is processed |
| `dm_account_deletion_before_delete` | Fires immediately before deletion or anonymisation |
| `dm_account_deletion_after_anonymise` | Fires after anonymisation is complete |
| `dm_account_deletion_after_delete` | Fires after deletion is complete |
| `dm_account_deletion_scheduled` | Fires when a delayed deletion is scheduled |
| `dm_account_deletion_scheduled_failed` | Fires if a scheduled deletion fails |
| `dm_account_deletion_before_logout` | Fires before the session is destroyed |
| `dm_account_deletion_after_logout` | Fires after the session is destroyed |

---

## Frequently Asked Questions

**Does this delete customer orders?**
No. Orders are preserved by default. The plugin detaches orders from the customer account so records remain available for accounting and legal requirements.

**Is this GDPR compliant?**
The anonymise mode is designed with GDPR right-to-erasure principles in mind — it removes personal identifiers while retaining the minimum order data required for legitimate business purposes. However, GDPR compliance depends on your specific store configuration and legal obligations. Review with your legal adviser.

**Does this work with WooCommerce HPOS?**
Yes. The plugin declares HPOS compatibility and uses WooCommerce order APIs (`wc_get_orders`) rather than direct database queries.

**Will it work inside a WKWebView?**
Yes — that's what it was built for. The success screen is rendered as a standalone page to avoid the redirect and session issues common with WebView-wrapped WooCommerce flows.

**Does it work with WooCommerce Subscriptions?**
The plugin will process the deletion request. It does not automatically cancel active subscriptions first — you may want to handle that via the `dm_account_deletion_before_request` action hook.

---

## Changelog

**1.0.0**
- Initial release

---

## Contributing

Pull requests are welcome. For major changes please open an issue first.

---

## Licence

[GPL-2.0](https://www.gnu.org/licenses/gpl-2.0.html) — consistent with WordPress and WooCommerce licensing.

---

## Author

Built by [Mark Watkiss](https://www.digitalmarkonline.co.uk) — digital consultant specialising in SEO/GEO and full-stack development.
