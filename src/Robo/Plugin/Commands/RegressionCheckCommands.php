<?php

namespace Dockworker\Robo\Plugin\Commands;

use Consolidation\AnnotatedCommand\Events\CustomEventAwareInterface;
use Consolidation\AnnotatedCommand\Events\CustomEventAwareTrait;
use Dockworker\DockworkerApplicationCommands;
use Dockworker\DockworkerException;
use Dockworker\IO\DockworkerIOTrait;
use Dockworker\Regression\RegressionCheckResult;
use Dockworker\Regression\RegressionCheckSeverity;
use Dockworker\Regression\RegressionCheckerTrait;

/**
 * Runs regression checks registered by all installed dockworker packages.
 *
 * This class is the event dispatcher for "dockworker-regression-checks"; it
 * intentionally does not register any handlers of its own. Framework-specific
 * rules live in their respective extension packages (e.g. dockworker-drupal).
 */
class RegressionCheckCommands extends DockworkerApplicationCommands implements CustomEventAwareInterface
{
    use CustomEventAwareTrait;
    use DockworkerIOTrait;
    use RegressionCheckerTrait;

    /**
     * Runs all regression checks registered against the managed application.
     *
     * Prints warnings, then errors, then aborts with a non-zero exit if any
     * check returned a Fail-level result.
     *
     * @command validate:regressions
     * @aliases dockworker:validate:regressions
     *
     * @usage validate:regressions
     *
     * @throws \Dockworker\DockworkerException
     *   When one or more checks return a Fail-level result.
     */
    public function validateRegressions(): void
    {
        $results = $this->getRegressionCheckResults();

        $fails = [];
        $warns = [];
        foreach ($results as $result) {
            match ($result->severity) {
                RegressionCheckSeverity::Fail => $fails[] = $result,
                RegressionCheckSeverity::Warn => $warns[] = $result,
            };
        }

        foreach ($warns as $warn) {
            $this->dockworkerIO->warning($this->formatResult($warn));
        }
        foreach ($fails as $fail) {
            $this->dockworkerIO->error($this->formatResult($fail));
        }

        if (count($fails) > 0) {
            throw new DockworkerException(sprintf(
                'Regression checks failed: %d FAIL, %d WARN. Refusing to continue.',
                count($fails),
                count($warns),
            ));
        }

        if (count($warns) === 0) {
            $this->dockworkerIO->say('All regression checks passed.');
        }
    }

    /**
     * Formats a result for terminal display.
     */
    private function formatResult(RegressionCheckResult $result): string
    {
        $line = sprintf('[%s] %s', $result->checkId, $result->message);
        if ($result->remediation !== null && $result->remediation !== '') {
            $line .= "\n  " . $result->remediation;
        }
        return $line;
    }
}
