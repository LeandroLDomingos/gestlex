<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessHistoryEntry extends Model
{
    use HasFactory;
    use HasUuids; // Para usar UUIDs como chave primária

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'process_history_entries';

    /**
     * Indicates if the model should be timestamped.
     * Apenas created_at é usado, então desabilitamos updated_at.
     *
     * @var bool
     */
    public const UPDATED_AT = null;


    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'process_id',
        'user_id',
        'action',
        'description',
        'old_value',
        'new_value',
        // 'created_at' é preenchido automaticamente
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'process_id' => 'string', // Se process.id for UUID
        'user_id' => 'string',    // Se user.id for UUID
        // Se old_value ou new_value forem JSON:
        // 'old_value' => 'array',
        // 'new_value' => 'array',
    ];

    /**
     * Get the process that owns the history entry.
     */
    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class, 'process_id');
    }

    /**
     * Get the user who performed the action (if any).
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /**
     * Accessor to get the user's name directly.
     * Isso é útil para o frontend.
     */
    public function getUserNameAttribute(): ?string
    {
        return $this->user?->name;
    }

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'user_name' // Para incluir o nome do usuário na serialização
    ];
}
