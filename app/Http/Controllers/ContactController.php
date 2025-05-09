<?php

namespace App\Http\Controllers;

use App\Models\Contact;
use Inertia\Inertia;
use Inertia\Response;

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


}
