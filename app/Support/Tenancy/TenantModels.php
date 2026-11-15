<?php

namespace App\Support\Tenancy;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use ReflectionClass;

/**
 * Discovers every model in app/Models that uses BelongsToOrganization, so data
 * export (and tests) automatically cover domain models added later.
 */
class TenantModels
{
    /**
     * @return list<class-string<Model>>
     */
    public static function all(): array
    {
        $models = [];

        foreach (File::allFiles(app_path('Models')) as $file) {
            $class = 'App\\Models\\'.Str::of($file->getRelativePathname())->replace(['/', '.php'], ['\\', ''])->toString();

            if (class_exists($class)
                && is_subclass_of($class, Model::class)
                && ! (new ReflectionClass($class))->isAbstract()
                && in_array(BelongsToOrganization::class, class_uses_recursive($class), true)) {
                $models[] = $class;
            }
        }

        sort($models);

        return $models;
    }
}
