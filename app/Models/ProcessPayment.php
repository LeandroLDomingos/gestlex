<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Enums\PaymentType; // Seu Enum de tipos de pagamento
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class ProcessPayment extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    // Constantes para status para uso interno no código
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REFUNDED = 'refunded';

    // Mapeamento de status para rótulos em Português
    public static array $statuses = [
        self::STATUS_PENDING => 'Pendente',
        self::STATUS_PAID => 'Pago',
        self::STATUS_FAILED => 'Falhou',
        self::STATUS_REFUNDED => 'Reembolsado',
    ];

    protected $fillable = [
        'id', // Se não estiver usando HasUuids para auto-geração, mantenha. Caso contrário, pode remover.
        'process_id',
        'total_amount',         // Valor da transação (entrada, parcela, pagamento único)
        'down_payment_amount',  // Valor da entrada (se esta transação for uma entrada)
        'payment_type',         // Tipo original do pagamento (a_vista, parcelado)
        'payment_method',
        'down_payment_date',    // Data da entrada
        'number_of_installments',// Número total de parcelas do plano original
        'value_of_installment', // Valor desta parcela específica
        'interest_amount',
        'status',               // Status desta transação: pending, paid, failed, refunded
        'first_installment_due_date', // Data de vencimento desta transação/parcela
        'notes',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'down_payment_amount' => 'decimal:2',
        'value_of_installment' => 'decimal:2',
        'down_payment_date' => 'date',
        'first_installment_due_date' => 'date',
        'payment_type' => PaymentType::class, // Seu Enum
        'number_of_installments' => 'integer',
        'status' => 'string', // O tipo ENUM na migration já cuida da restrição no DB
    ];

    /**
     * Get the status label.
     *
     * @return \Illuminate\Database\Eloquent\Casts\Attribute
     */
    protected function statusLabel(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => self::$statuses[$attributes['status']] ?? ucfirst($attributes['status'] ?? 'N/A'),
        );
    }

    /**
     * Get all status options for frontend select/display.
     *
     * @return array
     */
    public static function getStatusesForFrontend(): array
    {
        return collect(self::$statuses)->map(fn($label, $key) => ['key' => $key, 'label' => $label])->values()->all();
    }


    public function process()
    {
        return $this->belongsTo(Process::class, 'process_id');
    }
}
