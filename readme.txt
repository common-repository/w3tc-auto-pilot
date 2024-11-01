=== W3TC Auto Pilot ===
Contributors: Cybr
Tags: cache, control, w3, total, automatic, flush, update, multisite, domain, mapping, hide, comments, notifications, errors
Requires at least: 3.6.0
Tested up to: 4.5.0
Stable tag: 1.1.7.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Put W3 Total Cache on auto pilot. This plugin allows you to control W3 Total Cache by simply using your website. So your cache is always up to date.

== Description ==

= This plugin puts your W3 Total Cache configuration on auto pilot. =

It's great when you have users that don't have access to W3 Total Cache control but still need to purge the cache.

It's also brilliant when you have created a blog for a customer, this way they won't even know it's there: All cache is purged automatically.

It's absolutely great on MultiSite installations, especially when you allow untrusted users to create a blog.

**What this plugin does:**

***On the front end:***

* No more W3 Total Cache comments in the HTML output

***Behind the screens:***

*Purge cache, whenever:*

* a post is updated.
* theme is changed.
* a widget is updated or changed from position.
* a sidebar is updated.
* the theme is edited in Customizer.
* the nav menu is updated.

***MultiSite, if not Super-Admin:***

*No more:*

* purge from cache button on pages and posts edit screens.
* admin menu on the admin bar.
* admin menu in the dashboard.
* amin notices in the dashboard after settings change or on error.
* admin scripts in back end.
* admin scripts on front end.

***WPMUdev Domain Mapping support***

* This plugin fixes a few issues when you're combining W3TC and Domain Mapping by automatically flushing the posts on a site. This eliminates confusion.

== Installation ==

1. Install Advanced W3TC either via the WordPress.org plugin directory, or by uploading the files to your server.
1. Activate this plugin either through Network Activation or per site.
1. That's it! There are currently no options available.

== Changelog ==

= 1.1.7.1 =
* Fixed: Non-admins were treated as admins, this caused default W3TC behaviour for menus.
* Fixed: WordPress.org translations should now be activated.

= 1.1.7 =
* Added: This plugin is now translatable through WordPress.org.
* Added: POT Translation file.
* Improved: Massively improved the already neglegible plugin load time by adding PHP caches and removing redundant calls.
* Fixed: W3TC Error and notice warnings when they're trying to be output. Thanks @pcfreak30 :)!

= 1.1.6 =
* Added: Now also flushes posts without a title set on global actions (nav/theme/customizer/widget change).
* Confirmed: WordPress 4.4.0 support.
* Cleaned up code.

= 1.1.5 =
* Improved: WPMUdev Domain Mapping flush, it now uses the new filters
* Fixed: The maximum flush is now active, it was left deactivated in 1.1.4 by accident for debugging purposes.

= 1.1.4 =
* Improved plugin speed, read more below.
* Added: Maximum flush of pages is set to the 20 most recent posts and including the front page.
* Added: New Filters, see "Other Notes" for usage.
* Improved: Theme switch cache flush.
* Fixed: Widgets are now being saved correctly on AJAX requests.
* Fixed: Widgets now correctly fire a flush.
* Update: This plugin is now written in a Class Object.
* Optimized and cleaned up code.
* Developers notice: Because of the new class structure, I took the liberty to rename all functions aptly to what they do. None of the old functions contain a deprecation notice and have been removed. If you don't use the habit of 'function_exists' when extending plugins your site may crash on a fatal error. Then again, I don't expect anyone to have extended this plugin since it only calls functions directly from W3 Total Cache based on other actions. It's also considered bad practice when extending plugins without using function_exists and/or is_plugin_active()

= 1.1.3 =
* Added a flush on Customizer Ajax save.
* Fixed theme switch flush. This switch will be visible after the second load (best I could do, for now).

= 1.1.2 =
* Fixed PHP Warnings when W3TC is deactivated
* Fixed internationalisation caused by mistake in 1.1.1

= 1.1.1 =
* Made W3TC completely silent by removing the latest scripts from non-admins (single) / non-super-admins (multi) in wp-admin
* Tested on PHP7

= 1.1.0 =
* Added flush on Theme Menu change
* Added textdomain WapPilot for translating
* Added redirect with notice if an unauthorized user tries to access the W3TC dashboard or any other w3tc page.
* Cleaned up code and made it more readable for other programmers

= 1.0.6 =
* Fixed a bug with Domain Mapping. Make sure Administrative Mapping is set to "Either" or "Mapped Domain".

= 1.0.5 =
* Made sure the admin bar was removed. It's only removed if you're not admin (single) or super-admin (multisite)

= 1.0.4 =
* Removed popup admin script if user isn't allowed to control W3TC

= 1.0.3 =
* Fine tuned the purging of page cache to only when a domain is actually mapped.

= 1.0.2 =
* Added forced page cache purging on each post save when Domain Mapping (by WPMUdev) is active. This will fix a bug with Domain Mapping.

= 1.0.1 =
* Removed admin notices and errors for non-super-admins (MultiSite) / non-admins (single)

= 1.0.0 =
* Initial Release

== Other Notes ==

This plugin allows you to adjust the output of a few filters. However, the defaults should work out for everyone.

== Filters ==

***Add a greater amount of pages and posts to be flushed on several actions***
`add_filter( 'wap_limit_flush', 'my_wap_limit_flush' )
function my_wap_limit_flush() {
    $limit = 50; // Default is 20.

    return $limit;
}`

***Flush everything, ignoring the limit***
`add_filter( 'wap_flush_all', '__return_true' );`
