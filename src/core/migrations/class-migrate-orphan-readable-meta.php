<?php

namespace Uncanny_Automator\Migrations;

use Uncanny_Automator\App\Presentation\Recipe_Builder\Sentence\Item_Sentence_Composer;
use Uncanny_Automator\App\Recipe_Builder\Shared\Sentence_Html\Field_Label_Resolver;
use Uncanny_Automator\Services\Integrations\Fields;

/**
 * Class Migrate_Orphan_Readable_Meta
 *
 * Removes `{CODE}_readable` item meta that nothing maintains, and rebuilds the
 * stored sentence for the items it touched.
 *
 * The recipe builder writes a readable label for select, radio and file fields
 * only. Until 7.6 the app-layer CRUD services persisted one for *every* field,
 * so option-less fields (time, date, text, int, float, ...) got a readable that
 * merely echoed their value at the time of writing. No later save refreshed or
 * removed it, and both sentence renderers prefer `_readable` over the raw value
 * — so the pill kept showing a value the field had already replaced.
 *
 * Field_Label_Resolver no longer creates those entries; this migration clears
 * the ones already stored.
 *
 * @package Uncanny_Automator
 */
class Migrate_Orphan_Readable_Meta extends Migration {

	/**
	 * Set once the whole table has been walked.
	 *
	 * @var string
	 */
	const MIGRATED_FLAG = 'automator_orphan_readable_meta_migrated';

	/**
	 * Highest item ID processed so far.
	 *
	 * Deleting meta as we go removes rows from the result set, so a numeric
	 * OFFSET would step over unprocessed items. Page on the post ID instead.
	 *
	 * @var string
	 */
	const CURSOR_OPTION = 'automator_orphan_readable_meta_cursor';

	/**
	 * Items whose definitions could not be resolved during the pass.
	 *
	 * @var string
	 */
	const DEFERRED_OPTION = 'automator_orphan_readable_meta_deferred';

	/**
	 * Items per request. Each one costs a definition lookup (cached per code),
	 * a meta read, and — only when something was removed — a recompose.
	 *
	 * @var int
	 */
	const BATCH_SIZE = 250;

	/**
	 * @var string
	 */
	const READABLE_SUFFIX = '_readable';

	/**
	 * `sentence_human_readable` also ends in `_readable`. It is the sentence
	 * itself, not a field label, and must never be treated as a candidate.
	 *
	 * @var string
	 */
	const SENTENCE_META = 'sentence_human_readable';

	/**
	 * @var string
	 */
	const SENTENCE_HTML_META = 'sentence_human_readable_html';

	/**
	 * Field types the recipe builder keeps a readable label for.
	 *
	 * Mirrors getOptionValue() in item/options.js. Anything outside this list
	 * stores its value verbatim and needs no label.
	 *
	 * @var string[]
	 */
	const OPTION_BEARING_TYPES = array( 'select', 'radio', 'file' );

	/**
	 * Field definitions, keyed by "object_type:code".
	 *
	 * @var array
	 */
	private $definitions = array();

	/**
	 * @var Item_Sentence_Composer|null
	 */
	private $composer = null;

	/**
	 * @var Field_Label_Resolver|null
	 */
	private $resolver = null;

	/**
	 * @return void
	 */
	public function __construct() {
		parent::__construct( '76_orphan_readable_meta' );
	}

	/**
	 * Whether the repair still has work to do.
	 *
	 * Read before Automator boots, to decide whether this request has to load
	 * every integration. Deliberately does not touch the class' own state.
	 *
	 * @return bool
	 */
	public static function is_pending() {
		return empty( automator_get_option( self::MIGRATED_FLAG, '' ) );
	}

	/**
	 * Only run where the item definitions are actually available.
	 *
	 * Automator loads integrations on demand, so on a typical front-end request
	 * almost no trigger or action is registered and every item would be skipped
	 * as unresolvable. Automator_Load forces a full load for admin requests
	 * while this migration is pending; restrict the run to match.
	 *
	 * @return bool
	 */
	public function conditions_met() {

		if ( ! self::is_pending() ) {
			return false;
		}

		if ( wp_doing_ajax() || wp_doing_cron() || ( defined( 'REST_REQUEST' ) && REST_REQUEST ) ) {
			return false;
		}

		return is_admin();
	}

