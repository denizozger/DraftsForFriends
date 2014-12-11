<?php
/*
 * Plugin Name: Drafts for Friends
 * Text Domain: draftsforfriends
 * Description: Now you don't need to add friends as users to the blog in order to let them preview your drafts
 * Author: Deniz Ozger
 * Version: 1.0
*/ 

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}

if ( ! class_exists( 'DraftsForFriends' ) ):

class DraftsForFriends	{

	function __construct(){
    		add_action('init', array( &$this, 'init') );
	}

	function init() {
		global $current_user;

		if(!session_id()) {
	        	session_start();

		        if (! $_SESSION['submitted_form_ids'] ) {
		        	$_SESSION['submitted_form_ids'] = array();
		        }
		}

		add_action('admin_menu', array( $this, 'add_admin_pages') );
		add_filter('the_posts', array( $this, 'the_posts_intercept') );
		add_filter('posts_results', array( $this, 'posts_results_intercept') );
	
		$this->admin_options = $this->get_admin_options();
	
		$this->user_options = ($current_user->id > 0 && 
			isset( $this->admin_options[$current_user->id]) ) ? $this->admin_options[$current_user->id] : 
				array();
	
		$this->save_admin_options();
	
		$this->load_scripts_and_stylesheets();
	}

	function load_scripts_and_stylesheets() {
		wp_enqueue_script('jquery');

		wp_register_script( 'drafts-for-friends-javascript', 
			plugins_url( 'js/drafts-for-friends.js', __FILE__ ), 
			array( 'jquery' ),'1.1', true) ;
		wp_enqueue_script( 'drafts-for-friends-javascript' );
		wp_localize_script( 'drafts-for-friends-javascript', 'dffL10n', array(
			'expired' => __( 'Expired', 'draftsforfriends' ),
			'seconds' => __( 'seconds', 'draftsforfriends' ),
			'aboutAMinute' => __( 'about a minute', 'draftsforfriends' ),
			'minute' => __( 'minute', 'draftsforfriends' ),
			'minutes' => __( 'minutes', 'draftsforfriends' ),
			'hours' => __( 'hours', 'draftsforfriends' ),
			'days' => __( 'days', 'draftsforfriends' ),
		) );

		wp_register_style( 'drafts-for-friends-stylesheet', 
			plugins_url( 'css/drafts-for-friends.css', __FILE__ ) );
		wp_enqueue_style( 'drafts-for-friends-stylesheet' );
	}

	function get_admin_options() {
		$saved_options = get_option( 'shared' );

		return is_array( $saved_options ) ? $saved_options : array();
	}

	function save_admin_options(){
		global $current_user;
	
		if ( $current_user->id > 0 ) {
		    $this->admin_options[$current_user->id] = $this->user_options;
		}
	
		update_option( 'shared', $this->admin_options );
	}

	function add_admin_pages(){
		add_submenu_page('edit.php', __('Drafts for Friends', 'draftsforfriends'), 
			__('Drafts for Friends', 'draftsforfriends'), 1, __FILE__, 
			array( $this, 'output_existing_menu_sub_admin_page' ) );
	}

	/*
	 * Form Actions
	 */

	function process_share( $form ) {
		if ( ! isset( $form['post_id'] ) || ! current_user_can( 'publish_pages' ) ||
				! wp_verify_nonce( $form['nonce'], 'draftsforfriends_frontend_share' ) ) {
			$_POST['error'] = true;
	        	return __('Sorry, there was an error. Please contact the plugin developer', 
	        		'draftsforfriends');
		}

		if ( $this->same_form_was_submitted_already( $form['unique_form_id'] ) )  {
			return;
		}

		// Check if there is a draft for this post already
		foreach ($this->get_shared_drafts() as $shared_draft) {
  	 		if ($shared_draft['id'] == $form['post_id']) {
 				$_POST['error'] = true;
 		        	return __('A shared draft for this post already exists, please extend it', 
 		        		'draftsforfriends');
	  	 	}   
		}

		global $current_user;

		$post = get_post( $form['post_id'] );

		if ( ! $post ) {
			$_POST['error'] = true;
			return __('Please choose a draft to share', 'draftsforfriends');
		}

		if ( ! isset($form['expires']) || 0 >= sanitize_text_field( $form['expires'] ) ) {
			$_POST['error'] = true;
			return __('Time to share should be a positive number', 'draftsforfriends');	
		}

		if ( 'publish' == get_post_status($post) ) {
			$_POST['error'] = true;
			return __('This post is published already', 'draftsforfriends');
		}

		$this->user_options['shared'][] = array(
			'id' => $post->ID,
			'expires' => time() + $this->get_extended_time_from_form($form),
			'key' => wp_generate_password( 8, false, false ) 
		);
		
		$this->save_admin_options();

		array_push($_SESSION['submitted_form_ids'], $form['unique_form_id']);

		return __('Draft has been shared', 'draftsforfriends');
	}

