<?php

declare(strict_types=1);

namespace app;

class TsGenerator
{
    public const OP_API_OPERATION_MAP = 'api_operation_map';
    public const OP_MODEL = 'model';
    public const OP_GENERAL = 'general';
    public const GEN_TYPE_OPERATION = 'operation';
    public const GEN_TYPE_MODEL_BASE = 'model_base';
    public const GEN_TYPE_MODEL = 'model';
    public const GEN_TYPE_MODEL_TRANSLATION = 'model_translation';

    private string $alias;

    public function __construct(
        private Config $config,
        private string $output,
    ) {
        $this->alias = $this->getAlias($output);
    }

    public static function allowedTypes(): array
    {
        return [
            static::OP_API_OPERATION_MAP,
            static::OP_MODEL,
        ];
    }

    public function generate(array $data, string $type, array $modules): void
    {
        match ($type) {
            static::OP_API_OPERATION_MAP => $this->generateApiOperationMap($data, $modules),
            static::OP_MODEL => $this->generateModel($data, $modules),
        };
    }

    protected function generateApiOperationMap(array $data, array $modules): void
    {
        printInfo('Generating ApiOperationMap...');

        $outputStructure = $this->config->getOutputStructure(static::GEN_TYPE_OPERATION);

        foreach ($modules as $module) {
            printInfo("Generating ApiOperationMap for module '{$module}'");

            $paths = $data['paths'] ?? [];
            $operationItems = '';
            $operationItemsTemplate = file_get_contents('stubs/api_operation_map/api_operation_map_item.stub');

            foreach ($paths as $path => $pathInfo) {
                if (! is_array($pathInfo)) {
                    continue;
                }

                foreach ($pathInfo as $method => $info) {
                    printInfo("Mapping path '{$path}', method '{$method}'...");

                    if ($module && (! isset($info['x-modules']) || ! in_array($module, $info['x-modules']))) {
                        printInfo(
                            "The 'x-modules' not contain provided module: path '{$path}', method '{$method}', skipping...",
                        );

                        continue;
                    }

                    $operationId = $info['operationId'];

                    if (! $operationId || str_contains($operationId, ':') || str_contains($operationId, '.')) {
                        printWarning(
                            "Operation Id not exist or wrong format: path '{$path}', id '{$operationId}', skipping...",
                        );

                        continue;
                    }

                    $responseType = $this->getResponseType($data, array_shift($info['responses']));

                    if (! $responseType) {
                        printWarning("Can't resolve response type for '{$operationId}': '{$path}' '{$method}'. Setting 'collection' type by default");
                        $responseType = 'collection';
                    }

                    $operationItems .= strtr(
                        $operationItemsTemplate,
                        [
                            '{operationId}' => $operationId,
                            '{url}' => $path,
                            '{method}' => strtoupper($method),
                            '{responseType}' => $responseType,
                        ],
                    );
                }
            }

            $moduleName = toCamelCase($module);
            $filename = str_replace('{module}', $moduleName, $this->config->getFilename('operation_map_template'));
            $content = strtr(file_get_contents('stubs/api_operation_map/api_operation_map.stub'), [
                '{operationMapItems}' => $operationItems,
                '{operationMapItemsName}' => basename($filename, '.ts'),
            ]);
            $path = "{$this->output}{$outputStructure}{$filename}";
            $this->saveFile($path, $content);

            printSuccess("ApiOperationMap has been generated to '{$path}'.");
        }

        $this->generateIndex(static::GEN_TYPE_OPERATION);
    }

    protected function getAlias(string $source): string
    {
        $modulePackageJson = json_decode(
            file_exists("{$source}package.json") ? file_get_contents("{$source}package.json") : '',
            true,
        );
        $alias = $modulePackageJson['config']['alias'] ?? '';

        if (! $alias) {
            printAbort("Please check that package.json exist in path '{$source}' and it has config 'alias'.");
        }

        return $alias;
    }

