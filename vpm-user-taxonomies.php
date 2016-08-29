<?php
/*
Plugin Name: VPM User Taxonomies
Plugin URI: https://vanpattenmedia.com/
Author: Chris Van Patten & Peter Shaw
Author URI: https://vanpattenmedia.com/
Description: Simplify the process of adding support for custom taxonomies for Users. Just use `register_taxonomy` and everything else is taken care of. Based on work by Peter Shaw and Justin Tadlock.
Version: 2.0.0
*/

class VPM_User_Taxonomies {

	private static $taxonomies = [];

	var $namespace = 'vpm_user_taxonomies';

	/**
	 * Register all the hooks and filters we can in advance
	 * Some will need to be registered later on, as they require knowledge of the taxonomy name
	 */
	public function __construct() {
		// Taxonomies
		add_action('registered_taxonomy', array($this, 'registered_taxonomy'), 10, 3);

		// Menus
		add_action('admin_menu', array($this, 'admin_menu'));
		add_filter('parent_file', array($this, 'parent_menu'));

		// User Profiles
		add_action('show_user_profile',	array($this, 'user_profile'));
		add_action('edit_user_profile',	array($this, 'user_profile'));
		add_action('personal_options_update', array($this, 'save_profile'));
		add_action('edit_user_profile_update', array($this, 'save_profile'));
		add_action('user_register', array($this, 'save_profile'));
		add_filter('sanitize_user', array($this, 'restrict_username'));
		add_action('pre_user_query', array($this, 'user_query'));
	}

	/**
	 * This is our way into manipulating registered taxonomies
	 * It's fired at the end of the register_taxonomy function
	 *
	 * @param String $taxonomy	- The name of the taxonomy being registered
	 * @param String $object	- The object type the taxonomy is for; We only care if this is "user"
	 * @param Array $args		- The user supplied + default arguments for registering the taxonomy
	 */
	public function registered_taxonomy($taxonomy, $object, $args) {
		global $wp_taxonomies;

		// Reject anything not tied to a user
		if ( $object !== 'user' && ! is_array($object) )
			return;

		// Reject anything not tied to a user
		if ( is_array($object) && ! in_array('user', $object) )
			return;

		// We're given an array, but expected to work with an object later on
		$args = (object) $args;

		// Register any hooks/filters that rely on knowing the taxonomy now
		add_filter("manage_edit-{$taxonomy}_columns", array($this, 'set_user_column'));
		add_action("manage_{$taxonomy}_custom_column", array($this, 'set_user_column_values'), 10, 3);

		// Set the callback to update the count if not already set
		if(empty($args->update_count_callback)) {
			$args->update_count_callback = array($this, 'update_count');
		}

		// We're finished, make sure we save out changes
		$wp_taxonomies[$taxonomy] = $args;
		self::$taxonomies[$taxonomy] = $args;
	}

	/**
	 * We need to manually update the number of users for a taxonomy term
	 *
	 * @see _update_post_term_count()
	 * @param Array $terms - List of Term taxonomy IDs
	 * @param Object $taxonomy - Current taxonomy object of terms
	 */
	public function update_count($terms, $taxonomy) {
		global $wpdb;

		foreach((array) $terms as $term) {
			$count = $wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM $wpdb->term_relationships, $wpdb->users WHERE $wpdb->term_relationships.object_id = $wpdb->users.ID and $wpdb->term_relationships.term_taxonomy_id = %d", $term));

			do_action('edit_term_taxonomy', $term, $taxonomy);
			$wpdb->update($wpdb->term_taxonomy, compact('count'), array('term_taxonomy_id'=>$term));
			do_action('edited_term_taxonomy', $term, $taxonomy);
		}
	}

	/**
	 * Add each of the taxonomies to the Users menu
	 * They will behave in the same was as post taxonomies under the Posts menu item
	 * Taxonomies will appear in alphabetical order
	 */
	public function admin_menu() {
		// Put the taxonomies in alphabetical order
		$taxonomies = self::$taxonomies;
		ksort($taxonomies);

		foreach($taxonomies as $key=>$taxonomy) {
			add_users_page(
				$taxonomy->labels->menu_name,
				$taxonomy->labels->menu_name,
				$taxonomy->cap->manage_terms,
				"edit-tags.php?taxonomy={$key}"
			);
		}
	}

