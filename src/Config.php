<?php

declare(strict_types=1);

namespace app;

class Config
{
    private array $output_structure;

    private array $generic_types;

    public function __construct($configs)
    {
        $this->output_structure = array_map(
            static fn($value): string => ! str_ends_with($value, '/') ? "{$value}/" : $value,
            $configs['output_structure'],
        );

        $this->generic_types = $configs['generic_types'];
    }

    public function getOutputStructure(): array
    {
        return $this->output_structure;
    }

    public function getGenericTypes(): array
    {
        return $this->generic_types;
    }
}
