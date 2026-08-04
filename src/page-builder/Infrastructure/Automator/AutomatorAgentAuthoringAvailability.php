<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Automator;

use UncannyPageBuilder\Application\Access\AgentAuthoringAvailabilityInterface;

use function Uncanny_Automator\App\Infrastructure\automator_license_manager;

/**
 * Reads Agent authoring availability from Automator's local license state.
 */
final class AutomatorAgentAuthoringAvailability implements AgentAuthoringAvailabilityInterface
{
    private const LICENSE_DATA_TRANSIENT = 'automator_api_license';

    public function isAvailable(): bool
    {
        if (!function_exists('Uncanny_Automator\App\Infrastructure\automator_license_manager')) {
            return false;
        }

        try {
            $licenseManager = automator_license_manager();
            if (
                !is_object($licenseManager)
                || !method_exists($licenseManager, 'get_type')
                || !method_exists($licenseManager, 'get_key')
            ) {
                return false;
            }

            $type = $licenseManager->get_type();
            $key = $licenseManager->get_key();
            $licenseData = get_transient(self::LICENSE_DATA_TRANSIENT);
            $licenseStatus = is_array($licenseData)
                ? ($licenseData['license'] ?? $licenseData['status'] ?? '')
                : '';

            return in_array($type, ['free', 'pro'], true)
                && is_string($key)
                && trim($key) !== ''
                && is_scalar($licenseStatus)
                && sanitize_text_field((string) $licenseStatus) === 'valid';
        } catch (\Throwable) {
            return false;
        }
    }
}
