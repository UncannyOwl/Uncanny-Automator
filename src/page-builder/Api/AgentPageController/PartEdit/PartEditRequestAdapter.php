<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api\AgentPageController\PartEdit;

/**
 * Converts the nested edit_part payload into the flat requests consumed by
 * focused write controllers.
 */
final class PartEditRequestAdapter
{
    /**
     * @param array<string, mixed> $params
     */
    public function withOverrides(\WP_REST_Request $request, array $params): \WP_REST_Request
    {
        if (method_exists($request, 'set_param')) {
            $cloned = clone $request;
            foreach ($params as $key => $value) {
                $cloned->set_param((string) $key, $value);
            }

            return $cloned;
        }

        $existing = [];
        if (method_exists($request, 'get_params')) {
            $existing = (array) $request->get_params();
        } elseif (property_exists($request, 'params')) {
            $reflection = new \ReflectionProperty($request, 'params');
            $reflection->setAccessible(true);
            $existing = (array) $reflection->getValue($request);
        }

        return new \WP_REST_Request(array_replace($existing, $params));
    }

    /**
     * @param array<string, mixed> $operation
     * @return mixed
     */
    public function durableStyleChanges(array $operation): mixed
    {
        $target = is_array($operation['target'] ?? null) ? $operation['target'] : null;
        $styles = is_array($operation['styles'] ?? null) ? $operation['styles'] : null;

        if ($target === null || $styles === null || $styles === []) {
            return null;
        }

        $viewport = $this->styleScopeValue($operation['viewport'] ?? null, 'desktop');
        $state = $this->styleScopeValue($operation['state'] ?? null, 'normal');
        $changes = [];

        foreach ($styles as $property => $value) {
            $property = is_string($property) ? trim($property) : '';
            if ($property === '' || is_array($value) || is_object($value)) {
                continue;
            }

            $changes[] = [
                'property' => $property,
                'value' => is_int($value) || is_float($value) ? (string) $value : (string) $value,
                'viewport' => $viewport,
                'state' => $state,
                'target' => $target,
            ];
        }

        return $changes !== [] ? $changes : null;
    }

    private function styleScopeValue(mixed $value, string $fallback): string
    {
        if (is_string($value)) {
            $value = trim($value);
        }

        return is_string($value) && $value !== '' ? $value : $fallback;
    }
}
