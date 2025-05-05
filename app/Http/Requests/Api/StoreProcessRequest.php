<?php

namespace App\Http\Requests\Api;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreProcessRequest extends FormRequest
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
            'title' => [
                'required',
                'min:3',
                'max:255',
                'string',
            ],
            'origin' => [
                'nullable',
                'min:3',
                'max:255',
                'string',
            ],
            'negotiated_value' => [
                'nullable',
                'decimal:2',
            ],
            'description' => [
                'nullable',
                'min:3',
                'max:255',
                'string'
            ],
            'responsible_id' => [
                'nullable',
                'exists:contacts,id'
            ],
            'workflow'=> [
                'required',
                'number',
            ],
            'contacts' => [
                'nullable',
                'exists:contacts,id'
            ],
            



            
            // readonly public ?array $contacts = null,
            // readonly public ?array $tags = null,
            // readonly public ?string $stage = null,
        ];
    }
}
