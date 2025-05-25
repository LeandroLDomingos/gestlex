<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use App\Models\ContactAnnotation;
use App\Models\ContactDocument;
use App\Models\Process;
use App\Enums\ContactMaritalStatus;
use App\Enums\ContactGender;
use App\Models\ProcessDocument; // Embora não usado diretamente aqui, mantido por consistência com o original
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str; // Adicionado para Str::slug

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
                    'state',
                ];
                $query->where(function ($q) use ($searchTerm, $searchableFields) {
                    foreach ($searchableFields as $field) {
                        $q->orWhere($field, 'LIKE', "%{$searchTerm}%");
                    }
                    $q->orWhereHas('emails', fn($q_email) => $q_email->where('email', 'LIKE', "%{$searchTerm}%"));
                    $q->orWhereHas('phones', fn($q_phone) => $q_phone->where('phone', 'LIKE', "%{$searchTerm}%"));
                });
            });

        if (in_array($sortBy, ['name', 'business_name', 'type'])) {
            $contactsQuery->orderByRaw("LOWER(CAST({$sortBy} AS TEXT)) {$sortDirection}");
        } else {
            $contactsQuery->orderBy($sortBy, $sortDirection);
        }

        $contacts = $contactsQuery->with(['emails', 'phones'])->paginate(15)->withQueryString();

        return Inertia::render('contacts/Index', [
            'contacts' => $contacts,
            'filters' => $request->only(['search', 'sort_by', 'sort_direction']),
        ]);
    }

    public function create(): Response
    {
        $physicalContacts = Contact::where('type', 'physical')->orderBy('name')->get(['id', 'name']);
        return Inertia::render('contacts/Create', [
            'contacts' => $physicalContacts,
            'marital_statuses' => ContactMaritalStatus::cases(),
            'genders' => ContactGender::cases(),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:physical,legal',
            'name' => 'required_if:type,physical|nullable|string|max:255',
            'business_name' => 'required_if:type,legal|nullable|string|max:255',
            'cpf_cnpj' => ['required', 'string', 'max:20', Rule::unique('contacts', 'cpf_cnpj')->where(fn($query) => $query->whereNull('deleted_at'))],
            'rg' => ['nullable', 'string', 'max:20', Rule::unique('contacts', 'rg')->ignore($request->id)->where(fn($query) => $query->whereNull('deleted_at'))], // Adicionado ignore para update
            'gender' => 'nullable|string|in:male,female,other,prefer_not_to_say',
            'nationality' => 'nullable|string|max:50',
            'marital_status' => 'nullable|string|max:50',
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
            } else {
                $contactData['name'] = $data['name'] ?? $data['business_name'];
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
            'administrator',
            'annotations' => function ($query) {
                $query->with('user:id,name')->latest();
            },
            'documents' => function ($query) {
                $query->with('uploader:id,name')
                    ->orderBy('created_at', 'desc');
            },
            'processes' => function ($query) {
                $query->with('responsible:id,name')
                    ->with(['documents' => function ($docQuery) {
                        $docQuery->orderBy('created_at', 'desc');
                    }])
                    ->orderBy('updated_at', 'desc');
            }
        ]);

        return Inertia::render('contacts/Show', [
            'contact' => $contact,
            'marital_statuses' => ContactMaritalStatus::cases(),
            'genders' => ContactGender::cases(),
        ]);
    }

    public function edit(Contact $contact)
    {
        $contact->load(['emails', 'phones']);
        $physicalContactsQuery = Contact::where('type', 'physical')->orderBy('name');
        if ($contact->type === 'physical' && $contact->id) {
            $physicalContactsQuery->where('id', '!=', $contact->id);
        }
        $physicalContacts = $physicalContactsQuery->get(['id', 'name']);

        return Inertia::render('contacts/Edit', [
            'contacts' => $physicalContacts,
            'contact' => $contact,
            'marital_statuses' => ContactMaritalStatus::cases(),
            'genders' => ContactGender::cases(),
        ]);
    }

    public function update(Request $request, Contact $contact)
    {
        $data = $request->validate([
            'name' => 'required_if:type,physical|nullable|string|max:255',
            'business_name' => 'required_if:type,legal|nullable|string|max:255',
            'cpf_cnpj' => ['required', 'string', 'max:20', Rule::unique('contacts', 'cpf_cnpj')->ignore($contact->id)->where(fn($query) => $query->whereNull('deleted_at'))],
            'rg' => ['nullable', 'string', 'max:20', Rule::unique('contacts', 'rg')->ignore($contact->id)->where(fn($query) => $query->whereNull('deleted_at'))],
            'gender' => 'nullable|string|in:male,female,other,prefer_not_to_say',
            'nationality' => 'nullable|string|max:50',
            'marital_status' => 'nullable|string|max:50',
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
            $contactDataToUpdate = $data;
            // O tipo não deve ser alterado na edição, então removemos do array de update.
            // Se precisar mudar o tipo, seria uma lógica mais complexa.
            // unset($contactDataToUpdate['type']); 

            $contactDataToUpdate['cpf_cnpj'] = preg_replace('/\D/', '', $data['cpf_cnpj']);
            $contactDataToUpdate['rg'] = isset($data['rg']) ? preg_replace('/\D/', '', $data['rg']) : null;
            $contactDataToUpdate['zip_code'] = isset($data['zip_code']) ? preg_replace('/\D/', '', $data['zip_code']) : null;

            if ($contact->type === 'physical') {
                $contactDataToUpdate['name'] = $data['name'];
                $contactDataToUpdate['business_name'] = null;
                $contactDataToUpdate['business_activity'] = null;
            } else { // legal
                $contactDataToUpdate['name'] = $data['name'] ?? $data['business_name'];
                $contactDataToUpdate['business_name'] = $data['business_name'];
                $contactDataToUpdate['rg'] = null;
                $contactDataToUpdate['gender'] = null;
                // Manter outros campos como date_of_birth, marital_status, profession nulos para PJ
                 $contactDataToUpdate['date_of_birth'] = null;
                 $contactDataToUpdate['marital_status'] = null;
                 $contactDataToUpdate['profession'] = null;
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
            $contactName = $contact->name ?? $contact->business_name ?? "ID {$contact->id}";
            $contact->emails()->delete();
            $contact->phones()->delete();
            $contact->annotations()->delete();
            $contact->documents()->each(function ($document) {
                Storage::disk('public')->delete($document->path);
                $document->delete();
            });
            $contact->processes()->detach();
            Contact::where('administrator_id', $contact->id)->update(['administrator_id' => null]);
            $contact->delete();
            DB::commit();
            return redirect()->route('contacts.index')->with('success', "Contato '{$contactName}' deletado com sucesso.");
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao deletar contato {$contact->id}: " . $e->getMessage() . ' Stack: ' . $e->getTraceAsString());
            $contactIdentifier = $contact->name ?: $contact->business_name ?: "ID {$contact->id}";
            return redirect()->route('contacts.index')->with('error', "Não foi possível deletar o contato '{$contactIdentifier}'. Verifique dependências ou contate o suporte.");
        }
    }

    public function storeAnnotation(Request $request, Contact $contact)
    {
        $data = $request->validate([
            'content' => 'required|string|max:5000',
        ]);

        DB::beginTransaction();
        try {
            $contact->annotations()->create([
                'content' => $data['content'],
                'user_id' => auth()->id(),
            ]);
            DB::commit();
            return redirect()->route('contacts.show', $contact->id)->with('success', 'Anotação adicionada com sucesso!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao adicionar anotação ao contato {$contact->id}: " . $e->getMessage());
            return back()->with('error', 'Falha ao adicionar anotação.');
        }
    }

    public function destroyAnnotation(Request $request, Contact $contact, ContactAnnotation $annotation)
    {
        if ($annotation->contact_id !== $contact->id) {
            return Redirect::back()->with('error', 'A anotação não pertence a este contato ou não foi encontrada.');
        }
        try {
            $annotation->delete();
            return Redirect::route('contacts.show', $contact->id)
                ->with('success', 'Anotação excluída com sucesso!');
        } catch (\Exception $e) {
            Log::error("Erro ao excluir anotação {$annotation->id} do contato {$contact->id}: " . $e->getMessage());
            return Redirect::back()->with('error', 'Ocorreu um erro ao excluir a anotação.');
        }
    }

    public function storeDocument(Request $request, Contact $contact)
    {
        $data = $request->validate([
            'file' => 'required|file|mimes:pdf,doc,docx,xls,xlsx,ppt,pptx,txt,jpg,jpeg,png|max:20480',
            'description' => 'nullable|string|max:255',
        ]);

        DB::beginTransaction();
        try {
            $file = $request->file('file');
            $originalName = $file->getClientOriginalName();
            // Usar Str::slug para criar um nome de arquivo mais seguro e limpo
            $safeFileName = time() . '_' . Str::slug(pathinfo($originalName, PATHINFO_FILENAME)) . '.' . $file->getClientOriginalExtension();
            $path = $file->storeAs("contact_documents/{$contact->id}", $safeFileName, 'public');

            if (!$path) {
                throw new \Exception("Falha ao armazenar o arquivo.");
            }

            $contact->documents()->create([
                'uploader_user_id' => auth()->id(),
                'name' => $originalName, // CORREÇÃO: Usar 'name' em vez de 'file_name'
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
            if (isset($path) && Storage::disk('public')->exists($path)) {
                Storage::disk('public')->delete($path);
            }
            return back()->with('error', 'Falha ao enviar documento.');
        }
    }

    public function destroyDocument(Contact $contact, ContactDocument $document)
    {
        if ($document->contact_id !== $contact->id) {
            return back()->with('error', 'Documento não pertence a este contato.');
        }

        DB::beginTransaction();
        try {
            Storage::disk('public')->delete($document->path);
            $document->delete();
            DB::commit();
            return redirect()->route('contacts.show', $contact->id)->with('success', 'Documento excluído com sucesso!');
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao excluir documento {$document->id} do contato {$contact->id}: " . $e->getMessage());
            return back()->with('error', 'Falha ao excluir documento.');
        }
    }

    // Este método parece pertencer ao ProcessController, mas estava no seu ContactController.
    // Se for para download de documentos de PROCESSO, deve estar no ProcessController.
    // Se for para download de documentos de CONTATO, o parâmetro $process não faz sentido.
    // Vou assumir que é para documentos de CONTATO, e remover o $process.
    public function documentDownload(Contact $contact, ContactDocument $document) // Removido Process $process
    {
        if ($document->contact_id !== $contact->id) { // Verificação ajustada
            abort(404, 'Documento não encontrado para este contato.');
        }
        if (Storage::disk('public')->exists($document->path)) {
            // Usa 'name' (ou 'file_name' se for o nome da coluna no seu model ContactDocument)
            $downloadName = $document->name ?? $document->file_name ?? 'documento';
            return Storage::disk('public')->download($document->path, $downloadName);
        } else {
            abort(404, 'Arquivo não encontrado no servidor.');
        }
    }
}
