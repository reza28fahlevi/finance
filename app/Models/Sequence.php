<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['key', 'last_number'])]
class Sequence extends Model
{
    protected function casts(): array
    {
        return ['last_number' => 'integer'];
    }
}
