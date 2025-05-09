<?php

namespace App\Models;

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
        'fantasy_name',
        'cpf_cnpj',
        'business_name',
        'business_activity',
        'tax_state',
        'tax_city',
        'admin_contact_id',
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

    /**
     * Retorna todos os e-mails do contato como array.
     */
    public function getAllEmailsAttribute(): array
    {
        return $this->emails()->pluck('email')->toArray();
    }

    /**
     * Retorna todos os telefones do contato como array.
     */
    public function getAllPhonesAttribute(): array
    {
        return $this->phones()->pluck('phone')->toArray();
    }

    public function processes(): BelongsToMany
    {
        return $this->belongsToMany(Process::class, 'contact_processes');
    }

}
