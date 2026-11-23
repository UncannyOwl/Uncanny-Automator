<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Recipe_Builder\Recipe\Template_Library;

use Uncanny_Automator\App\Integration_Catalog\Dependency\Dependencies_Resolver;
use Uncanny_Automator\App\Integration_Catalog\Dependency\Dependency;
use Uncanny_Automator\App\Integration_Catalog\Dependency\Dependency_Context;
use Uncanny_Automator\App\Integration_Catalog\Scope_Tag\Resolvers\Integration\Dependency\Dependency_Tag_Resolver;
use Uncanny_Automator\App\Integration_Catalog\Services\Integration_Query_Service;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Availability_Status;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Key;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Enums\Wire_Node_Type;
use Uncanny_Automator\App\Recipe_Builder\Recipe\Wire_Serializer\Recipe_Entity_Availability_Resolver;

/**
 * Per-node `{ availability, tags }` for a template preview.
 *
 * A preview shows what the template does, not what this site lacks: every
 * node reads as usable and carries no Locked chip, since anything missing
 * is set up after import. The node keeps the other tags the builder would
 * give it, such as its license tier. An item or block modifier whose
 * integration still needs a plugin or an account on this site also gets
 * the catalog's dependency chip (Not installed, Not active, Not connected),
 * with that requirement's own description as its tooltip.
 *
 * @since 8.1.0
 */
final class Template_Preview_Availability_Resolver {

	/**
	 * Tag type of the builder's Locked chip, which a preview leaves out.
	 *
	 * @since 8.1.0
	 *
	 * @var string
	 */
	const LOCKED_TAG_TYPE = 'availability';

	/**
	 * Tag key naming the chip's scenario.
	 *
	 * @since 8.1.0
	 *
	 * @var string
	 */
	const TAG_SCENARIO_ID = 'scenario_id';

	/**
	 * Tag key holding the chip's tooltip text.
	 *
	 * @since 8.1.0
	 *
	 * @var string
	 */
	const TAG_HELPER = 'helper';

	/**
	 * The dependency type each dependency chip stands for, so the chip's tooltip can quote that requirement.
	 *
	 * @since 8.1.0
	 *
	 * @var array<string,string>
	 */
	const DEPENDENCY_TYPE_BY_SCENARIO = array(
		'dependency-not-installed' => 'installable',
		'dependency-not-active'    => 'installable',
		'dependency-not-connected' => 'account',
	);

	/**
	 * The builder's resolver, read for the node's tags.
	 *
	 * @var Recipe_Entity_Availability_Resolver
	 */
	private $resolver;

	/**
	 * Catalog lookups for the node's integration.
	 *
	 * @var Integration_Query_Service
	 */
	private $query;

	/**
	 * Resolves an integration's dependencies on this site.
	 *
	 * @var Dependencies_Resolver
	 */
	private $dependencies;

	/**
	 * Chooses an integration's dependency chip.
	 *
	 * @var Dependency_Tag_Resolver
	 */
	private $dependency_tags;

	/**
	 * Dependency chips by integration code, resolved once per preview.
	 *
	 * @var array<string,array<int,array<string,string>>>
	 */
	private $chips_by_integration = array();

	/**
	 * Lazy-default constructor injection (Rule 9c).
	 *
	 * @since 8.1.0 Takes the catalog lookups behind the dependency chips.
	 *
	 * @param Recipe_Entity_Availability_Resolver|null $resolver        The builder's resolver.
	 * @param Integration_Query_Service|null           $query           Catalog lookups.
	 * @param Dependencies_Resolver|null               $dependencies    Dependency resolution.
	 * @param Dependency_Tag_Resolver|null             $dependency_tags Dependency chip selection.
	 */
	public function __construct(
		?Recipe_Entity_Availability_Resolver $resolver = null,
		?Integration_Query_Service $query = null,
		?Dependencies_Resolver $dependencies = null,
		?Dependency_Tag_Resolver $dependency_tags = null
	) {
		$this->resolver        = $resolver ?? new Recipe_Entity_Availability_Resolver();
		$this->query           = $query ?? Integration_Query_Service::get_instance();
		$this->dependencies    = $dependencies ?? new Dependencies_Resolver( new Dependency_Context() );
		$this->dependency_tags = $dependency_tags ?? new Dependency_Tag_Resolver();
	}

