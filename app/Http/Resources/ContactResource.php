<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ContactResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'type' => $this->type,
            'name' => $this->name,
            'cpf_cnpj' => $this->cpf_cnpj,
            'rg' => $this->rg,
            'gender' => $this->gender,
            'nationatily' => $this->nationatily,
            'marital_status' => $this->marital_status,
            'profession' => $this->profession,
            'business_activity' => $this->business_activity,
            'tax_state' => $this->tax_state,
            'tax_city' => $this->tax_city,
            'administrator_id' => $this->administrator_id,
            'zip_code' => $this->zip_code,
            'address_number' => $this->address_number,
            'address_complement' => $this->address_complement,
            'emails' => ContactEmailsResource::collection($this->whenLoaded('emails')),
            'phones' => ContactPhonesResource::collection($this->whenLoaded('phones')),

        ];
    }
}
