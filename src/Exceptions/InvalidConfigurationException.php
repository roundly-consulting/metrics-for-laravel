<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Exceptions;

/**
 * Thrown when a `metrics.*` config value is present but unusable (e.g. a
 * `METRICS_CACHE_TTL` that is not a whole number of seconds, or a
 * `METRICS_CACHE_ENABLED` that is not a boolean spelling).
 */
final class InvalidConfigurationException extends MetricsException {}
