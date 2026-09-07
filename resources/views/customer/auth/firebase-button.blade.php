@php
    $firebaseConfig = config('services.firebase.web');
    $firebaseRequiredConfig = collect($firebaseConfig)->only(['apiKey', 'authDomain', 'projectId', 'appId']);
    $firebaseLabel = $firebaseLabel ?? 'Masuk dengan Google';
    $firebaseReady = config('services.firebase.enabled')
        && $firebaseRequiredConfig->every(fn ($value) => filled($value));
@endphp

@if($firebaseReady)
    <div
        class="mt-6"
        data-firebase-auth
        data-firebase-config="{{ json_encode($firebaseConfig) }}"
        data-firebase-endpoint="{{ route('customer.firebase') }}"
        data-firebase-default-label="{{ $firebaseLabel }}"
    >
        <button
            type="button"
            data-firebase-google
            class="flex min-h-12 w-full items-center justify-center gap-3 rounded-xl border border-slate-300 bg-white px-4 py-3 text-sm font-bold text-slate-700 shadow-sm transition hover:border-slate-400 hover:bg-slate-50 disabled:cursor-wait disabled:opacity-60"
        >
            <span data-auth-icon="gmail" aria-hidden="true"></span>
            <span data-firebase-button-label>{{ $firebaseLabel }}</span>
        </button>
        <p data-firebase-error role="alert" aria-live="polite" class="mt-3 hidden rounded-xl border border-red-200 bg-red-50 px-3 py-2 text-xs leading-5 text-red-700"></p>
    </div>
    <div class="my-5 flex items-center gap-3 text-[10px] font-bold tracking-wider text-slate-400 uppercase"><span class="h-px flex-1 bg-slate-200"></span>atau gunakan email<span class="h-px flex-1 bg-slate-200"></span></div>
@endif
