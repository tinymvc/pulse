<?php

namespace Spark\Pulse\Recorders;

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
    /**
     * Sample all server and application performance metrics.
     *
     * @return array
     */
    public static function sample(): array
    {
        return [
            'cpu' => self::sampleCpu(),
            'memory' => self::sampleMemory(),
            'disk' => self::sampleDisk(),
            'load' => self::sampleLoad(),
            'runtime' => self::sampleRuntime(),
            'sampled_at' => gmdate('Y-m-d\TH:i:s.v\Z'),
        ];
    }

    /**
     * Sample CPU utilization percentage.
     *
     * @return array{usage_percent: float}
     */
    public static function sampleCpu(): array
    {
        $usage = 0.0;

        if (PHP_OS_FAMILY === 'Windows') {
            // Windows CPU sample via wmic or PowerShell if available
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
                // Approximate 1-core % from 1-min load average
                $cores = self::getCpuCores();
                $usage = min(100.0, round(($loads[0] / max(1, $cores)) * 100, 1));
            }
        }

        return [
            'usage_percent' => max(0.0, min(100.0, $usage)),
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
        $totalMemory = 0;
        $freeMemory = 0;

        if (PHP_OS_FAMILY === 'Linux' && is_readable('/proc/meminfo')) {
            $meminfo = @file_get_contents('/proc/meminfo');
            if ($meminfo) {
                if (preg_match('/MemTotal:\s+(\d+)\s+kB/', $meminfo, $totalMatches)) {
                    $totalMemory = (int) $totalMatches[1] * 1024;
                }
                if (preg_match('/MemAvailable:\s+(\d+)\s+kB/', $meminfo, $freeMatches)) {
                    $freeMemory = (int) $freeMatches[1] * 1024;
                }
            }
        } elseif (PHP_OS_FAMILY === 'Windows' && function_exists('shell_exec')) {
            try {
                $output = @shell_exec('wmic OS get FreePhysicalMemory,TotalVisibleMemorySize /Value 2>&1');
                if ($output) {
                    if (preg_match('/TotalVisibleMemorySize=(\d+)/i', $output, $m1)) {
                        $totalMemory = (int) $m1[1] * 1024;
                    }
                    if (preg_match('/FreePhysicalMemory=(\d+)/i', $output, $m2)) {
                        $freeMemory = (int) $m2[1] * 1024;
                    }
                }
            } catch (\Throwable) {
                // Fallback to PHP limit
            }
        }

        // If OS total memory could not be resolved, fallback to ini memory_limit
        if ($totalMemory <= 0) {
            $iniLimit = ini_get('memory_limit');
            $totalMemory = self::parseBytes($iniLimit ?: '512M');
            $freeMemory = max(0, $totalMemory - $appMemory);
        }

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
            'spark_version' => defined('\Spark\Foundation\Application::VERSION') ? \Spark\Foundation\Application::VERSION : '4.x',
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
