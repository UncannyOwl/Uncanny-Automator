<?php
/**
 * Facebook Lead Ads Page Connections
 *
 * @package Uncanny_Automator
 * @author  Uncanny Owl
 */

namespace Uncanny_Automator\Integrations\Facebook_Lead_Ads;

use Uncanny_Automator\App\Infrastructure\Connections\Connection;
use Uncanny_Automator\App\Infrastructure\Connections\Connection_Registry;
use Uncanny_Automator\App\Infrastructure\Connections\Platform_Connection;

/**
 * The site's platform connections for its Facebook pages.
 *
 * Each page is its own platform account (an Identity per page), so each
 * adopted page has its own v3 `uap_connections` row. None is the
 * integration's default: Lead Ads actions still go to /v2 with the local page
 * token. A row names its page in its metadata (`page_id`, `page_name`). A row
 * without them was created by a connection sync for a connection this site
 * had lost its row for; the next adoption of that page finds it by its
 * connection and names it.
 *
 * Whether a row is subscribed for signed lead events is kept on the row
 * (`webhook_subscription`), with the connection it was recorded for. The
 * platform keys the subscription on the connection, so a row given another
 * connection (a copy attaching to the live site's accounts, a sync) is not
 * subscribed until it registers again.
 *
 * @since 8.0.0
 *
 * @package Uncanny_Automator\Integrations\Facebook_Lead_Ads
 */
final class Facebook_Lead_Ads_Page_Connections {

	/**
	 * The platform's slug for Facebook Lead Ads.
	 *
	 * @var string
	 */
	const INTEGRATION = 'facebook_lead_ads';

	/**
	 * The metadata key a row's subscription is kept under.
	 *
	 * @var string
	 */
	const SUBSCRIPTION = 'webhook_subscription';

	/**
	 * How far a lead's `created_time` must be past the subscription before
	 * its legacy forward is dropped. Meta stamps `created_time`, this site
	 * stamps the subscription; a site whose clock is off by more than 90
	 * seconds receives no signed delivery at all (the inbox's signature
	 * window), so five minutes covers the clock of any site signed delivery
	 * reaches.
	 *
	 * @var int
	 */
	const LEAD_TIME_MARGIN = 5 * MINUTE_IN_SECONDS;

	/**
	 * Local uap_connections store.
	 *
	 * @var Connection_Registry
	 */
	private $registry;

	/**
	 * Constructor.
	 *
	 * @param Connection_Registry $registry Local uap_connections store.
	 */
	public function __construct( Connection_Registry $registry ) {
		$this->registry = $registry;
	}

	/**
	 * The active v3 rows that name their page, oldest first.
	 *
	 * @return Connection[]
	 */
	public function page_rows() {

		return array_values(
			array_filter(
				$this->registry->list_for_integration( self::INTEGRATION ),
				function ( Connection $row ) {
					return ! $row->is_legacy() && '' !== $this->page_id_of( $row );
				}
			)
		);
	}

	/**
	 * Every v3 Lead Ads row, whatever its status and whether or not it names
	 * its page.
	 *
	 * @return Connection[]
	 */
	public function all_rows() {

		return array_values(
			array_filter(
				$this->registry->list_for_integration( self::INTEGRATION, false ),
				static function ( Connection $row ) {
					return ! $row->is_legacy();
				}
			)
		);
	}

	/**
	 * The page's active connection.
	 *
	 * @param string $page_id The Facebook page id.
	 *
	 * @return Connection|null
	 */
	public function row_for_page( $page_id ) {

		foreach ( $this->page_rows() as $row ) {
			if ( (string) $page_id === $this->page_id_of( $row ) ) {
				return $row;
			}
		}

		return null;
	}

	/**
	 * @param Connection $row A Lead Ads row.
	 *
	 * @return string The page the row names, '' when it names none.
	 */
	public function page_id_of( Connection $row ) {

		$page_id = $row->metadata()['page_id'] ?? '';

		return is_scalar( $page_id ) ? (string) $page_id : '';
	}

	/**
	 * Whether the row's current connection is subscribed for signed lead events.
	 *
	 * @param Connection $row A Lead Ads row.
	 *
	 * @return bool
	 */
	public function is_subscribed( Connection $row ) {

		$subscription = $row->metadata()[ self::SUBSCRIPTION ] ?? null;

		return is_array( $subscription ) && $row->connection_id() === ( $subscription['connection_id'] ?? '' );
	}

	/**
	 * Record that the row's connection is subscribed, on the row as it is now.
	 *
	 * @param Connection $row The row the platform subscribed.
	 *
	 * @return bool False when the row is gone or holds another connection now,
	 *              or the write failed.
	 */
	public function record_subscription( Connection $row ) {

		$current = $this->registry->get_by_id( $row->id() );

		if ( null === $current || $current->connection_id() !== $row->connection_id() ) {
			return false;
		}

		$metadata                       = $current->metadata();
		$metadata[ self::SUBSCRIPTION ] = array(
			'connection_id' => $current->connection_id(),
			'subscribed_at' => time(),
		);

		return $this->registry->replace_metadata( $current->id(), $metadata );
	}

