<?php
/**
 * Plugin Name:       BM Custom Header & Footer
 * Plugin URI:        https://github.com/mrbearsmediagr-netizen
 * Description:       Custom header & footer templates for any classic theme. Build them with Elementor (Free) or paste plain HTML. WPML-ready.
 * Version:           1.0.0
 * Author:            BEARSMEDIA
 * License:           GPL-2.0-or-later
 * License URI:       https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain:       bm-header-footer
 * Domain Path:       /languages
 * Requires at least: 6.5
 * Requires PHP:      7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

define( 'BMHF_VERSION', '1.0.0' );
define( 'BMHF_FILE', __FILE__ );
define( 'BMHF_DIR', plugin_dir_path( __FILE__ ) );
define( 'BMHF_URL', plugin_dir_url( __FILE__ ) );
define( 'BMHF_POST_TYPE', 'bmhf_template' );

/* -------------------------------------------------------------------------
 * Setup
 * ---------------------------------------------------------------------- */

add_action( 'init', 'bmhf_load_textdomain', 1 );
function bmhf_load_textdomain() {
	load_plugin_textdomain( 'bm-header-footer', false, dirname( plugin_basename( BMHF_FILE ) ) . '/languages' );
}

add_action( 'init', 'bmhf_register_post_type' );
function bmhf_register_post_type() {
	$labels = array(
		'name'               => __( 'Header & Footer', 'bm-header-footer' ),
		'singular_name'      => __( 'Template', 'bm-header-footer' ),
		'menu_name'          => __( 'Header & Footer', 'bm-header-footer' ),
		'add_new'            => __( 'Add New', 'bm-header-footer' ),
		'add_new_item'       => __( 'Add New Template', 'bm-header-footer' ),
		'edit_item'          => __( 'Edit Template', 'bm-header-footer' ),
		'new_item'           => __( 'New Template', 'bm-header-footer' ),
		'view_item'          => __( 'View Template', 'bm-header-footer' ),
		'search_items'       => __( 'Search Templates', 'bm-header-footer' ),
		'not_found'          => __( 'No templates found. Create a Header or Footer template to get started.', 'bm-header-footer' ),
		'not_found_in_trash' => __( 'No templates found in Trash.', 'bm-header-footer' ),
	);

	register_post_type(
		BMHF_POST_TYPE,
		array(
			'labels'              => $labels,
			'public'              => true,
			'publicly_queryable'  => true,
			'exclude_from_search' => true,
			'show_ui'             => true,
			'show_in_menu'        => 'themes.php',
			'show_in_nav_menus'   => false,
			'show_in_rest'        => false,
			'rewrite'             => false,
			'capability_type'     => 'post',
			'hierarchical'        => false,
			'supports'            => array( 'title', 'editor', 'elementor' ),
		)
	);
}

add_action( 'init', 'bmhf_register_meta' );
function bmhf_register_meta() {
	// Sanitization at the meta layer so every write path is covered —
	// including WPML's Translation Editor, which bypasses our save handler.
	register_post_meta(
		BMHF_POST_TYPE,
		'_bmhf_html',
		array(
			'type'              => 'string',
			'single'            => true,
			'show_in_rest'      => false,
			'sanitize_callback' => 'bmhf_sanitize_html_meta',
		)
	);
}

function bmhf_sanitize_html_meta( $value ) {
	return current_user_can( 'unfiltered_html' ) ? $value : wp_kses_post( (string) $value );
}

register_activation_hook( BMHF_FILE, 'bmhf_activate' );
function bmhf_activate() {
	bmhf_register_post_type();
	bmhf_add_elementor_cpt_support();
}

/**
 * Make the template post type selectable/editable in Elementor (Free).
 */
add_action( 'admin_init', 'bmhf_add_elementor_cpt_support' );
function bmhf_add_elementor_cpt_support() {
	$supported = get_option( 'elementor_cpt_support' );

	if ( false === $supported ) {
		// Elementor not configured yet (or not installed). The 'elementor'
		// entry in the post type's supports array covers editing.
		return;
	}

	if ( is_array( $supported ) && ! in_array( BMHF_POST_TYPE, $supported, true ) ) {
		$supported[] = BMHF_POST_TYPE;
		update_option( 'elementor_cpt_support', $supported );
	}
}

/* -------------------------------------------------------------------------
 * Meta box: template type + content mode + custom HTML
 * ---------------------------------------------------------------------- */

