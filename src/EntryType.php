<?php

namespace Spark\Pulse;

use function in_array;

/**
 * Enumeration of all supported Pulse entry types.
 *
 * @package Spark\Pulse
 * @author Shahin Moyshan <shahin.moyshan2@gmail.com>
 */
final class EntryType
{
    public const REQUEST = 'request';
    public const COMMAND = 'command';
    public const JOB = 'job';
    public const CACHE = 'cache';
    public const EVENT = 'event';
    public const EXCEPTION = 'exception';
    public const GATE = 'gate';
    public const HTTP_CLIENT = 'http_client';
    public const LOG = 'log';
    public const MAIL = 'mail';
    public const MODEL = 'model';
    public const QUERY = 'query';
    public const VIEW = 'view';
    public const PERFORMANCE = 'performance';

    /**
     * Get all supported entry types.
     *
     * @return string[]
     */
    public static function all(): array
    {
        return [
            self::REQUEST,
            self::COMMAND,
            self::JOB,
            self::CACHE,
            self::EVENT,
            self::EXCEPTION,
            self::GATE,
            self::HTTP_CLIENT,
            self::LOG,
            self::MAIL,
            self::MODEL,
            self::QUERY,
            self::VIEW,
            self::PERFORMANCE,
        ];
    }

    /**
     * Normalize an entry type string (handling plurals or aliases).
     *
     * @param string $type
     * @return string
     */
    public static function normalize(string $type): string
    {
        $type = strtolower(trim($type));

        return match ($type) {
            'requests' => self::REQUEST,
            'commands' => self::COMMAND,
            'jobs' => self::JOB,
            'events' => self::EVENT,
            'exceptions' => self::EXCEPTION,
            'gates' => self::GATE,
            'logs' => self::LOG,
            'queries' => self::QUERY,
            'views' => self::VIEW,
            'models' => self::MODEL,
            'http', 'http_clients', 'httpclient' => self::HTTP_CLIENT,
            default => $type,
        };
    }

    /**
     * Check if the given entry type is valid.
     *
     * @param string $type
     * @return bool
     */
    public static function isValid(string $type): bool
    {
        return in_array(self::normalize($type), self::all(), true);
    }
}
