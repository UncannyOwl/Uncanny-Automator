<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Transports\Restful\Addons;

use Uncanny_Automator\App\Plan\Addons\Value_Objects\Addon_Action_Result;

/**
 * Shapes the plugin-manager body the AddOns page strict-parses.
 *
 * No envelope — the frontend posts with `useEnvelope: false` and parses the
 * whole body — and every optional key is built conditionally: an empty
 * `direct_download` or a `data` object without a card would both fail parse.
 * `data` carries exactly one key, `addon`.
 */
final class Plugin_Manager_Response {

	/**
	 * Build the response body.
	 *
	 * @param Addon_Action_Result $result The action outcome.
	 * @param array|null          $card   The refreshed addon card, when the
	 *                                    addon exists.
	 *
	 * @return array<string,mixed>
	 */
	public static function body( Addon_Action_Result $result, ?array $card ): array {

		$body = array( 'success' => $result->succeeded() );

		if ( '' !== $result->direct_download() ) {
			$body['direct_download'] = $result->direct_download();
		}

		if ( ! $result->succeeded() ) {
			$body['user_intervention'] = array(
				'message' => $result->intervention_message(),
				'cta'     => self::intervention_cta( $result ),
			);
		}

		if ( null !== $card ) {
			$body['data'] = array( 'addon' => $card );
		}

		return $body;
	}

	/**
	 * The CTA that resolves the failure.
	 *
	 * The frontend requires a CTA on every intervention, so every intent
	 * maps and the default is the safe one: reload and try again.
	 *
	 * @param Addon_Action_Result $result The action outcome.
	 *
	 * @return array<string,string>
	 */
	private static function intervention_cta( Addon_Action_Result $result ): array {

		// The two URL-carrying intents fail z.url() if a producer ever omits the
		// URL, and that would take the whole body down.
		if ( '' === $result->intervention_url()
			&& in_array(
				$result->intervention_intent(),
				array( Addon_Action_Result::INTENT_PLUGINS_PAGE, Addon_Action_Result::INTENT_DIRECT_DOWNLOAD ),
				true
			)
		) {
			return Addon_Ctas::reload_addons();
		}

		switch ( $result->intervention_intent() ) {
			case Addon_Action_Result::INTENT_FIX_LICENSE:
				return Addon_Ctas::fix_license();
			case Addon_Action_Result::INTENT_UPGRADE:
				return Addon_Ctas::upgrade_plan();
			case Addon_Action_Result::INTENT_MY_ACCOUNT:
				return Addon_Ctas::my_account();
			case Addon_Action_Result::INTENT_PLUGINS_PAGE:
				return Addon_Ctas::activate_plugin( $result->intervention_url() );
			case Addon_Action_Result::INTENT_DIRECT_DOWNLOAD:
				return Addon_Ctas::direct_download( $result->intervention_url() );
			default:
				return Addon_Ctas::reload_addons();
		}
	}
}
