<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\ContactEmail;
use App\Models\ContactPhone;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log; // Importar Log para registrar erros
use Illuminate\Support\Facades\DB; // Importar DB para transações, se necessário

class ContactController extends Controller
{
    public function index(Request $request): Response
    {
        $sortBy = $request->input('sort_by', 'name');
        $sortDirection = $request->input('sort_direction', 'asc');
        $search = $request->input('search', '');

        // Colunas permitidas para ordenação. É importante validar para evitar injeção de SQL com orderByRaw.
        $allowedSortColumns = ['name', 'date_of_birth', 'created_at', 'updated_at', 'cpf_cnpj', 'type'];
        if (!in_array($sortBy, $allowedSortColumns)) {
            $sortBy = 'name'; // Coluna padrão segura
        }
        if (!in_array(strtolower($sortDirection), ['asc', 'desc'])) {
            $sortDirection = 'asc';
        }

        $contactsQuery = Contact::query()
            ->when($search, function ($query, $searchTerm) {
                $searchableFields = [
                    'name',
                    'cpf_cnpj',
                    'rg',
                    'nationality',
                    'marital_status',
                    'profession',
                    'zip_code',
                    'address',
                    'neighborhood',
                    'number',
                    'complement',
                    'city',
                    'state',
                    'name',
                    'business_name',
                    'business_activity',
                    'tax_state',
                    'tax_city',
                    'gender',
                ];

                $query->where(function ($q) use ($searchTerm, $searchableFields) {
                    foreach ($searchableFields as $field) {
                        $q->orWhere($field, 'LIKE', "%{$searchTerm}%");
                    }
                    $q->orWhereHas('emails', function ($emailQuery) use ($searchTerm) {
                        $emailQuery->where('email', 'LIKE', "%{$searchTerm}%");
                    });
                    $q->orWhereHas('phones', function ($phoneQuery) use ($searchTerm) {
                        $phoneQuery->where('phone', 'LIKE', "%{$searchTerm}%");
                    });
                });
            });

        // Aplicar ordenação case-insensitive
        // Certifique-se de que $sortBy é uma coluna validada para evitar injeção de SQL.
        // A função LOWER() é comum para a maioria dos bancos SQL (MySQL, PostgreSQL, SQL Server).
        // Para SQLite, LOWER() também funciona.
        $contactsQuery->orderByRaw("LOWER({$sortBy}) {$sortDirection}");

        $contacts = $contactsQuery->paginate(50)->withQueryString();
        $contacts->load('emails', 'phones');

        return Inertia::render('contacts/Index', [
            'contacts' => $contacts,
            'filters' => ['search' => $search],
            'sortBy' => $sortBy,
            'sortDirection' => $sortDirection,
        ]);
    }

    public function create(): Response
    {
        $physicalContacts = Contact::where('type', 'physical')->orderBy('name')->get(['id', 'name']);
        return Inertia::render('contacts/Create', [
            'contacts' => $physicalContacts,
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'type' => 'required|in:physical,legal',
            'name' => 'required_if:type,physical|nullable|string|max:255',
            'name' => 'required_if:type,legal|nullable|string|max:255',
            'business_name' => 'required_if:type,legal|nullable|string|max:255',
            'cpf_cnpj' => 'required|string|max:20|unique:contacts,cpf_cnpj',
            'rg' => 'nullable|string|max:20|unique:contacts,rg',
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
            } else {
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

            return redirect()->route('contacts.index')
                ->with('success', 'Contato criado com sucesso!');

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Erro ao criar contato: ' . $e->getMessage() . ' Stack: ' . $e->getTraceAsString());
            return back()->withInput()
                ->with('error', 'Ocorreu um erro inesperado ao criar o contato. Por favor, tente novamente.');
        }
    }

    public function show(Contact $contact): Response
    {
        $contact->load(['emails', 'phones', 'adminContact']);
        return Inertia::render('contacts/Show', [
            'contact' => $contact,
        ]);
    }

    public function edit(Contact $contact)
    {
        $contact->load(['emails', 'phones']);
        $physicalContactsQuery = Contact::where('type', 'physical')->orderBy('name');
        if ($contact->type === 'physical') {
            $physicalContactsQuery->where('id', '!=', $contact->id);
        }
        $physicalContacts = $physicalContactsQuery->get(['id', 'name']);

        return Inertia::render('contacts/Edit', [
            'contacts' => $physicalContacts,
            'contact' => $contact,
        ]);
    }

    public function update(Request $request, Contact $contact)
    {
        $data = $request->validate([
            'type' => 'required|in:physical,legal',
            'name' => 'required_if:type,physical|nullable|string|max:255',
            'name' => 'required_if:type,legal|nullable|string|max:255',
            'business_name' => 'required_if:type,legal|nullable|string|max:255',
            'cpf_cnpj' => 'required|string|max:20|unique:contacts,cpf_cnpj,' . $contact->id,
            'rg' => 'nullable|string|max:20|unique:contacts,rg,' . $contact->id,
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
            $contactDataToUpdate = [
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

            if ($contact->type === 'physical') { // Ou $data['type'] se o tipo puder mudar
                $contactDataToUpdate['name'] = $data['name'];
            } else {
                $contactDataToUpdate['name'] = $data['name'];
                $contactDataToUpdate['business_name'] = $data['business_name'];
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

            return redirect()->route('contacts.show', $contact->id)
                ->with('success', 'Contato atualizado com sucesso.');

        } catch (\Illuminate\Validation\ValidationException $e) {
            DB::rollBack();
            return back()->withErrors($e->errors())->withInput();
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao atualizar contato {$contact->id}: " . $e->getMessage() . ' Stack: ' . $e->getTraceAsString());
            return back()->withInput()
                ->with('error', 'Falha ao atualizar o contato. Por favor, tente novamente.');
        }
    }

    public function destroy(Contact $contact)
    {
        DB::beginTransaction();
        try {
            $contact->emails()->delete();
            $contact->phones()->delete();

            $contactName = $contact->name;
            $contact->delete();

            DB::commit();

            return redirect()->route('contacts.index')
                ->with('success', "Contato '{$contactName}' deletado com sucesso.");

        } catch (\Exception $e) {
            DB::rollBack();
            Log::error("Erro ao deletar contato {$contact->id}: " . $e->getMessage() . ' Stack: ' . $e->getTraceAsString());

            $contactIdentifier = $contact->name ?: "ID {$contact->id}";

            return redirect()->route('contacts.index')
                ->with('error', "Não foi possível deletar o contato '{$contactIdentifier}'. Pode estar associado a outros registros.");
        }
    }
}