	/**
	 * Fix a bug with highlighting the parent menu item
	 * By default, when on the edit taxonomy page for a user taxonomy, the Posts tab is highlighted
	 * This will correct that bug
	 */
	function parent_menu($parent = '') {
		global $pagenow;

		// If we're editing one of the user taxonomies
		// We must be within the users menu, so highlight that

		// If we're editing one of the user taxonomies, we need to highlight the right menu
		if ( !isset($_GET['taxonomy']) || empty($_GET['taxonomy']) )
			return $parent;

		if ( isset($_GET['post_type']) && $_GET['post_type'] !== 'user' )
			return $parent;

		if ( $pagenow !== 'edit-tags.php' )
			return $parent;

		if ( ! isset(self::$taxonomies[$_GET['taxonomy']]) )
			return $parent;

		return $parent;
	}

	/**
	 * Correct the column names for user taxonomies
	 * Need to replace "Posts" with "Users"
	 */
	public function set_user_column($columns) {

		if ( isset($_GET['post_type']) )
			return $columns;

		if ( ! empty($_GET['post_type']) )
			return $columns;

		unset($columns['posts']);
		$columns['users'] = __('Users');
		return $columns;
	}

	/**
	 * Set values for custom columns in user taxonomies
	 */
	public function set_user_column_values($display, $column, $term_id) {
		if('users' === $column) {
			$term = get_term($term_id, $_REQUEST['taxonomy']);
			echo $term->count;
		}
	}

	private function buildTree( array &$elements, $parentId = 0 ) {
	    $branch = array();
	    foreach ($elements as $element) {
	        if ($element->parent == $parentId) {
	            $children = $this->buildTree($elements, $element->term_id);
	            if ($children) {
	                $element->children = $children;
	            }
	            $branch[$element->term_id] = $element;
	            unset($elements[$element->term_id]);
	        }
	    }
	    return $branch;
	}

	private function renderTree( $elements, $stack, $user, $key ) {
		foreach ( $elements as $element ) {
			?>
			<div>
				<input type="checkbox" name="<?php echo $key?>[]" id="<?php echo "{$key}-{$element->slug}"?>" value="<?php echo $element->slug?>" <?php
				if ($user->ID){
					if (in_array($element->slug, $stack)) {
						echo "checked=\"checked\"";
					}
				}
				?> />
				<label for="<?php echo "{$key}-{$element->slug}"?>"><?php echo $element->name ?></label>
				<?php if( isset( $element->children ) ) {
					?><div style="padding-left: 24px;"><?php
						$this->renderTree( $element->children, $stack, $user, $key );
					?></div><?php
				}
			?></div><?php
	    }
	}
	/**
	 * Add the taxonomies to the user view/edit screen
	 *
	 * @param Object $user The user of the view/edit screen
	 */
	public function user_profile($user) {

		if ( empty(self::$taxonomies) )
			echo '<h3>' . __('Taxonomies') . '</h3>';

		foreach (self::$taxonomies as $key => $taxonomy) :

			// Check the current user can assign terms for this taxonomy
			// TODO why is this commented out
			// if(!current_user_can($taxonomy->cap->assign_terms)) continue;

			// Get all the terms in this taxonomy
			$terms = get_terms($key, array('hide_empty'=>false));
			$stack = wp_list_pluck( wp_get_object_terms( $user->ID, $key ), 'slug' );
			?>
			<table class="form-table">
				<tr>
					<th><label for=""><?php _e("Select {$taxonomy->labels->singular_name}")?></label></th>
					<td>
						<?php
						if(!empty($terms)) {
							$this->renderTree( $this->buildTree( $terms ), $stack, $user, $key );
						} else {
							_e("There are no {$taxonomy->name} available.");
						}
						?>
					</td>
				</tr>
			</table>
		<?php endforeach;
	}

