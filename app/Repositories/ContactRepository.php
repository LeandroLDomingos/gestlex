<?php

namespace App\Repositories;

use App\DTO\Contacts\CreateContactDTO;
use App\DTO\Contacts\EditContactDTO;
use App\Models\Api\Contact;
use Illuminate\Pagination\LengthAwarePaginator;


class ContactRepository
{
    public function __construct(protected Contact $contact) {}

    /**
     * Obtém todos os contatos com seus e-mails e telefones.
     */
    public function getPaginate(int $totalPerPage = 15, int $page = 1, string $filter = ''): LengthAwarePaginator
    {
        return $this->contact->where(function ($query) use ($filter) {
            if ($filter !== '') {
                $query->where('name', 'LIKE', "%{$filter}%");
            }
        })
            ->with(['emails', 'phones'])
            ->paginate($totalPerPage, ['*'], 'page', $page);
    }

    /**
     * Obtém um contato pelo ID, incluindo e-mails e telefones.
     */
    public function findById(int $id): ?Contact
    {
        return $this->contact->with(['emails', 'phones'])->find($id);
    }

    /**
     * Cria um novo contato com e-mails e telefones.
     */
    public function create(CreateContactDTO $dto): Contact
    {
        $contact = $this->contact->create((array)$dto);
        
        if (!empty($dto->emails)) {
            $contact->emails()->createMany(
                array_map(fn($email) => ['email' => $email], (array) $dto->emails)
            );
        }
    
        if (!empty($dto->phones)) {
            $contact->phones()->createMany(
                array_map(fn($phone) => ['phone' => $phone], (array) $dto->phones)
            );
        }
    
        return $contact->load(['emails', 'phones']);
    }

    /**
     * Atualiza um contato e suas informações relacionadas.
     */
    public function update(EditContactDTO $dto): Contact
    {
        $contact = $this->contact->findOrFail($dto->id);
        $contact->update((array) $dto);
    
        // Atualiza ou recria os e-mails
        if (!empty($dto->emails)) {
            $contact->emails()->delete(); // Remove os e-mails antigos
            $contact->emails()->createMany(
                array_map(fn($email) => ['email' => $email], (array) $dto->emails)
            );
        }
    
        // Atualiza ou recria os telefones
        if (!empty($dto->phones)) {
            $contact->phones()->delete(); // Remove os telefones antigos
            $contact->phones()->createMany(
                array_map(fn($phone) => ['phone' => $phone], (array) $dto->phones)
            );
        }
    
        return $contact->load(['emails', 'phones']);
    }
    

    /**
     * Deleta um contato e suas relações.
     */
    public function delete(int $id)
    {
        if(!$contact = $this->findById($id))
        {
            return false;
        }
        return $contact->delete();
    }
}
