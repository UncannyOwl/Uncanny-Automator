<?php
/**
 * Abstract base class for MCP loop tools.
 *
 * Provides shared functionality for loop add/update operations.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Catalog\Loops;

use Uncanny_Automator\Api\Presentation\Loop\Loop_Token_Sentence_Composer;
use Uncanny_Automator\Api\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\Api\Services\Search\Collectors\Loopable_Token_Collector;

/**
 * Abstract Loop Tool.
 *
 * Base class for loop CRUD operations via MCP.
 * Handles shared logic for token enumeration, JSON parsing, and backup building.
 *
 * @since 7.0.0
 */
abstract class Abstract_Loop_Tool extends Abstract_MCP_Tool {

	/**
	 * Get available loopable tokens for schema enum.
	 *
	 * Collects all loopable tokens from the system for use in JSON schema enum.
	 *
	 * @return array Array of loopable token IDs.
	 */
	protected function get_loopable_token_enum(): array {
		$collector  = new Loopable_Token_Collector();
		$collection = $collector->collect_loopable_tokens( '' );

		if ( $collection->is_empty() ) {
			return array();
		}

		// Return just the IDs for the enum.
		return array_map(
			fn( $item ) => $item['id'],
			$collection->to_array()
		);
	}

	/**
	 * Build backup structure for token loops.
	 *
	 * Creates the sentence_html that displays the token name in the UI.
	 * Includes icon from token or integration for visual display.
	 *
	 * @param string $token_id The loopable token ID (e.g., TOKEN_EXTENDED:DATA_TOKEN_ACTIVE_SUBSCRIPTION:...).
	 * @return array Backup structure with sentence and sentence_html.
	 */
	protected function build_token_backup( string $token_id ): array {
		$token_meta = $this->resolve_token_backup_meta( $token_id );

		$composer = new Loop_Token_Sentence_Composer();

		return array(
			'sentence'      => '{{Token:TOKEN}}',
			'sentence_html' => $composer->compose( $token_meta['name'], $token_meta['icon'] ),
		);
	}

	/**
	 * Resolve display metadata for a loopable token backup.
	 *
	 * @param string $token_id Loopable token identifier.
	 *
	 * @return array{name:string,icon:string}
	 */
	private function resolve_token_backup_meta( string $token_id ): array {
		$registry_match = $this->find_registry_token_meta( $token_id );
		if ( ! empty( $registry_match ) ) {
			return $registry_match;
		}

		$parsed = $this->parse_loopable_token_identifier( $token_id );
		if ( empty( $parsed ) ) {
			return array(
				'name' => $token_id,
				'icon' => '',
			);
		}

		$token_name = $this->resolve_recipe_context_token_name( $parsed );
		$icon_url   = $this->resolve_integration_icon( $parsed['integration_code'] ?? '' );

		return array(
			'name' => '' !== $token_name ? $token_name : ( $parsed['loopable_id'] ?? $token_id ),
			'icon' => $icon_url,
		);
	}

	/**
	 * Find token metadata from the registry collector.
	 *
	 * @param string $token_id Loopable token identifier.
	 *
	 * @return array{name:string,icon:string}
	 */
	private function find_registry_token_meta( string $token_id ): array {
		$collector  = new Loopable_Token_Collector();
		$collection = $collector->collect_loopable_tokens( '' );
		$tokens     = $collection->to_array();

		foreach ( $tokens as $token ) {
			if ( ( $token['id'] ?? '' ) === $token_id ) {
				$icon = (string) ( $token['icon'] ?? '' );
				if ( '' === $icon ) {
					$icon = $this->resolve_integration_icon( (string) ( $token['integration_code'] ?? '' ) );
				}

				return array(
					'name' => (string) ( $token['name'] ?? $token_id ),
					'icon' => $icon,
				);
			}
		}

		return array();
	}

	/**
	 * Parse a loopable token identifier into a normalized structure.
	 *
	 * @param string $token_id Loopable token identifier.
	 *
	 * @return array<string,mixed>
	 */
	protected function parse_loopable_token_identifier( string $token_id ): array {
		$normalized = trim( $token_id );
		$normalized = preg_replace( '/^\{\{|\}\}$/', '', $normalized );

		if ( ! is_string( $normalized ) || '' === $normalized ) {
			return array();
		}

		$parts = explode( ':', $normalized );

		if ( count( $parts ) < 5 || 'TOKEN_EXTENDED' !== ( $parts[0] ?? '' ) ) {
			return array();
		}

		if ( 'UNIVERSAL' === ( $parts[2] ?? '' ) && count( $parts ) >= 5 ) {
			return array(
				'context'          => 'universal',
				'integration_code' => (string) ( $parts[3] ?? '' ),
				'loopable_id'      => (string) ( $parts[4] ?? '' ),
			);
		}

		if ( 'ACTION_TOKEN' === ( $parts[2] ?? '' ) && count( $parts ) >= 6 ) {
			return array(
				'context'          => 'action',
				'entity_id'        => absint( $parts[3] ?? 0 ),
				'entity_code'      => (string) ( $parts[4] ?? '' ),
				'loopable_id'      => (string) ( $parts[5] ?? '' ),
				'integration_code' => $this->resolve_definition_integration_code( 'action', (string) ( $parts[4] ?? '' ) ),
			);
		}

		if ( is_numeric( $parts[2] ?? null ) && count( $parts ) >= 5 ) {
			return array(
				'context'          => 'trigger',
				'entity_id'        => absint( $parts[2] ?? 0 ),
				'entity_code'      => (string) ( $parts[3] ?? '' ),
				'loopable_id'      => (string) ( $parts[4] ?? '' ),
				'integration_code' => $this->resolve_definition_integration_code( 'trigger', (string) ( $parts[3] ?? '' ) ),
			);
		}

		return array();
	}

