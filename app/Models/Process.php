<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany; // Certifique-se de importar HasMany
// Importar outros modelos necessários
use App\Models\Contact;
use App\Models\ProcessAnnotation;
use App\Models\Task; // <<< IMPORTAR O MODELO Task

class Process extends Model
{
    use HasUuids;
    use HasFactory;

    protected $fillable = [
        'title',
        'origin',
        'negotiated_value',
        'description',
        'responsible_id',
        'workflow',
        'stage',
        'contact_id',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'negotiated_value' => 'decimal:2',
        'stage' => 'integer',
        'responsible_id' => 'string',
        'contact_id' => 'string',
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

    public function documents(): HasMany // <<< MÉTODO ADICIONADO AQUI
    {
        // 'process_id' é a chave estrangeira na tabela 'process_documents'
        // 'id' (ou a chave primária do Process) é a chave local
        return $this->hasMany(ProcessDocument::class, 'process_id', 'id')->orderBy('created_at', 'desc');
    }
    
    /**
     * Get all of the tasks for the Process.
     */
    public function tasks(): HasMany // <<< MÉTODO ADICIONADO/CORRIGIDO AQUI
    {
        // 'process_id' é a chave estrangeira na tabela 'tasks'
        // 'id' (ou a chave primária do Process) é a chave local
        return $this->hasMany(Task::class, 'process_id', 'id')->orderBy('due_datetime', 'asc')->orderBy('created_at', 'asc');
    }

    // ... (resto do seu modelo Process.php, incluindo constantes e outros métodos) ...

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
}
