<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
// Se 'process_id' for UUID e você quiser que o Laravel o trate como tal no modelo Task,
// e se o ID da Task não for UUID, não precisa de HasUuids aqui.
// Se o ID da Task fosse UUID, você usaria HasUuids.

class Task extends Model
{
    use HasFactory;

    // A chave primária é 'id' e é auto-incrementável por padrão (bigIncrements)
    // public $incrementing = true;
    // protected $keyType = 'int';

    protected $fillable = [
        'title',
        'due_datetime',
        'status',
        'tags', // Assumindo que tags é uma string (ex: JSON ou CSV)
        'description',
        'process_id',
        // 'responsible_user_id' // Não presente na sua migration de tasks, mas estava na minha sugestão anterior
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array
     */
    protected $casts = [
        'due_datetime' => 'datetime',
        'process_id' => 'string', // Cast para string pois process_id é UUID
        // Se 'tags' for armazenado como JSON:
        // 'tags' => 'array',
    ];

    /**
     * Get the process that owns the task.
     */
    public function process(): BelongsTo
    {
        return $this->belongsTo(Process::class, 'process_id', 'id');
    }

    // Se você adicionar um responsible_user_id à sua tabela 'tasks' no futuro:
    // public function responsibleUser(): BelongsTo
    // {
    //     return $this->belongsTo(User::class, 'responsible_user_id');
    // }

    // Exemplo de acessor para verificar se a tarefa está atrasada
    public function getIsOverdueAttribute(): bool
    {
        return $this->due_datetime && $this->due_datetime < now() && $this->status !== 'completed';
    }
}
