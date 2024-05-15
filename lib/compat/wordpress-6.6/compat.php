<?php
/**
 * WordPress 6.6 compatibility functions.
 *
 * @package gutenberg
 */

/**
 * Change the Patterns submenu link and remove the Template Parts submenu for
 * the Classic theme. This function should not be backported to core, and should be
 * removed when the required WP core version for Gutenberg is >= 6.6.0.
 *
 * @global array $submenu
 */
function gutenberg_change_patterns_link_and_remove_template_parts_submenu_item() {
	if ( ! wp_is_block_theme() ) {
		global $submenu;

		if ( empty( $submenu['themes.php'] ) ) {
			return;
		}

		foreach ( $submenu['themes.php'] as $key => $item ) {
			if ( 'edit.php?post_type=wp_block' === $item[2] ) {
				$submenu['themes.php'][ $key ][2] = 'site-editor.php?path=/patterns';
			} elseif ( 'site-editor.php?path=/wp_template_part/all' === $item[2] ) {
				unset( $submenu['themes.php'][ $key ] );
			}
		}
	}
}
add_action( 'admin_init', 'gutenberg_change_patterns_link_and_remove_template_parts_submenu_item' );

/**
 * Given that we can't modify `locate_block_template()` in `wp-includes` to call `gutenberg_get_block_templates()` instead of `get_block_templates()` we need to filter the results and return the correct template.
 *
 * @global string $_wp_current_template_content
 * @global int $_wp_current_template_id
 */
$template_filters = array(
	'404_template',
	'archive_template',
	'attachment_template',
	'author_template',
	'category_template',
	'date_template',
	'embed_template',
	'frontpage_template',
	'home_template',
	'index_template',
	'page_template',
	'paged_template',
	'privacypolicy_template',
	'search_template',
	'single_template',
	'singular_template',
	'tag_template',
	'taxonomy_template',
);
foreach ( $template_filters as $template_filter ) {
	add_filter(
		$template_filter,
		function (
			$template,
			$type,
			$templates
		) {
			global $_wp_current_template_content, $_wp_current_template_id;
			foreach ( $templates as $template_slug ) {
				$located_template = WP_Block_Templates_Registry::get_instance()->get_by_slug( 'wp_template', $template_slug );
				if ( isset( $located_template ) ) {
					$_wp_current_template_id      = $located_template->id;
					$_wp_current_template_content = $located_template->content;

					return ABSPATH . WPINC . '/template-canvas.php';
				}
			}

			return $template;
		},
		10,
		3
	);
}
