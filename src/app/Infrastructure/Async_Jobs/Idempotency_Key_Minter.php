<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Async_Jobs;

/**
 * Mints deterministic idempotency keys for async action dispatch.
 *
 * Same (recipe_log_id, action_id, retry_seq) → same key, every time. The
 * platform's V2AsyncDispatch / V3 async chassis uses (license_id, key) as
 * the 12h dedup tuple per Contract D — non-deterministic keys would burn
 * cache slots without delivering dedup, and retries on the SAME logical
 * step would dispatch as fresh jobs instead of returning the original
 * job_id.
 *
 * Format: `iak_<32-hex>` where the hex is sha256(site_uuid . payload)
 * truncated to 32 chars. The site_uuid prefix scopes keys to this site,
 * preventing accidental cross-site collisions if a key ever leaks into
 * shared storage. The literal `iak_` prefix (idempotent action key) is
 * for log-grep readability — `Idempotency-Key: iak_…` is unambiguous in
 * platform logs.
 *
 * Persisted to wp_uap_async_jobs.idempotency_key alongside the job_id
 * the platform returns, so recovery polling can correlate a stale job_id
 * back to its key for retry-safe resume.
 *
 * Two minters live on this class — `mint()` for v2 / non-upload v3 async
 * dispatch (carries retry_seq + site_uuid), `mint_for_upload()` for
 * upload-class actions whose 24h R6 cache requires bit-for-bit canonical
 * alignment with the platform's file-transfer spec (`<run>:<act>` only,
 * no retry_seq, no site_uuid prefix). The split is deliberate: bumping
 * retry_seq on an upload would mint a different key on a transient
 * network blip, defeat the platform's same-key-same-body cache, and
 * upload the file twice.
 *
 * @since 8.0.0
 */
final class Idempotency_Key_Minter {

	private const PREFIX = 'iak_';

	/**
	 * Mint a deterministic key.
	 *
	 * @param int $recipe_log_id Authoritative run identifier.
	 * @param int $action_id     Recipe-builder action ID (the action step's post ID).
	 * @param int $retry_seq     Retry sequence — bump on intentional re-dispatch
	 *                           (e.g. user-triggered retry of a failed step).
	 *                           Default 0 for first attempt.
	 *
	 * @return string
	 */
	public function mint( int $recipe_log_id, int $action_id, int $retry_seq = 0 ): string {

		$payload = sprintf( '%d:%d:%d', $recipe_log_id, $action_id, $retry_seq );
		$scope   = (string) automator_get_option( 'automator_site_uuid', '' );
		$digest  = hash( 'sha256', $scope . '|' . $payload );

		return self::PREFIX . substr( $digest, 0, 32 );
	}

	/**
	 * Mint an upload-spec-aligned key for upload-class actions.
	 *
	 * Per the platform's file-transfer R6 contract, the upload pathway
	 * uses a canonical string of `"<recipe_log_id>:<action_id>"` (no
	 * retry_seq, no site_uuid prefix) so retries on the same logical
	 * (run, action) hash to the same key. The platform's IdempotencyKey
	 * middleware then replays the cached 202 response on retry instead
	 * of dispatching again — preventing duplicate uploads on transient
	 * network failures.
	 *
	 * site_uuid is omitted because the platform middleware already
	 * scopes by `license_id`, making the prefix redundant. retry_seq is
	 * omitted because user-triggered "retry this step" on an upload
	 * MUST hit the cache; bumping the seq would defeat the dedup and
	 * the file would upload twice.
	 *
	 * Existing `mint()` (with retry_seq + site_uuid) stays in use for
	 * non-upload async dispatch (Slack, CREATE_FOLDER, etc.) — those
	 * flows aren't validated by R6's body-hash matching, so the extra
	 * inputs are harmless.
	 *
	 * @param int $recipe_log_id Authoritative run identifier.
	 * @param int $action_id     Recipe-builder action ID (the action step's post ID).
	 *
	 * @return string `iak_<32-hex>` where the hex is sha256("<run>:<act>") truncated.
	 */
	public function mint_for_upload( int $recipe_log_id, int $action_id ): string {

		$payload = sprintf( '%d:%d', $recipe_log_id, $action_id );
		$digest  = hash( 'sha256', $payload );

		return self::PREFIX . substr( $digest, 0, 32 );
	}
}
