<?php
declare(strict_types=1);
namespace Uncanny_Automator\Api\Services\Closure\Services;

use Uncanny_Automator\Api\Application\Value_Objects\Url;
use Uncanny_Automator\Api\Components\Closure\Closure;
use Uncanny_Automator\Api\Components\Closure\Closure_Config;
use Uncanny_Automator\Api\Components\Recipe\Value_Objects\Recipe_Id;
use Uncanny_Automator\Api\Database\Stores\WP_Closure_Store;
use WP_Error;

/**
 * Closure Service - WordPress Application Layer.
 *
 * Service layer that handles closure operations with WordPress integration.
 * Acts as adapter between API/UI layer and pure domain logic/stores.
 *
 * @since 7.0.0
 */
class Closure_Service {

	/**
	 * Singleton instance.
	 *
	 * @var Closure_Service|null
	 */
	private static $instance = null;

	/**
	 * Closure store.
	 *
	 * @var WP_Closure_Store
	 */
	private $closure_store;

	/**
	 * Constructor.
	 *
	 * Allows dependency injection for testing. Production code can use instance()
	 * for backward compatibility with singleton pattern.
	 *
	 * @param WP_Closure_Store|null $closure_store Optional closure store instance.
	 */
	public function __construct( ?WP_Closure_Store $closure_store = null ) {
		global $wpdb;
		$this->closure_store = $closure_store ?? new WP_Closure_Store( $wpdb );
	}

	/**
	 * Get singleton instance.
	 *
	 * @return Closure_Service
	 */
	public static function instance(): Closure_Service {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Add empty closure to recipe.
	 *
	 * Creates an empty REDIRECT closure for the specified recipe.
	 * Used by REST endpoints for human interaction where the closure
	 * is created first and configured later via update.
	 *
	 * @param int $recipe_id Recipe ID.
	 * @return array|\WP_Error Success data with closure_id or error.
	 */
	public function add_empty_to_recipe( int $recipe_id ) {

		// Validate recipe ID.
		if ( empty( $recipe_id ) ) {
			return new WP_Error(
				'invalid_recipe_id',
				esc_html_x( 'Recipe ID is required.', 'Closure service error', 'uncanny-automator' )
			);
		}

		try {
			// Build empty closure configuration with REDIRECT code.
			// Using REDIRECT as default since it's the primary closure type.
			$closure_config = ( new Closure_Config() )
				->code( 'REDIRECT' )
				->recipe_id( new Recipe_Id( $recipe_id ) )
				->integration( 'WP' )
				->integration_name( 'WordPress' );

			$closure = $this->closure_store->save( $closure_config );

			return array(
				'success'    => true,
				'message'    => esc_html_x( 'Empty closure created for recipe.', 'Closure service success message', 'uncanny-automator' ),
				'recipe_id'  => $recipe_id,
				'closure_id' => $closure->get_id(),
				'closure'    => array(
					'id'          => $closure->get_id(),
					'code'        => $closure->get_code(),
					'integration' => $closure->get_integration(),
					'sentence'    => $closure->get_sentence_human_readable(),
				),
			);
		} catch ( \Exception $e ) {
			return new WP_Error(
				'closure_creation_failed',
				sprintf(
					/* translators: %s Error message. */
					esc_html_x( 'Failed to create closure: %s', 'Closure service error', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Add closure to recipe.
	 *
	 * Creates a REDIRECT closure that redirects to the specified URL when recipe completes.
	 *
	 * @param int    $recipe_id Recipe ID.
	 * @param string $redirect_url URL to redirect to.
	 * @return array|\WP_Error Success data or error.
	 */
	public function add_closure( int $recipe_id, string $redirect_url ) {

		// Validate inputs
		if ( empty( $recipe_id ) ) {
			return new WP_Error(
				'invalid_recipe_id',
				esc_html_x( 'Recipe ID is required.', 'Closure service error', 'uncanny-automator' )
			);
		}

		// Validate URL
		if ( empty( $redirect_url ) ) {
			return new WP_Error(
				'invalid_url',
				esc_html_x( 'URL is required.', 'Closure service error', 'uncanny-automator' )
			);
		}

		// Ensure the redirect URL is a valid URL.
		try {
			$url_obj      = new Url( $redirect_url );
			$redirect_url = $url_obj->get_value();
		} catch ( \InvalidArgumentException $e ) {
			return new WP_Error(
				'invalid_url_format',
				sprintf(
					/* translators: %s Error message. */
					esc_html_x( 'Invalid URL format: %s', 'Closure service error', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}

		try {
			// Build closure configuration
			$config = ( new Closure_Config() )
				->code( 'REDIRECT' )
				->recipe_id( new Recipe_Id( $recipe_id ) )
				->integration( 'WP' )
				->integration_name( 'WordPress' )
				->set_meta( 'REDIRECTURL', $redirect_url );

			$closure = $this->closure_store->save( $config );

			// Return structured response for backward compatibility
			return array(
				'success'    => true,
				'message'    => esc_html_x( 'Closure successfully added to recipe.', 'Closure service success message', 'uncanny-automator' ),
				'recipe_id'  => $recipe_id,
				'closure_id' => $closure->get_id(),
				'closure'    => array(
					'id'          => $closure->get_id(),
					'code'        => $closure->get_code(),
					'integration' => $closure->get_integration(),
					'url'         => $redirect_url,
					'sentence'    => $closure->get_sentence_human_readable(),
				),
			);
		} catch ( \Exception $e ) {
			return new WP_Error(
				'closure_creation_failed',
				sprintf(
					/* translators: %s Error message. */
					esc_html_x( 'Failed to create closure: %s', 'Closure service error', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}
	}

	/**
	 * Delete all closures from recipe.
	 *
	 * Removes all closures associated with the specified recipe.
	 *
	 * @param int $recipe_id Recipe ID.
	 * @return array|\WP_Error Success data or error.
	 */
	public function delete_recipe_closures( int $recipe_id ) {
		try {
			// Validate input
			if ( empty( $recipe_id ) ) {
				return new WP_Error(
					'invalid_recipe_id',
					esc_html_x( 'Recipe ID is required.', 'Closure service error', 'uncanny-automator' )
				);
			}

			// Get all closures for the recipe
			$closures = $this->closure_store->all( array( 'recipe_id' => $recipe_id ) );

			// Delete each closure
			$deleted_count = 0;
			foreach ( $closures as $closure ) {
				try {
					$this->closure_store->delete( $closure );
					++$deleted_count;
				} catch ( \Throwable $e ) {
					// Log individual deletion failures but continue
					// Closure created for redirect handling.
					unset( $e ); // Suppress unused variable warning
				}
			}

			// Return success response
			return array(
				'success'       => true,
				'message'       => sprintf(
					/* translators: %d Number of closures deleted. */
					esc_html_x( 'Successfully deleted %d closure(s) from recipe.', 'Closure service success message', 'uncanny-automator' ),
					$deleted_count
				),
				'recipe_id'     => $recipe_id,
				'deleted_count' => $deleted_count,
			);

		} catch ( \Throwable $e ) {
			return new WP_Error(
				'closure_deletion_failed',
				sprintf(
					/* translators: %s Error message. */
					esc_html_x( 'Failed to delete closures: %s', 'Closure service error', 'uncanny-automator' ),
					$e->getMessage()
				)
			);
		}
	}
}
