<?php
/**
 * Block template functions.
 *
 * @package gutenberg
 */

final class WP_Block_Templates_Registry {
	private $registered_block_templates = array(
		'wp_template'      => array(),
		'wp_template_part' => array(),
	);
	private static $instance            = null;

	public function register( $template_name, $template_type, $args = array() ) {

		$template = null;
		if ( $template_name instanceof WP_Block_Template ) {
			$template      = $template_name;
			$template_name = $template->name;
		}


		if ( ! is_string( $template_name ) ) {
			_doing_it_wrong(
				__METHOD__,
				__( 'Block template names must be strings.', 'gutenberg' ),
				'6.6.0'
			);
			return false;
		}

		if ( 'wp_template' !== $template_type && 'wp_template_part' !== $template_type ) {
			_doing_it_wrong(
				__METHOD__,
				__( 'Block templates need to be of `wp_template` or `wp_template_part` type.', 'gutenberg' ),
				'6.6.0'
			);
			return false;
		}

		if ( preg_match( '/[A-Z]+/', $template_name ) ) {
			_doing_it_wrong(
				__METHOD__,
				__( 'Block template names must not contain uppercase characters.', 'gutenberg' ),
				'6.6.0'
			);
			return false;
		}

		$name_matcher = '/^[a-z0-9-]+\/\/[a-z0-9-]+$/';
		if ( ! preg_match( $name_matcher, $template_name ) ) {
			_doing_it_wrong(
				__METHOD__,
				__( 'Block template names must contain a namespace prefix. Example: my-plugin/my-custom-template', 'gutenberg' ),
				'6.6.0'
			);
			return false;
		}

		if ( $this->is_registered( $template_type, $template_name ) ) {
			_doing_it_wrong(
				__METHOD__,
				/* translators: %s: Template name. */
				sprintf( __( 'Template "%s" is already registered.', 'gutenberg' ), $template_name ),
				'6.6.0'
			);
			return false;
		}

		if ( ! $template ) {
			$theme_name               = get_stylesheet();
			$template                 = new WP_Block_Template();
			$template->id             = $theme_name . '//' . $args['slug'];
			$template->theme          = $theme_name; // @todo If not attached to the theme, this should be the plugin URI.
			$template->plugin         = array_key_exists( 'plugin', $args ) ? $args['plugin'] : '';
			$template->author         = null;
			$template->content        = array_key_exists( 'path', $args ) ? file_get_contents( $args['path'] ) : '';
			$template->source         = 'plugin';
			$template->slug           = array_key_exists( 'slug', $args ) ? $args['slug'] : '';
			$template->type           = $template_type;
			$template->title          = array_key_exists( 'title', $args ) ? $args['title'] : '';
			$template->description    = array_key_exists( 'description', $args ) ? $args['description'] : '';
			$template->status         = 'publish';
			$template->has_theme_file = true;
			$template->origin         = 'plugin';
			$template->is_custom      = false;
			$template->post_types     = array_key_exists( 'post_types', $args ) ? $args['post_types'] : '';
			$template->area           = 'wp_template_part' === $template_type && array_key_exists( 'area', $args ) ? $args['area'] : '';
		}

		$this->registered_block_templates[ $template_type ][ $template_name ] = $template;

		return $template;
	}

	public function get_by_slug( $template_type, $template_slug ) {
		$all_templates = $this->get_all_registered( $template_type );

		if ( ! $all_templates ) {
			return null;
		}

		foreach ( $all_templates as $template ) {
			if ( $template->slug === $template_slug ) {
				return $template;
			}
		}

		return null;
	}

	/**
	 * Retrieves a registered template.
	 *
	 * @since 6.6.0
	 *
	 * @param string $template_type Template type, either `wp_template` or `wp_template_part`.
	 * @param string $template_name Block type name including namespace.
	 * @return WP_Block_Type|null The registered block type, or null if it is not registered.
	 */
	public function get_registered( $template_type, $template_name ) {
		if ( 'wp_template' !== $template_type && 'wp_template_part' !== $template_type ) {
			_doing_it_wrong(
				__METHOD__,
				__( 'Only valid block template types are `wp_template` and `wp_template_part`.', 'gutenberg' ),
				'6.6.0'
			);
			return false;
		}

		if ( ! $this->is_registered( $template_type, $template_name ) ) {
			return null;
		}

		return $this->registered_block_templates[ $template_type ][ $template_name ];
	}

