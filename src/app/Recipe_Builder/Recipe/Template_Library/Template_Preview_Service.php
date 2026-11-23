<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Template_Library;

use Uncanny_Automator\App\Infrastructure\Template_Library\Template_Library_Client;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Portability\Legacy_Export_Adapter;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Recipe_Entity_Availability_Decorator;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Recipe_Entity_Token_Decorator;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Recipe_Entity_Token_Resolver;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Recipe_Wire_Serializer;
use Uncanny_Automator\App\Recipe_Builder\Token\Services\Reference_Rewriter;
use WP_Error;

/**
 * Transforms a template export into the exact recipe-builder wire shape
 * for the read-only preview.
 *
 * No local post exists: the canonical tree from the export gets the
 * negated template id as `recipe_id` (preview_recipe_id()), is serialized through the same
 * Recipe_Wire_Serializer the builder uses, and is then normalized for
 * preview (`can_edit` forced false; the trash URL — which
 * get_delete_post_link() cannot build without a post — replaced with the
 * all-recipes URL so the FE's z.url() validation passes). Every node is
 * serialized as usable (Template_Preview_Availability_Resolver): the
 * preview shows what the template does, and the requirements sidebar
 * shows what this site lacks. Token rows read each item's meta from the
 * template (Template_Preview_Item_Meta_Bridge), never from the site's post
 * that shares the item's id. Nothing is cached here: the export is read
 * on every request. Only the feed is cached, by Template_Library_Client.
 */
final class Template_Preview_Service {

	/**
	 * What a preview shows in place of a trigger's own post id.
	 *
	 * @var string
	 */
	const ITEM_ID_PLACEHOLDER = '{{ID}}';

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
	 * The read-only preview body: exactly `{ recipe: <wire> }`, built from the
	 * current export on every request.
	 *
	 * @since 8.1.0 No longer cached, and every node reads as usable.
	 *
	 * @param int $template_id Upstream template id.
	 * @return array{recipe: array<string,mixed>}|WP_Error
	 */
	public function get_preview( int $template_id ) {
		$export = $this->client->get_template_export( $template_id );
		if ( is_wp_error( $export ) ) {
			return $export;
		}

		$tree = $this->tree_from_export( $export );
		if ( is_wp_error( $tree ) ) {
			return $tree;
		}

		$tree              = $this->with_item_id_placeholders( $tree );
		$tree['recipe_id'] = self::preview_recipe_id( $template_id );

		$wire = $this->serializer_for( $tree )->serialize( $tree );

		$wire['can_edit'] = false;
		if ( empty( $wire['miscellaneous']['url_trash_recipe'] ) ) {
			$wire['miscellaneous']['url_trash_recipe'] = ! empty( $wire['miscellaneous']['url_all_recipes'] )
				? $wire['miscellaneous']['url_all_recipes']
				: admin_url( 'edit.php?post_type=uo-recipe' );
		}

		return array( 'recipe' => $wire );
	}

	/**
	 * The `recipe_id` a template's preview carries: the template id negated.
	 *
	 * A preview has no post on this site. A positive id could be a real recipe
	 * here: the serializer would read that recipe's runs, state flags, dates,
	 * URLs and token data into the preview, and the builder's token pills and
	 * loop, filter and delay blocks would fetch it (`GET uap/v2/recipe/{id}`).
	 * A post id is never negative,
	 * so every per-recipe lookup finds nothing, as for a recipe that doesn't
	 * exist, and the FE keeps the preview in its recipe cache under an id no
	 * real recipe has.
	 *
	 * @param int $template_id Upstream template id.
	 * @return int
	 *
	 * @since 8.1.0
	 */
	public static function preview_recipe_id( int $template_id ): int {
		return -abs( $template_id );
	}

	/**
	 * The serializer for one template's preview: every node reads as usable
	 * (Template_Preview_Availability_Resolver), and token rows read each
	 * item's meta from the template (Template_Preview_Item_Meta_Bridge),
	 * never from the site's post with the same id.
	 *
	 * @since 8.1.0
	 *
	 * @param array<string,mixed> $tree Canonical recipe tree of the template.
	 * @return Recipe_Wire_Serializer
	 */
	private function serializer_for( array $tree ): Recipe_Wire_Serializer {
		return new Recipe_Wire_Serializer(
			null,
			null,
			null,
			null,
			new Recipe_Entity_Availability_Decorator( new Template_Preview_Availability_Resolver() ),
			new Recipe_Entity_Token_Decorator(
				new Recipe_Entity_Token_Resolver( null, null, new Template_Preview_Item_Meta_Bridge( $tree ) )
			)
		);
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
