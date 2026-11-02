<?php
/**
 * Dropbox API Caller
 *
 * @package Uncanny_Automator
 * @author  Uncanny Owl
 */

namespace Uncanny_Automator\Integrations\Dropbox;

use Exception;
use Uncanny_Automator\App\Infrastructure\Connections\Pending_Operation_Key;
use Uncanny_Automator\App\Recipe_Runner\Exceptions\Action_Error_Exception;
use Uncanny_Automator\App_Integrations\Api_Caller;

/**
 * Class Dropbox_Api_Caller
 *
 * What the site owner is told when the platform refuses a Dropbox call is not
 * decided here: the shared /v3 caller tells it from the answer's error.type
 * (Platform_Failure_Message), and Dropbox's own refusals arrive as the
 * platform describes them. This class keeps only what is Dropbox's.
 *
 * @property Dropbox_App_Helpers $helpers
 *
 * @package Uncanny_Automator\Integrations\Dropbox
 */
class Dropbox_Api_Caller extends Api_Caller {

	/**
	 * What every Dropbox registration's subject starts with; the connection
	 * id follows ({@see self::registration_subject()}).
	 */
	private const REGISTRATION_SUBJECT_PREFIX = 'dropbox:';

	/**
	 * The Idempotency-Keys of the registrations under way.
	 *
	 * @var Pending_Operation_Key|null
	 */
	private $operation_keys;

	////////////////////////////////////////////////////////////
	// Integration specific methods
	////////////////////////////////////////////////////////////

	/**
	 * The account's top-level folders, cached until a refresh or a
	 * disconnect ({@see self::clear_picker_cache()}).
	 *
	 * @param bool $refresh Force refresh.
	 *
	 * @return array
	 */
	public function get_folders( $refresh = false ) {
		$key    = 'AUTOMATOR_DROPBOX_FOLDERS';
		$cached = automator_get_option( $key, array() );

		if ( ! empty( $cached ) && ! $refresh ) {
			return $cached;
		}

		$response = $this->api_request(
			array(
				'action' => 'list_folder',
				'path'   => '',
			)
		);

		$folders = array();

		if ( ! empty( $response['data']['entries'] ) ) {
			foreach ( $response['data']['entries'] as $entry ) {
				if ( 'folder' === ( $entry['.tag'] ?? '' ) ) {
					$folders[] = array(
						'text'  => $entry['path_display'] ?? $entry['name'],
						'value' => $entry['path_lower'] ?? $entry['path_display'],
					);
				}
			}
		}

		if ( ! empty( $folders ) ) {
			automator_update_option( $key, $folders, false );
		}

		return $folders;
	}

	/**
	 * Up to 200 of the account's top-level files, cached until a refresh or a
	 * disconnect ({@see self::clear_picker_cache()}).
	 *
	 * @param bool $refresh Force refresh.
	 *
	 * @return array
	 */
	public function get_files( $refresh = false ) {
		$key    = 'AUTOMATOR_DROPBOX_FILES';
		$cached = automator_get_option( $key, array() );

		if ( ! empty( $cached ) && ! $refresh ) {
			return $cached;
		}

		$response = $this->api_request(
			array(
				'action' => 'list_folder',
			)
		);

		$files = array();
		$count = 0;

		if ( ! empty( $response['data']['entries'] ) ) {
			foreach ( $response['data']['entries'] as $entry ) {
				if ( 'file' === ( $entry['.tag'] ?? '' ) ) {
					$files[] = array(
						'text'  => $entry['path_display'] ?? $entry['name'],
						'value' => $entry['path_lower'] ?? $entry['path_display'],
					);
					++$count;
					// Limit to 200 files
					if ( 200 === $count ) {
						break;
					}
				}
			}
		}

		if ( ! empty( $files ) ) {
			automator_update_option( $key, $files, false );
		}

		return $files;
	}

	/**
	 * Forget the cached folder and file lists. They belong to the account
	 * that was connected when they were fetched.
	 *
	 * @return void
	 */
	public function clear_picker_cache() {
		automator_delete_option( 'AUTOMATOR_DROPBOX_FOLDERS' );
		automator_delete_option( 'AUTOMATOR_DROPBOX_FILES' );
	}

