<?php

namespace Dockworker\Regression;

use Robo\Robo;

/**
 * Provides regression-check orchestration via the dockworker-regression-checks event.
 *
 * Consuming classes MUST also implement
 * Consolidation\AnnotatedCommand\Events\CustomEventAwareInterface and use
 * Consolidation\AnnotatedCommand\Events\CustomEventAwareTrait, and MUST extend
 * a class that provides the protected $applicationRoot property (typically a
 * subclass of Dockworker\DockworkerCommands).
 *
 * Downstream packages register checks by adding methods annotated with
 * "@hook on-event dockworker-regression-checks" that take a
 * RegressionCheckContext and return an array of RegressionCheckResult objects.
 *
 * Each handler runs in isolation: a handler that throws or returns malformed
 * data does not prevent other handlers from running; the orchestrator converts
 * such cases into a meta-FAIL result so the user sees the offending handler in
 * the regular report instead of a stack trace.
 */
trait RegressionCheckerTrait
{
    /**
     * Dispatches the regression-check event and collects results from all handlers.
     *
     * Subscribers register against the event name "dockworker-regression-checks"
     * via "@hook on-event dockworker-regression-checks".
     *
     * @return RegressionCheckResult[]
     */
    protected function getRegressionCheckResults(): array
    {
        $context = $this->buildRegressionCheckContext();
        $results = [];
        $handlers = $this->getCustomEventHandlers('dockworker-regression-checks');
        foreach ($handlers as $handler) {
            $label = $this->describeHandler($handler);
            try {
                $handlerResults = $handler($context);
            } catch (\Throwable $e) {
                $results[] = RegressionCheckResult::fail(
                    'orchestrator:handler-error:' . $label,
                    sprintf(
                        'Handler threw %s: %s',
                        $e::class,
                        $e->getMessage(),
                    ),
                );
                continue;
            }
            if (!is_array($handlerResults)) {
                $results[] = RegressionCheckResult::fail(
                    'orchestrator:bad-handler-return:' . $label,
                    sprintf(
                        'Handler did not return an array (got %s).',
                        get_debug_type($handlerResults),
                    ),
                );
                continue;
            }
            foreach ($handlerResults as $item) {
                if ($item instanceof RegressionCheckResult) {
                    $results[] = $item;
                    continue;
                }
                $results[] = RegressionCheckResult::fail(
                    'orchestrator:bad-handler-return:' . $label,
                    sprintf(
                        'Handler returned a non-RegressionCheckResult item (got %s).',
                        get_debug_type($item),
                    ),
                );
            }
        }
        return $results;
    }

    /**
     * Builds the context object passed to every handler.
     */
    private function buildRegressionCheckContext(): RegressionCheckContext
    {
        $config = Robo::config();
        $dockworkerConfig = $config->get('dockworker') ?? [];
        if (!is_array($dockworkerConfig)) {
            $dockworkerConfig = [];
        }
        $frameworkName = $config->get('dockworker.application.framework.name');
        $frameworkVersion = $config->get('dockworker.application.framework.version');
        return new RegressionCheckContext(
            applicationRoot: $this->applicationRoot,
            dockworkerConfig: $dockworkerConfig,
            frameworkName: is_string($frameworkName) ? $frameworkName : null,
            frameworkVersion: $frameworkVersion === null ? null : (string) $frameworkVersion,
        );
    }

    /**
     * Produces a human-readable label for a hook-manager-supplied callable.
     *
     * Hook handlers are typically returned as [object, methodName] arrays;
     * other callable shapes are handled defensively.
     *
     * @param callable $handler
     */
    private function describeHandler(callable $handler): string
    {
        if (is_array($handler) && count($handler) === 2) {
            $owner = $handler[0];
            $method = (string) $handler[1];
            $ownerLabel = is_object($owner) ? $owner::class : (string) $owner;
            return $ownerLabel . '::' . $method;
        }
        if (is_string($handler)) {
            return $handler;
        }
        if ($handler instanceof \Closure) {
            return 'Closure';
        }
        return 'anonymous';
    }
}
