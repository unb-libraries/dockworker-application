<?php

namespace Dockworker\Git;

use CzProject\GitPhp\GitRepository;
use Dockworker\Git\GitRepoTrait;

/**
 * Provides methods to interact with an application's git repository.
 *
 * @INTERNAL This trait is intended only to be used by Dockworker commands. It
 * references the Dockworker application root, which is not in its own scope.
 */
trait ApplicationGitRepoTrait
{
    use GitRepoTrait;

    /**
     * The current application's git repository.
     *
     * @var \CzProject\GitPhp\GitRepository
     */
    protected GitRepository $curApplicationRepository;

    /**
     * Sets up the lean repository git repo.
     *
     * @hook init
     */
    public function initGitRepo(): void
    {
        if (isset($this->applicationRoot)) {
            $this->curApplicationRepository = $this->getGitRepoFromPath($this->applicationRoot);
        }
    }

    /**
     * Retrieves files staged for commit in the current application repository.
     *
     * @param string $file_mask
     *   An optional regex pattern for files to include in the list.
     *
     * @return string[]
     * @throws \CzProject\GitPhp\GitException
     */
    protected function getApplicationGitRepoStagedFiles(
        string $file_mask = ''
    ): array {
        return $this->getGitRepoStagedFiles(
            $this->curApplicationRepository,
            $file_mask
        );
    }

    /**
     * Retrieves changed files in the current application repository.
     *
     * @param string $file_mask
     *   An optional regex pattern for files to include in the list.
     *
     * @return string[]
     *   The changed files.
     *
     * @throws \CzProject\GitPhp\GitException
     */
    protected function getApplicationGitRepoChangedFiles(
        string $file_mask = ''
    ): array {
        return array_keys($this->getGitRepoChanges(
            $this->curApplicationRepository,
            $file_mask
        ));
    }
}
