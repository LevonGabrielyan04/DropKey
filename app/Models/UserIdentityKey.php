<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Casts\AsBinary;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property int $user_id
 * @property string $browser_db_id
 * @property array<string, mixed> $public_key_jwk
 * @property string $fingerprint
 * @property Carbon $created_at
 */
#[Fillable(['user_id', 'browser_db_id', 'public_key_jwk', 'fingerprint'])]
#[Hidden(['user_id', 'id'])]
class UserIdentityKey extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'browser_db_id' => AsBinary::ulid(),
            'public_key_jwk' => 'array',
            'created_at' => 'datetime',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (UserIdentityKey $identityKey): void {
            if (blank($identityKey->browser_db_id)) {
                $identityKey->browser_db_id = (string) Str::ulid();
            }
        });
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