    protected function generateModel(array $data, array $modules): void
    {
        printInfo('Generating models...');

        $models = array_filter(
            $data['components']['schemas'] ?? [],
            fn($key) => preg_match($this->config->getFilterRegex('models'), $key),
            ARRAY_FILTER_USE_KEY,
        );
        $genericTypesDefaults = $this->config->getGenericTypesDefaults();
        $notNullableTypes = $this->config->getNotNullableTypes();
        $baseModelOutputStructure = $this->config->getOutputStructure(static::GEN_TYPE_MODEL_BASE);
        $mainModelOutputStructure = $this->config->getOutputStructure(static::GEN_TYPE_MODEL);
        $translateModelOutputStructure = $this->config->getOutputStructure(static::GEN_TYPE_MODEL_TRANSLATION);

        foreach ($models as $model => $modelData) {
            printInfo("Generating model {$model}...");

            $modelModule = $modelData['x-module'] ?? '';

            if (! $modelModule) {
                printWarning("Model {$model} missing 'x-module' property, skipping...");

                continue;
            }

            if ($modules && ! in_array($modelModule, $modules)) {
                printInfo(
                    "The 'x-module' not match with provided modules: model {$model}, model module '{$modelModule}', skipping...",
                );

                continue;
            }

            $additionalImport = [];
            $additionalProperties = '';
            $properties = $modelData['properties']['attributes']['properties'] ?? [];
            $modelType = $modelData['properties']['type']['type'] ?? '';

            $modelTypeValue = $modelData['properties']['type']['default'] ?? '';

            if (! $modelType || ! $modelTypeValue) {
                printWarning("Model {$model} missing type or type default value. Skipping...");

                continue;
            }

            $requiredProperties = $modelData['properties']['attributes']['required'] ?? [];
            $enums = [];

            foreach ($properties as $propertyName => $propertyInfo) {
                if (! isset($propertyInfo['x-module']) || $propertyInfo['x-module'] !== $modelModule) {
                    continue;
                }
                if (isset($propertyInfo['enum'])) {
                    $enums[$propertyName] = $propertyInfo['enum'];
                }

                [$propertyType, $addImport] = $this->getPropertyType($propertyInfo);
                $additionalImport = array_merge($additionalImport, $addImport);

                if (isset($propertyInfo['default'])) {
                    $defaultValue = 'string' === $propertyType
                        ? "'{$propertyInfo['default']}'"
                        : $propertyInfo['default'];
                    $nullableType = false;
                } else {
                    $schemaType = $this->getSchemaType($propertyInfo);
                    $defaultValue = match (true) {
                        array_key_exists($schemaType, $genericTypesDefaults) => $genericTypesDefaults[$schemaType],
                        array_key_exists($propertyType, $genericTypesDefaults) => $genericTypesDefaults[$propertyType],
                        default => 'null',
                    };
                    $nullableType = ! in_array($schemaType, $notNullableTypes)
                        || ! in_array($propertyType, $notNullableTypes);
                }
                $nullable = $nullableType && (! in_array($propertyName, $requiredProperties) || $defaultValue === 'null');

                $additionalProperties .= strtr(file_get_contents('stubs/model/model_property.stub'), [
                    '{name}' => $propertyName,
                    '{type}' => $propertyType,
                    '{nullable}' => $nullable ? ' | null' : '',
                    '{default}' => " = {$defaultValue}",
                ]);
            }

            $modelName = preg_replace($this->config->getFilterRegex('models'), '', $model);
            $baseModelContent = strtr(file_get_contents('stubs/model/base_model.stub'), [
                '{additionalImport}' => $additionalImport ? implode(PHP_EOL, $additionalImport) . PHP_EOL : '',
                '{modelName}' => $modelName,
                '{modelType}' => $modelType,
                '{modelTypeValue}' => $modelTypeValue,
                '{properties}' => rtrim($additionalProperties),
            ]);

            $filename = str_replace(
                '{entity}',
                $modelName,
                $this->config->getFilename('models_template'),
            );
            $baseModelPath = "{$this->output}{$baseModelOutputStructure}{$filename}";
            $this->saveFile($baseModelPath, $baseModelContent);
            printSuccess("Base model has been generated to '{$baseModelPath}'.");

            // Main model
            $relations = $modelData['properties']['relationships']['properties'] ?? [];
            $relationsContent = '';

            foreach ($relations as $relationKey => $relation) {
                $isMultipleRelation = $relation['properties']['data']['type'] !== 'object';
                $relationType = match (true) {
                    isset($relation['properties']['data']['items']['properties']['type']['default']) => $relation['properties']['data']['items']['properties']['type']['default'],
                    isset($relation['properties']['data']['properties']['type']['default']) => $relation['properties']['data']['properties']['type']['default'],
                    default => null,
                };

                // skip morph relations
                if (!$relationType) {
                    continue;
                }

                $relationType = pluralToSingular($relationType);

                $relationsContent .= strtr(file_get_contents('stubs/model/relation.stub'), [
                    '{relation}' => $relationKey,
                    '{relationMethod}' => toCamelCase($relationKey),
                    '{relationType}' => $isMultipleRelation ? 'Relations' : 'Relation',
                    '{relationModel}' => toPascalCase($relationType),
                    '{relationId}' => "{$relationType}_id",
                ]) . PHP_EOL;
            }

            $mainModelContent = strtr(file_get_contents('stubs/model/model.stub'), [
                '{modelName}' => $modelName,
                '{alias}' => $this->alias,
                '{path}' => $baseModelOutputStructure,
                '{importModel}' => $relationsContent ? file_get_contents('stubs/model/model_import.stub') : '',
                '{relations}' => rtrim($relationsContent),
            ]);

            $mainModelPath = "{$this->output}{$mainModelOutputStructure}{$filename}";

            if (! file_exists($mainModelPath)) {
                $this->saveFile($mainModelPath, $mainModelContent);
                printSuccess("Model has been generated to '{$mainModelPath}'.");
            } else {
                printWarning("Model already exist '{$mainModelPath}'.");
            }

            // translate model
            $enumsContent = '';

            foreach ($enums as $propertyName => $values) {
                $attributeElements = '';

                foreach ($values as $value) {
                    if (! $value) {
                        continue;
                    }

                    $attributeElements .= strtr(file_get_contents('stubs/model/translation_attribute_item.stub'), [
                        '{value}' => $value,
                        '{title}' => ucfirst(str_replace('_', ' ', $value)),
                    ]);
                }

                $enumsContent .= strtr(file_get_contents('stubs/model/translation_attribute.stub'), [
                    '{attributeTitle}' => $propertyName,
                    '{attributeElements}' => rtrim($attributeElements),
                ]);
            }

            $translateModelContent = strtr(file_get_contents('stubs/model/model_translations.stub'), [
                '{translations}' => rtrim($enumsContent),
            ]);

            $translateModelPath = "{$this->output}{$translateModelOutputStructure}{$filename}";

            if (! file_exists($translateModelPath)) {
                $this->saveFile($translateModelPath, $translateModelContent);
                printSuccess("Model translations has been generated to '{$translateModelPath}'.");
            } else {
                printWarning("Model translations already exist '{$translateModelPath}'.");
            }
        }

        $this->generateIndex(static::GEN_TYPE_MODEL_BASE);
        $this->generateIndex(static::GEN_TYPE_MODEL);
        $this->generateIndex(static::GEN_TYPE_MODEL_TRANSLATION);
    }

