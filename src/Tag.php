<?php

namespace Spatie\Tags;

use ArrayAccess;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as DbCollection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Spatie\EloquentSortable\Sortable;
use Spatie\EloquentSortable\SortableTrait;
use Spatie\Translatable\HasTranslations;

class Tag extends Model implements Sortable
{
    use SortableTrait;
    use HasTranslations;
    use HasSlug;
    use HasFactory;

    public array $translatable = ['name', 'slug'];

    public $guarded = [];

    public static function getLocale()
    {
        return app()->getLocale();
    }

    public function scopeWithType(Builder $query, string $type = null): Builder
    {
        if (is_null($type)) {
            return $query;
        }

        return $query->where('type', $type)->ordered();
    }

    public function scopeWithJob(Builder $query, int $job): Builder
    {
        return $query->where('job_id', $job)->ordered();
    }

    public function scopeContaining(Builder $query, string $name, $locale = null): Builder
    {
        $locale = $locale ?? static::getLocale();

        return $query->whereRaw('lower(' . $this->getQuery()->getGrammar()->wrap('name->') . ') like ?', ['%' . mb_strtolower($name) . '%']);
    }

    public static function findOrCreate(int $job, string | array | ArrayAccess $values, string | null $type = null): Collection | Tag | static {

        $tags = collect($values)->map(function ($value) use ($type, $job) {
            if ($value instanceof self) {
                return $value;
            }

            return static::findOrCreateFromString($job, $value, $type);
        });

        return is_string($values) ? $tags->first() : $tags;
    }

    public static function getWithType(int $job, string $type): DbCollection
    {
        return static::withType($type)->get();
    }

    public static function findFromString(int $job, string $name, string $type = null, string $locale = null)
    {
        $locale = $locale ?? static::getLocale();

        return static::query()
            ->where('job_id', $job)
            ->where('type', $type)
            ->where(function ($query) use ($name, $locale) {
                $query->where("name->{$locale}", $name)
                    ->orWhere("slug->{$locale}", $name);
            })
            ->first();
    }

    public static function findFromStringOfAnyType(int $job, string $name, string $locale = null)
    {
        $locale = $locale ?? static::getLocale();

        return static::query()
            ->where('job_id', $job)
            ->where("name->{$locale}", $name)
            ->orWhere("slug->{$locale}", $name)
            ->get();
    }

    public static function findOrCreateFromString(int $job, string $name, string $type = null, string $locale = null)
    {
        $locale = $locale ?? static::getLocale();

        $tag = static::findFromString($job, $name, $type, $locale);

        if (! $tag) {
            $tag = static::create([
                'job_id' => $job,
                'name' => [$locale => $name],
                'type' => $type,
            ]);
        }

        return $tag;
    }

    public static function getTypes(): Collection
    {
        return static::groupBy('type')->pluck('type');
    }

    public function setAttribute($key, $value)
    {
        if (in_array($key, $this->translatable) && ! is_array($value)) {
            return $this->setTranslation($key, static::getLocale(), $value);
        }

        return parent::setAttribute($key, $value);
    }
}
