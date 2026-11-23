<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Template_Library;

use Uncanny_Automator\App\Events\Dispatcher;
use Uncanny_Automator\App\Infrastructure\Template_Library\Template_Library_Client;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Portability\Recipe_Importer;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Services\Recipe_CRUD_Service;
use Uncanny_Automator\Automator_Load;
use Uncanny_Automator\Import_Recipe;
use WP_Error;

/**
 * Imports a library template as a new local recipe, or into a blank draft.
 *
 * Orchestration only: the fetch goes through the client (always fresh —
 * never the preview cache), the import through the shared core
 * Import_Recipe entry (old/new format detection, imported-recipe warning
 * meta, friendly engine-error mapping), the legacy-parity
 * `automator_recipe_imported` action through the Dispatcher, and the
 * usage event through Template_Usage_Service.
 *
 * On posts storage that can't roll a fill back, a blank draft is replaced:
 * the template lands in a new recipe and the draft is deleted.
 */
final class Template_Import_Service {

	/**
	 * Shared instance.
	 *
	 * @var self|null
	 */
	private static $instance = null;

	/**
	 * Upstream client.
	 *
	 * @var Template_Library_Client
	 */
	private $client;

	/**
	 * Core import entry point.
	 *
	 * @var Import_Recipe
	 */
	private $importer;

	/**
	 * Usage-report recorder.
	 *
	 * @var Template_Usage_Service
	 */
	private $usage;

	/**
	 * Answers whether a recipe is a blank draft a template can fill.
	 *
	 * @since 8.1.0
	 *
	 * @var Recipe_Importer
	 */
	private $fill_rule;

	/**
	 * Deletes the blank draft a new recipe replaced.
	 *
	 * @since 8.1.0
	 *
	 * @var Recipe_CRUD_Service
	 */
	private $recipes;

	/**
	 * Lazy-default constructor injection (Rule 9c). The importer default
	 * reuses the boot-time core instance when present so admin hooks are
	 * not double-registered.
	 *
	 * @since 8.1.0 Takes the usage recorder, the blank-draft rule and the recipe service.
	 *
	 * @param Template_Library_Client|null $client    Upstream client.
	 * @param Import_Recipe|null           $importer  Core import entry.
	 * @param Template_Usage_Service|null  $usage     Usage-report recorder.
	 * @param Recipe_Importer|null         $fill_rule Answers whether a recipe is a blank draft a template can fill.
	 * @param Recipe_CRUD_Service|null     $recipes   Deletes the blank draft a new recipe replaced.
	 */
	public function __construct(
		?Template_Library_Client $client = null,
		?Import_Recipe $importer = null,
		?Template_Usage_Service $usage = null,
		?Recipe_Importer $fill_rule = null,
		?Recipe_CRUD_Service $recipes = null
	) {
		$this->client = $client ?? Template_Library_Client::instance();

		if ( null === $importer ) {
			$importer = Automator_Load::get_core_class_instance( 'Import_Recipe' );
			$importer = is_a( $importer, Import_Recipe::class ) ? $importer : new Import_Recipe();
		}
		$this->importer  = $importer;
		$this->usage     = $usage ?? Template_Usage_Service::instance();
		$this->fill_rule = $fill_rule ?? new Recipe_Importer();
		$this->recipes   = $recipes ?? Recipe_CRUD_Service::instance();
	}

