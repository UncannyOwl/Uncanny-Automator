<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Template_Library;

use Uncanny_Automator\App\Infrastructure\Template_Library\Template_Library_Client;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Portability\Legacy_Export_Adapter;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Recipe_Wire_Serializer;
use Uncanny_Automator\App\Recipe_Builder\Token\Services\Reference_Rewriter;
use WP_Error;

/**
 * Transforms a template export into the exact recipe-builder wire shape
 * for the read-only preview.
 *
 * No local post exists: the canonical tree from the export gets the
 * template id injected as `recipe_id`, is serialized through the same
 * Recipe_Wire_Serializer the builder uses, and is then normalized for
 * preview (`can_edit` forced false; the trash URL — which
 * get_delete_post_link() cannot build without a post — replaced with the
 * all-recipes URL so the FE's z.url() validation passes). The
 * transformed payload is transient-cached per template id with the same
 * TTL policy as the feed.
 */
final class Template_Preview_Service {

	/**
	 * What a preview shows in place of a trigger's own post id.
	 *
	 * @var string
	 */
	const ITEM_ID_PLACEHOLDER = '{{ID}}';

	/**
	 * Transient key prefix; the client's LIBRARY_VERSION rides in the key
	 * so a version bump invalidates cached previews with the feed.
	 *
	 * @var string
	 */
	const TRANSIENT_PREFIX = 'automator_template_library_preview_v';

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
	 * Lazy-default constructor injection (Rule 9c).
	 *
	 * @param Template_Library_Client|null $client Upstream client.
	 */
	public function __construct( ?Template_Library_Client $client = null ) {
		$this->client = $client ?? Template_Library_Client::instance();
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
	 * The read-only preview body: exactly `{ recipe: <wire> }`.
	 *
	 * @param int $template_id Upstream template id.
	 * @return array{recipe: array<string,mixed>}|WP_Error
	 */
	public function get_preview( int $template_id ) {
		$key = $this->transient_key( $template_id );

		$cached = get_transient( $key );
		if ( is_array( $cached ) ) {
			return $cached;
		}

		$export = $this->client->get_template_export( $template_id );
		if ( is_wp_error( $export ) ) {
			return $export;
		}

		$tree = $this->tree_from_export( $export );
		if ( is_wp_error( $tree ) ) {
			return $tree;
		}

		$tree              = $this->with_item_id_placeholders( $tree );
		$tree['recipe_id'] = $template_id;

		$wire = Recipe_Wire_Serializer::instance()->serialize( $tree );

		$wire['can_edit'] = false;
		if ( empty( $wire['miscellaneous']['url_trash_recipe'] ) ) {
			$wire['miscellaneous']['url_trash_recipe'] = ! empty( $wire['miscellaneous']['url_all_recipes'] )
				? $wire['miscellaneous']['url_all_recipes']
				: admin_url( 'edit.php?post_type=uo-recipe' );
		}

		$body = array( 'recipe' => $wire );

		set_transient( $key, $body, Template_Library_Client::CACHE_TTL );

		return $body;
	}

	/**
	 * Transient key for one template's transformed preview payload.
	 *
	 * @param int $template_id Upstream template id.
	 * @return string
	 */
	public function transient_key( int $template_id ): string {
		return self::TRANSIENT_PREFIX . Template_Library_Client::LIBRARY_VERSION . '_' . $template_id;
	}

	/**
	 * Resolve the canonical tree from either export format: legacy raw
	 * dumps (`recipe->post`) go through the Legacy_Export_Adapter; new
	 * versioned portable documents carry the tree at `recipe`.
	 *
	 * @param object $export Decoded export JSON.
	 * @return array<string,mixed>|WP_Error
	 */
	private function tree_from_export( object $export ) {
		if ( isset( $export->recipe->post ) ) {
			$document = ( new Legacy_Export_Adapter() )->adapt( $export );
		} else {
			$document = (array) json_decode( (string) wp_json_encode( $export ), true );
		}

		if ( is_wp_error( $document ) ) {
			return $document;
		}

		if ( ! isset( $document['recipe'] ) || ! is_array( $document['recipe'] ) ) {
			return new WP_Error(
				'template_library_invalid_export',
				esc_html_x( 'The template export returned invalid data.', 'Template library', 'uncanny-automator' )
			);
		}

		return $document['recipe'];
	}

	/**
	 * Show a placeholder in the fields where a magic button or link trigger
	 * stores its own post id. In a template that id belongs to the site the
	 * template was made on; on the viewer's site it names another post or
	 * none, yet the trigger's sentence shows it in a shortcode that can be
	 * copied. With {@see self::ITEM_ID_PLACEHOLDER} the shortcode reads
	 * `id="{{ID}}"`, and the builder doesn't offer to copy a shortcode that
	 * still holds a `{{…}}` placeholder. Import gives the trigger its new id
	 * (Recipe_Tree_Materializer).
	 *
	 * @param array<string,mixed> $tree Canonical recipe tree.
	 * @return array<string,mixed>
	 */
	private function with_item_id_placeholders( array $tree ): array {
		foreach ( (array) ( $tree['triggers'] ?? array() ) as $index => $trigger ) {
			if ( ! is_array( $trigger ) ) {
				continue;
			}
			$code = (string) ( $trigger['code'] ?? '' );
			foreach ( Reference_Rewriter::BARE_ID_FIELD_KEYS as $key ) {
				if ( $code === $key || isset( $trigger['fields'][ $key ] ) ) {
					$tree['triggers'][ $index ]['fields'][ $key ] = self::ITEM_ID_PLACEHOLDER;
				}
			}
		}

		return $tree;
	}
}
