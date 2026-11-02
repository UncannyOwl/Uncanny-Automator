<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Presentation\Recipe_View;

use InvalidArgumentException;

/**
 * The projection a recipe is drawn in: the diagram or the list.
 *
 * The values are the frontend's `ModelRecipeView` words, so what is stored,
 * what travels on the wire and what the switcher shows are one name.
 *
 * @since 8.0.0
 */
final class Recipe_View {

	/**
	 * The pannable flowchart.
	 *
	 * @var string
	 */
	public const DIAGRAM = 'diagram';

	/**
	 * The vertical list, as the builder drew a recipe before the diagram.
	 *
	 * @var string
	 */
	public const LIST = 'list';

	/**
	 * The view's name.
	 *
	 * @var string
	 */
	private $value;

	/**
	 * Wrap a view name.
	 *
	 * @since 8.0.0
	 *
	 * @param string $value `diagram` or `list`.
	 *
	 * @throws InvalidArgumentException For any other value.
	 */
	public function __construct( string $value ) {

		if ( ! self::is_view( $value ) ) {
			throw new InvalidArgumentException(
				sprintf( 'Unknown recipe view "%s"; expected diagram or list.', esc_html( $value ) )
			);
		}

		$this->value = $value;
	}

	/**
	 * Whether a value, typically a stored one, is one of the two views.
	 *
	 * @since 8.0.0
	 *
	 * @param mixed $value Any value.
	 *
	 * @return bool
	 */
	public static function is_view( $value ): bool {

		return in_array( $value, array( self::DIAGRAM, self::LIST ), true );
	}

	/**
	 * The view's name.
	 *
	 * @since 8.0.0
	 *
	 * @return string `diagram` or `list`.
	 */
	public function value(): string {

		return $this->value;
	}
}
