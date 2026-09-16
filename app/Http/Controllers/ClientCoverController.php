<?php

namespace App\Http\Controllers;

use App\Models\Client;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ClientCoverController extends Controller
{
    public function store(Request $request, Client $client)
    {
        $data = $request->validate([
            'theme' => ['required', Rule::in(['iris', 'mist', 'dune', 'rose'])],
            'image' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120', 'dimensions:max_width=6000,max_height=6000'],
        ]);
        $path = $request->hasFile('image') ? $request->file('image')->store('client-covers', 'local') : null;
        abort_if($path === false, 500, 'Não foi possível salvar a imagem.');
        $client->cover_theme = $data['theme'];
        $client->cover_path = $path;
        $client->save();

        return back()->with('success', 'Capa do cliente atualizada.');
    }

    public function show(Client $client)
    {
        abort_unless($client->cover_path && Storage::disk('local')->exists($client->cover_path), 404);

        return response()->file(Storage::disk('local')->path($client->cover_path), [
            'Cache-Control' => 'private, no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
