<?php

namespace Dockworker\Regression;

use Symfony\Component\Yaml\Yaml;

/**
 * Context object passed to every regression check handler.
 *
 * The orchestrator constructs one context per command run and reuses it across
 * every handler. Parsed YAML files are memoized so multiple handlers inspecting
 * the same file share a single parse.
 *
 * This class is part of the stable public contract for the
 * dockworker-regression-checks event. Adding new public fields or methods is
 * backwards-compatible; renaming or removing them is a breaking change.
 *
 * YAML parse errors propagate as Symfony\Component\Yaml\Exception\ParseException
 * so the orchestrator can convert them to a meta-FAIL result with the offending
 * handler's identity; handlers are not expected to catch ParseException
 * themselves.
 */
final class RegressionCheckContext
{
    /**
     * Memoization for loadYaml() keyed by relative path.
     *
     * Uses array_key_exists() to disambiguate "loaded, returned null" (file
     * absent) from "never loaded".
     *
     * @var array<string, array<mixed>|null>
     */
    private array $yamlCache = [];

    /**
     * @param array<mixed> $dockworkerConfig
     *   The parsed contents of the managed repo's .dockworker/dockworker.yml,
     *   already loaded by Robo at @hook pre-init. Typically what you'd get from
     *   Robo::config()->get('dockworker').
     */
    public function __construct(
        public readonly string $applicationRoot,
        public readonly array $dockworkerConfig,
        public readonly ?string $frameworkName,
        public readonly ?string $frameworkVersion,
    ) {
    }

    /**
     * Loads and memoizes a YAML file relative to the managed repo root.
     *
     * @param string $relativePath
     *   A path relative to the repo root. Absolute paths and paths containing
     *   ".." are rejected with an InvalidArgumentException.
     *
     * @return array<mixed>|null
     *   The parsed contents of the file, or null if the file does not exist.
     *
     * @throws \InvalidArgumentException
     *   When the path is absolute or contains "..".
     * @throws \Symfony\Component\Yaml\Exception\ParseException
     *   When the file exists but cannot be parsed as YAML. The orchestrator
     *   catches this and surfaces it as a meta-FAIL result.
     */
    public function loadYaml(string $relativePath): ?array
    {
        if ($relativePath === '' || str_starts_with($relativePath, '/') || str_contains($relativePath, '..')) {
            throw new \InvalidArgumentException(sprintf(
                'Refusing to load YAML from suspicious path: "%s". Paths must be repo-relative and must not contain "..".',
                $relativePath,
            ));
        }
        if (array_key_exists($relativePath, $this->yamlCache)) {
            return $this->yamlCache[$relativePath];
        }
        $fullPath = $this->applicationRoot . '/' . $relativePath;
        $parsed = is_file($fullPath) ? Yaml::parseFile($fullPath) : null;
        if ($parsed !== null && !is_array($parsed)) {
            // A YAML file whose top-level value is a scalar or null parses to a
            // non-array. Normalize to null so handlers can rely on array|null.
            $parsed = null;
        }
        return $this->yamlCache[$relativePath] = $parsed;
    }

    /**
     * Convenience accessor for the parsed docker-compose.yml in the repo root.
     *
     * @return array<mixed>|null
     */
    public function getDockerComposeConfig(): ?array
    {
        return $this->loadYaml('docker-compose.yml');
    }

    /**
     * Convenience accessor for the services map from docker-compose.yml.
     *
     * @return array<string, array<mixed>>
     *   Keys are service names; values are the per-service config blocks.
     *   Empty if docker-compose.yml is missing or has no services key.
     */
    public function getDockerComposeServices(): array
    {
        $config = $this->getDockerComposeConfig();
        if (!is_array($config) || !isset($config['services']) || !is_array($config['services'])) {
            return [];
        }
        return $config['services'];
    }
}
