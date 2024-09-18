<?php

declare(strict_types=1);

namespace app;

class Config
{
    private array $output_structure;

    private array $generic_types;

    private array $filter_regex;

    private array $filename;

    public function __construct($configs)
    {
        $this->output_structure = array_map(
            static fn($value): string => ! str_ends_with($value, '/') ? "{$value}/" : $value,
            $configs['output_structure'],
        );

        $this->generic_types = $configs['generic_types'];
        $this->filter_regex = $configs['filter_regex'];
        $this->filename = $configs['filename'];
    }

    public function getOutputStructure(string $type): string
    {
        return $this->output_structure[$type];
    }

    public function getGenericTypes(string $type): array|string
    {
        return $this->generic_types[$type] ?? [];
    }

    public function getFilterRegex(string $type): string
    {
        return $this->filter_regex[$type];
    }

    public function getFilename(string $type): string
    {
        return $this->filename[$type];
    }
}