    protected function getResponseType(array $data, string|array $response): string
    {
        $resource = 'resource';
        $collection = 'collection';

        if (is_string($response)) {
            return $this->getResponseType($data, $this->resolveRef($data, $response));
        }

        if (isset($response['content']['application/json']['schema'])) {
            $schema = $response['content']['application/json']['schema'];

            if (isset($schema['type'])) {
                return $schema['type'] === 'object' ? $resource : $collection;
            } elseif (isset($schema['anyOf'])) {
                return '';
            } elseif (isset($schema['allOf'])) {
                $allOffResponse = array_pop($schema['allOf']);

                if (isset($allOffResponse['properties']['data']['type'])) {
                    return $allOffResponse['properties']['data']['type'] === 'object' ? $resource : $collection;
                } elseif (isset($allOffResponse['properties']['data']['$ref'])) {
                    if (preg_match('/^.*Resource$/', $allOffResponse['properties']['data']['$ref'])) {
                        return $resource;
                    } elseif (preg_match('/^.*Collection$/', $allOffResponse['properties']['data']['$ref'])) {
                        return $collection;
                    }
                }
            } elseif (isset($schema['$ref'])) {
                return $this->getResponseType($data, $schema['$ref']);
            }
        }

        return '';
    }

    protected function resolveRef(array $source, string $path): array
    {
        $keys = explode('/', trim($path, '#/'));

        foreach ($keys as $key) {
            if (isset($source[$key])) {
                $source = $source[$key];
            } else {
                printError("Can't resolve ref: '{$path}'");

                return [];
            }
        }

        return $source;
    }

