<?php

namespace Dockworker\Regression;

/**
 * Severity levels for a regression check result.
 *
 * Fail: the check identified a condition that must be corrected before the
 * managed application can be deployed or continue. The orchestrator surfaces
 * Fail results as errors and aborts with a non-zero exit.
 *
 * Warn: the check identified drift from a recommended convention but the
 * application can still proceed. The orchestrator surfaces Warn results as
 * warnings and exits zero.
 */
enum RegressionCheckSeverity: string
{
    case Fail = 'fail';
    case Warn = 'warn';
}
