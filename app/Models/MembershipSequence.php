<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class MembershipSequence extends Model
{
    protected $fillable = [
        'prefix',
        'next_value',
    ];
}
