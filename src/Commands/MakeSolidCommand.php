<?php

namespace AmpTech\LaravelSolidStructure\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;
use Illuminate\Filesystem\Filesystem;
use Illuminate\Support\Facades\Schema;

class MakeSolidCommand extends Command
{
    protected $signature = 'make:solid {name : El nombre del modelo existente}
                                      {--path= : Ruta personalizada para el controlador (ej: V1/Admin)}
                                      {--paginate=15 : Número de elementos por página (default: 15)}
                                      {--test : Crear también el archivo de tests}
                                      {--force : Sobrescribir archivos existentes}';

    protected $description = 'Crea arquitectura SOLID (Controller, Service, Repository, Interface, Requests) para un modelo existente';

    protected $files;
    protected $stubsPath;

    public function __construct(Filesystem $files)
    {
        parent::__construct();
        $this->files = $files;
        
        $this->stubsPath = __DIR__ . '/../stubs';
    }

    public function handle()
    {
        $name = $this->argument('name');
        $withTests = $this->option('test');
        $customPath = $this->option('path');
        $perPage = $this->option('paginate');
        
        if (!$this->files->exists(app_path("Models/{$name}.php"))) {
            $this->error("✖ El modelo {$name} no existe.");
            $this->line("   Primero crea el modelo con: php artisan make:model {$name} -mf");
            return Command::FAILURE;
        }
        
        if ($customPath && $this->hasInvalidPathCharacters($customPath)) {
            $this->error("✖ La ruta personalizada contiene caracteres inválidos.");
            $this->line("   Ejemplo válido: V1/Admin");
            $this->line("   Evita usar: espacios, dos puntos, barras invertidas");
            return Command::FAILURE;
        }
        
        $this->info("🚀 Creando arquitectura SOLID para: {$name}");
        if ($customPath) {
            $this->info("📁 Path del controlador: {$customPath}");
        }
        $this->info("📄 Paginación: {$perPage} elementos por página");
        $this->newLine();

        $this->createDirectories($customPath);

        if (!$this->verifyStubsExist()) {
            return Command::FAILURE;
        }

        $controllerPath = $this->getControllerPath($name, $customPath);
        $controllerNamespace = $this->getControllerNamespace($customPath);

        $this->createFromStub('controller', $name, $controllerPath, [
            'controllerNamespace' => $controllerNamespace,
        ]);

        $storeRules = $this->getModelFields($name, 'store');
        $updateRules = $this->getModelFields($name, 'update');
        
        $this->createFromStub('request.store', $name, 
            app_path("Http/Requests/Store{$name}Request.php"), [
                'controllerNamespace' => $controllerNamespace,
                'storeRules' => $storeRules,
            ]);
        $this->createFromStub('request.update', $name, 
            app_path("Http/Requests/Update{$name}Request.php"), [
                'controllerNamespace' => $controllerNamespace,
                'updateRules' => $updateRules,
            ]);

        $this->createFromStub('interface', $name, 
            app_path("Contracts/{$name}RepositoryInterface.php"));

        $this->createFromStub('repository', $name, 
            app_path("Repositories/{$name}Repository.php"), [
                'perPage' => $perPage,
            ]);

        $this->createFromStub('service', $name, 
            app_path("Services/{$name}Service.php"));

        $this->ensureServiceProvider($name);

        if ($withTests) {
            $this->createFromStub('test', $name, 
                base_path("tests/Feature/{$name}Test.php"), [
                    'perPage' => $perPage,
                ]);
        }

        $this->newLine();
        $this->info('✅ Arquitectura SOLID creada exitosamente!');
        $this->newLine();
        $this->showNextSteps($name, $customPath, $perPage);

        return Command::SUCCESS;
    }

    protected function hasInvalidPathCharacters($path)
    {
        return preg_match('/[:\\\\]|^\s|\s$|^[A-Z]:/', $path);
    }