	/**
	 * Process one batch of items.
	 *
	 * @return void
	 */
	public function migrate() {

		global $wpdb;

		$cursor = (int) automator_get_option( self::CURSOR_OPTION, 0 );

		$items = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT DISTINCT p.ID, p.post_type
				   FROM $wpdb->posts p
				   INNER JOIN $wpdb->postmeta m ON m.post_id = p.ID
				  WHERE p.post_type IN ( 'uo-trigger', 'uo-action' )
				    AND p.ID > %d
				    AND m.meta_key LIKE %s
				    AND m.meta_key != %s
				  ORDER BY p.ID ASC
				  LIMIT %d",
				$cursor,
				'%' . $wpdb->esc_like( self::READABLE_SUFFIX ),
				self::SENTENCE_META,
				self::BATCH_SIZE
			)
		);

		if ( empty( $items ) ) {
			$this->finish();

			return;
		}

		$deferred = (int) automator_get_option( self::DEFERRED_OPTION, 0 );
		$last_id  = $cursor;

		foreach ( $items as $item ) {

			$removed = $this->repair_item( (int) $item->ID, (string) $item->post_type );

			if ( null === $removed ) {
				++$deferred;
			}

			$last_id = (int) $item->ID;
		}

		automator_update_option( self::CURSOR_OPTION, $last_id, true );
		automator_update_option( self::DEFERRED_OPTION, $deferred, true );

		// A short page means the table has been walked to the end.
		if ( count( $items ) < self::BATCH_SIZE ) {
			$this->finish();
		}
	}

	/**
	 * Remove the unmaintained readable labels from a single item.
	 *
	 * @param int    $item_id   Trigger or action post ID.
	 * @param string $post_type Post type of that item.
	 *
	 * @return int|null Number of meta rows removed, or null when the item's
	 *                  definitions could not be resolved and it was left alone.
	 */
	private function repair_item( $item_id, $post_type ) {

		$code = get_post_meta( $item_id, 'code', true );

		if ( empty( $code ) ) {
			return null;
		}

		$object_type = ( 'uo-trigger' === $post_type ) ? 'triggers' : 'actions';
		$fields      = $this->get_field_definitions( (string) $code, $object_type );

		// Integration inactive or code retired: without the definitions there is
		// no way to tell a maintained label from an orphan, so touch nothing.
		if ( empty( $fields ) ) {
			return null;
		}

		$input_types = $this->map_input_types( $fields );
		$removed     = 0;

		foreach ( array_keys( get_post_meta( $item_id ) ) as $meta_key ) {

			if ( ! $this->is_orphan_candidate( $meta_key ) ) {
				continue;
			}

			$field_code = substr( $meta_key, 0, - strlen( self::READABLE_SUFFIX ) );

			// Field not in this item's definition — a leftover from a previous
			// version of the item. Not ours to judge.
			if ( ! isset( $input_types[ $field_code ] ) ) {
				continue;
			}

			if ( in_array( $input_types[ $field_code ], self::OPTION_BEARING_TYPES, true ) ) {
				continue;
			}

			delete_post_meta( $item_id, $meta_key );
			++$removed;
		}

		if ( $removed > 0 ) {
			$this->rebuild_sentence( $item_id, (string) $code, $object_type, $fields );
		}

		return $removed;
	}

	/**
	 * Whether a meta key could be an orphaned readable label.
	 *
	 * @param string $meta_key Meta key.
	 *
	 * @return bool
	 */
	protected function is_orphan_candidate( $meta_key ) {

		if ( self::SENTENCE_META === $meta_key ) {
			return false;
		}

		$suffix_length = strlen( self::READABLE_SUFFIX );

		if ( strlen( $meta_key ) <= $suffix_length ) {
			return false;
		}

		return substr( $meta_key, - $suffix_length ) === self::READABLE_SUFFIX;
	}

	/**
	 * Flatten the definition into a map of option code => input type.
	 *
	 * Fields arrive keyed by options *group*, and a group holds many fields, so
	 * the field being looked up is rarely the first entry under its own name.
	 * Match on `option_code` across every group instead.
	 *
	 * @param array $fields Configuration fields.
	 *
	 * @return array
	 */
	protected function map_input_types( array $fields ) {

		$types = array();

		foreach ( $fields as $group ) {

			if ( ! is_array( $group ) ) {
				continue;
			}

			foreach ( $group as $field ) {

				if ( ! is_array( $field ) ) {
					continue;
				}

				$code = $field['option_code'] ?? '';
				$type = $field['input_type'] ?? '';

				if ( '' !== $code && '' !== $type ) {
					$types[ $code ] = $type;
				}
			}
		}

		return $types;
	}

	/**
	 * Rebuild the stored sentence after labels were removed.
	 *
	 * The builder rebuilds its own sentence on the next save, but 8.0 renders
	 * the pill straight from `sentence_human_readable_html`, and the logs read
	 * the stored copy on both versions — so refresh it here rather than leave
	 * the removed label baked into the markup.
	 *
	 * @param int    $item_id     Item post ID.
	 * @param string $code        Trigger or action code.
	 * @param string $object_type Either 'triggers' or 'actions'.
	 * @param array  $fields      Configuration fields.
	 *
	 * @return void
	 */
	private function rebuild_sentence( $item_id, $code, $object_type, array $fields ) {

		$template = $this->get_sentence_template( $code, $object_type );

		if ( empty( $template ) ) {
			return;
		}

		$configuration = array();

		foreach ( get_post_meta( $item_id ) as $meta_key => $values ) {
			$configuration[ $meta_key ] = maybe_unserialize( $values[0] );
		}

		$sentence = $this->composer()->compose(
			$template,
			$configuration,
			$this->resolver()->extract_field_labels( $fields )
		);

		if ( ! empty( $sentence['brackets'] ) ) {
			update_post_meta( $item_id, self::SENTENCE_META, $sentence['brackets'] );
		}

		if ( ! empty( $sentence['html'] ) ) {
			update_post_meta( $item_id, self::SENTENCE_HTML_META, $sentence['html'] );
		}
	}

	/**
	 * Sentence template from the registered item definition.
	 *
	 * @param string $code        Trigger or action code.
	 * @param string $object_type Either 'triggers' or 'actions'.
	 *
	 * @return string
	 */
	private function get_sentence_template( $code, $object_type ) {

		$object = ( 'triggers' === $object_type )
			? Automator()->get_trigger( $code )
			: Automator()->get_action( $code );

		if ( empty( $object ) || ! is_array( $object ) ) {
			return '';
		}

		return (string) ( $object['sentence'] ?? '' );
	}

	/**
	 * Configuration fields for a code, resolved once per code per request.
	 *
	 * @param string $code        Trigger or action code.
	 * @param string $object_type Either 'triggers' or 'actions'.
	 *
	 * @return array Empty when the definition is not registered.
	 */
	private function get_field_definitions( $code, $object_type ) {

		$cache_key = $object_type . ':' . $code;

		if ( array_key_exists( $cache_key, $this->definitions ) ) {
			return (array) $this->definitions[ $cache_key ];
		}

		$this->definitions[ $cache_key ] = array();

		try {
			$fields = new Fields();
			$fields->set_config(
				array(
					'code'        => $code,
					'object_type' => $object_type,
				)
			);

			$this->definitions[ $cache_key ] = (array) $fields->get();
		} catch ( \Throwable $e ) {
			// Fields::get() throws for codes that are not registered.
			$this->definitions[ $cache_key ] = array();
		}

		return (array) $this->definitions[ $cache_key ];
	}

	/**
	 * @return Item_Sentence_Composer
	 */
	private function composer() {

		if ( null === $this->composer ) {
			$this->composer = new Item_Sentence_Composer();
		}

		return $this->composer;
	}

	/**
	 * @return Field_Label_Resolver
	 */
	private function resolver() {

		if ( null === $this->resolver ) {
			$this->resolver = new Field_Label_Resolver();
		}

		return $this->resolver;
	}

	/**
	 * Close the migration out.
	 *
	 * Items skipped because their integration was inactive keep their labels.
	 * The pass still completes: retrying forever would force every admin request
	 * to load all integrations on sites where those codes never come back.
	 *
	 * @return void
	 */
	private function finish() {

		$deferred = (int) automator_get_option( self::DEFERRED_OPTION, 0 );

		if ( $deferred > 0 ) {
			automator_log(
				sprintf( 'Skipped %d item(s) with unresolvable definitions', $deferred ),
				$this->name,
				true
			);
		}

		automator_update_option( self::MIGRATED_FLAG, time(), true );
		automator_delete_option( self::CURSOR_OPTION );
		automator_delete_option( self::DEFERRED_OPTION );

		$this->complete();
	}
}

new Migrate_Orphan_Readable_Meta();
