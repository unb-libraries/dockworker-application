<?php

namespace Dockworker\Robo\Plugin\Commands;

use Dockworker\DockworkerApplicationCommands;

/**
 * Provides commands for building the application's theme assets.
 */
class ThemeCommands extends DockworkerApplicationCommands
{
    /**
     * Builds this application's theme assets into a distributable state.
     *
     * This command is a placeholder for framework-specific update commands that
     * can be implemented by extensions of this package.
     *
     * @command theme:build-all
     * @aliases build-themes
     */
    public function buildThemes(): void
    {
        // Pass.
    }
}
