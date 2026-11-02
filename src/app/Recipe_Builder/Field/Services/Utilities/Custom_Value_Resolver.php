<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Field\Services\Utilities;

/**
 * Reads a saved field the way a run uses it.
 *
 * A select set to "Use a custom value" saves the `automator_custom_value`
 * sentinel as its value and the real value in the `{code}_custom` companion.
 * The run uses the companion. One rule for every runner that reads flat
 * saved fields: the action parser of a recipe run and a one-off run_action.
 *
 * @since 8.0.0
 */
final class Custom_Value_Resolver {

	/**
	 * Value a select saves when it is set to a custom value.
	 *
	 * @var string
	 */
	public const SENTINEL = 'automator_custom_value';

	/**
	 * Suffix of the companion key that holds the custom value.
	 *
	 * @var string
	 */
	public const COMPANION_SUFFIX = '_custom';

	/**
	 * The value a field stands for: its custom companion when the field holds
	 * the sentinel and the companion is saved, otherwise its own value.
	 *
	 * @param array      $fields Flat saved fields, code => value.
	 * @param int|string $code   Field code.
	 * @return mixed
	 */
	public static function value_of( array $fields, $code ) {
		$value     = $fields[ $code ] ?? null;
		$companion = $code . self::COMPANION_SUFFIX;

		if ( self::SENTINEL === $value && isset( $fields[ $companion ] ) ) {
			return $fields[ $companion ];
		}

		return $value;
	}

	/**
	 * Every field read as a run uses it. Companion keys stay as saved.
	 *
	 * @param array $fields Flat saved fields, code => value.
	 * @return array
	 */
	public static function resolve( array $fields ): array {
		$resolved = $fields;
		foreach ( array_keys( $fields ) as $code ) {
			$resolved[ $code ] = self::value_of( $fields, $code );
		}

		return $resolved;
	}
}
