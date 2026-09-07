@extends('layouts.app')

@section('content')
<div class="flex flex-wrap items-end justify-between gap-4">
    <div>
        <p class="text-sm font-bold text-brand-700">YOUNZ AI</p>
        <h1 class="mt-1 text-3xl font-black text-ink-900">Knowledge base</h1>
        <p class="mt-2 text-sm text-slate-500">{{ $activeCount }} aktif · {{ $draftCount }} draft. Hanya dokumen aktif yang dipakai Younz AI.</p>
    </div>
    <form class="flex flex-wrap gap-2">
        <input name="q" value="{{ request('q') }}" placeholder="Cari judul atau isi">
        <select name="status">
            <option value="">Semua status</option>
            @foreach(['active' => 'Aktif', 'draft' => 'Draft', 'archived' => 'Arsip'] as $value => $label)
                <option value="{{ $value }}" @selected(request('status') === $value)>{{ $label }}</option>
            @endforeach
        </select>
        <button class="btn-secondary">Filter</button>
    </form>
</div>

<details class="card mt-6" @if($errors->any()) open @endif>
    <summary class="cursor-pointer font-black text-ink-900">+ Tambah dokumen</summary>
    <form method="post" action="{{ route('knowledge.store') }}" class="mt-5 grid gap-4 sm:grid-cols-2">
        @csrf
        <div class="grid gap-1.5 sm:col-span-2"><label>Judul</label><input name="title" value="{{ old('title') }}" required maxlength="180"></div>
        <div class="grid gap-1.5"><label>Tipe</label><select name="type" required>@foreach(['faq','policy','privacy','service','guide'] as $type)<option value="{{ $type }}" @selected(old('type', 'faq') === $type)>{{ strtoupper($type) }}</option>@endforeach</select></div>
        <div class="grid gap-1.5"><label>Status</label><select name="status" required>@foreach(['active' => 'Aktif', 'draft' => 'Draft', 'archived' => 'Arsip'] as $value => $label)<option value="{{ $value }}" @selected(old('status', 'draft') === $value)>{{ $label }}</option>@endforeach</select></div>
        <div class="grid gap-1.5 sm:col-span-2"><label>Isi terverifikasi</label><textarea name="content" rows="8" required maxlength="20000" placeholder="Tuliskan fakta operasional yang sudah diverifikasi.">{{ old('content') }}</textarea><p class="text-xs text-slate-500">Jangan memasukkan API key, password, atau data pribadi pelanggan.</p></div>
        <div class="sm:col-span-2"><button class="btn-primary">Simpan dokumen</button></div>
    </form>
</details>

<div class="mt-6 space-y-4">
    @forelse($documents as $document)
        <details class="card">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-4">
                <span><strong class="text-ink-900">{{ $document->title }}</strong><span class="mt-1 block text-xs text-slate-500">{{ strtoupper($document->type) }} · diperbarui {{ $document->updated_at->diffForHumans() }}</span></span>
                <span @class(['badge', 'bg-emerald-100 text-emerald-700' => $document->status === 'active', 'bg-amber-100 text-amber-700' => $document->status === 'draft', 'bg-slate-100 text-slate-600' => $document->status === 'archived'])>{{ ucfirst($document->status) }}</span>
            </summary>
            <form method="post" action="{{ route('knowledge.update', $document) }}" class="mt-5 grid gap-4 sm:grid-cols-2">
                @csrf @method('put')
                <div class="grid gap-1.5 sm:col-span-2"><label>Judul</label><input name="title" value="{{ $document->title }}" required maxlength="180"></div>
                <div class="grid gap-1.5"><label>Tipe</label><select name="type" required>@foreach(['faq','policy','privacy','service','guide'] as $type)<option value="{{ $type }}" @selected($document->type === $type)>{{ strtoupper($type) }}</option>@endforeach</select></div>
                <div class="grid gap-1.5"><label>Status</label><select name="status" required>@foreach(['active' => 'Aktif', 'draft' => 'Draft', 'archived' => 'Arsip'] as $value => $label)<option value="{{ $value }}" @selected($document->status === $value)>{{ $label }}</option>@endforeach</select></div>
                <div class="grid gap-1.5 sm:col-span-2"><label>Isi terverifikasi</label><textarea name="content" rows="8" required maxlength="20000">{{ $document->content }}</textarea></div>
                <div class="flex flex-wrap gap-2 sm:col-span-2"><button class="btn-primary">Simpan perubahan</button></div>
            </form>
            <form method="post" action="{{ route('knowledge.destroy', $document) }}" class="mt-3" onsubmit="return confirm('Hapus dokumen ini dari knowledge base?')">
                @csrf @method('delete')
                <button class="text-xs font-bold text-red-600">Hapus dokumen</button>
            </form>
        </details>
    @empty
        <div class="card text-center text-sm text-slate-500">Belum ada dokumen knowledge base.</div>
    @endforelse
</div>

<div class="mt-4">{{ $documents->links() }}</div>
@endsection