	/**
	 * Shared-instance accessor.
	 *
	 * @return self
	 */
	public static function instance(): self {
		if ( null === self::$instance ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Import one template; returns exactly `{ redirect: <edit URL>, recipe_id: <the recipe> }`.
	 *
	 * The reply names the draft it filled, or a new recipe. A new recipe also answers a blank draft the site's posts storage can't fill: the draft is then deleted.
	 *
	 * @since 8.1.0 Takes the blank draft to fill, and the reply names the recipe.
	 *
	 * @param int      $template_id Upstream template id.
	 * @param int|null $recipe_id   The blank draft to fill, or null to create a recipe.
	 * @return array{redirect: string, recipe_id: int}|WP_Error
	 */
	public function import( int $template_id, ?int $recipe_id = null ) {
		if ( null !== $recipe_id ) {
			return $this->fill( $template_id, $recipe_id );
		}

		$export = $this->client->get_template_export( $template_id );

		return is_wp_error( $export ) ? $export : $this->import_as_new( $export );
	}

	/**
	 * Import a fetched template as a new recipe.
	 *
	 * @since 8.1.0
	 *
	 * @param object $export Decoded export JSON.
	 * @return array{redirect: string, recipe_id: int}|WP_Error
	 */
	private function import_as_new( object $export ) {
		$result = $this->importer->import_recipe_json( $export );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$new_recipe_id = (int) $result;

		$this->report_import( $new_recipe_id, $export );

		$redirect = $this->edit_url( $new_recipe_id );
		if ( '' === $redirect ) {
			return new WP_Error(
				'template_import_failed',
				esc_html_x( 'The recipe was imported but its edit URL could not be built.', 'Template library', 'uncanny-automator' )
			);
		}

		return array(
			'redirect'  => $redirect,
			'recipe_id' => $new_recipe_id,
		);
	}

	/**
	 * Fill a blank draft from one template.
	 *
	 * An error from here always means the draft is unchanged. So the edit URL is built before the fill, and a listener that throws once the fill is final is logged: an error reply would invite a retry against a recipe that is no longer blank.
	 *
	 * Posts storage that can't roll a fill back refuses it before the blank-draft rule runs; such a fill goes to replace_draft() with the export already fetched.
	 *
	 * @since 8.1.0
	 *
	 * @param int $template_id Upstream template id.
	 * @param int $recipe_id   The blank draft to fill.
	 * @return array{redirect: string, recipe_id: int}|WP_Error
	 */
	private function fill( int $template_id, int $recipe_id ) {
		$redirect = $this->edit_url( $recipe_id );
		if ( '' === $redirect ) {
			return new WP_Error(
				Recipe_Importer::ERROR_RECIPE_NOT_FOUND,
				esc_html_x( 'The recipe to fill could not be found.', 'Template library', 'uncanny-automator' )
			);
		}

		$export = $this->client->get_template_export( $template_id );
		if ( is_wp_error( $export ) ) {
			return $export;
		}

		$result = $this->importer->import_recipe_json( $export, $recipe_id );
		if ( is_wp_error( $result ) ) {
			return Recipe_Importer::ERROR_FILL_STORAGE_UNAVAILABLE === $result->get_error_code()
				? $this->replace_draft( $export, $recipe_id )
				: $result;
		}

		try {
			$this->report_import( $recipe_id, $export );
		} catch ( \Throwable $e ) {
			automator_log( $e->getMessage(), 'Template_Import_Service', true );
		}

		return array(
			'redirect'  => $redirect,
			'recipe_id' => $recipe_id,
		);
	}

	/**
	 * Import the template as a new recipe in place of a blank draft the site's posts storage can't fill, then delete the draft, so the person ends with one recipe, as after a fill.
	 *
	 * The draft has to pass the importer's blank-draft rule first, so a recipe that isn't a blank draft gets the refusal a fill answers. When the new recipe can't be imported, its error is the reply and the draft stays.
	 *
	 * @since 8.1.0
	 *
	 * @param object $export    Decoded export JSON.
	 * @param int    $recipe_id The blank draft the fill was refused for.
	 * @return array{redirect: string, recipe_id: int}|WP_Error
	 */
	private function replace_draft( object $export, int $recipe_id ) {
		$refusal = $this->fill_rule->fill_refusal( $recipe_id );
		if ( null !== $refusal ) {
			return $refusal;
		}

		$result = $this->import_as_new( $export );
		if ( is_wp_error( $result ) ) {
			return $result;
		}

		$this->delete_draft( $recipe_id, $result['recipe_id'] );

		return $result;
	}

	/**
	 * Permanently delete the draft a new recipe replaced, while it is still a blank draft this user can delete.
	 *
	 * A draft that is kept, for any reason, is logged and never fails the reply: the new recipe is the answer either way.
	 *
	 * @since 8.1.0
	 *
	 * @param int $recipe_id     The draft.
	 * @param int $new_recipe_id The recipe the template was imported as.
	 * @return void
	 */
	private function delete_draft( int $recipe_id, int $new_recipe_id ): void {
		$kept_because = null;

		try {
			$refusal = $this->fill_rule->fill_refusal( $recipe_id );

			if ( null !== $refusal ) {
				$kept_because = $refusal->get_error_message();
			} elseif ( ! current_user_can( 'delete_post', $recipe_id ) ) {
				$kept_because = 'The current user cannot delete it.';
			} else {
				$deleted      = $this->recipes->delete_recipe( $recipe_id, true );
				$kept_because = is_wp_error( $deleted ) ? $deleted->get_error_message() : null;
			}
		} catch ( \Throwable $e ) {
			$kept_because = $e->getMessage();
		}

		if ( null !== $kept_because ) {
			automator_log( sprintf( 'Recipe %1$d is kept beside recipe %2$d, the template imported in its place: %3$s', $recipe_id, $new_recipe_id, $kept_because ), 'Template_Import_Service' );
		}
	}

	/**
	 * Fire the imported action and count the import in the usage report.
	 *
	 * @since 8.1.0
	 *
	 * @param int    $recipe_id The imported recipe.
	 * @param object $export    Decoded export JSON.
	 * @return void
	 */
	private function report_import( int $recipe_id, object $export ): void {
		Dispatcher::action( 'automator_recipe_imported', $recipe_id );

		$this->usage->record_import( $this->template_title( $export ) );
	}

	/**
	 * A recipe's edit URL.
	 *
	 * @since 8.1.0
	 *
	 * @param int $recipe_id The recipe.
	 * @return string The URL, or '' when there is no such post or this user can't edit it.
	 */
	private function edit_url( int $recipe_id ): string {
		$url = get_edit_post_link( $recipe_id, 'url' );

		return is_string( $url ) ? $url : '';
	}

	/**
	 * Recipe title from either export format (legacy `recipe->post`,
	 * portable-document `recipe.title`).
	 *
	 * @param object $export Decoded export JSON.
	 * @return string
	 */
	private function template_title( object $export ): string {
		if ( isset( $export->recipe->post->post_title ) ) {
			return (string) $export->recipe->post->post_title;
		}
		if ( isset( $export->recipe->title ) ) {
			return (string) $export->recipe->title;
		}

		return '';
	}
}
