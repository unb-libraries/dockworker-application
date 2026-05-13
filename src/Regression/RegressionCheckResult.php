<?php

namespace Dockworker\Regression;

/**
 * Immutable result produced by a regression check handler.
 *
 * This class is part of the stable public contract for the
 * dockworker-regression-checks event. Adding new optional fields is
 * backwards-compatible; renaming or removing fields is a breaking change.
 *
 * Check IDs follow the convention <package-short>:<area>:<rule> to avoid
 * collisions across packages, e.g. "drupal:compose:missing-mysql-service" or
 * "daemon:env:missing-required-var".
 */
final class RegressionCheckResult
{
    public function __construct(
        public readonly string $checkId,
        public readonly RegressionCheckSeverity $severity,
        public readonly string $message,
        public readonly ?string $remediation = null,
    ) {
    }

    public static function fail(
        string $checkId,
        string $message,
        ?string $remediation = null,
    ): self {
        return new self($checkId, RegressionCheckSeverity::Fail, $message, $remediation);
    }

    public static function warn(
        string $checkId,
        string $message,
        ?string $remediation = null,
    ): self {
        return new self($checkId, RegressionCheckSeverity::Warn, $message, $remediation);
    }
}
