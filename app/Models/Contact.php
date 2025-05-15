<?php

namespace App\Models;

use App\Enums\ContactGender;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Contact extends Model
{
    use HasFactory;

    protected $fillable = [
        'type', // Físico ou Jurídico
        'name',
        'cpf_cnpj',
        'rg',
        'gender',
        'nationality',
        'marital_status',
        'profession',
        'zip_code',
        'address',
        'neighborhood',
        'number',
        'complement',
        'city',
        'state',
        'country',
        'trade_name',
        'cpf_cnpj',
        'business_name',
        'business_activity',
        'tax_state',
        'tax_city',
        'administrator_id',
        'date_of_birth',
    ];

    /**
     * Relacionamento com e-mails do contato.
     */
    public function emails(): HasMany
    {
        return $this->hasMany(ContactEmail::class, 'contact_id');
    }

    /**
     * Relacionamento com telefones do contato.
     */

    
    public function phones(): HasMany
    {
        return $this->hasMany(ContactPhone::class, 'contact_id');
    }


    public function adminContact()
    {
        return $this->belongsTo(Contact::class, 'administrator_id');
    }
   public function getGenderLabelAttribute(): string
    {
        try {
            // Usa o Enum DocumentType para obter o rótulo associado ao tipo de documento
            return ContactGender::from($this->gender)->label();
        } catch (\ValueError) {
            // Retorna 'Desconhecido' caso o tipo seja inválido ou não existente
            return 'Desconhecido';
        }
    }

    // Campos adicionais que serão automaticamente adicionados na resposta do modelo
    protected $appends = ['gender_label'];

}
