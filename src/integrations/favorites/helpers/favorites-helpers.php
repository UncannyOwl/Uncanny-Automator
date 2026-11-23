<?php

namespace Uncanny_Automator\Integrations\Favorites;

use Favorites\Config\SettingsRepository;
use Favorites\Entities\User\UserRepository;
use Uncanny_Automator\Recipe\Abstract_Helpers;

/**
 * Class Favorites_Helpers
 *
 * @package Uncanny_Automator
 */
class Favorites_Helpers extends Abstract_Helpers {

	/**
	 * Whether the post is in the user's saved favourites.
	 *
	 * The hook's $status is what the browser asked for; the plugin toggles on the
	 * stored state instead, so only this answers what actually happened.
	 *
	 * @param int $post_id The post ID.
	 * @param int $site_id The site ID.
	 * @param int $user_id The user ID.
	 *
	 * @return bool
	 */
	public function is_user_favorite( $post_id, $site_id, $user_id ) {
		return (bool) ( new UserRepository() )->isFavorite( absint( $post_id ), absint( $site_id ), absint( $user_id ) );
	}

	/**
	 * The post IDs a user has favourited on a site, as a flat list.
	 *
	 * @param int $user_id The user ID.
	 * @param int $site_id The site ID.
	 *
	 * @return int[]
	 */
	public function get_user_favorite_ids( $user_id, $site_id ) {
		$favorites = ( new UserRepository() )->getFavorites( absint( $user_id ), absint( $site_id ) );

		return array_map( 'absint', (array) $favorites );
	}

	/**
	 * Public post types the plugin is set to display a favourite button on.
	 *
	 * @param bool $include_any Whether to include the "Any post type" option.
	 *
	 * @return array
	 */
	public function get_enabled_post_types( $include_any = false ) {

		$options = array();

		if ( true === $include_any ) {
			$options[] = array(
				'text'  => esc_html_x( 'Any post type', 'Favorites', 'uncanny-automator' ),
				'value' => '-1',
			);
		}

		$settings   = new SettingsRepository();
		$post_types = get_post_types( array( 'public' => true ), 'objects' );

		foreach ( $post_types as $post_type ) {
			if ( ! $settings->displayInPostType( $post_type->name ) ) {
				continue;
			}

			$options[] = array(
				'text'  => $post_type->labels->singular_name,
				'value' => $post_type->name,
			);
		}

		return $options;
	}

	/**
	 * Published posts of a post type as dropdown options.
	 *
	 * @param string $post_type   The post type slug.
	 * @param bool   $include_any Whether to include the "Any post" option.
	 *
	 * @return array
	 */
	public function get_posts_by_type( $post_type = 'post', $include_any = false ) {

		$options = array();

		if ( true === $include_any ) {
			$options[] = array(
				'text'  => esc_html_x( 'Any post', 'Favorites', 'uncanny-automator' ),
				'value' => '-1',
			);
		}

		$posts = get_posts(
			array(
				'post_type'      => sanitize_text_field( $post_type ),
				'posts_per_page' => 999,
				'orderby'        => 'title',
				'order'          => 'ASC',
				'post_status'    => 'publish',
			)
		);

		foreach ( $posts as $post ) {
			$options[] = array(
				'text'  => $post->post_title,
				'value' => (string) $post->ID,
			);
		}

		return $options;
	}

	/**
	 * Posts of the selected type, with the "Any post" sentinel first.
	 *
	 * @param mixed $request The remote-data request.
	 *
	 * @return array
	 */
	protected function remote_data_get_posts( $request ): array {

		$post_type = $request->get_field_value( 'FAVORITES_POST_TYPE' );

		if ( empty( $post_type ) || '-1' === (string) $post_type ) {
			return $this->remote_data_success(
				array(
					array(
						'text'  => esc_html_x( 'Any post', 'Favorites', 'uncanny-automator' ),
						'value' => '-1',
					),
				)
			);
		}

		return $this->remote_data_success( $this->get_posts_by_type( $post_type, true ) );
	}

