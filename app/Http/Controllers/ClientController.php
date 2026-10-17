<?php

namespace App\Http\Controllers;

use App\Http\Requests\ClientRequest;
use App\Models\Client;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ClientController extends Controller
{
    public function index(): View
    {
        Gate::authorize('viewAny', Client::class);

        return view('clients.index', ['clients' => Client::withCount('meetings')->orderBy('name')->paginate(15)]);
    }

    public function create(): View
    {
        Gate::authorize('create', Client::class);

        return view('clients.form', ['client' => new Client]);
    }

    public function store(ClientRequest $request): RedirectResponse
    {
        Client::create($request->validated());

        return redirect()->route('clients.index')->with('success', 'Klien berhasil ditambahkan.');
    }

    public function edit(Client $client): View
    {
        Gate::authorize('update', $client);

        return view('clients.form', compact('client'));
    }

    public function update(ClientRequest $request, Client $client): RedirectResponse
    {
        $client->update($request->validated());

        return redirect()->route('clients.index')->with('success', 'Data klien diperbarui.');
    }

    public function destroy(Client $client): RedirectResponse
    {
        Gate::authorize('delete', $client);
        DB::transaction(function () use ($client): void {
            $client = Client::whereKey($client->id)->lockForUpdate()->firstOrFail();
            if ($client->meetings()->exists() || $client->accounts()->exists()) {
                throw ValidationException::withMessages(['client' => 'Klien masih terkait rapat atau akun dan tidak dapat dihapus.']);
            }
            $client->delete();
        });

        return back()->with('success', 'Klien dihapus.');
    }
}