    protected function getControllerPath($name, $customPath)
    {
        if ($customPath) {
            $pathDirectory = str_replace('/', DIRECTORY_SEPARATOR, $customPath);
            return app_path("Http/Controllers/{$pathDirectory}/{$name}Controller.php");
        }
        
        return app_path("Http/Controllers/{$name}Controller.php");
    }

    protected function getControllerNamespace($customPath)
    {
        if ($customPath) {
            $namespace = str_replace('/', '\\', $customPath);
            return "App\\Http\\Controllers\\{$namespace}";
        }
        
        return "App\\Http\\Controllers";
    }

    protected function createDirectories($customPath = null)
    {
        $directories = [
            app_path('Contracts'),
            app_path('Repositories'),
            app_path('Services'),
            app_path('Http/Requests'),
        ];

        if ($customPath) {
            $pathDirectory = str_replace('/', DIRECTORY_SEPARATOR, $customPath);
            $directories[] = app_path("Http/Controllers/{$pathDirectory}");
        }

        foreach ($directories as $directory) {
            if (!$this->files->isDirectory($directory)) {
                $this->files->makeDirectory($directory, 0755, true);
                $this->info("✓ Directorio creado: {$directory}");
            }
        }
    }

    protected function verifyStubsExist()
    {
        $requiredStubs = [
            'controller.stub',
            'interface.stub',
            'repository.stub',
            'service.stub',
            'request.store.stub',
            'request.update.stub',
            'provider.stub',
            'test.stub',
        ];

        $missingStubs = [];
        foreach ($requiredStubs as $stub) {
            $path = "{$this->stubsPath}/{$stub}";
            if (!$this->files->exists($path)) {
                $missingStubs[] = $stub;
            }
        }

        if (!empty($missingStubs)) {
            $this->error('✖ Los siguientes stubs no existen en el paquete:');
            $this->newLine();
            foreach ($missingStubs as $stub) {
                $this->line("   ✗ {$stub}");
            }
            $this->newLine();
            $this->error('Por favor, contacta al autor del paquete.');
            return false;
        }

        $this->info("✓ Todos los stubs encontrados");
        return true;
    }

    protected function createFromStub($stubName, $modelName, $outputPath, $extraReplacements = [])
    {
        $stubFile = $stubName . '.stub';
        $stubPath = "{$this->stubsPath}/{$stubFile}";

        if (!$this->files->exists($stubPath)) {
            $this->error("✗ Stub no encontrado: {$stubPath}");
            return;
        }

        if ($this->files->exists($outputPath) && !$this->option('force')) {
            $fileName = basename($outputPath);
            $this->warn("⚠   {$fileName} ya existe (usa --force para sobrescribir)");
            return;
        }

        $stub = $this->files->get($stubPath);
        $content = $this->replacePlaceholders($stub, $modelName, $extraReplacements);
        
        $directory = dirname($outputPath);
        if (!$this->files->isDirectory($directory)) {
            $this->files->makeDirectory($directory, 0755, true);
        }
        
        $this->files->put($outputPath, $content);
        
        $fileName = basename($outputPath);
        $this->info("✓ {$fileName} creado");
    }

    protected function ensureServiceProvider($name)
    {
        $providerPath = app_path('Providers/RepositoryServiceProvider.php');
        
        if (!$this->files->exists($providerPath)) {
            $this->createFromStub('provider', $name, $providerPath);
            $this->info("✓ RepositoryServiceProvider creado");
            $this->newLine();
            $this->warn("⚠   IMPORTANTE: Registra el provider en config/app.php o bootstrap/providers.php");
        } else {
            $this->addBindingToProvider($providerPath, $name);
            $this->info("✓ Binding agregado al RepositoryServiceProvider");
        }
    }

    protected function addBindingToProvider($path, $name)
    {
        $content = $this->files->get($path);

        $binding = "        \$this->app->bind(\n" .
                   "            \\App\\Contracts\\{$name}RepositoryInterface::class,\n" .
                   "            \\App\\Repositories\\{$name}Repository::class\n" .
                   "        );\n";

        if (strpos($content, $binding) !== false) {
            $this->line("  (Binding ya existe)");
            return;
        }

        if (preg_match('/public function register\(\)(?::\s*void)?\s*\{/', $content, $matches, PREG_OFFSET_CAPTURE)) {
            $position = $matches[0][1] + strlen($matches[0][0]);
            $content = substr_replace($content, "\n{$binding}", $position, 0);
            $this->files->put($path, $content);
        }
    }

