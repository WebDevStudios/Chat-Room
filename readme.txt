=== Plugin Name ===
Contributors: ericlewis, williamsba1, webdevstudios
Tags: chat room
Requires at least: 3.3
Tested up to: 4.7
Stable tag: 0.2
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

Create chat rooms on your site for users to participate in.

== Description ==

The Chat Room plugin allows administrators to easily create chat rooms for their users to participate in. Chat rooms are created via the WordPress administrative interface. After creation, users can access it via the permalink for the chat room.

The plugin currently will only support servers that have direct PHP filesystem access, which may not be available on all hosting environments.

[Pluginize](https://pluginize.com/?utm_source=chat-room&utm_medium=text&utm_campaign=wporg) was launched in 2016 by [WebDevStudios](https://webdevstudios.com/) to promote, support, and house all of their [WordPress products](https://pluginize.com/shop/?utm_source=chat-room&utm_medium=text&utm_campaign=wporg). Pluginize is not only creating new products for WordPress all the time, but also provides [ongoing support and development for WordPress community favorites like CPTUI](https://wordpress.org/plugins/custom-post-type-ui/), [CMB2](https://wordpress.org/plugins/cmb2/), and more.

== Installation ==

1. Upload `chat-room` folder to the `/wp-content/plugins/` directory
1. Activate the plugin through the 'Plugins' menu in WordPress

== Screenshots ==

1. An example of a chat room in use within the Twenty Eleven theme.
2. The chat rooms are easily modifiable in the admin interface.
3. Chat rooms only require a title.

== Changelog ==

= 0.2 =
* Add ability to toggle if user should be logged in or not to use chat.
* Fix issue with log paths.
* Add some more styles to make the chat input look better.
* Hooks for other developers to utilize.
* Some usability notes.
* Prevent sending empty messages to chat
* Fix conflict with Yoast SEO and excerpts
* Add support for clicking user name and having it added to textarea

= 0.1.2 =
* Fix auto-scroll of the div down to new messages for Windows FF.

= 0.1.1 =
* Fix bug that hid display of contents of non-chat room post types.

= 0.1 =
* Initial release
