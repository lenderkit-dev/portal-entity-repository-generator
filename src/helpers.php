<?php

declare(strict_types=1);

define('ANSI_RESET', "\033[0m");
define('ANSI_BLACK', "\033[0;30m");
define('ANSI_RED', "\033[0;31m");
define('ANSI_GREEN', "\033[0;32m");
define('ANSI_YELLOW', "\033[0;33m");
define('ANSI_BLUE', "\033[0;34m");
define('ANSI_MAGENTA', "\033[0;35m");
define('ANSI_CYAN', "\033[0;36m");
define('ANSI_WHITE', "\033[0;37m");

function printLine(string $message): void
{
    echo formatMessage(ANSI_WHITE, $message);
}

function printInfo(string $message): void
{
    echo formatMessage(ANSI_RESET, $message);
}

function printSuccess(string $message): void
{
    echo formatMessage(ANSI_GREEN, $message);
}

function printWarning(string $message): void
{
    echo formatMessage(ANSI_YELLOW, "    WARNING! {$message}");
}

function printError(string $message): void
{
    echo formatMessage(ANSI_RED, $message);
}

function printAbort(string $message): void
{
    printError($message);
    exit(1);
}

function formatMessage(string $color, string $message): string
{
    return "{$color}{$message}" . ANSI_RESET . PHP_EOL;
}

function pluralToSingular(string $word): string
{
    return match (true) {
        preg_match('/(ies)$/i', $word) === 1 => preg_replace('/(ies)$/i', 'y', $word),
        preg_match('/(es)$/i', $word) === 1 => preg_replace('/(es)$/i', '', $word),
        preg_match('/(s)$/i', $word) === 1 => preg_replace('/(s)$/i', '', $word),
        default => $word,
    };
}

function toCamelCase(string $string, string $separator = '_'): string
{
    return lcfirst(toPascalCase($string, $separator));
}

function toPascalCase(string $string, string $separator = '_'): string
{
    return implode('', array_map('ucfirst', explode($separator, $string)));
}
