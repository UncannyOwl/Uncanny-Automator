<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Loop\Value_Objects;

use InvalidArgumentException;
use Uncanny_Automator\App\Recipe_Builder\Loop\Enums\Loop_Token_Refusal;
use Uncanny_Automator\App\Recipe_Builder\Token\Domain\Loopable_Token_Id;

/**
 * The loopable tokens a loop can iterate, and why each other loopable token of the recipe cannot.
 *
 * Built by `Loop_Token_Scope_Service` for one place in a recipe: an existing
 * loop, or the loop `save_loop` is about to add. Token ids carry no braces.
 *
 * @since 8.0.1
 */
final class Loop_Token_Scope {

	/**
	 * Loopable tokens of the recipe's triggers and actions valid at the loop, in run order.
	 *
	 * @var string[]
	 */
	private array $recipe_token_ids;

	/**
	 * Universal loopable tokens of the active integrations, valid at every loop.
	 *
	 * @var string[]
	 */
	private array $universal_token_ids;

	/**
	 * Loopable tokens of the recipe not valid at the loop, by id.
	 *
	 * @var array<string, array{reason: string, block_id: int|string|null}>
	 */
	private array $refusals;

	/**
	 * Freeze the scope.
	 *
	 * @param string[]                                                      $recipe_token_ids    Recipe loopable tokens valid at the loop, in run order.
	 * @param string[]                                                      $universal_token_ids Universal loopable tokens of the active integrations.
	 * @param array<string, array{reason: string, block_id: int|string|null}> $refusals          Recipe loopable tokens not valid at the loop: a Loop_Token_Refusal value, and the block it names or null.
	 *
	 * @throws InvalidArgumentException When a token is not loopable, is both valid and refused, or a refusal is unknown.
	 */
	public function __construct( array $recipe_token_ids, array $universal_token_ids, array $refusals ) {
		foreach ( array_merge( $recipe_token_ids, $universal_token_ids, array_keys( $refusals ) ) as $token_id ) {
			if ( ! Loopable_Token_Id::is_loopable( (string) $token_id ) ) {
				throw new InvalidArgumentException( sprintf( 'Token %s is not a loopable token.', esc_html( (string) $token_id ) ) );
			}
		}

		foreach ( $refusals as $token_id => $refusal ) {
			if ( ! Loop_Token_Refusal::is_valid( (string) ( $refusal['reason'] ?? '' ) ) ) {
				throw new InvalidArgumentException( sprintf( 'Token %s carries an unknown refusal.', esc_html( (string) $token_id ) ) );
			}
			if ( in_array( (string) $token_id, $recipe_token_ids, true ) ) {
				throw new InvalidArgumentException( sprintf( 'Token %s cannot be both valid and refused.', esc_html( (string) $token_id ) ) );
			}
		}

		$this->recipe_token_ids    = array_values( array_unique( array_map( 'strval', $recipe_token_ids ) ) );
		$this->universal_token_ids = array_values( array_unique( array_map( 'strval', $universal_token_ids ) ) );
		$this->refusals            = $refusals;
	}

	/**
	 * Every loopable token valid at the loop: the recipe's, in run order, then the universal ones.
	 *
	 * @return string[]
	 */
	public function get_token_ids(): array {
		return array_values( array_unique( array_merge( $this->recipe_token_ids, $this->universal_token_ids ) ) );
	}

	/**
	 * The loopable tokens of the recipe's triggers and actions valid at the loop, in run order.
	 *
	 * @return string[]
	 */
	public function get_recipe_token_ids(): array {
		return $this->recipe_token_ids;
	}

	/**
	 * Whether the loop can iterate this token.
	 *
	 * @param string $token_id Token id without braces.
	 *
	 * @return bool
	 */
	public function allows( string $token_id ): bool {
		return in_array( $token_id, $this->get_token_ids(), true );
	}

	/**
	 * Why a loopable token of the recipe is not valid at the loop.
	 *
	 * @param string $token_id Token id without braces.
	 *
	 * @return string|null A Loop_Token_Refusal value, or null when the recipe produces no such loopable token outside the scope.
	 */
	public function get_refusal_reason( string $token_id ): ?string {
		return isset( $this->refusals[ $token_id ] ) ? (string) $this->refusals[ $token_id ]['reason'] : null;
	}

	/**
	 * The block a refusal names: the other block, or the block whose other path holds the producer.
	 *
	 * @param string $token_id Token id without braces.
	 *
	 * @return int|string|null Null when the refusal names no block.
	 */
	public function get_refusal_block_id( string $token_id ) {
		return $this->refusals[ $token_id ]['block_id'] ?? null;
	}
}