	/**
	 * The Post type + Post selector shared by the favourite and unfavourite triggers.
	 *
	 * Neither field adds a token of its own; the triggers declare every token.
	 *
	 * @param string $trigger_meta The trigger meta code for the post field.
	 *
	 * @return array
	 */
	public function get_trigger_post_type_and_post_options( $trigger_meta ) {

		return array(
			array(
				'option_code'     => 'FAVORITES_POST_TYPE',
				'label'           => esc_html_x( 'Post type', 'Favorites', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => $this->get_enabled_post_types( true ),
				'relevant_tokens' => array(),
			),
			array(
				'option_code'     => $trigger_meta,
				'label'           => esc_html_x( 'Post', 'Favorites', 'uncanny-automator' ),
				'input_type'      => 'select',
				'required'        => true,
				'options'         => array(),
				'relevant_tokens' => array(),
				'remote_data'     => $this->remote_data_parent_config( 'posts', array( 'FAVORITES_POST_TYPE' ) ),
			),
		);
	}

	/**
	 * Whether the favourited post passes the trigger's Post type and Post filters.
	 *
	 * @param array  $trigger      The trigger settings.
	 * @param string $trigger_meta The trigger meta code for the post field.
	 * @param int    $post_id      The post ID.
	 * @param int    $site_id      The site the post belongs to.
	 *
	 * @return bool
	 */
	public function post_passes_filters( $trigger, $trigger_meta, $post_id, $site_id ) {

		if ( ! isset( $trigger['meta'][ $trigger_meta ], $trigger['meta']['FAVORITES_POST_TYPE'] ) ) {
			return false;
		}

		$post_type = $this->get_post_type_on_site( $post_id, $site_id );

		if ( '' === $post_type ) {
			return false;
		}

		$selected_post_type = $trigger['meta']['FAVORITES_POST_TYPE'];

		if ( '-1' !== (string) $selected_post_type && $post_type !== $selected_post_type ) {
			return false;
		}

		$selected_post = $trigger['meta'][ $trigger_meta ];

		if ( '-1' !== (string) $selected_post && absint( $post_id ) !== absint( $selected_post ) ) {
			return false;
		}

		return true;
	}

	/**
	 * Token values shared by the favourite and unfavourite triggers.
	 *
	 * @param int $post_id The post ID.
	 * @param int $site_id The site the post belongs to.
	 *
	 * @return array
	 */
	public function post_token_values( $post_id, $site_id ) {

		$fields = $this->read_post_on_site( $post_id, $site_id );

		return array(
			'POST_ID'         => absint( $post_id ),
			'POST_TITLE'      => $fields['title'],
			'POST_URL'        => $fields['url'],
			'POST_TYPE'       => $fields['type'],
			'FAVORITE_COUNT'  => $this->get_post_favourite_count( $post_id ),
			'SITE_ID'         => absint( $site_id ),
		);
	}

	/**
	 * How many users have favourited a post, on the site the plugin counts on.
	 *
	 * @param int $post_id The post ID.
	 *
	 * @return int
	 */
	public function get_post_favourite_count( $post_id ) {
		return (int) get_favorites_count( absint( $post_id ), null, false );
	}

	/**
	 * The post type of a post, read on the site it belongs to.
	 *
	 * @param int $post_id The post ID.
	 * @param int $site_id The site the post belongs to.
	 *
	 * @return string Empty when the post is missing.
	 */
	public function get_post_type_on_site( $post_id, $site_id ) {

		$switched = $this->maybe_switch_to_site( $site_id );

		$post_type = get_post_type( absint( $post_id ) );

		if ( $switched ) {
			restore_current_blog();
		}

		return false === $post_type ? '' : $post_type;
	}

	/**
	 * Title, URL and type of a post, read on the site it belongs to.
	 *
	 * @param int $post_id The post ID.
	 * @param int $site_id The site the post belongs to.
	 *
	 * @return array
	 */
	private function read_post_on_site( $post_id, $site_id ) {

		$post_id  = absint( $post_id );
		$switched = $this->maybe_switch_to_site( $site_id );

		$post_type = get_post_type( $post_id );
		$permalink = get_permalink( $post_id );

		$fields = array(
			'title' => (string) get_the_title( $post_id ),
			'url'   => false === $permalink ? '' : $permalink,
			'type'  => false === $post_type ? '' : $post_type,
		);

		if ( $switched ) {
			restore_current_blog();
		}

		return $fields;
	}

	/**
	 * Switch to another site on multisite when the post lives elsewhere.
	 *
	 * @param int $site_id The site the post belongs to.
	 *
	 * @return bool Whether a switch happened and must be restored.
	 */
	private function maybe_switch_to_site( $site_id ) {

		$site_id = absint( $site_id );

		if ( ! is_multisite() || $site_id < 1 || get_current_blog_id() === $site_id ) {
			return false;
		}

		switch_to_blog( $site_id );

		return true;
	}
}