	/**
	 * Whether the lead reaches the site signed, so its legacy forward is a
	 * duplicate: the page's connection is subscribed, and the lead was
	 * created after the subscription. The platform decides a lead's delivery
	 * when Meta sends it, so a forward it queued before the page was
	 * subscribed has no signed delivery to follow it, however late it arrives.
	 *
	 * @param string $page_id      The Facebook page id.
	 * @param int    $created_time The lead's `created_time`, unix seconds; 0 when unknown.
	 *
	 * @return bool
	 */
	public function covers_lead( $page_id, $created_time ) {

		$row = $this->row_for_page( (string) $page_id );

		if ( null === $row || ! $this->is_subscribed( $row ) ) {
			return false;
		}

		$subscription = $row->metadata()[ self::SUBSCRIPTION ];

		if ( ! isset( $subscription['subscribed_at'] ) ) {
			return false;
		}

		return 0 < (int) $created_time && (int) $created_time >= (int) $subscription['subscribed_at'] + self::LEAD_TIME_MARGIN;
	}

	/**
	 * Keep the connection the platform adopted for a page.
	 *
	 * A row that already holds the connection, or the same account (the page's
	 * identity), is rewritten in place rather than duplicated: a row a sync
	 * created for a connection this site had lost its row for, or a row this
	 * site inherited as a copy and switched off. A row that keeps its
	 * connection keeps its metadata; a row given a new connection starts
	 * again from the page's facts, so nothing it said about the old
	 * connection (a quarantine, a subscription) carries over.
	 *
	 * @param string              $page_id    The Facebook page id.
	 * @param string              $page_name  The page's name.
	 * @param Platform_Connection $connection The adopted connection.
	 *
	 * @return Connection|null The page's row, null when it could not be written.
	 */
	public function store_adopted( $page_id, $page_name, Platform_Connection $connection ) {

		$facts = array(
			'page_id'   => (string) $page_id,
			'page_name' => (string) $page_name,
		);
		if ( '' !== $connection->external_user_label() ) {
			$facts['external_user_label'] = $connection->external_user_label();
		}

		$existing = $this->existing_row_for( $connection );

		if ( null === $existing ) {
			return $this->store_new( $page_name, $connection, $facts );
		}

		$metadata = $existing->connection_id() === $connection->connection_id()
			? array_merge( $existing->metadata(), $facts )
			: $facts;

		$identity_id = '' !== $connection->identity_id() ? $connection->identity_id() : (string) $existing->identity_id();

		if ( ! $this->registry->bind( $existing->id(), $connection->connection_id(), $connection->connection_signature(), $identity_id, Connection::STATUS_ACTIVE, $metadata ) ) {
			return null;
		}

		return $this->registry->get_by_id( $existing->id() );
	}

	/**
	 * Remove the row.
	 *
	 * @param Connection $row A Lead Ads row.
	 *
	 * @return void
	 */
	public function remove( Connection $row ) {
		$this->registry->delete( $row->id() );
	}

	/**
	 * The v3 row that already holds the connection, or its account.
	 *
	 * @param Platform_Connection $connection The adopted connection.
	 *
	 * @return Connection|null
	 */
	private function existing_row_for( Platform_Connection $connection ) {

		$row = $this->registry->get_by_connection_id( $connection->connection_id() );

		if ( null === $row && '' !== $connection->identity_id() ) {
			$row = $this->registry->get_by_identity( self::INTEGRATION, $connection->identity_id() );
		}

		return null !== $row && ! $row->is_legacy() && self::INTEGRATION === $row->integration() ? $row : null;
	}

	/**
	 * Store a new row for the page. It is never the integration's default.
	 *
	 * @param string               $page_name  The page's name.
	 * @param Platform_Connection  $connection The adopted connection.
	 * @param array<string, mixed> $metadata   The page's facts.
	 *
	 * @return Connection|null The row; when another run stored it first, that row.
	 */
	private function store_new( $page_name, Platform_Connection $connection, array $metadata ) {

		$id = $this->registry->store(
			array(
				'connection_id'        => $connection->connection_id(),
				'connection_signature' => $connection->connection_signature(),
				'identity_id'          => $connection->identity_id(),
				'integration'          => self::INTEGRATION,
				'platform_version'     => Connection::PLATFORM_VERSION_V3,
				'label'                => '' !== (string) $page_name ? (string) $page_name : null,
				'metadata'             => $metadata,
				'status'               => Connection::STATUS_ACTIVE,
				'is_default'           => false,
			)
		);

		return 0 !== $id
			? $this->registry->get_by_id( $id )
			: $this->registry->get_by_connection_id( $connection->connection_id() );
	}
}
