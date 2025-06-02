<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\SoftDeletes;
use App\Enums\PaymentType; // Seu Enum de tipos de pagamento
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ProcessPayment extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    // Constantes para status para uso interno no código
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';
    public const STATUS_REFUNDED = 'refunded';
    public const STATUS_OVERDUE = 'overdue'; // <<< CONSTANTE ADICIONADA

    // Mapeamento de status para rótulos em Português
    public static array $statuses = [
        self::STATUS_PENDING => 'Pendente',
        self::STATUS_PAID => 'Pago',
        self::STATUS_FAILED => 'Falhou',
        self::STATUS_REFUNDED => 'Reembolsado',
        self::STATUS_OVERDUE => 'Vencido', // <<< RÓTULO ADICIONADO
    ];

    protected $fillable = [
        'id',
        'process_id',
        'total_amount',
        'down_payment_amount',
        'payment_type',
        'payment_method',
        'down_payment_date',
        'number_of_installments',
        'value_of_installment',
        'interest_amount',
        'status',
        'first_installment_due_date',
        'notes',
        'transaction_nature',
        'supplier_contact_id',
    ];

    protected $casts = [
        'total_amount' => 'decimal:2',
        'down_payment_amount' => 'decimal:2',
        'value_of_installment' => 'decimal:2',
        'down_payment_date' => 'date',
        'first_installment_due_date' => 'date',
        'payment_type' => PaymentType::class,
        'number_of_installments' => 'integer',
        'status' => 'string',
        'transaction_nature' => 'string',
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

    /**
     * Get the process that owns the payment.
     */
    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class, 'process_id');
    }

    /**
     * Get the supplier contact associated with this payment (for expenses).
     */
    public function supplierContact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'supplier_contact_id');
    }
}
