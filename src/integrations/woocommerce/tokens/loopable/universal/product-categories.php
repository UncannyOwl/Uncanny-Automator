<?php
namespace Uncanny_Automator\Integrations\Woocommerce\Tokens\Loopable\Universal;

// Uses WP Integration Utility.
use Uncanny_Automator\Integrations\Wp\Tokens\Loopable\Universal\Utils\Taxonomy_Fetcher;

use Uncanny_Automator\Services\Loopable\Loopable_Token_Collection;
use Uncanny_Automator\Services\Loopable\Universal_Loopable_Token;

/**
 * Product_Categories
 *
 * @package Uncanny_Automator\Integrations\Buddypress\Tokens\Loopable\Universal
 */
class Product_Categories extends Universal_Loopable_Token {

	/**
	 * Register loopable token.
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

		$this->set_id( 'WOO_PRODUCT_CATEGORIES' );
		$this->set_name( _x( 'All products categories', 'Woo', 'uncanny-automator' ) );
		$this->set_log_identifier( '#{{CAT_ID}} {{CAT_NAME}}' );
		$this->set_child_tokens( $child_tokens );
		$this->set_requires_user( false );

	}

	/**
	 * Hydrate the tokens.
	 *
	 * @param mixed $args
	 *
	 * @return Loopable_Token_Collection
	 */
	public function hydrate_token_loopable( $args ) {

		$loopable = new Loopable_Token_Collection();

		$cats = Taxonomy_Fetcher::get_terms_list( 'product_cat' );

		// Bail.
		if ( false === $cats ) {
			return $loopable;
		}

		foreach ( $cats as $cat ) {
			$loopable->create_item(
				array(
					'CAT_ID'   => $cat['term_id'],
					'CAT_NAME' => $cat['term_name'],
				)
			);
		}

		return $loopable;

	}

}
