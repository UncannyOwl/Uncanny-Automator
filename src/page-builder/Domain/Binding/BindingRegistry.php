<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Domain\Binding;

/**
 * Domain service — holds all loaded binding declarations.
 *
 * Consumers (BindingSchema, DynamicRenderer, AgentGuideController) read from
 * this registry instead of maintaining their own hardcoded constants.
 */
final class BindingRegistry
{
    /** @var array<string, BindingDeclaration> */
    private array $bindings;

    /**
     * @param array<string, BindingDeclaration> $bindings
     */
    public function __construct(array $bindings)
    {
        $this->bindings = $bindings;
    }

    public function get(string $id): ?BindingDeclaration
    {
        return $this->bindings[$id] ?? null;
    }

    public function regionContractFor(string $id): ?RegionContract
    {
        return $this->get($id)?->regionContract();
    }

    /**
     * Binding ids whose bare regions are fully projected (safe to collapse
     * to a code-editor token). See RegionContract::isFullyProjected().
     *
     * @return list<string>
     */
    public function fullyProjectedBindingIds(): array
    {
        $ids = [];
        foreach ($this->bindings as $id => $declaration) {
            if ($declaration->regionContract()->isFullyProjected()) {
                $ids[] = (string) $id;
            }
        }

        return $ids;
    }

    /**
     * Binding ids whose regions may be masked in the code editor: their
     * children are projected at render time (replaces=children) and they are
     * not card loops, whose first child is authored design a human should
     * edit. State-carrying regions (attributes, template element) round-trip
     * through the mask payload.
     *
     * @return list<string>
     */
    public function maskableRegionBindingIds(): array
    {
        $ids = [];
        foreach ($this->bindings as $id => $declaration) {
            if ($declaration->isCard()) {
                continue;
            }
            if ($declaration->regionContract()->replaces === RegionReplaces::Children) {
                $ids[] = (string) $id;
            }
        }

        return $ids;
    }

    public function has(string $id): bool
    {
        return isset($this->bindings[$id]);
    }

    /**
     * @return array<string, BindingDeclaration>
     */
    public function all(): array
    {
        return $this->bindings;
    }

    /**
     * @return list<string>
     */
    public function ids(): array
    {
        return array_keys($this->bindings);
    }

    /**
     * @return list<string>
     */
    public function bindKeysForSource(string $id): array
    {
        $binding = $this->get($id);
        return $binding ? $binding->bindKeys : [];
    }

    /**
     * @return list<string>
     */
    public function queryAttributesForSource(string $id): array
    {
        $binding = $this->get($id);
        if (!$binding) {
            return [];
        }
        return array_keys($binding->queryAttributes);
    }

    /**
     * @return list<string>
     */
    public function requiredQueryAttributesForSource(string $id): array
    {
        $binding = $this->get($id);
        return $binding ? $binding->requiredQueryAttributes() : [];
    }

    public function allowsMetaBindings(string $id): bool
    {
        $binding = $this->get($id);
        return $binding ? $binding->metaBindings : false;
    }

    public function allowsTermsBindings(string $id): bool
    {
        $binding = $this->get($id);
        return $binding ? $binding->termsBindings : false;
    }

    public function staticSafetyForSource(string $id): BindingStaticSafety
    {
        $binding = $this->get($id);

        return $binding ? $binding->staticSafety : BindingStaticSafety::NotStatic;
    }

    /**
     * @return array<string, string>
     */
    public function staticSafetyMap(): array
    {
        $map = [];
        foreach ($this->bindings as $id => $decl) {
            $map[$id] = $decl->staticSafety->value;
        }
        ksort($map);

        return $map;
    }

    /**
     * Build renderer class map for DynamicRenderer.
     *
     * Returns binding ID → renderer FQCN. The caller (infrastructure layer)
     * is responsible for instantiation, allowing DI when needed.
     *
     * @return array<string, string>
     */
    public function rendererClassMap(): array
    {
        $map = [];
        foreach ($this->bindings as $id => $decl) {
            if (class_exists($decl->rendererClass)) {
                $map[$id] = $decl->rendererClass;
            }
        }
        return $map;
    }

    /**
     * Build guide registry for AgentGuideController.
     *
     * @return array<string, array{title: string, summary: string, file: string}>
     */
    public function guideRegistry(): array
    {
        $registry = [];
        foreach ($this->bindings as $id => $decl) {
            if ($decl->guidePath === '' || !file_exists($decl->guidePath)) {
                continue;
            }
            $registry[$id] = [
                'title'   => $decl->title,
                'summary' => $decl->summary,
                'file'    => $decl->guidePath,
            ];
        }
        return $registry;
    }

    /**
     * Search bindings by keyword. Matches against id, title, summary, and tags.
     *
     * @param string $query     Search query (case-insensitive).
     * @param int    $limit     Max results.
     * @return array<string, BindingDeclaration> Matched declarations keyed by id.
     */
    public function search(string $query, int $limit = 10): array
    {
        $query = strtolower(trim($query));
        if ($query === '') {
            return [];
        }

        $words = preg_split('/\s+/', $query);
        $scored = [];

        foreach ($this->bindings as $id => $decl) {
            $haystack = strtolower(
                $id . ' ' . $decl->title . ' ' . $decl->summary . ' ' . implode(' ', $decl->tags)
            );

            $score = 0;
            foreach ($words as $word) {
                if (str_contains($haystack, $word)) {
                    $score++;
                    // Boost exact id match.
                    if (str_contains(strtolower($id), $word)) {
                        $score += 2;
                    }
                    // Boost tag match.
                    foreach ($decl->tags as $tag) {
                        if (str_contains(strtolower($tag), $word)) {
                            $score++;
                            break;
                        }
                    }
                }
            }

            if ($score > 0) {
                $scored[$id] = ['score' => $score, 'decl' => $decl];
            }
        }

        // Sort by score descending.
        uasort($scored, fn($a, $b) => $b['score'] <=> $a['score']);

        $results = [];
        $i = 0;
        foreach ($scored as $id => $entry) {
            if ($i >= $limit) {
                break;
            }
            $results[$id] = $entry['decl'];
            $i++;
        }

        return $results;
    }

    /**
     * Get unique tag counts for a summary overview.
     *
     * @return array<string, int> Tag name → number of bindings with that tag.
     */
    public function tagCounts(): array
    {
        $counts = [];
        foreach ($this->bindings as $decl) {
            foreach ($decl->tags as $tag) {
                $counts[$tag] = ($counts[$tag] ?? 0) + 1;
            }
        }
        arsort($counts);
        return $counts;
    }
}
