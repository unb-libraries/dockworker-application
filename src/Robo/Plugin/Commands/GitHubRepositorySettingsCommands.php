<?php

namespace Dockworker\Robo\Plugin\Commands;

use Consolidation\AnnotatedCommand\Events\CustomEventAwareInterface;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareTrait;
use Dockworker\DockworkerApplicationCommands;
use Dockworker\GitHub\GitHubRepoSettingsTrait;
use Dockworker\IO\DockworkerIOTrait;
use Symfony\Component\Yaml\Yaml;

/**
 * Provides commands for building the application's theme assets.
 */
class GitHubRepositorySettingsCommands extends DockworkerApplicationCommands implements CustomEventAwareInterface
{
    use CustomEventAwareTrait;
    use DockworkerIOTrait;
    use GitHubRepoSettingsTrait;

    /**
     * Sets topics for the repository.
     *
     * @command github:repository:set-topics
     * @hidden
     */
    public function setGitHubTopics(): void
    {
        $this->initGitHubRepoSettingsCommands();
        $this->dockworkerIO->title("Setting GitHub Repository Topics");
        $topics = $this->getGitHubRepositoryTopics();
        try{
            $this->dockworkerIO->listing($topics);
            $currentTopics = $this->gitHubClient->api('repo')->replaceTopics(
                $this->applicationGitHubRepoOwner,
                $this->applicationGitHubRepoName,
                $topics
            );
            $this->say("GitHub repository topics set successfully!");
        }
        catch (\Exception $e) {
            $this->dockworkerIO->error("Unable to set GitHub repository topics: " . $e->getMessage());
            exit(1);
        }
    }

    protected function initGitHubRepoSettingsCommands(): void
    {
        $this->initGitHubClientApplicationRepo(
            $this->applicationGitHubRepoOwner,
            $this->applicationGitHubRepoName
        );
        $this->checkPreflightChecks($this->dockworkerIO);
    }

    protected function getGitHubRepositoryTopics()
    {
        $file_path = "$this->applicationRoot/vendor/unb-libraries/dockworker-application/data/github.yml";
        $data = Yaml::parseFile($file_path);
        $topics = $data['github']['repository']['topics'];
        $handlers = $this->getCustomEventHandlers('dockworker-github-topics');
        foreach ($handlers as $handler) {
            $new_topics = $handler();
            $topics = array_merge(
                $topics,
                $new_topics
            );
        }
        return $topics;
    }
}
