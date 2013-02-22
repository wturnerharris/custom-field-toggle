<?php /*

**************************************************************************

Plugin Name:  Custom Field Toggle
Plugin URI:   http://www.witdesigns.com/plugins/wordpress/custom-field-toggle/
Description:  Allows you to create a toggle for a specific custom field.
Version:      1.0
Author:       Wes Turner
Author URI:   http://www.witdesigns.com/

**************************************************************************

Copyright (C) 2008-2012  Wes Turner

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License, version 2, as 
published by the Free Software Foundation.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA


**************************************************************************/

class CustomFieldToggle {
	var $menu_id;

	function __construct() {
		global $wpdb;
		if ( ! function_exists( 'admin_url' ) )
			return false;

		// Load up the localization file if we're using WordPress in a different language
		// Place it in this plugin's "localization" folder and name it "custom-field-toggle-[value in wp-config].mo"
		load_plugin_textdomain( 'custom-field-toggle', false, '/custom-field-toggle/localization' );

		add_action( 'admin_menu',                        array( &$this, 'add_admin_menu' ) );
		add_action( 'admin_enqueue_scripts',             array( &$this, 'admin_enqueues' ) );
		add_action( 'wp_ajax_toggle_option',             array( &$this, 'ajax_toggle_option' ) );
		add_action( 'add_meta_boxes',                    array( &$this, 'add_toggle_meta' ), 10, 2 );
		
		if ( ! defined( 'CFT_TABLE' ) ) define( 'CFT_TABLE', $wpdb->prefix . 'cft_toggles' );
		
	}
	
	// install db table
	function install_cftoggles(){
		global $wpdb;
		$cft_db_version = "1.0";
		
		$table_name = $wpdb->prefix . "cft_toggles";
		
		$sql = "CREATE TABLE " . $table_name . " (
			id mediumint(9) NOT NULL AUTO_INCREMENT,
			title varchar(20) DEFAULT '',
			type longtext,
			field VARCHAR(20) DEFAULT '',
			post_type VARCHAR(20) DEFAULT '',
			post_id longtext,
			UNIQUE KEY id (id)
		);";

		require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
		dbDelta($sql);
		
