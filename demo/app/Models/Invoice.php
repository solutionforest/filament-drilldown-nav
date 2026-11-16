<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Invoice extends Model
{
    use HasFactory;

    protected $fillable = ['number', 'amount', 'status', 'due_on'];

    protected $casts = ['due_on' => 'date'];

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }
}
