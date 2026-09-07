@php
    $cookieConsentSaved = in_array(
        request()->cookie('ydc_cookie_consent'),
        ['all', 'essential'],
        true,
    );
@endphp

<section
    data-cookie-consent
    data-cookie-consent-state="{{ $cookieConsentSaved ? 'saved' : 'unset' }}"
    @if ($cookieConsentSaved) hidden @endif
    class="fixed inset-x-0 bottom-0 z-[80] p-4 sm:p-6"
    role="dialog"
    aria-live="polite"
    aria-labelledby="cookie-consent-title"
    aria-describedby="cookie-consent-description"
>
    <div class="mx-auto flex max-w-5xl flex-col gap-5 rounded-[1.75rem] border border-emerald-200 bg-white p-5 shadow-[0_28px_80px_-28px_rgba(15,23,42,.55)] sm:flex-row sm:items-center sm:justify-between sm:p-6">
        <div class="max-w-2xl">
            <p id="cookie-consent-title" class="font-display text-lg font-extrabold text-[#132016]">Pengaturan cookie</p>
            <p id="cookie-consent-description" class="mt-1.5 text-xs leading-5 text-slate-600 sm:text-sm sm:leading-6">
                Cookie esensial menjaga sesi login dan keamanan formulir. Cookie opsional hanya digunakan setelah Anda memberikan izin.
                <a href="{{ route('privacy') }}" class="font-bold text-[#006c49] underline underline-offset-2">Pelajari kebijakan privasi</a>.
            </p>
        </div>
        <div class="flex shrink-0 flex-col-reverse gap-2 sm:flex-row">
            <button type="button" data-cookie-essential class="min-h-11 rounded-xl border border-slate-300 px-4 py-2 text-xs font-bold text-slate-700 transition hover:border-emerald-500 hover:text-emerald-700">Hanya esensial</button>
            <button type="button" data-cookie-accept class="min-h-11 rounded-xl bg-[#006c49] px-4 py-2 text-xs font-bold text-white transition hover:bg-[#00583c]">Terima semua</button>
        </div>
    </div>
</section>