add_action( 'add_meta_boxes', 'bmhf_add_meta_box' );
function bmhf_add_meta_box() {
	add_meta_box(
		'bmhf_settings',
		__( 'Template Settings', 'bm-header-footer' ),
		'bmhf_render_meta_box',
		BMHF_POST_TYPE,
		'normal',
		'high'
	);
}

function bmhf_render_meta_box( $post ) {
	$type = get_post_meta( $post->ID, '_bmhf_type', true );
	$mode = get_post_meta( $post->ID, '_bmhf_mode', true );
	$html = get_post_meta( $post->ID, '_bmhf_html', true );

	$type = in_array( $type, array( 'header', 'footer' ), true ) ? $type : 'header';
	$mode = in_array( $mode, array( 'default', 'html' ), true ) ? $mode : 'default';

	wp_nonce_field( 'bmhf_save_settings', 'bmhf_nonce' );
	?>
	<table class="form-table" role="presentation">
		<tr>
			<th scope="row"><?php esc_html_e( 'Type', 'bm-header-footer' ); ?></th>
			<td>
				<label style="margin-right:16px;">
					<input type="radio" name="bmhf_type" value="header" <?php checked( $type, 'header' ); ?> />
					<?php esc_html_e( 'Header', 'bm-header-footer' ); ?>
				</label>
				<label>
					<input type="radio" name="bmhf_type" value="footer" <?php checked( $type, 'footer' ); ?> />
					<?php esc_html_e( 'Footer', 'bm-header-footer' ); ?>
				</label>
			</td>
		</tr>
		<tr>
			<th scope="row"><?php esc_html_e( 'Content source', 'bm-header-footer' ); ?></th>
			<td>
				<label style="margin-right:16px;">
					<input type="radio" name="bmhf_mode" value="default" <?php checked( $mode, 'default' ); ?> />
					<?php esc_html_e( 'Elementor / WordPress editor', 'bm-header-footer' ); ?>
				</label>
				<label>
					<input type="radio" name="bmhf_mode" value="html" <?php checked( $mode, 'html' ); ?> />
					<?php esc_html_e( 'Custom HTML', 'bm-header-footer' ); ?>
				</label>
				<p class="description">
					<?php esc_html_e( 'With "Elementor / WordPress editor" the template content is designed with Elementor (or the normal editor). With "Custom HTML" the HTML below is printed as-is.', 'bm-header-footer' ); ?>
				</p>
			</td>
		</tr>
		<tr class="bmhf-html-row" <?php echo 'html' === $mode ? '' : 'style="display:none;"'; ?>>
			<th scope="row"><label for="bmhf_html"><?php esc_html_e( 'Custom HTML', 'bm-header-footer' ); ?></label></th>
			<td>
				<textarea name="bmhf_html" id="bmhf_html" rows="14" class="large-text code"><?php echo esc_textarea( $html ); ?></textarea>
				<p class="description">
					<?php esc_html_e( 'Full HTML is allowed (including <style> and <script>) for administrators. Shortcodes are supported.', 'bm-header-footer' ); ?>
				</p>
			</td>
		</tr>
	</table>
	<p class="description">
		<?php esc_html_e( 'Publish the template to activate it. Keep it as Draft to disable it. The most recently published template of each type is used site-wide.', 'bm-header-footer' ); ?>
		<?php esc_html_e( 'Save the template after choosing the Type and before opening Elementor, so the choice is stored.', 'bm-header-footer' ); ?>
		<?php esc_html_e( 'With WPML: translate this template into each language and the matching language version is shown automatically.', 'bm-header-footer' ); ?>
	</p>
	<?php
}

