<?php
/**
 * REST API: Gutenberg_REST_Templates_Controller_6_6 class
 *
 * @package    gutenberg
 */

/**
 * Gutenberg_REST_Templates_Controller_6_6 class
 *
 * Templates and template parts currently only allow access to administrators with the
 * `edit_theme_options` capability. In order to allow other roles to also view the templates,
 * we need to override the permissions check for the REST API endpoints.
 */
class Gutenberg_REST_Templates_Controller_6_6 extends Gutenberg_REST_Templates_Controller_6_4 {

	/**
	 * Checks if a given request has access to read templates.
	 *
	 * @since 6.6.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function get_items_permissions_check( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		if ( current_user_can( 'edit_posts' ) ) {
			return true;
		}
		foreach ( get_post_types( array( 'show_in_rest' => true ), 'objects' ) as $post_type ) {
			if ( current_user_can( $post_type->cap->edit_posts ) ) {
				return true;
			}
		}

		return new WP_Error(
			'rest_cannot_manage_templates',
			__( 'Sorry, you are not allowed to access the templates on this site.', 'default' ),
			array(
				'status' => rest_authorization_required_code(),
			)
		);
	}

	/**
	 * Returns a list of templates.
	 *
	 * @since 5.8.0
	 *
	 * @param WP_REST_Request $request The request instance.
	 * @return WP_REST_Response
	 */
	public function get_items( $request ) {
		$query = array();
		if ( isset( $request['wp_id'] ) ) {
			$query['wp_id'] = $request['wp_id'];
		}
		if ( isset( $request['area'] ) ) {
			$query['area'] = $request['area'];
		}
		if ( isset( $request['post_type'] ) ) {
			$query['post_type'] = $request['post_type'];
		}

		$templates = array();
		foreach ( gutenberg_get_block_templates( $query, $this->post_type ) as $template ) {
			$data        = $this->prepare_item_for_response( $template, $request );
			$templates[] = $this->prepare_response_for_collection( $data );
		}

		return rest_ensure_response( $templates );
	}

	/**
	 * Checks if a given request has access to read templates.
	 *
	 * @since 6.6.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return true|WP_Error True if the request has read access, WP_Error object otherwise.
	 */
	public function get_item_permissions_check( $request ) { // phpcs:ignore VariableAnalysis.CodeAnalysis.VariableAnalysis.UnusedVariable
		if ( current_user_can( 'edit_posts' ) ) {
			return true;
		}
		foreach ( get_post_types( array( 'show_in_rest' => true ), 'objects' ) as $post_type ) {
			if ( current_user_can( $post_type->cap->edit_posts ) ) {
				return true;
			}
		}

		return new WP_Error(
			'rest_cannot_manage_templates',
			__( 'Sorry, you are not allowed to access the templates on this site.', 'default' ),
			array(
				'status' => rest_authorization_required_code(),
			)
		);
	}

	/**
	 * Returns the fallback template for the given slug.
	 *
	 * @since 6.1.0
	 *
	 * @param WP_REST_Request $request The request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_template_fallback( $request ) {
		$hierarchy = gutenberg_get_template_hierarchy( $request['slug'], $request['is_custom'], $request['template_prefix'] );

		do {
			$fallback_template = resolve_block_template( $request['slug'], $hierarchy, '' );
			array_shift( $hierarchy );
		} while ( ! empty( $hierarchy ) && empty( $fallback_template->content ) );

		$response = $this->prepare_item_for_response( $fallback_template, $request );

		return rest_ensure_response( $response );
	}

	/**
	 * Returns the given template
	 *
	 * @param WP_REST_Request $request The request instance.
	 * @return WP_REST_Response|WP_Error
	 */
	public function get_item( $request ) {
		if ( isset( $request['source'] ) && 'theme' === $request['source'] ) {
			$template = get_block_file_template( $request['id'], $this->post_type );
		} elseif ( isset( $request['source'] ) && 'plugin' === $request['source'] ) {
			list( $namespace, $slug ) = explode( '//', $request['id'] );
			$template                 = WP_Block_Templates_Registry::get_instance()->get_by_slug( $this->post_type, $slug );
		} else {
			$template = get_block_template( $request['id'], $this->post_type );
		}

		if ( ! $template ) {
			return new WP_Error( 'rest_template_not_found', __( 'No templates exist with that id.' ), array( 'status' => 404 ) );
		}

		return $this->prepare_item_for_response( $template, $request );
	}

