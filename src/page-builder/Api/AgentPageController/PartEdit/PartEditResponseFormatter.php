<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api\AgentPageController\PartEdit;

use UncannyPageBuilder\Api\AgentTextResponse;

/**
 * Rewrites focused-controller responses back into the public edit_part
 * vocabulary without changing their status or error details.
 */
final class PartEditResponseFormatter
{
    public function facade(
        \WP_REST_Response|\WP_Error $response,
        ?string $operation = null,
    ): \WP_REST_Response|\WP_Error {
        if (!$response instanceof \WP_REST_Response) {
            return $response;
        }

        $body = $response->get_data();
        if (!is_string($body)) {
            return $response;
        }

        $body = $this->rewriteRetiredToolReferences($body);
        $replacement = 'TOOL: edit_part';
        $body = preg_match('/^TOOL: /m', $body) === 1
            ? (preg_replace('/^TOOL: [^\n]+/m', $replacement, $body, 1) ?? $body)
            : $replacement . "\n" . $body;

        if ($operation !== null && !str_contains($body, "\nOPERATION:")) {
            $body = preg_replace(
                '/^RESULT: ([^\n]+)/m',
                'RESULT: $1' . "\n" . 'OPERATION: ' . $operation,
                $body,
                1,
            ) ?? $body;
        }

        return AgentTextResponse::withStatus($body, $response->get_status());
    }

    /**
     * @param list<string> $lines
     */
    public function error(int $status, string $code, array $lines): \WP_REST_Response
    {
        return AgentTextResponse::withStatus(implode("\n", [
            'TOOL: edit_part',
            'RESULT: error',
            'ERROR_CODE: ' . $code,
            ...$lines,
        ]), $status);
    }

    private function rewriteRetiredToolReferences(string $body): string
    {
        return strtr($body, [
            'read_page_outline' => 'read_page_context',
            'read_section_manifest' => 'read_part include=manifest',
            'read_section_source' => 'read_part include=source',
            'read_content_targets' => 'read_part include=content_targets',
            'read_design_targets' => 'read_part include=design_targets',
            'read_global_part_source' => 'read_part kind=global_part include=source',
            'update_text_target' => 'edit_part mode=text',
            'update_link_target' => 'edit_part mode=link',
            'update_image_target' => 'edit_part mode=image',
            'update_element_style' => 'edit_part mode=durable_style',
            'patch_section_source' => 'edit_part mode=source_patch',
            'rewrite_section_source' => 'edit_part mode=source_replace',
            'update_global_part' => 'edit_part kind=global_part mode=source_replace',
            'patch_global_part_source' => 'edit_part kind=global_part mode=source_patch',
            'patch_global_part' => 'edit_part kind=global_part mode=source_patch',
            'list_bindings' => 'manage_binding operation=search',
            'get_binding_guide' => 'manage_binding operation=guide',
            'update_binding' => 'manage_binding operation=update_query or update_template',
            'preview_patch' => 'preview_change',
            'reorder_sections' => 'manage_sections operation=reorder',
            'delete_section' => 'manage_sections operation=delete',
        ]);
    }
}