	/**
	 * Upload a file from a signed source URL: the upload action's one write.
	 *
	 * Sends the `{body, headers}` bundle from prepare_upload_request() unchanged; its headers go through
	 * `$args['headers']`, not the `idempotency_key` argument, because the upload's `Idempotency-Key` is derived from
	 * the upload itself rather than from the run and action ids. With `$action_data` the shared caller adds the
	 * background headers beside it when the owner action may defer, and a 202 job acceptance comes back untouched
	 * so the action can defer on it. Any other answer is unwrapped to `data`, as every other method here does.
	 *
	 * @param array      $body        source_url, source_size_bytes, source_mime_type, destination_path, on_conflict, create_shared_link.
	 * @param array      $headers     Idempotency-Key and X-Automator-Source-Token.
	 * @param array|null $action_data The action data of the write that may go to the background.
	 *
	 * @return array The uploaded file's id, path, size, mime_type and shared_url; the 202 envelope (`statusCode`,
	 *               `job_id`) of an accepted job; empty when the response carries no `data`.
	 *
	 * @throws Action_Error_Exception On platform error.
	 */
	public function upload_from_url( $body, $headers, $action_data = null ) {
		$response = $this->api_request(
			array_merge( array( 'action' => 'upload_from_url' ), $body ),
			$action_data,
			array( 'headers' => $headers )
		);

		if ( $this->is_accepted_job( $response ) ) {
			return $response;
		}

		return is_array( $response ) && isset( $response['data'] ) && is_array( $response['data'] )
			? $response['data']
			: array();
	}

	/**
	 * Whether the platform accepted the write as a background job (202 with a job id) instead of answering it.
	 *
	 * @param mixed $response What api_request() returned.
	 *
	 * @return bool
	 */
	private function is_accepted_job( $response ) {
		return is_array( $response ) && 202 === (int) ( $response['statusCode'] ?? 0 ) && '' !== (string) ( $response['job_id'] ?? '' );
	}

	/**
	 * Create (or return the existing) shared link for a file.
	 *
	 * @param string     $path        The file path.
	 * @param array|null $action_data The action data of the write that may go to the background.
	 *
	 * @return array The link data, or the 202 envelope of an accepted job.
	 * @throws Action_Error_Exception If creation fails.
	 */
	public function create_shared_link( $path, $action_data = null ) {
		$response = $this->api_request(
			array(
				'action' => 'create_shared_link',
				'path'   => $path,
			),
			$action_data
		);
		if ( $this->is_accepted_job( $response ) ) {
			return $response;
		}

		return is_array( $response['data'] ?? null ) ? $response['data'] : array();
	}

	/**
	 * Create a folder.
	 *
	 * @param string $path The folder path.
	 * @param array|null $action_data The action data of the write that may go to the background.
	 *
	 * @return array
	 * @throws Action_Error_Exception If creation fails.
	 */
	public function create_folder( $path, $action_data = null ) {
		$response = $this->api_request(
			array(
				'action' => 'create_folder',
				'path'   => $path,
			),
			$action_data
		);

		if ( $this->is_accepted_job( $response ) ) {
			return $response;
		}

		if ( ! in_array( $response['statusCode'], array( 200, 201 ), true ) ) {
			throw Action_Error_Exception::from_http_status(
				esc_html( $response['error']['description'] ?? esc_html_x( 'Failed to create folder.', 'Dropbox', 'uncanny-automator' ) ),
				(int) $response['statusCode']
			);
		}

		return $response['data'];
	}

