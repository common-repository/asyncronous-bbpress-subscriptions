<?php
/*
Plugin Name: AsynCRONous bbPress Subscriptions
Description: Email notifications done right. No BCC lists, no added page load time.
Plugin URI: https://wordpress.org/plugins/asyncronous-bbpress-subscriptions/
Author: Markus Echterhoff
Author URI: https://www.markusechterhoff.com
Version: 3.7
License: GPLv3 or later
Text Domain: asyncronous-bbpress-subscriptions
Domain Path: /languages
*/

add_action( 'plugins_loaded', 'abbps_load_plugin_textdomain' );
function abbps_load_plugin_textdomain() {
    load_plugin_textdomain( 'asyncronous-bbpress-subscriptions', FALSE, basename( dirname( __FILE__ ) ) . '/languages' );
}

add_action( 'abbps_subscription_email_cron', array( 'ABBPS_Subscriber_Notification', 'send' ), 10, 1 );

class ABBPS_Subscriber_Notification {
	public $subject;
	public $message;
	public $headers;
	public $recipients;

	protected function __construct( $recipients ) {
		$this->recipients = $recipients;
		$from = array(
			'name' => wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES ),
			'address' => get_bloginfo( 'admin_email' )
		);
		$from = apply_filters( 'abbps_from', $from ); // abbps 1.5 compability
		$from = apply_filters( 'bbp_subscription_email_from', $from ); // bbpress forwards compatibility
		if ( is_array( $from ) ) {
			$from_string = empty( $from['name'] ) ? $from['address'] : "{$from['name']} <{$from['address']}>";
		} else {
			$from_string = $from;
		}
		$from_string = apply_filters( 'bbp_subscription_from_email', $from_string ); // bbpress backwards compatibility
		$this->headers []= "From: $from_string"; // array version $headers does not require proper line ending, see wp codex on wp_mail()
		$this->headers = apply_filters( 'bbp_subscription_email_headers', $this->headers ); // bbpress forwards compatibility
		$this->headers = apply_filters( 'bbp_subscription_mail_headers', $this->headers ); // bbpress backwards compatibility
	}

	public static function send( $notification ) {
		do_action( 'bbp_pre_notify_subscribers' );

		add_action( 'phpmailer_init', array( 'ABBPS_Subscriber_Notification', 'set_bounce_address' ), 10, 1 );

		foreach ( $notification->recipients as $to ) {
			$to_string = empty( $to['name'] ) ? $to['address'] : "{$to['name']} <{$to['address']}>";
			wp_mail( $to_string, $notification->subject, $notification->message, $notification->headers );
		}

		remove_action( 'phpmailer_init', array( 'ABBPS_Subscriber_Notification', 'set_bounce_address' ), 10, 1 );

		do_action( 'bbp_post_notify_subscribers' );
	}

	public static function set_bounce_address( $phpmailer ) {
		$bounce_address = apply_filters( 'abbps_bounce_address', false ); // abbps 1.5 compatibility
		$bounce_address = apply_filters( 'bbp_bounce_address', $bounce_address );  // bbpress forwards compatibility
		$bounce_address = apply_filters( 'bbp_get_do_not_reply_address', $bounce_address ); // bbpress backwards compatibility
		if ( $bounce_address ) {
			$phpmailer->Sender = $bounce_address;
		}
	}

	protected static function get_recipients( $user_ids ) {
		$recipients = array();

		if ( !empty( $user_ids ) ) {
			global $wpdb;
			$ids_substitution = substr( str_repeat( ',%d', count( $user_ids ) ), 1 );
			$params = array_merge( array( "select user_email as address, display_name as name from {$wpdb->users} where ID in ($ids_substitution)" ), $user_ids );
			$recipients = $wpdb->get_results( call_user_func_array( array( $wpdb, 'prepare' ), $params ), ARRAY_A );
			if ( !is_array( $recipients ) ) {
				$recipients = array();
			}
		}

		$recipients = apply_filters( 'abbps_recipients', $recipients ); // abbps 1.5 compatibility
		$recipients = apply_filters( 'bbp_subscription_email_recipients', $recipients ); // bbpress forwards compatibility

		return $recipients;
	}

	protected static function schedule_wp_cron_event( $notification ) {
		wp_schedule_single_event( time(), 'abbps_subscription_email_cron', array( $notification ) );
	}
}

class ABBPS_Forum_Subscriber_Notification extends ABBPS_Subscriber_Notification {

