<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TimePeriod;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\AsBinary;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\BinaryCodec;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $public_key
 * @property int $user_one_id
 * @property int $user_two_id
 * @property TimePeriod $auto_delete
 * @property Carbon $created_at
 */
#[Fillable(['user_one_id', 'user_two_id', 'auto_delete'])]
#[Hidden(['user_one_id', 'user_two_id', 'id'])]
class Conversation extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'auto_delete' => TimePeriod::class,
            'created_at' => 'datetime',
            'public_key' => AsBinary::uuid(),
        ];
    }

    /**
     * Get the columns that should receive a unique identifier.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['id', 'public_key'];
    }

    /**
     * Generate unique keys for the model.
     *
     * Internal ids stay time-ordered for database locality. Public keys use
     * random UUID v4 so broadcast channel names do not leak creation time.
     */
    public function setUniqueIds(): void
    {
        if (empty($this->id)) {
            $this->id = (string) Str::uuid7();
        }

        if (empty($this->public_key)) {
            $this->public_key = (string) Str::uuid();
        }
    }

    /**
     * Resolve route model bindings using the public identifier.
     */
    public function getRouteKeyName(): string
    {
        return 'public_key';
    }

    /**
     * Retrieve the model for a bound value, encoding the public identifier
     * to match the binary column it is stored in.
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $field ??= $this->getRouteKeyName();

        if ($field === 'public_key' && Str::isUuid($value)) {
            $value = BinaryCodec::encode($value, 'uuid');
        }

        return parent::resolveRouteBindingQuery($query, $value, $field);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function userOne(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_one_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function userTwo(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_two_id');
    }

    /**
     * @return HasMany<ChatMessage, $this>
     */
    public function messages(): HasMany
    {
        return $this->hasMany(ChatMessage::class);
    }

    public function includesUser(User $user): bool
    {
        return $this->user_one_id === $user->id || $this->user_two_id === $user->id;
    }

    public function partnerFor(User $user): User
    {
        if ($this->user_one_id === $user->id) {
            return $this->userTwo;
        }

        return $this->userOne;
    }
}
