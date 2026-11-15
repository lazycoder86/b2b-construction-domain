<?php

namespace Tests\Fixtures;

use App\Models\Concerns\BelongsToOrganization;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Minimal tenant-scoped model used to test the tenancy plumbing
 * without depending on a real domain model.
 *
 * @property int $id
 * @property string $name
 */
class Widget extends Model
{
    use BelongsToOrganization;

    protected $guarded = [];

    public static function createTable(): void
    {
        Schema::create('widgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('organization_id')->constrained()->cascadeOnDelete();
            $table->string('name');
            $table->timestamps();
        });
    }
}
