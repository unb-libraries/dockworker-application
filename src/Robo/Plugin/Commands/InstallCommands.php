<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\DockworkerApplicationCommands;

/**
 * Provides commands for installing application dependencies.
 */
class InstallCommands extends DockworkerApplicationCommands
{
    /**
     * Install one or more dependencies.
     *
     * @param string[] $dependencies
     *   A list of dependencies to install.
     * @param string[] $options
     *   The options passed to the command.
     *
     * @option only
     *   Limit the dependency to only the provided value, e.g. "dev".
     *   Leave blank for no limitation.
     *
     * @command dockworker:install
     * @aliases install
     *
     * @usage dockworker install dep1 dep2 --only=dev
     */
    public function installDependencies(
        array $dependencies,
        array $options = [
      'only' => '',
        ]
    ): void {
      // Pass
    }

    /**
     * Uninstall one or more dependencies.
     *
     * @param string[] $dependencies
     *   A list of dependencies to uninstall.
     *
     * @command dockworker:uninstall
     * @aliases uninstall remove
     *
     * @usage dockworker uninstall dep1 dep2
     */
    public function uninstallDependencies(array $dependencies): void
    {
      // Pass
    }
}
