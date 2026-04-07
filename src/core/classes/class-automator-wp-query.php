<?php

namespace Uncanny_Automator;

/**
 * Clean replacement for the legacy wp_query() method on Automator_Helpers_Recipe.
 *
 * Uses get_posts() instead of raw SQL, removes extract(), returns modern value/text format.
 *
 * @since 7.2
 */
class Automator_WP_Query {

	/**
	 * Per-request cache keyed on full query args.
	 *
	 * @var array
	 */
	private $cache = array();

	/**
	 * Default parameters.
	 *
	 * @var array
	 */
	private $defaults = array(
		'post_type'      => 'page',
		'post_status'    => 'publish',
		'posts_per_page' => 99999, // phpcs:ignore WordPress.WP.PostsPerPage.posts_per_page_posts_per_page
		'orderby'        => 'title',
		'order'          => 'ASC',
		'post_parent'    => null,
		'meta_query'     => array(),
		'include_any'    => false,
		'any_label'      => null,
		'include_all'    => false,
		'all_label'      => null,
	);

	/**
	 * Public orchestrator. Returns value/text array.
	 *
	 * @param array  $params Query parameters.
	 * @param string $format 'modern' for value/text arrays, 'legacy' for id => title.
	 *
	 * @return array
	 */
	public function post_options( array $params = array(), string $format = 'modern' ) {

		$this->deprecate_removed_filters();

		$params  = $this->parse_params( $params );
		$args    = $this->build_query_args( $params );
		$posts   = $this->fetch_posts( $args );
		$options = $this->format_options( $posts );
		$options = $this->prepend_sentinels( $options, $params );

		/**
		 * Filter the final options array.
		 *
		 * @param array $options Value/text pairs.
		 * @param array $params  Parsed parameters.
		 */
		$options = apply_filters( 'automator_post_options', $options, $params );

		if ( 'legacy' === $format ) {
			return array_column( $options, 'text', 'value' );
		}

		return $options;
	}

	/**
	 * Merge user params with defaults.
	 *
	 * @param array $params Raw parameters.
	 *
	 * @return array
	 */
	private function parse_params( array $params ) {
		return wp_parse_args( $params, $this->defaults );
	}

	/**
	 * Translate our params into get_posts arguments.
	 *
	 * @param array $params Parsed parameters.
	 *
	 * @return array
	 */
	private function build_query_args( array $params ) {

		// Strip Automator-specific keys — everything else passes through to get_posts().
		$custom_keys = array( 'include_any', 'any_label', 'include_all', 'all_label' );
		$args        = array_diff_key( $params, array_flip( $custom_keys ) );

		// Ensure performance defaults.
		$args['no_found_rows']          = true;
		$args['update_post_meta_cache'] = false;
		$args['update_post_term_cache'] = false;

		return $args;
	}

	/**
	 * Execute get_posts.
	 *
	 * @param array $args get_posts arguments.
	 *
	 * @return \WP_Post[]
	 */
	private function fetch_posts( array $args ) {

		$key = md5( wp_json_encode( $args ) );

		if ( ! isset( $this->cache[ $key ] ) ) {
			$this->cache[ $key ] = get_posts( $args );
		}

		return $this->cache[ $key ];
	}

	/**
	 * Convert WP_Post objects to value/text arrays.
	 *
	 * @param \WP_Post[] $posts Array of post objects.
	 *
	 * @return array
	 */
	private function format_options( array $posts ) {

		$options = array();

		foreach ( $posts as $post ) {
			$title = $post->post_title;

			if ( empty( $title ) ) {
				/* translators: %1$s is the post ID */
				$title = sprintf( _x( 'ID: %1$s (no title)', 'Uncanny Automator', 'uncanny-automator' ), $post->ID ); // phpcs:ignore Uncanny_Automator.Strings.AutoContextTranslation.MissingContext -- Data layer; escaped at render time.
			}

			$options[] = array(
				'value' => (string) $post->ID,
				'text'  => $title,
			);
		}

		return $options;
	}

	/**
	 * Prepend Any/All sentinel options.
	 *
	 * @param array $options Formatted options.
	 * @param array $params  Parsed parameters.
	 *
	 * @return array
	 */
	private function prepend_sentinels( array $options, array $params ) {

		// include_all takes precedence over include_any.
		$sentinels = array(
			'all' => _x( 'All', 'Uncanny Automator', 'uncanny-automator' ), // phpcs:ignore Uncanny_Automator.Strings.AutoContextTranslation.MissingContext -- Data layer; escaped at render time.
			'any' => _x( 'Any', 'Uncanny Automator', 'uncanny-automator' ), // phpcs:ignore Uncanny_Automator.Strings.AutoContextTranslation.MissingContext -- Data layer; escaped at render time.
		);

		foreach ( $sentinels as $key => $default_label ) {
			if ( true === $params[ "include_{$key}" ] ) {
				array_unshift(
					$options,
					array(
						'value' => '-1',
						'text'  => $params[ "{$key}_label" ] ?? $default_label,
					)
				);
				break;
			}
		}

		return $options;
	}

	/**
	 * Fire deprecation notices for filters removed during the wp_query() rewrite.
	 *
	 * Only triggers when a third-party has actually hooked into the filter.
	 *
	 * @return void
	 */
	private function deprecate_removed_filters() {

		if ( has_filter( 'automator_maybe_modify_wp_query' ) ) {
			_deprecated_hook(
				'automator_maybe_modify_wp_query',
				'7.2',
				'automator_post_options',
				'Raw SQL queries have been replaced with get_posts(). Use the automator_post_options filter to modify results.'
			);
		}

		if ( has_filter( 'automator_modify_transient_options' ) ) {
			_deprecated_hook(
				'automator_modify_transient_options',
				'7.2',
				'automator_post_options',
				'Transient caching has been replaced with per-request caching. Use the automator_post_options filter to modify results.'
			);
		}
	}
}
