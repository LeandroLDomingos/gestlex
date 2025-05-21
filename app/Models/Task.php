<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids; // Adicione se Task usa UUIDs
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Carbon\Carbon; // Para o acessor is_overdue

class Task extends Model
{
    use HasFactory;
    // use HasUuids; // Descomente se a tabela 'tasks' usa UUIDs como chave primária

    protected $fillable = [
        'process_id',
        'responsible_user_id',
        'title',
        'description',
        'due_date',
        'status',
        'completed_at',
        // 'user_id', // Se você tiver um campo para quem criou a tarefa
    ];

    protected $casts = [
        'due_date' => 'date',
        'completed_at' => 'datetime',
        'process_id' => 'string', // Ajuste para 'integer' se process.id for int
        'responsible_user_id' => 'string', // Ajuste para 'integer' se user.id for int
        // 'user_id' => 'string', // Ajuste para 'integer' se user.id for int
    ];

    // Constantes para Status da Tarefa <<< ADICIONADO AQUI
    public const STATUS_PENDING = 'Pendente';
    public const STATUS_IN_PROGRESS = 'Em Andamento';
    public const STATUS_COMPLETED = 'Concluída';
    public const STATUS_CANCELED = 'Cancelada';

    public const STATUSES = [
        self::STATUS_PENDING => 'Pendente',
        self::STATUS_IN_PROGRESS => 'Em Andamento',
        self::STATUS_COMPLETED => 'Concluída',
        self::STATUS_CANCELED => 'Cancelada',
    ];
    // Fim das Constantes de Status

    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class);
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    // Opcional: Usuário que criou a tarefa
    // public function creatorUser(): BelongsTo
    // {
    //     return $this->belongsTo(User::class, 'user_id');
    // }

    /**
     * Accessor para verificar se a tarefa está atrasada.
     */
    public function getIsOverdueAttribute(): bool
    {
        // Só está atrasada se tiver uma data de vencimento, não estiver concluída e a data já passou
        return $this->due_date && 
               $this->due_date instanceof Carbon && // Garante que é um objeto Carbon
               $this->due_date->isPast() && 
               $this->status !== self::STATUS_COMPLETED &&
               $this->status !== self::STATUS_CANCELED;
    }

    /**
     * Accessor para o label do status.
     */
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? ucfirst((string)$this->status);
    }


    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'is_overdue', // Para incluir no JSON
        'status_label', // Para incluir o label do status
    ];
}
