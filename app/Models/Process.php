<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
// Importar outros modelos necessários
use App\Models\Contact;
use App\Models\User; // Adicionado para o relacionamento 'responsible'
use App\Models\ProcessAnnotation;
use App\Models\ProcessDocument; // Adicionado para o relacionamento 'documents'
use App\Models\Task;

class Process extends Model
{
    use HasUuids;
    use HasFactory;

    protected $fillable = [
        'title',
        'origin',
        'negotiated_value',
        'description',
        'responsible_id', // Chave estrangeira para User
        'workflow',
        'stage',
        'contact_id',     // Chave estrangeira para Contact
        'priority',       // <<< ADICIONADO
        'status',         // <<< ADICIONADO
        'due_date',       // <<< ADICIONADO
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'negotiated_value' => 'decimal:2',
        'stage' => 'integer',
        'responsible_id' => 'string', // Se o ID do User for UUID
        'contact_id' => 'string',     // Se o ID do Contact for UUID
        'due_date' => 'date',         // <<< ADICIONADO
    ];

    /**
     * Get the user responsible for the process.
     */
    public function responsible(): BelongsTo
    {
        return $this->belongsTo(User::class, 'responsible_id');
    }

    /**
     * Get the contact associated with the process.
     */
    public function contact(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'contact_id');
    }

    /**
     * Get all of the annotations for the Process.
     */
    public function annotations(): HasMany
    {
        return $this->hasMany(ProcessAnnotation::class, 'process_id', 'id')->latest();
    }

    /**
     * Get all of the documents for the Process.
     */
    public function documents(): HasMany
    {
        return $this->hasMany(ProcessDocument::class, 'process_id', 'id')->orderBy('created_at', 'desc');
    }
    
    /**
     * Get all of the tasks for the Process.
     */
    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class, 'process_id', 'id')->orderBy('due_datetime', 'asc')->orderBy('created_at', 'asc');
    }

    // Constantes para Workflows
    public const WORKFLOW_PROSPECTING = 'prospecting';
    public const WORKFLOW_CONSULTATIVE = 'consultative';
    public const WORKFLOW_ADMINISTRATIVE = 'administrative';
    public const WORKFLOW_JUDICIAL = 'judicial';

    public const WORKFLOWS = [
        self::WORKFLOW_PROSPECTING => 'Prospecção',
        self::WORKFLOW_CONSULTATIVE => 'Consultivo',
        self::WORKFLOW_ADMINISTRATIVE => 'Administrativo',
        self::WORKFLOW_JUDICIAL => 'Judicial',
    ];

    // Constantes para Estágios (Stages)
    public const STAGES_PROSPECTING = [
        1 => 'Contato inicial',
        2 => 'Coleta documental',
        3 => 'Avaliação jurídica',
        4 => 'Envio de proposta',
        5 => 'Negociação',
    ];
    public const STAGES_CONSULTATIVE = [
        1 => 'Briefing',
        2 => 'Análise',
        3 => 'Parecer',
    ];
    public const STAGES_ADMINISTRATIVE = [
        1 => 'Análise Inicial AdM',
        2 => 'Protocolo AdM',
        3 => 'Acompanhamento AdM',
        4 => 'Recurso AdM',
        5 => 'Conclusão AdM',
    ];
    public const STAGES_JUDICIAL = [
        1 => 'Petição Inicial Jud',
        2 => 'Citação Jud',
        3 => 'Contestação Jud',
        4 => 'Instrução Jud',
        5 => 'Sentença Jud',
        6 => 'Recursos Jud',
        7 => 'Execução Jud',
    ];

    // Constantes para Prioridades
    public const PRIORITY_LOW = 'low';
    public const PRIORITY_MEDIUM = 'medium';
    public const PRIORITY_HIGH = 'high';

    public const PRIORITIES = [
        self::PRIORITY_LOW => 'Baixa',
        self::PRIORITY_MEDIUM => 'Média',
        self::PRIORITY_HIGH => 'Alta',
    ];
    
    // Constantes para Status (Exemplo, ajuste conforme necessário)
    public const STATUS_OPEN = 'Aberto';
    public const STATUS_IN_PROGRESS = 'Em Andamento';
    public const STATUS_PENDING_CLIENT = 'Pendente Cliente';
    public const STATUS_COMPLETED = 'Concluído';
    public const STATUS_CANCELED = 'Cancelado';
    // Adicione outros que você usa no seu sistema
    public const STATUSES = [
        self::STATUS_OPEN => 'Aberto',
        self::STATUS_IN_PROGRESS => 'Em Andamento',
        self::STATUS_PENDING_CLIENT => 'Pendente Cliente',
        self::STATUS_COMPLETED => 'Concluído',
        self::STATUS_CANCELED => 'Cancelado',
    ];


    public static function getStagesForWorkflow(string $workflowKey): array
    {
        return match ($workflowKey) {
            self::WORKFLOW_PROSPECTING => self::STAGES_PROSPECTING,
            self::WORKFLOW_CONSULTATIVE => self::STAGES_CONSULTATIVE,
            self::WORKFLOW_ADMINISTRATIVE => self::STAGES_ADMINISTRATIVE,
            self::WORKFLOW_JUDICIAL => self::STAGES_JUDICIAL,
            default => [],
        };
    }

    public function getWorkflowLabelAttribute(): string
    {
        return self::WORKFLOWS[$this->workflow] ?? ucfirst((string) $this->workflow);
    }

    public function getStageLabelAttribute(): ?string
    {
        if (is_null($this->stage) || is_null($this->workflow)) {
            return null;
        }
        $stages = self::getStagesForWorkflow($this->workflow);
        return $stages[$this->stage] ?? "Estágio {$this->stage}";
    }

    /**
     * Obtém o rótulo da prioridade do processo.
     */
    public function getPriorityLabelAttribute(): string
    {
        if (is_null($this->priority)) {
            return 'Não definida';
        }
        return self::PRIORITIES[$this->priority] ?? ucfirst((string) $this->priority);
    }
    
    /**
     * Obtém o rótulo do status do processo.
     */
    public function getStatusLabelAttribute(): string
    {
        if (is_null($this->status)) {
            return 'Não definido';
        }
        // Se você definir self::STATUSES como no exemplo acima, use-o:
        return self::STATUSES[$this->status] ?? ucfirst((string) $this->status);
        // Caso contrário, se status for um texto livre, apenas retorne ele:
        // return ucfirst((string) $this->status);
    }

    /**
     * The accessors to append to the model's array form.
     *
     * @var array
     */
    protected $appends = [
        'workflow_label', 
        'stage_label',
        'priority_label', // Adicionado
        'status_label',   // Adicionado
    ];
}
