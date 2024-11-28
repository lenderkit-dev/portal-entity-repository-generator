<?php

declare(strict_types=1);

return [
    'output_structure' => [
        'model_base' => 'src/api/models/',
        'model' => 'src/models/',
        'model_translation' => 'src/i18n/en/models/',
        'operation' => 'src/api/operations/',
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
                'ts_type' => 'DateAttribute',
                'ts_import' => 'import type { DateAttribute } from \'@lk-framework/src/models/types\';',
            ],
            'DateTime' => [
                'ts_type' => 'DateTimeAttribute',
                'ts_import' => 'import type { DateTimeAttribute } from \'@lk-framework/src/models/types\';',
            ],
            'Money' => [
                'ts_type' => 'MoneyAttribute',
                'ts_import' => 'import type { MoneyAttribute } from \'@lk-framework/src/models/types\';',
            ],
            'Media' => [
                'ts_type' => 'MediaAttribute',
                'ts_import' => 'import type { MediaAttribute } from \'@lk-framework/src/models/types\';',
            ],
        ],
    ],
    'generic_types_defaults' => [
        'boolean' => 'false',
        'string' => "''",
        'number' => '0',
    ],
];
