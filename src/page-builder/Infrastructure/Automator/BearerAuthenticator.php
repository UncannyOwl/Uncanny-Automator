<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Infrastructure\Automator;

use Uncanny_Automator\App\Transports\Model_Context_Protocol\OAuth\Token_Manager;

final class BearerAuthenticator
{
    /**
     * Attempt bearer token auth via Automator's Token_Manager.
     * Falls back to native WP auth (cookie/nonce/Application Passwords)
     * ONLY when no Bearer token is present. If a Bearer token IS present
     * but invalid, authentication fails — no fallback to cookies.
     */
    public function authenticate(\WP_REST_Request $request): bool
    {
        $authHeader = $this->bearerHeader($request);

        if ($authHeader && preg_match('/^Bearer\s+(.+)$/i', $authHeader, $matches)) {
            $token = $matches[1];

            if (class_exists(Token_Manager::class)) {
                $tokenManager = new Token_Manager();
                $user = $tokenManager->get_user_from_token($token);

                if ($user) {
                    wp_set_current_user($user->ID);
                    return true;
                }
            }

            // Bearer token present but invalid — reject, don't fall through to cookies.
            wp_set_current_user(0);
            return false;
        }

        // No Bearer token — fall through to native WP auth (cookies, Application Passwords).
        return false;
    }

    /**
     * Distinguishes an Automator/Agent transport request from an interactive
     * WordPress request without authenticating or mutating the current user.
     */
    public function hasBearerCredentials(\WP_REST_Request $request): bool
    {
        return preg_match('/^Bearer\s+(.+)$/i', $this->bearerHeader($request)) === 1;
    }

    private function bearerHeader(\WP_REST_Request $request): string
    {
        $authHeader = (string) $request->get_header('authorization');

        // Apache may strip Authorization while preserving Automator's fallback.
        if (stripos($authHeader, 'bearer') === false) {
            return (string) $request->get_header('x-automator-creds');
        }

        return $authHeader;
    }
}
