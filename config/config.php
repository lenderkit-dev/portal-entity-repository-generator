<?php

declare(strict_types=1);

return [
    'output_structure' => [
        'base_models' => 'src/api/models/',
        'models' => 'src/models/',
        'model_translations' => 'src/i18n/en/models/',
        'api_models_map' => 'src/api/operations/',
    ],
    'filename' => [
        'models_template' => '{entity}.ts',
        'operation_map_template' => '{module}OperationsMap.ts',
    ],
    'filter_regex' => [
        'models' => '/Resource$/',
    ],
    'generic_types' => [
        'boolean' => 'boolean',
        'array' => 'any[]',
        'object' => 'Record<string, any>',
        'string' => 'string',
        'integer' => 'number',
        'number' => 'number',
        '$ref' => [
            'Date' => [
                'ts_type' => 'Date',
                'ts_import' => 'import type { Date } from \'@lk-framework/src/models\';',
            ],
            'DateTime' => [
                'ts_type' => 'DateTime',
                'ts_import' => 'import type { DateTime } from \'@lk-framework/src/models\';',
            ],
            'Money' => [
                'ts_type' => 'Money',
                'ts_import' => 'import type { Money } from \'@lk-framework/src/models\';',
            ],
            'Media' => [
                'ts_type' => 'Media',
                'ts_import' => 'import type { Media } from \'@lk-framework/src/models\';',
            ],
        ],
    ],
    'generic_types_defaults' => [
        'boolean' => 'false',
        'array' => '[]',
        'string' => "''",
        'number' => '0',
        'object' => '{}',
    ],
];
