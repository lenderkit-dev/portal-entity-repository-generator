<?php

declare(strict_types=1);

return [
    'output_structure' => [
        'models' => 'src/models/',
        'api_models_map' => 'src/api/',
    ],
    'filename' => [
        'models_template' => '{entity}Model.ts',
        'operation_map' => 'ApiOperationMap.ts',
    ],
    'filter_regex' => [
        'models' => '/^.*Resource$/',
    ],
    'generic_types' => [
        'boolean' => 'boolean',
        'array' => 'Array',
        'object' => 'object',
        'string' => 'string',
        'integer' => 'number',
        'number' => 'number',
        '$ref' => [
            'Date' => [
                'ts_type' => 'Date',
                'ts_import' => 'import type { Date } from \'@lk-core/model/component/Date\';',
            ],
            'DateTime' => [
                'ts_type' => 'DateTime',
                'ts_import' => 'import type { DateTime } from \'@lk-core/model/component/DateTime\';',
            ],
            'Money' => [
                'ts_type' => 'Money',
                'ts_import' => 'import type { Money } from \'@lk-core/model/component/Money\';',
            ],
            'Media' => [
                'ts_type' => 'Media',
                'ts_import' => 'import type { Media } from \'@lk-core/model/component/Media\';',
            ],
        ],
    ],
];
