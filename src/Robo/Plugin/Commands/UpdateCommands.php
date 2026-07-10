<?php

namespace Dockworker\Robo\Plugin\Commands;

use Consolidation\AnnotatedCommand\CommandData;
use Dockworker\Cli\CliCommandTrait;
use Dockworker\DockworkerCommands;
use Dockworker\DockworkerException;
use Dockworker\Git\GitRepoTrait;
use Dockworker\IO\DockworkerIOTrait;
use Robo\Robo;

/**
 * Provides the generic, config-driven dependency update command.
 *
 * Unlike most commands in this package (which are empty placeholders whose
 * behavior is supplied per-framework via "@hook post-command"), the real logic
 * lives here in the command body. Dependency updating is generic - composer is
 * composer across frameworks - so it belongs in the generic command rather than
 * being duplicated in each flavor package. Framework-specific *extra* post-update
 * steps may still be added by extensions via "@hook post-command dockworker:update"
 * (such a hook runs on its own instance and must initialize its own resources).
 *
 * Targets are declared in .dockworker/dockworker.yml, and this command is the
 * single source of truth for both what is updated and what is committed:
 *
 *   dockworker:
 *     update:
 *       targets:
 *         - dir: build
 *           type: composer
 *
 * When run under GitHub Actions it emits two step outputs consumed by the reusable
 * update.yaml workflow: "add-paths" (the pathspecs create-pull-request should stage)
 * and "primary-lock" (the first composer lock file, for composer-diff-action).
 */
class UpdateCommands extends DockworkerCommands
{
    use CliCommandTrait;
    use DockworkerIOTrait;
    use GitRepoTrait;

    /**
     * The updater presets, keyed by target 'type'.
     *
     * @var array<string, array{command: string[], commit_paths: string[], lockfile: string, manifest: string}>
     */
    private const UPDATE_PRESETS = [
        'composer' => [
            'command' => ['composer', 'update', '--no-autoloader', '--no-scripts', '--no-plugins'],
            'commit_paths' => ['composer.json', 'composer.lock'],
            'lockfile' => 'composer.lock',
            'manifest' => 'composer.json',
        ],
    ];

    /**
     * Updates this application's dependencies to their latest allowable versions.
     *
     * @command dockworker:update
     * @aliases update
     * @hidden
     *
     * @return void
     * @throws \CzProject\GitPhp\GitException
     * @throws \Dockworker\DockworkerException
     */
    public function updateApplication(): void
    {
        $this->dockworkerIO->title('Updating Application Dependencies');

        $failures = [];
        $commit_paths = [];
        $primary_lock = '';

        foreach ($this->getUpdateTargets() as $target) {
            $updater = $this->resolveUpdater($target);
            $dir = rtrim((string) $target['dir'], '/');
            $target_path = $this->applicationRoot . '/' . $dir;
            $this->exceptIfFileDoesNotExist($target_path);
            if ($updater['manifest'] !== '') {
                $this->exceptIfFileDoesNotExist($target_path . '/' . $updater['manifest']);
            }

            $this->dockworkerIO->section("Updating $dir");
            $command = $this->executeCliCommand(
                $updater['command'],
                $this->dockworkerIO,
                $target_path,
                '',
                sprintf('Running [%s] in %s', implode(' ', $updater['command']), $dir),
                true
            );

            if ($command === null || !$command->isSuccessful()) {
                $exit_code = $command === null ? -1 : ($command->getExitCode() ?? -1);
                $failures[] = "$dir (exit code $exit_code)";
                continue;
            }

            foreach ($updater['commit_paths'] as $commit_path) {
                $commit_paths[] = "$dir/$commit_path";
            }
            if ($primary_lock === '' && ($target['type'] ?? null) === 'composer') {
                $primary_lock = "$dir/" . $updater['lockfile'];
            }

            $lock_key = "$dir/" . $updater['lockfile'];
            if ($this->repoFileHasChanges($lock_key)) {
                $this->dockworkerIO->say("Updates found: changes detected in $lock_key.");
            } else {
                $this->dockworkerIO->say("No updates: $lock_key unchanged.");
            }
        }

        if ($failures !== []) {
            throw new DockworkerException(
                'Dependency update failed for: ' . implode('; ', $failures)
            );
        }

        $this->writeUpdateOutputs($commit_paths, $primary_lock);
    }