	protected function __construct( $recipients, $forum_id, $topic_id ) {
		parent::__construct( $recipients );

		// Remove filters from reply content and topic title to prevent content
		// from being encoded with HTML entities, wrapped in paragraph tags, etc...
		bbp_remove_all_filters( 'bbp_get_topic_content' );
		bbp_remove_all_filters( 'bbp_get_topic_title'   );
		bbp_remove_all_filters( 'the_title'             );

		// collect various pieces of information
		$blog_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		$topic_title = wp_specialchars_decode( strip_tags( bbp_get_topic_title( $topic_id ) ), ENT_QUOTES );
		$topic_author_display_name = bbp_get_topic_author_display_name( $topic_id );
		$topic_url = get_permalink( $topic_id );
		$topic_content = wp_specialchars_decode( strip_tags( bbp_get_topic_content( $topic_id ) ), ENT_QUOTES );
		$user_id = bbp_get_topic_author_id( $topic_id ); // for backwards compatible filters below

		// Restore previously removed filters
		bbp_restore_all_filters( 'bbp_get_topic_content' );
		bbp_restore_all_filters( 'bbp_get_topic_title'   );
		bbp_restore_all_filters( 'the_title'             );

		// subject
		$this->subject = sprintf( __( "[%s Forums] New Topic: \"%s\"", 'asyncronous-bbpress-subscriptions' ), $blog_name, $topic_title );
		$this->subject = apply_filters( 'abbps_topic_subject', $this->subject, $forum_id, $topic_id ); // abbps 1.5 compatibility
		$this->subject = apply_filters( 'bbp_forum_subscription_email_subject', $this->subject, $forum_id, $topic_id ); // bbpress forwards compatibility
		$this->subject = apply_filters( 'bbp_forum_subscription_mail_title', $this->subject, $topic_id, $forum_id, $user_id ); // bbpress backwards compatibility

		// message
		$this->message = sprintf( __( "%s wrote:

%s


-----------------------------------------
Read this post online: %s

If you don't want to receive any more email notifications for this forum, please visit the above link and click \"Unsubscribe\" at the top of the page.", 'asyncronous-bbpress-subscriptions' ), $topic_author_display_name, $topic_content, $topic_url );
		$this->message = apply_filters( 'abbps_topic_message', $this->message, $forum_id, $topic_id ); // abbps 1.5 compatibility
		$this->message = apply_filters( 'bbp_forum_subscription_email_message', $this->message, $forum_id, $topic_id ); // bbpress forwards compatibility
		$this->message = apply_filters( 'bbp_forum_subscription_mail_message', $this->message, $topic_id, $forum_id, $user_id ); // bbpress backwards compatibility
	}

	public static function schedule_sending( $forum_id, $topic_id ) {

		// general checks and parameter validation
		if ( !bbp_is_subscriptions_active() ||
				!bbp_get_forum_id( $forum_id ) ||
				!bbp_is_topic_published( $topic_id ) ) {
			return false;
		}

		// recipients
		$user_ids = bbp_get_forum_subscribers( $forum_id, true );
		if ( ! apply_filters( 'bbp_forum_subscription_notify_author', false ) ) {
			$user_ids = array_diff( $user_ids, array( bbp_get_topic_author_id( $topic_id ) ) );
		}
		$user_ids = apply_filters( 'bbp_forum_subscription_user_ids', $user_ids ); // bbpress compatibility
		$recipients = parent::get_recipients( $user_ids );
		if ( !$recipients ) {
			return false;
		}

		$notification = new ABBPS_Forum_Subscriber_Notification( $recipients, $forum_id, $topic_id );

		if ( apply_filters( 'bbp_subscription_disable_async', false ) || // bbpress forwards compatibility
			apply_filters( 'bbp_forum_subscription_disable_async', false ) ) { // bbpress forwards compatibility
			parent::send( $notification );
			return false;
		}

		parent::schedule_wp_cron_event( $notification );

		return true;
	}
}

class ABBPS_Topic_Subscriber_Notification extends ABBPS_Subscriber_Notification {

	protected function __construct( $recipients, $forum_id, $topic_id, $reply_id ) {
		parent::__construct( $recipients );

		// Remove filters from reply content and topic title to prevent content
		// from being encoded with HTML entities, wrapped in paragraph tags, etc...
		bbp_remove_all_filters( 'bbp_get_reply_content' );
		bbp_remove_all_filters( 'bbp_get_topic_title'   );
		bbp_remove_all_filters( 'the_title'             );

		// collect various pieces of information
		$blog_name = wp_specialchars_decode( get_option( 'blogname' ), ENT_QUOTES );
		$topic_title = wp_specialchars_decode( strip_tags( bbp_get_topic_title( $topic_id ) ), ENT_QUOTES );
		$reply_author_display_name = bbp_get_reply_author_display_name( $reply_id );
		$reply_url = bbp_get_reply_url( $reply_id );
		$reply_content = wp_specialchars_decode( strip_tags( bbp_get_reply_content( $reply_id ) ), ENT_QUOTES );

		// Restore previously removed filters
		bbp_restore_all_filters( 'bbp_get_reply_content' );
		bbp_restore_all_filters( 'bbp_get_topic_title'   );
		bbp_restore_all_filters( 'the_title'             );

		// subject
		$this->subject = sprintf( __( "[%s Forums] New reply to: \"%s\"", 'asyncronous-bbpress-subscriptions' ), $blog_name, $topic_title );
		$this->subject = apply_filters( 'abbps_reply_subject', $this->subject, $forum_id, $topic_id, $reply_id ); // abbps 1.5 compatibility
		$this->subject = apply_filters( 'bbp_topic_subscription_email_subject', $this->subject, $forum_id, $topic_id, $reply_id ); // bbpress forwards compatibility
		$this->subject = apply_filters( 'bbp_subscription_mail_title', $this->subject, $reply_id, $topic_id ); // bbpress backwards compatibility

		// message
		$this->message = sprintf( __( "%s replied:

%s


-----------------------------------------
Read this post online: %s

If you don't want to receive any more email notifications for this topic, please visit the above link and click \"Unsubscribe\" at the top of the page.", 'asyncronous-bbpress-subscriptions' ), $reply_author_display_name, $reply_content, $reply_url );
		$this->message = apply_filters( 'abbps_reply_message', $this->message, $forum_id, $topic_id, $reply_id ); // abbps 1.5 compatibility
		$this->message = apply_filters( 'bbp_topic_subscription_email_message', $this->message, $forum_id, $topic_id, $reply_id ); // bbpress forwards compatibility
		$this->message = apply_filters( 'bbp_subscription_mail_message', $this->message, $reply_id, $topic_id ); // bbpress backwards compatibility
	}

	public static function schedule_sending( $forum_id, $topic_id, $reply_id ) {

		// general checks and parameter validation
		if ( !bbp_is_subscriptions_active() ||
				!bbp_get_forum_id( $forum_id ) ||
				!bbp_is_topic_published( $topic_id ) ||
				!bbp_is_reply_published( $reply_id ) ) {
			return false;
		}

		// recipients
		$user_ids = bbp_get_topic_subscribers( $topic_id, true );
		if ( ! apply_filters( 'bbp_topic_subscription_notify_author', false ) ) {
			$user_ids = array_diff( $user_ids, array( bbp_get_reply_author_id( $reply_id ) ) );
		}
		$user_ids = apply_filters( 'bbp_topic_subscription_user_ids', $user_ids ); // bbpress compatibility
		$recipients = parent::get_recipients( $user_ids );
		if ( !$recipients ) {
			return false;
		}

		$notification = new ABBPS_Topic_Subscriber_Notification( $recipients, $forum_id, $topic_id, $reply_id );

		if ( apply_filters( 'bbp_subscription_disable_async', false ) || // bbpress forwards compatibility
			apply_filters( 'bbp_topic_subscription_disable_async', false ) ) { // bbpress forwards compatibility
			parent::send( $notification );
			return false;
		}

		parent::schedule_wp_cron_event( $notification );

		return true;
	}
}

add_action( 'bbp_after_setup_actions', 'abbps_inject' );
function abbps_inject() {
	remove_action( 'bbp_new_topic', 'bbp_notify_forum_subscribers', 11, 4 );
	add_action( 'bbp_new_topic', 'abbps_notify_forum_subscribers', 11, 4 );

	remove_action( 'bbp_new_reply', 'bbp_notify_topic_subscribers', 11, 5 );
	add_action( 'bbp_new_reply', 'abbps_notify_topic_subscribers', 11, 5 );
}

function abbps_notify_forum_subscribers( $topic_id = 0, $forum_id = 0, $anonymous_data = false, $topic_author = 0 ) {
	return ABBPS_Forum_Subscriber_Notification::schedule_sending( $forum_id, $topic_id );
}

function abbps_notify_topic_subscribers( $reply_id = 0, $topic_id = 0, $forum_id = 0, $anonymous_data = false, $reply_author = 0 ) {
	return ABBPS_Topic_Subscriber_Notification::schedule_sending( $forum_id, $topic_id, $reply_id );
}

?>
