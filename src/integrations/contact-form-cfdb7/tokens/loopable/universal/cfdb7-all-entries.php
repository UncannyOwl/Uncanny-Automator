<?php

namespace Uncanny_Automator\Integrations\Cfdb7\Tokens\Loopable\Universal;

use Uncanny_Automator\Integrations\Cfdb7\Cfdb7_Helpers;
use Uncanny_Automator\Services\Loopable\Loopable_Token_Collection;
use Uncanny_Automator\Services\Loopable\Universal_Loopable_Token;

/**
 * Cfdb7_All_Entries
 *
 * Every CFDB7 entry on the current site, oldest first.
 *
 * CFDB7 exposes no function for reading entries — its own screens query the
 * table directly — so the rows are read through the `cfdb7_database` filter,
 * which is what lets a site keep its entries in another database.
 *
 * A row needs only the entry's status from its stored submission, so one query
 * reads every entry with the start of its stored value, where CFDB7 keeps the
 * status. Only the entries whose start does not prove their status have their
 * whole value read, a batch at a time.
 *
 * The id holds letters and underscores only: the loop matches a row token's id
 * with `[A-Z_]+`, so an id carrying a digit would show no row tokens at all.
 *
 * @package Uncanny_Automator\Integrations\Cfdb7
 */
class Cfdb7_All_Entries extends Universal_Loopable_Token {

	/**
	 * Characters of each stored value the main query reads.
	 *
	 * Must hold the serialized array's opening, the `cfdb7_status` key and the
	 * statuses CFDB7 writes: `a:<count>:{s:12:"cfdb7_status";s:6:"unread";` is
	 * about 40 bytes.
	 *
	 * @var int
	 */
	const STATUS_PREFIX_LENGTH = 64;

	/**
	 * Entries whose whole stored value one query reads.
	 *
	 * Read through `static::` so a test can lower it.
	 *
	 * @var int
	 */
	const STATUS_BATCH_SIZE = 100;

	/**
	 * Register loopable token.
	 *
	 * @return void
	 */
	public function register_loopable_token() {

		$this->set_id( 'ALL_ENTRIES' );
		$this->set_name( esc_html_x( 'All entries', 'Contact Form CFDB7', 'uncanny-automator' ) );
		$this->set_log_identifier( 'Entry ID: {{ENTRY_ID}} - {{FORM_TITLE}}' );
		$this->set_child_tokens( $this->get_row_tokens() );

		// An entry records no user: the table has no user column.
		$this->set_requires_user( false );
	}

	/**
	 * The row tokens, matching the entry tokens of the "An entry is saved for
	 * a form" trigger.
	 *
	 * @return array
	 */
	private function get_row_tokens() {

		return array(
			'ENTRY_ID'     => array(
				'name'       => esc_html_x( 'Entry ID', 'Contact Form CFDB7', 'uncanny-automator' ),
				'token_type' => 'integer',
			),
			'FORM_ID'      => array(
				'name'       => esc_html_x( 'Form ID', 'Contact Form CFDB7', 'uncanny-automator' ),
				'token_type' => 'integer',
			),
			'FORM_TITLE'   => array(
				'name'       => esc_html_x( 'Form title', 'Contact Form CFDB7', 'uncanny-automator' ),
				'token_type' => 'text',
			),
			'ENTRY_DATE'   => array(
				'name'       => esc_html_x( 'Submission date', 'Contact Form CFDB7', 'uncanny-automator' ),
				'token_type' => 'text',
			),
			'ENTRY_STATUS' => array(
				'name'       => esc_html_x( 'Entry status', 'Contact Form CFDB7', 'uncanny-automator' ),
				'token_type' => 'text',
			),
		);
	}

	/**
	 * Hydrate the loopable token.
	 *
	 * @param mixed $args The loop args.
	 *
	 * @return Loopable_Token_Collection
	 */
	public function hydrate_token_loopable( $args ) {

		$loopable = new Loopable_Token_Collection();
		$helpers  = new Cfdb7_Helpers();
		$db       = $helpers->get_db();
		$table    = $helpers->get_table_name();
		$length   = self::STATUS_PREFIX_LENGTH;

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Contact Form CFDB7's own table; the plugin offers no API for this query. The table name is built from the database prefix and the prefix length is an integer constant.
		$entries = (array) $db->get_results( "SELECT form_id, form_post_id, form_date, LEFT( form_value, {$length} ) AS status_prefix FROM {$table} ORDER BY form_id ASC" );

		$statuses = array();
		$unproven = array();

		foreach ( $entries as $entry ) {

			$status = $helpers->get_status_from_prefix( $entry->status_prefix );

			if ( null === $status ) {
				$unproven[] = absint( $entry->form_id );
				continue;
			}

			$statuses[ absint( $entry->form_id ) ] = $helpers->get_status_label( $status );
		}

		$statuses += $this->read_statuses( $helpers, $unproven );

		foreach ( $entries as $entry ) {

			$entry_id = absint( $entry->form_id );
			$form_id  = absint( $entry->form_post_id );

			$loopable->create_item(
				array(
					'ENTRY_ID'     => $entry_id,
					'FORM_ID'      => $form_id,
					'FORM_TITLE'   => 0 === $form_id ? '' : (string) get_the_title( $form_id ),
					'ENTRY_DATE'   => (string) $entry->form_date,
					'ENTRY_STATUS' => $statuses[ $entry_id ] ?? '',
				)
			);
		}

		return $loopable;
	}

	/**
	 * Status labels read from the whole stored values of the given entries.
	 *
	 * The values are read a batch at a time, so no more than one batch of
	 * submissions is in memory at once.
	 *
	 * @param Cfdb7_Helpers $helpers   The integration's helpers.
	 * @param int[]         $entry_ids Entry IDs (`form_id`).
	 *
	 * @return array<int,string> Status label keyed by entry ID; `''` when the
	 *                           value is not a serialized array or holds no status.
	 */
	private function read_statuses( Cfdb7_Helpers $helpers, array $entry_ids ) {

		$db       = $helpers->get_db();
		$table    = $helpers->get_table_name();
		$statuses = array();

		foreach ( array_chunk( $entry_ids, static::STATUS_BATCH_SIZE ) as $batch ) {

			$placeholders = implode( ', ', array_fill( 0, count( $batch ), '%d' ) );

			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared, WordPress.DB.DirectDatabaseQuery -- Contact Form CFDB7's own table; the plugin offers no API for this query. The table name is built from the database prefix and the placeholders are one `%d` per id.
			$rows = $db->get_results( $db->prepare( "SELECT form_id, form_value FROM {$table} WHERE form_id IN ( {$placeholders} )", $batch ) );

			foreach ( (array) $rows as $row ) {

				$values = $helpers->get_entry_values( $row );

				$statuses[ absint( $row->form_id ) ] = is_array( $values ) ? $helpers->get_status_label( $values['cfdb7_status'] ?? '' ) : '';
			}
		}

		return $statuses;
	}
}
