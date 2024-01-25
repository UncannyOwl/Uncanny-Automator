<?php

namespace Uncanny_Automator;

/**
 * Class Blocks
 *
 * @package Uncanny_Automator
 */
class Blocks {

	const CATEGORY = 'uncanny-automator';

	/**
	 * Blocks class constructor.
	 */
	public function __construct() {
		$this->add_filters();
	}

	/**
	 * Add filters
	 */
	public function add_filters() {

		// Register the Automator block category
		if ( version_compare( get_bloginfo( 'version' ), '5.8', '>=' ) ) {
			add_filter( 'block_categories_all', array( $this, 'register_category' ) );
		} else {
			add_filter( 'block_categories', array( $this, 'register_category' ) );
		}

	}

	/**
	 * Register the Automator block category
	 *
	 * @param array $categories
	 *
	 * @return array
	 */
	function register_category( $categories ) {

		// Check if the automator slug exists, otherwise create it.
		$category_slugs = wp_list_pluck( $categories, 'slug' );
		if ( in_array( self::CATEGORY, $category_slugs, true ) ) {
			return $categories;
		}

		$categories[] = array(
			'slug'  => self::CATEGORY,
			'title' => 'Uncanny Automator',
			'icon'  => null,
		);

		return $categories;
	}

}
