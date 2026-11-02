<?php

namespace Uncanny_Automator\App\Recipe_Builder\Token\Services\Integration;

use Uncanny_Automator\App\Recipe_Builder\Token\Integration\Registry\WP_Integration_Token_Registry;
use Uncanny_Automator\App\Recipe_Builder\Token\Services\Token_Data_Types_Helper;
use Uncanny_Automator\Traits\Singleton;

/**
 * Integration Token Registry Service.
 *
 * Provides a clean interface to integration token registry operations.
 *
 * This is for integration-level tokens ( Universal tokens etc. ) provided by integrations.
 *
 * @since 7.0.0
 */
class Integration_Token_Registry_Service {

	use Singleton;

	/**
	 * Integration token registry.
	 *
	 * @var WP_Integration_Token_Registry
	 */
	private $registry;

	/**
	 * Initialize the service.
	 *
	 * @return void
	 */
	private function __construct() {
		$this->registry = new WP_Integration_Token_Registry();
	}

	/**
	 * Get integration tokens for an integration.
	 *
	 * @param string $integration_code Integration code.
	 *
	 * @return array Array of integration token definitions in Integration_Token format.
	 */
	public function get_tokens_for_integration( string $integration_code ): array {
		$tokens = $this->registry->get_tokens_by_integration( $integration_code );

		// Map original format to Integration_Token format. The registry's rich
		// camelCase config keys (idTemplate/nameTemplate/fields) pass through
		// snake_cased so configurable tokens keep their setup-modal config on
		// the wire (previously narrowed away).
		$integration_tokens = array();
		foreach ( $tokens as $token_id => $token_data ) {
			$fields = is_array( $token_data['fields'] ?? null ) ? $token_data['fields'] : array();

			// Map original fields to normalized format
			$integration_tokens[ $token_id ] = array(
				'code'               => $token_id,
				'name'               => $token_data['name'] ?? '',
				'data_type'          => Token_Data_Types_Helper::normalize( $token_data['type'] ?? 'text' ),
				'requires_user_data' => $token_data['requiresUser'] ?? false,
				'fields'             => $fields,
				'id_template'        => $this->compose_id_template( $token_id, (string) ( $token_data['idTemplate'] ?? '' ), $fields ),
				'name_template'      => $this->compose_name_template( (string) ( $token_data['nameTemplate'] ?? '' ), $fields ),
			);
		}

		return $integration_tokens;
	}

	/**
	 * Compose the FULL code-prefixed id_template the wire contract requires.
	 *
	 * The registry stores the template as a bare colon-delimited suffix whose
	 * segments name config fields (e.g. 'POSTID:KEY' relative to the token's
	 * base id); the frontend composes inserted ids from id_template alone and
	 * resolves them by longest-prefix-matching the catalog code, so the wire
	 * must carry '{code}:{{POSTID}}:{{KEY}}' — reproducing the frozen
	 * stored-id shape. Segments that don't name a field pass through as
	 * static parts.
	 *
	 * @param string $code   Catalog token code (the stored-id prefix).
	 * @param string $suffix Registry idTemplate suffix.
	 * @param array  $fields Field-directory rows (option_code keys).
	 *
	 * @return string Full template, or '' for non-configurable tokens.
	 */
	private function compose_id_template( string $code, string $suffix, array $fields ): string {
		if ( '' === $suffix ) {
			return '';
		}

		$field_codes = array();
		foreach ( $fields as $field ) {
			if ( is_array( $field ) && isset( $field['option_code'] ) ) {
				$field_codes[ (string) $field['option_code'] ] = true;
			}
		}

		$segments = array();
		foreach ( explode( ':', $suffix ) as $segment ) {
			$segments[] = isset( $field_codes[ $segment ] ) ? '{{' . $segment . '}}' : $segment;
		}

		return $code . ':' . implode( ':', $segments );
	}

	/**
	 * Rewrite name-template placeholders to the frontend sentence syntax.
	 *
	 * The registry stores bare '{{FIELDCODE}}' placeholders; the frontend
	 * renders name_template with the same sentence parser as trigger/action
	 * sentences, which needs '{{Label:FIELDCODE}}' (the label renders until
	 * a value is entered; the field code binds the configured value).
	 *
	 * @param string $template Registry nameTemplate.
	 * @param array  $fields   Field-directory rows (option_code + label).
	 *
	 * @return string Sentence-syntax template, or '' when not declared.
	 */
	private function compose_name_template( string $template, array $fields ): string {
		if ( '' === $template ) {
			return '';
		}

		foreach ( $fields as $field ) {
			if ( ! is_array( $field ) || ! isset( $field['option_code'] ) ) {
				continue;
			}
			$option_code = (string) $field['option_code'];
			$label       = (string) ( $field['label'] ?? $option_code );
			$template    = str_replace( '{{' . $option_code . '}}', '{{' . $label . ':' . $option_code . '}}', $template );
		}

		return $template;
	}
}
