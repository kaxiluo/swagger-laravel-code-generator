<?php

namespace Kaxiluo\Swagger\LaravelCodeGenerator\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Kaxiluo\Swagger\LaravelCodeGenerator\Generator\ControllerGenerator;
use Kaxiluo\Swagger\LaravelCodeGenerator\Generator\ResourceGenerator;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Yaml\Yaml;

class SwaggerLaravelCodeGeneratorCommand extends Command
{
    protected $name = 'swagger-to-code:gen';

    protected $description = 'Automatically generate laravel models, resources, controllers, routes based on swagger documents';

    private $resourceGenerator;

    private $controllerGenerator;

    public function __construct(ResourceGenerator $resourceGenerator, ControllerGenerator $controllerGenerator)
    {
        parent::__construct();
        $this->resourceGenerator = $resourceGenerator;
        $this->controllerGenerator = $controllerGenerator;
    }

    public function handle()
    {
        $file = $this->argument('file');
        $yaml = Yaml::parseFile(base_path($file));

        if ($this->option('all')) {
            $this->input->setOption('resource', true);
            $this->input->setOption('controller', true);
            $this->input->setOption('route', true);
        }

        // create model, resource
        if (isset($yaml['components']['schemas'])) {
            $this->handleSchemas($yaml['components']['schemas']);
        }

        // create controller, route
        if (($this->option('controller') || $this->option('route')) && isset($yaml['paths'])) {
            $this->handlePaths($yaml['paths']);
        }
    }

    private function handleSchemas(array $schemas)
    {
        $this->info("######## Schemas ########\n#########################");

        $ignoredSchemaRegular = $this->option('ignored-schema-regular');
        foreach ($schemas as $name => $schema) {
            // 该正则模式不创建模型
            if ($ignoredSchemaRegular && preg_match('/' . $ignoredSchemaRegular . '/', $name)) {
                continue;
            }

            $dataType = $schema['type'] ?? '';

            // 非object或allOf组合的不创建模型
            if ($dataType == 'object' && strtolower($name) !== 'user') {
                $this->createModel($name);
            }

            if ($this->option('resource') && $dataType != 'array') {
                $this->createResource($name, $schema);
            }
        }
    }

    private function handlePaths(array $paths)
    {
        $this->info("######## Paths ########\n#######################");

        // 分组
        $controllers = [];
        $routes = [];
        foreach ($paths as $path => $verbs) {
            foreach ($verbs as $verb => $verbDefine) {
                // parse operationId
                $operationId = $verbDefine['operationId'] ?? '';
                $operationId = trim(trim($operationId), '/');
                if ($operationId && $callable = explode('@', $operationId)) {
                    if (count($callable) == 2) {
                        list($controller, $func) = $callable;
                        $controllers[$controller][$func] = $verbDefine;
                        $routes[] = [$verb, $path, $controller, $func];
                    }
                }
            }
        }

        if ($this->option('controller')) {
            foreach ($controllers as $name => $controllerDefine) {
                $this->createController($name, $controllerDefine);
            }
        }
        if ($this->option('route')) {
            if (count($routes)) {
                $this->appendStrToApiRouteFile("/**Auto Generate By Docs Paths*/\n");
            }
            foreach ($routes as $routeDefine) {
                $this->createRoute($routeDefine);
            }
        }
    }

    private function createModel(string $name)
    {
        $this->info('Creating model ' . $name);

        $this->call('make:model', [
            'name' => $name,
            '--force' => $this->option('force'),
        ]);
    }

    private function createResource(string $name, array $schema)
    {
        $this->info('Creating resource ' . $name);

        list($level, $message) = $this->resourceGenerator->generate(
            $name,
            $schema,
            $this->option('force')
        );

        $this->{$level}($message);
    }

    private function createController(string $name, array $controllerDefine)
    {
        $this->info('Creating controller ' . $name);

        list($level, $message) = $this->controllerGenerator->generate(
            $name,
            $controllerDefine,
            $this->option('force')
        );

        $this->{$level}($message);
    }

    private function createRoute(array $routeDefine)
    {
        list($verb, $path, $controller, $func) = $routeDefine;

        $this->info('Creating route ' . $path);

        $controller = $this->controllerGenerator->getDefaultClassName($controller);
        $controller = str_replace('/', '\\', $controller);

        $this->appendStrToApiRouteFile(
            sprintf("Route::%s('%s', '%s@%s');\n", $verb, $path, $controller, $func)
        );

        $this->info('Route create successfully.');
    }

    private function appendStrToApiRouteFile(string $content)
    {
        $routeFile = base_path('routes/api.php');
        if (!Str::contains(file_get_contents($routeFile), $content)) {
            file_put_contents($routeFile, $content, FILE_APPEND);
        }
    }

    protected function getArguments(): array
    {
        return [
            ['file', InputArgument::REQUIRED, 'The yaml file of swagger documents'],
        ];
    }

    protected function getOptions(): array
    {
        return [
            ['all', 'a', InputOption::VALUE_NONE, 'Generate model, resource, controller and route for the swagger documents'],
            ['force', null, InputOption::VALUE_NONE, 'Create the class even if the class already exists'],
            ['resource', 'r', InputOption::VALUE_NONE, 'Create the resource for the documents schemas'],
            ['controller', 'c', InputOption::VALUE_NONE, 'Create the controller for the documents paths'],
            ['route', null, InputOption::VALUE_NONE, 'Create the route for the documents paths'],
            ['ignored-schema-regular', null, InputOption::VALUE_REQUIRED, 'Through this regular filter, no model is created'],
        ];
    }
}
