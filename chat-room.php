<?php
/*
Plugin Name: Chat Room
Plugin URI: http://webdevstudios.com/support/wordpress-plugins/
Description: Chat Room for WordPress
Author: WebDevStudios.com
Version: 0.2
Author URI: http://webdevstudios.com/
License: GPLv2 or later
*/

Class Chatroom {

	public $noinception = 'false';

	function __construct() {
		register_activation_hook( __FILE__, array( $this, 'activation_hook' ) );
		register_deactivation_hook( __FILE__, array( $this, 'deactivation_hook' ) );
		add_action( 'init', array( $this, 'register_post_types' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
		add_action( 'save_post', array( $this, 'maybe_create_chatroom_log_file' ), 10, 2 );
		add_action( 'wp_head', array( $this, 'define_javascript_variables' ) );
		add_action( 'wp_ajax_check_updates', array( $this, 'ajax_check_updates_handler' ) );
		add_action( 'wp_ajax_send_message', array( $this, 'ajax_send_message_handler' ) );
		add_filter( 'the_content', array( $this, 'the_content_filter' ) );
		load_plugin_textdomain( 'chat-room', false, 'chat-room/languages' );
		add_action( 'add_meta_boxes', array( $this, 'register_meta_box' ) );
		add_action( 'save_post', array( $this, 'save_meta_box' ) );
		add_action( 'after_chat_room_msgbox', array( $this, 'submit_button' ) );
		add_action( 'after_chat_room_msgbox', array( $this, 'usability_notes' ) );
		add_action( 'publish_chat-room', array( $this, 'yoast_fix' ), 10, 2 );
	}

	/**
	 * Let's get the party started. Register our CPT and flush the rewrite rules.
	 * @return void nothing to return here.
	 */
	function activation_hook() {
		$this->register_post_types();
		flush_rewrite_rules();
	}
	/**
	 * Aww, busted. Flush the rewrite rules for after the chat room CPT left.
	 * @return void nothing to return here.
	 */
	function deactivation_hook() {
		flush_rewrite_rules();
	}

	/**
	 * Create and register our post type
	 * @return void nothing to return here.
	 */
	function register_post_types() {
		$labels = array(
			'name' => _x( 'Chat Rooms', 'post type general name', 'chat-room' ),
			'singular_name' => _x( 'Chat Room', 'post type singular name', 'chat-room' ),
			'add_new' => _x( 'Add New', 'book', 'chat-room' ),
			'add_new_item' => __( 'Add New Chat Room', 'chat-room' ),
			'edit_item' => __( 'Edit Chat Room', 'chat-room' ),
			'new_item' => __( 'New Chat Room', 'chat-room' ),
			'all_items' => __( 'All Chat Rooms', 'chat-room' ),
			'view_item' => __( 'View Chat Room', 'chat-room' ),
			'search_items' => __( 'Search Chat Rooms', 'chat-room' ),
			'not_found' => __( 'No Chat Rooms found', 'chat-room' ),
			'not_found_in_trash' => __( 'No Chat Rooms found in Trash', 'chat-room' ),
			'parent_item_colon' => '',
			'menu_name' => __( 'Chat Rooms', 'chat-room' )
		);
		$args = array(
			'labels' => $labels,
			'public' => true,
			'publicly_queryable' => true,
			'show_ui' => true,
			'show_in_menu' => true,
			'query_var' => true,
			'capability_type' => 'post',
			'has_archive' => true,
			'hierarchical' => false,
			'menu_position' => null,
			'show_in_nav_menus' => true,
			'supports' => array( 'title' )
		);
		register_post_type( 'chat-room', $args );
	}

	/**
	 * Enqueue our js and css, only for when we're on a chat room.
	 * @return void nothing to return here.
	 */
	function enqueue_scripts() {
		global $post;
		if ( $post->post_type != 'chat-room' )
			return;
		wp_enqueue_script( 'chat-room', plugins_url( 'chat-room.js', __FILE__ ), array( 'jquery' ) );
		wp_enqueue_style( 'chat-room-styles', plugins_url( 'chat-room.css', __FILE__ ) );
	}

	/**
	 * Set up and prepare our log files
	 * @param  int $post_id ID for the current chat room
	 * @param  object $post    WP_Query object for our chat room post type.
	 * @return void          nothing to return here.
	 */
	function maybe_create_chatroom_log_file( $post_id, $post ) {
		if ( empty( $post->post_type ) || 'chat-room' != $post->post_type || 'Auto Draft' == $post->post_title ) {
			return;
		}

		$upload_dir = wp_upload_dir();
		$log_folder = apply_filters( 'chat_room_log_dir', 'chatter' );
		$log_filename = $upload_dir['basedir'] . '/' . $log_folder . '/' . $post->post_name . '-' . date( 'm-d-y', time() );
		if ( file_exists( $log_filename ) ) {
			return;
		}

		wp_mkdir_p( $upload_dir['basedir'] . '/' . $log_folder );
		$handle = fopen( $log_filename, 'w' );

		fwrite( $handle, json_encode( array() ) );

		// TODO create warnings if the user can't create a file, and suggest putting FTP creds in wp-config
	}

	/**
	 * set up our js variables for use with ajax.
	 * @return void nothing to return here.
	 */
	function define_javascript_variables() {
		global $post;
		if ( empty( $post->post_type ) || $post->post_type != 'chat-room' )
			return; ?>
		<script>
		var ajaxurl = '<?php echo admin_url( 'admin-ajax.php' ); ?>';
		var chatroom_slug = '<?echo $post->post_name; ?>';
		</script>
		<?php

	}

	/**
	 * Check on our logs
	 * @return void nothing to return here.
	 */
	function ajax_check_updates_handler() {
		$upload_dir = wp_upload_dir();
		$log_filename = $this->get_log_filename( $_POST['chatroom_slug'] );
		$contents = $this->parse_messages_log_file( $log_filename );
		$messages = json_decode( $contents );

		if ( !is_array( $messages ) ) { die; }

		foreach ( $messages as $key => $message ) {
			if ( $message->id <= $_POST['last_update_id'] )
				unset( $messages[$key] );
		}
		$messages = array_values( $messages );
		echo json_encode( $messages );
		die;
	}

	/**
	 * AJAX server-side handler for sending a message.
	 *
	 * Stores the message in a recent messages file.
	 *
	 * Clears out cache of any messages older than 10 seconds.
	 */
	function ajax_send_message_handler() {
		$current_user = wp_get_current_user();
		$this->save_message( $_POST['chatroom_slug'], $current_user->id, $_POST['message'] );
		die;
	}

	/**
	 * Prepare to write to our log file with new content
	 * @param  string $chatroom_slug slug for the current chat room
	 * @param  int $user_id       user ID for the message sender
	 * @param  string $content       user's message
	 * @return void                nothing to return here.
	 */
	function save_message( $chatroom_slug, $user_id, $content ) {
		$user = get_userdata( $user_id );

		if ( ! $user_text_color = get_user_meta( $user_id, 'user_color', true ) ) {
	    	// Set random color for each user
	    	$red = rand( 0, 16 );
	    	$green = 16 - $red;
	    	$blue = rand( 0, 16 );
		    $user_text_color = '#' . dechex( $red^2 ) . dechex( $green^2 ) . dechex( $blue^2 );
	    	update_user_meta( $user_id, 'user_color', $user_text_color );
	    }

		$content = esc_attr( $content );
		//allow adding custom classes to message output
		$chat_custom_classes = implode( ' ', apply_filters( 'chat_room_custom_msg_classes', array() ) );
		// Save the message in recent messages file

		$log_filename = $this->get_log_filename( $chatroom_slug );
		$contents = $this->parse_messages_log_file( $log_filename );
		$messages = json_decode( $contents );
		$last_message_id = 0; // Helps determine the new message's ID
		foreach ( $messages as $key => $message ) {
			if ( time() - $message->time > 10 ) {
				$last_message_id = $message->id;
				unset( $messages[$key] );
			}
			else {
				break;
			}
		}
		$messages = array_values( $messages );
		if ( ! empty( $messages ) )
			$last_message_id = end( $messages )->id;
		$new_message_id = $last_message_id + 1;
		$messages[] = array(
			'id' => $new_message_id,
			'time' => time(),
			'sender' => $user_id,
			'contents' => $content,
			'html' => '<div data-user-id="' . $user->user_login . '" class="chat-message-' . $new_message_id . ' ' . $chat_custom_classes . '"><strong style="color: ' . $user_text_color . ';">' . $user->user_login . '</strong>: ' . $content . '</div>',
		);
		$this->write_log_file( $log_filename, json_encode( $messages ) );

		// Save the message in the daily log
		$log_filename = $this->get_log_filename( $chatroom_slug, date( 'm-d-y', time() ) );
		$contents = $this->parse_messages_log_file( $log_filename );
		$messages = json_decode( $contents );
		$messages[] = array(
			'id' => $new_message_id,
			'time' => time(),
			'sender' => $user_id,
			'contents' => $content,
			'html' => '<div data-user-id="' . $user->user_login . '" class="chat-message-' . $new_message_id . ' ' . $chat_custom_classes . '"><strong style="color: ' . $user_text_color . ';">' . $user->user_login . '</strong>: ' . $content . '</div>',
		);
		$this->write_log_file( $log_filename, json_encode( $messages ) );
	}

	/**
	 * Open and actually write to the log file
	 * @param  string $log_filename url for the appropriate log file
	 * @param  string $content      messages to add
	 * @return void               nothing to return here.
	 */
	function write_log_file( $log_filename, $content ) {
		$handle = fopen( $log_filename, 'w' );
		fwrite( $handle, $content );
	}

	/**
	 * Return our log file name
	 * @param  string $chatroom_slug slug for the chat room log to write to
	 * @param  string $date          date timestamp or "recent" to append to log file name
	 * @return string                log file name
	 */
	function get_log_filename( $chatroom_slug, $date = 'recent' ) {
		$upload_dir = wp_upload_dir();
		$log_folder = apply_filters( 'chat_room_log_dir', 'chatter' );
		$log_filename = $upload_dir['basedir'] . $log_folder . $chatroom_slug . '-' . $date;
		return $log_filename;
	}

	/**
	 * Parse our file for reading
	 * @param  string $log_filename url for the appropriate log file
	 * @return string               log content
	 */
	function parse_messages_log_file( $log_filename ) {
		$upload_dir = wp_upload_dir();
		$handle = fopen( $log_filename, 'r' );
		$contents = fread( $handle, filesize( $log_filename ) );
		fclose( $handle );
		return $contents;
	}

	/**
	 * Filter callback for chat room used to display chat room in place of the_content.
	 * @param  [type] $content [description]
	 * @return [type]          [description]
	 */
	function the_content_filter( $content ) {
		global $post;

		if ( $post->post_type != 'chat-room' ) {
			return $content;
		}

		if ( '1' == get_post_meta( $post->ID, '_logged_in_only', true ) )  {
			echo apply_filters( 'chat_room_not_logged_in_msg', 'You need to be logged in to participate in the chatroom.' );
			return;
		}

		do_action( 'before_chat_room_log' );
		?>
		<div class="chat-container">
		</div>
		<?php
		do_action( 'before_chat_room_msgbox' ); ?>
		<textarea class="chat-text-entry" placeholder="<?php echo esc_attr( apply_filters( 'chat-room-placeholder', '' ) ); ?>"></textarea>
		<?php
		do_action( 'after_chat_room_msgbox' );
	}

	function register_meta_box() {
		$screens = array( 'chat-room' );
		foreach ($screens as $screen) {
			add_meta_box( 'logged-in-only', 'Privacy?', array( $this, 'render_meta_box' ), $screen, 'side', 'default' );
		}
	}

	/* Prints the box content */
	function render_meta_box( $post ) {
		// Use nonce for verification
		wp_nonce_field( plugin_basename( __FILE__ ), 'chatroom_loggedin_nonce' );

		// The actual fields for data entry
		// Use get_post_meta to retrieve an existing value from the database and use the value for the form
		$value = get_post_meta( $post->ID, '_logged_in_only', true );
		?>
		<input type="checkbox" id="logged_in_only" name="logged_in_only" value="1" <?php if( $value == '1') echo 'checked="checked"'; ?> />
		<label for="logged_in_only"><?php _e( 'Should the user be logged in?', 'chat-room' ); ?></label><br/>
		<?php
	}

	function save_meta_box( $post_id ) {
		// First we need to check if the current user is authorised to do this action.
		if ( 'page' == $_POST['post_type'] ) {
			if ( ! current_user_can( 'edit_page', $post_id ) )
				return;
		} else {
			if ( ! current_user_can( 'edit_post', $post_id ) )
				return;
		}

		// Secondly we need to check if the user intended to change this value.
		if ( ! isset( $_POST['chatroom_loggedin_nonce'] ) || ! wp_verify_nonce( $_POST['chatroom_loggedin_nonce'], plugin_basename( __FILE__ ) ) )
			return;

		//if saving in a custom table, get post_ID
		$post_ID = $_POST['post_ID'];
		//sanitize user input
		$mydata = sanitize_text_field( $_POST['logged_in_only'] );

		update_post_meta($post_ID, '_logged_in_only', $mydata);
	}

	function submit_button() {
		echo '<p><a href="#" class="chat-submit">Send to chat</a></p>';
	}

	function usability_notes() {
		echo '<p><small>' . __( 'Press enter, or click "Send to chat", to submit. Click a user name to add it to the textarea.', 'chat-room' ) . '<small></p>';
	}

	function yoast_fix( $post_id, $post ) {

		if( 'true' == $this->noinception || isset( $post->post_excerpt ) ) {
			return;
		}

		$this->noinception = 'true';

		$args = array(
			'ID' => $post_id,
			'post_excerpt' => $post->post_title
		);
		wp_update_post( $args );

	}
}

$chatroom = new Chatroom();
