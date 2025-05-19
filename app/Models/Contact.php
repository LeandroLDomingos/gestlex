<?php

namespace App\Models;

use App\Enums\ContactGender;
use App\Enums\ContactMaritalStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUuids; // Se Contact usa UUIDs
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo; // Para administrator_id

class Contact extends Model
{
    use HasFactory;
    use HasUuids; // Adicione se sua tabela 'contacts' usa UUIDs para a chave primária

    protected $fillable = [
        'type',
        'name',
        'business_name',
        'cpf_cnpj',
        'rg',
        'gender',
        'nationality',
        'marital_status',
        'profession',
        'date_of_birth',
        'zip_code',
        'address',
        'neighborhood',
        'city',
        'state',
        'complement',
        'number',
        'business_activity',
        'tax_state',
        'tax_city',
        'administrator_id',
        // Adicione 'id' se você o define manualmente e não é auto-gerado
    ];

    protected $casts = [
        'date_of_birth' => 'date:Y-m-d',
        'administrator_id' => 'string', // Se for UUID
        // Adicione outros casts conforme necessário
    ];

    // Relacionamento com emails (já deve existir se você seguiu os exemplos anteriores)
    public function emails(): HasMany
    {
        return $this->hasMany(ContactEmail::class);
    }

    // Relacionamento com phones (já deve existir)
    public function phones(): HasMany
    {
        return $this->hasMany(ContactPhone::class);
    }

    // Novo: Relacionamento com anotações do contato
    public function annotations(): HasMany
    {
        return $this->hasMany(ContactAnnotation::class, 'contact_id', 'id')->latest();
    }

    // Novo: Relacionamento com documentos do contato
    public function documents(): HasMany
    {
        return $this->hasMany(ContactDocument::class, 'contact_id', 'id')->orderBy('created_at', 'desc');
    }

    // Novo: Relacionamento com processos/casos onde este contato é o principal
    // Assumindo que a tabela 'processes' tem uma coluna 'contact_id'
    public function processes(): HasMany
    {
        return $this->hasMany(Process::class, 'contact_id', 'id')->orderBy('updated_at', 'desc');
    }

    // Se um contato (Pessoa Física) pode ser administrador de outro contato (Pessoa Jurídica)
    // Este é o inverso do que está no Processo (responsible_id).
    // Se você tem um campo 'administrator_id' na tabela 'contacts' referenciando outro contato:
    public function administrator(): BelongsTo
    {
        return $this->belongsTo(Contact::class, 'administrator_id'); // Um contato pode ser administrado por outro contato
    }

    // Se este contato (Pessoa Física) é o administrador de outros contatos (Pessoas Jurídicas)
    public function administeredContacts(): HasMany
    {
        return $this->hasMany(Contact::class, 'administrator_id', 'id');
    }

    // Accessor para nome de exibição (útil no frontend)
    public function getDisplayNameAttribute(): string
    {
        if ($this->type === 'physical') {
            return $this->name ?? 'N/A';
        }
        return $this->business_name ?: $this->name ?? 'N/A';
    }

    public function getGenderLabelAttribute(): string
    {
        try {
            return ContactGender::from($this->gender)->label();
        } catch (\ValueError) {
            return 'Desconhecido';
        }
    }

    public function getMaritalStatusLabelAttribute(): string
    {
        if (is_null($this->marital_status)) {
            return 'Não informado'; // Ou 'N/A', ou string vazia, conforme preferir
        }
        try {
            // Usa o Enum ContactMaritalStatus para obter o rótulo
            return ContactMaritalStatus::from($this->marital_status)->label();
        } catch (\ValueError) {
            // Retorna 'Desconhecido' caso o valor seja inválido ou não existente no Enum
            // Ou você pode retornar o valor bruto: return $this->marital_status;
            return 'Desconhecido';
        }
    }
    protected $appends = ['gender_label', 'marital_status_label'];
}
