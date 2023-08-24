<?php
namespace Uncanny_Automator\Services\Recipe\Structure;

use Uncanny_Automator\Services\Recipe\Common;
use Uncanny_Automator\Services\Recipe\Structure;

/**
 * Handles the closure object inside the recipe object.
 *
 * @package Uncanny_Automator\Services\Recipe\Structure
 */
final class Closure implements \JsonSerializable {

	use Common\Trait_JSON_Serializer;
	use Common\Trait_Setter_Getter;

	/**
	 * @var \Uncanny_Automator\Services\Recipe\Structure
	 */
	private static $recipe = null;

	public $_ui_order = 10000000000; //phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore

	protected $type             = 'closure';
	protected $is_item_on       = false;
	protected $id               = null;
	protected $integration_code = null;
	protected $code             = null;
	protected $backup           = array();
	protected $misc             = array();
	protected $fields           = array();

	/**
	 *
	 * @param Structure $recipe
	 * @param mixed $closure
	 *
	 * @return void
	 */
	public function __construct( \Uncanny_Automator\Services\Recipe\Structure $recipe, $closure ) {

		self::$recipe = $recipe;

		$this->hydrate_closure( $closure );

	}

	/**
	 * Populates closures.
	 *
	 * @param \WP_Post $closure
	 *
	 * @return void
	 */
	private function hydrate_closure( $closure ) {

		$code             = get_post_meta( $closure->ID, 'code', true );
		$integration      = get_post_meta( $closure->ID, 'integration', true );
		$integration_name = get_post_meta( $closure->ID, 'integration_name', true );
		$sentence         = get_post_meta( $closure->ID, 'sentence', true );
		$sentence_html    = get_post_meta( $closure->ID, 'sentence_html', true );
		$redirect_url     = get_post_meta( $closure->ID, 'REDIRECTURL', true );

		$this->is_item_on       = 'published' === $closure->post_status;
		$this->id               = $closure->ID;
		$this->integration_code = $integration;
		$this->code             = $code;
		$this->backup           = array(
			'integration_name' => $integration_name,
			'sentence'         => $sentence,
			'sentence_html'    => $sentence_html,
		);

		$this->fields = array(
			'REDIRECTURL' => array(
				'value' => $redirect_url,
			),
		);

	}
}