		add_option("cft_db_version", $cft_db_version);
		
	}
	
	// Register the management page
	function add_admin_menu() {
		$this->menu_id = add_menu_page( 
			__( 'Custom Field Toggle', 'custom-field-toggle' ), 
			__( 'Custom Toggles', 'custom-field-toggle' ), 
			'manage_options', 
			'custom-field-toggle', 
			array(&$this, 'cftoggle_interface') 
		);
	}

	// Enqueue the needed Javascript and CSS
	function admin_enqueues( $hook_suffix ) {
		if ( $hook_suffix == 'post-new.php' || $hook_suffix == 'post.php' || $hook_suffix == $this->menu_id ) :
			wp_enqueue_script( 
				'custom-field-toggle', 
				plugins_url( 'custom-field-toggle.js', __FILE__ ), 
				array( 'jquery' ), 
				'1.4.3' 
			);
			wp_enqueue_style( 
				'custom-field-toggle', 
				plugins_url( 'custom-field-toggle.css', __FILE__ ), 
				array(), 
				'1.0' 
			);
		endif;
		return;
	}

	// Add meta box to post/pages
	function add_toggle_meta($type, $post) {
		global $wpdb;
				
		// first get the registered options
		$options = $wpdb->get_results( sprintf( "SELECT * FROM %s", CFT_TABLE ) );
		
		foreach($options as $key) {
			$post_ids = is_numeric($key->post_id) ? $key->post_id : unserialize($key->post_id);
			$type = unserialize($key->type);
			if ( $post_ids == $post->ID || $post_ids == 0 || is_array($post_ids) && in_array($post->ID, $post_ids) ) {
				add_meta_box( 
					'custom-field-toggle',
					$key->title,
					array(&$this, 'toggle_inner_html'),
					$key->post_type,
					'side',
					'high',
					array('field' => $key->field, 'class' => $type['class'])
				);
			}
		}
	}
	
	// insert toggle html
	function toggle_inner_html( $post, $args ) {
		// get state and boolean from custom field. now defaults to on/off style and off state
		$field = $args['args']['field'];
		$post_id = $post->ID;
		
		$state = get_post_meta($post->ID, $field, true);
		$state = ((bool) $state ? 'on' : 'off');
		$type = $args['args']['class'];
		
		// The actual fields for data entry 
		?>
		<div class='full-width'>
			<input id="cft-<?php echo $field; ?>" type="hidden" value="<?php echo $field; ?>" name="cft_field" />
			<a href='javascript:void(0);' class='ui-toggle ui-state-<?php echo $state; ?> ui-toggle-<?php echo $type; ?>'>Toggle</a>
		</div>
		<?php
	}

	// update toggle options
	function cftoggle_update($input){
		global $wpdb; 

		$pid = $input['post_ids'];
		if ($pid == 0 || $pid == 'any' || empty($pid)) {
			$post_ids = 0;
		} else {
			$post_ids = is_numeric($pid) ? array($pid) : explode( ',', preg_replace('/\s/','',$pid) );
			foreach($post_ids as $key => $val) {
				if ( preg_replace('/[a-zA-Z]/','',$val) != $val) unset($post_ids[$key]);
			}
			$post_ids = is_array($post_ids) ? serialize( $post_ids ) : 0;
		}
		
		$sql_update = array( 
			'title' => $input['title'], 
			'type' => serialize( array('name' => $input['type_name'], 'class' => $input['type_class']) ),
			'field' => $input['field'],
			'post_type' => !empty($input['post_type']) ? $input['post_type'] : 'post',
			'post_id' => $post_ids
		);
		
		$update = $wpdb->update( 
			CFT_TABLE, 
			$sql_update,
			array( 'id' => $input['toggle'] ),
			array('%s','%s','%s','%s','%s'),
			array('%d')
		);
		return ($update ? 'Toggle Updated!' : 'No changes made!');
	}
	
	// insert new toggle
	function cftoggle_insert($input){
		global $wpdb; 

		$pid = $input['post_ids'];
		if ($pid == 0 || $pid == 'any' || empty($pid)) {
			$post_ids = 0;
		} else {
			$post_ids = is_numeric($pid) ? array($pid) : explode( ',', preg_replace('/\s/','',$pid) );
			foreach($post_ids as $key => $val) {
				if ( preg_replace('/[a-zA-Z]/','',$val) != $val) unset($post_ids[$key]);
			}
			$post_ids = is_array($post_ids) ? serialize( $post_ids ) : 0;
		}

		$insert = $wpdb->insert( 
			CFT_TABLE, 
			array( 
				'title' => $input['title'], 
				'type' => serialize(array('name' => $input['type_name'], 'class' => $input['type_class'])),
				'field' => $input['field'],
				'post_type' => !empty($input['post_type']) ? $input['post_type'] : 'post',
				'post_id' => $post_ids
			)
		);
		return ($insert ? 'Toggle Added!' : 'There was a problem adding this toggle.');
	}
	
	// remove toggle
	function cftoggle_remove($input){
		global $wpdb;
		$toggle_id = $input['toggle'];
		$delete = $wpdb->query( sprintf( "DELETE FROM %s WHERE id = %s", CFT_TABLE, $toggle_id ) );
		return ($delete ? 'Toggle Removed!' : 'There was a problem removing this toggle.');
	}
	
	// The user interface to add/edit/delete toggles
	function cftoggle_interface() {
		global $wpdb; 
	?>
	<div class="wrap">
		<h2><?php _e('Custom Field Toggle', 'custom-field-toggle'); ?></h2>
		<?php 
		
		$action = isset($_REQUEST['action']) ? $_REQUEST['action'] : false;
		
		switch ($action) {
			case "new":
				$message = $this->cftoggle_insert($_REQUEST);
				$this->cftoggle_default_form($message);
				break;
			case "update":
				$message = $this->cftoggle_update($_REQUEST);
				$this->cftoggle_default_form($message);
				break;
			case "remove":
				$message = $this->cftoggle_remove($_REQUEST);
				$this->cftoggle_default_form($message);
				break;
			case "edit":
			case "add":
				// Capability check
				if ( !current_user_can( 'manage_options' ) )
					wp_die( __( 'Cheatin&#8217; uh?' ) );

				// Form nonce check
				check_admin_referer( 'custom-field-toggle' );

				$toggle_id = (isset($_REQUEST['toggle']) && "edit" == $action ? $_REQUEST['toggle'] : 0);
				$toggles = $wpdb->get_results( sprintf("SELECT * FROM %s WHERE id = %s", CFT_TABLE, $toggle_id) ); 
				$toggle_obj = (count($toggles) == 1 ? $toggles[0] : false);
				$toggle = array(
					'id' => ($toggle_obj ? $toggle_obj->id : ''),
					'title' => ($toggle_obj ? $toggle_obj->title : ''),
					'type' => ($toggle_obj ? $toggle_obj->type : ''),
					'field' => ($toggle_obj ? $toggle_obj->field : ''),
					'post_type' => ($toggle_obj ? $toggle_obj->post_type : ''),
					'post_ids' => ($toggle_obj ? (is_numeric($toggle_obj->post_id) ? ($toggle_obj->post_id > 0 ? $toggle_obj->post_id : '') : implode(',',@unserialize($toggle_obj->post_id))) : '' )
				);
				$toggle = (object) $toggle;

				// Show toggle edit form or new toggle form
				if ( $toggle_id ) : ?>

			<h3>Edit Toggle</h3>
			<form id="ToggleForm" name="edit_toggle" method="post" action="">
				<input type="hidden" value="<?php echo $toggle->id; ?>" name="toggle">
				<input type="hidden" value="update" name="action">

				<?php else : ?>

			<h3>New Toggle</h3>
			<form id="ToggleForm" name="new_toggle" method="post" action="">
				<input type="hidden" value="new" name="action">

				<?php endif; ?>

				<input type="hidden" value="On/Off" name="type_name" />
				<input type="hidden" value="on-off" name="type_class" />
				<?php wp_nonce_field('custom-field-toggle', '_wpnonce', false) ?>
				<table class="form-table">
					<tbody>
					<tr class="form-field form-required">
						<th scope="row"><label for="title">Title <span class="description">(required)</span></label></th>
						<td><input type="text" aria-required="true" value="<?php echo $toggle->title; ?>" id="title" name="title" placeholder="Name the meta box for your toggle" /></td>
					</tr>
					<tr class="form-field form-required">
						<th scope="row"><label for="field">Field <span class="description">(required)</span></label></th>
						<td><input type="text" aria-required="true" value="<?php echo $toggle->field; ?>" id="field" name="field" placeholder="This is the custom field key." /></td>
					</tr>
					<tr class="form-field">
						<th scope="row"><label for="post_type">Post Type <span class="description">(select)</span></label></th>
						<td><input type="text" value="<?php echo $toggle->post_type; ?>" id="post_type" name="post_type" placeholder="Default: post" /></td>
					</tr>
					<tr class="form-field">
						<th scope="row"><label for="post_ids">Post IDs <span class="description">(comma separated)</span></label></th>
						<td><input type="text" value="<?php echo $toggle->post_ids; ?>" id="post_ids" name="post_ids" placeholder="Default: 0 (any)" /></td>
					</tr>
					</tbody>
				</table>
				<p class="submit">
					<input type="submit" value="Save" class="button-primary" id="save_toggle" name="save_toggle" />
					<a class="button hide-if-no-js" href="admin.php?page=custom-field-toggle"><?php _e( 'Cancel', 'custom-field-toggle' ) ?></a>
				</p>
			</form>
			<?php 
				break;
			default : $this->cftoggle_default_form();
		}
		echo '</div>';
	}

	// print default form
	function cftoggle_default_form($message = null){
		global $wpdb;
		$nonce = wp_create_nonce('custom-field-toggle');
		$toggles = $wpdb->get_results( sprintf( "SELECT * FROM %s", CFT_TABLE ) );
	?>
	<div id="message" class="updated fade" style="display:none"><?php if (isset($message)) echo $message; ?></div>
	<form method="post" action="">
		<input type="hidden" value="add" name="action">
		<?php wp_nonce_field('custom-field-toggle') ?>
		<p><?php _e( "Use this tool to create custom toggles for the admin instead of dealing with custom field text values.", 'custom-field-toggle' ); ?></p>
		<p><?php _e( "Simply add a new toggle below and register the settings. To begin, just press the button below.", 'custom-field-toggle '); ?></p>
		<p><input type="submit" class="button hide-if-no-js" name="custom-field-toggle" id="custom-field-toggle" value="<?php _e( 'Add Toggle', 'custom-field-toggle' ) ?>" /></p>
		<noscript><p><em><?php _e( 'You must enable Javascript in order to proceed!', 'custom-field-toggle' ) ?></em></p></noscript>
	</form>
	<table cellspacing="0" class="wp-list-table widefat fixed users">
		<?php for ($i=0;$i<2;$i++) : ?>
		<<?php echo ($i==0 ? 'thead' : 'tfoot'); ?>>
			<tr>
				<th class="manage-column column-cb check-column" scope="col"><input type="checkbox" /></th>
				<th class="manage-column column-title desc" scope="col">Toggle Title</th>
				<th class="manage-column column-toggle desc" scope="col">Toggle Type</th>
				<th class="manage-column column-field desc" scope="col">Custom Field Name</th>
				<th class="manage-column column-type desc" scope="col">Post Type</th>
				<th class="manage-column column-post-id num" scope="col">Post IDs</th>
			</tr>
		</<?php echo ($i==0 ? 'thead' : 'tfoot'); ?>>
		<?php endfor; ?>
		<tbody class="list" id="the-list">
			<?php if (count($toggles) < 1) : ?>
			<tr class="alternate">
				<td colspan="6">
					No Toggles Available
				</td>
			</tr>
			<?php endif; ?>
			<?php foreach ($toggles as $toggle) : ?>
			<tr class="alternate" id="toggle-<?php echo $toggle->id; ?>">
				<th class="check-column" scope="row"><input type="checkbox" value="1" class="toggle" id="toggle_<?php echo $toggle->id; ?>" name="toggles[]"></th>
				<td class="title column-title">
					<strong>
						<a href="admin.php?page=custom-field-toggle&action=edit&toggle=<?php echo $toggle->id; ?>&_wpnonce=<?php echo $nonce; ?>">
							<?php echo $toggle->title; ?>
						</a>
					</strong><br/>
					<div class="row-actions">
						<span class="delete">
							<a href="admin.php?page=custom-field-toggle&action=remove&toggle=<?php echo $toggle->id; ?>&_wpnonce=<?php echo $nonce; ?>" 
								class="submitdelete">Delete
							</a>
						</span>
					</div>
				</td>
				<td class="ttype column-ttype"><?php $types = @unserialize($toggle->type); echo $types['name']; ?></td>
				<td class="field column-field"><?php echo $toggle->field; ?></td>
				<td class="ptype column-ptype"><?php echo $toggle->post_type; ?></td>
				<td class="pid column-post-id num">
					<?php $pids = $toggle->post_id; echo (is_numeric($pids) ? ($pids == 0 ? 'any' : $pids) : implode(',', unserialize($pids)) ); ?>
				</td>
			</tr>
			<?php endforeach; ?>
		</tbody>
	</table>
	<script type="text/javascript">
	// <![CDATA[
		jQuery(document).ready(function($){
			if ($('#message').html() != '') $('#message').show();
		});
	// ]]>
	</script>
	<?php	
	}
	
	// ajax function if necessary
	function ajax_toggle_option() {
		@error_reporting( 0 ); // Don't break the JSON result
		$post_id = $_REQUEST['post_id'];
		$key = $_REQUEST['field'];
		$val = $_REQUEST['val'];
		
		$value = update_post_meta($post_id, $key, $val);
		$new_value = get_post_meta($post_id, $key, true);
		
		header( 'Content-type: application/json' );
		echo json_encode( array( 'status' => (bool)$value , 'update' => $new_value));
		exit;
	}

}

// install db
register_activation_hook(__FILE__, array( 'CustomFieldToggle', 'install_cftoggles' ) );

// Start up this plugin
add_action( 'init', 'CustomFieldToggle' );
function CustomFieldToggle() {
	global $CustomFieldToggle;
	$CustomFieldToggle = new CustomFieldToggle();
}

?>