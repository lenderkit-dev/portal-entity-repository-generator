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
    echo ANSI_WHITE . "{$message}\033[0m" . PHP_EOL;
}

function printInfo(string $message): void
{
    echo ANSI_BLUE . "{$message}\033[0m" . PHP_EOL;
}

function printSuccess(string $message): void
{
    echo ANSI_GREEN . "{$message}\033[0m" . PHP_EOL;
}

function printWarning(string $message): void
{
    echo ANSI_YELLOW . "{$message}\033[0m" . PHP_EOL;
}

function printError(string $message): void
{
    echo ANSI_RED . "{$message}\033[0m" . PHP_EOL;
}

function printAbort(string $message): void
{
    printError($message);
    exit(1);
}
