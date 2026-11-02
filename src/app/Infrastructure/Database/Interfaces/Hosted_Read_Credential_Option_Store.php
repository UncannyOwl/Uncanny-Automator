<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Infrastructure\Database\Interfaces;

/**
 * Authoritative read of the site's encrypted hosted read-credential option.
 *
 * @since 8.0.0
 */
interface Hosted_Read_Credential_Option_Store {

	/**
	 * @return array|null The stored entries, or null if the read is unproven.
	 */
	public function read_confirmed(): ?array;
}
