<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api;

use UncannyPageBuilder\Application\Contracts\Agent\AgentToolResponse;
use UncannyPageBuilder\Domain\ErrorMessage;
use UncannyPageBuilder\Api\ApiResponse;

/**
 * Wrap AgentToolResponse contract validation for REST controllers.
 *
 * Contract mismatches are logged and mapped to a structured REST error so
 * invalid tool payloads never bubble up as fatal exceptions.
 */
final class AgentToolResponseResponder
{
    /**
     * @param array<string, mixed> $data
     */
    public static function toRestResponse(string $toolName, array $data): \WP_REST_Response|\WP_Error
    {
        try {
            return ApiResponse::ok(AgentToolResponse::validate($toolName, $data))->toResponse();
        } catch (\InvalidArgumentException | \UnexpectedValueException $e) {
            error_log(sprintf(
                '[AgentToolResponseResponder] tool=%s | %s',
                $toolName,
                $e->getMessage(),
            ));

            return ApiResponse::error(
                ErrorMessage::AgentToolContractInvalid,
                [
                    'tool_name' => $toolName,
                    'detail' => $e->getMessage(),
                ],
            );
        }
    }
}
