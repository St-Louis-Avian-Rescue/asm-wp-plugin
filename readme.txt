=== ASM WP Plugin - Adoptable Animals from Animal Shelter Manager ===
Contributors: jacobsabella
Tags: animal shelter, adoption, rescue, pets, sheltermanager
Requires at least: 6.3
Tested up to: 7.1
Requires PHP: 7.4
Stable tag: 1.0.1
License: MIT
License URI: https://opensource.org/licenses/MIT

Show the adoptable animals from your Animal Shelter Manager (sheltermanager.com) account, with photos, details, sharing, search and filters.

== Description ==

ASM WP Plugin shows the animals your shelter or rescue has up for adoption, straight from Animal Shelter Manager (ASM). It works with sheltermanager.com accounts and with self-hosted ASM3 servers.

* A grid of cards, one per animal, that fits your theme's fonts and colours.
* A details dialog with a photo carousel, breed, sex, age, special needs, bonded companions with a combined fee, and the description.
* An apply button that links to your own application form, with the animal's code filled in.
* Share buttons, and a link for every animal that opens their details directly.
* An optional search and filter bar: search by name or breed, filter by species, sex, size and special needs, and sort by name, arrival or age. The filtered view can be shared as a link.
* A block and a shortcode: `[asm_adoptable]`.
* Fast and resilient: animals are cached, and the last good copy keeps showing if ASM cannot be reached.
* Works with full-page caches and CDNs: the listing corrects itself in the browser when the cached page is out of date.
* Themeable with CSS custom properties and template overrides.
* Works without JavaScript, and is keyboard and screen-reader friendly.

= External service =

This plugin connects to the Animal Shelter Manager service that you configure, to fetch your adoptable animals and to show their photos.

* On your server, it requests the list of adoptable animals from the service URL in the settings (by default `https://service.sheltermanager.com/asmservice`), sending the account, username and password you entered. This happens when the cached copy has expired, at most once per refresh period.
* In your visitors' browsers, animal photos are loaded directly from the same service. As with any image, the service receives the visitor's IP address and browser details.

If you use sheltermanager.com, its terms and privacy policy apply; see [sheltermanager.com](https://sheltermanager.com). If you run your own ASM server, your own terms apply.

= Privacy =

Only public animal details are stored and shown: name, breed, species, shelter code, description, sex, size, special-needs flag, date of birth, arrival date, fee, bonded companions and photo count. Everything else ASM sends is discarded. The plugin sets no cookies and collects nothing about visitors.

== Installation ==

1. Upload the plugin and activate it.
2. Go to Settings > ASM WP Plugin.
3. Enter your sheltermanager.com account number, and the username and password of an ASM user. Create a user just for the website, with permission to view animals only.
4. Click Test connection.
5. Add the "Adoptable animals" block, or the `[asm_adoptable]` shortcode, to a page.

== Frequently Asked Questions ==

= Does it work with a self-hosted ASM server? =

Yes. Set the service URL to the address of your server's `/service` page and leave the account empty.

= How do I turn on search and filters? =

Tick "Search and filter bar" in the settings to turn it on everywhere, or use `[asm_adoptable filters="yes"]` for one listing. `filters="search,sort"` shows only those controls.

= What shortcode attributes are there? =

`species="Dog,Cat"`, `sort="name|newest|youngest|oldest"`, `limit="12"`, `columns="4"` and `filters="yes|no|search,species,sex,size,special,sort"`.

= How do I hide the adoption fee? =

Untick "Adoption fee" under "Details to show" in the settings.

= An adopted animal is still showing. =

The animals are refreshed every 15 minutes by default; change "Refresh every" or click Clear cache. If your site uses a full-page cache or CDN, make sure it does not cache `/wp-json/asm-wp-plugin/*`, which the listing uses to keep itself up to date.

= How do I change how it looks? =

Set CSS custom properties such as `--asm-wp-plugin-accent` on `.asm-wp-plugin`, or copy a file from the plugin's `templates` folder into an `asm-wp-plugin` folder in your theme and edit it. The plugin's README lists every property and template.

= Can I keep the password out of the database? =

Yes. Define `ASM_WP_PLUGIN_USERNAME` and `ASM_WP_PLUGIN_PASSWORD` (and optionally `ASM_WP_PLUGIN_ACCOUNT` and `ASM_WP_PLUGIN_SERVICE_URL`) in `wp-config.php`.

== Screenshots ==

1. The grid of adoptable animals.
2. An animal's details, with photos, facts, an apply button and share links.
3. The optional search and filter bar.
4. The same listing restyled by a theme.
5. The settings page.
6. The block in the editor.
7. An animal's details on a phone.

== Changelog ==

= 1.0.1 =
* A card no longer stays enlarged after its details are closed with the mouse.
* The details dialog keeps its own line spacing, companion link style and muted breed colour under themes that style every paragraph or link.
* The breed line on cards is smaller and lighter.

= 1.0.0 =
* First release.
