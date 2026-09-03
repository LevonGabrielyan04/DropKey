<?php

declare(strict_types=1);

namespace App\Models\Traits;

use Illuminate\Support\Str;

trait HasPublicAndPrivateIds
{
    public function setUniqueIds(): void
    {
        if (empty($this->{$this->getKeyName()})) {
            $this->{$this->getKeyName()} = (string) Str::uuid7();
        }

        if (empty($this->public_id)) {
            $this->public_id = (string) Str::uuid();
        }
    }
}