add_action( 'save_post_' . BMHF_POST_TYPE, 'bmhf_save_meta', 10, 2 );
function bmhf_save_meta( $post_id, $post ) {
	if ( ! isset( $_POST['bmhf_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['bmhf_nonce'] ), 'bmhf_save_settings' ) ) {
		return;
	}
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) {
		return;
	}

	$type = isset( $_POST['bmhf_type'] ) ? sanitize_key( wp_unslash( $_POST['bmhf_type'] ) ) : 'header';
	$mode = isset( $_POST['bmhf_mode'] ) ? sanitize_key( wp_unslash( $_POST['bmhf_mode'] ) ) : 'default';

	update_post_meta( $post_id, '_bmhf_type', in_array( $type, array( 'header', 'footer' ), true ) ? $type : 'header' );
	update_post_meta( $post_id, '_bmhf_mode', in_array( $mode, array( 'default', 'html' ), true ) ? $mode : 'default' );

	if ( isset( $_POST['bmhf_html'] ) ) {
		$html = wp_unslash( $_POST['bmhf_html'] );
		if ( ! current_user_can( 'unfiltered_html' ) ) {
			$html = wp_kses_post( $html );
		}
		// The meta API expects slashed input (it unslashes internally).
		update_post_meta( $post_id, '_bmhf_html', wp_slash( $html ) );
	}
}

/**
 * Seed default meta on every save so templates created/published entirely
 * through Elementor (whose AJAX save skips the meta box form) still get a
 * type and are picked up by the front end lookup.
 */
add_action( 'save_post_' . BMHF_POST_TYPE, 'bmhf_seed_default_meta', 20 );
function bmhf_seed_default_meta( $post_id ) {
	if ( wp_is_post_revision( $post_id ) || wp_is_post_autosave( $post_id ) ) {
		return;
	}
	add_post_meta( $post_id, '_bmhf_type', 'header', true );
	add_post_meta( $post_id, '_bmhf_mode', 'default', true );
}

/**
 * Code editor + mode toggle on the template edit screen.
 */
add_action( 'admin_enqueue_scripts', 'bmhf_admin_scripts' );
function bmhf_admin_scripts( $hook ) {
	if ( 'post.php' !== $hook && 'post-new.php' !== $hook ) {
		return;
	}
	$screen = get_current_screen();
	if ( ! $screen || BMHF_POST_TYPE !== $screen->post_type ) {
		return;
	}

	$settings = wp_enqueue_code_editor( array( 'type' => 'text/html' ) );

	$js = '
	jQuery( function( $ ) {
		var editor = null;
		var settings = ' . wp_json_encode( $settings ) . ';

		function initEditor() {
			if ( editor || ! settings || ! window.wp || ! wp.codeEditor ) {
				if ( editor ) { editor.codemirror.refresh(); }
				return;
			}
			editor = wp.codeEditor.initialize( "bmhf_html", settings );
		}

		function toggle() {
			var isHtml = "html" === $( "input[name=bmhf_mode]:checked" ).val();
			$( ".bmhf-html-row" ).toggle( isHtml );
			if ( isHtml ) { initEditor(); }
		}

		$( "input[name=bmhf_mode]" ).on( "change", toggle );
		toggle();
	} );
	';

	wp_add_inline_script( 'code-editor', $js );

	// wp_enqueue_code_editor() bails when the user disabled syntax
	// highlighting; fall back to jQuery only for the toggle behaviour.
	if ( false === $settings ) {
		wp_enqueue_script( 'jquery' );
		wp_add_inline_script( 'jquery-core', $js );
	}
}

/* -------------------------------------------------------------------------
 * Admin list table columns + notices
 * ---------------------------------------------------------------------- */

add_filter( 'manage_' . BMHF_POST_TYPE . '_posts_columns', 'bmhf_admin_columns' );
function bmhf_admin_columns( $columns ) {
	$new = array();
	foreach ( $columns as $key => $label ) {
		$new[ $key ] = $label;
		if ( 'title' === $key ) {
			$new['bmhf_type']      = __( 'Type', 'bm-header-footer' );
			$new['bmhf_mode']      = __( 'Content source', 'bm-header-footer' );
			$new['bmhf_shortcode'] = __( 'Shortcode', 'bm-header-footer' );
		}
	}
	return $new;
}

add_action( 'manage_' . BMHF_POST_TYPE . '_posts_custom_column', 'bmhf_admin_column_content', 10, 2 );
function bmhf_admin_column_content( $column, $post_id ) {
	if ( 'bmhf_type' === $column ) {
		$type = get_post_meta( $post_id, '_bmhf_type', true );
		echo 'footer' === $type ? esc_html__( 'Footer', 'bm-header-footer' ) : esc_html__( 'Header', 'bm-header-footer' );
	} elseif ( 'bmhf_mode' === $column ) {
		$mode = get_post_meta( $post_id, '_bmhf_mode', true );
		if ( 'html' === $mode ) {
			esc_html_e( 'Custom HTML', 'bm-header-footer' );
		} elseif ( bmhf_is_built_with_elementor( $post_id ) ) {
			esc_html_e( 'Elementor', 'bm-header-footer' );
		} else {
			esc_html_e( 'WordPress editor', 'bm-header-footer' );
		}
	} elseif ( 'bmhf_shortcode' === $column ) {
		printf( '<code>[bmhf_template id="%d"]</code>', absint( $post_id ) );
	}
}

add_action( 'admin_notices', 'bmhf_block_theme_notice' );
function bmhf_block_theme_notice() {
	$screen = get_current_screen();
	if ( ! $screen || BMHF_POST_TYPE !== $screen->post_type ) {
		return;
	}
	if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
		printf(
			'<div class="notice notice-warning"><p>%s</p></div>',
			esc_html__( 'Your active theme is a block (Full Site Editing) theme. Automatic header/footer replacement works with classic themes only (e.g. Hello Elementor, Astra, OceanWP, GeneratePress). You can still output templates with the [bmhf_template id="…"] shortcode.', 'bm-header-footer' )
		);
	}
}

