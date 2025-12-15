<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class Project extends Model
{
    use HasFactory;

    protected static function booted(): void
    {
        static::saving(function (Project $project): void {
            $base = Str::slug((string) $project->name);

            if ($base === '') {
                $base = Str::random(8);
            }

            $project->slug = $project->generateUniqueSlug($base);
        });
    }

    protected $fillable = [
        'user_id',
        'name',
        'description',
        'status',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function designs(): HasMany
    {
        return $this->hasMany(Design::class);
    }

    private function generateUniqueSlug(string $base): string
    {
        $slug = $base;
        $suffix = 2;

        while (
            static::query()
                ->where('slug', $slug)
                ->when($this->exists, fn ($q) => $q->where('id', '!=', $this->id))
                ->exists()
        ) {
            $slug = $base . '-' . $suffix;
            $suffix++;
        }

        return $slug;
    }
}
