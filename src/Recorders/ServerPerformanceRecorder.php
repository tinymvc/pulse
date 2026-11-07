<?php

namespace Spark\Pulse\Recorders;

use function count;
use function floatval;

/**
 * Cross-Platform Server & Runtime Performance Recorder.
 *
 * Samples CPU, Memory, Disk, and System Load across Linux, macOS, and Windows.
 *
 * @package Spark\Pulse\Recorders
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
class ServerPerformanceRecorder
{
    /** @var array|null Cached sample data */
    private static ?array $cachedSample = null;

    /** @var float Timestamp of last full sample */
    private static float $cachedAt = 0.0;

    /** @var float Cache TTL in seconds for full sample */
    public static float $cacheTtl = 2.0;

    /** @var float|null Cached CPU usage */
    private static ?float $cachedCpu = null;

    /** @var float Timestamp of last CPU sample */
    private static float $cachedCpuAt = 0.0;

    /** First line of /proc/stat from the previous sample, in scheduler ticks. */
    private static ?array $cpuTicks = null;

    /**
     * Clear all cached performance metrics.
     */
    public static function clearCache(): void
    {
        self::$cachedSample = null;
        self::$cachedAt = 0.0;
        self::$cachedCpu = null;
        self::$cachedCpuAt = 0.0;
        self::$cpuTicks = null;
    }

    /**
     * Sample all server and application performance metrics.
     *
     * @param bool $fresh
     * @return array
     */
    public static function sample(bool $fresh = false): array
    {
        $now = microtime(true);
        if (!$fresh && self::$cachedSample !== null && ($now - self::$cachedAt) < self::$cacheTtl) {
            return self::$cachedSample;
        }

        self::$cachedSample = [
            'cpu' => self::sampleCpu($fresh),
            'memory' => self::sampleMemory(),
            'disk' => self::sampleDisk(),
            'load' => self::sampleLoad(),
            'runtime' => self::sampleRuntime(),
            'sampled_at' => gmdate('Y-m-d\TH:i:s.v\Z'),
        ];
        self::$cachedAt = $now;

        return self::$cachedSample;
    }

    /**
     * Sample CPU utilization percentage.
     *
     * @param bool $fresh
     * @return array{usage_percent: ?float, cores: ?int}
     */
    public static function sampleCpu(bool $fresh = false): array
    {
        $now = microtime(true);
        if (!$fresh && self::$cachedCpu !== null && ($now - self::$cachedCpuAt) < 3.0) {
            return [
                'usage_percent' => self::$cachedCpu,
                'cores' => self::getCpuCores(),
            ];
        }

        $usage = null;
        if (is_readable('/proc/stat')) {
            $line = file_get_contents('/proc/stat', false, null, 0, 256);
            if ($line && preg_match('/^cpu\\s+([0-9 ]+)/', $line, $matches)) {
                // Guest ticks are already included in the user and nice counters.
                $fields = array_map('intval', preg_split('/\\s+/', trim($matches[1])));
                $ticks = [array_sum(array_slice($fields, 0, 8)), ($fields[3] ?? 0) + ($fields[4] ?? 0)];
                if (self::$cpuTicks !== null && ($elapsed = $ticks[0] - self::$cpuTicks[0]) > 0) {
                    $busy = 1 - ($ticks[1] - self::$cpuTicks[1]) / $elapsed;
                    $usage = round(100 * max(0, min(1, $busy)), 1);
                }
                self::$cpuTicks = $ticks;
            }
        }
        self::$cachedCpu = $usage;
        self::$cachedCpuAt = $now;

        return [
            'usage_percent' => $usage,
            'cores' => self::getCpuCores(),
        ];
    }

    /**
     * Sample Memory utilization.
     *
     * @return array
     */
    public static function sampleMemory(): array
    {
        $appMemory = memory_get_usage(true);
        $peakMemory = memory_get_peak_usage(true);
        $totalMemory = null;
        $freeMemory = null;
        if (is_readable('/proc/meminfo') && ($info = file_get_contents('/proc/meminfo'))) {
            if (preg_match('/^MemTotal:\\s+(\\d+) kB/m', $info, $total)) {
                $totalMemory = (int) $total[1] * 1024;
            }
            if (preg_match('/^MemAvailable:\\s+(\\d+) kB/m', $info, $available)) {
                $freeMemory = (int) $available[1] * 1024;
            }
        }
        $usedMemory = $totalMemory !== null && $freeMemory !== null ? max(0, $totalMemory - $freeMemory) : null;
        $usagePercent = $totalMemory && $usedMemory !== null ? round(100 * $usedMemory / $totalMemory, 1) : null;

        return [
            'app_bytes' => $appMemory,
            'app_peak_bytes' => $peakMemory,
            'app_formatted' => self::formatBytes($appMemory),
            'app_peak_formatted' => self::formatBytes($peakMemory),
            'total_bytes' => $totalMemory,
            'total_formatted' => $totalMemory !== null ? self::formatBytes($totalMemory) : null,
            'used_bytes' => $usedMemory,
            'used_formatted' => $usedMemory !== null ? self::formatBytes($usedMemory) : null,
            'free_bytes' => $freeMemory,
            'free_formatted' => $freeMemory !== null ? self::formatBytes($freeMemory) : null,
            'usage_percent' => $usagePercent,
        ];
    }

    /**
     * Sample Disk storage utilization.
     *
     * @return array
     */
    public static function sampleDisk(): array
    {
        $path = function_exists('storage_dir') ? storage_dir() : getcwd();
        $total = @disk_total_space($path);
        $free = @disk_free_space($path);
        $used = $total !== false && $free !== false ? max(0, $total - $free) : null;
        $usagePercent = $total > 0 && $used !== null ? round(($used / $total) * 100, 1) : null;

        return [
            'total_bytes' => $total !== false ? $total : null,
            'total_formatted' => $total !== false ? self::formatBytes($total) : null,
            'free_bytes' => $free !== false ? $free : null,
            'free_formatted' => $free !== false ? self::formatBytes($free) : null,
            'used_bytes' => $used,
            'used_formatted' => $used !== null ? self::formatBytes($used) : null,
            'usage_percent' => $usagePercent,
            'path' => $path,
        ];
    }

    /**
     * Sample system load averages (1m, 5m, 15m).
     *
     * @return array{1m: ?float, 5m: ?float, 15m: ?float}
     */
    public static function sampleLoad(): array
    {
        if (function_exists('sys_getloadavg')) {
            $loads = sys_getloadavg();
            if ($loads !== false && count($loads) >= 3) {
                return [
                    '1m' => round($loads[0], 2),
                    '5m' => round($loads[1], 2),
                    '15m' => round($loads[2], 2),
                ];
            }
        }

        return ['1m' => null, '5m' => null, '15m' => null];
    }

    /**
     * Sample runtime environment metadata.
     *
     * @return array
     */
    public static function sampleRuntime(): array
    {
        return [
            'os' => PHP_OS . ' (' . PHP_OS_FAMILY . ')',
            'php_version' => PHP_VERSION,
            'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? (PHP_SAPI === 'cli' ? 'CLI (' . php_sapi_name() . ')' : 'Web Server'),
            'spark_version' => \Composer\InstalledVersions::getPrettyVersion('tinymvc/tinycore') ?: \Spark\Foundation\Application::VERSION,
            'app_env' => config('app.env', env('APP_ENV', 'production')),
            'app_debug' => (bool) config('app.debug', false),
            'uptime_seconds' => self::getSystemUptime(),
        ];
    }

    private static function getCpuCores(): ?int
    {
        if (PHP_OS_FAMILY === 'Windows') {
            $cores = getenv('NUMBER_OF_PROCESSORS');
            if ($cores && is_numeric($cores)) {
                return (int) $cores;
            }
        } elseif (is_readable('/proc/cpuinfo')) {
            $cpuinfo = @file_get_contents('/proc/cpuinfo');
            if ($cpuinfo) {
                return max(1, substr_count($cpuinfo, 'processor'));
            }
        }
        return null;
    }

    private static function getSystemUptime(): ?int
    {
        if (PHP_OS_FAMILY === 'Linux' && is_readable('/proc/uptime')) {
            $uptime = @file_get_contents('/proc/uptime');
            if ($uptime) {
                return (int) floatval(explode(' ', $uptime)[0]);
            }
        }
        return null;
    }

    public static function formatBytes(int|float $bytes, int $precision = 1): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max($bytes, 0);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= (1 << (10 * $pow));

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    public static function formatByteChange(int $bytes): string
    {
        $sign = $bytes < 0 ? '−' : ($bytes > 0 ? '+' : '');
        return $sign . self::formatBytes(abs($bytes));
    }

    public static function formatDuration(float $milliseconds): string
    {
        return $milliseconds < 1
            ? round($milliseconds * 1000, 1) . ' µs'
            : round($milliseconds, 2) . ' ms';
    }

    public static function parseBytes(string $val): int
    {
        $val = trim($val);
        $last = strtolower(substr($val, -1));
        $val = (int) $val;
        return match ($last) {
            'g' => $val * 1024 * 1024 * 1024,
            'm' => $val * 1024 * 1024,
            'k' => $val * 1024,
            default => $val,
        };
    }
}
