<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Editing;

use UncannyPageBuilder\Domain\Exception\NodeEditRejectedException;

/** An address in one exact editable source revision. It is not a DOM selector. */
final class SourceNodeReference
{
    private function __construct(private readonly array $value) {}

    public static function fromArray(mixed $value): self
    {
        // A malformed request is a rejected source reference, not a runtime
        // failure that would make the client treat a write as uncertain.
        if (!is_array($value)) {
            throw NodeEditRejectedException::targetChanged('A source reference object is required.');
        }

        if (($value['version'] ?? null) !== 1) {
            throw NodeEditRejectedException::targetChanged('The source reference version is unsupported.');
        }

        self::assertOwner($value['owner'] ?? null);
        self::assertPositiveId($value['section_id'] ?? null, 'section');
        self::assertGeneration($value['generation'] ?? null);
        self::assertNodeAddress($value);
        self::assertBasis($value['basis'] ?? null);

        // Canonical field order makes equality independent of JSON object order.
        return new self([
            'version' => 1,
            'owner' => ['kind' => $value['owner']['kind'], 'id' => $value['owner']['id']],
            'section_id' => $value['section_id'],
            'basis' => self::canonicalBasis($value['basis']),
            'generation' => $value['generation'],
            'source_hash' => $value['source_hash'],
            'node_key' => $value['node_key'],
        ]);
    }

    /** A source owner selects the page or shared reusable mutation boundary. */
    private static function assertOwner(mixed $owner): void
    {
        if (!is_array($owner)) {
            throw NodeEditRejectedException::targetChanged('A source owner is required.');
        }

        if (!in_array($owner['kind'] ?? null, ['page', 'global_part'], true)) {
            throw NodeEditRejectedException::targetChanged('The source owner kind is unsupported.');
        }

        self::assertPositiveId($owner['id'] ?? null, 'owner');
    }

    /** References address persisted objects. Temporary and coerced IDs are invalid. */
    private static function assertPositiveId(mixed $id, string $name): void
    {
        if (!is_int($id) || $id <= 0) {
            throw NodeEditRejectedException::targetChanged('The source ' . $name . ' ID is invalid.');
        }
    }

    /** Generation zero is a valid baseline before the first source write. */
    private static function assertGeneration(mixed $generation): void
    {
        if (!is_int($generation) || $generation < 0) {
            throw NodeEditRejectedException::targetChanged('The source generation is invalid.');
        }
    }

    /** The hash fixes the source bytes. The node key addresses that parsed document. */
    private static function assertNodeAddress(array $value): void
    {
        if (!self::matches($value['source_hash'] ?? null, '/^[a-f0-9]{64}$/D')) {
            throw NodeEditRejectedException::targetChanged('The source hash is invalid.');
        }

        if (!self::matches($value['node_key'] ?? null, '/^n[0-9a-f]+$/D')) {
            throw NodeEditRejectedException::targetChanged('The source node key is invalid.');
        }
    }

    private static function matches(mixed $value, string $pattern): bool
    {
        return is_string($value) && preg_match($pattern, $value) === 1;
    }

    /** Working, published, and history source must never share write authority. */
    private static function assertBasis(mixed $basis): void
    {
        if (!is_array($basis)) {
            throw NodeEditRejectedException::targetChanged('The source reference is invalid.');
        }

        // PHP switch uses loose equality. Reject nonstrings before it can treat
        // a Boolean as the name of an authorized source basis.
        $kind = $basis['kind'] ?? null;
        if (!is_string($kind)) {
            throw NodeEditRejectedException::targetChanged('The source basis is unsupported.');
        }

        switch ($kind) {
            case 'working':
                return;
            case 'published':
                self::assertPositiveId($basis['snapshot_id'] ?? null, 'snapshot');
                return;
            case 'history':
                self::assertHistoryBasis($basis);
                return;
            default:
                throw NodeEditRejectedException::targetChanged('The source basis is unsupported.');
        }
    }

    /** A history address belongs to one explicit Undo or Redo preview. */
    private static function assertHistoryBasis(array $basis): void
    {
        self::assertPositiveId($basis['operation_id'] ?? null, 'history operation');

        if (!in_array($basis['direction'] ?? null, ['undo', 'redo'], true)) {
            throw NodeEditRejectedException::targetChanged('The history source reference is invalid.');
        }
    }

    private static function canonicalBasis(array $basis): array
    {
        return match ($basis['kind']) {
            'published' => ['kind' => 'published', 'snapshot_id' => $basis['snapshot_id']],
            'history' => [
                'kind' => 'history',
                'operation_id' => $basis['operation_id'],
                'direction' => $basis['direction'],
            ],
            default => ['kind' => 'working'],
        };
    }

    public function assertBaseline(array $baseline, int $sectionId, string $html): void
    {
        $sameOwner = $this->value['owner']['kind'] === $baseline['owner']['kind']
            && $this->value['owner']['id'] === $baseline['owner']['id'];
        $sameSection = $this->value['section_id'] === $sectionId;

        if (!$sameOwner || !$sameSection) {
            throw NodeEditRejectedException::targetChanged('The reference belongs to a different source owner or section.');
        }

        $sameGeneration = $this->value['generation'] === $baseline['generation'];
        $sameBasis = $this->value['basis'] === self::canonicalBasis($baseline['basis']);
        if (!$sameGeneration || !$sameBasis) {
            throw NodeEditRejectedException::targetChanged('The source revision changed. Read its current revision before saving.');
        }

        // Matching revisions alone cannot authorize a node in different source bytes.
        if (!hash_equals($this->value['source_hash'], hash('sha256', $html))) {
            throw NodeEditRejectedException::targetChanged('The original source changed. Read its current revision before saving.');
        }
    }

    public function nodeKey(): string
    {
        return $this->value['node_key'];
    }

    public function toArray(): array
    {
        return $this->value;
    }
}
