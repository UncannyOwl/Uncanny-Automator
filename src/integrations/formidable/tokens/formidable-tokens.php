<?php

namespace Uncanny_Automator\Integrations\Formidable;

use FrmAppHelper;
use FrmEntry;

/**
 * Token definitions and hydration for the Formidable integration.
 *
 * Formidable spreads a trigger's tokens across three storage buckets, and each
 * one is addressed by recipes as {trigger_id}:{tokenIdentifier}:{tokenId}. The
 * bucket is therefore part of the contract, not an implementation detail:
 *
 *   FIENTRYTOKENS   the four entry tokens, shared by every trigger
 *   trigger code    the form title and id, via the framework default
 *   trigger meta    every per-form field token
 *
 * A token that keeps its id but changes bucket stops resolving, and nothing
 * reports it — hence the explicit `tokenIdentifier` on the entry and field sets.
 *
 * @package Uncanny_Automator
 */
class Formidable_Tokens {

	/**
	 * Bucket the entry tokens have always been stored under.
	 *
	 * @var string
	 */
	const ENTRY_IDENTIFIER = 'FIENTRYTOKENS';

	/**
	 * Integration helpers.
	 *
	 * @var Formidable_Helpers
	 */
	private $helpers;

	/**
	 * Child entry metas already read this request, keyed by parent section.
	 *
	 * @var array
	 */
	private $child_metas = array();

	/**
	 * @param Formidable_Helpers $helpers
	 */
	public function __construct( Formidable_Helpers $helpers ) {
		$this->helpers = $helpers;
	}

	// -------------------------------------------------------------------------
	// Entry tokens — bucket FIENTRYTOKENS
	// -------------------------------------------------------------------------

