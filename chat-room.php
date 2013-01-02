<?php
/*
Plugin Name: Chat Room
Plugin URI: http://webdevstudios.com/support/wordpress-plugins/
Description: Chat Room for WordPress
Author: WebDevStudios.com
Version: 0.1.2
Author URI: http://webdevstudios.com/
License: GPLv2 or later
*/

Class Chatroom {
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
	}

	function activation_hook() {
		$this->register_post_types();
		flush_rewrite_rules();
	}

	function deactivation_hook() {
		flush_rewrite_rules();
	}

	function register_post_types() {
		$labels = array(
			'name' => _x( 'Chat Rooms', 'post type general name', 'chatroom' ),
			'singular_name' => _x( 'Chat Room', 'post type singular name', 'chatroom' ),
			'add_new' => _x( 'Add New', 'book', 'chatroom' ),
			'add_new_item' => __( 'Add New Chat Room', 'chatroom' ),
			'edit_item' => __( 'Edit Chat Room', 'chatroom' ),
			'new_item' => __( 'New Chat Room', 'chatroom' ),
			'all_items' => __( 'All Chat Rooms', 'chatroom' ),
			'view_item' => __( 'View Chat Room', 'chatroom' ),
			'search_items' => __( 'Search Chat Rooms', 'chatroom' ),
			'not_found' => __( 'No Chat Rooms found', 'chatroom' ),
			'not_found_in_trash' => __( 'No Chat Rooms found in Trash', 'chatroom' ), 
			'parent_item_colon' => '',
			'menu_name' => __( 'Chat Rooms', 'chatroom' )
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

	function enqueue_scripts() {
		global $post;
		if ( $post->post_type != 'chat-room' )
			return;
		wp_enqueue_script( 'jquery' );
		wp_enqueue_script( 'chat-room', plugins_url( 'chat-room.js', __FILE__ ) );
		wp_enqueue_style( 'chat-room-styles', plugins_url( 'chat-room.css', __FILE__ ) );
	}
	function maybe_create_chatroom_log_file( $post_id, $post ) {
		if ( empty( $post->post_type ) || $post->post_type != 'chat-room' )
			return;
		$upload_dir = wp_upload_dir();
		$log_filename = $upload_dir['basedir'] . '/chatter/' . $post->post_name . '-' . date( 'm-d-y', time() );
		if ( file_exists( $log_filename ) )
			return;
		wp_mkdir_p( $upload_dir['basedir'] . '/chatter/' );
		$handle = fopen( $log_filename, 'w' );

		fwrite( $handle, json_encode( array() ) );

		// TODO create warnings if the user can't create a file, and suggest putting FTP creds in wp-config
	}
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
	function ajax_check_updates_handler() {
		$upload_dir = wp_upload_dir();
		$log_filename = $this->get_log_filename( $_POST['chatroom_slug'] ); 
		$contents = $this->parse_messages_log_file( $log_filename );
		$messages = json_decode( $contents );
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
			'html' => '<div class="chat-message-' . $new_message_id . '"><strong style="color: ' . $user_text_color . ';">' . $user->user_login . '</strong>: ' . $content . '</div>',
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
			'html' => '<div class="chat-message-' . $new_message_id .'"><strong style="color: ' . $user_text_color . ';">' . $user->user_login . '</strong>: ' . $content . '</div>',
		);
		$this->write_log_file( $log_filename, json_encode( $messages ) );
	}
	function write_log_file( $log_filename, $content ) {
		$handle = fopen( $log_filename, 'w' );
		fwrite( $handle, $content );
	}



	function get_log_filename( $chatroom_slug, $date = 'recent' ) {
		$upload_dir = wp_upload_dir();	
		$log_filename = $upload_dir['basedir'] . '/chatter/' . $chatroom_slug . '-' . $date;
		return $log_filename;
	}

	function parse_messages_log_file( $log_filename ) {
		$upload_dir = wp_upload_dir();
		$handle = fopen( $log_filename, 'r' );
		$contents = fread( $handle, filesize( $log_filename ) );
		fclose( $handle );
		return $contents;
	}

	function the_content_filter( $content ) {
		global $post;
		if ( $post->post_type != 'chat-room' ) 
			return $content;
		if ( ! is_user_logged_in() )  {
			?>You need to be logged in to participate in the chatroom.<?php 
			return;
		}
			
		?>
		<div class="chat-container">
		</div>
		<textarea class="chat-text-entry"></textarea>
		<?php
		return '';
	}

}

$chatroom = new Chatroom();