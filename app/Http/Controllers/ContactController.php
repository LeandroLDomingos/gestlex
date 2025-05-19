<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\ContactAnnotation;
use App\Models\ContactDocument;
use Illuminate\Support\Facades\Redirect; // Adicionado para Redirect
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage; // Para manipulação de arquivos de documentos
use Illuminate\Validation\Rule; // Para regras de validação mais complexas

class ContactController extends Controller
{
    public function index(Request $request): Response
    {
        $sortBy = $request->input('sort_by', 'name');
        $sortDirection = $request->input('sort_direction', 'asc');
        $search = $request->input('search', '');

        $allowedSortColumns = ['name', 'business_name', 'cpf_cnpj', 'type', 'created_at', 'updated_at'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'name';
        }
        if (!in_array(strtolower($sortDirection), ['asc', 'desc'])) {
            $sortDirection = 'asc';
        }

        $contactsQuery = Contact::query()
            ->when($search, function ($query, $searchTerm) {
                $searchableFields = [
                    'name',
                    'business_name',
                    'cpf_cnpj',
                    'rg',
                    'city',
                    'state', // Adicionar mais campos se necessário
                ];
                $query->where(function ($q) use ($searchTerm, $searchableFields) {
                    foreach ($searchableFields as $field) {
                        $q->orWhere($field, 'LIKE', "%{$searchTerm}%");
                    }
                    $q->orWhereHas('emails', fn($q) => $q->where('email', 'LIKE', "%{$searchTerm}%"));
                    $q->orWhereHas('phones', fn($q) => $q->where('phone', 'LIKE', "%{$searchTerm}%"));
                });
            });

        // Para ordenação case-insensitive em colunas de texto
        if (in_array($sortBy, ['name', 'business_name', 'type'])) {
            $contactsQuery->orderByRaw("LOWER({$sortBy}) {$sortDirection}");
        } else {
            $contactsQuery->orderBy($sortBy, $sortDirection);
        }

        $contacts = $contactsQuery->with(['emails', 'phones'])->paginate(15)->withQueryString(); // Ajuste a paginação

        return Inertia::render('contacts/Index', [
            'contacts' => $contacts,
            'filters' => $request->only(['search', 'sort_by', 'sort_direction']),
        ]);
    }