    protected function getSchemaType(array $propertyInfo): string
    {
        $type = '';
        if (isset($propertyInfo['type'])) {
            $type = is_array($propertyInfo['type']) ? array_shift($propertyInfo['type']) : $propertyInfo['type'];
        }

        return $type;
    }

    protected function getPropertyType(array $propertyInfo): array
    {
        $additionalImport = [];
        $propertyType = '';

        if (isset($propertyInfo['type'])) {
            $type = $this->getSchemaType($propertyInfo);

            if ($type === 'array' && isset($propertyInfo['items'])) {
                [$itemType, $additionalImport] = $this->getPropertyType($propertyInfo['items']);
                $propertyType = "{$itemType}[]";
            } else {
                $propertyType = $this->config->getGenericTypes($type);
            }

            if (! $propertyType) {
                printAbort("Undefined property type '{$type}'. Please check config!");
            }
        } elseif (isset($propertyInfo['$ref'])) {
            $typeConfigs = $this->config->getGenericTypes('$ref');

            $pos = strrpos($propertyInfo['$ref'], '/');
            $type = $pos !== false ? substr($propertyInfo['$ref'], $pos + 1) : $propertyInfo['$ref'];

            if (! isset($typeConfigs[$type])) {
                printAbort("Undefined \$ref '{$type}'. Please check config!");
            }

            $propertyType = $typeConfigs[$type]['ts_type'];
            $additionalImport[$propertyType] = $typeConfigs[$type]['ts_import'];
        } elseif (isset($propertyInfo['anyOf'])) {
            foreach ($propertyInfo['anyOf'] as $key => $anyOf) {
                if ($anyOf === 'null') {
                    continue;
                }

                [$propertyType, $additionalImport] = $this->getPropertyType($propertyInfo['anyOf'][$key]);
                break;
            }
        }

        if (! $propertyType) {
            printLine('Property info: ' . json_encode($propertyInfo));
            printAbort('Undefined property type. Please check source!');
        }

        return [$propertyType, $additionalImport];
    }

    protected function generateIndex(string $type): void
    {
        $outputStructure = $this->config->getOutputStructure($type);
        $directory = "{$this->output}{$outputStructure}";
        $files = scandir($directory);
        $indexPath = "{$directory}index.ts";
        $models = array_map(
            static fn($file): string => basename($file, '.ts'),
            array_filter($files, static fn($file): bool => ! in_array($file, [
                '.',
                '..',
                'index.ts',
            ])),
        );

        $content = '';
        $contentDefaults = '';
        $contentTemplate = match ($type) {
            static::GEN_TYPE_OPERATION => "import { {modelName} } from '@lk-{alias}/{path}{modelName}';",
            static::GEN_TYPE_MODEL_BASE, static::GEN_TYPE_MODEL => "export * from '@lk-{alias}/{path}{modelName}';",
            static::GEN_TYPE_MODEL_TRANSLATION => "import { default as {modelName} } from '@lk-{alias}/{path}{modelName}';",
        };

        foreach ($models as $model) {
            $content .= strtr($contentTemplate, [
                '{alias}' => $this->alias,
                '{path}' => $outputStructure,
                '{modelName}' => $model,
            ]) . PHP_EOL;
            $contentDefaults .= match ($type) {
                static::GEN_TYPE_OPERATION => "  ...{$model}," . PHP_EOL,
                static::GEN_TYPE_MODEL_TRANSLATION => "  {$model}," . PHP_EOL,
                default => '',
            };
        }

        if ($contentDefaults) {
            $content .= strtr(file_get_contents('stubs/index_defaults.stub'), [
                '{defaults}' => rtrim($contentDefaults),
            ]);
        }

        $this->saveFile($indexPath, $content);
        printSuccess("Index has been generated in '{$indexPath}'.");
    }

    protected function saveFile(string $path, string $data): void
    {
        $directory = dirname($path);

        if (!is_dir($directory)) {
            printInfo("Creating directory '{$directory}'");
            mkdir($directory, 0755, true);
        }

        file_put_contents($path, $data);
    }
}
