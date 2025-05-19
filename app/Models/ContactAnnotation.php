<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids; // Use se 'id' for UUID
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ContactAnnotation extends Model
{
    use HasFactory;
    use HasUuids; // Remova se 'id' não for UUID

    protected $fillable = [
        'contact_id',
        'user_id',
        'content',
    ];

    protected $casts = [
        'contact_id' => 'string', // Se UUID
        'user_id' => 'string',    // Se UUID
    ];

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
