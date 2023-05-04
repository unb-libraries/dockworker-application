<?php

namespace Dockworker;

use Consolidation\AnnotatedCommand\AnnotationData;
use Dockworker\DockworkerCommands;
use Robo\Robo;
use Symfony\Component\Console\Input\InputInterface;

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

    /**
     * @hook pre-init
     */
    public function initOptions()
    {
        parent::initOptions();
        $this->setApplicationProperties();
    }
}
