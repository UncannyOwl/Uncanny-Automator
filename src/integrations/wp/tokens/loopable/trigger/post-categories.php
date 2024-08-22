<?php
namespace Uncanny_Automator\Integrations\Wp\Tokens\Trigger\Loopable;

use Exception;
use Uncanny_Automator\Services\Loopable\Loopable_Token_Collection;
use Uncanny_Automator\Services\Loopable\Trigger_Loopable_Token;

/**
 * Loopable Order Items.
 *
 * @since 5.10
 *
 * @package Uncanny_Automator\Integrations\Wp\Tokens\Loopable
 */
class Post_Categories extends Trigger_Loopable_Token {

	/**
	 * Register loopable tokens.
	 *
	 * @return void
	 */
	public function register_loopable_token() {

		$child_tokens = array(
			'CAT_ID'   => array(
				'name'       => _x( 'Category ID', 'Woo', 'uncanny-automator' ),
				'token_type' => 'integer',
			),
			'CAT_NAME' => array(
				'name' => _x( 'Category name', 'Woo', 'uncanny-automator' ),
			),
		);

		$this->set_id( 'WP_POST_CATEGORIES' );
		$this->set_name( _x( 'Post categories', 'Woo', 'uncanny-automator' ) );
		$this->set_log_identifier( '#{{CAT_ID}} {{CAT_NAME}}' );
		$this->set_child_tokens( $child_tokens );

	}

	/**
	 * Hydrate the tokens.
	 *
	 * @param mixed $trigger_args
	 *
	 * @return Loopable_Token_Collection
	 */
	public function hydrate_token_loopable( $trigger_args ) {

		$loopable = new Loopable_Token_Collection();

		// Retrieve the post id from the trigger args.
		$post_id = $this->get_post_id( $trigger_args );

		$categories = get_the_category( absint( $post_id ) );

		if ( ! empty( $categories ) ) {
			foreach ( $categories as $category ) {
				$loopable->create_item(
					array(
						'CAT_ID'   => $category->term_id ?? '',
						'CAT_NAME' => $category->name ?? '',
					)
				);
			}
		}

		return $loopable;

	}


	/**
	 * @param mixed[] $trigger_args
	 *
	 * @return int|false The post ID. Otherwise, false.
	 */
	public function get_post_id( $trigger_args ) {

		// Always log the $trigger_args to inspect.

		$trigger      = $this->get_trigger();
		$trigger_code = $trigger['code'] ?? '';

		$post_id = 0;

		switch ( $trigger_code ) {

			# Triggers where post ID is in index 2.
			case 'WP_POST_PUBLISHED_IN_TAXONOMY':
			case 'ANON_POST_UPDATED_IN_TAXONOMY':
			case 'WPPOSTSTATUS':
			case 'ANON_WPPOSTSTATUS':
				$post_id = $trigger_args[2]->ID ?? '';
				break;
			# Triggers where post ID is in index 0.
			case 'ANON_POST_UPDATED_IN_TAXONOMY':
			case 'WP_ANON_POST_UPDATED':
			case 'WP_USER_POST_UPDATED':
			case 'WP_USER_POST_PUBLISHED':
				$post_id = $trigger_args[0][0] ?? 0;
				break;
			case 'WPPOSTINSTATUS':
			case 'WPPOSTINTAXONOMY':
			case 'WPPOSTUPDATED':
				$post_id = $trigger_args[0] ?? 0;
				break;
		}

		return absint( $post_id );

	}

}
