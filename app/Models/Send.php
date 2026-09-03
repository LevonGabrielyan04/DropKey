<?php

namespace App\Models;

use App\Models\Traits\HasPublicAndPrivateIds;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Table;
use Illuminate\Database\Eloquent\Attributes\WithoutTimestamps;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

#[Table(key: 'id', keyType: 'string', incrementing: false)]
#[WithoutTimestamps]
#[Hidden(['user_id', 'id'])]
class Send extends Model
{
    use HasPublicAndPrivateIds, HasUuids;

    /**
     * The attributes that aren't mass assignable.
     *
     * @var list<string>
     */
    protected $guarded = [];

    /**
     * The attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'message' => 'encrypted',
        ];
    }

    /**
     * Resolve route model bindings using the public identifier.
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * Generate a new UUID for the public identifier.
     */
    public function newUniqueId(): string
    {
        return (string) Str::uuid();
    }

    /**
     * The users authorized for this send.
     *
     * @return BelongsToMany<User, $this, SendUser, 'pivot'>
     */
    public function authorizedUsers(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'send_user')
            ->using(SendUser::class);
    }

    /**
     * Get the columns that should receive a unique identifier.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['id', 'public_id'];
    }
}