	/**
	 * Save the custom user taxonomies when saving a users profile
	 *
	 * @param Integer $user_id The ID of the user to update
	 */
	public function save_profile($user_id) {
		foreach(self::$taxonomies as $key => $taxonomy) {
			// Check the current user can edit this user and assign terms for this taxonomy
			if (current_user_can('edit_user', $user_id) && current_user_can($taxonomy->cap->assign_terms)) {

				if (is_array($_POST[$key])){
					$term = $_POST[$key];
					wp_set_object_terms($user_id, $term, $key, false);
				} else {
					$term = esc_attr($_POST[$key]);
					wp_set_object_terms($user_id, array($term), $key, false);
				}

				// Save the data
				clean_object_term_cache($user_id, $key);
			}
		}
	}

	/**
	 * Usernames can't match any of our user taxonomies as it will cause a URL conflict.
	 * This method prevents that from happening.
	 */
	public function restrict_username($username) {
		if (isset(self::$taxonomies[$username]))
			return;

		return $username;
	}

	/**
	 * Get terms for a user and a taxonomy
	 *
	 * @since 0.1.0
	 *
	 * @param  mixed  $user
	 * @param  int    $taxonomy
	 *
	 * @return boolean
	 */
	private function get_terms_for_user( $user = false, $taxonomy = '' ) {
		// Verify user ID
		$user_id = is_object( $user ) ? $user->ID : absint( $user );

		// Bail if empty
		if ( empty( $user_id ) )
			return false;

		// Return user terms
		return wp_get_object_terms( $user_id, $taxonomy, array(
			'fields' => 'all_with_object_id'
		) );
	}

	/**
	 * Set the terms for a user
	 */
	private function set_terms_for_user( $user_id, $taxonomy, $terms = array(), $bulk = false ) {
		// Get the taxonomy
		$tax = get_taxonomy( $taxonomy );

		// Make sure the current user can edit the user and assign terms before proceeding.
		if ( ! current_user_can( 'edit_user', $user_id ) && current_user_can( $tax->cap->assign_terms ) ) {
			return false;
		}

		if ( empty( $terms ) && empty( $bulk ) )
			$terms = isset( $_POST[$taxonomy] ) ? $_POST[$taxonomy] : null;

		// Delete all user terms
		if ( is_null( $terms ) || empty( $terms ) ) {

			wp_delete_object_term_relationships( $user_id, $taxonomy );

		// Set the terms
		} else {
			$_terms = array_map( 'sanitize_key', $terms );

			// Sets the terms for the user
			wp_set_object_terms( $user_id, $_terms, $taxonomy, false );
		}

		// Clean the cache
		clean_object_term_cache( $user_id, $taxonomy );
	}

	/**
	 * Alters the user query to return a different list based on
	 * query vars on users.php or if a tax_query is set
	 */
	public function user_query( $query = '' ) {
		global $pagenow, $wpdb;

		// Handle the tax query
		if ( isset( $query->query_vars['tax_query'] ) && is_array( $query->query_vars['tax_query'] ) ) {
			$sql = get_tax_sql( $query->query_vars['tax_query'], $wpdb->prefix . 'users', 'ID' );

			if ( isset( $sql['join'] ) ){
				$query->query_from .= $sql['join'];
			}

			if ( isset( $sql['where'] ) ){
				$query->query_where .= $sql['where'];
			}

			return;
		}

		// Handle the user pages
		// TODO can probably improve this
		if ( $pagenow == 'users.php' ) {
			$args = array(
				'object_type' => array('user'),
				'show_admin_column' => true
			);

			$taxonomies = get_taxonomies( $args, "objects");

			foreach ($taxonomies as $taxonomy) {
				if(!empty($_GET[$taxonomy->name])) {
					$term = get_term_by('slug', esc_attr($_GET[$taxonomy->name]), $taxonomy->name);
					$new_ids = get_objects_in_term($term->term_id, $taxonomy->name);

					if (!isset($ids) || empty($ids)){
						$ids = $new_ids;
					} else {
						$ids = array_intersect($ids, $new_ids);
					}
				}
			}

			if ( isset( $ids ) ) {
				$ids = implode(',', wp_parse_id_list( $ids ) );
				$query->query_where .= " AND $wpdb->users.ID IN ($ids)";
			}
		}
	}

}

new VPM_User_Taxonomies;
