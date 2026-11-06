<?php

namespace Spark\Pulse\Recorders;

use function count;
use function floatval;
use function strlen;

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

    /** @var int|null Cached total system memory in bytes */
    private static ?int $osTotalMemory = null;

    /**
     * Clear all cached performance metrics.
     */
    public static function clearCache(): void
    {
        self::$cachedSample = null;
        self::$cachedAt = 0.0;
        self::$cachedCpu = null;
        self::$cachedCpuAt = 0.0;
        self::$osTotalMemory = null;
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
     * @return array{usage_percent: float, cores: int}
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

        $usage = 0.0;

        if (PHP_OS_FAMILY === 'Windows') {
            try {
                if (function_exists('shell_exec')) {
                    $output = @shell_exec('wmic cpu get loadpercentage 2>&1');
                    if ($output && preg_match('/\d+/', $output, $matches)) {
                        $usage = (float) $matches[0];
                    }
                }
            } catch (\Throwable) {
                $usage = 0.0;
            }
        } elseif (function_exists('sys_getloadavg')) {
            $loads = sys_getloadavg();
            if ($loads !== false && isset($loads[0])) {
                $cores = self::getCpuCores();
                $usage = min(100.0, round(($loads[0] / max(1, $cores)) * 100, 1));
            }
        }

        $usage = max(0.0, min(100.0, $usage));
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
        $totalMemory = self::$osTotalMemory ?? 0;
        $freeMemory = 0;

        if ($totalMemory <= 0) {
            if (PHP_OS_FAMILY === 'Linux' && is_readable('/proc/meminfo')) {
                $meminfo = @file_get_contents('/proc/meminfo');
                if ($meminfo && preg_match('/MemTotal:\s+(\d+)\s+kB/', $meminfo, $totalMatches)) {
                    $totalMemory = (int) $totalMatches[1] * 1024;
                }
            } elseif (PHP_OS_FAMILY === 'Windows' && function_exists('shell_exec')) {
                try {
                    $output = @shell_exec('wmic OS get TotalVisibleMemorySize /Value 2>&1');
                    if ($output && preg_match('/TotalVisibleMemorySize=(\d+)/i', $output, $m1)) {
                        $totalMemory = (int) $m1[1] * 1024;
                    }
                } catch (\Throwable) {
                    // Fallback to PHP limit
                }
            }

            if ($totalMemory <= 0) {
                $iniLimit = ini_get('memory_limit');
                $totalMemory = self::parseBytes($iniLimit ?: '512M');
            }

            self::$osTotalMemory = $totalMemory;
        }

        $freeMemory = max(0, $totalMemory - $appMemory);
        $usedMemory = max(0, $totalMemory - $freeMemory);
        $usagePercent = $totalMemory > 0 ? round(($usedMemory / $totalMemory) * 100, 1) : 0;

        return [
            'app_bytes' => $appMemory,
            'app_peak_bytes' => $peakMemory,
            'app_formatted' => self::formatBytes($appMemory),
            'app_peak_formatted' => self::formatBytes($peakMemory),
            'total_bytes' => $totalMemory,
            'total_formatted' => self::formatBytes($totalMemory),
            'used_bytes' => $usedMemory,
            'used_formatted' => self::formatBytes($usedMemory),
            'free_bytes' => $freeMemory,
            'free_formatted' => self::formatBytes($freeMemory),
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
        $total = @disk_total_space($path) ?: 0;
        $free = @disk_free_space($path) ?: 0;
        $used = max(0, $total - $free);
        $usagePercent = $total > 0 ? round(($used / $total) * 100, 1) : 0;

        return [
            'total_bytes' => $total,
            'total_formatted' => self::formatBytes($total),
            'free_bytes' => $free,
            'free_formatted' => self::formatBytes($free),
            'used_bytes' => $used,
            'used_formatted' => self::formatBytes($used),
            'usage_percent' => $usagePercent,
            'path' => $path,
        ];
    }

    /**
     * Sample system load averages (1m, 5m, 15m).
     *
     * @return array{1m: float, 5m: float, 15m: float}
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

        return ['1m' => 0.0, '5m' => 0.0, '15m' => 0.0];
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
            'spark_version' => \Spark\Foundation\Application::VERSION,
            'app_env' => env('APP_ENV', 'local'),
            'app_debug' => (bool) config('app.debug', false),
            'uptime_seconds' => self::getSystemUptime(),
        ];
    }

    private static function getCpuCores(): int
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
        return 1;
    }

    private static function getSystemUptime(): int
    {
        if (PHP_OS_FAMILY === 'Linux' && is_readable('/proc/uptime')) {
            $uptime = @file_get_contents('/proc/uptime');
            if ($uptime) {
                return (int) floatval(explode(' ', $uptime)[0]);
            }
        }
        return 0;
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

    public static function parseBytes(string $val): int
    {
        $val = trim($val);
        $last = strtolower($val[strlen($val) - 1]);
        $val = (int) $val;
        return match ($last) {
            'g' => $val * 1024 * 1024 * 1024,
            'm' => $val * 1024 * 1024,
            'k' => $val * 1024,
            default => $val,
        };
    }
}
