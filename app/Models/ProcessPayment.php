<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Str; // Importar a classe Str para gerar UUIDs

class ProcessPayment extends Model
{
    use HasFactory, SoftDeletes;

    // Nome da tabela (se for diferente do plural do nome do model em snake_case)
    // protected $table = 'process_payments'; // Descomente se o nome da sua tabela for diferente

    /**
     * Indica que a chave primária não é auto-incremental.
     *
     * @var bool
     */
    public $incrementing = false;

    /**
     * O tipo da chave primária.
     *
     * @var string
     */
    protected $keyType = 'string';

    /**
     * Os atributos que são atribuíveis em massa.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'process_id',
        'amount',
        'payment_method',
        'payment_date',
        'status',
        'notes',
        // 'id' não precisa estar no fillable se for gerado automaticamente
    ];

    /**
     * Os atributos que devem ser convertidos para tipos nativos.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'amount' => 'decimal:2', // Garante que o valor seja tratado como decimal com 2 casas
        'payment_date' => 'date', // Converte para objeto Carbon/Date
    ];

    /**
     * O "boot" method do model.
     *
     * É executado quando o model é inicializado. Usamos para adicionar um listener
     * ao evento 'creating', que é disparado antes de um novo registro ser salvo.
     * Assim, garantimos que cada novo ProcessPayment tenha um UUID.
     */
    protected static function boot()
    {
        parent::boot(); // Chama o boot method da classe pai

        // Adiciona um listener para o evento 'creating' (antes de salvar um novo registro)
        static::creating(function ($model) {
            // Se o modelo ainda não tem um ID (para evitar sobrescrever em cenários específicos)
            // e a chave primária não é auto-incremental (o que já definimos com $incrementing = false)
            if (empty($model->{$model->getKeyName()})) {
                // Gera um UUID e atribui à chave primária do modelo
                $model->{$model->getKeyName()} = (string) Str::uuid();
            }
        });
    }

    /**
     * Define o relacionamento inverso com Process.
     * Um pagamento pertence a um processo.
     */
    public function process()
    {
        return $this->belongsTo(Process::class);
    }
}
