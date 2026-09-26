# Changelog

All notable changes to `metrics-for-laravel` will be documented in this file.

## 1.0.0 - Unreleased

### Fixed

- `METRICS_CACHE_TTL` from `.env` is honoured: the digit string env hands over (`'600'`) was
  rejected as a non-int and the TTL silently stayed 300 seconds. The TTL is now validated
  (`1`–`31536000` seconds) and an unusable value throws the new
  `InvalidConfigurationException` (a `MetricsException`) instead of falling back.
- `METRICS_CACHE_ENABLED` is parsed as a boolean: `off`/`no` no longer switch caching on, and
  `php artisan about` no longer reports the cache as `OFF` for `METRICS_CACHE_ENABLED=1`.

