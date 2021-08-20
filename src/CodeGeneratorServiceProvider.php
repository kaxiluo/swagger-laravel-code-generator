<?php

namespace Kaxiluo\Swagger\LaravelCodeGenerator;

use Illuminate\Support\ServiceProvider;

class CodeGeneratorServiceProvider extends ServiceProvider
{
    public function register()
    {
        $this->commands(['Kaxiluo\Swagger\LaravelCodeGenerator\Commands\SwaggerLaravelCodeGeneratorCommand']);
    }
}
