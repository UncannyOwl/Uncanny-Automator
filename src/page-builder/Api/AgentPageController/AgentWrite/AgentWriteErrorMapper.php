<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api\AgentPageController\AgentWrite;

use UncannyPageBuilder\Api\AgentTextResponse;
use UncannyPageBuilder\Domain\Exception\CssRuleIntegrityException;
use UncannyPageBuilder\Infrastructure\Persistence\SourceTransactionsUnavailableException;
use UncannyPageBuilder\Infrastructure\Persistence\WordPressWriteVerificationException;

/**
 * Maps infrastructure write failures into the stable Agent text protocol.
 */
final class AgentWriteErrorMapper
{
    public function cssRuleIntegrity(
        string $toolName,
        array $contextLines,
        ?CssRuleIntegrityException $exception = null,
    ): \WP_REST_Response {
        return $this->textToolError($toolName, 422, 'css_rule_integrity_failed', [
            ...$contextLines,
            'DETAIL: ' . ($exception?->getMessage() ?? 'WordPress would rewrite CSS outside the requested declaration change. Nothing was saved.'),
            'NEXT STEP',
            $this->cssRuleIntegrityNextStep($exception),
        ]);
    }

    /**
     * A direct transaction-engine failure happens before its protected write.
     *
     * @param list<string> $contextLines
     */
    public function sourceTransactionUnavailable(
        string $toolName,
        array $contextLines,
        \RuntimeException $exception,
    ): ?\WP_REST_Response {
        if (!$exception instanceof SourceTransactionsUnavailableException) {
            return null;
        }

        return $this->textToolError($toolName, 500, 'source_transactions_unavailable', [
            ...$contextLines,
            'DETAIL: ' . $exception->getMessage(),
            'RETRY_SAFETY: Nothing was saved by this operation.',
            'NEXT STEP',
            'Convert the named database table to InnoDB. Then call read_part include=source again and retry against the current source.',
        ]);
    }

    /**
     * @param list<string> $contextLines
     */
    public function conservativeSourceTransaction(
        string $toolName,
        array $contextLines,
        \Throwable $exception,
    ): ?\WP_REST_Response {
        $failure = $this->sourceTransactionFailureInChain($exception);
        if (!$failure instanceof SourceTransactionsUnavailableException) {
            return null;
        }

        return $this->textToolError($toolName, 500, 'source_transactions_unavailable', [
            ...$contextLines,
            'DETAIL: ' . $failure->getMessage(),
            'RETRY_SAFETY: An earlier persistence step may already have completed. Do not retry blindly.',
            'NEXT STEP',
            'Inspect the current page, part, canvas, or reusable with its read operation first. If the requested change is already present, do not retry. Convert the named table to InnoDB, read the current state again, and retry only if the change is absent.',
        ]);
    }

    /**
     * @param list<string> $contextLines
     */
    public function conservativeWordPressWriteVerification(
        string $toolName,
        array $contextLines,
        \Throwable $exception,
    ): ?\WP_REST_Response {
        $failure = $this->wordpressWriteVerificationFailureInChain($exception);
        if (!$failure instanceof WordPressWriteVerificationException) {
            return null;
        }

        return $this->textToolError($toolName, 500, 'source_write_unverified', [
            ...$contextLines,
            'DETAIL: ' . $failure->getMessage(),
            'RETRY_SAFETY: The requested write could not be verified, and an earlier persistence step may already have completed. Do not retry blindly.',
            'NEXT STEP',
            'Call the matching read operation first: read_runtime for custom JavaScript, or read_part for page and global-part source. If the requested change is present, do not retry. If it is absent, resolve the WordPress persistence error, read the current source again, and retry only then.',
        ]);
    }

    /** @return list<string> */
    public function contextLines(\WP_REST_Request $request): array
    {
        $lines = [];
        $part = $request->get_param('part');
        $operation = $request->get_param('operation');
        $sources = [
            is_array($part) ? $part : [],
            is_array($operation) ? $operation : [],
        ];

        foreach (
            [
            'kind' => 'KIND',
            'scope' => 'SCOPE',
            'page_id' => 'PAGE_ID',
            'section_id' => 'SECTION_ID',
            'global_part_id' => 'GLOBAL_PART_ID',
            'part_type' => 'PART_TYPE',
            'canvas_id' => 'CANVAS_ID',
            'reusable_id' => 'REUSABLE_ID',
            ] as $key => $label
        ) {
            foreach ([...$sources, [$key => $request->get_param($key)]] as $source) {
                if (!array_key_exists($key, $source) || !is_scalar($source[$key])) {
                    continue;
                }

                $value = trim((string) $source[$key]);
                if ($value !== '') {
                    $lines[] = $label . ': ' . $value;
                    break;
                }
            }
        }

        return array_values(array_unique($lines));
    }

    private function cssRuleIntegrityNextStep(?CssRuleIntegrityException $exception): string
    {
        return match ($exception?->reason()) {
            CssRuleIntegrityException::MALFORMED_SOURCE => 'Call read_part include=source, repair the unbalanced CSS with mode=source_replace, then retry the css_rule edit.',
            CssRuleIntegrityException::AMBIGUOUS_COMMENT => 'Call read_part include=source and use mode=source_patch with an exact current substring so the comment and intended replacement are both explicit.',
            CssRuleIntegrityException::AMBIGUOUS_DECLARATION_BOUNDARY => 'Call read_part include=source and repair the declaration boundary with mode=source_patch or mode=source_replace before retrying css_rule.',
            CssRuleIntegrityException::MULTIPLE_GLOBAL_PART_SOURCE_ROWS => 'Call read_part kind=global_part include=source. Migrate or explicitly consolidate every stored source row before retrying the write.',
            CssRuleIntegrityException::UNPRESERVABLE_GLOBAL_PART_SOURCE_ROWS => 'Ask an administrator to repair or explicitly consolidate the stored legacy global-part rows before retrying the write.',
            default => 'Call read_part include=source again. Preserve or explicitly repair the rejected CSS with mode=source_replace before retrying the requested edit.',
        };
    }

    private function wordpressWriteVerificationFailureInChain(\Throwable $exception): ?WordPressWriteVerificationException
    {
        for ($current = $exception; $current instanceof \Throwable; $current = $current->getPrevious()) {
            if ($current instanceof WordPressWriteVerificationException) {
                return $current;
            }
        }

        return null;
    }

    private function sourceTransactionFailureInChain(\Throwable $exception): ?SourceTransactionsUnavailableException
    {
        for ($current = $exception; $current instanceof \Throwable; $current = $current->getPrevious()) {
            if ($current instanceof SourceTransactionsUnavailableException) {
                return $current;
            }
        }

        return null;
    }

    /**
     * @param list<string> $lines
     */
    private function textToolError(string $toolName, int $status, string $code, array $lines): \WP_REST_Response
    {
        return AgentTextResponse::withStatus(implode("\n", [
            'TOOL: ' . $toolName,
            'RESULT: error',
            'ERROR_CODE: ' . $code,
            ...$lines,
        ]), $status);
    }
}
