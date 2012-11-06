<?php
/*
 * Plugin Name: Subscriptions (to post comments)
 * Description: Get comment notifications (CC'd) on posts you haven't written
 * Plugin URI:
 * Version: 2012-11-05
 * Author: Kailey Lampert
 * Author URI: http://kaileylampert.com
 */

// give our set of plugins a special filter for sorting actions
if ( ! has_action( 'p2_action_links', 'p2_append_actions') ) {
	add_action( 'p2_action_links', 'p2_append_actions');
	function p2_append_actions( ) {

		if ( ! is_user_logged_in() ) return;
		$items = apply_filters( 'p2_action_items', array() );
		ksort( $items );
		$items = implode( ' | ', $items );

		echo " | $items";
	}
}

$p2_subscriptions = new P2_Subscriptions();

class P2_Subscriptions {
	function __construct() {
		add_action( 'init', array( &$this, 'init' ) );
	}
	function init() {

		$subscriptions_labels = array(
			'name' => 'Subscriptions',
			'singular_name' => 'Subscription',
		);
		register_taxonomy( 'subscriptions', 'post', array(
				'hierarchical' => false,
				'labels' => $subscriptions_labels,
				'sort' => true,
				'public' => true,
				'rewrite' => array('slug' => 'subscriptions'),
			)
		);

		if ( ! is_user_logged_in() ) return;

		add_action( 'wp_ajax_toggle_sub_status', array( &$this, 'toggle_sub_status_cb' ) );
		add_action( 'wp_enqueue_scripts', array( &$this, 'wp_enqueue_scripts') );
		add_filter( 'p2_action_items', array( &$this, 'p2_action_items' ) );
		add_filter( 'comment_notification_headers', array( &$this, 'comment_notification_headers' ), 10, 2 );

		add_action( 'admin_bar_menu', array( &$this, 'admin_bar_menu' ) );
	}

	function comment_notification_headers( $message_headers, $comment_id ) {
		$comment = get_comment( $comment_id );
		$post_id = $comment->comment_post_ID;
		$post = get_post($post_id);

		$pauth = get_userdata($post->post_author)->user_email;
		$cauth = $comment->comment_author_email;

		//loop through subscribers (taxonomy) get user email addresses
		$terms = wp_get_post_terms( $post_id, 'subscriptions' );
		$emails = array();
		foreach ($terms as $k=>$t) {
			$email = get_user_by( 'login', $t->name )->user_email;
			if ($email == $pauth || $email == $cauth) //don't cc the OP or commenter
				continue;
			$emails[] = $email;
		}

		$message_headers .= 'CC: '.implode(',',$emails) . "\n";

		return $message_headers;
	}

	function wp_enqueue_scripts() {
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'subscriptions', plugins_url( 'js/subscriptions.js', __FILE__ ), array( 'jquery' ), '4' );
		wp_localize_script( 'subscriptions', 'subscriptions', array( 'ajaxurl' => admin_url( 'admin-ajax.php' ) ) );
	}

	function p2_action_items( $items ) {
		if ( is_page() ) return $items;

		global $post;
		if ( $post->post_author != get_current_user_id() )
		$items[-1] = '<a href="#" class="toggle-sub-status">'. $this->get_sub_status($post->ID).'</a>';
		return $items;
	}

	function get_sub_status( $post_id ) {
		$username = get_userdata( get_current_user_id() )->user_login;
		if ( has_term( $username, 'subscriptions', $post_id ))
			return 'Unsubscribe';
		return 'Subscribe';
	}

	// ajax callback
	function toggle_sub_status_cb() {
		$id = $_POST['post_id'];

		$username = get_userdata( get_current_user_id() )->user_login;
		if ( has_term( $username, 'subscriptions', $id )) {
			$terms = wp_get_post_terms( $id, 'subscriptions' );
			$keep = array();
			foreach ($terms as $k=>$t) {
				if ($t->name != $username) {
					$keep[] = $t->name;
				}
			}
			wp_set_post_terms( $id, $keep, 'subscriptions' );
			die( 'Subscribe' );
		} elseif ( wp_set_post_terms( $id, $username, 'subscriptions', true )) {
			die( 'Unsubscribe' );
		} else {
			die( 'could not change status' );
		}
	}

	function admin_bar_menu( $wp_admin_bar ) {

		if ( is_admin() ) return;

		$login = wp_get_current_user()->user_login;

		$node = array (
			'parent' => 'my-account',
			'id' => 'my-subscriptions',
			'title' => 'My Subscriptions',
			'href' => get_term_link( $login, 'subscriptions' )
		);

		$wp_admin_bar->add_menu( $node );

	}

}