	function process_delete( $params ) {
		if ( ! isset( $params['key'] ) || ! isset( $this->user_options['shared'] ) || 
			! current_user_can( 'publish_pages' ) || 
			! wp_verify_nonce( $_REQUEST['nonce'], 'draftsforfriends_frontend_delete' ) ) {
			$_POST['error'] = true;
	        return __('Sorry, there was an error. Please contact the plugin developer', 
	        	'draftsforfriends');
		}

		$shared = array();

		foreach( $this->user_options['shared'] as $share ) {
			if ( $share['key'] == $params['key'] ) {
				continue;
			}

			$shared[] = $share;	     
		}

		$this->user_options['shared'] = $shared;
		$this->save_admin_options();	

		return __('Share has been deleted', 'draftsforfriends');
	}

	function process_extend( $params ) {
		if ( ! isset( $params['key'] ) || ! isset( $this->user_options['shared'] ) || 
				! current_user_can( 'publish_pages' ) || ! isset( $params['unique_form_id'] ) ||
				! wp_verify_nonce( $params['nonce'], 'draftsforfriends_frontend_extend' )) {
			$_POST['error'] = true;
	        	return __('Sorry, there was an error. Please contact the plugin developer', 
	        		'draftsforfriends');
		}

		if ( $this->same_form_was_submitted_already( $params['unique_form_id']) )  {
			return;
		}

		$shared = array();

		foreach( $this->user_options['shared'] as $share ) {
			if ( $share['key'] == $params['key'] ) {

				$time_to_add = $this->get_extended_time_from_form($params);

				if ( $share['expires'] < time() ) {
					$share['expires'] = time() + $time_to_add;
				} else {
					$share['expires'] += $time_to_add;
				}
			}

			$shared[] = $share;
		}

		$this->user_options['shared'] = $shared;

		$this->save_admin_options();

		array_push($_SESSION['submitted_form_ids'], $params['unique_form_id']);

		return __("Draft's share duration has been extended", 'draftsforfriends');
	}

	/*
	 * Helper functions
	 */

	function same_form_was_submitted_already( $form_id ) {
		return in_array( $form_id, $_SESSION['submitted_form_ids'] );
	}

	function get_extended_time_from_form( $form ) {
		$extend_by = 60;
		$time_in_seconds = 60;

		if ( isset($form['expires']) && 
			( $e = intval( sanitize_text_field( $form['expires'] ) ) ) && 0 < $e ) {
			$extend_by = $e;
		}

		$time_coefficients_in_seconds = array('s' => 1, 'm' => 60, 'h' => 3600, 'd' => 24*3600);
		
		if ( isset($form['measure']) && $time_coefficients_in_seconds[$form['measure']] ) {
			$time_in_seconds = $time_coefficients_in_seconds[$form['measure']];
		}

		return $extend_by * $time_in_seconds;
	}

	/*
	 * Database access 
	 */

	function get_drafts() {
		global $current_user;

		$my_drafts = get_users_drafts($current_user->id);

		$future_posts_query = array( 'post_author' => $current_user->id,
			'post_status' => array( 'future' ) );
		$future = query_posts( $future_posts_query );

		$pending_posts_query = array( 'post_author' => $current_user->id,
		    'post_status' => array( 'pending' ) );
		$pending = query_posts($pending_posts_query);

		$drafts = array(
			array(
				__('Your Drafts:', 'draftsforfriends'),
				count($my_drafts),
				$my_drafts,
			),
			array(
				__('Your Scheduled Posts:', 'draftsforfriends'),
				count($future),
				$future,
			),
			array(
				__('Pending Review:', 'draftsforfriends'),
				count($pending),
				$pending,
			),
		);

		return $drafts; 
	}

