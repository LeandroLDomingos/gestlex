<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreContactRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, \Illuminate\Contracts\Validation\ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::in(['physical', 'legal'])],

            // Campos para Pessoa Física
            'name' => ['required', 'string', 'max:255'],
            'cpf_cnpj' => ['required_if:type,individual', 'nullable', 'string', 'size:11', 'unique:contacts,cpf_cnpj', 'cpf_ou_cnpj'],
            'rg' => ['nullable', 'string', 'max:20'],
            'gender' => ['nullable', Rule::in(['female', 'male'])],
            'marital_status' => ['nullable'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'profession' => ['nullable', 'string', 'max:100'],

            // Campos para Pessoa Jurídica
            'business_name' => ['required_if:type,business', 'nullable', 'string', 'max:255'],
            'trade_name' => ['nullable', 'string', 'max:255'],
            'business_activity' => ['nullable', 'string', 'max:255'],
            'tax_state' => ['nullable', 'string', 'max:100'],
            'tax_city' => ['nullable', 'string', 'max:100'],
            'administrator_id' => ['nullable', 'exists:contacts,id'], // Deve referenciar um contato existente

            // Contatos (Pode ter vários)
            'emails' => ['nullable', 'array'],
            'emails.*' => ['required', 'email', 'max:255', 'distinct'],

            'phones' => ['nullable', 'array'],
            'phones.*' => ['required', 'string', 'max:20', 'distinct'],


            // Endereço
            'zip_code' => ['nullable', 'string', 'max:8'],
            'number' => ['nullable', 'integer'],
            'complement' => ['nullable', 'string', 'max:255'],
        ];
    }
}
