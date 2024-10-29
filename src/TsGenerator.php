<?php

declare(strict_types=1);

namespace app;

class TsGenerator
{
    public const OP_API_OPERATION_MAP = 'api_operation_map';
    public const OP_MODEL = 'model';
    public const OP_GENERAL = 'general';

    public function __construct(
        private Config $config,
    ) {}

    public static function allowedTypes(): array
    {
        return [
            static::OP_API_OPERATION_MAP,
            static::OP_MODEL,
        ];
    }

    public function generate(array $data, string $type, array $modules, string $output): void
    {
        match ($type) {
            static::OP_API_OPERATION_MAP => $this->generateApiOperationMap($data, $modules, $output),
            static::OP_MODEL => $this->generateModel($data, $modules, $output),
        };
    }

    protected function generateApiOperationMap(array $data, array $modules, string $output): void
    {
        printInfo('Generating ApiOperationMap...');

        $outputStructure = $this->config->getOutputStructure('api_models_map');
        $directory = "{$output}{$outputStructure}";
        $indexPath = "{$directory}index.ts";
        $indexContent = file_exists($indexPath) ? file_get_contents($indexPath) : '';
        $alias = $this->getAlias($output);

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
                        printWarning(
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
                        printWarning("Can't resolve response type for '{$operationId}': '{$path}' '{$method}'.");
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
            $content = str_replace(
                '{operationMapItems}',
                $operationItems,
                file_get_contents('stubs/api_operation_map/api_operation_map.stub'),
            );

            $moduleName = $this->toCamelCase($module);
            $filename = str_replace('{module}', $moduleName, $this->config->getFilename('operation_map_template'));
            $path = "{$directory}{$filename}";
            $this->saveFile($path, $content);

            printSuccess("ApiOperationMap has been generated to '{$path}'.");

            $indexModuleExport = strtr("export * from '@lk-{alias}/{path}{moduleOperationMap}';", [
                '{alias}' => $alias,
                '{path}' => $outputStructure,
                '{moduleOperationMap}' => str_replace('.ts', '', $filename),
            ]);

            if (! str_contains($indexContent, $indexModuleExport)) {
                $indexContent .= PHP_EOL . $indexModuleExport;
            }
        }

        $this->saveFile($indexPath, $indexContent);
        printSuccess('ApiOperationMap index has been updated.');
    }

    protected function getAlias(string $source): string
    {
        $modulePackageJson = json_decode(
            file_exists("{$source}package.json") ? file_get_contents("{$source}package.json") : [],
            true,
        );
        $alias = $modulePackageJson['config']['alias'] ?? '';

        if (! $alias) {
            printAbort("Please check that package.json exist in path '{$source}' and it has config 'alias'.");
        }

        return $alias;
    }

    protected function generateModel(array $data, array $modules, string $output): void
    {
        printInfo('Generating models...');

        $alias = $this->getAlias($output);

        $models = array_filter(
            $data['components']['schemas'] ?? [],
            fn($key) => preg_match($this->config->getFilterRegex('models'), $key),
            ARRAY_FILTER_USE_KEY,
        );
        $genericTypesDefaults = $this->config->getGenericTypesDefaults();

        $baseModelOutputStructure = $this->config->getOutputStructure('base_models');
        $baseModelDirectory = "{$output}{$baseModelOutputStructure}";
        $baseModelIndexPath = "{$baseModelDirectory}index.ts";
        $baseModelIndexContent = '';

        $mainModelOutputStructure = $this->config->getOutputStructure('models');
        $mainModelDirectory = "{$output}{$mainModelOutputStructure}";
        $mainModelIndexPath = "{$mainModelDirectory}index.ts";
        $mainModelIndexContent = '';

        $translateModelOutputStructure = $this->config->getOutputStructure('model_translations');
        $translateModelDirectory = "{$output}{$translateModelOutputStructure}";
        $translateModelIndexPath = "{$translateModelDirectory}index.ts";
        $translateModelIndexContent = '';

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
                if (isset($propertyInfo['enum'])) {
                    $enums[$propertyName] = $propertyInfo['enum'];

                    continue;
                }

                if (! isset($propertyInfo['x-module']) || $propertyInfo['x-module'] !== $modelModule) {
                    continue;
                }

                [$propertyType, $addImport] = $this->getPropertyType($propertyInfo);
                $additionalImport = array_merge($additionalImport, $addImport);

                if (isset($propertyInfo['default'])) {
                    $defaultValue = 'string' === $propertyType
                        ? "'{$propertyInfo['default']}'"
                        : $propertyInfo['default'];
                } else {
                    if (array_key_exists($propertyType, $genericTypesDefaults)) {
                        $defaultValue = in_array($propertyName, $requiredProperties)
                            ? $genericTypesDefaults[$propertyType] : '';
                    } else {
                        $defaultValue = in_array($propertyName, $requiredProperties) ? '' : 'null';
                    }
                }

                $additionalProperties .= strtr(
                    file_get_contents('stubs/model/model_property.stub'),
                    [
                        '{name}' => $propertyName,
                        '{type}' => $propertyType,
                        '{nullable}' => in_array($propertyName, $requiredProperties)
                            || array_key_exists($propertyType, $genericTypesDefaults) ? '' : ' | null',
                        '{default}' => $defaultValue !== '' ? " = {$defaultValue}" : '',
                    ],
                );
            }

            $modelName = preg_replace($this->config->getFilterRegex('models'), '', $model);

