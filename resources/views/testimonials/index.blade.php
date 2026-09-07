@extends('layouts.app')

@section('content')
<div>
    <p class="text-sm font-bold text-brand-700">KONTEN PUBLIK</p>
    <h1 class="mt-1 text-3xl font-black text-ink-900">Testimoni terverifikasi</h1>
    <p class="mt-2 max-w-3xl text-sm text-slate-500">Publikasikan hanya testimoni yang benar-benar diterima dan sudah memiliki persetujuan pelanggan.</p>
</div>

<details class="card mt-6" @if($errors->any()) open @endif>
    <summary class="cursor-pointer font-black text-ink-900">+ Tambah testimoni nyata</summary>
    <form method="post" action="{{ route('testimonials.store') }}" class="mt-5 grid gap-4 sm:grid-cols-2">
        @csrf
        <div class="grid gap-1.5"><label>Nama tampilan pelanggan</label><input name="customer_name" value="{{ old('customer_name') }}" required maxlength="80"></div>
        <div class="grid gap-1.5"><label>Peran / jenis pelanggan</label><input name="customer_role" value="{{ old('customer_role') }}" maxlength="100" placeholder="Contoh: Pemilik UMKM"></div>
        <div class="grid gap-1.5 sm:col-span-2"><label>Kutipan asli</label><textarea name="quote" rows="5" required maxlength="800">{{ old('quote') }}</textarea></div>
        <div class="grid gap-1.5"><label>Rating</label><select name="rating">@foreach(range(5, 1) as $rating)<option value="{{ $rating }}" @selected((int) old('rating', 5) === $rating)>{{ $rating }} bintang</option>@endforeach</select></div>
        <div class="grid gap-1.5"><label>Sumber</label><input name="source_label" value="{{ old('source_label') }}" maxlength="80" placeholder="WhatsApp / Google Business"></div>
        <div class="grid gap-1.5"><label>Tanggal persetujuan publikasi</label><input type="date" name="consent_at" value="{{ old('consent_at') }}"></div>
        <div class="grid gap-1.5"><label>Urutan tampil</label><input type="number" name="display_order" min="0" max="999" value="{{ old('display_order', 0) }}" required></div>
        <label class="flex items-center gap-2 sm:col-span-2"><input type="checkbox" name="is_published" value="1" @checked(old('is_published'))><span>Publikasikan di halaman utama</span></label>
        <div class="sm:col-span-2"><button class="btn-primary">Simpan testimoni</button></div>
    </form>
</details>

<div class="mt-6 space-y-4">
    @forelse($testimonials as $testimonial)
        <details class="card">
            <summary class="flex cursor-pointer list-none items-center justify-between gap-4">
                <span><strong class="text-ink-900">{{ $testimonial->customer_name }}</strong><span class="mt-1 block text-xs text-slate-500">{{ $testimonial->customer_role ?: 'Pelanggan' }} · {{ $testimonial->rating }} bintang</span></span>
                <span @class(['badge', 'bg-emerald-100 text-emerald-700' => $testimonial->is_published && $testimonial->consent_at, 'bg-amber-100 text-amber-700' => ! $testimonial->is_published || ! $testimonial->consent_at])>{{ $testimonial->is_published && $testimonial->consent_at ? 'Tayang' : 'Draft' }}</span>
            </summary>
            <form method="post" action="{{ route('testimonials.update', $testimonial) }}" class="mt-5 grid gap-4 sm:grid-cols-2">
                @csrf @method('put')
                <div class="grid gap-1.5"><label>Nama tampilan</label><input name="customer_name" value="{{ $testimonial->customer_name }}" required maxlength="80"></div>
                <div class="grid gap-1.5"><label>Peran</label><input name="customer_role" value="{{ $testimonial->customer_role }}" maxlength="100"></div>
                <div class="grid gap-1.5 sm:col-span-2"><label>Kutipan asli</label><textarea name="quote" rows="5" required maxlength="800">{{ $testimonial->quote }}</textarea></div>
                <div class="grid gap-1.5"><label>Rating</label><select name="rating">@foreach(range(5, 1) as $rating)<option value="{{ $rating }}" @selected($testimonial->rating === $rating)>{{ $rating }} bintang</option>@endforeach</select></div>
                <div class="grid gap-1.5"><label>Sumber</label><input name="source_label" value="{{ $testimonial->source_label }}" maxlength="80"></div>
                <div class="grid gap-1.5"><label>Tanggal persetujuan</label><input type="date" name="consent_at" value="{{ $testimonial->consent_at?->format('Y-m-d') }}"></div>
                <div class="grid gap-1.5"><label>Urutan tampil</label><input type="number" name="display_order" min="0" max="999" value="{{ $testimonial->display_order }}" required></div>
                <label class="flex items-center gap-2 sm:col-span-2"><input type="checkbox" name="is_published" value="1" @checked($testimonial->is_published)><span>Publikasikan di halaman utama</span></label>
                <div class="sm:col-span-2"><button class="btn-primary">Simpan perubahan</button></div>
            </form>
            <form method="post" action="{{ route('testimonials.destroy', $testimonial) }}" class="mt-3" onsubmit="return confirm('Hapus testimoni ini?')">@csrf @method('delete')<button class="text-xs font-bold text-red-600">Hapus testimoni</button></form>
        </details>
    @empty
        <div class="card text-center text-sm text-slate-500">Belum ada testimoni terverifikasi. Halaman publik tidak akan menampilkan kutipan palsu.</div>
    @endforelse
</div>

<div class="mt-4">{{ $testimonials->links() }}</div>
@endsection
