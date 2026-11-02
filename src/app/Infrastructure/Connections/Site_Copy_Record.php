<?php
declare( strict_types=1 );

namespace Uncanny_Automator\App\Infrastructure\Connections;

/**
 * What this site is, when it is a copy of another site — staging-copies
 * handoff R2.
 *
 * A copy has two answers besides "this site moved here":
 *
 *  - adopt     "This is a staging copy — use the live site's app connections
 *              here." The copy attaches to the same accounts
 *              ({@see Connection_Adoption}).
 *  - keep_off  "This is a separate site — keep the copied connections
 *              switched off." The inherited rows stay quarantined.
 *
 * A copy the plugin recognised by itself (a non-production site whose address
 * changed registers as a copy without asking) starts `pending`: nothing is
 * adopted until the admin answers. The record names the source site — the
 * address the copied database was registered under — for every message that
 * says whose connections these are.
 *
 * Durable (one option): registration is asynchronous and the copy lifecycle
 * may finish on a later page load or cron tick, after the request that
 * recorded the answer. A record is replaced only when a new copy begins.
 *
 * @since 8.0.0
 */
final class Site_Copy_Record {

	public const ANSWER_PENDING  = 'pending';
	public const ANSWER_ADOPT    = 'adopt';
	public const ANSWER_KEEP_OFF = 'keep_off';

	private const OPTION = 'automator_platform_site_copy_record';

	/**
	 * Begin the record of a new copy. Called once, when a copy lifecycle
	 * starts — never on a retry of the same copy.
	 *
	 * @param string $source_site_name The address the copied database was registered under.
	 * @param string $answer           The admin's answer, or pending when none was asked.
	 *
	 * @return void
	 */
	public function begin( string $source_site_name, string $answer = self::ANSWER_PENDING ): void {

		automator_update_option(
			self::OPTION,
			array(
				'source_site_name' => $source_site_name,
				'answer'           => $this->is_answer( $answer ) ? $answer : self::ANSWER_PENDING,
				'recorded_at'      => time(),
			)
		);
	}

	/**
	 * Record the admin's answer. A copy recognised before this record existed
	 * gets a record with no source.
	 *
	 * @param string $answer ANSWER_ADOPT or ANSWER_KEEP_OFF.
	 *
	 * @return bool False for anything but the two answers.
	 */
	public function choose( string $answer ): bool {

		if ( ! $this->is_answer( $answer ) ) {
			return false;
		}

		$record                = $this->read();
		$record['answer']      = $answer;
		$record['recorded_at'] = time();

		automator_update_option( self::OPTION, $record );

		return true;
	}

	/**
	 * @return bool Whether this site is a copy of another site.
	 */
	public function is_copy(): bool {

		return '' !== $this->answer();
	}

	/**
	 * @return string One of the ANSWER_* constants, '' when this site is not a copy.
	 */
	public function answer(): string {

		$answer = (string) ( $this->read()['answer'] ?? '' );

		return in_array( $answer, array( self::ANSWER_PENDING, self::ANSWER_ADOPT, self::ANSWER_KEEP_OFF ), true ) ? $answer : '';
	}

	/**
	 * @return bool Whether the copy waits for the admin's answer.
	 */
	public function is_pending(): bool {

		return self::ANSWER_PENDING === $this->answer();
	}

	/**
	 * @return bool Whether the admin chose to use the live site's connections.
	 */
	public function wants_adoption(): bool {

		return self::ANSWER_ADOPT === $this->answer();
	}

	/**
	 * @return string The source site's address, '' when not known.
	 */
	public function source_site_name(): string {

		return (string) ( $this->read()['source_site_name'] ?? '' );
	}

	/**
	 * Forget the record (a dev site's registration reset).
	 *
	 * @return void
	 */
	public function forget(): void {

		automator_delete_option( self::OPTION );
	}

	/**
	 * @param string $answer A candidate answer.
	 *
	 * @return bool
	 */
	private function is_answer( string $answer ): bool {

		return self::ANSWER_ADOPT === $answer || self::ANSWER_KEEP_OFF === $answer;
	}

	/**
	 * @return array<string, mixed>
	 */
	private function read(): array {

		$record = automator_get_option( self::OPTION, array() );

		return is_array( $record ) ? $record : array();
	}
}
