<?php

namespace Kaxiluo\Swagger\LaravelCodeGenerator\Generator;

use Illuminate\Support\Str;

class ControllerGenerator extends AbstractGenerator
{
    protected $type = 'Controller';

    public function getDefaultClassName(string $name): string
    {
        $name = trim($name);
        return Str::endsWith($name, 'Controller') ? $name : $name . 'Controller';
    }

    protected function getStub(): string
    {
        return $this->resolveStubPath('/stubs/controller.api.stub');
    }

    protected function buildClass($name, array $contentDefine): string
    {
        $replace = $this->buildReplacements($contentDefine);

        return str_replace(
            array_keys($replace), array_values($replace), parent::buildClass($name, $contentDefine)
        );
    }

    protected function buildReplacements($contentDefine): array
    {
        $replace = [];

        $functions = [];
        foreach ($contentDefine as $func => $funcDefine) {
            $pathVars = [];
            $hasRequest = false;
            foreach (($funcDefine['parameters'] ?? []) as $parameter) {
                if (isset($parameter['$ref'])) {
                    $hasRequest = true;
                }
                if (isset($parameter['in']) && $parameter['in'] == 'path') {
                    $pathVars[] = '$' . $parameter['name'];
                }
                if (isset($parameter['in']) && $parameter['in'] == 'query') {
                    $hasRequest = true;
                }
            }
            if (isset($funcDefine['requestBody'])) {
                $hasRequest = true;
            }

            $functions[] = $this->buildFunctionString($func, $hasRequest, $pathVars, ($funcDefine['summary'] ?? ''));
        }

        $replace['{{ functions }}'] = implode("\n\n", $functions);

        return $replace;
    }

    protected function buildFunctionString($name, $hasRequest, array $pathVars, $annotation)
    {
        $tpl = <<<FUNC
    // {{ annotation }}
    public function {{ name }}({{ request }}{{ pathVars }})
    {
        //
    }
FUNC;
        $fun = $tpl;
        if ($hasRequest) {
            $fun = str_replace('{{ request }}', 'Request $request', $fun);
        } else {
            $fun = str_replace('{{ request }}', '', $fun);
        }

        if ($pathVars) {
            $pathVarsStr = implode(', ', $pathVars);
            if ($hasRequest) {
                $pathVarsStr = ', ' . $pathVarsStr;
            }
            $fun = str_replace('{{ pathVars }}', $pathVarsStr, $fun);
        } else {
            $fun = str_replace('{{ pathVars }}', '', $fun);
        }

        return str_replace(['{{ name }}', '{{ annotation }}',], [$name, $annotation], $fun);
    }

    protected function resolveStubPath($stub): string
    {
        return __DIR__ . $stub;
    }

    protected function getDefaultNamespace($rootNamespace): string
    {
        return $rootNamespace . '\Http\Controllers';
    }
}
