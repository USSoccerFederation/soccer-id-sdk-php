<?php

namespace USSoccerFederation\UssfAuthSdkPhp\Logging;

use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;

class StdoutLogger implements LoggerInterface
{
    private const LOG_LEVELS = [
        LogLevel::EMERGENCY => 'EMERGENCY',
        LogLevel::ALERT => 'ALERT',
        LogLevel::CRITICAL => 'CRITICAL',
        LogLevel::ERROR => 'ERROR',
        LogLevel::WARNING => 'WARNING',
        LogLevel::NOTICE => 'NOTICE',
        LogLevel::INFO => 'INFO',
        LogLevel::DEBUG => 'DEBUG',
    ];

    public function emergency($message, array $context = []): void
    {
        $this->log(LogLevel::EMERGENCY, $message, $context);
    }

    public function log($level, $message, array $context = []): void
    {
        $levelName = self::LOG_LEVELS[$level] ?? 'UNKNOWN';
        $timestamp = date('Y-m-d H:i:s');
        $contextString = json_encode($context);

        echo sprintf("[%s] %s: %s %s\n", $timestamp, $levelName, $message, $contextString);
    }

    public function alert($message, array $context = []): void
    {
        $this->log(LogLevel::ALERT, $message, $context);
    }

    public function critical($message, array $context = []): void
    {
        $this->log(LogLevel::CRITICAL, $message, $context);
    }

    public function error($message, array $context = []): void
    {
        $this->log(LogLevel::ERROR, $message, $context);
    }

    public function warning($message, array $context = []): void
    {
        $this->log(LogLevel::WARNING, $message, $context);
    }

    public function notice($message, array $context = []): void
    {
        $this->log(LogLevel::NOTICE, $message, $context);
    }

    public function info($message, array $context = []): void
    {
        $this->log(LogLevel::INFO, $message, $context);
    }

    public function debug($message, array $context = []): void
    {
        $this->log(LogLevel::DEBUG, $message, $context);
    }
}