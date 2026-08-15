<?php

declare(strict_types=1);

namespace UncannyPageBuilder\Api;

use UncannyPageBuilder\Api\ApiResponse;
use UncannyPageBuilder\Application\Agent\AgentToolRegistryAdapter;
use UncannyPageBuilder\Application\Controls\ControlRegistry;
use UncannyPageBuilder\Application\Filesystem\LocalFileReaderInterface;

/**
 * Serves the page-building tool contract for agent discovery.
 *
 * The PHP control registry owns exposure and approval semantics. The checked-in
 * tools/*.json manifests provide the public HTTP and parameter contract.
 */
final class AgentToolsController
{
    private const SCHEMA_VERSION = '1';

    public function __construct(
        private readonly PermissionChecker $permissions,
        private readonly LocalFileReaderInterface $filesystem,
        private readonly ?ControlRegistry $registry = null,
    ) {}

    public function registerRoutes(): void
    {
        register_rest_route('uncanny-page-builder/v1', '/agent/tools', [
            'methods'             => 'GET',
            'callback'            => [$this, 'index'],
            'permission_callback' => [$this->permissions, 'canEdit'],
        ]);
    }

    public function index(\WP_REST_Request $request): \WP_REST_Response
    {
        return ApiResponse::ok(self::contract($this->filesystem, $this->registry))->toResponse();
    }

    /**
     * Assemble the full contract from registry-exposed controls, with
     * tools/*.json filling any names not yet represented by controls.
     *
     * @return array{schema_version: string, tools: list<array<string, mixed>>}
     */
    public static function contract(
        LocalFileReaderInterface $filesystem,
        ?ControlRegistry $registry = null,
    ): array {
        if ($registry instanceof ControlRegistry) {
            return (new AgentToolRegistryAdapter($registry, UNCANNY_PB_PATH . 'tools', $filesystem))->contract(self::SCHEMA_VERSION);
        }

        return self::manifestContract($filesystem);
    }

    /**
     * @return array{schema_version: string, tools: list<array<string, mixed>>}
     */
    public static function manifestContract(LocalFileReaderInterface $filesystem): array
    {
        $toolsDir = UNCANNY_PB_PATH . 'tools';
        $tools = [];

        foreach (glob($toolsDir . '/*.json') as $file) {
            $json = $filesystem->read($file);
            if ($json === false) {
                continue;
            }
            $tool = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
            $tools[] = $tool;
        }

        return [
            'schema_version' => self::SCHEMA_VERSION,
            'tools'          => $tools,
        ];
    }
}