    protected function classNameToTitle($className)
    {
        return preg_replace('/(?<!^)([A-Z])/', ' $1', $className);
    }

    protected function replacePlaceholders($stub, $name, $extraReplacements = [])
    {
        $customPath = $this->option('path');
        $perPage = $this->option('paginate');
        
        $apiRoutePath = $customPath 
            ? strtolower(str_replace('/', '/', $customPath)) . '/' . Str::kebab(Str::plural($name))
            : Str::kebab(Str::plural($name));
        
        $controllerNamespace = $extraReplacements['controllerNamespace'] ?? 'App\\Http\\Controllers';
        
        $storeRules = $extraReplacements['storeRules'] ?? '';
        $updateRules = $extraReplacements['updateRules'] ?? '';
        
        $modelTitle = $this->classNameToTitle($name);
        $modelTitlePlural = $this->classNameToTitle(Str::plural($name));
        
        $replacements = [
            '{{namespace}}' => 'App',
            '{{controllerNamespace}}' => $controllerNamespace,
            '{{class}}' => $name,
            '{{variable}}' => Str::camel($name),
            '{{variablePlural}}' => Str::camel(Str::plural($name)),
            '{{model}}' => $name,
            '{{modelVariable}}' => Str::camel($name),
            '{{modelVariablePlural}}' => Str::camel(Str::plural($name)),
            '{{modelTitle}}' => $modelTitle,
            '{{modelTitlePlural}}' => $modelTitlePlural,
            '{{routeName}}' => Str::kebab(Str::plural($name)),
            '{{routePath}}' => $apiRoutePath,
            '{{tableName}}' => Str::snake(Str::plural($name)),
            '{{storeRules}}' => $storeRules,
            '{{updateRules}}' => $updateRules,
            '{{perPage}}' => $perPage,
        ];

        return str_replace(
            array_keys($replacements),
            array_values($replacements),
            $stub
        );
    }

    protected function getModelFields($name, $requestType = 'store')
    {
        $tableName = Str::snake(Str::plural($name));
        
        try {
            if (Schema::hasTable($tableName)) {
                $columns = Schema::getColumnListing($tableName);
                
                $excludedFields = ['id', 'created_at', 'updated_at', 'deleted_at'];
                $fields = array_diff($columns, $excludedFields);
                
                return $this->formatFieldsForValidation($fields, $tableName, $requestType, $name);
            }
        } catch (\Exception $e) {
            $this->warn("⚠   No se pudo leer la tabla {$tableName}: " . $e->getMessage());
        }
        
        return $this->getFieldsFromMigration($name, $requestType);
    }

    protected function getFieldsFromMigration($name, $requestType = 'store')
    {
        $tableName = Str::snake(Str::plural($name));
        $migrationPath = database_path('migrations');
        
        if (!$this->files->isDirectory($migrationPath)) {
            return $this->getDefaultFieldsComment();
        }
        
        $migrationFiles = $this->files->glob("{$migrationPath}/*_create_{$tableName}_table.php");
        
        if (empty($migrationFiles)) {
            return $this->getDefaultFieldsComment();
        }
        
        $migrationContent = $this->files->get($migrationFiles[0]);
        $fields = $this->extractFieldsFromMigration($migrationContent);
        
        if (empty($fields)) {
            return $this->getDefaultFieldsComment();
        }
        
        return $this->formatFieldsForValidation($fields, null, $requestType, $name);
    }

