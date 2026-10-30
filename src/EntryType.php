<?php

namespace Spark\Pulse;

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
}
