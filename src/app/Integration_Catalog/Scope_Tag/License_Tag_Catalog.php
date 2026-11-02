<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Integration_Catalog\Scope_Tag;

/**
 * Canonical license display-tag arrays, keyed by paid tier. The labels and
 * helpers name the plans as the shared tier config does: Basic, Plus, Elite
 * (the Basic tier's badge reads Pro).
 *
 * Single source for the `license-pro-*` tag literals shared by the
 * scope-tag resolvers and the template-library feed enrichment. The
 * shapes are a frozen frontend contract (strict Zod tag schema) —
 * change them only together with the FE tag models.
 */
final class License_Tag_Catalog {

	/**
	 * Return the license display tag for a paid tier.
	 *
	 * @param string $tier Tier key: 'pro-basic' | 'pro-plus' | 'pro-elite'.
	 * @return array<string,string>|null Tag array, or null for unknown tiers.
	 */
	public static function for_tier( string $tier ): ?array {
		$tags = array(
			'pro-basic' => array(
				'type'        => 'license',
				'scenario_id' => 'license-pro-basic',
				'label'       => esc_html__( 'Pro', 'uncanny-automator' ),
				'color'       => 'success',
				'helper'      => esc_html__( 'You must have a valid Basic license or higher', 'uncanny-automator' ),
			),
			'pro-plus'  => array(
				'type'        => 'license',
				'scenario_id' => 'license-pro-plus',
				'label'       => esc_html__( 'Plus', 'uncanny-automator' ),
				'color'       => 'success',
				'helper'      => esc_html__( 'You must have a valid Plus license or higher', 'uncanny-automator' ),
			),
			'pro-elite' => array(
				'type'        => 'license',
				'scenario_id' => 'license-pro-elite',
				'label'       => esc_html__( 'Elite', 'uncanny-automator' ),
				'color'       => 'success',
				'icon'        => 'gem',
				'helper'      => esc_html__( 'You must have a valid Elite license', 'uncanny-automator' ),
			),
		);

		return isset( $tags[ $tier ] ) ? $tags[ $tier ] : null;
	}
}