    protected function extractFieldsFromMigration($content)
    {
        $fields = [];
        
        $patterns = [
            '/\$table->(\w+)\([\'"](\w+)[\'"]\)/',
            '/\$table->(\w+)\([\'"](\w+)[\'"],/',
        ];
        
        foreach ($patterns as $pattern) {
            if (preg_match_all($pattern, $content, $matches)) {
                foreach ($matches[2] as $field) {
                    if (!in_array($field, ['id', 'created_at', 'updated_at', 'deleted_at', 'remember_token'])) {
                        $fields[] = $field;
                    }
                }
            }
        }
        
        return array_unique($fields);
    }

    protected function formatFieldsForValidation($fields, $tableName = null, $requestType = 'store', $modelName = null)
    {
        if (empty($fields)) {
            return $this->getDefaultFieldsComment();
        }
        
        $rules = [];
        $modelVariable = Str::camel($modelName);
        
        foreach ($fields as $field) {
            $rule = $this->guessValidationRule($field, $tableName, $requestType, $modelVariable);
            $rules[] = "            '{$field}' => '{$rule}',";
        }
        
        return implode("\n", $rules);
    }

    protected function guessValidationRule($field, $tableName = null, $requestType = 'store', $modelVariable = null)
    {
        return 'required';
    }

    protected function getDefaultFieldsComment()
    {
        return "            // TODO: Agrega aquí las reglas de validación para tu modelo\n" .
               "            // Ejemplo:\n" .
               "            // 'name' => 'required|string|max:255',\n" .
               "            // 'email' => 'required|email|unique:users',";
    }

    protected function showNextSteps($name, $customPath, $perPage)
    {
        $routePath = $customPath 
            ? strtolower(str_replace('/', '/', $customPath)) . '/' . Str::kebab(Str::plural($name))
            : Str::kebab(Str::plural($name));
        
        $controllerClass = $customPath
            ? str_replace('/', '\\', $customPath) . "\\{$name}Controller"
            : "{$name}Controller";
        
        $this->line('📋 Próximos pasos:');
        $this->newLine();
        
        $this->line('1. Registra el provider (si es la primera vez):');
        $this->comment('   En config/app.php (Laravel 10):');
        $this->line("   App\\Providers\\RepositoryServiceProvider::class,");
        $this->comment('   O en bootstrap/providers.php (Laravel 11+):');
        $this->line("   App\\Providers\\RepositoryServiceProvider::class,");
        $this->newLine();
        
        $this->line('2. Revisa y personaliza las reglas de validación en:');
        $this->line("   - app/Http/Requests/Store{$name}Request.php");
        $this->line("   - app/Http/Requests/Update{$name}Request.php");
        $this->newLine();
        
        $this->line('3. Agrega las rutas en routes/api.php:');
        if ($customPath) {
            $this->comment("   use App\\Http\\Controllers\\{$controllerClass};");
            $this->newLine();
        }
        $this->comment("   Route::apiResource('{$routePath}', {$controllerClass}::class);");
        $this->newLine();
        $this->comment('   Endpoints disponibles:');
        $this->line("   GET    /api/{$routePath}           - Listar todos (paginado: {$perPage} por página)");
        $this->line("   POST   /api/{$routePath}           - Crear nuevo");
        $this->line("   GET    /api/{$routePath}/{id}      - Ver uno");
        $this->line("   PUT    /api/{$routePath}/{id}      - Actualizar");
        $this->line("   DELETE /api/{$routePath}/{id}      - Eliminar");
        $this->newLine();
        $this->comment('   Parámetros de paginación opcionales:');
        $this->line("   ?page=2&per_page=20                - Página 2, 20 elementos");
        $this->newLine();
        
        $this->line('4. Personaliza la lógica de negocio (opcional):');
        $this->line("   app/Services/{$name}Service.php");
        $this->newLine();
        
        if ($this->option('test')) {
            $this->line('5. Completa los datos de prueba en:');
            $this->line("   tests/Feature/{$name}Test.php");
            $this->newLine();
            $this->line('6. Ejecuta los tests:');
            $this->line("   php artisan test --filter {$name}Test");
            $this->newLine();
        }
        
        $this->info("🎉 ¡Arquitectura SOLID lista para usar!");
    }
}