add_action( 'admin_notices', 'bmhf_pair_notice' );
function bmhf_pair_notice() {
	$screen = get_current_screen();
	if ( ! $screen || 'edit' !== $screen->base || BMHF_POST_TYPE !== $screen->post_type ) {
		return;
	}

	$has_header = (bool) bmhf_get_template_id( 'header' );
	$has_footer = (bool) bmhf_get_template_id( 'footer' );

	if ( $has_header xor $has_footer ) {
		printf(
			'<div class="notice notice-info"><p>%s</p></div>',
			esc_html__( 'Only one template type is published. Classic themes open their layout wrappers in the header and close them in the footer, so for correct markup publish both a Header and a Footer template.', 'bm-header-footer' )
		);
	}
}

/* -------------------------------------------------------------------------
 * Template lookup (WPML aware)
 * ---------------------------------------------------------------------- */

/**
 * Get the template ID to display for a given type ('header' or 'footer'),
 * in the current language when WPML (or a compatible plugin) is active.
 *
 * @param string $type 'header' or 'footer'.
 * @return int Template post ID, 0 if none.
 */
function bmhf_get_template_id( $type ) {
	static $cache = array();

	// Key the cache by language too, so a mid-request language switch
	// (wpml_switch_language) does not serve a stale other-language ID.
	$lang      = (string) apply_filters( 'wpml_current_language', '' );
	$cache_key = $type . ':' . $lang;

	if ( isset( $cache[ $cache_key ] ) ) {
		return $cache[ $cache_key ];
	}

	$meta_query = array(
		array(
			'key'   => '_bmhf_type',
			'value' => $type,
		),
	);

	// Templates saved only through Elementor may lack the meta row;
	// missing meta counts as 'header' (the meta box default).
	if ( 'header' === $type ) {
		$meta_query = array(
			'relation' => 'OR',
			$meta_query[0],
			array(
				'key'     => '_bmhf_type',
				'compare' => 'NOT EXISTS',
			),
		);
	}

	// All published templates of this type, in every language.
	$candidates = get_posts(
		array(
			'post_type'        => BMHF_POST_TYPE,
			'post_status'      => 'publish',
			'numberposts'      => -1,
			'fields'           => 'ids',
			'orderby'          => 'date',
			'order'            => 'DESC',
			'meta_query'       => $meta_query, // phpcs:ignore WordPress.DB.SlowDBQuery.slow_db_query_meta_query
			'suppress_filters' => true,
			'no_found_rows'    => true,
		)
	);

	$found    = 0;
	$fallback = 0;

	foreach ( $candidates as $candidate_id ) {
		// Translation in the current language (null when none). Without
		// WPML the filter is a no-op and returns the ID unchanged.
		$translated = apply_filters( 'wpml_object_id', $candidate_id, BMHF_POST_TYPE, false );

		if ( $translated && 'publish' === get_post_status( $translated ) ) {
			$found = (int) $translated;
			break;
		}

		if ( ! $fallback ) {
			// Untranslated language: prefer the default-language version,
			// keep the newest published candidate as last resort.
			$default_lang = apply_filters( 'wpml_default_language', null );
			$original     = (int) apply_filters( 'wpml_object_id', $candidate_id, BMHF_POST_TYPE, true, $default_lang );

			if ( $original && 'publish' === get_post_status( $original ) ) {
				$fallback = $original;
			} else {
				$fallback = (int) $candidate_id;
			}
		}
	}

	$id = $found ? $found : $fallback;

	/**
	 * Filter the template ID used for a location.
	 *
	 * @param int    $id   Template post ID (0 = none).
	 * @param string $type 'header' or 'footer'.
	 */
	$id = (int) apply_filters( 'bmhf_template_id', $id, $type );

	$cache[ $cache_key ] = $id;

	return $id;
}

