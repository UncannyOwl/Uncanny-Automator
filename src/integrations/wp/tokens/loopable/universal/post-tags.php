<?php
namespace Uncanny_Automator\Integrations\WP\Tokens\Loopable\Universal;

use Uncanny_Automator\Integrations\Wp\Tokens\Loopable\Universal\Utils\Taxonomy_Fetcher;
use Uncanny_Automator\Services\Loopable\Loopable_Token_Collection;
use Uncanny_Automator\Services\Loopable\Universal_Loopable_Token;

/**
 * Post_Tags
 *
 * @package Uncanny_Automator\Integrations\Buddypress\Tokens\Loopable\Universal
 */
class Post_Tags extends Universal_Loopable_Token {

	/**
	 * Register loopable token.
	 *
	 * @return void
	 */
	public function register_loopable_token() {

		$child_tokens = array(
			'TAG_ID'   => array(
				'name'       => _x( 'Tag ID', 'WordPress', 'uncanny-automator' ),
				'token_type' => 'integer',
			),
			'TAG_NAME' => array(
				'name' => _x( 'Tag name', 'WordPress', 'uncanny-automator' ),
			),
		);

		$this->set_id( 'WP_POST_TAGS' );
		$this->set_name( _x( 'All posts tags', 'WordPress', 'uncanny-automator' ) );
		$this->set_log_identifier( '#{{TAG_ID}} {{TAG_NAME}}' );
		$this->set_child_tokens( $child_tokens );
		$this->set_requires_user( false );

	}

	/**
	 * Hydrate the tokens.
	 *
	 * @param mixed $args
	 * @return Loopable_Token_Collection
	 */
	public function hydrate_token_loopable( $args ) {

		$loopable = new Loopable_Token_Collection();

		$tags = Taxonomy_Fetcher::get_terms_list( 'post_tag' );

		// Bail.
		if ( false === $tags ) {
			return $loopable;
		}

		foreach ( $tags as $tag ) {
			$loopable->create_item(
				array(
					'TAG_ID'   => $tag['term_id'],
					'TAG_NAME' => $tag['term_name'],
				)
			);
		}

		return $loopable;

	}

}
