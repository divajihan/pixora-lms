<?php

namespace App\Models;

use App\Enums\CourseStatus;
use Database\Factories\CourseFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;

/**
 * @property int $id
 * @property int|null $category_id
 * @property string $title
 * @property string $slug
 * @property string|null $summary
 * @property string|null $description
 * @property string|null $thumbnail_path
 * @property-read string|null $thumbnail_url
 * @property int $price Integer rupiah.
 * @property bool $is_free
 * @property CourseStatus $status
 * @property bool $drip_enabled
 * @property int|null $access_duration_days
 * @property bool $certificate_enabled
 * @property Carbon|null $published_at
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property Carbon|null $updated_at
 * @property Carbon|null $deleted_at
 */
#[Fillable([
    'category_id', 'title', 'slug', 'summary', 'description', 'thumbnail_path',
    'price', 'is_free', 'status', 'drip_enabled', 'access_duration_days',
    'certificate_enabled', 'published_at', 'created_by',
])]
class Course extends Model
{
    /** @use HasFactory<CourseFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'price' => 'integer',
            'is_free' => 'boolean',
            'status' => CourseStatus::class,
            'drip_enabled' => 'boolean',
            'access_duration_days' => 'integer',
            'certificate_enabled' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * Courses visible to the public: published and with a publish date not in the future.
     *
     * @param  Builder<Course>  $query
     */
    #[Scope]
    protected function published(Builder $query): void
    {
        $query->where('status', CourseStatus::Published)
            ->where(fn (Builder $query) => $query
                ->whereNull('published_at')
                ->orWhere('published_at', '<=', now()));
    }

    /**
     * Eager loads what a course card needs (avoids N+1 on listings).
     *
     * @param  Builder<Course>  $query
     */
    #[Scope]
    protected function withCardRelations(Builder $query): void
    {
        $query->with([
            'category:id,name,slug',
            'instructors:users.id,users.name',
        ]);
    }

    public function isPublished(): bool
    {
        return $this->status === CourseStatus::Published
            && ($this->published_at === null || $this->published_at->isPast());
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function thumbnailUrl(): Attribute
    {
        return Attribute::get(fn (): ?string => $this->thumbnail_path
            ? Storage::disk('public')->url($this->thumbnail_path)
            : null);
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return BelongsToMany<User, $this, CourseInstructor, 'assignment'>
     */
    public function instructors(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'course_instructors')
            ->using(CourseInstructor::class)
            ->as('assignment')
            ->withPivot('role')
            ->withTimestamps();
    }

    public function hasInstructor(User $user): bool
    {
        return $this->instructors()->whereKey($user->getKey())->exists();
    }
}
