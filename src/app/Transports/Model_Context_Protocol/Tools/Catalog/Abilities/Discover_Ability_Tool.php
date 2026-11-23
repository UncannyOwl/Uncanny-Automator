<?php

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Catalog\Abilities;

use Uncanny_Automator\App\Recipe_Builder\Shared\User\Value_Objects\User_Context;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Abilities\Ability_Catalog;

/**
 * Direct discovery through the shared WordPress ability catalog.
 *
 * @since 8.0.0
 */
final class Discover_Ability_Tool extends Abstract_MCP_Tool {

	public const NAME = 'discover_ability';

	/** @var Ability_Catalog */
	private Ability_Catalog $catalog;

	/**
	 * @since 8.0.0
	 * @param Ability_Catalog|null $catalog Shared ability catalog.
	 */
	public function __construct( ?Ability_Catalog $catalog = null ) {
		$this->catalog = $catalog ?? new Ability_Catalog();
	}

	/** @inheritDoc */
	public function get_name() {
		return self::NAME;
	}

	/** @inheritDoc */
	public function get_description() {
		return 'Shortcut to the ability lookup used by automator/search (type: ability) and automator/get-component-schema (component_type: ability). Search or list WordPress/plugin abilities and their availability, or pass an exact ability name to inspect its input/output schemas before run_ability. Uses the same catalog, site-owner settings and plugin restrictions; does not execute abilities.';
	}

	/** @inheritDoc */
	protected function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'ability'  => array(
					'type'        => 'string',
					'description' => 'Exact returned ability name, such as core/get-site-info. Returns its complete schemas. Takes precedence over search, provider and paging.',
				),
				'search'   => array(
					'type'        => 'string',
					'description' => 'Keywords matching ability name, label, description, provider or category. Omit to browse the catalog.',
				),
				'provider' => array(
					'type'        => 'string',
					'description' => 'Optional provider.slug from a result, not the ability namespace. WordPress core abilities use wordpress.',
				),
				'limit'    => array(
					'type'    => 'integer',
					'minimum' => 1,
					'maximum' => 100,
					'default' => 20,
				),
				'offset'   => array(
					'type'    => 'integer',
					'minimum' => 0,
					'default' => 0,
				),
			),
		);
	}

	/** @inheritDoc */
	protected function execute_tool( User_Context $user_context, array $params ) {
		if ( ! Ability_Catalog::is_supported() ) {
			return Json_Rpc_Response::create_error_response( 'The WordPress Abilities API is not available on this site.' );
		}

		$name = trim( $params['ability'] ?? '' );
		if ( '' !== $name ) {
			$ability = $this->catalog->find_enabled( $name );
			if ( null === $ability ) {
				return Json_Rpc_Response::create_error_response( $this->catalog->refusal_for( $name ) );
			}
			$message = 'Ability schema retrieved. Use run_ability with this name and input schema.';
			$effect  = $this->catalog->undeclared_effect_message( $ability );
			return Json_Rpc_Response::create_success_response( null === $effect ? $message : $message . ' ' . $effect, $this->catalog->schema_for( $ability ) );
		}

		$search   = trim( $params['search'] ?? '' );
		$provider = trim( $params['provider'] ?? '' );
		$limit    = $params['limit'] ?? 20;
		$offset   = $params['offset'] ?? 0;
		$result   = $this->catalog->search( '' === $search ? 'list' : 'search', $search, '' === $provider ? null : $provider, $limit, $offset );

		$message          = 'Ability catalog retrieved. Pass a returned name as ability to inspect its schemas.';
		$automator_search = preg_match( '/\bautomator\b/i', $search ) || 'uncanny-automator' === strtolower( $provider );
		$recipe_search    = '' === $provider && preg_match( '/\b(?:recipes?|triggers?|actions?)\b/i', $search );
		if ( $automator_search || $recipe_search ) {
			$guidance = 'Discover Automator operations through REST. Search for /wp-abilities/v1/abilities/automator/ with search_rest_operations in hosted MCP or rest_discover on a direct WordPress connection.';
			$message  = 0 === $result['total'] ? $guidance : $message . ' ' . $guidance;
		}
		return Json_Rpc_Response::create_success_response(
			$message,
			array(
				'items'  => $result['items'],
				'total'  => $result['total'],
				'limit'  => $limit,
				'offset' => $offset,
			)
		);
	}
}
