<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Expense extends Model
{
    use HasFactory, HasUuids, SoftDeletes;

    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_CANCELLED = 'cancelled';

    public static array $statuses = [
        self::STATUS_PENDING => 'Pendente',
        self::STATUS_PAID => 'Paga',
        self::STATUS_CANCELLED => 'Cancelada',
    ];

    protected $fillable = [
        'id',
        'user_id',
        'process_id',
        // 'contact_id', // Se adicionar fornecedor
        'description',
        'amount',
        'expense_date',
        'due_date',
        'category',
        'payment_method',
        'status',
        'notes',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'expense_date' => 'date',
        'due_date' => 'date',
        'is_paid' => 'boolean', // Se você for usar um campo is_paid separado
    ];

    // Accesor para status_label
    protected function statusLabel(): Attribute
    {
        return Attribute::make(
            get: fn ($value, $attributes) => self::$statuses[$attributes['status']] ?? ucfirst($attributes['status'] ?? 'N/A'),
        );
    }

    public static function getStatusesForFrontend(): array
    {
        return collect(self::$statuses)->map(fn($label, $key) => ['key' => $key, 'label' => $label])->values()->all();
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function process()
    {
        return $this->belongsTo(Process::class, 'process_id');
    }

    // public function contact() // Se adicionar fornecedor
    // {
    //     return $this->belongsTo(Contact::class, 'contact_id');
    // }
}