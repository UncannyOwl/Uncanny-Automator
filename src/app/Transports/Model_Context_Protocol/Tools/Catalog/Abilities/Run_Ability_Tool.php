<?php
/**
 * Runs a WordPress ability the site owner switched on.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Catalog\Abilities;

use Uncanny_Automator\App\Recipe_Builder\Shared\User\Value_Objects\User_Context;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Json_Rpc_Response;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Abilities\Ability_Catalog;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Abilities\Ability_Input;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Abstract_MCP_Tool;
use Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Rest\Rest_Request_Scope;
use WP_Ability;

/**
 * One tool for every switched-on ability, so the tool list stays the same
 * size however many are on. Discovery uses discover_ability and the shared Automator ability catalog; this tool only runs one by name.
 *
 * The ability's own execute() normalises and validates input, checks the
 * plugin's permission callback and validates output. This tool adds the
 * site-owner switch and the credential's write scope for abilities that
 * can make changes, for both Agent and external callers.
 */
final class Run_Ability_Tool extends Abstract_MCP_Tool {

	/**
	 * Tool name.
	 */
	public const NAME = 'run_ability';

	/**
	 * Switched-on abilities.
	 *
	 * @var Ability_Catalog
	 */
	private Ability_Catalog $catalog;

	/**
	 * @param Ability_Catalog|null $catalog Catalog; defaults to the stored switches.
	 */
	public function __construct( ?Ability_Catalog $catalog = null ) {
		$this->catalog = $catalog ?? new Ability_Catalog();
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_name(): string {
		return self::NAME;
	}

	/**
	 * {@inheritDoc}
	 */
	public function get_description(): string {
		return 'Run a WordPress ability the site owner has switched on for AI apps, such as one registered by WooCommerce. '
			. 'Requires the WordPress Abilities API and the ability name (e.g. "woocommerce/products-query"). '
			. 'Use discover_ability to search abilities, then pass its returned name as ability to inspect the complete input schema. '
			. 'Pass arguments as the actual JSON value from that schema, not JSON-encoded text. '
			. 'Example: {"ability":"core/get-site-info","arguments":{"fields":["name","url"]}}. '
			. 'Read-only abilities run with read access; abilities that make changes need write access.';
	}

	/**
	 * {@inheritDoc}
	 */
	protected function get_annotations(): array {
		return array(
			'readOnlyHint'    => false,
			'destructiveHint' => true,
			'idempotentHint'  => false,
			'openWorldHint'   => true,
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function schema_definition() {
		return array(
			'type'       => 'object',
			'properties' => array(
				'ability'   => array(
					'type'        => 'string',
					'description' => 'Ability name, e.g. "woocommerce/products-query". Use discover_ability to find enabled names.',
				),
				'arguments' => array(
					'description' => 'Pass the actual JSON value required by the ability\'s input schema from discovery. '
						. 'For an object input, use "arguments": {"fields":["name","url"]}. Do not serialize that object into a string. '
						. 'Preserve arrays, numbers, booleans, strings or null when the ability expects those types. '
						. 'Omit arguments to use the ability\'s default.',
				),
			),
			'required'   => array( 'ability' ),
		);
	}

	/**
	 * {@inheritDoc}
	 */
	protected function output_schema_definition(): ?array {
		// Abilities return whatever shape their plugin declared. A fixed contract
		// would make conforming clients reject valid results.
		return null;
	}

	/**
	 * {@inheritDoc}
	 */
	protected function execute_tool( User_Context $user_context, array $params ): array {
		if ( ! Ability_Catalog::is_supported() ) {
			return Json_Rpc_Response::create_error_response( 'The WordPress Abilities API is not available on this site.' );
		}
		$name = trim( (string) ( $params['ability'] ?? '' ) );

		if ( '' === $name ) {
			return Json_Rpc_Response::create_error_response( 'ability is required.' );
		}

		$ability = $this->catalog->find_enabled( $name );

		if ( ! $ability instanceof WP_Ability ) {
			return Json_Rpc_Response::create_error_response( $this->catalog->refusal_for( $name ) );
		}

		if ( ! $user_context->can_write() && ! $this->catalog->is_read_only( $ability ) ) {
			return Json_Rpc_Response::create_error_response( sprintf( 'The ability "%s" can make changes. This access token only allows reading.', $name ) );
		}

		$arguments = $params['arguments'] ?? null;
		$arguments = $this->decode_container_input( $arguments, $ability->get_input_schema() );

		$input = Ability_Input::normalize( $arguments, $ability->get_input_schema() );
		// WordPress announces the run only after input and permission checks pass,
		// so an error before then is a refusal that changed nothing.
		$started = false;
		$watch   = static function ( $ability_name ) use ( $name, &$started ): void {
			if ( $name === $ability_name ) {
				$started = true;
			}
		};
		add_action( 'wp_before_execute_ability', $watch, PHP_INT_MAX, 1 );
		try {
			// Keep this scope: without it, every ability that makes internal
			// REST requests (all of LifterLMS's) is refused for this MCP token.
			// Why it adds no authority: Rest_Request_Scope::run_plugin_ability().
			$result = Rest_Request_Scope::run_plugin_ability(
				$user_context,
				static function () use ( $ability, $input ) {
					return $ability->execute( $input );
				}
			);
		} finally {
			remove_action( 'wp_before_execute_ability', $watch, PHP_INT_MAX );
		}

		if ( is_wp_error( $result ) ) {
			return Json_Rpc_Response::create_wp_error_response( $result, $started, ! $started );
		}

		return Json_Rpc_Response::create_success_response(
			sprintf( 'Ability "%s" executed.', $name ),
			array(
				'ability' => $name,
				'result'  => is_array( $result ) ? $result : array( 'value' => $result ),
			)
		);
	}

	/**
	 * Decode an accidentally serialized container without guessing its contents.
	 *
	 * Only an explicit object/array schema permits decoding. String, union and
	 * unspecified schemas retain their input. Native execute() still validates
	 * the decoded fields and checks permission before running the callback.
	 *
	 * @param mixed $input Supplied input.
	 * @param array $schema Native ability input schema.
	 * @return mixed
	 */
	private function decode_container_input( $input, array $schema ) {
		$type = $schema['type'] ?? null;
		if ( ! is_string( $input ) || ! in_array( $type, array( 'object', 'array' ), true ) ) {
			return $input;
		}
		$text = ltrim( $input );
		if ( '' === $text || ( 'object' === $type ? '{' : '[' ) !== $text[0] ) {
			return $input;
		}
		$decoded = json_decode( $text, true );
		return JSON_ERROR_NONE === json_last_error() && is_array( $decoded ) ? $decoded : $input;
	}
}
