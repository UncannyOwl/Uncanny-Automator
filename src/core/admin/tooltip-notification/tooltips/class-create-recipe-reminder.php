<?php

namespace Uncanny_Automator;

// Include the base class file
require_once dirname( __FILE__ ) . '/../trait-tooltip-notification.php';

/**
 * Class Automator_Tooltip_48hr
 *
 * Handles the tooltip that appears 48 hours after installation if no recipe is created.
 */
class Automator_Tooltip_48hr {
	use Automator_Tooltip_Trait;

	/**
	 * Time period to check if a recipe hasn't been created (in seconds).
	 *
	 * @var int
	 */
	protected $recipe_creation_threshold = 48 * HOUR_IN_SECONDS;

	/**
	 * Automator_Tooltip_48hr constructor.
	 */
	public function __construct() {
		// Set the tooltip ID
		$this->tooltip_id = 'create-recipe-reminder';

		// Set the parent selector
		$this->parent_selector = '#menu-posts-uo-recipe';

		// Set the element position
		// 'beforebegin': Before the targetElement itself.
		// 'afterbegin': Just inside the targetElement, before its first child.
		// 'beforeend': Just inside the targetElement, after its last child.
		// 'afterend': After the targetElement itself.
		$this->element_position = 'beforebegin';

		// Init the tooltip
		$this->init();
	}

	/**
	 * Determine if the tooltip should be shown based on the parent logic and additional checks.
	 *
	 * @return bool True if the tooltip should be shown, false otherwise.
	 */
	protected function should_display_tooltip() {
		// Check if a recipe hasn't been created
		if ( ! $this->is_recipe_creation_pending() ) {
			return false;
		}

		return true;
	}

	/**
	 * Check if a recipe (post type `uo-recipe`) hasn't been created within 48 hours after installation.
	 *
	 * @return bool True if no recipe has been created, false otherwise.
	 */
	private function is_recipe_creation_pending() {
		$args  = array(
			'post_type'      => 'uo-recipe',
			'posts_per_page' => 1,
		);
		$query = new \WP_Query( $args );

		if ( $query->have_posts() ) {
			return false; // A recipe has been created, no need to show the tooltip.
		}

		// Get the plugin installation time from the 'automator_over_time' option
		$automator_over_time = automator_get_option( 'automator_over_time' );
		$installation_time   = isset( $automator_over_time['installed_date'] ) ? $automator_over_time['installed_date'] : 0;

		// Check if the installation time is more than 48 hours ago
		if ( $installation_time && ( time() - $installation_time > $this->recipe_creation_threshold ) ) {
			return true;
		}

		return false;
	}
}
