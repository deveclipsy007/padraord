<?php

namespace App\Http\Controllers;

use App\AI\AudioInspector;
use App\Models\AuditLog;
use App\Models\Opportunity;
use App\Services\DocumentRevisions;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ClientPortalController extends Controller
{
    public function publish(Request $request, Opportunity $opportunity)
    {
        $this->authorizeOwner($request, $opportunity);
        $data = $request->validate(['message' => 'required|string|max:3000', 'milestones' => 'required|string|max:3000', 'deliverables' => 'present|array|max:12', 'deliverables.*' => 'required|string|max:180', 'document_ids' => 'present|array|max:5', 'document_ids.*' => 'integer|distinct', 'expires_days' => 'required|integer|min:1|max:30']);
        $snapshot = ['title' => $opportunity->title, 'client' => $opportunity->client_name, 'event_date' => $opportunity->event_date?->format('d/m/Y'), 'message' => $data['message'], 'milestones' => array_values(array_filter(array_map('trim', explode("\n", $data['milestones'])))), 'deliverables' => array_map(fn ($title, $i) => ['key' => 'delivery-'.$i, 'title' => $title], $data['deliverables'], array_keys($data['deliverables'])), 'proposals' => []];
        foreach ($data['document_ids'] as $id) {
            $document = $opportunity->documents()->whereKey($id)->where('type', 'proposal')->whereIn('status', ['sent', 'accepted', 'changes_requested'])->whereNotNull('sent_at')->first();
            if (! $document) {
                throw ValidationException::withMessages(['document_ids' => 'Selecione somente propostas enviadas deste projeto.']);
            }
            $release = app(DocumentRevisions::class)->released($document);
            $snapshot['proposals'][] = ['title' => $document->title, 'version' => $document->version, 'sections' => collect($release['sections'] ?? [])->only(['objective', 'scope', 'inclusions', 'exclusions', 'conditions'])->all(), 'total_cents' => data_get($release, 'sources.budget.totalCents')];
        }
        $token = Str::random(64);
        DB::transaction(function () use ($request, $opportunity, $snapshot, $data, $token) {
            $id = DB::table('client_portal_links')->insertGetId(['opportunity_id' => $opportunity->id, 'created_by' => $request->user()->id, 'token_hash' => hash('sha256', $token), 'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR), 'expires_at' => now()->addDays($data['expires_days']), 'created_at' => now(), 'updated_at' => now()]);
            AuditLog::create(['user_id' => $request->user()->id, 'action' => 'portal.published', 'subject_type' => Opportunity::class, 'subject_id' => $opportunity->id, 'metadata' => ['portal_id' => $id]]);
        });

        return back()->with('portal_url', url('/client-portal/'.$token))->with('success', 'Portal publicado. Copie o link agora; o endereço completo não fica armazenado.');
    }

    public function show(string $token)
    {
        $link = $this->resolve($token);

        return response()->view('portal.show', ['token' => $token, 'portal' => json_decode($link->snapshot, true), 'expires' => $link->expires_at, 'uploadLimitMb' => round(min(10240, AudioInspector::maxKilobytes()) / 1024, 1), 'responses' => DB::table('client_portal_responses')->where('client_portal_link_id', $link->id)->get()->keyBy('item_key')])->header('Cache-Control', 'no-store, private')->header('Referrer-Policy', 'no-referrer')->header('X-Robots-Tag', 'noindex, nofollow');
    }

    public function respond(Request $request, string $token)
    {
        $data = $request->validate(['item_key' => 'required|string|max:80', 'decision' => 'required|in:approved,changes_requested', 'name' => 'required|string|max:160', 'message' => 'nullable|string|max:3000']);
        DB::transaction(function () use ($data, $token) {
            $link = $this->resolve($token, true);
            $snapshot = json_decode($link->snapshot, true);
            abort_unless(collect($snapshot['deliverables'])->contains('key', $data['item_key']), 404);
            $old = DB::table('client_portal_responses')->where('client_portal_link_id', $link->id)->where('item_key', $data['item_key'])->first();
            if ($old) {
                if ($old->decision !== $data['decision'] || $old->name !== $data['name'] || (string) $old->message !== (string) ($data['message'] ?? '')) {
                    throw ValidationException::withMessages(['response' => 'Já existe uma resposta para esta entrega. Peça à equipe uma nova revisão.']);
                }

                return;
            }
            DB::table('client_portal_responses')->insert([...$data, 'client_portal_link_id' => $link->id, 'created_at' => now(), 'updated_at' => now()]);
            AuditLog::create(['action' => 'portal.response', 'subject_type' => Opportunity::class, 'subject_id' => $link->opportunity_id, 'metadata' => ['portal_id' => $link->id, 'item' => $data['item_key'], 'decision' => $data['decision']]]);
        }, 3);

        return back()->with('success', 'Retorno registrado. A equipe pode consultá-lo no projeto.');
    }

    public function upload(Request $request, string $token)
    {
        $data = $request->validate(['name' => 'required|string|max:160', 'file' => 'required|file|mimes:pdf,jpg,jpeg,png|max:'.min(10240, AudioInspector::maxKilobytes())]);
        $path = null;
        try {
            DB::transaction(function () use ($request, $data, $token, &$path) {
                $link = $this->resolve($token, true);
                if (DB::table('client_portal_uploads')->where('client_portal_link_id', $link->id)->count() >= 20) {
                    throw ValidationException::withMessages(['file' => 'Limite de 20 arquivos atingido. Entre em contato com a equipe.']);
                }
                $path = $request->file('file')->store('client-portal', 'local');
                if (! $path) {
                    throw ValidationException::withMessages(['file' => 'Não foi possível guardar o arquivo. Tente novamente.']);
                }
                DB::table('client_portal_uploads')->insert(['client_portal_link_id' => $link->id, 'name' => $data['name'], 'original_name' => Str::limit(basename($request->file('file')->getClientOriginalName()), 200, ''), 'path' => $path, 'size' => $request->file('file')->getSize(), 'created_at' => now(), 'updated_at' => now()]);
                AuditLog::create(['action' => 'portal.file_received', 'subject_type' => Opportunity::class, 'subject_id' => $link->opportunity_id, 'metadata' => ['portal_id' => $link->id]]);
            });
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            } throw $e;
        }

        return back()->with('success', 'Arquivo recebido e guardado para a equipe.');
    }

    public function download(Request $request, Opportunity $opportunity, int $upload)
    {
        $this->authorizeOwner($request, $opportunity);
        $file = DB::table('client_portal_uploads as u')->join('client_portal_links as p', 'p.id', '=', 'u.client_portal_link_id')->where('u.id', $upload)->where('p.opportunity_id', $opportunity->id)->select('u.*')->first();
        abort_unless($file, 404);

        return Storage::disk('local')->download($file->path, $file->original_name, ['X-Content-Type-Options' => 'nosniff']);
    }

    public function revoke(Request $request, Opportunity $opportunity, int $portal)
    {
        $this->authorizeOwner($request, $opportunity);
        DB::transaction(function () use ($portal, $opportunity, $request) {
            $row = DB::table('client_portal_links')->where('id', $portal)->where('opportunity_id', $opportunity->id)->lockForUpdate()->first();
            abort_unless($row, 404);
            DB::table('client_portal_links')->where('id', $portal)->update(['revoked_at' => now(), 'updated_at' => now()]);
            AuditLog::create(['user_id' => $request->user()->id, 'action' => 'portal.revoked', 'subject_type' => Opportunity::class, 'subject_id' => $opportunity->id, 'metadata' => ['portal_id' => $portal]]);
        });

        return back()->with('success', 'Link revogado. O histórico permanece disponível.');
    }

    private function resolve(string $token, bool $lock = false): object
    {
        $q = DB::table('client_portal_links')->where('token_hash', hash('sha256', $token));
        if ($lock) {
            $q->lockForUpdate();
        }
        $row = $q->first();
        abort_unless($row && ! $row->revoked_at && now()->lt($row->expires_at), 404);

        return $row;
    }

    private function authorizeOwner(Request $request, Opportunity $case): void
    {
        abort_unless($request->user()->isAdmin() || $case->owner_id === $request->user()->id, 403);
    }
}