    /**
     * Validates the configured update targets before the command runs.
     *
     * @param \Consolidation\AnnotatedCommand\CommandData $commandData
     *   The command data.
     *
     * @hook validate dockworker:update
     *
     * @return void
     * @throws \Dockworker\DockworkerException
     */
    public function validateUpdateTargets(CommandData $commandData): void
    {
        foreach ($this->getUpdateTargets() as $index => $target) {
            if (empty($target['dir']) || !is_string($target['dir'])) {
                throw new DockworkerException(
                    "Update target #$index is missing a valid 'dir'."
                );
            }
            if (str_starts_with($target['dir'], '/') || str_contains($target['dir'], '..')) {
                throw new DockworkerException(
                    sprintf(
                        'Update target dir [%s] must be a relative path within the repo (no leading "/" or "..").',
                        $target['dir']
                    )
                );
            }
            // Throws if the type/command cannot be resolved.
            $this->resolveUpdater($target);
        }
    }

    /**
     * Gets the configured update targets.
     *
     * @return array<int, array<string, mixed>>
     *   The list of target definitions.
     *
     * @throws \Dockworker\DockworkerException
     */
    private function getUpdateTargets(): array
    {
        $targets = $this->getConfigItem(Robo::config(), 'dockworker.update.targets', []);
        if (!is_array($targets) || $targets === []) {
            throw new DockworkerException(
                'No dockworker.update.targets configured in .dockworker/dockworker.yml. Add e.g.:'
                . PHP_EOL . '  dockworker:'
                . PHP_EOL . '    update:'
                . PHP_EOL . '      targets:'
                . PHP_EOL . '        - dir: build'
                . PHP_EOL . '          type: composer'
            );
        }
        return array_values($targets);
    }

    /**
     * Resolves a target's updater command, lock file, manifest, and commit paths.
     *
     * Inline target keys (command, lockfile, manifest, commit_paths) override the
     * preset selected by the target's 'type'.
     *
     * @param array<string, mixed> $target
     *   The target definition.
     *
     * @return array{command: string[], commit_paths: string[], lockfile: string, manifest: string}
     *   The resolved updater specification.
     *
     * @throws \Dockworker\DockworkerException
     */
    private function resolveUpdater(array $target): array
    {
        $preset = [];
        $type = $target['type'] ?? null;
        if (is_string($type)) {
            if (!isset(self::UPDATE_PRESETS[$type])) {
                throw new DockworkerException(
                    sprintf(
                        'Unknown update target type [%s]. Available types: %s.',
                        $type,
                        implode(', ', array_keys(self::UPDATE_PRESETS))
                    )
                );
            }
            $preset = self::UPDATE_PRESETS[$type];
        }

        $command = $target['command'] ?? $preset['command'] ?? null;
        if (!is_array($command) || $command === []) {
            throw new DockworkerException(
                "Update target must specify a known 'type' or an inline 'command' array."
            );
        }

        $lockfile = $target['lockfile'] ?? $preset['lockfile'] ?? null;
        if (!is_string($lockfile) || $lockfile === '') {
            throw new DockworkerException(
                "Update target must resolve a 'lockfile' from its preset or inline configuration."
            );
        }

        $commit_paths = $target['commit_paths'] ?? $preset['commit_paths'] ?? [$lockfile];
        if (!is_array($commit_paths) || $commit_paths === []) {
            $commit_paths = [$lockfile];
        }

        $manifest = $target['manifest'] ?? $preset['manifest'] ?? '';

        return [
            'command' => array_values(array_map('strval', $command)),
            'commit_paths' => array_values(array_map('strval', $commit_paths)),
            'lockfile' => $lockfile,
            'manifest' => is_string($manifest) ? $manifest : '',
        ];
    }

    /**
     * Emits the commit paths and primary lock file for the CI workflow.
     *
     * Writes GitHub Actions step outputs "add-paths" (newline-delimited pathspecs)
     * and "primary-lock" when running under Actions; otherwise prints them.
     *
     * @param string[] $commit_paths
     *   The repo-relative paths the pull request should stage.
     * @param string $primary_lock
     *   The first composer lock file path, for composer-diff-action.
     *
     * @return void
     */
    private function writeUpdateOutputs(array $commit_paths, string $primary_lock): void
    {
        $commit_paths = array_values(array_unique($commit_paths));

        $github_output = getenv('GITHUB_OUTPUT');
        if ($github_output === false || $github_output === '') {
            $this->dockworkerIO->say('add-paths: ' . implode(' ', $commit_paths));
            $this->dockworkerIO->say('primary-lock: ' . $primary_lock);
            return;
        }

        $delimiter = 'DOCKWORKER_ADD_PATHS_' . bin2hex(random_bytes(8));
        $output = 'add-paths<<' . $delimiter . PHP_EOL
            . implode(PHP_EOL, $commit_paths) . PHP_EOL
            . $delimiter . PHP_EOL
            . 'primary-lock=' . $primary_lock . PHP_EOL;
        file_put_contents($github_output, $output, FILE_APPEND);
    }
}
