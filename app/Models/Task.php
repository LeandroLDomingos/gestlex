<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Carbon\Carbon;

class Task extends Model
{
    use HasFactory, HasUuids;

    public const STATUS_PENDING = 'Pendente';
    public const STATUS_IN_PROGRESS = 'Em Andamento';
    public const STATUS_COMPLETED = 'Concluída';
    public const STATUS_CANCELLED = 'Cancelada'; // Corrigido de CANCELED para CANCELLED para consistência

    public const STATUSES = [
        self::STATUS_PENDING => 'Pendente',
        self::STATUS_IN_PROGRESS => 'Em Andamento',
        self::STATUS_COMPLETED => 'Concluída',
        self::STATUS_CANCELLED => 'Cancelada', // Corrigido
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
        'contact_id',
        'title',
        'description',
        'due_date',
        'responsible_user_id',
        'status',
        'priority', // Adicionado priority aqui, pois estava faltando na migration original
        'completed_at',
    ];

    protected $casts = [
        'due_date' => 'date:Y-m-d',
        'completed_at' => 'datetime',
    ];

    public function uniqueIds(): array
    {
        return ['id'];
    }

    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class, 'process_id');
    }

    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    public function responsibleUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_user_id');
    }

    public function responsibles(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'task_responsibles', 'task_id', 'user_id')->withTimestamps();
    }

    public function associatedContacts(): BelongsToMany
    {
        // CORRIGIDO: Usar o nome da tabela pivot correto 'task_contacts'
        return $this->belongsToMany(Contact::class, 'task_contacts', 'task_id', 'contact_id')->withTimestamps();
    }

    public function getStatusLabelAttribute(): string
    {
        return self::STATUSES[$this->status] ?? $this->status;
    }

    public function getPriorityLabelAttribute(): string
    {
        return self::PRIORITIES[$this->priority] ?? $this->priority;
    }

    public function getIsOverdueAttribute(): bool
    {
        if (!$this->due_date) {
            return false;
        }
        $dueDate = $this->due_date instanceof Carbon ? $this->due_date : Carbon::parse($this->due_date);
        // Considera atrasada se não estiver Concluída nem Cancelada e a data passou
        return !in_array($this->status, [self::STATUS_COMPLETED, self::STATUS_CANCELLED]) && $dueDate->isPast();
    }

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'is_overdue',
        'status_label',
        'priority_label',
    ];
}
