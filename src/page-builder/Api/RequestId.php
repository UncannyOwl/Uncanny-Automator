<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api;

final class RequestId
{
    public static function positive(mixed $value): ?int
    {
        if (is_int($value)) {
            return $value > 0 ? $value : null;
        }

        if (!is_string($value) || $value === '' || !ctype_digit($value)) {
            return null;
        }

        $digits = ltrim($value, '0');
        if ($digits === '') {
            return null;
        }

        $integer = filter_var($digits, FILTER_VALIDATE_INT);

        return is_int($integer) && $integer > 0 ? $integer : null;
    }

    public static function nonNegative(mixed $value): ?int
    {
        if ($value === 0 || $value === '0') {
            return 0;
        }

        return self::positive($value);
    }

    /**
     * @return null|list<int>
     */
    public static function positiveList(mixed $values): ?array
    {
        if (!is_array($values) || $values === [] || !array_is_list($values)) {
            return null;
        }

        $integers = [];
        foreach ($values as $value) {
            $integer = self::positive($value);
            if ($integer === null) {
                return null;
            }

            $integers[] = $integer;
        }

        return $integers;
    }

    public static function fromUrl(\WP_REST_Request $request, string $key): ?int
    {
        return self::fromUrlWithParser($request, $key, self::positive(...));
    }

    public static function nonNegativeFromUrl(\WP_REST_Request $request, string $key): ?int
    {
        return self::fromUrlWithParser($request, $key, self::nonNegative(...));
    }

    /**
     * @param callable(mixed): ?int $parser
     */
    private static function fromUrlWithParser(\WP_REST_Request $request, string $key, callable $parser): ?int
    {
        $urlParams = method_exists($request, 'get_url_params')
            ? $request->get_url_params()
            : [];
        $hasUrlValue = is_array($urlParams) && array_key_exists($key, $urlParams);
        $urlId = $parser($hasUrlValue ? $urlParams[$key] : $request->get_param($key));
        if ($urlId === null) {
            return null;
        }

        if (!$hasUrlValue) {
            return $urlId;
        }

        foreach (['get_json_params', 'get_body_params', 'get_query_params'] as $method) {
            if (!method_exists($request, $method)) {
                continue;
            }

            $params = $request->{$method}();
            if (!is_array($params) || !array_key_exists($key, $params)) {
                continue;
            }

            if ($parser($params[$key]) !== $urlId) {
                return null;
            }
        }

        return $urlId;
    }

    /**
     * @return array{required: true, validate_callback: callable, sanitize_callback: callable}
     */
    public static function routeArgument(): array
    {
        return [
            'required' => true,
            'validate_callback' => static fn (mixed $value): bool => self::positive($value) !== null,
            'sanitize_callback' => static fn (mixed $value): ?int => self::positive($value),
        ];
    }

    /**
     * @return array{required: true, validate_callback: callable, sanitize_callback: callable}
     */
    public static function nonNegativeRouteArgument(): array
    {
        return [
            'required' => true,
            'validate_callback' => static fn (mixed $value): bool => self::nonNegative($value) !== null,
            'sanitize_callback' => static fn (mixed $value): ?int => self::nonNegative($value),
        ];
    }
}
