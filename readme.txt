=== Dead Dove ===  
Contributors: c4osl
Donate link: https://c4osl.org/support-us/
Tags: content warning, sensitive content, shortcode, tags  
Requires at least: 5.0  
Tested up to: 6.8
Stable tag: 2.0
Requires PHP: 7.2  
License: GPLv2 or later  
License URI: https://www.gnu.org/licenses/gpl-2.0.html  

Extend the WordPress tagging system to provide content warnings. Selected content will be blurred until the user reads and agrees to a disclaimer.

== Description ==

The **Dead Dove** plugin lets administrators and users apply content warnings to their content. Administrators define the available content warnings in a custom taxonomy of terms, and specify which of them trigger warnings by default. Users can overide these defaults in their user settings. Tags may be applied at the post level, block level, or even within a block using a shortcode. From version 2.0, they can also be applied when posting to BuddyBoss activity feeds and forums, even by users who lack access to the Wordpress dashboard.

Content that has been tagged with a term that triggers a content warning for the user viewing it will be blurred from view. To view the content the user must read and accept a disclaimer that has been defined by the administrator in the description of the taxonomy term.

### **Features**  
- Blur content based on assigned terms and display warning text before viewing.  
- Administrators select which terms require warnings, with the warning text pulled from term descriptions.  
- Users can override admin settings by choosing their own tag warning preferences.  
- Warning can be applied at the post or block level, using a shortcode with terms as parameters, or when posting to a Buddyboss activity feed or forum.
- Multiple term descriptions are shown if more than one warning term is applied.  

== Installation ==

1. Download the plugin as a `.zip` file or install it directly from the WordPress plugin repository.
2. Go to **Plugins > Add New** and click **Upload Plugin** (if using the `.zip`).
3. After installation, click **Activate Plugin**.
4. Configure warning terms by navigating to **Settings > Content Warning**.
5. If using the BuddyBoss theme, create a child theme (if not already created) and add the **assets**, **buddypress**, and **languages** folders, as well as the contents of the **style.css** and **functions.php** files, to the child theme folder.

== Usage ==

### **Admin Settings**  
1. Go to **Settings > Content Warning**.  
2. Select the terms that require a content warning.  
3. Add a description to each term to provide the warning text.

### **User Settings**  
1. Users who have access to the Wordpress Dashboard can go to **Your Profile** to adjust their warning settings.
2. BuddyBoss users can adjust their warning settings by going to **Account Settings**, **Content Warning Settings**.
3. Users can disable warnings for certain terms set by the admin or enable warnings for terms that were not set by the admin.
4. User selections are stored in the `deaddove_user_warning_terms` user meta key.

### **Post term usage**
To apply a content warning to an entire post, apply a term that requires a content warning to the post. The content warning taxonomy will appear in the post editor screen, alongside tags, and are used in the same way.

### **Block Usage**
1. In the block editor, add the Content Warning block.
2. Select warning terms in the block settings.
3. Add your content inside the Content Warning block, which will be blurred until the user agrees to view it.

### **Shortcode Usage**  
Use the `[content_warning]` shortcode to apply warnings within a block. The slug of the term or terms should be entered into the shortcode separated by commas.  

**Example 1:** Single term  

`[content_warning tags="sensitive"]
This section discusses sensitive material.
[/content_warning]`

**Example 2:** Multiple terms  

`[content_warning tags="graphic,offensive"]
This section contains graphic language and offensive themes.
[/content_warning]`

### **BuddyBoss Usage**
When adding content to the Activity Feed or Forum post, the available content warnings are shown in a drop-down in the editing box.

== Frequently Asked Questions ==

**Q: What happens if multiple terms apply to a post?**  
A: All applicable tag descriptions are concatenated and displayed as warnings.  

**Q: Can users disable warnings for certain terms?**  
A: Yes, users can override the admin’s settings through their profile. They can disable certain warnings or add new tags that they want to be warned about.

**Q: Can you mix block and shortcode warnings on the same page or post?**  
A: Yes, you can.

== Screenshots ==

1. [Example of content warning]![screenshot-1](assets/screenshot-1.png)
2. [Block settings]![screenshot-2](assets/screenshot-2.png)
3. [Admin settings]![screenshot-3](assets/screenshot-3.png)
4. [User settings (Dashboard user)]![screenshot-4](assets/screenshot-4.png) 
5. [User settings (BuddyBoss user)]![screenshot-5](assets/screenshot-5.png) 
6. [BuddyBoss Activity screen]![screenshot-6](assets/screenshot-6.png) 

== Changelog ==

### Version 2.0
- Supports content warnings in BuddyBoss Activity and Forum posts.
- New BuddyBoss Content Warning Settings screen.
- Content is now also blurred on category pages.

### Version 1.1
- Moved from using post tags to a custom taxonomy for identifying terms that receive a warning.

### Version 1.0  
- Initial release.  
- Admin and user tag-based warning configurations.  
- Support for multiple tag descriptions in warnings.  
- Support for post, block, and shortcode-level warnings.

== Roadmap ==

- **Geolocation-based Warnings**: Modify content visibility based on the viewer’s location.  
- **Custom Styling Options**: Provide options to style blurred content and buttons using CSS.  
- **Apply Shortcode from Editing Toolbar**: Simpler application of content warnings to text selections.

== License ==

This plugin is licensed under the GPLv2 or later. See the [license](https://www.gnu.org/licenses/gpl-2.0.html) for more details.
