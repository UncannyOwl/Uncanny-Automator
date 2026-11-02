<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Plan\Addons\Value_Objects;

/**
 * The outcome of one plugin-manager action on an addon.
 *
 * A failure carries an INTENT rather than a CTA: what the user needs to do
 * next is domain knowledge, while the button that expresses it is the
 * transport's frozen wire shape.
 */
final class Addon_Action_Result {

	/**
	 * Reload the AddOns page and try again.
	 *
	 * @var string
	 */
	const INTENT_RELOAD = 'reload';

	/**
	 * Repair the license.
	 *
	 * @var string
	 */
	const INTENT_FIX_LICENSE = 'fix-license';

	/**
	 * Move to a higher plan.
	 *
	 * @var string
	 */
	const INTENT_UPGRADE = 'upgrade';

	/**
	 * Download the package from the store account.
	 *
	 * @var string
	 */
	const INTENT_MY_ACCOUNT = 'my-account';

	/**
	 * Finish up on the WordPress plugins screen.
	 *
	 * @var string
	 */
	const INTENT_PLUGINS_PAGE = 'plugins-page';

	/**
	 * Hand the user the signed package URL to install manually.
	 *
	 * @var string
	 */
	const INTENT_DIRECT_DOWNLOAD = 'direct-download';

	/**
	 * Whether the action completed.
	 *
	 * @var bool
	 */
	private bool $succeeded;

	/**
	 * Whether the addon ended up active.
	 *
	 * @var bool
	 */
	private bool $activated;

	/**
	 * Signed download URL, when the action was a download.
	 *
	 * @var string
	 */
	private string $direct_download;

	/**
	 * What went wrong.
	 *
	 * @var string
	 */
	private string $intervention_message;

	/**
	 * What the user should do about it.
	 *
	 * @var string
	 */
	private string $intervention_intent;

	/**
	 * Where that action lives, when the intent needs a specific URL.
	 *
	 * @var string
	 */
	private string $intervention_url;

	/**
	 * Construct from a resolved outcome.
	 *
	 * @param bool   $succeeded            Whether the action completed.
	 * @param bool   $activated            Whether the addon ended up active.
	 * @param string $direct_download      Signed download URL.
	 * @param string $intervention_message What went wrong.
	 * @param string $intervention_intent  What the user should do.
	 * @param string $intervention_url     Where that action lives.
	 */
	private function __construct(
		bool $succeeded,
		bool $activated,
		string $direct_download,
		string $intervention_message,
		string $intervention_intent,
		string $intervention_url
	) {
		$this->succeeded            = $succeeded;
		$this->activated            = $activated;
		$this->direct_download      = $direct_download;
		$this->intervention_message = $intervention_message;
		$this->intervention_intent  = $intervention_intent;
		$this->intervention_url     = $intervention_url;
	}

	/**
	 * The action completed.
	 *
	 * @param bool $activated Whether the addon ended up active.
	 *
	 * @return self
	 */
	public static function success( bool $activated ): self {
		return new self( true, $activated, '', '', '', '' );
	}

	/**
	 * The package is ready to download.
	 *
	 * @param string $url Signed download URL.
	 *
	 * @return self
	 */
	public static function download( string $url ): self {
		return new self( true, false, $url, '', '', '' );
	}

	/**
	 * The action could not be completed.
	 *
	 * @param string $message What went wrong.
	 * @param string $intent  What the user should do.
	 * @param string $url     Where that action lives, when it needs one.
	 *
	 * @return self
	 */
	public static function failure( string $message, string $intent, string $url = '' ): self {
		return new self( false, false, '', $message, $intent, $url );
	}

	/**
	 * Whether the action completed.
	 *
	 * @return bool
	 */
	public function succeeded(): bool {
		return $this->succeeded;
	}

	/**
	 * Whether the addon ended up active.
	 *
	 * @return bool
	 */
	public function was_activated(): bool {
		return $this->activated;
	}

	/**
	 * Signed download URL, or an empty string.
	 *
	 * @return string
	 */
	public function direct_download(): string {
		return $this->direct_download;
	}

	/**
	 * What went wrong.
	 *
	 * @return string
	 */
	public function intervention_message(): string {
		return $this->intervention_message;
	}

	/**
	 * What the user should do about it.
	 *
	 * @return string
	 */
	public function intervention_intent(): string {
		return $this->intervention_intent;
	}

	/**
	 * Where that action lives.
	 *
	 * @return string
	 */
	public function intervention_url(): string {
		return $this->intervention_url;
	}
}
