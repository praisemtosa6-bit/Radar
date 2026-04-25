<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Opportunity extends Model
{
    protected $fillable = [
        'title', 'company', 'description', 'tags', 'salary',
        'location', 'url', 'source', 'posted_at',
    ];

    protected function casts(): array
    {
        return [
            'tags' => 'array',
            'posted_at' => 'datetime',
        ];
    }
}
