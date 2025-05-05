<?php 

namespace	 App\DTO\Processes;

class CreateProcessDTO
{
    public function __construct(
        readonly public string $title,
        readonly public ?string $origin = null,
        readonly public ?string $negotiated_value = null,
        readonly public ?string $description = null,
        readonly public string $responsible_id,
        readonly public string $workflow,
        readonly public ?array $contacts = null,
        readonly public ?array $tags = null,
        readonly public ?string $stage = null,
        ){
        }
    }