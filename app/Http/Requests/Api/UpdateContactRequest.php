<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;

class UpdateContactRequest extends StoreContactRequest
{
    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $contactId = $this->route('contact'); // Pega o ID do contato da URL
        $rules = parent::rules();

        $rules['cpf_cnpj'] = [
            'required_if:type,individual', 
            'nullable', 
            'string', 
            'size:11',
            'cpf_ou_cnpj',
            "unique:contacts,cpf_cnpj,{$contactId},id", // Ignora o próprio ID ao verificar unicidade
        ];
        return $rules;
    }
}
