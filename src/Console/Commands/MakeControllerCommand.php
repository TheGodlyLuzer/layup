<?php

declare(strict_types=1);

namespace Crumbls\Layup\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Str;

class MakeControllerCommand extends Command
{
    protected $signature = 'layup:make-controller {name : Controller class name (e.g. PageController)}';

    protected $description = 'Scaffold a Layup controller extending AbstractController';

    public function handle(): int
    {
        $name = Str::studly($this->argument('name'));
        if (! str_ends_with($name, 'Controller')) {
            $name .= 'Controller';
        }

        $path = app_path("Http/Controllers/{$name}.php");

        if (file_exists($path)) {
            $this->error("Controller already exists: {$path}");

            return self::FAILURE;
        }

        $dir = dirname($path);
        if (! is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        file_put_contents($path, $this->generateStub($name));
        $this->info("Controller created: {$path}");

        $this->newLine();
        $this->comment('Next steps:');
        $this->line('  1. Register the route in routes/web.php:');
        $this->newLine();
        $this->line("     use App\\Http\\Controllers\\{$name};");
        $this->newLine();
        $this->line("     Route::get('/{slug}', {$name}::class)");
        $this->line("         ->where('slug', '.*')");
        $this->line("         ->name('pages.show');");
        $this->newLine();
        $this->line('  2. Disable built-in routes in config/layup.php:');
        $this->newLine();
        $this->line("     'frontend' => ['enabled' => false]");
        $this->newLine();
        $this->line('  3. Set your layout component in config/layup.php:');
        $this->newLine();
        $this->line("     'frontend' => ['layout' => 'layouts.app']");

        return self::SUCCESS;
    }

    protected function generateStub(string $className): string
    {
        return <<<PHP
<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use Crumbls\Layup\Http\Controllers\AbstractController;
use Crumbls\Layup\Models\Page;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

class {$className} extends AbstractController
{
    /**
     * Resolve the record for the current request.
     *
     * Use ->firstOrFail() for automatic 404 responses.
     */
    protected function getRecord(Request \$request): Model
    {
        return Page::published()
            ->where('slug', \$request->route('slug', ''))
            ->firstOrFail();
    }

    // Override any of these methods to customize behavior.
    // Your IDE will autocomplete them from AbstractController:
    //
    // protected function authorize(Request \$request, Model \$record): void
    // protected function getLayout(Request \$request, Model \$record): string
    // protected function getView(Request \$request, Model \$record): string
    // protected function getViewData(Request \$request, Model \$record, array \$sections): array
    // protected function getCacheTtl(Request \$request, Model \$record): ?int
}

PHP;
    }
}
