<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Agent extends Model
{
    protected $fillable = ['name', 'model'];

    public function documents(): HasMany
    {
        return $this->hasMany(Document::class);
    }
}
