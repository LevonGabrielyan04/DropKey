<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class SendUser extends Pivot
{
    public $incrementing = false;

    public $timestamps = false;
}
