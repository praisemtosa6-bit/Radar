<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class UserOpportunity extends Model
{
    public $timestamps = false;

    protected $fillable = ['user_id', 'opportunity_id', 'notified_at'];

    protected function casts(): array
    {
        return [
            'notified_at' => 'datetime',
        ];
    }
}
