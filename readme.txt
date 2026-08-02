=== Custom Wordfence Block Page Manager ===
Contributors: sflwa
Tags: wordfence, block page, 503 template, custom lockout, security
Requires at least: 6.0
Tested up to: 6.7
Requires PHP: 8.0
Stable tag: 2.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Manage and brand Wordfence Security 503 block pages with a Visual Wizard or an Advanced PHP Editor.

== Description ==

Wordfence Security uses a hardcoded `wf503.php` file when locking out blocked visitors or malicious bots. Customizing this page traditionally requires editing core plugin files that are deleted during plugin updates.

**Custom Wordfence Block Page Manager** solves this issue by offering a visual management interface for your lockout templates. It automatically retains your custom design across Wordfence updates while enforcing critical recovery features.

### Features
* **Dual Architecture (Wizard vs. Advanced):** 
  * **Wizard Mode:** Simple controls to add logos, edit headings, consolidate lock reasons, and format custom rich-text instructions without code.
  * **Advanced Mode:** Embedded CodeMirror PHP/HTML editor for total layout customization.
* **Core Compliance & Safety:** Essential Wordfence tools (Admin Email Unlock Form, Tech Data, and Security Badge) are preserved by default in Wizard Mode to prevent accidental admin lockouts.
* **Styled UI Elements:** Automatically renders plain return text as a clean, styled call-to-action button.
* **Automatic Lifecycle Manager:** Hooks into `upgrader_process_complete` to automatically restore custom templates after Wordfence updates.
* **Automatic Backup & Restoration:** Backs up default files on initial setup and restores original files on plugin deactivation.

== Installation ==

1. Ensure **Wordfence Security** is installed and activated.
2. Upload `custom-wordfence-template` to `/wp-content/plugins/`.
3. Activate the plugin via **Plugins** menu.
4. Go to **Settings > Wordfence 503 Page** to customize your template.

== Frequently Asked Questions ==

= Why are Admin Box, Tech Data, and Badge locked in Wizard Mode? =
The Admin Unlock form prevents administrators from permanently locking themselves out. Enforcing these support tools in Wizard Mode ensures site owners retain recovery options without writing raw code. Advanced Mode remains available for users who want to remove these elements completely.

= Will I lose my layout when Wordfence updates? =
No. The plugin detects Wordfence updates via `upgrader_process_complete` and writes your configured template directly back into `wordfence/lib/wf503.php`.

== Changelog ==

= 2.3.0 =
* **Feature:** Added client-side Base64 encoding on form submit and server-side decoding in sanitization to bypass ModSecurity, Cloudflare, and host-level WAF 403 Forbidden errors.
* **Fix:** Improved CodeMirror instance retrieval during form submission.

= 2.2.0 =
* **Feature:** Implemented dual-target strategy writing simultaneously to `503.php` and `503-lockout.php` in Wordfence's `wf-waf` views directory.
* **Enhancement:** Added `503-lockout.original.php` backup logic to ensure zero data loss on deactivation.

= 2.1.0 =
* Implemented locked-core section strategy for Wizard Mode (enforcing Admin Box, Tech Data, and Security Badge).
* Upgraded Return Link to render as a styled CSS button.
* Consolidated Reason text into a simplified single-line field.
* Certified PHP 8.2 & WP 6.7 compatibility.

= 2.0.0 =
* Introduced Dual Architecture (Wizard vs Advanced Code Mirror).
* Added WP Media Uploader logo support and Rich Text Instructions editor.

= 1.0.0 =
* Initial release with baseline backup/restore hooks.
