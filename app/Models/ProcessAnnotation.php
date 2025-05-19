<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids; // Use se 'id' for UUID
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessAnnotation extends Model
{
    use HasFactory;
    use HasUuids; // Descomente ou remova se 'id' não for UUID

    protected $fillable = [
        'process_id',
        'user_id',
        'content',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'process_id' => 'string', // Se process_id for UUID
        'user_id' => 'string',    // Se user_id for UUID
    ];

    /**
     * Get the process that owns the annotation.
     */
    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class, 'process_id');
    }

    /**
     * Get the user who created the annotation.
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