function bmhf_is_built_with_elementor( $post_id ) {
	return did_action( 'elementor/loaded' ) && 'builder' === get_post_meta( $post_id, '_elementor_edit_mode', true );
}

/* -------------------------------------------------------------------------
 * Rendering
 * ---------------------------------------------------------------------- */

/**
 * Render a template's content (HTML mode, Elementor, or editor content).
 *
 * @param int $template_id Template post ID.
 */
function bmhf_render_template_content( $template_id ) {
	static $rendering = array();

	$template_id = (int) $template_id;
	if ( ! $template_id || BMHF_POST_TYPE !== get_post_type( $template_id ) ) {
		return;
	}
	if ( isset( $rendering[ $template_id ] ) ) {
		return; // Template embeds itself, directly or via a cycle.
	}
	$rendering[ $template_id ] = true;

	try {
		$mode = get_post_meta( $template_id, '_bmhf_mode', true );

		if ( 'html' === $mode ) {
			$html = (string) get_post_meta( $template_id, '_bmhf_html', true );
			echo do_shortcode( $html ); // phpcs:ignore WordPress.Security.EscapeOutput -- intentional raw admin-provided HTML.
			return;
		}

		if ( bmhf_is_built_with_elementor( $template_id ) ) {
			echo \Elementor\Plugin::instance()->frontend->get_builder_content_for_display( $template_id, true ); // phpcs:ignore WordPress.Security.EscapeOutput
			return;
		}

		$template_post = get_post( $template_id );
		if ( ! $template_post ) {
			return;
		}

		// Make the template the global post while filtering, so the_content
		// consumers that key off get_the_ID() (Elementor included) target
		// the template, not the page being viewed.
		global $post;
		$previous_post = $post;

		$post = $template_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		setup_postdata( $post );

		echo apply_filters( 'the_content', $post->post_content ); // phpcs:ignore WordPress.Security.EscapeOutput

		$post = $previous_post; // phpcs:ignore WordPress.WP.GlobalVariablesOverride
		if ( $post instanceof WP_Post ) {
			setup_postdata( $post );
		} else {
			wp_reset_postdata();
		}
	} finally {
		unset( $rendering[ $template_id ] );
	}
}

/**
 * Render the site header template. Called from templates/header.php.
 */
function bmhf_render_header() {
	$id = bmhf_get_template_id( 'header' );
	if ( ! $id ) {
		return;
	}
	do_action( 'bmhf_before_header' );
	echo '<header id="bmhf-header" class="bmhf-header">';
	bmhf_render_template_content( $id );
	echo '</header>';
	do_action( 'bmhf_after_header' );
}

/**
 * Render the site footer template. Called from templates/footer.php.
 */
function bmhf_render_footer() {
	$id = bmhf_get_template_id( 'footer' );
	if ( ! $id ) {
		return;
	}
	do_action( 'bmhf_before_footer' );
	echo '<footer id="bmhf-footer" class="bmhf-footer">';
	bmhf_render_template_content( $id );
	echo '</footer>';
	do_action( 'bmhf_after_footer' );
}

/* -------------------------------------------------------------------------
 * Theme header/footer override (classic themes)
 * ---------------------------------------------------------------------- */

/**
 * Whether the theme's header/footer should be replaced on this request.
 */
function bmhf_should_override() {
	if ( is_admin() ) {
		return false;
	}
	// Never replace on the template's own single view (Elementor editor
	// preview / front end preview uses its own blank canvas).
	if ( is_singular( BMHF_POST_TYPE ) ) {
		return false;
	}
	if ( function_exists( 'wp_is_block_theme' ) && wp_is_block_theme() ) {
		return false;
	}

	/**
	 * Filter whether the plugin replaces the theme header/footer.
	 */
	return (bool) apply_filters( 'bmhf_enabled', true );
}

add_action( 'get_header', 'bmhf_override_header' );
function bmhf_override_header( $name ) {
	if ( ! bmhf_should_override() || ! bmhf_get_template_id( 'header' ) ) {
		return;
	}

	require BMHF_DIR . 'templates/header.php';

	// Consume the theme's header.php so get_header() finds it already
	// loaded (locate_template() uses require_once) and outputs nothing.
	$templates = array();
	$name      = (string) $name;
	if ( '' !== $name ) {
		$templates[] = "header-{$name}.php";
	}
	$templates[] = 'header.php';

	remove_all_actions( 'wp_head' );
	ob_start();
	locate_template( $templates, true );
	ob_get_clean();
}

