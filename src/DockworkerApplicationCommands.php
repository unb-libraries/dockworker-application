<?php

namespace Dockworker;

use Dockworker\DockworkerCommands;
use Robo\Robo;

/**
 * Defines a base abstract class for all dockworker-application commands.
 *
 * This is not a command class. It should not contain any hooks or commands.
 */
class DockworkerApplicationCommands extends DockworkerCommands
{
    /**
     * The name of the application framework/application.
     *
     * @var string
     */
    protected string $applicationFrameworkName;

    /**
     * The UNB Libraries application uuid for the application.
     *
     * @link https://systems.lib.unb.ca/wiki/systems:docker:unique-site-uuids UNB Libraries UUIDs
     * @var string
     */
    protected string $applicationUuid;

    /**
     * DockworkerCommands constructor.
     *
     * @throws \Dockworker\DockworkerException
     */
    public function __construct()
    {
        parent::__construct();
        $this->setApplicationProperties();
    }

    /**
     * Initializes the application's core properties.
     *
     * @throws \Dockworker\DockworkerException
     */
    public function setApplicationProperties(): void
    {
        $config = Robo::config();
        $this->setPropertyFromConfigKey(
            $config,
            'applicationUuid',
            'dockworker.application.identifiers.uuid'
        );
        $this->setPropertyFromConfigKey(
            $config,
            'applicationFrameworkName',
            'dockworker.application.framework.name'
        );
    }
}
