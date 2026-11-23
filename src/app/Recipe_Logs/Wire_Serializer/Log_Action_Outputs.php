<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Logs\Wire_Serializer;

use Uncanny_Automator\App\Bridge\Action_Tokens_Bridge;
use Uncanny_Automator\App\Bridge\Automator_Action_Tokens_Bridge;
use Uncanny_Automator\App\Bridge\Automator_Recipe_Item_Meta_Bridge;
use Uncanny_Automator\App\Bridge\Recipe_Item_Meta_Bridge;

/**
 * What an action returned on its run, as wire property entries: the values it stored for its tokens with
 * `hydrate_tokens()`, each labelled with the name the token picker gives the token.
 *
 * An action log holds one `action_tokens` row per store: the action's own, and the runner's completion status.
 * Only JSON objects represent output token records. The rows read as one record, oldest first, with later
 * values taking precedence. The completion status is left out, since the run's status already shows it.
 *
 * A token the action declares is labelled with its name. One it no longer declares, or one stored by an action no
 * longer in the recipe, is labelled with its token id. A list or an
 * object ships as JSON, a value with line breaks as multi-line text, anything else as text. Every entry passes
 * through Log_Properties_Mapper, which every reader of a log property relies on.
 *
 * @since 8.1.0
 */
final class Log_Action_Outputs {

	/**
	 * The token the runner stores for every action: its completion status.
	 *
	 * @since 8.1.0
	 *
	 * @var string
	 */
	private const COMPLETION_STATUS_TOKEN = 'ACTION_RUN_STATUS';

	/**
	 * Zoom action outputs that carry passcodes.
	 *
	 * @since 8.1.0
	 *
	 * @var string[]
	 */
	private const ZOOM_PASSCODES = array( 'MEETING_PASSWORD', 'WEBINAR_PASSWORD' );

	/**
	 * Zoom action join links that can contain an embedded passcode.
	 *
	 * @since 8.1.0
	 *
	 * @var string[]
	 */
	private const ZOOM_JOIN_LINKS = array( 'MEETING_LINK_URL', 'MEETING_LINK', 'WEBINAR_LINK' );

	/**
	 * The action's token definitions.
	 *
	 * @var Action_Tokens_Bridge
	 */
	private $tokens;

	/**
	 * The action's flat post meta, which the token definitions are read with.
	 *
	 * @var Recipe_Item_Meta_Bridge
	 */
	private $item_meta;

	/**
	 * The wire property mapper.
	 *
	 * @var Log_Properties_Mapper
	 */
	private $properties;

	/**
	 * Wires up the collaborators, defaulting each to its production implementation.
	 *
	 * @since 8.1.0
	 *
	 * @param Action_Tokens_Bridge|null    $tokens     The action's token definitions.
	 * @param Recipe_Item_Meta_Bridge|null $item_meta  The action's flat post meta.
	 * @param Log_Properties_Mapper|null   $properties The wire property mapper.
	 */
	public function __construct(
		?Action_Tokens_Bridge $tokens = null,
		?Recipe_Item_Meta_Bridge $item_meta = null,
		?Log_Properties_Mapper $properties = null
	) {
		$this->tokens     = $tokens ?? new Automator_Action_Tokens_Bridge();
		$this->item_meta  = $item_meta ?? new Automator_Recipe_Item_Meta_Bridge();
		$this->properties = $properties ?? new Log_Properties_Mapper();
	}