	/**
	 * Delete a file or folder.
	 *
	 * @param string $path The item path.
	 * @param array|null $action_data The action data of the write that may go to the background.
	 *
	 * @return array
	 * @throws Action_Error_Exception If deletion fails.
	 */
	public function delete_item( $path, $action_data = null ) {
		$response = $this->api_request(
			array(
				'action' => 'delete_item',
				'path'   => $path,
			),
			$action_data
		);

		if ( $this->is_accepted_job( $response ) ) {
			return $response;
		}

		if ( ! in_array( $response['statusCode'], array( 200, 201 ), true ) ) {
			throw Action_Error_Exception::from_http_status(
				esc_html( $response['error']['description'] ?? esc_html_x( 'Failed to delete item.', 'Dropbox', 'uncanny-automator' ) ),
				(int) $response['statusCode']
			);
		}

		return $response['data'];
	}

	/**
	 * Move a file or folder.
	 *
	 * @param string $from_path The source path.
	 * @param string $to_path   The destination path.
	 * @param array|null $action_data The action data of the write that may go to the background.
	 *
	 * @return array
	 * @throws Action_Error_Exception If move fails.
	 */
	public function move_file( $from_path, $to_path, $action_data = null ) {
		$response = $this->api_request(
			array(
				'action'    => 'move_file',
				'from_path' => $from_path,
				'to_path'   => $to_path,
			),
			$action_data
		);

		if ( $this->is_accepted_job( $response ) ) {
			return $response;
		}

		if ( ! in_array( $response['statusCode'], array( 200, 201 ), true ) ) {
			throw Action_Error_Exception::from_http_status(
				esc_html( $response['error']['description'] ?? esc_html_x( 'Failed to move file.', 'Dropbox', 'uncanny-automator' ) ),
				(int) $response['statusCode']
			);
		}

		return $response['data'];
	}

	/**
	 * Copy a file or folder.
	 *
	 * @param string $from_path The source path.
	 * @param string $to_path   The destination path.
	 * @param array|null $action_data The action data of the write that may go to the background.
	 *
	 * @return array
	 * @throws Action_Error_Exception If copy fails.
	 */
	public function copy_file( $from_path, $to_path, $action_data = null ) {
		$response = $this->api_request(
			array(
				'action'    => 'copy_file',
				'from_path' => $from_path,
				'to_path'   => $to_path,
			),
			$action_data
		);

		if ( $this->is_accepted_job( $response ) ) {
			return $response;
		}

		if ( ! in_array( $response['statusCode'], array( 200, 201 ), true ) ) {
			throw Action_Error_Exception::from_http_status(
				esc_html( $response['error']['description'] ?? esc_html_x( 'Failed to copy file.', 'Dropbox', 'uncanny-automator' ) ),
				(int) $response['statusCode']
			);
		}

		return $response['data'];
	}

	/**
	 * Share a folder.
	 *
	 * @param string      $path               The folder path.
	 * @param string|null $member_policy      Who can be a member.
	 * @param string|null $acl_update_policy  Who can change permissions.
	 * @param string|null $shared_link_policy Shared link policy.
	 * @param string|null $viewer_info_policy Viewer info policy.
	 * @param string|null $access_inheritance Access inheritance.
	 * @param array|null $action_data The action data of the write that may go to the background.
	 *
	 * @return array
	 * @throws Action_Error_Exception If sharing fails.
	 */
	public function share_folder( $path, $member_policy = null, $acl_update_policy = null, $shared_link_policy = null, $viewer_info_policy = null, $access_inheritance = null, $action_data = null ) {
		$body = array(
			'action' => 'share_folder',
			'path'   => $path,
		);

		if ( null !== $member_policy ) {
			$body['member_policy'] = $member_policy;
		}
		if ( null !== $acl_update_policy ) {
			$body['acl_update_policy'] = $acl_update_policy;
		}
		if ( null !== $shared_link_policy ) {
			$body['shared_link_policy'] = $shared_link_policy;
		}
		if ( null !== $viewer_info_policy ) {
			$body['viewer_info_policy'] = $viewer_info_policy;
		}
		if ( null !== $access_inheritance ) {
			$body['access_inheritance'] = $access_inheritance;
		}

		$response = $this->api_request( $body, $action_data );
		if ( $this->is_accepted_job( $response ) ) {
			return $response;
		}

		if ( ! in_array( $response['statusCode'], array( 200, 201 ), true ) ) {
			throw Action_Error_Exception::from_http_status(
				esc_html( $response['error']['description'] ?? esc_html_x( 'Failed to share folder.', 'Dropbox', 'uncanny-automator' ) ),
				(int) $response['statusCode']
			);
		}

		return $response['data'];
	}