	/**
	 * Tokens describing the entry itself, independent of the form's fields.
	 *
	 * @return array
	 */
	public function entry_tokens() {
		return array(
			array(
				'tokenId'         => 'FIENTRYID',
				'tokenName'       => esc_html_x( 'Entry ID', 'Formidable', 'uncanny-automator' ),
				'tokenType'       => 'int',
				'tokenIdentifier' => self::ENTRY_IDENTIFIER,
			),
			array(
				'tokenId'         => 'FIUSERIP',
				'tokenName'       => esc_html_x( 'User IP', 'Formidable', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => self::ENTRY_IDENTIFIER,
			),
			array(
				'tokenId'         => 'FIENTRYDATE',
				'tokenName'       => esc_html_x( 'Entry submission date', 'Formidable', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => self::ENTRY_IDENTIFIER,
			),
			array(
				'tokenId'         => 'FIENTRYSOURCEURL',
				'tokenName'       => esc_html_x( 'Entry source URL', 'Formidable', 'uncanny-automator' ),
				'tokenType'       => 'text',
				'tokenIdentifier' => self::ENTRY_IDENTIFIER,
			),
		);
	}

	/**
	 * Read the entry row behind the entry tokens.
	 *
	 * @param int $entry_id
	 *
	 * @return array Always the full keyset, so a missing entry leaves empty
	 *               strings rather than raw placeholders in the output.
	 */
	public function hydrate_entry_tokens( $entry_id ) {

		$empty = array(
			'FIENTRYID'        => '',
			'FIUSERIP'         => '',
			'FIENTRYDATE'      => '',
			'FIENTRYSOURCEURL' => '',
		);

		$entry_id = absint( $entry_id );

		if ( empty( $entry_id ) ) {
			return $empty;
		}

		global $wpdb;

		$entry = $wpdb->get_row( $wpdb->prepare( "SELECT ip, created_at, description FROM {$wpdb->prefix}frm_items WHERE id = %d", $entry_id ) ); // phpcs:ignore WordPress.DB.DirectDatabaseQuery -- Formidable exposes no accessor for the raw row.

		if ( null === $entry ) {
			return $empty;
		}

		$description = json_decode( (string) $entry->description );

		// Reuses Formidable's own translation of its entry date format.
		$date_format = esc_html__( 'M j, Y @ G:i', 'formidable' ); // phpcs:ignore WordPress.WP.I18n.TextDomainMismatch, Uncanny_Automator.Strings

		return array(
			'FIENTRYID'        => (string) $entry_id,
			'FIUSERIP'         => (string) $entry->ip,
			'FIENTRYDATE'      => (string) FrmAppHelper::get_localized_date( $date_format, $entry->created_at ),
			'FIENTRYSOURCEURL' => isset( $description->referrer ) ? (string) $description->referrer : '',
		);
	}

	// -------------------------------------------------------------------------
	// Form tokens — bucket = trigger code (framework default)
	// -------------------------------------------------------------------------

	/**
	 * Title and id of the submitted form.
	 *
	 * No `tokenIdentifier` — these belong to the trigger-code bucket, which
	 * Abstract_Trigger fills in for any token that does not name one.
	 *
	 * @param string $trigger_meta Meta code the tokens are named after.
	 *
	 * @return array
	 */
	public function form_tokens( $trigger_meta ) {
		return array(
			array(
				'tokenId'   => $trigger_meta,
				'tokenName' => esc_html_x( 'Form title', 'Formidable', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
			array(
				'tokenId'   => $trigger_meta . '_ID',
				'tokenName' => esc_html_x( 'Form ID', 'Formidable', 'uncanny-automator' ),
				'tokenType' => 'text',
			),
		);
	}

	/**
	 * Resolve the form's title and id.
	 *
	 * @param int    $form_id
	 * @param string $trigger_meta
	 *
	 * @return array
	 */
	public function hydrate_form_tokens( $form_id, $trigger_meta ) {

		$form_id = absint( $form_id );
		$form    = $form_id ? $this->helpers->get_form( $form_id ) : null;

		return array(
			$trigger_meta         => null !== $form ? (string) $form->name : '',
			$trigger_meta . '_ID' => $form_id ? (string) $form_id : '',
		);
	}

	// -------------------------------------------------------------------------
	// Field tokens — bucket = trigger meta
	// -------------------------------------------------------------------------

	/**
	 * One token per field of the selected form.
	 *
	 * @param int    $form_id      Form chosen in the recipe, 0 when none is.
	 * @param string $trigger_meta Meta code the tokens are stored under.
	 *
	 * @return array
	 */
	public function form_field_tokens( $form_id, $trigger_meta ) {

		$form_id = absint( $form_id );

		if ( empty( $form_id ) ) {
			return array();
		}

		$fields = $this->helpers->describe_form_fields( $form_id );

		if ( empty( $fields ) ) {
			return array();
		}

		return Formidable_Field_Tokens::build_tokens( (string) $form_id, $fields, $trigger_meta );
	}

	/**
	 * Resolve every field token against the submitted entry.
	 *
	 * A repeater keeps its rows as entries of a child form, so a field inside
	 * one is read from the children rather than the entry itself.
	 *
	 * @param int    $entry_id
	 * @param int    $form_id
	 * @param string $trigger_meta
	 *
	 * @return array
	 */
	public function hydrate_form_field_tokens( $entry_id, $form_id, $trigger_meta ) {

		$tokens = $this->form_field_tokens( $form_id, $trigger_meta );

		if ( empty( $tokens ) ) {
			return array();
		}

		$this->child_metas = array();

		$entry        = FrmEntry::getOne( absint( $entry_id ), true );
		$parent_metas = ( $entry && isset( $entry->metas ) ) ? (array) $entry->metas : array();

		$values = array();

		foreach ( $tokens as $token ) {

			$key    = explode( '|', $token['tokenId'] );
			$parsed = Formidable_Field_Tokens::parse_token_key( isset( $key[1] ) ? $key[1] : '' );

			$children = ( null !== $parsed && $parsed['section_id'] > 0 )
				? $this->read_child_metas( $parent_metas, $parsed['section_id'] )
				: array();

			$value = Formidable_Field_Tokens::resolve_value( $parsed, $parent_metas, $children );

			// A file field stores an attachment id; rows of a repeater keep theirs as ids.
			if ( null !== $parsed && 0 === $parsed['section_id'] && '' !== $value && $this->helpers->is_file_field( $form_id, $parsed['field_id'] ) ) {
				$value = $this->helpers->attachment_url( $value );
			}

			$values[ $token['tokenId'] ] = $value;
		}

		return $values;
	}

	/**
	 * Read the metas of every row of a repeater, once per request.
	 *
	 * @param array $parent_metas Parent entry metas, keyed by field id.
	 * @param int   $section_id   Field id of the repeating section.
	 *
	 * @return array
	 */
	private function read_child_metas( array $parent_metas, $section_id ) {

		if ( isset( $this->child_metas[ $section_id ] ) ) {
			return $this->child_metas[ $section_id ];
		}

		$child_ids = isset( $parent_metas[ $section_id ] ) ? (array) $parent_metas[ $section_id ] : array();
		$metas     = array();

		foreach ( $child_ids as $child_id ) {

			$child = FrmEntry::getOne( $child_id, true );

			if ( $child && isset( $child->metas ) ) {
				$metas[ $child_id ] = (array) $child->metas;
			}
		}

		$this->child_metas[ $section_id ] = $metas;

		return $metas;
	}

	// -------------------------------------------------------------------------
	// Format conversion
	// -------------------------------------------------------------------------

	/**
	 * Convert trigger-format token definitions to the action format.
	 *
	 * @param array $trigger_tokens
	 *
	 * @return array
	 */
	public static function to_action_tokens( array $trigger_tokens ) {

		$out = array();

		foreach ( $trigger_tokens as $token ) {
			$out[ $token['tokenId'] ] = array(
				'name' => $token['tokenName'],
				'type' => isset( $token['tokenType'] ) ? $token['tokenType'] : 'text',
			);
		}

		return $out;
	}
}
