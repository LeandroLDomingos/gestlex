<?php

namespace App\Http\Controllers\Api;

use App\DTO\Contacts\CreateContactDTO;
use App\DTO\Contacts\EditContactDTO;
use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreContactRequest;
use App\Http\Requests\Api\UpdateContactRequest;
use App\Http\Resources\ContactResource;
use App\Repositories\ContactRepository;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class ContactController extends Controller
{
    public function __construct(protected ContactRepository $contactRepository) {}

    /**
     * Lista todos os contatos.
     */
    public function index(Request $request)
    {
        $contacts = $this->contactRepository->getPaginate(
            totalPerPage: $request->total_per_page ?? 15,
            page: $request->page ?? 1,
            filter: $request->get('filter', '')
        );
        return ContactResource::collection($contacts);
    }

    /**
     * Cria um novo contato.
     */
    public function store(StoreContactRequest $request)
    {
        $contact = $this->contactRepository->create(new CreateContactDTO(... $request->validated()));
        return new ContactResource($contact);
    }

    /**
     * Exibe um contato específico.
     */
    public function show(int $id): JsonResponse
    {
        $contact = $this->contactRepository->findById($id);

        if (!$contact) {
            return response()->json(['message' => 'Contato não encontrado.'], 404);
        }

        return response()->json($contact);
    }

    /**
     * Atualiza um contato existente.
     */
    public function update(UpdateContactRequest $request, string $id)
    {
        $response = $this->contactRepository->update(new EditContactDTO(...[$id, ...$request->validated()]));
        if (!$response){
            return response()->json(['message' => 'contact not found'], Response::HTTP_NOT_FOUND);
        }
        return response()->json(['message' => 'contact updated with sucess']);

    }

    /**
     * Exclui um contato.
     */
    public function destroy(int $id): JsonResponse
    {
        if (!$this->contactRepository->delete($id)) {
            return response()->json(['message' => 'Contato não encontrado.'], 404);
        }

        return response()->json(['message' => 'Contato excluído com sucesso!']);
    }
}
