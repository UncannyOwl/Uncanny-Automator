<?php
/**
 * Keep transferred file bytes separate from native attachment metadata.
 *
 * @package Uncanny_Automator
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Model_Context_Protocol\Tools\Rest;

use WP_REST_Request;

/** Raw file MIME headers describe the file, never a REST JSON argument body. */
final class Media_Upload_Request extends WP_REST_Request {

	/** Preserve the MIME header for core upload handling without parsing the file. */
	public function is_json_content_type(): bool {
		return false;
	}

	/** Body parameters are explicit metadata, never fields decoded from file bytes. */
	protected function parse_body_params(): void {
	}
}
