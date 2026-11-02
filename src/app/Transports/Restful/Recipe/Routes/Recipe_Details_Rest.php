<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Recipe\Routes;

use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Recipe_Details_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Recipe_Taxonomy_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Key;
use Uncanny_Automator\App\Transports\Restful\Recipe\Legacy_Rest_Hooks;
use Uncanny_Automator\App\Transports\Restful\Recipe\Traits\Recipe_Entity;
use Uncanny_Automator\App\Transports\Restful\Utilities\Traits\Rest_Responses;
use WP_REST_Request;

/**
 * Recipe detail-field REST handler.
 *
 * Routes the post-entity detail fields on
 * `POST /uap/v2/recipe/{recipe_id}/details/{field}`:
 *   - `notes`      — set the recipe's free-text notes (`{ notes: string }`).
 *   - `title`      — set the recipe's post title (`{ title: string }`).
 *   - `categories` — replace recipe_category terms (`{ categories: Term[] }`; `id<=0` creates).
 *   - `tags`       — replace recipe_tag terms (`{ tags: Term[] }`; `id<=0` creates, flat).
 *
 * The taxonomy fields respond `{ recipe: <wire>, created: CreatedRow[] }`; the
 * other two respond `{ recipe: <wire> }`.
 *
 * Mirrors Recipe_Settings_Rest: no `type` segment, so it carries its own
 * static dispatch() + validate_field(). `field` -> method name is a single
 * hyphen->underscore swap. The whitelist + method-per-field is the seam for
 * adding title / categories / tags later.
 *
 * Response shape: `{ recipe: <ModelRecipe wire shape> }` — same envelope as
 * Recipe_Settings_Rest. Delegates persistence to Recipe_Details_Service.
 *
 * After a successful save each field fires the hook of the legacy REST API
 * endpoint it replaces, through `Legacy_Rest_Hooks`, with the stored value.
 */
final class Recipe_Details_Rest {

	use Rest_Responses;
	use Recipe_Entity;

	/**
	 * URL-form field slugs this route accepts.
	 *
	 * @var array<int,string>
	 */
	private const WHITELISTED_FIELDS = array( 'notes', 'title', 'categories', 'tags' );

	/**
	 * REST callback. Referenced from Recipe_Rest_Controller as
	 * `[ Recipe_Details_Rest::class, 'dispatch' ]`.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public static function dispatch( WP_REST_Request $request ) {
		$recipe_id = (int) $request['recipe_id'];
		$field     = sanitize_key( (string) $request['field'] );

		if ( ! self::validate_field( $field ) ) {
			return self::failure(
				sprintf(
					// translators: %s is the field slug that was requested.
					esc_html_x( 'Field "%s" is not supported.', 'Recipe details REST error', 'uncanny-automator' ),
					esc_html( $field )
				),
				400,
				'automator_rest_invalid_field'
			);
		}

		$method  = str_replace( '-', '_', $field );
		$handler = new self( $recipe_id, $request );

		return $handler->{$method}();
	}

	/**
	 * Field-whitelist validator. Referenced from Recipe_Rest_Controller as
	 * the route's `field` arg validator.
	 *
	 * @param mixed $value
	 */
	public static function validate_field( $value ): bool {
		return in_array( $value, self::WHITELISTED_FIELDS, true );
	}

	/**
	 * `notes` field — set the recipe's free-text notes.
	 *
	 * Body: `{ notes: string }`.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function notes() {
		$notes = (string) ( $this->get_request()->get_param( 'notes' ) ?? '' );

		$result = Recipe_Details_Service::instance()->update_notes( $this->get_recipe_id(), $notes );
		if ( is_wp_error( $result ) ) {
			return $this->failure( $result->get_error_message(), 400, $result->get_error_code() );
		}

		$data = array( 'recipe' => $this->get_recipe_wire() );
		Legacy_Rest_Hooks::action( 'automator_recipe_notes_updated', array( $this->get_recipe_id(), $data['recipe'][ Wire_Key::NOTES ], array_merge( array( 'success' => true ), $data ) ) );

		return $this->success( $data );
	}

	/**
	 * `title` field — set the recipe's post title.
	 *
	 * Body: `{ title: string }` (empty -> '(no-title)' via the service).
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function title() {
		$title = (string) ( $this->get_request()->get_param( 'title' ) ?? '' );

		$result = Recipe_Details_Service::instance()->update_title( $this->get_recipe_id(), $title );
		if ( is_wp_error( $result ) ) {
			return $this->failure( $result->get_error_message(), 400, $result->get_error_code() );
		}

		$data = array( 'recipe' => $this->get_recipe_wire() );
		Legacy_Rest_Hooks::action( 'automator_recipe_title_updated', array( $this->get_recipe_id(), $data['recipe'][ Wire_Key::TITLE ], array_merge( array( 'success' => true ), $data ) ) );

		return $this->success( $data );
	}

	/**
	 * `categories` field — replace the recipe's category terms.
	 *
	 * Body: `{ categories: Term[] }` (full desired set; `id<=0` creates).
	 * Response: `{ recipe: <wire>, created: CreatedRow[] }`.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function categories() {
		return $this->write_terms( 'recipe_category', 'categories' );
	}

	/**
	 * `tags` field — replace the recipe's tag terms.
	 *
	 * Body: `{ tags: Term[] }` (full desired set; `id<=0` creates, flat).
	 * Response: `{ recipe: <wire>, created: CreatedRow[] }`.
	 *
	 * @return \WP_REST_Response|\WP_Error
	 */
	public function tags() {
		return $this->write_terms( 'recipe_tag', 'tags' );
	}

	/**
	 * Shared term-write path for the two taxonomy fields.
	 *
	 * @param string $taxonomy  'recipe_category' or 'recipe_tag'.
	 * @param string $param_key Request body key ('categories' or 'tags').
	 * @return \WP_REST_Response|\WP_Error
	 */
	private function write_terms( string $taxonomy, string $param_key ) {
		$terms = $this->get_request()->get_param( $param_key );
		$terms = is_array( $terms ) ? $terms : array();

		$taxonomies = Recipe_Taxonomy_Service::instance();
		$result     = $taxonomies->set_terms( $this->get_recipe_id(), $taxonomy, $terms );
		if ( is_wp_error( $result ) ) {
			return $this->failure( $result->get_error_message(), 400, $result->get_error_code() );
		}

		$data = array(
			'recipe'  => $this->get_recipe_wire(),
			'created' => $result['created'],
		);
		Legacy_Rest_Hooks::action(
			'automator_recipe_terms_updated',
			array(
				$this->get_recipe_id(),
				$taxonomy,
				array_column( $taxonomies->get_assigned_terms_wire( $this->get_recipe_id(), $taxonomy ), 'id' ),
				array_merge( array( 'success' => true ), $data ),
			)
		);

		return $this->success( $data );
	}
}