    public function create(): Response
    {
        $physicalContacts = Contact::where('type', 'physical')->orderBy('name')->get(['id', 'name']);
        return Inertia::render('contacts/Create', [
            'contacts' => $physicalContacts, // Para o select de administrador
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:physical,legal',
            'name' => 'required_if:type,physical|nullable|string|max:255',
            'business_name' => 'required_if:type,legal|nullable|string|max:255',
            'cpf_cnpj' => ['required', 'string', 'max:20', Rule::unique('contacts', 'cpf_cnpj')->where(fn($query) => $query->whereNull('deleted_at'))],
            'rg' => ['nullable', 'string', 'max:20', Rule::unique('contacts', 'rg')->where(fn($query) => $query->whereNull('deleted_at'))],
            'gender' => 'nullable|in:male,female,other,prefer_not_to_say',
            'nationality' => 'nullable|string|max:5',
            'marital_status' => 'nullable|string|max:20',
            'profession' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|date_format:Y-m-d',
            'business_activity' => 'nullable|string|max:100',
            'tax_state' => 'nullable|string|size:2',
            'tax_city' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:10',
            'address' => 'nullable|string|max:255',
            'neighborhood' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|size:2',
            'complement' => 'nullable|string|max:100',
            'number' => 'nullable|string|max:20',
            'administrator_id' => 'nullable|exists:contacts,id',
            'emails' => 'present|array',
            'emails.*' => 'nullable|email|max:255',
            'phones' => 'present|array',
            'phones.*' => 'nullable|string|max:20',
        ]);

        DB::beginTransaction();
        try {
            $contactData = [
                'type' => $data['type'],
                'cpf_cnpj' => preg_replace('/\D/', '', $data['cpf_cnpj']),
                'rg' => isset($data['rg']) ? preg_replace('/\D/', '', $data['rg']) : null,
                'gender' => $data['gender'] ?? null,
                'nationality' => $data['nationality'] ?? null,
                'marital_status' => $data['marital_status'] ?? null,
                'profession' => $data['profession'] ?? null,
                'date_of_birth' => $data['date_of_birth'] ?? null,
                'business_activity' => $data['business_activity'] ?? null,
                'tax_state' => $data['tax_state'] ?? null,
                'tax_city' => $data['tax_city'] ?? null,
                'zip_code' => isset($data['zip_code']) ? preg_replace('/\D/', '', $data['zip_code']) : null,
                'address' => $data['address'] ?? null,
                'neighborhood' => $data['neighborhood'] ?? null,
                'city' => $data['city'] ?? null,
                'state' => $data['state'] ?? null,
                'complement' => $data['complement'] ?? null,
                'number' => $data['number'] ?? null,
                'administrator_id' => $data['administrator_id'] ?? null,
            ];

            if ($data['type'] === 'physical') {
                $contactData['name'] = $data['name'];
            } else { // legal
                $contactData['name'] = $data['name'];
                $contactData['business_name'] = $data['business_name'];
            }

            $contact = Contact::create($contactData);

            if (!empty($data['emails'])) {
                foreach ($data['emails'] as $email) {
                    if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $contact->emails()->create(['email' => $email]);
                    }
                }
            }
            if (!empty($data['phones'])) {
                foreach ($data['phones'] as $phone) {
                    if ($phone) {
                        $contact->phones()->create(['phone' => preg_replace('/\D/', '', $phone)]);
                    }
                }
            }
            DB::commit();
            return redirect()->route('contacts.show', $contact->id)->with('success', 'Contato criado com sucesso!');
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao criar contato: ' . $e->getMessage() . ' Stack: ' . $e->getTraceAsString());
            return back()->withInput()->with('error', 'Ocorreu um erro inesperado ao criar o contato.');
        }
    }

    public function show(Contact $contact): Response
    {
        $contact->load([
            'emails',
            'phones',
            'administrator', // Se 'administrator_id' referencia outro contato
            'annotations' => function ($query) {
                $query->with('user:id,name')->latest(); // Carrega o usuário da anotação
            },
            'documents' => function ($query) {
                $query->with('uploader:id,name')->orderBy('created_at', 'desc'); // Carrega quem fez o upload
            },
            'processes' => function ($query) { // Casos/Processos vinculados a este contato
                $query->with('responsible:id,name')->orderBy('updated_at', 'desc');
            }
        ]);

        // Para popular o dropdown de administrador na página de edição, se necessário
        // $physicalContacts = Contact::where('type', 'physical')->orderBy('name')->get(['id', 'name']);

        return Inertia::render('contacts/Show', [
            'contact' => $contact,
            // 'adminContacts' => $physicalContacts, // Se o 'Show' precisar disso para algo
        ]);
    }

    public function edit(Contact $contact)
    {
        $contact->load(['emails', 'phones']);
        $physicalContactsQuery = Contact::where('type', 'physical')->orderBy('name');
        if ($contact->type === 'physical' && $contact->id) { // Adicionado $contact->id para segurança
            $physicalContactsQuery->where('id', '!=', $contact->id);
        }
        $physicalContacts = $physicalContactsQuery->get(['id', 'name']);

        return Inertia::render('contacts/Edit', [
            'contacts' => $physicalContacts, // Para o select de administrador
            'contact' => $contact,
        ]);
    }

    public function update(Request $request, Contact $contact)
    {
        $data = $request->validate([
            // 'type' => 'required|in:physical,legal', // Geralmente não se muda o tipo de um contato existente
            'name' => 'required_if:type,physical|nullable|string|max:255',
            'business_name' => 'required_if:type,legal|nullable|string|max:255',
            'cpf_cnpj' => ['required', 'string', 'max:20', Rule::unique('contacts', 'cpf_cnpj')->ignore($contact->id)->where(fn($query) => $query->whereNull('deleted_at'))],
            'rg' => ['nullable', 'string', 'max:20', Rule::unique('contacts', 'rg')->ignore($contact->id)->where(fn($query) => $query->whereNull('deleted_at'))],
            'gender' => 'nullable|in:male,female,other,prefer_not_to_say',
            'nationality' => 'nullable|string|max:5',
            'marital_status' => 'nullable|string|max:20',
            'profession' => 'nullable|string|max:100',
            'date_of_birth' => 'nullable|date_format:Y-m-d',
            'business_activity' => 'nullable|string|max:100',
            'tax_state' => 'nullable|string|size:2',
            'tax_city' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:10',
            'address' => 'nullable|string|max:255',
            'neighborhood' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|size:2',
            'complement' => 'nullable|string|max:100',
            'number' => 'nullable|string|max:20',
            'administrator_id' => 'nullable|exists:contacts,id',
            'emails' => 'present|array',
            'emails.*' => 'nullable|email|max:255',
            'phones' => 'present|array',
            'phones.*' => 'nullable|string|max:20',
        ]);

        DB::beginTransaction();
        try {
            $contactDataToUpdate = $data; // Começa com todos os dados validados
            unset($contactDataToUpdate['type']); // Não atualiza o tipo

            // Sanitiza campos numéricos
            $contactDataToUpdate['cpf_cnpj'] = preg_replace('/\D/', '', $data['cpf_cnpj']);
            $contactDataToUpdate['rg'] = isset($data['rg']) ? preg_replace('/\D/', '', $data['rg']) : null;
            $contactDataToUpdate['zip_code'] = isset($data['zip_code']) ? preg_replace('/\D/', '', $data['zip_code']) : null;

            if ($contact->type === 'physical') {
                $contactDataToUpdate['name'] = $data['name'];
                // Limpar campos de PJ se estiver atualizando um PF (opcional, mas bom para consistência)
                $contactDataToUpdate['business_name'] = null;
                $contactDataToUpdate['business_activity'] = null;
                // ... outros campos de PJ
            } else { // legal
                $contactDataToUpdate['name'] = $data['name']; // Ou o campo que você usa para Nome Fantasia
                $contactDataToUpdate['business_name'] = $data['business_name'];
                // Limpar campos de PF se estiver atualizando um PJ
                $contactDataToUpdate['rg'] = null;
                $contactDataToUpdate['gender'] = null;
                // ... outros campos de PF
            }

            $contact->update($contactDataToUpdate);

            $contact->emails()->delete();
            if (!empty($data['emails'])) {
                foreach ($data['emails'] as $email) {
                    if ($email && filter_var($email, FILTER_VALIDATE_EMAIL)) {
                        $contact->emails()->create(['email' => $email]);
                    }
                }
            }
            $contact->phones()->delete();
            if (!empty($data['phones'])) {
                foreach ($data['phones'] as $phone) {
                    if ($phone) {
                        $contact->phones()->create(['phone' => preg_replace('/\D/', '', $phone)]);
                    }
                }
            }
            DB::commit();
            return redirect()->route('contacts.show', $contact->id)->with('success', 'Contato atualizado com sucesso.');
        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao atualizar contato {$contact->id}: " . $e->getMessage() . ' Stack: ' . $e->getTraceAsString());
            return back()->withInput()->with('error', 'Falha ao atualizar o contato.');
        }
    }

    public function destroy(Contact $contact)
    {
        DB::beginTransaction();
        try {
            $contactName = $contact->name;

            // Deletar relacionamentos primeiro
            $contact->emails()->delete();
            $contact->phones()->delete();
            $contact->annotations()->delete();
            $contact->documents()->each(function ($document) {
                Storage::disk('public')->delete($document->path);
                $document->delete();
            });
            // Decidir o que fazer com processos vinculados
            // Opção 1: Desassociar (setar contact_id para null) - requer que contact_id em processes seja nullable
            // $contact->processes()->update(['contact_id' => null]);
            // Opção 2: Impedir exclusão se houver processos (o onDelete('restrict') na FK faria isso)
            // Opção 3: Deletar processos vinculados (CUIDADO: pode ser destrutivo)
            // $contact->processes()->delete(); 

            $contact->delete();
            DB::commit();
            return redirect()->route('contacts.index')->with('success', "Contato '{$contactName}' deletado com sucesso.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao deletar contato {$contact->id}: " . $e->getMessage() . ' Stack: ' . $e->getTraceAsString());
            $contactIdentifier = $contact->name ?: "ID {$contact->id}";
            return redirect()->route('contacts.index')->with('error', "Não foi possível deletar o contato '{$contactIdentifier}'. Verifique dependências.");
        }
    }

    // --- MÉTODOS PARA ANOTAÇÕES DE CONTATO ---
    public function storeAnnotation(Request $request, Contact $contact)
    {
        $data = $request->validate([
            'content' => 'required|string|max:5000', // Limite de texto para anotação
        ]);

        DB::beginTransaction();
        try {
            $contact->annotations()->create([
                'content' => $data['content'],
                'user_id' => auth()->id(), // Associa o usuário logado
            ]);
            DB::commit();
            // Redireciona de volta para a página do contato para que o Inertia recarregue as props
            return redirect()->route('contacts.show', $contact->id)->with('success', 'Anotação adicionada com sucesso!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao adicionar anotação ao contato {$contact->id}: " . $e->getMessage());
            return back()->with('error', 'Falha ao adicionar anotação.');
        }
    }

    /**
     * Remove the specified annotation from storage.
     * NOVO MÉTODO ADICIONADO AQUI
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \App\Models\Contact  $contact
     * @param  \App\Models\ContactAnnotation  $annotation  // Route model binding para a anotação
     * @return \Illuminate\Http\RedirectResponse
     */
    public function destroyAnnotation(Request $request, Contact $contact, ContactAnnotation $annotation)
    {
        // Opcional: Verificar se a anotação realmente pertence ao contato fornecido.
        // Se suas rotas estiverem configuradas para aninhamento com escopo (scoped bindings),
        // o Laravel já fará essa verificação. Ex: Route::resource('contacts.annotations', ...);
        // Se não, esta verificação manual é uma boa prática.
        if ($annotation->contact_id !== $contact->id) {
            // Pode ser um erro 404 ou um redirecionamento com mensagem de erro.
            // abort(404, 'Anotação não encontrada para este contato.');
            return Redirect::back()->with('error', 'A anotação não pertence a este contato ou não foi encontrada.');
        }

        // Opcional: Adicionar verificação de permissão aqui (ex: usando Policies)
        // if ($request->user()->cannot('delete', $annotation)) {
        //     abort(403, 'Você não tem permissão para excluir esta anotação.');
        // }

        try {
            $annotation->delete();

            // Redireciona de volta para a página de detalhes do contato
            // com uma mensagem de sucesso.
            // O frontend (Show.vue) já está configurado para recarregar os dados do contato.
            return Redirect::route('contacts.show', $contact->id)
                ->with('success', 'Anotação excluída com sucesso!');

        } catch (\Exception $e) {
            // Log do erro
            Log::error("Erro ao excluir anotação {$annotation->id} do contato {$contact->id}: " . $e->getMessage());

            // Redireciona de volta com uma mensagem de erro
            return Redirect::back()->with('error', 'Ocorreu um erro ao excluir a anotação.');
        }
    }

    // --- MÉTODOS PARA DOCUMENTOS DE CONTATO ---
    public function storeDocument(Request $request, Contact $contact)
    {

        $data = $request->validate([
            'file' => 'required|file|max:20480', // Ex: max 20MB
            'description' => 'nullable|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            $file = $request->file('file');
            // Armazena no disco 'public' dentro de uma pasta 'contact_documents/{contact_id}'
            $path = $file->store("contact_documents/{$contact->id}", 'public');

            if (!$path) {
                throw new \Exception("Falha ao armazenar o arquivo.");
            }

            $contact->documents()->create([
                'uploader_user_id' => auth()->id(),
                'name' => $file->getClientOriginalName(),
                'path' => $path,
                'mime_type' => $file->getMimeType(),
                'size' => $file->getSize(),
                'description' => $data['description'],
            ]);

            DB::commit();
            return redirect()->route('contacts.show', $contact->id)->with('success', 'Documento enviado com sucesso!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao enviar documento para o contato {$contact->id}: " . $e->getMessage() . ' Stack: ' . $e->getTraceAsString());
            // Se o arquivo foi salvo mas o registro no DB falhou, idealmente deletar o arquivo do storage
            if (isset($path) && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
            return back()->with('error', 'Falha ao enviar documento.');
        }
    }

    public function destroyDocument(Contact $contact, ContactDocument $document)
    {
        DB::beginTransaction();
        try {
            Storage::disk('public')->delete($document->path);
            $document->delete();
            DB::commit();
            return back()->with('success', 'Documento excluído com sucesso.');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao excluir documento {$document->id} do contato {$contact->id}: " . $e->getMessage());
            return back()->with('error', 'Falha ao excluir documento.');
        }
    }


}
