=== Dead Dove ===
Contributors: c4osl
Donate link: https://c4osl.org/support-us/
Tags: content warning, sensitive content, blur, shortcode, buddyboss
Requires at least: 5.3
Tested up to: 7.1
Stable tag: 2.4.0
Requires PHP: 7.2
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Extend the WordPress tagging system to provide content warnings. Selected content will be blurred until the user reads and agrees to a disclaimer.

== Description ==

The **Dead Dove** plugin lets administrators and users apply content warnings to content. Administrators define the available warnings as terms in a custom taxonomy and choose which of them trigger a warning by default. Users can override those defaults in their settings.

Warnings can be applied to a whole post, to a block, to selected text, and to a post's featured image. From version 2.0, they can also be applied when posting to BuddyBoss activity feeds and forums, even by users who can't reach the WordPress dashboard.

Content tagged with a term that triggers a warning for the viewer is blurred. To view it, the viewer reads and accepts the disclaimer, which is the description of the term.

= Features =

* Blur content based on assigned terms and show the warning text before viewing.
* Choose which terms trigger warnings, with the warning text taken from the term descriptions.
* Let users set warning preferences that override the admin settings.
* Apply warnings to a post, a block, selected text, or a featured image, or when posting to a BuddyBoss activity feed or forum.
* Show every term description when more than one warning term applies.
* Set the amount of blur and the overlay text shown on blurred content.

== Installation ==

1. Download the plugin as a `.zip` file or install it directly from the WordPress plugin repository.
2. Go to **Plugins > Add New** and click **Upload Plugin** (if using the `.zip`).
3. After installation, click **Activate Plugin**.
4. Configure warning terms by navigating to **Settings > Content Warning**.
== Usage ==

= Admin settings =

1. Go to **Settings > Content Warning**.
2. Select the terms that require a content warning.
3. Add a description to each term to provide the warning text.

= User settings =

1. Users who can reach the WordPress dashboard can go to **Your Profile** to adjust their warning settings.
2. BuddyBoss users can go to **Account Settings > Content Warning Settings**.
3. Users can turn off warnings for terms the admin selected and turn on warnings for terms the admin didn't.

Selections are stored in the `deaddove_user_warning_terms` user meta key.

= Post terms =

To warn about a whole post, apply a term that requires a warning to it. The Content Warning taxonomy appears in the post editor next to tags and is used the same way.

= Block usage =

1. In the block editor, add the Content Warning block.
2. Select warning terms in the block settings.
3. Add your content inside the block. It stays blurred until the viewer agrees to view it.

= Editor toolbar =

1. In the block editor, select the text you want to warn about.
2. Open the **More** menu in the formatting toolbar and choose **Content warning**.
3. Pick one or more terms and click **Apply**. The selection is wrapped in the `[content_warning]` shortcode.

To take a warning off, select the wrapped text, open the same menu item, and click **Remove**.

= Featured image =

In the post editor, open the **Content warning** panel in the document sidebar and check **Content warning on featured image** to blur the featured image on the single post page.

= Shortcode usage =

Use the `[content_warning]` shortcode to apply warnings within a block. Enter the slug of each term in `tags`, separated by commas.

**Single term**

    [content_warning tags="sensitive"]
    This section discusses sensitive material.
    [/content_warning]

**Multiple terms**

    [content_warning tags="graphic,offensive"]
    This section contains graphic language and offensive themes.
    [/content_warning]

= BuddyBoss usage =

When adding content to the activity feed or a forum post, the available content warnings are shown in a drop-down in the editing box.

== Frequently Asked Questions ==

= What happens if multiple terms apply to a post? =

All applicable term descriptions are combined and displayed as warnings.

= Can users turn off warnings for certain terms? =

Yes. Users can turn off warnings for terms the admin selected and turn on warnings for other terms from their profile or, in BuddyBoss, from Content Warning Settings.

= Can I mix block and shortcode warnings on the same page or post? =

Yes.

= Do I need BuddyBoss? =

No. BuddyBoss is only needed for warnings on activity feeds and forums.

== Screenshots ==

1. Example of a content warning
2. Block settings
3. Admin settings
4. User settings (dashboard user)
5. User settings (BuddyBoss user)
6. BuddyBoss activity screen

== Changelog ==

= 2.4 =
* Added a block editor toolbar button that wraps selected text in a content warning shortcode.
* Added a document sidebar panel for blurring a post's featured image.
* Removed the unused featured image meta box code.
* Removed the unused custom user widget.
* Used the BuddyPress 12 profile URL function when it exists.

= 2.3.3 =
* Added permission checks to the AJAX endpoints, and activity warnings now respect BuddyBoss privacy settings.
* Escaped the shortcode tags attribute.
* Fixed errors on sites that don't have BuddyPress active.
* Fixed the Content Warning block registration.
* Handled unexpected AJAX responses in the front-end script.
* Removed unused code and debug logging.

= 2.3.2 =
* Added the overlay text to blurred featured images and blocks.
* Moved the overlay text to the top when the whole content is blurred.
* Hid the overlay text once the content is unblurred.

= 2.3 =
* Added setting for text overlay on top of the blurred content.

= 2.2 =
* Added admin setting to configure the amount of blur.

= 2.1 =
* Simplified code to ensure user settings stored in only one place.
* Improved visual display of blurred content in-line in a text block.

= 2.0 =
* Supports content warnings in BuddyBoss Activity and Forum posts.
* New BuddyBoss Content Warning Settings screen.
* Content is now also blurred on category pages.

= 1.1 =
* Moved from using post tags to a custom taxonomy for identifying terms that receive a warning.

= 1.0 =
* Initial release.
* Admin and user tag-based warning configurations.
* Support for multiple tag descriptions in warnings.
* Support for post, block, and shortcode-level warnings.

== Roadmap ==

* **Geolocation-based warnings:** Modify content visibility based on the viewer's location.
* **Custom styling options:** Provide options to style blurred content and buttons using CSS.

== License ==

This plugin is licensed under the GPLv2 or later. See the [license](https://www.gnu.org/licenses/gpl-2.0.html) for more details.
