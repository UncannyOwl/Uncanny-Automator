<?php
namespace Uncanny_Automator\Services\Recipe;

use Uncanny_Automator\Services\Recipe\Common;

use JsonSerializable;
use Uncanny_Automator\Automator_Exception;
use Uncanny_Automator\Services\Recipe\Structure\Pluggable\Conditions_Pluggable;

/**
 * This class is the main class for our Recipe UI object.
 *
 * It has no real behaviours only values that are mapped to object.
 *
 * @since 5.0
 */
final class Structure implements JsonSerializable {

	use Common\Trait_JSON_Serializer;
	use Common\Trait_Setter_Getter;

	protected $recipe_id     = 0;
	protected $is_pro_active = false;
	protected $has_pro_item  = false;
	protected $is_recipe_on  = false;
	protected $title         = '';
	protected $recipe_type   = '';
	protected $stats         = null;
	protected $miscellaneous = null;
	protected $triggers      = null;
	protected $actions       = null;

	// Basic config we can use to manipulate the structures.
	protected $_config = array( // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore
		'fields'       => array(
			'show_original_field_resolver_structure' => false,
		),
		'publish_only' => false,
	);

	protected static $meta = null;
	protected static $post = null;

	public function __construct( $recipe_id = null, $config = array() ) {

		if ( empty( $recipe_id ) || ! is_int( $recipe_id ) ) {
			throw new Automator_Exception( 'Structure::__construct parameter 1 has invalid recipe id.', 422 );
		}

		$this->recipe_id = $recipe_id;

		// Registers all pluggable object before we hydrate the properties.
		// Think of the conditions as separate plugin until we move it as a post type.
		$conditions_pluggable = new Conditions_Pluggable();
		$conditions_pluggable->register_recipe_action_hook();

		$this->is_pro_active = defined( 'UAPro_ABSPATH' );

		$this->_config = wp_parse_args(
			(array) $config,
			$this->get_default_configs()
		);

		$this->retrieve_record()->hydrate_properties();

	}

	/**
	 * Retrieves the config.
	 *
	 * @return mixed[]
	 */
	public function get_config() {
		return $this->_config;
	}

	/**
	 * Retrieves the default configs.
	 *
	 * @return array{fields:array{show_original_field_resolver_structure:false}}
	 */
	public function get_default_configs() {
		return array(
			'fields' => array(
				'show_original_field_resolver_structure' => false,
			),
		);
	}

	/**
	 * Retrieves the recipe ID.
	 *
	 * @return int
	 */
	public function get_recipe_id() {
		return $this->recipe_id;
	}

	/**
	 * Retrieves the recipe record and automatically hydrates static values for meta and post.
	 *
	 * @throws Automator_Exception
	 *
	 * @return self
	 */
	private function retrieve_record() {

		$data = Automator()->get_recipe_data_by_recipe_id( $this->recipe_id );

		if ( ! is_array( $data ) || empty( $data ) ) {
			throw new Automator_Exception( 'No recipe found with ID: ' . $this->recipe_id, 404 );
		}

		$data = $data[ $this->recipe_id ];

		self::$meta = $this->get_meta();
		self::$post = get_post( $this->recipe_id, ARRAY_A );

		return $this;

	}

	/**
	 * Hydrates the properties.
	 *
	 * @return self
	 */
	private function hydrate_properties() {

		$this->is_recipe_on = 'publish' === self::$post['post_status'];
		$this->title        = self::$post['post_title'];
		$this->recipe_type  = isset( self::$meta['uap_recipe_type'] ) ? self::$meta['uap_recipe_type'] : '';

		$stats         = new Structure\Stats( $this );
		$miscellaneous = new Structure\Miscellaneous( $this );
		$triggers      = new Structure\Triggers\Triggers( $this );
		$actions       = new Structure\Actions\Actions( $this, self::$meta );

		$this->stats         = apply_filters( 'automator_recipe_main_object\structure\stats', $stats, $this );
		$this->miscellaneous = apply_filters( 'automator_recipe_main_object\structure\miscellaneous', $miscellaneous, $this );
		$this->triggers      = apply_filters( 'automator_recipe_main_object\structure\triggers', $triggers, $this );
		// @see Conditions_Pluggable::register_hooks().
		$this->actions = apply_filters( 'automator_recipe_main_object\structure\actions', $actions, $this ); //phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		return $this;

	}

	/**
	 * Manually assign "anonymous" value to the recipe_type.
	 *
	 * @return self
	 */
	public function retrieve() {

		// The recipe type can be user. Only assign if recipe_type is already anonymous.
		if ( ! empty( $this->recipe_type ) && 'user' !== $this->recipe_type ) {
			$this->recipe_type = 'anonymous';
		}

		return $this;

	}

	/**
	 * @return mixed[]
	 */
	public function meta() {
		return self::$meta;
	}

	/**
	 * @return mixed[]
	 */
	public function post() {
		return self::$post;
	}

	/**
	 * Coverts this object to JSON string.
	 *
	 * @return string
	 */
	public function toJSON() {

		// Pass default option by default.
		$flags = apply_filters( 'automator_recipe_object_json_encoding_flags', 0, $this );

		// If its a php 7.2 where constant "JSON_INVALID_UTF8_SUBSTITUTE" is available.
		if ( defined( 'JSON_INVALID_UTF8_SUBSTITUTE' ) ) {
			// Provide a different filter and by default, substitute invalid utf-8 characters.
			$flags = apply_filters( 'automator_recipe_object_json_encoding_flags_php72', JSON_INVALID_UTF8_SUBSTITUTE, $this );
		}

		$decoded = wp_json_encode( $this, $flags );

		if ( false === $decoded || ! is_string( $decoded ) ) {
			return '';
		}

		return $decoded;

	}

	/**
	 * @return array
	 */
	private function get_meta() {

		// Normalize the recipe post meta.
		foreach ( (array) get_post_meta( $this->recipe_id ) as $key => $value ) {
			$list[ $key ] = $value[0];
		}

		return isset( $list ) ? $list : array();

	}

}
