<?php

declare(strict_types=1);

namespace RoundlyConsulting\Metrics\Exceptions;

/**
 * Thrown when a `metrics.*` config value is present but unusable (e.g. a
 * `METRICS_CACHE_TTL` that is not a whole number of seconds).
 */
final class InvalidConfigurationException extends MetricsException {}
