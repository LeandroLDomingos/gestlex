<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Inertia\Inertia;
use Inertia\Response;
use App\Models\ContactEmail;
use App\Models\ContactPhone;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function index(): Response
    {
        $contacts = Contact::get();
        return Inertia::render('contacts/Index', [
            'contacts' => $contacts,
        ]);
    }
    public function create(): Response
    {
        $contacts = Contact::where('type', 'physical')->get();
        return Inertia::render('contacts/Create', [
            'contacts' => $contacts,
        ]);
    }

    /**
     * Store a new Physical or Legal contact, along with emails and phones.
     */
    public function store(Request $request)
    {
        // Validação comum
        $data = $request->validate([
            'type' => 'required|in:physical,legal',
            'name' => 'required_if:type,physical|string|max:255',
            'trade_name' => 'string|max:255',
            'business_name' => 'required_if:type,legal|string|max:255',
            'cpf_cnpj' => 'required|string|max:20|unique:contacts,cpf_cnpj',
            'rg' => 'nullable|string|max:20|unique:contacts,cpf_cnpj',
            'gender' => 'nullable|in:male,female,other',
            'nationality' => 'nullable|string|max:2',
            'marital_status' => 'nullable|string|max:20',
            'profession' => 'nullable|string|max:100',
            'business_activity' => 'nullable|string|max:100',
            'tax_state' => 'nullable|string|size:2',
            'tax_city' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:10',
            'address' => 'nullable|string|max:255',
            'neighborhood' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|size:2',
            'complement' => 'nullable|string|max:100',
            'number' => 'nullable|string|max:10',
            'administrator_id' => 'nullable|exists:contacts,id',
            'emails' => 'array',
            'emails.*' => 'nullable|email|max:255',
            'phones' => 'array',
            'phones.*' => 'nullable|string|max:20',
        ]);
        // Cria o contato principal
        $contact = Contact::create([
            'type' => $data['type'],
            'name' => $data['type'] === 'physical' ? $data['name'] : $data['trade_name'],
            'cpf_cnpj' => $data['cpf_cnpj'],
            'rg' => $data['rg'] ?? null,
            'gender' => $data['gender'] ?? null,
            'nationality' => $data['nationality'] ?? null,
            'marital_status' => $data['marital_status'] ?? null,
            'profession' => $data['profession'] ?? null,
            'business_activity' => $data['business_activity'] ?? null,
            'tax_state' => $data['tax_state'] ?? null,
            'tax_city' => $data['tax_city'] ?? null,
            'zip_code' => $data['zip_code'] ?? null,
            'address' => $data['address'] ?? null,
            'neighborhood' => $data['neighborhood'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'complement' => $data['complement'] ?? null,
            'number' => $data['number'] ?? null,
            'trade_name' => $data['trade_name'] ?? null,
            'administrator_id' => $data['administrator_id'] ?? null,
        ]);

        // Persistir emails
        if (!empty($data['emails'])) {
            foreach ($data['emails'] as $email) {
                if ($email) {
                    ContactEmail::create([
                        'contact_id' => $contact->id,
                        'email' => $email,
                    ]);
                }
            }
        }

        // Persistir telefones
        if (!empty($data['phones'])) {
            foreach ($data['phones'] as $phone) {
                if ($phone) {
                    ContactPhone::create([
                        'contact_id' => $contact->id,
                        'phone' => $phone,
                    ]);
                }
            }
        }

        return redirect()->route('contacts.index')
            ->with('success', 'Contato criado com sucesso.');
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
        $contact->load(['emails', 'phones', 'adminContact']);
        $contacts = Contact::where('type', 'physical')->get();

        return Inertia::render('contacts/Edit', [
            'contacts' => $contacts,
            'contact' => $contact->load(['emails', 'phones']), // se tiver relations
        ]);
    }

    public function update(Request $request, Contact $contact)
    {
        // Validação comum
        $data = $request->validate([
            'type' => 'required|in:physical,legal',
            'name' => 'required_if:type,physical|string|max:255',
            'trade_name' => 'nullable|string|max:255',
            'business_name' => 'required_if:type,legal|string|max:255',
            'cpf_cnpj' => 'required|string|max:20|unique:contacts,cpf_cnpj,' . $contact->id,
            'rg' => 'nullable|string|max:20|unique:contacts,rg,' . $contact->id,
            'gender' => 'nullable|in:male,female,other',
            'nationality' => 'nullable|string|max:2',
            'marital_status' => 'nullable|string|max:20',
            'profession' => 'nullable|string|max:100',
            'business_activity' => 'nullable|string|max:100',
            'tax_state' => 'nullable|string|size:2',
            'tax_city' => 'nullable|string|max:100',
            'zip_code' => 'nullable|string|max:10',
            'address' => 'nullable|string|max:255',
            'neighborhood' => 'nullable|string|max:100',
            'city' => 'nullable|string|max:100',
            'state' => 'nullable|string|size:2',
            'complement' => 'nullable|string|max:100',
            'number' => 'nullable|numeric',
            'administrator_id' => 'nullable|exists:contacts,id',
            'emails' => 'array',
            'emails.*' => 'nullable|email|max:255',
            'phones' => 'array',
            'phones.*' => 'nullable|string|max:20',
        ]);

        // Atualiza o contato
        $contact->update([
            'type' => $data['type'],
            'name' => $data['type'] === 'physical' ? $data['name'] : $data['trade_name'],
            'cpf_cnpj' => $data['cpf_cnpj'],
            'rg' => $data['rg'] ?? null,
            'gender' => $data['gender'] ?? null,
            'nationality' => $data['nationality'] ?? null,
            'marital_status' => $data['marital_status'] ?? null,
            'profession' => $data['profession'] ?? null,
            'business_activity' => $data['business_activity'] ?? null,
            'tax_state' => $data['tax_state'] ?? null,
            'tax_city' => $data['tax_city'] ?? null,
            'zip_code' => $data['zip_code'] ?? null,
            'address' => $data['address'] ?? null,
            'neighborhood' => $data['neighborhood'] ?? null,
            'city' => $data['city'] ?? null,
            'state' => $data['state'] ?? null,
            'complement' => $data['complement'] ?? null,
            'number' => $data['number'] ?? null,
            'trade_name' => $data['trade_name'] ?? null,
            'administrator_id' => $data['administrator_id'] ?? null,
        ]);

        // Atualiza os e-mails (deleta os antigos e recria os novos)
        $contact->emails()->delete();
        if (!empty($data['emails'])) {
            foreach ($data['emails'] as $email) {
                if ($email) {
                    $contact->emails()->create([
                        'email' => $email,
                    ]);
                }
            }
        }

        // Atualiza os telefones (deleta os antigos e recria os novos)
        $contact->phones()->delete();
        if (!empty($data['phones'])) {
            foreach ($data['phones'] as $phone) {
                if ($phone) {
                    $contact->phones()->create([
                        'phone' => $phone,
                    ]);
                }
            }
        }

        return redirect()->route('contacts.index')
            ->with('success', 'Contato atualizado com sucesso.');
    }

    /**
     * Remove o contato.
     */
    public function destroy(Request $request)
    {
        $request->validate([
            'contact_id' => 'required|exists:contacts,id',
        ]);

        $contact = Contact::findOrFail($request->contact_id);

        $contact->emails()->delete();
        $contact->phones()->delete();
        $contact->delete();

        return redirect()->route('contacts.index')->with('success', 'Contato deletado com sucesso.');
    }
}
