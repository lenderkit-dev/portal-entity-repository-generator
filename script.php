<?php

$source = $argv[1];
$type = $argv[2];

$allowedTypes = [
    'api_operation_map',
    'model',
    'general'
];

if (count($argv) !== 3 || ! in_array($type, $allowedTypes)) {
    echo 'Usage: php script.php <source> <type>' . PHP_EOL;
    echo 'Source: filename or url' . PHP_EOL;
    echo 'Type: use one of available options (api_operation_map, model, general)' . PHP_EOL;
    exit(1);
}

$reader = new Reader;
$data = $reader->getData($source);

$converter = new Converter();
$converter->convert($data, $type);

class Reader
{
    public function getData(string $source): array
    {
        // get from url
        $parsedUrl = parse_url($source);
        if (isset($parsedUrl['scheme'])) {
            $curl = curl_init($source);
            curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
            $data = curl_exec($curl);
            if (curl_errno($curl)) {
                echo 'Error:' . curl_error($curl) . PHP_EOL;

            }
            curl_close($curl);

            return $this->toArray($data);
        }

        // get from file
        if (file_exists($source)) {
            $data = file_get_contents($source);

            return $this->toArray($data);
        }

        // Handle other cases or return an error
        echo 'Check the source!' . PHP_EOL;
        die();
    }

    protected function toArray(string $data): array
    {
        return json_decode($data, true) ?: yaml_parse($data);
    }
}

class Converter
{
    const TYPE_API_OPERATION_MAP = 'api_operation_map';
    const TYPE_MODEL = 'model';
    const TYPE_GENERAL = 'general';

    public function convert(array $data, string $type): void
    {
        match ($type) {
            static::TYPE_API_OPERATION_MAP => $this->generateApiOperationMap($data),
            static::TYPE_MODEL => $this->generateModel($data),
            static::TYPE_GENERAL => $this->generateGeneral($data),
        };
    }

    protected function generateApiOperationMap(array $data): void
    {
        $paths = $data['paths'] ?? [];
        $content = 'export const apiOperationMap = {' . PHP_EOL;

        foreach ($paths as $url => $path) {
            if (! is_array($path)) {
                continue;
            }

            foreach ($path as $method => $info) {
                $operationId = $info['operationId'];
                if (! $operationId) {
                    continue;
                }

                $method = strtoupper($method);
                $content .= implode(PHP_EOL, [
                    "    {$operationId}: {",
                    "        url: \"{$url}\",",
                    "        method: \"{$method}\"",
                    '    },' . PHP_EOL,
                ]);
            }
        }
        $content .= '};';

        file_put_contents('output/ApiOperationMap.ts', $content);
    }

    protected function generateModel(array $data): void
    {
        $definitions = $data['definitions'] ?? [];
        foreach ($definitions as $model => $definition) {
            $modelModule = $definition['x-module'] ?? '';
            if (! $modelModule) {
                continue;
            }

            $content = 'import { Model } from \'@lk-framework/boilerplate/model/Model\';' . PHP_EOL . PHP_EOL;
            // string to be replaced later with imports
            $content .= '*additionalImport*' . PHP_EOL . PHP_EOL;
            $content .= "export class {$model} extends Model {" . PHP_EOL;

            $additionalImport = [];
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
                continue;
            }
            $requiredProperties = $definition['properties']['attributes']['required'] ?? [];

            $content .= "    static type: {$modelType} = '{$modelTypeValue}';" . PHP_EOL . PHP_EOL;

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
                        ? " = '{$propertyInfo['default']}'"
                        : " = {$propertyInfo['default']}";
                } else {
                    $defaultValue = ' = null';
                }

                $content .= "    {$propertyName}: {$propertyType}";
                $content .= in_array($propertyName, $requiredProperties) ? '' : ' | null';
                $content .= $defaultValue;
                $content .= ';' . PHP_EOL;
            }

            $content = str_replace(
                '*additionalImport*' . ($additionalImport ? '' : PHP_EOL . PHP_EOL),
                implode(PHP_EOL . PHP_EOL, $additionalImport),
                $content
            );

            $content .= PHP_EOL;
            $content .= '    constructor(config: any) {' . PHP_EOL;
            $content .= '        super(config);' . PHP_EOL . PHP_EOL;
            $content .= '        this.collectAttributes(config);' . PHP_EOL;
            $content .= '    }' . PHP_EOL;
            $content .= '}';

            file_put_contents("output/{$model}Model.ts", $content);
        }
    }

    protected function getPropertyType(array $propertyInfo): array
    {
        $additionalImport = [];
        if (isset($propertyInfo['type'])) {
            $propertyType = match ($propertyInfo['type']) {
                'integer' => 'number',
                default => $propertyInfo['type'],
            };
        } elseif(isset($propertyInfo['$ref'])) {
            $pos = strrpos($propertyInfo['$ref'], '/');
            $propertyType = $pos !== false ? substr($propertyInfo['$ref'], $pos + 1) : $propertyInfo['$ref'];
            $additionalImport[$propertyType] = $this->addImportRefType($propertyType);
        } else {
            $propertyType = 'string';
        }

        if ('array' === $propertyType && isset($propertyInfo['items']['type'])) {
            [$addType, $additionalImport] = $this->getPropertyType($propertyInfo['items']);
            $propertyType = ! $additionalImport ? "Array<{$addType}>" : "{$addType}[]";
        }

        return [$propertyType, $additionalImport];
    }

    protected function addImportRefType(string $type): string
    {
        // todo add mapping for all imports
        return match ($type) {
            'Date' => 'import type { Date } from \'@lk-core/model/component/Date\'',
            default => "import type { {$type} } from '@lk-core/model/component/{$type}'",
        };
    }

    protected function generateGeneral(array $data): string
    {
        // TODO convert general data
        return '';
    }
}