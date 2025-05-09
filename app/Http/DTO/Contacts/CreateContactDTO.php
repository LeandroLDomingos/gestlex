<?php 

namespace	 App\DTO\Contacts;

class CreateContactDTO
{
    public function __construct(
        public readonly string $type,
        public readonly string $name,
        public readonly ?string $cpf_cnpj = null,
        public readonly ?string $rg = null,
        public readonly ?string $gender = null,
        public readonly ?string $nationality = null,
        public readonly ?string $marital_status = null,
        public readonly ?string $profession = null,
        public readonly ?string $business_activity = null,
        public readonly ?string $tax_state = null,
        public readonly ?string $tax_city = null,
        public readonly ?int $administrator_id,
        public readonly ?string $zip_code = null,
        public readonly ?string $number = null,
        public readonly ?string $complement = null,
        public readonly array $emails,
        public readonly array $phones
    ){
    }
}