	/**
	 * Retrieves all registered block templates by type.
	 *
	 * @since 6.6.0
	 *
	 * @param string $template_type Template type, either `wp_template` or `wp_template_part`.
	 * @return WP_Block_Template[] Associative array of `$block_type_name => $block_type` pairs.
	 */
	public function get_all_registered( $template_type ) {
		if ( 'wp_template' !== $template_type && 'wp_template_part' !== $template_type ) {
			_doing_it_wrong(
				__METHOD__,
				__( 'Only valid block template types are `wp_template` and `wp_template_part`.', 'gutenberg' ),
				'6.6.0'
			);
			return false;
		}

		return $this->registered_block_templates[ $template_type ];
	}

	/**
	 * Retrieves all registered block templates by type.
	 *
	 * @since 6.6.0
	 *
	 * @param string $template_type Template type. Either 'wp_template' or 'wp_template_part'.
	 * @param array  $query {
	 *     Arguments to retrieve templates. Optional, empty by default.
	 *
	 *     @type string[] $slug__in     List of slugs to include.
	 *     @type string[] $slug__not_in List of slugs to skip.
	 *     @type string   $area         A 'wp_template_part_area' taxonomy value to filter by (for 'wp_template_part' template type only).
	 *     @type string   $post_type    Post type to get the templates for.
	 * }
	 */
	public function get_by_query( $template_type, $query = array() ) {
		$all_templates = $this->get_all_registered( $template_type );

		if ( ! $all_templates ) {
			return array();
		}

		$slugs_to_include = isset( $query['slug__in'] ) ? $query['slug__in'] : array();
		$slugs_to_skip    = isset( $query['slug__not_in'] ) ? $query['slug__not_in'] : array();
		$area             = isset( $query['area'] ) ? $query['area'] : null;
		$post_type        = isset( $query['post_type'] ) ? $query['post_type'] : '';

		foreach ( $all_templates as $template_name => $template ) {
			if ( ! empty( $slugs_to_include ) && ! in_array( $template->slug, $slugs_to_include, true ) ) {
				unset( $all_templates[ $template_name ] );
			}

			if ( ! empty( $slugs_to_skip ) && in_array( $template->slug, $slugs_to_skip, true ) ) {
				unset( $all_templates[ $template_name ] );
			}

			if ( 'wp_template_part' === $template_type && $template->area !== $area ) {
				unset( $all_templates[ $template_name ] );
			}

			if ( ! empty( $post_type ) && ! in_array( $post_type, $template->post_types, true ) ) {
				unset( $all_templates[ $template_name ] );
			}
		}

		return $all_templates;
	}

	/**
	 * Checks if a block template is registered.
	 *
	 * @since 6.6.0
	 *
	 * @param string $template_type Template type, either `wp_template` or `wp_template_part`.
	 * @param string $template_name Block type name including namespace.
	 * @return bool True if the template is registered, false otherwise.
	 */
	public function is_registered( $template_type, $template_name ) {
		if ( 'wp_template' !== $template_type && 'wp_template_part' !== $template_type ) {
			_doing_it_wrong(
				__METHOD__,
				__( 'Only valid block template types are `wp_template` and `wp_template_part`.', 'gutenberg' ),
				'6.6.0'
			);
			return false;
		}

		return isset( $this->registered_block_templates[ $template_type ][ $template_name ] );
	}

	public function unregister( $template_name ) {
		// @todo
	}

	/**
	 * Utility method to retrieve the main instance of the class.
	 *
	 * The instance will be created if it does not exist yet.
	 *
	 * @since 6.6.0
	 *
	 * @return WP_Block_Templates_Registry The main instance.
	 */
	public static function get_instance() {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}
}