	/**
	 * Fix wrong author in plugin templates.
	 *
	 * @param WP_Block_Template $item    Template instance.
	 * @param WP_REST_Request   $request Request object.
	 * @return WP_REST_Response Response object.
	 */
	public function prepare_item_for_response( $item, $request ) {
		$template = $item;

		$fields = $this->get_fields_for_response( $request );

		if ( 'plugin' !== $item->origin ) {
			return parent::prepare_item_for_response( $item, $request );
		}
		$cloned_item = clone $item;
		// Set the origin as theme when calling the previous `prepare_item_for_response()` to prevent warnings when generating the author text.
		$cloned_item->origin = 'theme';
		$response            = parent::prepare_item_for_response( $cloned_item, $request );
		$data                = $response->data;

		if ( rest_is_field_included( 'origin', $fields ) ) {
			$data['origin'] = 'plugin';
		}

		if ( rest_is_field_included( 'author_text', $fields ) ) {
			$data['author_text'] = $this->get_wp_templates_author_text_field( $template );
		}

		if ( rest_is_field_included( 'original_source', $fields ) ) {
			$data['original_source'] = $this->get_wp_templates_original_source_field( $template );
		}

		$response = rest_ensure_response( $data );

		if ( rest_is_field_included( '_links', $fields ) || rest_is_field_included( '_embedded', $fields ) ) {
			$links = $this->prepare_links( $template->id );
			$response->add_links( $links );
			if ( ! empty( $links['self']['href'] ) ) {
				$actions = $this->get_available_actions();
				$self    = $links['self']['href'];
				foreach ( $actions as $rel ) {
					$response->add_link( $rel, $self );
				}
			}
		}

		return $response;
	}

	/**
	 * Allow plugin authors where `has_theme_file` is false.
	 *
	 * @param WP_Block_Template $template_object Template instance.
	 * @return string                            Original source of the template one of theme, plugin, site, or user.
	 */
	private static function get_wp_templates_original_source_field( $template_object ) {
		if ( 'wp_template' === $template_object->type || 'wp_template_part' === $template_object->type ) {
			// Added by theme.
			// Template originally provided by a theme, but customized by a user.
			// Templates originally didn't have the 'origin' field so identify
			// older customized templates by checking for no origin and a 'theme'
			// or 'custom' source.
			if ( $template_object->has_theme_file &&
			( 'theme' === $template_object->origin || (
				empty( $template_object->origin ) && in_array(
					$template_object->source,
					array(
						'theme',
						'custom',
					),
					true
				) )
			)
			) {
				return 'theme';
			}

			// Added by plugin.
			if ( 'plugin' === $template_object->origin ) {
				return 'plugin';
			}

			// Added by site.
			// Template was created from scratch, but has no author. Author support
			// was only added to templates in WordPress 5.9. Fallback to showing the
			// site logo and title.
			if ( empty( $template_object->has_theme_file ) && 'custom' === $template_object->source && empty( $template_object->author ) ) {
				return 'site';
			}
		}

		// Added by user.
		return 'user';
	}

	/**
	 * Prioritize plugin name instead of theme name for plugin-registered templates.
	 *
	 * @param WP_Block_Template $template_object Template instance.
	 * @return string                            Human readable text for the author.
	 */
	private static function get_wp_templates_author_text_field( $template_object ) {
		$original_source = self::get_wp_templates_original_source_field( $template_object );
		switch ( $original_source ) {
			case 'theme':
				$theme_name = wp_get_theme( $template_object->theme )->get( 'Name' );
				return empty( $theme_name ) ? $template_object->theme : $theme_name;
			case 'plugin':
				$plugins     = get_plugins();
				$plugin_name = isset( $template_object->plugin ) ? $template_object->plugin . '/' . $template_object->plugin : $template_object->theme;
				$plugin  = $plugins[ plugin_basename( sanitize_text_field( $plugin_name . '.php' ) ) ];
				return empty( $plugin['Name'] ) ? $template_object->theme : $plugin['Name'];
			case 'site':
				return get_bloginfo( 'name' );
			case 'user':
				$author = get_user_by( 'id', $template_object->author );
				if ( ! $author ) {
					return __( 'Unknown author' );
				}
				return $author->get( 'display_name' );
		}
	}
}
