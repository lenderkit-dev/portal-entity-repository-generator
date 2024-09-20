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

    public function generate(array $data, string $type, string $module, string $output): void
    {
        match ($type) {
            static::OP_API_OPERATION_MAP => $this->generateApiOperationMap($data, $module, $output),
            static::OP_MODEL => $this->generateModel($data, $module, $output),
            static::OP_GENERAL => $this->generateGeneral($data, $module, $output),
        };
    }

    protected function generateApiOperationMap(array $data, string $module, string $output): void
    {
        printInfo('Generating ApiOperationMap...');

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

                if (!$responseType) {
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

        $filename = $this->config->getFilename('operation_map');
        $outputStructure = $this->config->getOutputStructure('api_models_map');
        $path = "{$output}{$outputStructure}{$filename}";
        $this->saveFile($path, $content);

        printSuccess("ApiOperationMap has been generated to '{$path}'.");
    }

    protected function generateModel(array $data, string $module, string $output): void
    {
        printInfo('Generating models...');

        $models = array_filter(
            $data['components']['schemas'] ?? [],
            fn($key) => preg_match($this->config->getFilterRegex('models'), $key),
            ARRAY_FILTER_USE_KEY,
        );

        foreach ($models as $model => $modelData) {
            printInfo("Generating model {$model}...");

            $modelModule = $modelData['x-module'] ?? '';

            if (! $modelModule) {
                printWarning("Model {$model} missing 'x-module' property, skipping...");

                continue;
            }

            if ($module && $module !== $modelModule) {
                printInfo(
                    "The 'x-module' not match with provided module: model {$model}, model module '{$modelModule}', skipping...",
                );

                continue;
            }

            $additionalImport = [];
            $additionalProperties = '';
            $properties = $modelData['properties']['attributes']['properties'] ?? [];
            $modelType = $modelData['properties']['type']['type'] ?? '';

            $modelTypeValue = $modelData['properties']['type']['default'] ?? '';

            if (! $modelType || ! $modelTypeValue || ! $properties) {
                printWarning("Model {$model} missing type, skipping...");

                continue;
            }

            $requiredProperties = $modelData['properties']['attributes']['required'] ?? [];

            foreach ($properties as $propertyName => $propertyInfo) {
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
                    $defaultValue = 'null';
                }

                $additionalProperties .= strtr(
                    file_get_contents('stubs/model/model_property.stub'),
                    [
                        '{name}' => $propertyName,
                        '{type}' => $propertyType,
                        '{nullable}' => in_array($propertyName, $requiredProperties) ? '' : ' | null',
                        '{default}' => $defaultValue,
                    ],
                );
            }

            $content = strtr(
                file_get_contents('stubs/model/model.stub'),
                [
                    '{additionalImport}' => implode(PHP_EOL, $additionalImport),
                    '{modelName}' => $model,
                    '{modelType}' => $modelType,
                    '{modelTypeValue}' => $modelTypeValue,
                    '{properties}' => $additionalProperties,
                ],
            );

            $outputStructure = $this->config->getOutputStructure('models');
            $filename = str_replace('{entity}', $model, $this->config->getFilename('models_template'));
            $path = "{$output}{$outputStructure}{$filename}";
            $this->saveFile($path, $content);
            printSuccess("Model has been generated to '{$path}'.");
        }
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
            $propertyType = $this->config->getGenericTypes($type);

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
        }

        if (! $propertyType) {
            printLine('Property info: ' . json_encode($propertyInfo));
            printAbort('Undefined property type. Please check source!');
        }

        return [$propertyType, $additionalImport];
    }

    protected function generateGeneral(array $data, string $module, string $output): string
    {
        // TODO generate general data
        return '';
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
