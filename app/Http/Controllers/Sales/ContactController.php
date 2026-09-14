<?php

namespace App\Http\Controllers\Sales;

use App\Http\Controllers\Controller;
use App\Http\Requests\Sales\StoreContactRequest;
use App\Http\Requests\Sales\UpdateContactRequest;
use App\Models\Contact;
use App\Models\Lead;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class ContactController extends Controller
{
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', Contact::class);

        $search = trim((string) $request->query('search', ''));

        $contacts = Contact::query()
            ->where('created_by', $request->user()->id)
            ->when($search !== '', function ($query) use ($search) {
                $query->where(function ($query) use ($search) {
                    $query->where('name', 'like', "%{$search}%")
                        ->orWhere('company_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%")
                        ->orWhere('phone', 'like', "%{$search}%");
                });
            })
            ->withCount('leads')
            ->latest()
            ->paginate(10)
            ->withQueryString();

        $contacts->getCollection()->each(fn (Contact $contact) => $contact->whatsapp_number = $contact->whatsappNumber());

        return Inertia::render('Sales/Contacts/Index', [
            'contacts' => $contacts,
            'filters' => ['search' => $search],
        ]);
    }

    public function create(): Response
    {
        Gate::authorize('create', Contact::class);

        return Inertia::render('Sales/Contacts/Form');
    }

    public function store(StoreContactRequest $request): RedirectResponse
    {
        $contact = Contact::create([
            ...$request->safe()->except('npwp_document'),
            'created_by' => $request->user()->id,
        ]);

        $this->storeNpwpDocument($request, $contact);

        return redirect()->route('sales.contacts.show', $contact)
            ->with('success', 'Contact berhasil dibuat.');
    }

    public function show(Contact $contact): Response
    {
        Gate::authorize('view', $contact);

        $leads = Lead::query()
            ->where('contact_id', $contact->id)
            ->where('sales_id', request()->user()->id)
            ->latest()
            ->get(['id', 'type', 'stage', 'source', 'created_at']);

        $contact->whatsapp_number = $contact->whatsappNumber();

        return Inertia::render('Sales/Contacts/Show', [
            'contact' => $contact,
            'leads' => $leads,
            'npwpDocumentUrl' => $this->npwpDocumentUrl($contact),
        ]);
    }

    public function edit(Contact $contact): Response
    {
        Gate::authorize('update', $contact);

        return Inertia::render('Sales/Contacts/Form', [
            'contact' => $contact,
            'npwpDocumentUrl' => $this->npwpDocumentUrl($contact),
        ]);
    }

    public function update(UpdateContactRequest $request, Contact $contact): RedirectResponse
    {
        $contact->update($request->safe()->except('npwp_document'));

        $this->storeNpwpDocument($request, $contact);

        return redirect()->route('sales.contacts.show', $contact)
            ->with('success', 'Contact berhasil diperbarui.');
    }

    private function storeNpwpDocument(StoreContactRequest $request, Contact $contact): void
    {
        if (! $request->hasFile('npwp_document')) {
            return;
        }

        $contact->attachments()->create([
            'category' => 'npwp_document',
            'file_path' => $request->file('npwp_document')->store('npwp-documents'),
            'uploaded_by' => $request->user()->id,
        ]);
    }

    private function npwpDocumentUrl(Contact $contact): ?string
    {
        $doc = $contact->npwpDocument();

        return $doc ? Storage::disk('local')->temporaryUrl($doc->file_path, now()->addDay()) : null;
    }

    public function destroy(Contact $contact): RedirectResponse
    {
        Gate::authorize('delete', $contact);

        if (Lead::where('contact_id', $contact->id)->exists()) {
            return back()->with('error', 'Contact tidak dapat dihapus karena sudah digunakan oleh lead.');
        }

        $contact->delete();

        return redirect()->route('sales.contacts.index')
            ->with('success', 'Contact berhasil dihapus.');
    }
}