add_action( 'get_footer', 'bmhf_override_footer' );
function bmhf_override_footer( $name ) {
	if ( ! bmhf_should_override() || ! bmhf_get_template_id( 'footer' ) ) {
		return;
	}

	require BMHF_DIR . 'templates/footer.php';

	$templates = array();
	$name      = (string) $name;
	if ( '' !== $name ) {
		$templates[] = "footer-{$name}.php";
	}
	$templates[] = 'footer.php';

	remove_all_actions( 'wp_footer' );
	ob_start();
	locate_template( $templates, true );
	ob_get_clean();
}

add_filter( 'body_class', 'bmhf_body_class' );
function bmhf_body_class( $classes ) {
	if ( bmhf_should_override() ) {
		if ( bmhf_get_template_id( 'header' ) ) {
			$classes[] = 'bmhf-custom-header';
		}
		if ( bmhf_get_template_id( 'footer' ) ) {
			$classes[] = 'bmhf-custom-footer';
		}
	}
	return $classes;
}

/**
 * Make sure Elementor styles/scripts load even when the current page itself
 * is not built with Elementor but the header/footer template is.
 */
add_action( 'wp_enqueue_scripts', 'bmhf_enqueue_elementor_assets' );
function bmhf_enqueue_elementor_assets() {
	if ( ! did_action( 'elementor/loaded' ) || ! class_exists( '\Elementor\Plugin' ) ) {
		return;
	}
	if ( ! bmhf_should_override() && ! is_singular( BMHF_POST_TYPE ) ) {
		return;
	}

	$ids = array_filter(
		array(
			bmhf_get_template_id( 'header' ),
			bmhf_get_template_id( 'footer' ),
		)
	);

	$has_elementor_template = false;
	foreach ( $ids as $id ) {
		if ( bmhf_is_built_with_elementor( $id ) ) {
			$has_elementor_template = true;
			break;
		}
	}

	if ( ! $has_elementor_template ) {
		return;
	}

	\Elementor\Plugin::instance()->frontend->enqueue_styles();

	if ( class_exists( '\Elementor\Core\Files\CSS\Post' ) ) {
		foreach ( $ids as $id ) {
			if ( bmhf_is_built_with_elementor( $id ) ) {
				$css = \Elementor\Core\Files\CSS\Post::create( $id );
				$css->enqueue();
			}
		}
	}
}

/* -------------------------------------------------------------------------
 * Single view of a template = blank canvas (also used by Elementor editor)
 * ---------------------------------------------------------------------- */

add_filter( 'template_include', 'bmhf_single_template', 999 );
function bmhf_single_template( $template ) {
	if ( is_singular( BMHF_POST_TYPE ) ) {
		return BMHF_DIR . 'templates/single-preview.php';
	}
	return $template;
}

/* -------------------------------------------------------------------------
 * Shortcode
 * ---------------------------------------------------------------------- */

add_shortcode( 'bmhf_template', 'bmhf_shortcode' );
function bmhf_shortcode( $atts ) {
	$atts = shortcode_atts( array( 'id' => 0 ), $atts, 'bmhf_template' );

	$id = absint( $atts['id'] );
	if ( ! $id ) {
		return '';
	}

	// Map to the current language when WPML is active.
	$id = (int) apply_filters( 'wpml_object_id', $id, BMHF_POST_TYPE, true );

	if ( ! $id || 'publish' !== get_post_status( $id ) || BMHF_POST_TYPE !== get_post_type( $id ) ) {
		return '';
	}

	ob_start();
	bmhf_render_template_content( $id );
	return ob_get_clean();
}

/* -------------------------------------------------------------------------
 * Plugin row action link
 * ---------------------------------------------------------------------- */

add_filter( 'plugin_action_links_' . plugin_basename( BMHF_FILE ), 'bmhf_action_links' );
function bmhf_action_links( $links ) {
	$url = admin_url( 'edit.php?post_type=' . BMHF_POST_TYPE );
	array_unshift( $links, '<a href="' . esc_url( $url ) . '">' . esc_html__( 'Templates', 'bm-header-footer' ) . '</a>' );
	return $links;
}