	/**
	 * Add a member to a shared folder.
	 * If the folder is not shared, it will be shared first.
	 *
	 * @param string $folder_path  The folder path.
	 * @param string $member_email The member's email address.
	 * @param string $access_level The access level (viewer or editor).
	 * @param array|null $action_data The action data of the write that may go to the background.
	 *
	 * @return array
	 * @throws Action_Error_Exception If adding member fails.
	 */
	public function add_folder_member( $folder_path, $member_email, $access_level = 'viewer', $action_data = null ) {
		$response = $this->api_request(
			array(
				'action'       => 'add_folder_member',
				'folder_path'  => $folder_path,
				'member_email' => $member_email,
				'access_level' => $access_level,
			),
			$action_data
		);

		if ( $this->is_accepted_job( $response ) ) {
			return $response;
		}

		if ( ! in_array( $response['statusCode'], array( 200, 201 ), true ) ) {
			throw Action_Error_Exception::from_http_status(
				esc_html( $response['error']['description'] ?? esc_html_x( 'Failed to add member to folder.', 'Dropbox', 'uncanny-automator' ) ),
				(int) $response['statusCode']
			);
		}

		return $response['data'];
	}

	/**
	 * Add a member to a shared file.
	 *
	 * @param string $file_path    The file path.
	 * @param string $member_email The member's email address.
	 * @param string $access_level The access level (viewer or editor).
	 * @param array|null $action_data The action data of the write that may go to the background.
	 *
	 * @return array
	 * @throws Action_Error_Exception If adding member fails.
	 */
	public function add_file_member( $file_path, $member_email, $access_level = 'viewer', $action_data = null ) {
		$response = $this->api_request(
			array(
				'action'       => 'add_file_member',
				'file_path'    => $file_path,
				'member_email' => $member_email,
				'access_level' => $access_level,
			),
			$action_data
		);

		if ( $this->is_accepted_job( $response ) ) {
			return $response;
		}

		if ( ! in_array( $response['statusCode'], array( 200, 201 ), true ) ) {
			throw Action_Error_Exception::from_http_status(
				esc_html( $response['error']['description'] ?? esc_html_x( 'Failed to add member to file.', 'Dropbox', 'uncanny-automator' ) ),
				(int) $response['statusCode']
			);
		}

		return $response['data'];
	}

	/**
	 * Register the site to receive Dropbox change events.
	 *
	 * Events arrive at the plugin's shared platform receiver, not at a
	 * Dropbox-specific URL; the platform sends events for every integration
	 * there.
	 *
	 * Registering costs a credit (the platform prices it at 1), so it carries
	 * an Idempotency-Key: a retry after a timeout gets the answer of the
	 * registration that ran instead of registering, and charging, again. The
	 * key belongs to this registration alone ({@see Pending_Operation_Key}):
	 * reused while its outcome is unknown, forgotten once the platform
	 * answered for good. A key derived from the connection and URL answered a
	 * registration after an unregister with the first one's "subscribed",
	 * leaving the platform with no webhook URL.
	 *
	 * The fingerprint covers what the platform hashes that can change while a
	 * registration is pending: the URL, the connection's signature and the
	 * plugin version the transport adds to every body.
	 *
	 * @return array Response data — `{status, webhook_revision}`.
	 * @throws Action_Error_Exception If registration fails.
	 */
	public function register_site_for_webhooks() {

		$creds       = $this->helpers->get_credentials();
		$webhook_url = rest_url( AUTOMATOR_PLATFORM_WEBHOOK_RECEIVER_ROUTE );
		$subject     = self::registration_subject( (string) $creds['connection_id'] );
		$fingerprint = hash( 'sha256', (string) wp_json_encode( array( $webhook_url, $creds['connection_signature'] ?? '', AUTOMATOR_PLUGIN_VERSION ) ) );

		try {
			$response = $this->api_request(
				array(
					'action'      => 'register_site_for_webhooks',
					'webhook_url' => $webhook_url,
				),
				null,
				array( 'idempotency_key' => $this->operation_keys()->key_for( 'register_webhooks', $subject, $fingerprint ) )
			);
		} catch ( Exception $e ) {
			$this->operation_keys()->conclude( 'register_webhooks', $subject, (int) $e->getCode() );
			throw $e;
		}

		$this->operation_keys()->conclude( 'register_webhooks', $subject, (int) $response['statusCode'] );

		if ( ! in_array( $response['statusCode'], array( 200, 201 ), true ) ) {
			throw Action_Error_Exception::from_http_status(
				esc_html( $response['error']['description'] ?? esc_html_x( 'Failed to register site for webhooks.', 'Dropbox', 'uncanny-automator' ) ),
				(int) $response['statusCode']
			);
		}

		return $response['data'];
	}

