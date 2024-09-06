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

    public function generate(array $data, string $type, string $output): void
    {
        match ($type) {
            static::OP_API_OPERATION_MAP => $this->generateApiOperationMap($data, $output),
            static::OP_MODEL => $this->generateModel($data, $output),
            static::OP_GENERAL => $this->generateGeneral($data, $output),
        };
    }

    protected function generateApiOperationMap(array $data, string $output): void
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
                $operationId = $info['operationId'];

                if (! $operationId) {
                    continue;
                }

                $operationItems .= str_replace(
                    ['{operationId}', '{url}', '{method}'],
                    [$operationId, $path, strtoupper($method)],
                    $operationItemsTemplate,
                );
            }
        }
        $content = str_replace(
            '{operationMapItems}',
            $operationItems,
            file_get_contents('stubs/api_operation_map/api_operation_map.stub'),
        );

        $configOutput = $this->config->getOutputStructure();
        $path = "{$output}{$configOutput['api_models_map']}ApiOperationMap.ts";
        $this->saveFile($path, $content);

        printSuccess("ApiOperationMap has been generated to '{$path}'.");
    }

    protected function generateModel(array $data, string $output): void
    {
        printInfo('Generating models...');

        $definitions = $data['definitions'] ?? [];

        foreach ($definitions as $model => $definition) {
            printInfo("Generating model {$model}...");

            $modelModule = $definition['x-module'] ?? '';

            if (! $modelModule) {
                printWarning('Model has no "x-module" property...');

                continue;
            }

            $additionalImport = [];
            $additionalProperties = '';
            $properties = $definition['properties']['attributes']['properties'] ?? [];
            $modelType = $definition['properties']['type']['type'] ?? '';

            // todo update when there will be final format
            if (isset($definition['properties']['type']['default'])) {
                $modelTypeValue = $definition['properties']['type']['default'];
            } elseif (isset($definition['example']['type'])) {
                $modelTypeValue = $definition['example']['type'];
            } else {
                $modelTypeValue = strtolower($model);
            }

            if (! $modelType || ! $modelTypeValue || ! $properties) {
                printWarning('Model has no type, type value or properties...');

                continue;
            }

            $requiredProperties = $definition['properties']['attributes']['required'] ?? [];

            foreach ($properties as $propertyName => $propertyInfo) {
                if (! isset($propertyInfo['x-module']) || $propertyInfo['x-module'] !== $modelModule) {
                    continue;
                }

                [$propertyType, $addImport] = $this->getPropertyType($propertyInfo);
                $additionalImport = array_merge($additionalImport, $addImport);

                if (! isset($propertyInfo['default']) && in_array($propertyName, $requiredProperties)) {
                    $defaultValue = '';
                } elseif (isset($propertyInfo['default'])) {
                    $defaultValue = 'string' === $propertyType
                        ? "'{$propertyInfo['default']}'"
                        : $propertyInfo['default'];
                } else {
                    $defaultValue = 'null';
                }

                $additionalProperties .= str_replace(
                    ['{name}', '{type}', '{nullAble}', '{default}'],
                    [
                        $propertyName,
                        $propertyType,
                        in_array($propertyName, $requiredProperties) ? '' : ' | null',
                        $defaultValue,
                    ],
                    file_get_contents('stubs/model/model_property.stub'),
                );
            }

            $content = str_replace(
                ['{additionalImport}', '{modelName}', '{modelType}', '{modelTypeValue}', '{properties}'],
                [
                    implode(PHP_EOL, $additionalImport),
                    $model,
                    $modelType,
                    $modelTypeValue,
                    $additionalProperties,
                ],
                file_get_contents('stubs/model/model.stub'),
            );

            $configOutput = $this->config->getOutputStructure();
            $path = "{$output}{$configOutput['models']}{$model}Model.ts";
            $this->saveFile($path, $content);
            printSuccess("Model has been generated to '{$path}'.");
        }
    }

    protected function getPropertyType(array $propertyInfo): array
    {
        $additionalImport = [];
        $propertyType = '';
        $typeConfigs = $this->config->getGenericTypes();


        if (isset($propertyInfo['type'])) {
            if (! isset($typeConfigs[$propertyInfo['type']])) {
                printAbort("Undefined property type '{$propertyInfo['type']}'. Please check config!");
            }

            $propertyType = $typeConfigs[$propertyInfo['type']];
        } elseif (isset($propertyInfo['$ref'])) {
            $pos = strrpos($propertyInfo['$ref'], '/');
            $propertyType = $pos !== false ? substr($propertyInfo['$ref'], $pos + 1) : $propertyInfo['$ref'];

            if (! isset($typeConfigs['$ref'][$propertyType])) {
                printAbort("Undefined \$ref '{$propertyType}'. Please check config!");
            }

            $additionalImport[$propertyType] = $typeConfigs['$ref'][$propertyType]['ts_import'];
        }

        if (! $propertyType) {
            printLine('Property info: ' . json_encode($propertyInfo));
            printAbort('Undefined property type. Please check source!');
        }

        return [$propertyType, $additionalImport];
    }

    protected function generateGeneral(array $data, string $output): string
    {
        // TODO generate general data
        return '';
    }

    protected function saveFile(string $path, string $data): void
    {
        $directory = dirname($path);

        if (!is_dir($directory)) {
            printInfo("Creating directory '{$directory}'");
            mkdir($directory, 0777, true);
        }

        file_put_contents($path, $data);
    }
}
