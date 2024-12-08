<?php

declare(strict_types=1);

namespace app;

class Config
{
    private array $output_structure;

    private array $generic_types;

    private array $generic_types_defaults;

    private array $not_nullable_types;

    private array $filter_regex;

    private array $filename;

    public function __construct($configs)
    {
        $this->output_structure = array_map(
            static fn($value): string => ! str_ends_with($value, '/') ? "{$value}/" : $value,
            $configs['output_structure'],
        );

        $this->generic_types = $configs['generic_types'];
        $this->generic_types_defaults = $configs['generic_types_defaults'];
        $this->not_nullable_types = $configs['not_nullable_types'];
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

    public function getGenericTypesDefaults(): array
    {
        return $this->generic_types_defaults ?? [];
    }

    public function getNotNullableTypes(): array
    {
        return $this->not_nullable_types ?? [];
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
