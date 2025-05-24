<?php

namespace App\Models;

use App\Enums\PaymentType; // Importar o Enum
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str;

class ProcessPayment extends Model
{
    use HasFactory, SoftDeletes;

    public $incrementing = false;
    protected $keyType = 'string';

    protected $fillable = [
        'process_id',
        'amount',
        'payment_type', // Adicionado
        'payment_method',
        'payment_date',
        'status',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'payment_date' => 'date',
        'payment_type' => PaymentType::class, // Cast para o Enum
    ];

    // Adiciona o label do tipo de pagamento à serialização
    protected $appends = ['payment_type_label'];

    protected static function boot()
    {
        parent::boot();
        static::creating(function ($model) {
            if (empty($model->{$model->getKeyName()})) {
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    public function process()
    {
        return $this->belongsTo(Process::class);
    }

    // Accessor para o label do tipo de pagamento
    public function getPaymentTypeLabelAttribute(): ?string
    {
        return $this->payment_type?->label();
    }
}