	/**
	 * The node's availability, always usable, and its builder tags without the Locked chip, plus its integration's dependency chip.
	 *
	 * @since 8.1.0
	 *
	 * @param array<string,mixed> $node A serialized wire node.
	 * @return array{availability: array{status: string}, tags: array}
	 */
	public function resolve( array $node ): array {
		$tags = array_filter(
			$this->resolver->resolve( $node )[ Wire_Key::TAGS ],
			static function ( array $tag ): bool {
				return self::LOCKED_TAG_TYPE !== ( $tag[ Wire_Key::TYPE ] ?? '' );
			}
		);

		return array(
			Wire_Key::AVAILABILITY => array( Wire_Key::STATUS => Availability_Status::USABLE ),
			Wire_Key::TAGS         => array_merge( array_values( $tags ), $this->dependency_chips( $node ) ),
		);
	}

	/**
	 * The dependency chip of an item's or block modifier's integration, or none for a block or an integration with nothing missing.
	 *
	 * @since 8.1.0
	 *
	 * @param array<string,mixed> $node A serialized wire node.
	 * @return array<int,array<string,string>>
	 */
	private function dependency_chips( array $node ): array {
		$type = (string) ( $node[ Wire_Key::TYPE ] ?? '' );
		if ( Wire_Node_Type::ITEM !== $type && Wire_Node_Type::BLOCK_MODIFIER !== $type ) {
			return array();
		}

		$code = (string) ( $node[ Wire_Key::INTEGRATION_CODE ] ?? '' );
		if ( ! isset( $this->chips_by_integration[ $code ] ) ) {
			$this->chips_by_integration[ $code ] = $this->integration_chips( $code );
		}

		return $this->chips_by_integration[ $code ];
	}

	/**
	 * The catalog's dependency chip for an integration, each with the description of the unmet requirement it stands for as its tooltip.
	 *
	 * @since 8.1.0
	 *
	 * @param string $code The integration code.
	 * @return array<int,array<string,string>>
	 */
	private function integration_chips( string $code ): array {
		$integration = $this->query->get_integration( $code );
		if ( null === $integration ) {
			return array();
		}

		$dependencies = $this->dependencies->resolve_integration_dependencies( $integration );
		if ( ! $this->dependency_tags->should_evaluate( $integration, null, $dependencies ) ) {
			return array();
		}

		$chips = array();
		foreach ( $this->dependency_tags->evaluate() as $chip ) {
			$helper = $this->unmet_description( (string) ( $chip[ self::TAG_SCENARIO_ID ] ?? '' ), $dependencies['items'] ?? array() );
			if ( '' !== $helper ) {
				$chip[ self::TAG_HELPER ] = $helper;
			}
			$chips[] = $chip;
		}

		return $chips;
	}

	/**
	 * The description of the first unmet dependency of the type a chip stands for, as plain text: a tooltip renders no markup, so tags and markdown emphasis markers are dropped and their words kept.
	 *
	 * @since 8.1.0
	 *
	 * @param string           $scenario_id  The chip's scenario.
	 * @param array<int,mixed> $dependencies The integration's dependencies.
	 * @return string The plain-text description, or '' when none matches.
	 */
	private function unmet_description( string $scenario_id, array $dependencies ): string {
		$type = self::DEPENDENCY_TYPE_BY_SCENARIO[ $scenario_id ] ?? '';

		foreach ( $dependencies as $dependency ) {
			if ( $dependency instanceof Dependency && $type === $dependency->get_type() && ! $dependency->is_met() ) {
				return trim( (string) preg_replace( '/(\*\*|\*)(.+?)\1/', '$2', wp_strip_all_tags( $dependency->get_description() ) ) );
			}
		}

		return '';
	}
}
