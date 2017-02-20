<?php
/*
Plugin Name: Curated Posts
Plugin URI: http://wordpress.org/plugins/curated-posts/
Description: Build lists of curated posts to show on different sections on your website.
Author: Baki Goxhaj
Version: 1.0
Author URI: http://wplancer.com/
*/

if ( ! defined( 'ABSPATH' ) ) exit;

class Curated_Posts {

	/**
	 * Curated Posts Constructor.
	 * @access public
	 */
	public function __construct() {

		// Hook up
		add_action( 'init', array( $this, 'init' ) );
		add_action( 'init', array( $this, 'register_post_type' ) );
		add_action( 'init', array( $this, 'register_shortcode' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'action_links' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 10 );
		add_action( 'save_post', array( $this, 'save_meta_boxes' ), 1, 2 );
		add_filter( 'post_updated_messages', array( $this, 'post_updated_messages' ) );
		add_filter( 'widget_text', array( $this, 'widget_text' ) );
	}

	/**
	 * Init plugin when WordPress Initialises.
	 */
	public function init() {

  		// Define constants
		$this->define_constants();
		
		// Set up localisation
		$this->load_plugin_textdomain();
	}

	/**
	 * Define constants
	*/
	private function define_constants() {
		if ( !defined( 'CURATED_POSTS_VERSION' ) )
			define( 'CURATED_POSTS_VERSION', '1.0' );

		if ( !defined( 'CURATED_POSTS_URL' ) )
			define( 'CURATED_POSTS_URL', plugin_dir_url( __FILE__ ) );

		if ( !defined( 'CURATED_POSTS_DIR' ) )
			define( 'CURATED_POSTS_DIR', plugin_dir_path( __FILE__ ) );
	}

	/**
	 * Load Localisation files.
	 *
	 * Note: the first-loaded translation file overrides any following ones if the same translation is present
	 */
	public static function load_plugin_textdomain() {
		$locale = apply_filters( 'plugin_locale', get_locale(), 'curated' );

		load_plugin_textdomain( 'curated', false, plugin_basename( dirname( __FILE__ ) . "/lang" ) );
	}

	/**
	 * Show action links on the plugin screen.
	 *
	 * @param mixed $links
	 * @return array
	 */
	public function action_links( $links ) {
		return array_merge( array(
			'<a href="' . admin_url( 'edit.php?post_type=curated_posts' ) . '">' . __( 'Manage', 'curated' ) . '</a>',
		), $links );
	}

	/**
	 * Add menu item
	 */
	public static function register_post_type() {
		register_post_type( 'curated_posts',
			array(
				'labels' => array(
					'name' 					=> __( 'Curated Posts', 'curated' ),
					'singular_name' 		=> __( 'Curated Post', 'curated' ),
					'add_new_item' 			=> __( 'Add New Curated Posts', 'curated' ),
					'edit_item' 			=> __( 'Edit Curated Posts', 'curated' ),
					'new_item' 				=> __( 'New Curated Posts', 'curated' ),
					'view_item' 			=> __( 'View Curated Posts', 'curated' ),
					'search_items' 			=> __( 'Search Curated Posts', 'curated' ),
					'not_found' 			=> __( 'No curated posts found.', 'curated' ),
					'not_found_in_trash' 	=> __( 'No curated posts found in trash.', 'curated' ),
				),
				'public' 				=> false,
				'show_ui' 				=> true,
				'capability_type' 		=> 'post',
				'map_meta_cap' 			=> true,
				'publicly_queryable' 	=> false,
				'exclude_from_search' 	=> true,
				'hierarchical' 			=> false,
				'rewrite' 				=> array( 'slug' => 'group' ),
				'supports' 				=> array( 'title' ),
				'has_archive' 			=> false,
				'show_in_nav_menus' 	=> false,
				'show_in_admin_bar' 	=> false,
				'menu_icon' 			=> 'dashicons-thumbs-up',
			)
		);
	}

	/**
	 * Add menu item
	 */
	public function register_shortcode() {
		add_shortcode( 'curated_posts', array( $this, 'shortcode' ) );
	}

	/**
	 * Enqueue admin scripts
	 */
	public static function admin_scripts() {
		$screen = get_current_screen();

		if ( 'curated_posts' == $screen->id ):
			wp_enqueue_style( 'select2', CURATED_POSTS_URL . 'assets/css/select2.min.css', array(), '4.0.3' );
			wp_enqueue_style( 'curated', CURATED_POSTS_URL . 'assets/css/curated.css', array(), CURATED_POSTS_VERSION );
	    	wp_enqueue_script( 'jquery-ui-sortable' );
			wp_enqueue_script( 'select2', CURATED_POSTS_URL . 'assets/js/select2.min.js', array( 'jquery' ), '4.0.3', true );
	    	wp_enqueue_script( 'curated', CURATED_POSTS_URL . 'assets/js/curated.js', array( 'jquery', 'jquery-ui-sortable', 'select2' ), CURATED_POSTS_VERSION, true );
		endif;
	}

	/**
	 * Add meta boxes
	 */
	public function add_meta_boxes() {
		add_meta_box( 'curated_posts_box', __( 'Posts', 'curated' ), array( $this, 'posts_meta_box' ), 'curated_posts', 'advanced', 'high' );
		add_meta_box( 'curated_usage_box', __( 'Usage', 'curated' ), array( $this, 'usage_meta_box' ), 'curated_posts', 'side', 'default' );
	}

	/**
	 * Posts meta box
	 */
	public static function posts_meta_box( $post, $args ) {
	    // This is checked on save method
		wp_nonce_field( 'curated_save_data', 'curated_meta_nonce' );
	    
	    $curated_posts = get_post_meta( $post->ID, 'curated_posts' );
		?>

        <p>
            <select name="add_curated_posts" id="add_curated_posts" class="js-data-example-ajax">
                <option value="3620194" selected="selected">Select posts to add to curated list below:</option>
            </select>        
        </p>
		
		<table class="widefat curated-posts-table">
			<thead>
				<tr>
					<th style="width:1px;">&nbsp;</th>
					<th><?php _e( 'Title', 'curated' ); ?></th>
					<th style="width:20%;"><?php _e( 'Published', 'curated' ); ?></th>
					<th style="width:1px;">&nbsp;</th>
				</tr>
			</thead>
			<tbody>
				<tr class="curated-placeholder"<?php if ( sizeof( $curated_posts ) ): ?> style="display:none;"<?php endif; ?>>
					<td colspan="4">
						<?php _e( 'No posts found.', 'curated' ); ?>
						<?php _e( 'Use the menu above to add posts to this list.', 'curated' ); ?>
					</td>
				</tr>
				<?php if ( sizeof( $curated_posts ) ): foreach ( $curated_posts as $post_id ): ?>
					<tr>
						<td class="icon"><span class="dashicons dashicons-menu post-state-format"></span></td>
						<td><input type="hidden" name="curated_posts[]" value="<?php echo $post_id; ?>"><?php echo get_the_title( $post_id ); ?></td>
						<td><?php echo get_the_date( 'j F Y', $post_id ); ?></td>
						<td><a href="#" class="dashicons dashicons-no-alt curated-delete"></a></td>
					</tr>
				<?php endforeach; endif; ?>
			</tbody>
		</table>
		<p style="overflow:hidden">
			<span class="howto alignleft"><?php _e( 'Drag and drop to reorder posts.', 'curated' ); ?></span>
			<span class="credits alignright">
    			<?php _e( 'Made with <i aria-label="love" class="heart">&#10084;</i> by ', 'curated' ); ?>
				<a href="https://twitter.com/banago" target="_blank">@banago</a>
            </span>			
		</p>
		<?php
	}

	/**
	 * Shortcode meta box
	 */
	public static function usage_meta_box( $post ) {
		$post_IDs = get_post_meta( $post->ID, 'curated_posts' );
		?>
		<p class="howto">
			<?php _e( '1. Copy this code and paste it into your post, page or text widget content.', 'curated' ); ?>
		</p>
		<p><input type="text" value="[curated_posts <?php echo $post->ID; ?>]" readonly="readonly" class="code"></p>

		<p class="howto">
			<?php _e( '2. Copy the IDs and paste them into your latest post widget of choice.', 'curated' ); ?>
		</p>
		<p><input type="text" value="<?php echo implode(',', $post_IDs); ?>" readonly="readonly" class="code"></p>

		<p class="howto">
			<?php _e( '3. Use the PHP function below to get the IDs in a custom loop in your theme.', 'curated' ); ?>
		</p>
		<p><input type="text" value="get_curated_ids(<?php echo $post->ID; ?>)" readonly="readonly" class="code"></p>

		<?php
	}

	/**
	 * Check if we're saving, then trigger an action based on the post type
	 *
	 * @param  int $post_id
	 * @param  object $post
	 */
	public static function save_meta_boxes( $post_id, $post ) {
		if ( empty( $post_id ) || empty( $post ) ) return;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
		if ( is_int( wp_is_post_revision( $post ) ) ) return;
		if ( is_int( wp_is_post_autosave( $post ) ) ) return;
		if ( empty( $_POST['curated_meta_nonce'] ) || ! wp_verify_nonce( $_POST['curated_meta_nonce'], 'curated_save_data' ) ) return;
		if ( ! current_user_can( 'edit_post', $post_id  ) ) return;
		if ( 'curated_posts' != $post->post_type ) return;


		// Delete old
		delete_post_meta( $post_id, 'curated_posts' );
        // Save new		
		if ( isset( $_POST['curated_posts'] ) && is_array ( $_POST['curated_posts'] ) ):
			foreach ( $_POST['curated_posts'] as $value ):
				add_post_meta( $post_id, 'curated_posts', $value );
			endforeach;
		endif;
	}

	/**
	 * Remove link from post updated messages
	 */
	public static function post_updated_messages( $messages ) {
		global $typenow, $post;

		if ( 'curated_posts' == $typenow ):
			for ( $i = 0; $i <= 10; $i++ ):
				$messages['post'][ $i ] = '<strong>' . __( 'Curated posts saved.', 'curated' ) . '</strong>';
			endfor;
		endif;

		return $messages;
	}

	/**
	 * Shortcode content
	 */
	public static function shortcode( $atts ) {
		// Get shortcode attributes
		if ( ! is_array( $atts ) || ! sizeof( $atts ) ) return;

		// Get group ID
		$id = array_shift( $atts );

		// Get group post ids
		$post_IDs = get_curated_ids($id);
		
		if ( ! $post_IDs || ! is_array( $post_IDs ) || ! sizeof( $post_IDs ) ) return;

		/* Get current group post
		if ( isset( $_REQUEST['curated_posts'] ) && in_array( $_REQUEST['curated_posts'], $post_IDs ) )
			$current = $_REQUEST['curated_posts'];
		else
			$current = reset( $post_IDs );
        */
        
        // Loop through posts
		global $post;
		$content = '<ul class="curated-posts" id="curated-posts-' . $id . '">';
		$query = new WP_Query( array( 'post__in' => $post_IDs, 'post_type' => 'any', 'orderby' => 'post__in', 'posts_per_page' => -1 ) );
		while ( $query->have_posts() ): $query->the_post();
			$content .= '<li class="curated-post" id="curated-post-' . $post->ID . '">';
			$content .= '<a href="'. get_permalink() .'" title="'. get_the_title() .'">'.  get_the_title() .'</a>';
			$content .= '</li>';
		endwhile;

		wp_reset_query();
		$content .= '</ul>';

		// Return output
		return $content;
	}

	/**
	 * Do shortcode in widgets
	 */
	public static function widget_text( $content ) {
		if ( ! preg_match( '/\[[\r\n\t ]*(curated_posts)?[\r\n\t ].*?\]/', $content ) )
			return $content;

		$content = do_shortcode( $content );

		return $content;
	}
	
}

/**
 * API: Get curated posts IDs for this list
 *
 * @return array
 */
function get_curated_ids($id){
    return get_post_meta($id, 'curated_posts');
}

new Curated_Posts();