	function get_shared_drafts() {
		if ( !isset($this->user_options['shared']) ) {
			return array();
		}

		return $this->user_options['shared'];
	}

	/*
	 * Rendering
	 */

	function output_existing_menu_sub_admin_page() {

		if ( isset( $_POST['draftsforfriends_submit'] ) ) {
			$message = $this->process_share($_POST);
		} elseif ( isset($_POST['action']) && 'extend' == $_POST['action'] ) {
			$message = $this->process_extend($_POST);
		} elseif ( isset($_GET['action']) && 'delete' == $_GET['action'] ) {
			$message = $this->process_delete($_GET);
		}

		$all_drafts = $this->get_drafts();
?>
	<div id="drafts-for-friends" class="wrap">
		<h2><?php _e('Drafts for Friends', 'draftsforfriends'); ?></h2>
		
		<?php 
			if ( $message ) { 
				$class = 'updated fade';

				if( isset($_POST['error']) ) {
					$class = 'error';
				}	
		?>
				<div id="message" class="<?php echo $class; ?>"><?php echo $message; ?></div>
		<?php
			}
		?>

		<h3><?php _e('Share a New Draft', 'draftsforfriends'); ?></h3>
		<form class="share-form" action="" method="post">
		<p>
			<input type="hidden" name="nonce" value="<?php echo wp_create_nonce( 'draftsforfriends_frontend_share' ) ?>" />
			<input type="hidden" name="unique_form_id" value="<?php echo time(); ?>" />
			<select id="postid" name="post_id">
			<option value=""><?php _e('Choose a draft', 'draftsforfriends'); ?></option>
		
				<?php
				foreach( $all_drafts as $drafts_by_type ):
					$draft_posts = $drafts_by_type[0];
					$scheduled_posts = $drafts_by_type[1];
					$posts_pending_review = $drafts_by_type[2];

					if ( $scheduled_posts ):
				?>
						<option value="" disabled="disabled"></option>
						<option value="" disabled="disabled"><?php echo $draft_posts; ?></option>

						<?php
						
						foreach( $posts_pending_review as $draft_pending_review ):
							if ( empty( $draft_pending_review->post_title ) ) {
								continue;
							}
						?>
							<option value="<?php echo $draft_pending_review->ID?>">
								<?php echo wp_specialchars($draft_pending_review->post_title); ?>
							</option>
				<?php
						endforeach;
					endif;
				endforeach;
				?>
			</select>
		</p>
		<p>
			<input type="submit" class="button button-primary" name="draftsforfriends_submit"
				value="<?php _e('Share it', 'draftsforfriends'); ?>" />
			<?php _e('for', 'draftsforfriends'); ?>
			<?php echo $this->render_duration_options_dropdown(); ?>
		</p>
		</form>

		<h3><?php _e('Shared Drafts', 'draftsforfriends'); ?></h3>

		<table class="widefat">
			<thead>
			<tr>
				<th class="post-id"><?php _e('PostID', 'draftsforfriends'); ?></th>
				<th class="title"><?php _e('Title', 'draftsforfriends'); ?></th>
				<th class="url"><?php _e('Link', 'draftsforfriends'); ?></th>
				<th class="expires-after"><?php _e('Expires After', 'draftsforfriends'); ?></th>
				<th id="actions-table-header" colspan="2" class="centered"><?php _e('Actions', 'draftsforfriends'); ?></th>
			</tr>
			</thead>
			<tbody>
		<?php
			$shared_drafts = $this->get_shared_drafts();

			foreach( $shared_drafts as $shared_draft ):
				$p = get_post($shared_draft['id']);
				$url = get_bloginfo('url') . '/?p=' . $p->ID . '&draftsforfriends='. $shared_draft['key'];
		?>
				<tr id="myRow">
					<td class="post-id"><?php echo $p->ID; ?></td>

					<td class="title"><?php echo esc_html( $p->post_title) ; ?></td>

					<td class="url"><a href="<?php echo esc_url( $url ); ?>"><?php echo esc_url( $url ); ?></a></td>

					<td class="expires-after" data-draft-expiry-time="<?php echo $shared_draft['expires'] ; ?>"><i>Calculating...</i></td>

					<td id="inline-extend-actions-<?php echo $shared_draft['key']; ?>" class="centered">
						<a class="extend-toggle" id="expand-<?php echo $shared_draft['key']; ?>" data-key="<?php echo $shared_draft['key']; ?>" href="#">
								<?php _e('Extend', 'draftsforfriends'); ?>
						</a>
						<form class="extend-form" id="extend-form-<?php echo $shared_draft['key']; ?>"
							action="" method="post">	
							<input type="hidden" name="nonce" value="<?php echo wp_create_nonce( 'draftsforfriends_frontend_extend' ) ?>" />
							<input type="hidden" name="unique_form_id" value="<?php echo time(); ?>" />
							<input type="hidden" name="action" value="extend" />
							<input type="hidden" name="key" value="<?php echo $shared_draft['key']; ?>" />
							<input type="submit" class="button button-primary" name="draftsforfriends_extend_submit"
								value="<?php _e('Extend', 'draftsforfriends'); ?>"/>
							<?php _e('by', 'draftsforfriends');?>
							<?php echo $this->render_duration_options_dropdown(); ?>				
							<a class="warning extend-toggle" data-key="<?php echo $shared_draft['key']; ?>" href="#">
								<?php _e('Cancel', 'draftsforfriends'); ?>
							</a>
						</form>
					</td>

					<td class="delete-link-table-cell centered">
						<?php $this->render_delete_link( $shared_draft ); ?>
						
					</td>
				</tr>
		<?php
			endforeach;

			if ( empty( $shared_drafts ) ):
		?>
				<tr>
					<td colspan="5"><?php _e('No shared drafts!', 'draftsforfriends'); ?></td>
				</tr>
		<?php
				endif;
		?>
			</tbody>
		</table>

		</div>

<?php
	}