	/**
	 * End every Dropbox registration under way, whatever its outcome, so the
	 * next "on" mints a new key.
	 *
	 * Turning triggers off and disconnecting both end it. A key kept for a
	 * registration whose answer never arrived would otherwise be reused by the
	 * next "on" for 24 hours, with the same body when the connection id comes
	 * back (a reconnect inside the platform's grace window restores it), and
	 * the platform would answer that "on" with the first one's recorded
	 * "subscribed" while it holds no subscription (L200).
	 *
	 * The "Enable triggers" switch is the integration's, so every Dropbox
	 * registration ends ({@see Pending_Operation_Key::settle_every()}), not the
	 * one of whichever connection can be resolved now. Resolving it would
	 * depend on the site's connections being usable: while a copy's
	 * connections are re-checked the default lookup answers with no row, and
	 * an "off" in that window would end nothing (#8836 review). It also ends
	 * a registration for a connection id the site no longer holds (connection
	 * sync replaced it) and, when a site holds more than one Dropbox account,
	 * every account's — ending one too many only makes that account's next
	 * "on" a new request.
	 *
	 * @since 8.0.0
	 *
	 * @return void
	 */
	public function forget_pending_registration() {

		$this->operation_keys()->settle_every( 'register_webhooks', self::REGISTRATION_SUBJECT_PREFIX );
	}

	/**
	 * What a registration is done to: the connection it subscribes.
	 *
	 * @param string $connection_id The platform connection id.
	 *
	 * @return string
	 */
	private static function registration_subject( string $connection_id ): string {

		return self::REGISTRATION_SUBJECT_PREFIX . $connection_id;
	}

	/**
	 * @return Pending_Operation_Key
	 */
	private function operation_keys() {

		if ( null === $this->operation_keys ) {
			$this->operation_keys = new Pending_Operation_Key();
		}

		return $this->operation_keys;
	}

	/**
	 * Stop sending Dropbox change events to the site. Idempotent.
	 *
	 * Sent without an Idempotency-Key: unregistering is free and only clears
	 * the subscription, so a repeat changes nothing. A key would let the
	 * platform replay an earlier "unsubscribed" for 24 hours after a
	 * registration in between, and the platform would keep delivering.
	 *
	 * Turning triggers off also ends a registration whose outcome is still
	 * unknown ({@see self::forget_pending_registration()}): the next "on" is a
	 * new operation, never a retry answered with that registration's
	 * recorded "subscribed" while the platform holds no subscription.
	 *
	 * @return array Response data.
	 * @throws Action_Error_Exception If unregistration fails.
	 */
	public function unregister_site_for_webhooks() {

		$this->forget_pending_registration();

		$response = $this->api_request( array( 'action' => 'unregister_site_for_webhooks' ) );

		if ( ! in_array( $response['statusCode'], array( 200, 201 ), true ) ) {
			throw Action_Error_Exception::from_http_status(
				esc_html( $response['error']['description'] ?? esc_html_x( 'Failed to unregister site from webhooks.', 'Dropbox', 'uncanny-automator' ) ),
				(int) $response['statusCode']
			);
		}

		return $response['data'];
	}
}
