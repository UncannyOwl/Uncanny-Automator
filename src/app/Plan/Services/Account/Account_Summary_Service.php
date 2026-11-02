<?php
/**
 * Account Summary Service
 *
 * @package Uncanny_Automator\App\Plan\Services\Account
 */

declare(strict_types=1);

namespace Uncanny_Automator\App\Plan\Services\Account;

use Uncanny_Automator\Admin_Menu;
use Uncanny_Automator\App\Application\Mcp\Hosted_App;
use Uncanny_Automator\App\Infrastructure\License\License_Manager;
use Uncanny_Automator\App\Plan\Addons\Services\License_Summary_Service;
use Uncanny_Automator\App\Plan\Addons\Value_Objects\Summary_Scenario;
use Uncanny_Automator\App\Plan\Domain\Plan_Levels;
use WP_Error;

/**
 * The site's Automator account for the Dashboard: whether it is linked, who
 * owns it, the license scenario and plan, and the account URLs.
 *
 * Every read refreshes the license, so activations, renewals and expirations
 * show on the next visit. While the platform cannot answer, the last good
 * license stands in; with none cached, the reply is a retryable error rather
 * than an unlinked site, because nothing here reports a state it does not know.
 */
final class Account_Summary_Service {

	private Fresh_License_Reader $reader;

	private License_Summary_Service $summary;

	private Account_Connect_Url $connect;

	/**
	 * Constructor.
	 *
	 * @param License_Manager|null         $license License reader. Pass the one `$summary` reads.
	 * @param License_Summary_Service|null $summary Scenario resolver.
	 */
	public function __construct( ?License_Manager $license = null, ?License_Summary_Service $summary = null ) {
		$this->reader  = new Fresh_License_Reader( $license );
		$this->summary = $summary ?? License_Summary_Service::instance();
		$this->connect = new Account_Connect_Url();
	}

	/**
	 * The account summary, or a WP_Error carrying its HTTP status.
	 *
	 * @return array{linked:bool,license_scenario:string,owner:array{name:string,avatar_url:string}|null,plan:string,links:array<string,string>}|WP_Error
	 */
	public function get_summary() {
		$license = $this->reader->read();

		if ( $license instanceof WP_Error ) {
			return $license;
		}

		$scenario = $this->summary->get_scenario();
		$linked   = $this->is_linked( $scenario, $license );
		$owner    = $linked ? $this->owner( (array) $license ) : null;

		if ( $linked && null === $owner ) {
			return new WP_Error( 'account_owner_unknown', 'Your Automator account does not name its owner. Contact Automator support.', array( 'status' => 502 ) );
		}

		return array(
			'linked'           => $linked,
			'license_scenario' => $scenario->id(),
			'owner'            => $owner,
			'plan'             => $this->plan( $scenario ),
			'links'            => array(
				'link_account'       => $this->connect->to_dashboard(),
				'activate_license'   => Admin_Menu::get_license_page_url(),
				'my_account'         => AUTOMATOR_STORE_URL . 'my-account/',
				'manage_sites'       => $this->manage_sites_url( $linked ? (array) $license : array() ),
				'disconnect_account' => $this->disconnect_url(),
				'connect_ai'         => Hosted_App::connect_url(),
			),
		);
	}

	/**
	 * An active Pro license or a valid free account links the site. Installing
	 * Pro without activating its license does not disconnect the free account;
	 * the license scenario and plan still report Pro activation separately.
	 *
	 * @param array<string,mixed>|null $license License payload.
	 */
	private function is_linked( Summary_Scenario $scenario, ?array $license ): bool {
		if ( $scenario->is_active_license() ) {
			return true;
		}

		return 'free' === $this->reader->license_manager()->get_type() && 'valid' === ( $license['license'] ?? '' );
	}

	/**
	 * The owner named by the license: the customer name, else their email.
	 * The avatar is the license's when it is a safe URL, else the Gravatar
	 * for the owner's email — never the viewer's.
	 *
	 * @param array<string,mixed> $license License payload.
	 *
	 * @return array{name:string,avatar_url:string}|null Null when the license names no one.
	 */
	private function owner( array $license ): ?array {
		$email = is_string( $license['customer_email'] ?? null ) ? trim( $license['customer_email'] ) : '';
		$name  = is_string( $license['customer_name'] ?? null ) ? trim( $license['customer_name'] ) : '';
		$name  = '' !== $name ? $name : $email;

		if ( '' === $name ) {
			return null;
		}

		$avatar = is_string( $license['user_avatar'] ?? null ) ? esc_url_raw( $license['user_avatar'], array( 'https', 'http' ) ) : '';

		return array(
			'name'       => $name,
			'avatar_url' => '' !== $avatar ? $avatar : (string) get_avatar_url( $email ),
		);
	}

	/**
	 * The plan: the active license's tier, else Lite.
	 */
	private function plan( Summary_Scenario $scenario ): string {
		switch ( $scenario->id() ) {
			case Summary_Scenario::ACTIVE_BASIC:
				return Plan_Levels::PRO_BASIC;
			case Summary_Scenario::ACTIVE_PLUS:
				return Plan_Levels::PRO_PLUS;
			case Summary_Scenario::ACTIVE_ELITE:
				return Plan_Levels::PRO_ELITE;
			default:
				return Plan_Levels::LITE;
		}
	}

	/**
	 * The Dashboard address that disconnects the free account. Admin_Menu's
	 * admin_init handler checks the redirect nonce, deactivates the free
	 * license and returns to the Dashboard.
	 */
	private function disconnect_url(): string {
		return add_query_arg(
			array(
				'post_type' => AUTOMATOR_POST_TYPE_RECIPE,
				'page'      => 'uncanny-automator-dashboard',
				'action'    => 'discount_automator_connect',
				'state'     => wp_create_nonce( 'automator_setup_wizard_redirect_nonce' ),
			),
			admin_url( 'edit.php' )
		);
	}

	/**
	 * The store page listing the account's sites: the linked license's own
	 * page, else the purchase history the owner signs in to.
	 *
	 * @param array<string,mixed> $license License payload, empty when not linked.
	 */
	private function manage_sites_url( array $license ): string {
		$url = AUTOMATOR_STORE_URL . 'checkout/purchase-history/';

		if ( empty( $license['license_id'] ) || empty( $license['payment_id'] ) ) {
			return $url;
		}

		return add_query_arg(
			array(
				'license_id' => rawurlencode( (string) $license['license_id'] ),
				'action'     => 'manage_licenses',
				'payment_id' => rawurlencode( (string) $license['payment_id'] ),
			),
			$url
		);
	}
}
