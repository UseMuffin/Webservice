<?php
declare(strict_types=1);

namespace TestApp\Webservice;

use Psr\Log\LoggerInterface;
use Stringable;

/**
 * @package MuffinWebservice
 * @author David Yell <dyell@ukwebmedia.com>
 * @copyright UK Web Media Ltd
 */
class Logger implements LoggerInterface
{
    /**
     * System is unusable.
     *
     * @param \Stringable|string $message
     * @param array $context
     * @return void
     */
    public function emergency(string|Stringable $message, array $context = []): void
    {
    }

    /**
     * Action must be taken immediately.
     *
     * Example: Entire website down, database unavailable, etc. This should
     * trigger the SMS alerts and wake you up.
     *
     * @param \Stringable|string $message
     * @param array $context
     * @return void
     */
    public function alert(string|Stringable $message, array $context = []): void
    {
    }

    /**
     * Critical conditions.
     *
     * Example: Application component unavailable, unexpected exception.
     *
     * @param \Stringable|string $message
     * @param array $context
     * @return void
     */
    public function critical(string|Stringable $message, array $context = []): void
    {
    }

    /**
     * Runtime errors that do not require immediate action but should typically
     * be logged and monitored.
     *
     * @param \Stringable|string $message
     * @param array $context
     * @return void
     */
    public function error(string|Stringable $message, array $context = []): void
    {
    }

    /**
     * Exceptional occurrences that are not errors.
     *
     * Example: Use of deprecated APIs, poor use of an API, undesirable things
     * that are not necessarily wrong.
     *
     * @param string|\Stringable $message
     * @param array $context
     * @return void
     */
    public function warning(string|Stringable $message, array $context = []): void
    {
    }

    /**
     * Normal but significant events.
     *
     * @param \Stringable|string $message
     * @param array $context
     * @return void
     */
    public function notice(string|Stringable $message, array $context = []): void
    {
    }

    /**
     * Interesting events.
     *
     * Example: User logs in, SQL logs.
     *
     * @param \Stringable|string $message
     * @param array $context
     * @return void
     */
    public function info(string|Stringable $message, array $context = []): void
    {
    }

    /**
     * Detailed debug information.
     *
     * @param \Stringable|string $message
     * @param array $context
     * @return void
     */
    public function debug(string|Stringable $message, array $context = []): void
    {
    }

    /**
     * Logs with an arbitrary level.
     *
     * @param mixed $level
     * @param \Stringable|string $message
     * @param array $context
     * @return void
     */
    public function log($level, string|Stringable $message, array $context = []): void
    {
    }
}
