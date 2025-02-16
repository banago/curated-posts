<?php
/**
 * Plugin Name:       Curated Posts
 * Plugin URI:        http://wordpress.org/plugins/curated-posts/
 * Description:       Build lists of curated posts to show on different sections on your website.
 * Requires at least: 6.2
 * Requires PHP:      7.4
 * Version:           2.0.0
 * Author:            Baki Goxhaj
 * Author URI:        http://wplancer.com/
 * License:           GPLv2 or later
 * License URI:       https://www.gnu.org/licenses/gpl-3.0.html
 * Text Domain:       curated-posts
 * Domain Path:       /lang
 */


if ( ! defined( 'ABSPATH' ) )
	exit;

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
		add_action( 'rest_api_init', array( $this, 'register_rest_routes' ) );
		add_filter( 'plugin_action_links_' . plugin_basename( __FILE__ ), array( $this, 'action_links' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'admin_scripts' ) );
		add_action( 'add_meta_boxes', array( $this, 'add_meta_boxes' ), 10 );
		add_action( 'save_post', array( $this, 'save_meta_boxes' ), 1, 2 );
		add_filter( 'post_updated_messages', array( $this, 'post_updated_messages' ) );
		add_filter( 'widget_text', array( $this, 'widget_text' ) );
		add_filter( 'gettext', [ $this, 'update_excerpt_label' ], 10, 2 );
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
		if ( ! defined( 'CURATED_POSTS_VERSION' ) )
			define( 'CURATED_POSTS_VERSION', '2.0.0' );

		if ( ! defined( 'CURATED_POSTS_URL' ) )
			define( 'CURATED_POSTS_URL', plugin_dir_url( __FILE__ ) );

		if ( ! defined( 'CURATED_POSTS_DIR' ) )
			define( 'CURATED_POSTS_DIR', plugin_dir_path( __FILE__ ) );
	}

	/**
	 * Load Localisation files.
	 *
	 * Note: the first-loaded translation file overrides any following ones if the same translation is present
	 */
	public static function load_plugin_textdomain() {
		$locale = apply_filters( 'plugin_locale', get_locale(), 'curated-posts' );

		load_plugin_textdomain( 'curated-posts', false, plugin_basename( dirname( __FILE__ ) . "/lang" ) );
	}

	/**
	 * Show action links on the plugin screen.
	 *
	 * @param mixed $links
	 * @return array
	 */
	public function action_links( $links ) {
		return array_merge( array(
			'<a href="' . admin_url( 'edit.php?post_type=curated_posts' ) . '">' . __( 'Manage', 'curated-posts' ) . '</a>',
		), $links );
	}

	/**
	 * Add menu item
	 */
	public static function register_post_type() {
		register_post_type( 'curated_posts',
			array(
				'labels' => array(
					'name' => __( 'Curated Posts', 'curated-posts' ),
					'singular_name' => __( 'Curated Post', 'curated-posts' ),
					'add_new_item' => __( 'Add New Curation', 'curated-posts' ),
					'edit_item' => __( 'Edit Curated Posts', 'curated-posts' ),
					'new_item' => __( 'New Curated Posts', 'curated-posts' ),
					'view_item' => __( 'View Curated Posts', 'curated-posts' ),
					'search_items' => __( 'Search Curated Posts', 'curated-posts' ),
					'not_found' => __( 'No curated posts found.', 'curated-posts' ),
					'not_found_in_trash' => __( 'No curated posts found in trash.', 'curated-posts' ),
				),
				'public' => false,
				'show_ui' => true,
				'capability_type' => 'post',
				'map_meta_cap' => true,
				'publicly_queryable' => false,
				'exclude_from_search' => true,
				'hierarchical' => false,
				'rewrite' => array( 'slug' => 'group' ),
				'supports' => array( 'title', 'slug', 'excerpt' ),
				'has_archive' => false,
				'show_in_nav_menus' => false,
				'show_in_admin_bar' => false,
				'menu_icon' => 'dashicons-thumbs-up',
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

		if ( 'curated_posts' != $screen->id )
			return;

		// Enqueue React assets
		$asset_file = plugin_dir_path( __FILE__ ) . 'build/index.asset.php';

		if ( ! file_exists( $asset_file ) ) {
			return;
		}

		$asset = include $asset_file;

		// Get existing posts
		$post_id = get_the_ID();
		$curated_posts = $post_id ? get_post_meta( $post_id, 'curated_posts' ) : array();
		
		// Enqueue script
		wp_enqueue_script(
			'curated-posts-script',
			plugins_url( 'build/index.js', __FILE__ ),
			$asset['dependencies'],
			$asset['version'],
			array(
				'in_footer' => true,
			)
		);

		// Enqueue styles
		wp_enqueue_style(
			'curated-posts-style',
			plugins_url( 'build/style-index.css', __FILE__ ),
			array(),
			$asset['version']
		);

		// Pass data to script
		wp_localize_script(
			'curated-posts-script',
			'curatedPosts',
			array(
				'nonce' => wp_create_nonce('curated_save_data'),
				'restNonce' => wp_create_nonce('wp_rest'),
				'posts' => $curated_posts,
			)
		);
	}


	/**
	 * Add meta boxes
	 */
	public function add_meta_boxes() {
		add_meta_box( 'curated_posts_box', __( 'Posts', 'curated-posts' ), array( $this, 'posts_meta_box' ), 'curated_posts', 'advanced', 'high' );
		add_meta_box( 'curated_usage_box', __( 'Usage', 'curated-posts' ), array( $this, 'usage_meta_box' ), 'curated_posts', 'side', 'default' );
	}

	/**
	 * Posts meta box
	 */
	public static function posts_meta_box( $post, $args ) {
		// This is checked on save method
		wp_nonce_field( 'curated_save_data', 'curated_meta_nonce' );

		printf(
			'<div class="curated-posts-wrap" id="curated-posts-settings">%s</div>',
			esc_html__( 'Loading…', 'curated-posts' )
		);
	}

	/**
	 * Shortcode meta box
	 */
	public static function usage_meta_box() {
		global $post;

		$post_IDs = $post->ID ? get_post_meta( $post->ID, 'curated_posts' ) : array();
		if (!is_array($post_IDs)) {
			$post_IDs = array();
		}
		?>

		<p class="howto">
			<?php esc_html_e( '1. Copy this <code>shortcode</code> and paste it into your post, page or text widget.', 'curated-posts' ); ?>
		</p>
		<p><input type="text" value="[curated_posts <?php echo esc_attr( $post->ID ); ?>]" readonly="readonly" class="code"></p>

		<p class="howto">
			<?php esc_html_e( '2. Use the PHP function below to get the cureted posts by <code>ID</code> in a custom loop in your theme.', 'curated-posts' ); ?>
		</p>
		<p>
			<input type="text" value="get_curated_ids( <?php echo esc_attr( $post->ID ); ?> )" readonly="readonly" class="code">
		</p>

		<p class="howto">
			<?php esc_html_e( '3. Use the PHP function below to get the cureted posts by <code>slug</code> in a custom loop in your theme.', 'curated-posts' ); ?>
		</p>
		<p>
			<input type="text" value="get_curated_ids( '<?php echo esc_html( $post->post_name ); ?>' )" readonly="readonly"
				class="code">
		</p>

		<p class="howto">
			<?php esc_html_e( '4. Copy the IDs and paste them into your latest post widget of choice.', 'curated-posts' ); ?>
		</p>
		<p>
			<input type="text" value="<?php echo esc_attr( implode( ',', $post_IDs ) ); ?>" readonly="readonly" class="code">
		</p>

		<?php
	}

	/**
	 * Check if we're saving, then trigger an action based on the post type
	 *
	 * @param  int $post_id
	 * @param  object $post
	 */
	public static function save_meta_boxes( $post_id, $post ) {
		if ( empty( $post_id ) || empty( $post ) )
			return;
		if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE )
			return;
		if ( is_int( wp_is_post_revision( $post ) ) )
			return;
		if ( is_int( wp_is_post_autosave( $post ) ) )
			return;
		if ( ! current_user_can( 'edit_post', $post_id ) )
			return;
		if ( 'curated_posts' != $post->post_type )
			return;
		if ( empty( $_POST['curated_meta_nonce'] ) || ! wp_verify_nonce( wp_unslash( $_POST['curated_meta_nonce'] ), 'curated_save_data' ) ) // phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
			return;

		// Delete old
		delete_post_meta( $post_id, 'curated_posts' );

		// Save new
		if ( isset( $_POST['curated_posts'] ) ) :
			$post_ids = array_filter(
				array_map('trim', explode(',', sanitize_text_field(wp_unslash($_POST['curated_posts']))))
			);
			
			foreach ( $post_ids as $value ) :
				add_post_meta( $post_id, 'curated_posts', $value );
			endforeach;
		endif;
	}

	/**
	 * Remove link from post updated messages
	 */
	public static function post_updated_messages( $messages ) {
		global $typenow, $post;

		if ( 'curated_posts' == $typenow ) :
			for ( $i = 0; $i <= 10; $i++ ) :
				$messages['post'][ $i ] = '<strong>' . __( 'Curated posts saved.', 'curated-posts' ) . '</strong>';
			endfor;
		endif;

		return $messages;
	}

	/**
	 * Shortcode content
	 */
	public static function shortcode( $atts ) {
		// Get shortcode attributes
		if ( ! is_array( $atts ) || empty( $atts ) ) {
			return '';
		}

		// Get group ID
		$id = array_shift( $atts );
		if ( empty( $id ) ) {
			return '';
		}

		// Get group post ids
		$post_IDs = get_curated_ids( $id );
		if ( empty( $post_IDs ) ) {
			return '';
		}

		// Loop through posts
		global $post;
		$content = '<ul class="curated-posts" id="curated-posts-' . esc_attr( $id ) . '">';
		$query = new WP_Query( array( 
			'post__in' => $post_IDs, 
			'post_type' => array( 'post', 'page' ), 
			'orderby' => 'post__in', 
			'posts_per_page' => -1,
			'post_status' => 'publish'
		) );

		if ( $query->have_posts() ) {
			while ( $query->have_posts() ) {
				$query->the_post();
				$content .= '<li class="curated-post" id="curated-post-' . esc_attr( $post->ID ) . '">';
				$content .= '<a href="' . esc_url( get_permalink() ) . '" title="' . esc_attr( get_the_title() ) . '">' . esc_html( get_the_title() ) . '</a>';
				$content .= '</li>';
			}
		}

		wp_reset_postdata();
		$content .= '</ul>';

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

	public function update_excerpt_label( $translation, $original ) {
		if ( 'Excerpt' == $original ) {
			return __( 'Description', 'curated-posts' );
		}
		return $translation;
	}

	/**
	 * Register REST API routes
	 */
	public function register_rest_routes() {
		register_rest_route(
			'curated-posts/v1',
			'/search',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'search_posts' ),
				'permission_callback' => function () {
					return current_user_can( 'edit_posts' );
				},
			)
		);
	}

	/**
	 * Search posts endpoint
	 */
	public function search_posts( WP_REST_Request $request ) {
		// Initialize variables with defaults
		$per_page = 10;
		$page = max(1, (int) $request->get_param('page'));
		$search = $request->get_param('search');
		$include = $request->get_param('include');

		// Base query args
		$args = array(
			'post_type'      => 'post',
			'post_status'    => 'publish',
			'posts_per_page' => $per_page,
			'paged'          => $page,
		);

		// Add search parameter if provided
		if (!empty($search)) {
			$args['s'] = sanitize_text_field($search);
		}

		// Handle included posts
		if (!empty($include)) {
			$post_ids = array_map('intval', explode(',', sanitize_text_field($include)));
			if (empty($post_ids)) {
				return new WP_REST_Response(array());
			}
			$args['post__in'] = $post_ids;
			$args['orderby'] = 'post__in';
			$args['posts_per_page'] = -1; // Get all included posts
			$args['post_type'] = array('post', 'page'); // Allow both posts and pages
		}

		$query = new WP_Query( $args );
		$posts = array_map( function( $post ) {
			return array(
				'id'    => $post->ID,
				'title' => array(
					'rendered' => $post->post_title,
				),
				'date'  => $post->post_date,
			);
		}, $query->posts );

		$response = new WP_REST_Response( $posts );
		$response->header( 'X-WP-Total', $query->found_posts );
		$response->header( 'X-WP-TotalPages', ceil( $query->found_posts / $per_page ) );

		return $response;
	}
}

/**
 * API: Get curated posts IDs for this list
 *
 * @return array
 */
function get_curated_ids( $id_or_slug ) {
	if ( empty( $id_or_slug ) ) {
		return array();
	}

	if ( is_numeric( $id_or_slug ) ) {
		$post_ids = get_post_meta( $id_or_slug, 'curated_posts' );
		return is_array( $post_ids ) ? $post_ids : array();
	}

	$post = get_page_by_path( $id_or_slug, OBJECT, 'curated_posts' );
	if ( ! $post ) {
		return array();
	}

	$post_ids = get_post_meta( $post->ID, 'curated_posts' );
	return is_array( $post_ids ) ? $post_ids : array();
}

new Curated_Posts();