            $baseModelContent = strtr(
                file_get_contents('stubs/model/base_model.stub'),
                [
                    '{additionalImport}' => implode(PHP_EOL, $additionalImport),
                    '{modelName}' => $modelName,
                    '{modelType}' => $modelType,
                    '{modelTypeValue}' => $modelTypeValue,
                    '{properties}' => $additionalProperties,
                ],
            );

            $filename = str_replace(
                '{entity}',
                $modelName,
                $this->config->getFilename('models_template'),
            );
            $baseModelPath = "{$baseModelDirectory}{$filename}";
            $this->saveFile($baseModelPath, $baseModelContent);
            printSuccess("Base model has been generated to '{$baseModelPath}'.");

            $baseModelIndexExport = strtr("export * from '@lk-{alias}/{path}{modelName}';", [
                '{alias}' => $alias,
                '{path}' => $baseModelOutputStructure,
                '{modelName}' => str_replace('.ts', '', $filename),
            ]);

            if (! str_contains($baseModelIndexContent, $baseModelIndexExport)) {
                $baseModelIndexContent .= PHP_EOL . $baseModelIndexExport;
            }

            // Main model
            $relations = $modelData['properties']['relationships']['properties'] ?? [];
            $relationsContent = '';

            foreach ($relations as $relationKey => $relation) {
                $relationsContent .= strtr(
                    file_get_contents('stubs/model/relation.stub'),
                    [
                        '{relation}' => $this->toCamelCase($relationKey, '_'),
                    ],
                );
            }

            $mainModelContent = strtr(
                file_get_contents('stubs/model/model.stub'),
                [
                    '{modelName}' => $modelName,
                    '{alias}' => $alias,
                    '{path}' => $baseModelOutputStructure,
                    '{relations}' => rtrim($relationsContent),
                ],
            );

            $mainModelPath = "{$mainModelDirectory}{$filename}";

            if (! file_exists($mainModelPath)) {
                $this->saveFile($mainModelPath, $mainModelContent);
                printSuccess("Model has been generated to '{$mainModelPath}'.");
            } else {
                printWarning("Model already exist '{$mainModelPath}'.");
            }

            $mainModelIndexExport = strtr("export * from '@lk-{alias}/{path}{modelName}';", [
                '{alias}' => $alias,
                '{path}' => $mainModelOutputStructure,
                '{modelName}' => str_replace('.ts', '', $filename),
            ]);

            if (! str_contains($mainModelIndexContent, $mainModelIndexExport)) {
                $mainModelIndexContent .= PHP_EOL . $mainModelIndexExport;
            }

            // translate model
            $enumsContent = '';

            foreach ($enums as $propertyName => $values) {
                $attributeElements = '';

                foreach ($values as $value) {
                    if (! $value) {
                        continue;
                    }

                    $attributeElements .= strtr(
                        file_get_contents('stubs/model/translation_attribute_element.stub'),
                        [
                            '{value}' => $value,
                            '{title}' => ucfirst(str_replace('_', ' ', $value)),
                        ],
                    );
                }

                $enumsContent .= strtr(
                    file_get_contents('stubs/model/translation_attribute.stub'),
                    [
                        '{attributeTitle}' => $propertyName,
                        '{attributeElements}' => rtrim($attributeElements),
                    ],
                );
            }

            $translateModelContent = strtr(
                file_get_contents('stubs/model/model_translations.stub'),
                [
                    '{translations}' => rtrim($enumsContent),
                ],
            );

            $translateModelPath = "{$translateModelDirectory}{$filename}";

            if (! file_exists($translateModelPath)) {
                $this->saveFile($translateModelPath, $translateModelContent);
                printSuccess("Model translations has been generated to '{$translateModelPath}'.");
            } else {
                printWarning("Model translations already exist '{$translateModelPath}'.");
            }

            $translateModelIndexExport = strtr("export * from '@lk-{alias}/{path}{modelName}';", [
                '{alias}' => $alias,
                '{path}' => $translateModelOutputStructure,
                '{modelName}' => str_replace('.ts', '', $filename),
            ]);

            if (! str_contains($translateModelIndexContent, $translateModelIndexExport)) {
                $translateModelIndexContent .= PHP_EOL . $translateModelIndexExport;
            }
        }

        $this->saveFile($baseModelIndexPath, $baseModelIndexContent);
        printSuccess('Base model index has been updated.');

        $this->saveFile($mainModelIndexPath, $mainModelIndexContent);
        printSuccess('Model index has been updated.');

        $this->saveFile($translateModelIndexPath, $translateModelIndexContent);
        printSuccess('Model translations index has been updated.');
    }

    protected function toCamelCase(string $string, string $separator = '-'): string
    {
        return implode('', array_map('ucfirst', explode($separator, $string)));
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

    protected function getPropertyType(array $propertyInfo): array
    {
        $additionalImport = [];
        $propertyType = '';

        if (isset($propertyInfo['type'])) {
            $type = is_array($propertyInfo['type']) ? array_shift($propertyInfo['type']) : $propertyInfo['type'];

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
            $propertyType = $pos !== false ? substr($propertyInfo['$ref'], $pos + 1) : $propertyInfo['$ref'];

            if (! isset($typeConfigs[$propertyType])) {
                printAbort("Undefined \$ref '{$propertyType}'. Please check config!");
            }

            $additionalImport[$propertyType] = $typeConfigs[$propertyType]['ts_import'];
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