	/**
	 * The values an action returned on its run, in the order it stored them.
	 *
	 * @since 8.1.0
	 *
	 * @param array<int,string> $records   The run's stored `action_tokens` values, oldest first.
	 * @param int               $action_id Action post id.
	 * @param string            $code      The action's code from the recipe, '' when unknown.
	 * @param int               $recipe_id Recipe id.
	 * @return array<int,array<string,mixed>> Wire property entries; empty when the action returned nothing.
	 */
	public function map( array $records, int $action_id, string $code, int $recipe_id ): array {
		$values = $this->record( $records );
		unset( $values[ self::COMPLETION_STATUS_TOKEN ] );
		if ( empty( $values ) ) {
			return array();
		}

		$names   = $this->token_names( $action_id, $code, $recipe_id );
		$entries = array();
		foreach ( $values as $token_id => $value ) {
			$token_id  = (string) $token_id;
			$entries[] = $this->entry( $names[ $token_id ] ?? $token_id, $this->redact_value( $token_id, $value ) );
		}

		return $this->properties->map( $entries );
	}

	/**
	 * The stored rows as one record, a later row's value replacing an earlier one's.
	 *
	 * @param array<int,string> $records The stored JSON values, oldest first.
	 * @return array<int|string,mixed> Token id => value.
	 */
	private function record( array $records ): array {
		$record = array();
		foreach ( $records as $stored ) {
			$decoded = json_decode( $stored );
			if ( $decoded instanceof \stdClass ) {
				$record = array_replace( $record, (array) $decoded );
			}
		}

		return $record;
	}

	/**
	 * Hide the passcodes and embedded passcodes Zoom actions return.
	 *
	 * @since 8.1.0
	 *
	 * @param string $token_id The stored token identifier.
	 * @param mixed  $value    The stored value.
	 * @return mixed The value to show in the log.
	 */
	private function redact_value( string $token_id, $value ) {
		if ( in_array( $token_id, self::ZOOM_PASSCODES, true ) && null !== $value && '' !== $value ) {
			return Log_Property_Redactor::MARKER;
		}

		if ( is_string( $value ) && in_array( $token_id, self::ZOOM_JOIN_LINKS, true )
			&& 1 === preg_match( '/[?&]pwd=/i', html_entity_decode( $value, ENT_QUOTES | ENT_HTML5, 'UTF-8' ) ) ) {
			return Log_Property_Redactor::MARKER;
		}

		return $value;
	}

	/**
	 * The names of the tokens the action declares, keyed by token id. Empty when the action is no longer in the
	 * recipe and the recipe has no code for it either.
	 *
	 * @param int    $action_id Action post id.
	 * @param string $code      The action's code from the recipe, '' when unknown.
	 * @param int    $recipe_id Recipe id.
	 * @return array<string,string> Token id => token name.
	 */
	private function token_names( int $action_id, string $code, int $recipe_id ): array {
		$meta = $this->item_meta->get_item_meta( $action_id );
		if ( '' === (string) ( $meta['code'] ?? '' ) ) {
			if ( '' === $code ) {
				return array();
			}
			$meta['code'] = $code;
		}

		$names = array();
		foreach ( $this->tokens->get_renderable_tokens( $meta, $action_id, $recipe_id ) as $token ) {
			$id   = is_array( $token ) ? (string) ( $token['tokenId'] ?? '' ) : '';
			$name = is_array( $token ) ? (string) ( $token['tokenName'] ?? '' ) : '';
			if ( '' !== $id && '' !== $name ) {
				$names[ $id ] = $name;
			}
		}

		return $names;
	}

	/**
	 * One output as a property entry for Log_Properties_Mapper.
	 *
	 * @param string $label The token's name, or its id.
	 * @param mixed  $value The value the action stored.
	 * @return array<string,mixed> The property entry.
	 */
	private function entry( string $label, $value ): array {
		if ( is_array( $value ) || $value instanceof \stdClass ) {
			return array(
				'type'       => Log_Property_Types::CODE,
				'label'      => $label,
				'value'      => (string) wp_json_encode( $value ),
				'attributes' => array( 'code_language' => 'json' ),
			);
		}

		$text = is_bool( $value ) ? ( $value ? 'true' : 'false' ) : (string) $value;

		return array(
			'type'  => false !== strpos( $text, "\n" ) ? Log_Property_Types::TEXTAREA : Log_Property_Types::TEXT,
			'label' => $label,
			'value' => $text,
		);
	}
}
