<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
// use Illuminate\Database\Eloquent\SoftDeletes; // 1. Remover ou comentar esta linha
use Carbon\Carbon;

class Task extends Model
{
    use HasFactory, HasUuids; // 2. Remover SoftDeletes daqui

    public const STATUS_PENDING = 'Pendente';
    public const STATUS_IN_PROGRESS = 'Em Andamento';
    public const STATUS_COMPLETED = 'Concluída';
    public const STATUS_CANCELLED = 'Cancelada';

    public const STATUSES = [
        self::STATUS_PENDING => 'Pendente',
        self::STATUS_IN_PROGRESS => 'Em Andamento',
        self::STATUS_COMPLETED => 'Concluída',
        self::STATUS_CANCELLED => 'Cancelada',
    ];

    public const PRIORITY_LOW = 'Baixa';
    public const PRIORITY_MEDIUM = 'Média';
    public const PRIORITY_HIGH = 'Alta';

    public const PRIORITIES = [
        self::PRIORITY_LOW => 'Baixa',
        self::PRIORITY_MEDIUM => 'Média',
        self::PRIORITY_HIGH => 'Alta',
    ];


    protected $fillable = [
        'process_id',
        'title',
        'description',
        'due_date',
        'responsible_user_id', // Responsável principal (singular)
        'status',
        'priority',
        'completed_at',
    ];

    protected $casts = [
        'due_date' => 'date:Y-m-d',
        'completed_at' => 'datetime',
        // 'id' => 'string', // UUIDs são strings, mas o Eloquent geralmente lida bem sem cast explícito
    ];

    /**
     * Get the columns that should receive a unique identifier.
     *
     * @return array<int, string>
     */
    public function uniqueIds(): array
    {
        return ['id']; // Garante que a coluna 'id' use UUIDs
    }

    // Relacionamento com o Processo ao qual a tarefa pertence
    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class);
    }

    // Relacionamento com o Usuário responsável principal pela tarefa
    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    // Relacionamento Muitos-para-Muitos com Usuários (múltiplos responsáveis)
    public function responsibles(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_user', 'task_id', 'user_id')->withTimestamps();
    }

    // Relacionamento Muitos-para-Muitos com Contatos
    public function contacts(): BelongsToMany
    {
        return $this->belongsToMany(Contact::class, 'task_contact', 'task_id', 'contact_id')->withTimestamps();
    }

    // Accessor para o label do status
    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    // Accessor para o label da prioridade
    public function getPriorityLabelAttribute(): string
    {
        return self::PRIORITIES[$this->priority] ?? $this->priority;
    }

    // Verifica se a tarefa está atrasada
    public function getIsOverdueAttribute(): bool
    {
        if (!$this->due_date) {
            return false;
        }
        // Garante que due_date seja um objeto Carbon para comparação segura
        $dueDate = $this->due_date instanceof Carbon ? $this->due_date : Carbon::parse($this->due_date);
        return !$this->completed_at && $dueDate->isPast();
    }
}
