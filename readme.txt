=== DYAMAR Polls ===
Contributors: dyamar
Tags: poll, vote, dyamar, feedback, rating, review, survey, democracy, opinion, voting, booth
Requires at least: 3.2
Tested up to: 4.3
Stable tag: 1.2.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

This plugin allows to add interactive polls to your WordPress web site. You can use shortcodes to place desired questions in a post or in any widget.

== Description ==

This is a plugin that allows to add interactive and dynamic AJAX polls to your WordPress web site. 

You can use generated shortcodes to place desired questions in a post or in any widget.

There are no any limitations and you can create as much polls or questions as you want.

In addition, it's possible to set lifetime of the answer. It means that users will not be able to answer several times and you will see better picture of users' opinions.

== Installation ==

1. Download plugin and unzip.
2. Upload 'dyamar-polls' folder to the '/wp-content/plugins/' directory.
3. Activate our plugin through the 'Plugins' menu in WordPress.
4. Go to the 'Polls' admin menu in the back-end area of your site.
5. Create new poll by clicking on the 'Add New Poll' button.

Then, there are such ways to add poll to your site: 

1. Shortcode

- Copy short-code from 'Polls' page and place it inside of any page or widget.

2. Widget

- Navigate to the 'Widgets' page and add DYAMAR Polls widget to desired sidebar.
- Select required poll from drop down list and save widget.

== Frequently Asked Questions ==

= How many polls can I create? =

There are no limitations to the number of polls that you can create in this plugin.

= Does your plugin use AJAX? =

Yes, we do, we use that to send answers to the web site and save it in MySQL database.

= How to insert plugin into page? =

Just copy short-code from the admin section of the Poll plugin and paste it into desired page or widget. Or optionally, you may add 'DYAMAR Polls' widget in the 'Widgets' area of WordPress installation. In this case, you will need to select poll from drop down item and save widget.

== Changelog ==

= 1.2.0 =
* Bug fix: All CSS classed and HTML ids must have dyamar_poll prefix to avoid conflicts with other code.
* Bug fix: duplicate HTML ids on administration side.
* Bug fix: Poll cookies must not belong to specific path, they should be site wide instead.
* Bug fix: HTML code for administration side contained unclosed tag.
* Compatibility with WordPress 4.2.4
* Minor improvements to CSS to make it easy to add custom CSS styles.

= 1.1.2 =
* WordPress widget that allows to select and add polls to sidebars.
* All internals text labels are displayed by using translation functions.

= 1.1.1 =
* Bug fix: JavaScript should correctly generate HTML code for the poll widget.
* Bug fix: Poll widget should be correctly positioned in case if it is used inside of post.

= 1.1.0 =
* Ability to set different color schemes for polls.
* Validating form data before saving into database.
* Included CSS files should have correctly specified media type.

= 1.0 =
* Initial release of this plugin.
