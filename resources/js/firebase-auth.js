const friendlyFirebaseError = (error) => {
    const messages = {
        'auth/popup-closed-by-user': 'Login Google dibatalkan.',
        'auth/cancelled-popup-request': 'Login Google dibatalkan.',
        'auth/popup-blocked': 'Popup Google diblokir browser. Izinkan popup lalu coba lagi.',
        'auth/unauthorized-domain': 'Domain website ini belum diizinkan pada Firebase Authentication.',
        'auth/network-request-failed': 'Koneksi ke Firebase gagal. Periksa internet lalu coba kembali.',
        'auth/app-not-authorized': 'Domain atau API key aplikasi ini belum diizinkan untuk Firebase Authentication.',
        'auth/internal-error': 'Login Google gagal dimuat. Muat ulang halaman lalu coba kembali.',
    };

    return messages[error?.code]
        ?? (error instanceof Error && error.message ? error.message : 'Login Google gagal. Silakan coba kembali.');
};

export const setupFirebaseGoogleLogin = async () => {
    const roots = [...document.querySelectorAll('[data-firebase-auth]')]
        .filter((root) => root instanceof HTMLElement);
    if (roots.length === 0) return;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';

    const showError = (root, message) => {
        const errorBox = root?.querySelector('[data-firebase-error]');
        if (!(errorBox instanceof HTMLElement)) return;
        errorBox.textContent = message;
        errorBox.classList.remove('hidden');
    };

    const clearErrors = () => roots.forEach((root) => {
        const errorBox = root.querySelector('[data-firebase-error]');
        if (!(errorBox instanceof HTMLElement)) return;
        errorBox.textContent = '';
        errorBox.classList.add('hidden');
    });

    const setGoogleBusy = (busy, activeRoot = null, text = 'Menghubungkan ke Google…') => roots.forEach((root) => {
        const button = root.querySelector('[data-firebase-google]');
        const label = button?.querySelector('[data-firebase-button-label]');
        if (button instanceof HTMLButtonElement) button.disabled = busy;
        if (label) {
            const defaultLabel = root.dataset.firebaseDefaultLabel ?? 'Masuk dengan Google';
            label.textContent = busy && root === activeRoot ? text : defaultLabel;
        }
    });

    const activeRoot = () => {
        const mode = document.querySelector('[data-customer-auth-shell]')?.dataset.authMode;
        return roots.find((root) => root.closest('[data-auth-panel]')?.dataset.authPanel === mode) ?? roots[0];
    };

    let redirectStorageKey = null;

    try {
        const config = JSON.parse(roots[0].dataset.firebaseConfig ?? '{}');
        if (!config.apiKey || !config.authDomain || !config.projectId || !config.appId) return;

        const [{initializeApp, getApps}, authModule] = await Promise.all([
            import('firebase/app'),
            import('firebase/auth'),
        ]);
        const app = getApps().length > 0 ? getApps()[0] : initializeApp(config);
        const auth = authModule.getAuth(app);
        auth.languageCode = 'id';
        await authModule.setPersistence(auth, authModule.browserLocalPersistence);

        redirectStorageKey = `younz-firebase-google-redirect:${config.projectId}`;
        const hasPendingRedirect = sessionStorage.getItem(redirectStorageKey) === 'pending';

        const exchangeToken = async (user, root) => {
            const endpoint = root?.dataset.firebaseEndpoint;
            if (!endpoint) throw new Error('Endpoint login Google tidak tersedia.');

            const idToken = await user.getIdToken(true);
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({id_token: idToken}),
            });
            const payload = await response.json().catch(() => ({}));

            if (!response.ok) {
                const firstError = Object.values(payload.errors ?? {}).flat()[0];
                throw new Error(firstError ?? payload.message ?? 'Login Google gagal diverifikasi.');
            }

            window.location.assign(payload.redirect);
        };

        const provider = new authModule.GoogleAuthProvider();
        provider.setCustomParameters({prompt: 'select_account'});

        const redirectResult = await authModule.getRedirectResult(auth);

        if (hasPendingRedirect && typeof auth.authStateReady === 'function') {
            await auth.authStateReady();
        }

        const redirectUser = redirectResult?.user ?? (hasPendingRedirect ? auth.currentUser : null);
        if (redirectUser) {
            const root = activeRoot();
            setGoogleBusy(true, root, 'Memverifikasi akun…');
            await exchangeToken(redirectUser, root);
            sessionStorage.removeItem(redirectStorageKey);
            return;
        }

        if (hasPendingRedirect) {
            sessionStorage.removeItem(redirectStorageKey);
            showError(
                activeRoot(),
                'Google berhasil dibuka, tetapi sesi login belum diterima. Silakan coba sekali lagi.',
            );
        }

        roots.forEach((root) => {
            const googleButton = root.querySelector('[data-firebase-google]');
            googleButton?.addEventListener('click', async () => {
                clearErrors();
                setGoogleBusy(true, root, 'Membuka Google…');

                try {
                    sessionStorage.setItem(redirectStorageKey, 'pending');
                    await authModule.signInWithRedirect(auth, provider);
                } catch (error) {
                    sessionStorage.removeItem(redirectStorageKey);
                    showError(root, friendlyFirebaseError(error));
                    setGoogleBusy(false);
                }
            });
        });
    } catch (error) {
        if (redirectStorageKey) sessionStorage.removeItem(redirectStorageKey);
        const root = activeRoot();
        showError(root, friendlyFirebaseError(error));
        setGoogleBusy(false);
    }
};
