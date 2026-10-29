<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class Segment extends Model
{
    use SoftDeletes;

    protected $table = 'pm_segments';

    protected $fillable = ['name', 'filters'];

    protected function casts(): array
    {
        return ['filters' => 'array'];
    }
}
