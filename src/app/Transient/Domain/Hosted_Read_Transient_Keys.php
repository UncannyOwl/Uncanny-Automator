<?php
/**
 * Hosted read transient keys.
 *
 * @package Uncanny_Automator
 * @since 8.0.0
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transient\Domain;

/**
 * Names the cached AI Dashboard reads from Automator MCP: the connected AIs
 * and the AI meter. AI activity is never cached, because WordPress keeps no
 * text an AI wrote.
 *
 * Each read is keyed by the current generation, so changing the generation
 * drops every cached read at once without listing their keys.
 */
class Hosted_Read_Transient_Keys {

	/** The current generation of cached reads. */
	public const GENERATION = 'automator_mcp_hosted_reads_generation';

	/**
	 * Seconds a read is kept: five minutes. Connecting the site clears the
	 * reads at once; a change made only on Automator MCP (an AI added or
	 * removed, a meter that moved) shows within this time, because nothing on
	 * the site changes when it happens.
	 */
	public const TTL = 300;

	/**
	 * Seconds the generation is kept: one day. A generation that expires only
	 * makes the next read fresh, and nothing written here outlives it.
	 */
	public const GENERATION_TTL = 86400;

	/**
	 * The transient key of one read.
	 *
	 * @param string $generation The current generation.
	 * @param string $scope      The credential the read is made with.
	 * @param string $read       The read path and query.
	 *
	 * @return string
	 */
	public static function read( string $generation, string $scope, string $read ): string {
		return 'automator_mcp_hosted_read_' . substr( hash( 'sha256', $generation . "\0" . $scope . "\0" . $read ), 0, 32 );
	}
}
