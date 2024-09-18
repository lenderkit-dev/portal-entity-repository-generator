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
    echo formatMessage(ANSI_BLUE, $message);
}

function printSuccess(string $message): void
{
    echo formatMessage(ANSI_GREEN, $message);
}

function printWarning(string $message): void
{
    echo formatMessage(ANSI_YELLOW, $message);
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
