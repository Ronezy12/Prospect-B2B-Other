<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class SocialOpposition extends Model
{
    public const UPDATED_AT = null;

    protected $table = 'pm_social_oppositions';

    protected $fillable = ['fingerprint', 'platform', 'note', 'created_by'];
}
