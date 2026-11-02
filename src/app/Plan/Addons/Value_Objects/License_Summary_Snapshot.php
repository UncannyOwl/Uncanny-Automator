<?php
declare(strict_types=1);

namespace Uncanny_Automator\App\Plan\Addons\Value_Objects;

/**
 * The license state the AddOns summary renders, resolved once.
 *
 * Carries the domain truth — including the display label the wire currently
 * has no slot for — and leaves presentation (enum-safe status values, the
 * tier vocabulary, notices, CTAs) to the transport shaper.
 */
final class License_Summary_Snapshot {

	/**
	 * The resolved scenario.
	 *
	 * @var Summary_Scenario
	 */
	private Summary_Scenario $scenario;

	/**
	 * License owner name.
	 *
	 * @var string
	 */
	private string $owner_name;

	/**
	 * License owner email.
	 *
	 * @var string
	 */
	private string $owner_email;

	/**
	 * Resolved plan slug (lite|basic|plus|elite).
	 *
	 * @var string
	 */
	private string $resolved_plan;

	/**
	 * Human-readable plan label.
	 *
	 * @var string
	 */
	private string $plan_display_label;

	/**
	 * License status, cross-checked against the local Pro state.
	 *
	 * @var string
	 */
	private string $status;

	/**
	 * Addons this license makes available.
	 *
	 * @var int
	 */
	private int $addons_available;

	/**
	 * Addons that exist.
	 *
	 * @var int
	 */
	private int $addons_total;

	/**
	 * Construct from resolved state.
	 *
	 * @param Summary_Scenario $scenario           Resolved scenario.
	 * @param string           $owner_name         License owner name.
	 * @param string           $owner_email        License owner email.
	 * @param string           $resolved_plan      Resolved plan slug.
	 * @param string           $plan_display_label Human-readable plan label.
	 * @param string           $status             Cross-checked license status.
	 * @param int              $addons_available   Addons available.
	 * @param int              $addons_total       Addons that exist.
	 */
	public function __construct(
		Summary_Scenario $scenario,
		string $owner_name,
		string $owner_email,
		string $resolved_plan,
		string $plan_display_label,
		string $status,
		int $addons_available,
		int $addons_total
	) {
		$this->scenario           = $scenario;
		$this->owner_name         = $owner_name;
		$this->owner_email        = $owner_email;
		$this->resolved_plan      = $resolved_plan;
		$this->plan_display_label = $plan_display_label;
		$this->status             = $status;
		$this->addons_available   = $addons_available;
		$this->addons_total       = $addons_total;
	}

	/**
	 * The resolved scenario.
	 *
	 * @return Summary_Scenario
	 */
	public function scenario(): Summary_Scenario {
		return $this->scenario;
	}

	/**
	 * License owner name.
	 *
	 * @return string
	 */
	public function owner_name(): string {
		return $this->owner_name;
	}

	/**
	 * License owner email.
	 *
	 * @return string
	 */
	public function owner_email(): string {
		return $this->owner_email;
	}

	/**
	 * Resolved plan slug.
	 *
	 * @return string
	 */
	public function resolved_plan(): string {
		return $this->resolved_plan;
	}

	/**
	 * Human-readable plan label.
	 *
	 * @return string
	 */
	public function plan_display_label(): string {
		return $this->plan_display_label;
	}

	/**
	 * Cross-checked license status.
	 *
	 * @return string
	 */
	public function status(): string {
		return $this->status;
	}

	/**
	 * Addons this license makes available.
	 *
	 * @return int
	 */
	public function addons_available(): int {
		return $this->addons_available;
	}

	/**
	 * Addons that exist.
	 *
	 * @return int
	 */
	public function addons_total(): int {
		return $this->addons_total;
	}
}
