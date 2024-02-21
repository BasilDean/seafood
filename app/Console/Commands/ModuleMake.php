<?php

namespace App\Console\Commands;

use Exception;
use Illuminate\Console\Command;
use Illuminate\Contracts\Filesystem\FileNotFoundException;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Str;

class FileSystemOperations
{
    private Filesystem $filesystem;

    public function __construct(Filesystem $filesystem)
    {
        $this->filesystem = $filesystem;
    }

    public function isFile($path): bool
    {
        return $this->filesystem->isFile($path);
    }

    public function isDirectory($path): bool
    {
        return $this->filesystem->isDirectory($path);
    }

    public function makeDirectory($path, $mode = 0755): void
    {
        if (!$this->filesystem->isDirectory(dirname($path))) {
            $this->filesystem->makeDirectory(dirname($path), $mode, true);
        }
    }

    public function getFile($path): string
    {
        try {
            return $this->filesystem->get($path);
        } catch (FileNotFoundException $exception) {
            return '';
        }
    }

    public function putFile($path, $contents): void
    {
        $this->filesystem->put($path, $contents);
    }
}

class ModuleMake extends Command
{
    private FileSystemOperations $operations;

    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'make:module {name}
                                                   {--all}
                                                   {--migration}
                                                   {--vue}
                                                   {--view}
                                                   {--controller}
                                                   {--model}
                                                   {--api}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Command description';

    public function __construct(FileSystemOperations $operations)
    {
        parent::__construct();
        $this->operations = $operations;
    }

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        $optionList = [
            'all' => 'setAllOptions',
            'migration' => 'createMigration',
            'vue' => 'createVueComponent',
            'view' => 'createView',
            'controller' => 'createWebController',
            'model' => 'createModel',
            'api' => 'createApiController',
        ];
        foreach ($optionList as $option => $method) {
            if ($this->option($option)) {
                $this->$method();
            }
        }
    }

    private function createMigration(): void
    {
        $table = Str::plural(Str::snake(class_basename($this->argument('name'))));
        try {

            $this->call('make:migration', [
                'name' => "create_{$table}_table",
                '--create' => $table,
                '--path' => config('path.module') . trim($this->argument('name')) . config('path.migrations')
            ]);
        } catch (Exception $e) {
            $this->error($e->getMessage());
        }
    }

    private function createVueComponent(): void
    {

    }

    private function createView(): void
    {

    }

    private function createWebController()
    {
        $this->createController(false);
    }

    private function createApiController()
    {
        $this->createController(true);
    }

    private function createController(bool $isApi): void
    {
        [$controller, $model] = $this->getModelAndControllerNames();
        $path = $this->getControllerPath($this->argument('name'), $isApi);
        if ($this->operations->isFile($path)) {
            $this->error('Controller already exists');
            return;
        }
        $stub = $this->createDirectoryAndGetFileContent($path, 'controller.model.api');
        $this->updateStubAndSaveController($path, $stub, $isApi, $model, $controller);
        $this->createRoutes($controller, $model, $isApi);
    }


    /**
     * @param string $controller
     * @param string $model
     * @param bool $isApi
     */
    private function createRoutes(string $controller, string $model, bool $isApi): void
    {
        $routePath = $this->getRoutesPath($this->argument('name'), $isApi);
        if ($this->operations->isFile($routePath)) {
            $this->error('Routes already exists!');
            return;
        }
        $stub = $this->createDirectoryAndGetFileContent($routePath, 'routes.' . ($isApi ? 'api' : 'web'));
        $this->operations->makeDirectory($routePath);
        if ($stub !== '') {
            $stub = str_replace(
                [
                    'DummyClass',
                    'DummyRoutePrefix',
                    'DummyModelVariable'
                ],
                [
                    ($isApi ? "Api\\" : "") . $controller . 'Controller',
                    Str::plural(Str::snake(lcfirst($model), '-')),
                    lcfirst((($model)))
                ],
                $stub
            );
            $this->operations->putFile($routePath, $stub);
            $this->info(($isApi ? 'API ' : '') . 'Routes created successfully!');
        }

    }

    private function createModel(): void
    {
        $model = Str::singular(Str::studly(class_basename($this->argument('name'))));

        $this->call('make:model', [
            'name' => ucfirst(config('path.module')) . trim($this->argument('name')) . config('path.models') . $model
        ]);
    }

    private function getControllerPath(string $argument, bool $isApi): string
    {
        $controller = Str::studly(class_basename($argument));
        return $this->laravel['path'] . '/Modules/' . str_replace('\\', '/', $argument) . '/Controllers/' . ($isApi ? 'Api/' : '') . "{$controller}Controller.php";
    }

    private function updateConfigFiles(): void
    {

    }


    private function getRoutesPath(string $name, bool $isApi): string
    {
        return $this->laravel['path'] . '/Modules/' . str_replace('\\', '/', $name) . '/Routes/' . ($isApi ? 'api' : 'web') . ".php";
    }

    private function createDirectoryAndGetFileContent(string $path, string $stubName): string
    {
        $this->operations->makeDirectory($path);
        return $this->operations->getFile(base_path('resources/stubs/' . $stubName . '.stub'));
    }

    private function setAllOptions(): void
    {
        $this->input->setOption('migration', true);
        $this->input->setOption('vue', true);
        $this->input->setOption('view', true);
        $this->input->setOption('controller', true);
        $this->input->setOption('model', true);
        $this->input->setOption('api', true);
    }

    private function getModelAndControllerNames(): array
    {
        $model = Str::singular(Str::studly(class_basename($this->argument('name'))));
        $controller = Str::studly(class_basename($this->argument('name')));

        return [$model, $controller];
    }

    private function updateStubAndSaveController(string $path, string $stub, bool $isApi, string $model, string $controller): void
    {
        if ($stub !== '') {
            $stub = str_replace(
                [
                    'DummyNamespace',
                    'DummyRootNamespace',
                    'DummyClass',
                    'DummyFullModelClass',
                    'DummyModelClass',
                    'DummyModelVariable'
                ],
                [
                    config('path.module') . trim($this->argument('name')) . config('path.controllers') . ($isApi ? "\\Api" : ""),
                    $this->laravel->getNamespace(),
                    $controller . 'Controller',
                    config('path.module') . trim($this->argument('name')) . config('path.model') . $model,
                    $model,
                    lcfirst((($model)))
                ],
                $stub
            );
            $this->operations->putFile($path, $stub);
            $this->info(($isApi ? "API " : "") . "Controller created successfully");
            $this->updateConfigFiles();
        }
    }

}






