	function render_delete_link( $shared_draft ) {
		$delete_nonce = wp_create_nonce( 'draftsforfriends_frontend_delete' );
?>
		<a class="danger"
			href="edit.php?page=<?php echo plugin_basename(__FILE__); 
			?>&amp;action=delete&amp;key=<?php echo $shared_draft['key']; 
			?>&amp;nonce=<?php echo $delete_nonce; ?>">
			<?php _e('Delete', 'draftsforfriends'); ?>
		</a>
<?php

	}

	function render_duration_options_dropdown() {
		$secs = __('seconds', 'draftsforfriends');
		$mins = __('minutes', 'draftsforfriends');
		$hours = __('hours', 'draftsforfriends');
		$days = __('days', 'draftsforfriends');

		return <<<SELECT
			<input name="expires" type="text" value="2" size="2"/>
			<select name="measure">
				<option value="s">$secs</option>
				<option value="m">$mins</option>
				<option value="h" selected="selected">$hours</option>
				<option value="d">$days</option>
			</select>
SELECT;
	}

	/*
	 * Hooks
	 */

	function the_posts_intercept( $posts ){
		if ( empty( $posts ) && ! is_null( $this->shared_post ) ) {
			return array( $this->shared_post );
		} else {
			$this->shared_post = null;

			return $posts;
		}
	}

	function posts_results_intercept( $posts ) {
		if ( 1 != count( $posts ) || ! isset($posts[0]) ) {
			return $posts;
		}

		$post = $posts[0];
		$status = get_post_status($post);

		if ( 'publish' != $status && $this->can_view($post->ID) ) {
			$this->shared_post = $post;
		}

		return $posts;
	}

	function can_view( $post_id ) {
		if ( isset( $_GET['draftsforfriends'] ) ) {

			foreach( $this->admin_options as $option ) {
				$shares = $option['shared'];
				
				foreach( $shares as $share ) {
					if ( $share['key'] == $_GET['draftsforfriends'] && $share['id'] == $post_id ) {
						return true;
			  		}
				}
			}
		}

		return false;
	}
}

endif;

if ( class_exists( 'DraftsForFriends' ) ) {
	new draftsforfriends();
}


