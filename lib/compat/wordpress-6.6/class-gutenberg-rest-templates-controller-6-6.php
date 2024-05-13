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
			$template = gutenberg_get_block_template( $request['id'], $this->post_type );
		}

		if ( ! $template ) {
			return new WP_Error( 'rest_template_not_found', __( 'No templates exist with that id.' ), array( 'status' => 404 ) );
		}

		return $this->prepare_item_for_response( $template, $request );
	}

	/**
	 * Updates a single template.
	 *
	 * @since 5.8.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function update_item( $request ) {
		$template = gutenberg_get_block_template( $request['id'], $this->post_type );
		if ( ! $template ) {
			return new WP_Error( 'rest_template_not_found', __( 'No templates exist with that id.' ), array( 'status' => 404 ) );
		}

		$post_before = get_post( $template->wp_id );

		if ( isset( $request['source'] ) && 'theme' === $request['source'] ) {
			wp_delete_post( $template->wp_id, true );
			$request->set_param( 'context', 'edit' );

			$template = get_block_template( $request['id'], $this->post_type );
			$response = $this->prepare_item_for_response( $template, $request );

			return rest_ensure_response( $response );
		}

		$changes = $this->prepare_item_for_database( $request );

		if ( is_wp_error( $changes ) ) {
			return $changes;
		}

		if ( 'custom' === $template->source ) {
			$update = true;
			$result = wp_update_post( wp_slash( (array) $changes ), false );
		} else {
			$update      = false;
			$post_before = null;
			$result      = wp_insert_post( wp_slash( (array) $changes ), false );
		}

		if ( is_wp_error( $result ) ) {
			if ( 'db_update_error' === $result->get_error_code() ) {
				$result->add_data( array( 'status' => 500 ) );
			} else {
				$result->add_data( array( 'status' => 400 ) );
			}
			return $result;
		}

		$template      = gutenberg_get_block_template( $request['id'], $this->post_type );
		$fields_update = $this->update_additional_fields_for_object( $template, $request );
		if ( is_wp_error( $fields_update ) ) {
			return $fields_update;
		}

		$request->set_param( 'context', 'edit' );

		$post = get_post( $template->wp_id );
		/** This action is documented in wp-includes/rest-api/endpoints/class-wp-rest-posts-controller.php */
		do_action( "rest_after_insert_{$this->post_type}", $post, $request, false );

		wp_after_insert_post( $post, $update, $post_before );

		$response = $this->prepare_item_for_response( $template, $request );

		return rest_ensure_response( $response );
	}

	/**
	 * Creates a single template.
	 *
	 * @since 5.8.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function create_item( $request ) {
		$prepared_post = $this->prepare_item_for_database( $request );

		if ( is_wp_error( $prepared_post ) ) {
			return $prepared_post;
		}

		$prepared_post->post_name = $request['slug'];
		$post_id                  = wp_insert_post( wp_slash( (array) $prepared_post ), true );
		if ( is_wp_error( $post_id ) ) {
			if ( 'db_insert_error' === $post_id->get_error_code() ) {
				$post_id->add_data( array( 'status' => 500 ) );
			} else {
				$post_id->add_data( array( 'status' => 400 ) );
			}

			return $post_id;
		}
		$posts = get_block_templates( array( 'wp_id' => $post_id ), $this->post_type );
		if ( ! count( $posts ) ) {
			return new WP_Error( 'rest_template_insert_error', __( 'No templates exist with that id.' ), array( 'status' => 400 ) );
		}
		$id            = $posts[0]->id;
		$post          = get_post( $post_id );
		$template      = gutenberg_get_block_template( $id, $this->post_type );
		$fields_update = $this->update_additional_fields_for_object( $template, $request );
		if ( is_wp_error( $fields_update ) ) {
			return $fields_update;
		}

		/** This action is documented in wp-includes/rest-api/endpoints/class-wp-rest-posts-controller.php */
		do_action( "rest_after_insert_{$this->post_type}", $post, $request, true );

		wp_after_insert_post( $post, false, null );

		$response = $this->prepare_item_for_response( $template, $request );
		$response = rest_ensure_response( $response );

		$response->set_status( 201 );
		$response->header( 'Location', rest_url( sprintf( '%s/%s/%s', $this->namespace, $this->rest_base, $template->id ) ) );

		return $response;
	}

	/**
	 * Deletes a single template.
	 *
	 * @since 5.8.0
	 *
	 * @param WP_REST_Request $request Full details about the request.
	 * @return WP_REST_Response|WP_Error Response object on success, or WP_Error object on failure.
	 */
	public function delete_item( $request ) {
		$template = gutenberg_get_block_template( $request['id'], $this->post_type );
		if ( ! $template ) {
			return new WP_Error( 'rest_template_not_found', __( 'No templates exist with that id.' ), array( 'status' => 404 ) );
		}
		if ( 'custom' !== $template->source ) {
			return new WP_Error( 'rest_invalid_template', __( 'Templates based on theme files can\'t be removed.' ), array( 'status' => 400 ) );
		}

		$id    = $template->wp_id;
		$force = (bool) $request['force'];

		$request->set_param( 'context', 'edit' );

		// If we're forcing, then delete permanently.
		if ( $force ) {
			$previous = $this->prepare_item_for_response( $template, $request );
			$result   = wp_delete_post( $id, true );
			$response = new WP_REST_Response();
			$response->set_data(
				array(
					'deleted'  => true,
					'previous' => $previous->get_data(),
				)
			);
		} else {
			// Otherwise, only trash if we haven't already.
			if ( 'trash' === $template->status ) {
				return new WP_Error(
					'rest_template_already_trashed',
					__( 'The template has already been deleted.' ),
					array( 'status' => 410 )
				);
			}

			/*
			 * (Note that internally this falls through to `wp_delete_post()`
			 * if the Trash is disabled.)
			 */
			$result           = wp_trash_post( $id );
			$template->status = 'trash';
			$response         = $this->prepare_item_for_response( $template, $request );
		}

		if ( ! $result ) {
			return new WP_Error(
				'rest_cannot_delete',
				__( 'The template cannot be deleted.' ),
				array( 'status' => 500 )
			);
		}

		return $response;
	}

	/**
	 * Prepares a single template for create or update.
	 *
	 * @since 5.8.0
	 *
	 * @param WP_REST_Request $request Request object.
	 * @return stdClass|WP_Error Changes to pass to wp_update_post.
	 */
	protected function prepare_item_for_database( $request ) {
		$template = $request['id'] ? gutenberg_get_block_template( $request['id'], $this->post_type ) : null;
		$changes  = new stdClass();
		if ( null === $template ) {
			$changes->post_type   = $this->post_type;
			$changes->post_status = 'publish';
			$changes->tax_input   = array(
				'wp_theme' => isset( $request['theme'] ) ? $request['theme'] : get_stylesheet(),
			);
		} elseif ( 'custom' !== $template->source ) {
			$changes->post_name   = $template->slug;
			$changes->post_type   = $this->post_type;
			$changes->post_status = 'publish';
			$changes->tax_input   = array(
				'wp_theme' => $template->theme,
			);
			$changes->meta_input  = array(
				'origin' => $template->source,
			);
		} else {
			$changes->post_name   = $template->slug;
			$changes->ID          = $template->wp_id;
			$changes->post_status = 'publish';
		}
		if ( isset( $request['content'] ) ) {
			if ( is_string( $request['content'] ) ) {
				$changes->post_content = $request['content'];
			} elseif ( isset( $request['content']['raw'] ) ) {
				$changes->post_content = $request['content']['raw'];
			}
		} elseif ( null !== $template && 'custom' !== $template->source ) {
			$changes->post_content = $template->content;
		}
		if ( isset( $request['title'] ) ) {
			if ( is_string( $request['title'] ) ) {
				$changes->post_title = $request['title'];
			} elseif ( ! empty( $request['title']['raw'] ) ) {
				$changes->post_title = $request['title']['raw'];
			}
		} elseif ( null !== $template && 'custom' !== $template->source ) {
			$changes->post_title = $template->title;
		}
		if ( isset( $request['description'] ) ) {
			$changes->post_excerpt = $request['description'];
		} elseif ( null !== $template && 'custom' !== $template->source ) {
			$changes->post_excerpt = $template->description;
		}

		if ( 'wp_template' === $this->post_type && isset( $request['is_wp_suggestion'] ) ) {
			$changes->meta_input     = wp_parse_args(
				array(
					'is_wp_suggestion' => $request['is_wp_suggestion'],
				),
				$changes->meta_input = array()
			);
		}

		if ( 'wp_template_part' === $this->post_type ) {
			if ( isset( $request['area'] ) ) {
				$changes->tax_input['wp_template_part_area'] = _filter_block_template_part_area( $request['area'] );
			} elseif ( null !== $template && 'custom' !== $template->source && $template->area ) {
				$changes->tax_input['wp_template_part_area'] = _filter_block_template_part_area( $template->area );
			} elseif ( empty( $template->area ) ) {
				$changes->tax_input['wp_template_part_area'] = WP_TEMPLATE_PART_AREA_UNCATEGORIZED;
			}
		}

		if ( ! empty( $request['author'] ) ) {
			$post_author = (int) $request['author'];

			if ( get_current_user_id() !== $post_author ) {
				$user_obj = get_userdata( $post_author );

				if ( ! $user_obj ) {
					return new WP_Error(
						'rest_invalid_author',
						__( 'Invalid author ID.' ),
						array( 'status' => 400 )
					);
				}
			}

			$changes->post_author = $post_author;
		}

		/** This filter is documented in wp-includes/rest-api/endpoints/class-wp-rest-posts-controller.php */
		return apply_filters( "rest_pre_insert_{$this->post_type}", $changes, $request );
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

	/**
	 * Prepares links for the request.
	 *
	 * @since 5.8.0
	 *
	 * @param integer $id ID.
	 * @return array Links for the given post.
	 */
	protected function prepare_links( $id ) {
		$links = array(
			'self'       => array(
				'href' => rest_url( sprintf( '/%s/%s/%s', $this->namespace, $this->rest_base, $id ) ),
			),
			'collection' => array(
				'href' => rest_url( rest_get_route_for_post_type_items( $this->post_type ) ),
			),
			'about'      => array(
				'href' => rest_url( 'wp/v2/types/' . $this->post_type ),
			),
		);

		if ( post_type_supports( $this->post_type, 'revisions' ) ) {
			$template = gutenberg_get_block_template( $id, $this->post_type );
			if ( $template instanceof WP_Block_Template && ! empty( $template->wp_id ) ) {
				$revisions       = wp_get_latest_revision_id_and_total_count( $template->wp_id );
				$revisions_count = ! is_wp_error( $revisions ) ? $revisions['count'] : 0;
				$revisions_base  = sprintf( '/%s/%s/%s/revisions', $this->namespace, $this->rest_base, $id );

				$links['version-history'] = array(
					'href'  => rest_url( $revisions_base ),
					'count' => $revisions_count,
				);

				if ( $revisions_count > 0 ) {
					$links['predecessor-version'] = array(
						'href' => rest_url( $revisions_base . '/' . $revisions['latest_id'] ),
						'id'   => $revisions['latest_id'],
					);
				}
			}
		}

		return $links;
	}
}
