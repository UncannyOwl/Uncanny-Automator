<?php
//phpcs:disable WordPress.NamingConventions.ValidHookName.UseUnderscores
namespace Uncanny_Automator\Services\Recipe;

use Uncanny_Automator\Services\Recipe\Common;

use JsonSerializable;
use Uncanny_Automator\Automator_Exception;
use Uncanny_Automator\App\Recipe_Builder\Block\Resolvers\Block_Resolver;

/**
 * This class is the main class for our Recipe UI object.
 *
 * It has no real behaviours only values that are mapped to object.
 *
 * @since 5.0
 */
// Pro's conditions set has_pro_item through set(); a declared property would add it to the JSON.
#[\AllowDynamicProperties] // phpcs:ignore Uncanny_Automator.PHP.PHP80Features.Attribute -- a comment before PHP 8.
final class Structure implements JsonSerializable {

	use Common\Trait_JSON_Serializer;
	use Common\Trait_Setter_Getter;

	/**
	 * @var int
	 */
	protected $recipe_id = 0;
	/**
	 * @var bool
	 */
	protected $is_recipe_on = false;
	/**
	 * @var string
	 */
	protected $title = '';
	/**
	 * @var string
	 */
	protected $recipe_type = '';
	/**
	 * @var null
	 */
	protected $stats = null;
	/**
	 * @var null
	 */
	protected $settings = null;
	/**
	 * @var null
	 */
	protected $miscellaneous = null;
	/**
	 * @var null
	 */
	protected $triggers = null;
	/**
	 * @var null
	 */
	protected $actions = null;

	/**
	 * @var array
	 */
	protected $_config = array( // phpcs:ignore PSR2.Classes.PropertyDeclaration.Underscore
		'fields'       => array(
			'show_original_field_resolver_structure' => false,
		),
		'publish_only' => false,
	);

	/**
	 * @var null
	 */
	protected static $meta = null;
	/**
	 * @var null
	 */
	protected static $post = null;

	/**
	 * @param $recipe_id
	 * @param $config
	 *
	 * @throws \Uncanny_Automator\Automator_Exception
	 */
	public function __construct( $recipe_id = null, $config = array() ) {

		if ( empty( $recipe_id ) || ! is_int( $recipe_id ) ) {
			throw new Automator_Exception( 'Structure::__construct parameter 1 has invalid recipe id.', 422 );
		}

		$this->recipe_id = $recipe_id;

		// Block_Resolver hooks at priority 5 and resolves both blocks and loops.
		$block_resolver = Block_Resolver::instance();
		$block_resolver->register_hooks();

		// Legacy frontend compat: translate block entities back into the
		// legacy filter-wrapper shape (and surface inner Action items for
		// non-filter blocks) at priority 99 so the bundled main.js — which
		// only knows the pre-unified-blocks shape — can render them. Remove
		// this call when the new recipe builder ships.
		//
		// Kill switch — see `automator_blocks_legacy_translators_disabled()`.
		// When the flag is on, the recipe object emits native
		// `entity_type='block'` items so the new builder team can verify
		// their renderer against raw uo-block storage.
		if ( ! automator_blocks_legacy_translators_disabled() ) {
			\Uncanny_Automator\App\Recipe_Builder\Block\Resolvers\Legacy_Frontend_Translator::register_hooks();
		}

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
			throw new Automator_Exception(
				esc_html(
					sprintf(
					/* translators: %d: Recipe ID */
						esc_html__( 'No recipe found with ID: %d', 'uncanny-automator' ),
						absint( $this->recipe_id )
					)
				),
				404
			);
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

		$stats    = new Structure\Stats( $this );
		$settings = new Structure\Settings( $this );
		$triggers = new Structure\Triggers\Triggers( $this );
		$actions  = new Structure\Actions\Actions( $this, self::$meta );

		$this->stats    = apply_filters( 'automator_recipe_main_object\structure\stats', $stats, $this );
		$this->settings = apply_filters( 'automator_recipe_main_object\structure\settings', $settings, $this );
		$this->triggers = apply_filters( 'automator_recipe_main_object\structure\triggers', $triggers, $this );
		// @see Block_Resolver::register_hooks().
		$this->actions = apply_filters( 'automator_recipe_main_object\structure\actions', $actions, $this ); // phpcs:ignore WordPress.NamingConventions.ValidHookName.UseUnderscores

		// Miscellaneous is loaded last so it can access triggers and actions to determine pro entity usage.
		$miscellaneous       = new Structure\Miscellaneous( $this, $this->actions );
		$this->miscellaneous = apply_filters( 'automator_recipe_main_object\structure\miscellaneous', $miscellaneous, $this );

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
