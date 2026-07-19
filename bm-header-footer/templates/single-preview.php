<?php
/**
 * Blank canvas for the template's single view. Used by the Elementor editor
 * preview and by the front end "View" link of a template.
 *
 * @package bm-header-footer
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
?><!DOCTYPE html>
<html <?php language_attributes(); ?>>
<head>
	<meta charset="<?php bloginfo( 'charset' ); ?>">
	<meta name="viewport" content="width=device-width, initial-scale=1">
	<meta name="robots" content="noindex, nofollow">
	<?php wp_head(); ?>
</head>
<body <?php body_class(); ?>>
<?php wp_body_open(); ?>
<?php
while ( have_posts() ) :
	the_post();

	$bmhf_mode        = get_post_meta( get_the_ID(), '_bmhf_mode', true );
	$bmhf_in_elementor = did_action( 'elementor/loaded' )
		&& \Elementor\Plugin::instance()->preview->is_preview_mode();

	if ( 'html' === $bmhf_mode && ! $bmhf_in_elementor ) {
		bmhf_render_template_content( get_the_ID() );
	} else {
		// the_content() lets Elementor inject its builder / editor preview.
		the_content();
	}
endwhile;
?>
<?php wp_footer(); ?>
</body>
</html>
