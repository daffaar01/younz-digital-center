<?php

namespace App\Http\Controllers;

use App\Http\Requests\KnowledgeDocumentRequest;
use App\Models\KnowledgeDocument;
use App\Support\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class KnowledgeDocumentController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(): View
    {
        $documents = KnowledgeDocument::query()
            ->when(request('q'), fn ($query, string $q) => $query->where(fn ($query) => $query
                ->where('title', 'like', "%{$q}%")
                ->orWhere('content', 'like', "%{$q}%")))
            ->when(request('status'), fn ($query, string $status) => $query->where('status', $status))
            ->latest('updated_at')
            ->paginate(15)
            ->withQueryString();

        return view('knowledge.index', [
            'documents' => $documents,
            'activeCount' => KnowledgeDocument::query()->where('status', 'active')->count(),
            'draftCount' => KnowledgeDocument::query()->where('status', 'draft')->count(),
        ]);
    }

    public function store(KnowledgeDocumentRequest $request): RedirectResponse
    {
        $document = KnowledgeDocument::create($request->validated() + [
            'created_by' => $request->user()->id,
        ]);
        $this->audit->log('knowledge_document.created', $document, after: $document->toArray());

        return back()->with('status', 'Dokumen knowledge base berhasil ditambahkan.');
    }

    public function update(KnowledgeDocumentRequest $request, KnowledgeDocument $document): RedirectResponse
    {
        $before = $document->toArray();
        $document->update($request->validated());
        $this->audit->log('knowledge_document.updated', $document, $before, $document->fresh()->toArray());

        return back()->with('status', 'Dokumen knowledge base berhasil diperbarui.');
    }

    public function destroy(KnowledgeDocument $document): RedirectResponse
    {
        $before = $document->toArray();
        $this->audit->log('knowledge_document.deleted', $document, $before);
        $document->delete();

        return back()->with('status', 'Dokumen knowledge base berhasil dihapus.');
    }
}