	/**
	 * Resolve a recipe-context loopable token display name.
	 *
	 * @param array<string,mixed> $parsed Parsed token identifier.
	 *
	 * @return string
	 */
	private function resolve_recipe_context_token_name( array $parsed ): string {
		if ( ! function_exists( 'Automator' ) ) {
			return '';
		}

		$context      = (string) ( $parsed['context'] ?? '' );
		$entity_code  = (string) ( $parsed['entity_code'] ?? '' );
		$entity_id    = absint( $parsed['entity_id'] ?? 0 );
		$loopable_id  = (string) ( $parsed['loopable_id'] ?? '' );
		$definition   = 'action' === $context ? Automator()->get_action( $entity_code ) : Automator()->get_trigger( $entity_code );
		$resolved     = $this->resolve_name_from_loopable_definition( $definition, $loopable_id, $entity_id );

		if ( '' !== $resolved ) {
			return $resolved;
		}

		$meta_code = $this->resolve_definition_meta_code( $definition );
		if ( '' !== $meta_code && $entity_id > 0 ) {
			$title = get_post_meta( $entity_id, $meta_code, true );
			if ( is_string( $title ) && '' !== trim( $title ) ) {
				return $title;
			}
		}

		return '';
	}

	/**
	 * Resolve a loopable token name by instantiating its definition for the saved entity.
	 *
	 * @param mixed  $definition  Action or trigger definition.
	 * @param string $loopable_id Loopable token ID.
	 * @param int    $entity_id   Saved trigger/action post ID.
	 *
	 * @return string
	 */
	private function resolve_name_from_loopable_definition( $definition, string $loopable_id, int $entity_id ): string {
		if ( ! is_array( $definition ) ) {
			return '';
		}

		$token_definition = $definition['loopable_tokens'][ $loopable_id ] ?? null;
		$class_name       = '';

		if ( is_object( $token_definition ) ) {
			$class_name = get_class( $token_definition );
		} elseif ( is_string( $token_definition ) ) {
			$class_name = $token_definition;
		}

		if ( '' === $class_name || ! class_exists( $class_name ) ) {
			return '';
		}

		try {
			$reflection  = new \ReflectionClass( $class_name );
			$constructor = $reflection->getConstructor();

			if ( null === $constructor || 0 === $constructor->getNumberOfParameters() ) {
				$instance = $reflection->newInstance();
			} else {
				$instance = $reflection->newInstance( $entity_id );
			}
		} catch ( \Throwable $e ) {
			return '';
		}

		if ( is_object( $instance ) && method_exists( $instance, 'get_name' ) ) {
			$name = $instance->get_name();
			if ( is_string( $name ) && '' !== trim( $name ) ) {
				return $name;
			}
		}

		return '';
	}

	/**
	 * Resolve the meta code used by an action or trigger definition.
	 *
	 * @param mixed $definition Action or trigger definition.
	 *
	 * @return string
	 */
	private function resolve_definition_meta_code( $definition ): string {
		if ( ! is_array( $definition ) ) {
			return '';
		}

		$meta_code = $definition['meta_code'] ?? $definition['trigger_meta_code'] ?? '';

		if ( ! is_string( $meta_code ) || '' === $meta_code ) {
			return '';
		}

		return $meta_code;
	}

	/**
	 * Resolve the integration code from an action or trigger definition.
	 *
	 * @param string $definition_type One of action or trigger.
	 * @param string $entity_code     Action or trigger code.
	 *
	 * @return string
	 */
	private function resolve_definition_integration_code( string $definition_type, string $entity_code ): string {
		if ( '' === $entity_code || ! function_exists( 'Automator' ) ) {
			return '';
		}

		$definition = 'action' === $definition_type
			? Automator()->get_action( $entity_code )
			: Automator()->get_trigger( $entity_code );

		if ( ! is_array( $definition ) ) {
			return '';
		}

		$integration_code = $definition['integration'] ?? $definition['integration_code'] ?? '';

		return is_string( $integration_code ) ? $integration_code : '';
	}

	/**
	 * Resolve the icon for an integration code.
	 *
	 * @param string $integration_code Integration code.
	 *
	 * @return string
	 */
	private function resolve_integration_icon( string $integration_code ): string {
		if ( '' === $integration_code || ! function_exists( 'Automator' ) ) {
			return '';
		}

		$integration = Automator()->get_integration( $integration_code );

		if ( ! is_array( $integration ) ) {
			return '';
		}

		return (string) ( $integration['icon_svg'] ?? $integration['icon'] ?? '' );
	}

}
