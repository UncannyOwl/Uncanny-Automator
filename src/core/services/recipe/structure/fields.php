<?php
namespace Uncanny_Automator\Services\Recipe\Structure;

use Uncanny_Automator\Resolver\Fields_Resolver;
use Uncanny_Automator\Services\Recipe\Common;

/**
 * Handles the fields objects inside the recipe object.
 *
 * @package Uncanny_Automator\Services\Recipe\Structure
 */
final class Fields implements \JsonSerializable {

	use Common\Trait_JSON_Serializer;

	protected $fields = array();

	protected static $original_fields;

	public function __construct( $item, $recipe, $object_type = 'trigger' ) {
		$this->hydrate_values( $item, $recipe, $object_type );
	}

	/**
	 * Hydrates the fields object.
	 *
	 * @param mixed[] $item
	 * @param \Uncanny_Automator\Services\Recipe\Structure $recipe
	 * @param string $object_type Can be "trigger" or "action".
	 *
	 * @return void
	 */
	private function hydrate_values( $item, $recipe, $object_type ) {

		$resolver = new Fields_Resolver();
		$resolver->set_object_type( $object_type );
		$resolver->set_object_id( $item['ID'] );
		$resolver->set_recipe_id( $recipe->get_recipe_id() );
		$resolver->set_show_relevant_tokens( true );

		self::$original_fields = $resolver->resolve_object_fields();

		$config = $recipe->get_config();

		$this->fields = $config['fields']['show_original_field_resolver_structure']
			? self::$original_fields // If showing original field resolve structure
			: $this->restructure_fields( self::$original_fields ); // Otherwise, restructure for recipe ui.

	}

	/**
	 * Retrieve the original fields from resolver.
	 *
	 * @return mixed[]
	 */
	public function get_original_fields() {
		return self::$original_fields;
	}

	/**
	 * Retrieves the restructured fields.
	 *
	 * @return mixed[]
	 */
	public function get_fields() {
		return $this->fields;
	}

	/**
	 * Restructures the Trigger fields.
	 *
	 * @param mixed[] $fields
	 *
	 * @return mixed[]
	 */
	private function restructure_fields( $fields ) {

		$trigger_fields = array();

		foreach ( $fields as $options_types ) {
			foreach ( $options_types as $prop ) {
				$readable = null;
				if ( ! isset( $prop['type'] ) && is_array( $prop ) ) {
					foreach ( $prop as $_prop ) {
						if ( 'select' === $_prop['type'] ) {
							$readable = $_prop['value']['readable'];
						}

						$trigger_fields[ $_prop['field_code'] ] = array(
							'readable' => $readable,
							'value'    => $_prop['value']['raw'],
						);
					}
				} else {

					if ( 'select' === $prop['type'] ) {
						$readable = $prop['value']['readable'];
					}

					$trigger_fields[ $prop['field_code'] ] = array(
						'readable' => $readable,
						'value'    => $prop['value']['raw'],
					);
				}
			}
		}

		return $trigger_fields;

	}

}
