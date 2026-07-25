<?php

declare(strict_types=1);

namespace App\Models;

use App\Http\Resources\ChatMessageCollection;
use App\Http\Resources\ChatMessageResource;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\UseResource;
use Illuminate\Database\Eloquent\Attributes\UseResourceCollection;
use Illuminate\Database\Eloquent\Casts\AsBinary;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\BinaryCodec;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property string $id
 * @property string $public_id
 * @property string $conversation_id
 * @property int $sender_id
 * @property string $payload
 * @property bool $is_viewed
 * @property Carbon $created_at
 */
#[Fillable(['conversation_id', 'sender_id', 'payload'])]
#[Hidden(['id'])]
#[UseResource(ChatMessageResource::class)]
#[UseResourceCollection(ChatMessageCollection::class)]
class ChatMessage extends Model
{
    use HasUuids;

    public const UPDATED_AT = null;

    /**
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_viewed' => false,
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'created_at' => 'datetime',
            'public_id' => AsBinary::uuid(),
            'payload' => 'encrypted',
            'is_viewed' => 'boolean',
        ];
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

    /**
     * Resolve route model bindings using the public identifier.
     */
    public function getRouteKeyName(): string
    {
        return 'public_id';
    }

    /**
     * Retrieve the model for a bound value, encoding the public identifier
     * to match the binary column it is stored in.
     */
    public function resolveRouteBindingQuery($query, $value, $field = null)
    {
        $field ??= $this->getRouteKeyName();

        if ($field === 'public_id' && Str::isUuid($value)) {
            $value = BinaryCodec::encode($value, 'uuid');
        }

        return parent::resolveRouteBindingQuery($query, $value, $field);
    }

    /**
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sender_id');
    }
}
