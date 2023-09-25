<?php
namespace Uncanny_Automator\Services\Recipe\Structure;

use Uncanny_Automator\Services\Recipe\Common;

/**
 * Represents the miscellaneous object inside the recipe object.
 *
 * @package Uncanny_Automator\Services\Recipe\Structure
 *
 * @since 5.0
 */
final class Miscellaneous implements \JsonSerializable {

	/**
	 * @since 5.0
	 */
	use Common\Trait_JSON_Serializer;

	/**
	 * @since 5.1
	 */
	use Common\Trait_Setter_Getter;

	protected $created_on_date                = null;
	protected $has_loop                       = false;
	protected $has_loop_running               = false;
	protected $created_with_automator_version = null;
	protected $limit_per_recipe               = -1;
	protected $limit_per_user                 = -1;
	protected $url_duplicate_recipe           = null;
	protected $url_trash_recipe               = null;
	protected $url_logs                       = null;

	/**
	 * @var \Uncanny_Automator\Services\Recipe\Structure
	 */
	private static $recipe = null;

	public function __construct( \Uncanny_Automator\Services\Recipe\Structure $recipe ) {
		self::$recipe = $recipe;
		$this->hydrate_properties();
	}

	/**
	 * Hydrates object properties.
	 *
	 * @return self
	 */
	private function hydrate_properties() {

		$meta = self::$recipe->meta();

		$recipe_id = self::$recipe->get_recipe_id();

		$this->has_loop                       = $this->has_loop();
		$this->has_loop_running               = $this->has_loop_running();
		$this->created_on_date                = get_the_date( '', $recipe_id );
		$this->created_with_automator_version = isset( $meta['uap_recipe_version'] ) ? $meta['uap_recipe_version'] : null;
		$this->limit_per_recipe               = (int) $meta['recipe_max_completions_allowed'];
		$this->limit_per_user                 = isset( $meta['recipe_completions_allowed'] ) ? (int) $meta['recipe_completions_allowed'] : null;
		$this->url_duplicate_recipe           = sprintf( '%s?action=%s&post=%d&return_to_recipe=yes&_wpnonce=%s', admin_url( 'edit.php' ), 'copy_recipe_parts', $recipe_id, wp_create_nonce( 'Aut0Mat0R' ) );
		$this->url_trash_recipe               = get_delete_post_link( $recipe_id );
		$this->url_logs                       = sprintf( '%s?post_type=uo-recipe&page=uncanny-automator-admin-logs&recipe_id=%d', admin_url( 'edit.php' ), $recipe_id );

		return $this;

	}

	/**
	 * Determines if the current recipe has a loop block on it.
	 *
	 * @return bool
	 */
	public function has_loop() {

		return ! empty( Automator()->loop_db()->find_recipe_loops( absint( self::$recipe->get_recipe_id() ) ) );

	}

	/**
	 * Determines if the current recipe has loop that is running.
	 *
	 * @return bool
	 */
	public function has_loop_running() {

		return apply_filters( 'automator_recipe_has_loop_running', $this->has_loop_running, absint( self::$recipe->get_recipe_id() ) );

	}

}
