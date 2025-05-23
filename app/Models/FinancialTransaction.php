<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\SoftDeletes; // Adicionar se usou softDeletes na migration

class FinancialTransaction extends Model
{
    use HasFactory, HasUuids, SoftDeletes; // Adicionar SoftDeletes se usou na migration

    protected $fillable = [
        'description',
        'amount',
        'type',
        'transaction_date',
        'process_id',
        'contact_id',
        'created_by_user_id',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'transaction_date' => 'date:Y-m-d',
        'process_id' => 'string', // Se process.id for UUID
        'contact_id' => 'string', // Se contact.id for UUID
        'created_by_user_id' => 'string', // Se user.id for UUID
    ];

    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class, 'process_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    // Accessor para facilitar a exibição do tipo
    public function getTypeLabelAttribute(): string
    {
        return $this->type === 'income' ? 'Entrada' : 'Saída';
    }

    // Opcional: Adicionar ao $appends se quiser que seja sempre incluído no JSON
    // protected $appends = ['type_label'];
}
