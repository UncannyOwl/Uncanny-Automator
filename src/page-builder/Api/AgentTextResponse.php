<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api;

/**
 * Builds text/plain tool responses for agent-facing source and planning tools.
 */
final class AgentTextResponse
{
    public static function ok(string $body): \WP_REST_Response
    {
        return self::withStatus($body, 200);
    }

    /** @param list<string> $lines */
    public static function error(string $toolName, int $status, string $code, array $lines): \WP_REST_Response
    {
        return self::withStatus(implode("\n", [
            'TOOL: ' . $toolName,
            'RESULT: error',
            'ERROR_CODE: ' . $code,
            ...$lines,
        ]), $status);
    }

    public static function withStatus(string $body, int $status): \WP_REST_Response
    {
        $response = new \WP_REST_Response($body, $status);
        if (method_exists($response, 'header')) {
            $response->header('X-UE-Text-Response', '1');
        } elseif (method_exists($response, 'set_headers')) {
            $response->set_headers(['X-UE-Text-Response' => '1']);
        }

        return $response;
    }
}
