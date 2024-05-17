<?php
/**
 * REST API: Gutenberg_REST_Posts_Controller_6_6 class
 *
 * @package    gutenberg
 */

/**
 * Gutenberg_REST_Posts_Controller_6_6 class
 *
 * Whether the template used for a post was correct or not was only taking into
 * consideration theme templates. This class overrides the check_template method
 * to also consider templates in the Block Templates Registry.
 */
class Gutenberg_REST_Posts_Controller_6_6 extends WP_REST_Posts_Controller {

	/**
	 * Checks whether the template is valid for the given post.
	 *
	 * @since 4.9.0
	 *
	 * @param string          $template Page template filename.
	 * @param WP_REST_Request $request  Request.
	 * @return true|WP_Error True if template is still valid or if the same as existing value, or a WP_Error if template not supported.
	 */
	public function check_template( $template, $request ) {
		if ( ! $template ) {
			return true;
		}

		if ( $request['id'] ) {
			$current_template = get_page_template_slug( $request['id'] );
		} else {
			$current_template = '';
		}

		// Always allow for updating a post to the same template, even if that template is no longer supported.
		if ( $template === $current_template ) {
			return true;
		}

		// If this is a create request, get_post() will return null and wp theme will fallback to the passed post type.
		$allowed_templates = gutenberg_get_block_templates( array( 'post_type' => $this->post_type ), 'wp_template' );

		foreach ( $allowed_templates as $allowed_template ) {
			if ( $request['template'] === $allowed_template->slug ) {
				return true;
			}
		}

		if ( isset( $allowed_templates[ $template ] ) ) {
			return true;
		}

		$allowed_templates_slugs = array_map(
			function ( $allowed_template ) {
				return $allowed_template->slug;
			},
			$allowed_templates
		);

		return new WP_Error(
			'rest_invalid_param',
			/* translators: 1: Parameter, 2: List of valid values. */
			sprintf( __( '%1$s is not one of %2$s.' ), 'template', implode( ', ', array_keys( $allowed_templates_slugs ) ) )
		);
	}
}

add_action(
	'init',
	function () {
		// We need to register the Post and Page post types again to assign them the correct `rest_controller_class`.
		register_post_type(
			'post',
			array(
				'labels'                => array(
					'name_admin_bar' => _x( 'Post', 'add new from admin bar' ),
				),
				'public'                => true,
				'_builtin'              => true, /* internal use only. don't use this when registering your own post type. */
				'_edit_link'            => 'post.php?post=%d', /* internal use only. don't use this when registering your own post type. */
				'capability_type'       => 'post',
				'map_meta_cap'          => true,
				'menu_position'         => 5,
				'menu_icon'             => 'dashicons-admin-post',
				'hierarchical'          => false,
				'rewrite'               => false,
				'query_var'             => false,
				'delete_with_user'      => true,
				'supports'              => array( 'title', 'editor', 'author', 'thumbnail', 'excerpt', 'trackbacks', 'custom-fields', 'comments', 'revisions', 'post-formats' ),
				'show_in_rest'          => true,
				'rest_base'             => 'posts',
				'rest_controller_class' => 'Gutenberg_REST_Posts_Controller_6_6',
			)
		);

		register_post_type(
			'page',
			array(
				'labels'                => array(
					'name_admin_bar' => _x( 'Page', 'add new from admin bar' ),
				),
				'public'                => true,
				'publicly_queryable'    => false,
				'_builtin'              => true, /* internal use only. don't use this when registering your own post type. */
				'_edit_link'            => 'post.php?post=%d', /* internal use only. don't use this when registering your own post type. */
				'capability_type'       => 'page',
				'map_meta_cap'          => true,
				'menu_position'         => 20,
				'menu_icon'             => 'dashicons-admin-page',
				'hierarchical'          => true,
				'rewrite'               => false,
				'query_var'             => false,
				'delete_with_user'      => true,
				'supports'              => array( 'title', 'editor', 'author', 'thumbnail', 'page-attributes', 'custom-fields', 'comments', 'revisions' ),
				'show_in_rest'          => true,
				'rest_base'             => 'pages',
				'rest_controller_class' => 'Gutenberg_REST_Posts_Controller_6_6',
			)
		);
	}
);
