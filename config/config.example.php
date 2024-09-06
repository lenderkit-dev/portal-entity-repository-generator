<?php

declare(strict_types=1);

return [
    'output_structure' => [
        'models' => 'src/models/',
        'api_models_map' => 'src/api/',
    ],
    'generic_types' => [
        'boolean' => 'boolean',
        'array' => 'array',
        'object' => 'object',
        'string' => 'string',
        'integer' => 'number',
        '$ref' => [
            'Date' => [
                'ts_type' => 'Date',
                'ts_import' => 'import type { Date } from \'@lk-core/model/component/Date\';',
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
