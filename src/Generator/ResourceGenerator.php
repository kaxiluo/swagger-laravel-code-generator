<?php

namespace Kaxiluo\Swagger\LaravelCodeGenerator\Generator;

class ResourceGenerator extends AbstractGenerator
{
    protected $type = 'Resource';

    // 定义是否资源类继承其他资源类
    private $classIsExtendsMode = false;

    protected function getDefaultClassName(string $name): string
    {
        return trim($name) . 'Resource';
    }

    protected function beforeBuildClass($name, array $contentDefine = []): void
    {
        $this->classIsExtendsMode = false;
        if (isset($contentDefine['allOf'])) {
            $refCount = 0;
            foreach ($contentDefine['allOf'] as $item) {
                if (isset($item['$ref'])) {
                    $refCount += 1;
                }
            }
            // 单继承
            if ($refCount == 1) {
                $this->classIsExtendsMode = true;
            }
        }
    }

    protected function getStub()
    {
        return $this->classIsExtendsMode ?
            $this->resolveStubPath('/stubs/resource-extends.stub')
            : $this->resolveStubPath('/stubs/resource.stub');
    }

    protected function buildClass($name, array $contentDefine): string
    {
        $replace = $this->buildReplacements($contentDefine);

        return str_replace(
            array_keys($replace), array_values($replace), parent::buildClass($name, $contentDefine)
        );
    }

    protected function buildReplacements(array $contentDefine = []): array
    {
        $replace = [];

        $fields = '';

        if ($this->classIsExtendsMode) {
            $parentClass = 'JsonResource';
            foreach ($contentDefine['allOf'] as $item) {
                if (isset($item['$ref'])) {
                    $parentClass = $this->getRefResourceName($item['$ref']);
                }

                if (isset($item['properties'])) {
                    $fields .= $this->buildFieldsByProperties($item['properties']);
                }
            }

            $replace['{{ ParentClass }}'] = $parentClass;
        } else {
            if (isset($contentDefine['properties'])) {
                $fields = $this->buildFieldsByProperties($contentDefine['properties']);
            }
        }

        $replace['{{ fields }}'] = $fields;

        return $replace;
    }

    protected function buildFieldsByProperties(array $properties): string
    {
        $fields = "";
        foreach ($properties as $field => $filedDefine) {
            $fieldType = $filedDefine['type'] ?? '';
            $phpTypeConversion = $fieldType ? $this->getPhpTypeConversionByFieldType($fieldType) : '';

            // 普通字段 默认
            $fieldV = sprintf("%s\$this->%s,", $phpTypeConversion, $field);

            // 引用字段为对象
            if ((empty($fieldType) || $fieldType == 'object') && isset($filedDefine['$ref'])) {
                $refResource = $this->getRefResourceName($filedDefine['$ref']);

                $fieldV = sprintf("new %s(\$this->%s),", $refResource, $field);
            }

            // 引用字段为array
            if ($fieldType == 'array') {
                if (isset($filedDefine['items']) && isset($filedDefine['items']['$ref'])
                    && $filedDefine['items']['$ref']) {
                    $refResource = $this->getRefResourceName($filedDefine['items']['$ref']);

                    $fieldV = sprintf("%s::collection(\$this->%s),", $refResource, $field);
                }
            }

            $fields .= sprintf("\t\t\t'%s' => %s\n", $field, $fieldV);
        }

        return rtrim($fields, "\n");
    }

    protected function getRefResourceName($ref): string
    {
        $refResource = trim(strrchr($ref, '/'), '/');
        return $this->getDefaultClassName($refResource);
    }

    protected function getPhpTypeConversionByFieldType($fieldType): string
    {
        $phpTypeConversion = '';
        switch ($fieldType) {
            case 'string':
                $phpTypeConversion = '(string)';
                break;
            case 'number':
                $phpTypeConversion = '(double)';
                break;
            case 'integer':
                $phpTypeConversion = '(int)';
                break;
            case 'boolean':
                $phpTypeConversion = '(bool)';
                break;
            case 'array':
            case 'object':
                break;
        }
        return $phpTypeConversion;
    }

    protected function resolveStubPath($stub): string
    {
        return __DIR__ . $stub;
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\Http\Resources';
    }
}
