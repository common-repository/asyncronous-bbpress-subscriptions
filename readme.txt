=== AsynCRONous bbPress Subscriptions ===
Contributors: mechter
Donate link: http://www.markusechterhoff.com/donation/
Tags: bbpress, email, notifications, subscription, cron, wp cron, asynchronous
Requires at least: 3.6
Tested up to: 5.4
Stable tag: 3.7
License: GPLv3 or later
License URI: http://www.gnu.org/licenses/gpl-3.0.html

Email notifications done right. No BCC lists, no added page load time, better performance.

== Description ==

Per default, bbPress is sending subscription notification emails as one email with a bunch of BCCs. There are various reasons why it would make more sense to send individual emails. This plugin does that, quietly in the background via WP cron, without slowing down page load times. Also increases notification performance and reduces database load on large sites.

Translations by @mauriciogarofalo and @mechter

= Defaults =

If you don't customize this plugin, this is what you'll get:

* Sends mails from `"MyBlog <admin@MyBlog.foo>"` (with your Blog's name and admin email)
* Sends mail to `"Markus <markus@example.com>"` (with the name being the user's display name on the forums, not their username)
* Subject and Message have more user friendly defaults, use the available filters (see below) to make them your own.

= Customization =

You can install and activate this plugin and it just works, but you have full control over the details if you want to. Below are some filters and code snippets that help you do what you want. If you're new to working directly with code, please see the example at the bottom of this page.

= Available filters =

	bbp_subscription_email_from( $from ) // $from can be a string or array('name'=>string, 'address'=>string)
	bbp_subscription_email_recipients( $recipients ) // $recipients is array of array('name'=>string, 'address'=>string)
	bbp_subscription_email_headers( $headers )
	bbp_forum_subscription_email_subject( $subject, $forum_id, $topic_id )
	bbp_forum_subscription_email_message( $message, $forum_id, $topic_id )
	bbp_topic_subscription_email_subject( $subject, $forum_id, $topic_id, $reply_id )
	bbp_topic_subscription_email_message( $message, $forum_id, $topic_id, $reply_id )

	bbp_bounce_address( $bounce_address )

	bbp_subscription_disable_async( false )
	bbp_forum_subscription_disable_async( false )
	bbp_topic_subscription_disable_async( false )
	bbp_forum_subscription_notify_author( false )
	bbp_topic_subscription_notify_author( false )

= Helpful Snippets =

Here are some pointers to get the data you might want in your notifications:

	$blog_name = get_bloginfo( 'name' );

	$forum_title = bbp_get_forum_title( $forum_id );

	$topic_author_user_id = bbp_get_topic_author_id( $topic_id );
	$topic_author_display_name = bbp_get_topic_author_display_name( $topic_id );
	$topic_title = wp_specialchars_decode( strip_tags( bbp_get_topic_title( $topic_id ) ), ENT_QUOTES );
	$topic_content = wp_specialchars_decode( strip_tags( bbp_get_topic_content( $topic_id ) ), ENT_QUOTES );
	$topic_url = get_permalink( $topic_id );

	$reply_author_user_id = bbp_get_reply_author_id( $reply_id );
	$reply_author_display_name = bbp_get_topic_author_display_name( $reply_id );
	$reply_content = strip_tags( bbp_get_reply_content( $reply_id ) );
	$reply_url = bbp_get_reply_url( $reply_id ); // note that it's not get_permalink()

= Example =

To have a nice subject line for new topic notifications, add this to your theme's `functions.php`. If your theme does not have this file, you can simply create it and it will be loaded automatically. Note how the example is basically just one of the filters above, mixed with some of the snippets and a return statement. It's that simple.

	add_filter( 'bbp_forum_subscription_email_subject', function( $subject, $forum_id, $topic_id ) {
		$blog_name = get_bloginfo( 'name' );
		$topic_author_display_name = bbp_get_topic_author_display_name( $topic_id );
		$topic_title = wp_specialchars_decode( strip_tags( bbp_get_topic_title( $topic_id ) ), ENT_QUOTES );
		return "[$blog_name] $topic_author_display_name created a new topic: $topic_title";
	}, 10, 3); // first is priority (10 is default and just fine), second is number of arguments your filter expects

== Frequently Asked Questions ==

= No emails are being sent =
If other WP emails work normally try adding `define('ALTERNATE_WP_CRON', true);` to your `wp-config.php`

= Can I use real cron instead of WP cron? =
Yes. Add `define('DISABLE_WP_CRON', true);` to your `wp-config.php` and have a real cron job execute e.g. `wget -q -O - http://your.blog.example.com/wp-cron.php >/dev/null 2>&1`

== Changelog ==

= 3.7 =

* added two new filters that allow for sending notifications to post authors

= 3.6 =

* fixed bugs introduced in 3.5

= 3.5 =

* fix: in some cases email subjects contained html entities. They now contain proper characters.
* code improvements

= 3.4 =

* fix: notification email subjects now correctly display special characters like quotation marks

= 3.3 =

* fixed Spanish translation

= 3.2 =

* added Spanish translation

= 3.1 =

* fixed German translation

= 3.0 =

* now ready to be translated at https://translate.wordpress.org/projects/wp-plugins/asyncronous-bbpress-subscriptions
* added German translation

= 2.3 =

* bbp_subscription_email_from filter now accepts strings in addition to arrays

= 2.2 =

* added filters to enable synchronous sending if desired

= 2.1 =

* removed debug code

= 2.0 =

* replaced legacy bbPress code with proper implementation, major performance increase over bbPress default implementation
* updated message defaults to be more user friendly
* added filters to be backwards compatible with bbPress
* added filters to be forwards compatible with bbPress once this plugin's code is incorporated into bbPress core

= 1.5 =

* removed filter `abbps_to`, use `abbps_recipients` instead
* invoke `wp_specialchars_decode()` on blog name for From name

= 1.4 =

* updated code to match filter changes in bbPress 2.5.6
* now properly injects bbPress, using the `bbp_after_setup_actions` hook

= 1.3 =

* new filter: `abbps_bounce_address` allows setting of bounce address for email notifications
* minor code improvements

= 1.2 =

* changed filter: `abbps_from` to match the signature of the `abbps_to` filter (now passes an associative array instead of two strings).
* removed obsolete parameters from `abbps_to` `apply_filters()` call

= 1.1 =

* changed filter: `abbps_to` has new signature `abbps_to( $to, $post_author_user_id )` where $to is `array( 'name' => '', 'address' => '' )`
* new filter: `abbps_recipients` filters array of recipients just before sending so you can e.g. remove blacklisted emails just in time

= 1.0 =

* initial release
