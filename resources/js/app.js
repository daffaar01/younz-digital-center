const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)').matches;

if (!reducedMotion) {
    import('lenis').then(({default: Lenis}) => {
        new Lenis({
            autoRaf: true,
            anchors: true,
            lerp: 0.12,
            stopInertiaOnNavigate: true,
        });
    }).catch(() => {});
}

let dotLottieModule;
const getDotLottie = () => {
    dotLottieModule ??= import('@lottiefiles/dotlottie-web').then(({DotLottie}) => {
        DotLottie.setWasmUrl('/animations/dotlottie-player.wasm');
        return DotLottie;
    });

    return dotLottieModule;
};

const setupThinkingLottie = async (container) => {
    const canvas = container.querySelector('[data-ai-thinking-canvas]');
    if (!(canvas instanceof HTMLCanvasElement)) return;

    const DotLottie = await getDotLottie();
    const player = new DotLottie({
        canvas,
        src: '/animations/younz-ai-thinking.lottie',
        autoplay: !reducedMotion,
        loop: true,
    });
    const showAnimation = () => container.classList.add('is-lottie-ready');
    player.addEventListener('load', showAnimation);
    player.addEventListener('render', showAnimation);
};
const navigation = document.querySelector('[data-public-nav]');
const menuButton = document.querySelector('[data-mobile-menu-toggle]');
const mobileMenu = document.querySelector('[data-mobile-menu]');
const mobileMenuLabel = menuButton?.querySelector('[data-mobile-menu-label]');

const setPublicMobileMenu = (open, {restoreFocus = false} = {}) => {
    if (!(menuButton instanceof HTMLButtonElement) || !(mobileMenu instanceof HTMLElement)) return;

    menuButton.setAttribute('aria-expanded', String(open));
    menuButton.classList.toggle('is-open', open);
    mobileMenu.classList.toggle('is-open', open);
    mobileMenu.setAttribute('aria-hidden', String(!open));
    mobileMenu.toggleAttribute('inert', !open);
    document.body.classList.toggle('public-menu-open', open);
    if (mobileMenuLabel) mobileMenuLabel.textContent = open ? 'Tutup menu' : 'Buka menu';

    if (!open && restoreFocus) menuButton.focus({preventScroll: true});
};

menuButton?.addEventListener('click', () => {
    setPublicMobileMenu(menuButton.getAttribute('aria-expanded') !== 'true');
});

mobileMenu?.addEventListener('click', (event) => {
    if (!(event.target instanceof Element) || !event.target.closest('a, button[type="submit"]')) return;
    setPublicMobileMenu(false);
});

document.addEventListener('keydown', (event) => {
    if (event.key !== 'Escape' || menuButton?.getAttribute('aria-expanded') !== 'true') return;
    setPublicMobileMenu(false, {restoreFocus: true});
});

const desktopNavigation = window.matchMedia('(min-width: 1024px)');
const closePublicMobileMenuOnDesktop = (event) => {
    if (event.matches) setPublicMobileMenu(false);
};

desktopNavigation.addEventListener?.('change', closePublicMobileMenuOnDesktop);

window.addEventListener('scroll', () => navigation?.classList.toggle('shadow-md', window.scrollY > 40), {passive: true});

document.querySelectorAll('[data-customer-auth-shell]').forEach((shell) => {
    const panels = shell.querySelectorAll('[data-auth-panel]');
    const toggleButtons = shell.querySelectorAll('[data-customer-auth-toggle]');

    const setAuthMode = (mode, {focus = false, updateUrl = false} = {}) => {
        const registerMode = mode === 'register';
        shell.classList.toggle('is-register', registerMode);
        shell.dataset.authMode = registerMode ? 'register' : 'login';

        panels.forEach((panel) => {
            const active = panel.dataset.authPanel === shell.dataset.authMode;
            panel.toggleAttribute('inert', !active);
            panel.setAttribute('aria-hidden', String(!active));
        });

        toggleButtons.forEach((button) => {
            button.setAttribute('aria-pressed', String(button.dataset.customerAuthToggle === shell.dataset.authMode));
        });

        if (updateUrl) {
            const targetUrl = registerMode ? shell.dataset.registerUrl : shell.dataset.loginUrl;
            if (targetUrl) window.history.replaceState({}, '', targetUrl);
        }

        if (focus) {
            const target = shell.querySelector(`[data-auth-panel="${shell.dataset.authMode}"] input:not([type="hidden"])`);
            window.setTimeout(() => target?.focus({preventScroll: true}), window.matchMedia('(prefers-reduced-motion: reduce)').matches ? 0 : 720);
        }
    };

    toggleButtons.forEach((button) => button.addEventListener('click', () => {
        setAuthMode(button.dataset.customerAuthToggle, {focus: true, updateUrl: true});
    }));

    setAuthMode(shell.dataset.authMode);
});

document.querySelectorAll('[data-password-field]').forEach((field) => {
    const input = field.querySelector('[data-password-input]');
    const toggle = field.querySelector('[data-password-toggle]');
    const label = toggle?.querySelector('[data-password-toggle-label]');
    if (!(input instanceof HTMLInputElement) || !(toggle instanceof HTMLButtonElement)) return;

    const setPasswordVisible = (visible) => {
        input.type = visible ? 'text' : 'password';
        field.classList.toggle('is-password-visible', visible);
        toggle.setAttribute('aria-pressed', String(visible));
        if (label) label.textContent = visible ? 'Sembunyikan password' : 'Tampilkan password';
    };

    toggle.addEventListener('click', () => {
        const visible = input.type === 'password';
        setPasswordVisible(visible);
        input.focus({preventScroll: true});
        input.setSelectionRange(input.value.length, input.value.length);
    });

    field.addEventListener('pointermove', (event) => {
        const eye = toggle.getBoundingClientRect();
        const eyeX = eye.left + (eye.width / 2);
        const eyeY = eye.top + (eye.height / 2);
        const pointerAngle = Math.atan2(event.clientY - eyeY, event.clientX - eyeX) * (180 / Math.PI);
        field.style.setProperty('--password-beam-angle', `${pointerAngle - 180}deg`);
    });

    field.addEventListener('pointerleave', () => field.style.setProperty('--password-beam-angle', '0deg'));
    input.addEventListener('keydown', (event) => {
        if (event.key !== 'Escape' || input.type !== 'text') return;
        setPasswordVisible(false);
    });
});

if (document.querySelector('[data-firebase-google]')) {
    import('./firebase-auth').then(({setupFirebaseGoogleLogin}) => setupFirebaseGoogleLogin());
}

const appSidebar = document.querySelector('[data-app-sidebar]');
const appSidebarOverlay = document.querySelector('[data-app-sidebar-overlay]');
const appMenuButtons = document.querySelectorAll('[data-app-menu-toggle]');

const setAppSidebar = (open) => {
    if (!appSidebar) return;
    appSidebar.classList.toggle('-translate-x-full', !open);
    appSidebarOverlay?.classList.toggle('hidden', !open);
    appMenuButtons.forEach((button) => button.setAttribute('aria-expanded', String(open)));
    document.body.classList.toggle('overflow-hidden', open && window.innerWidth < 1024);
};

appMenuButtons.forEach((button) => button.addEventListener('click', () => {
    setAppSidebar(button.getAttribute('aria-expanded') !== 'true');
}));
document.querySelector('[data-app-menu-close]')?.addEventListener('click', () => setAppSidebar(false));
appSidebarOverlay?.addEventListener('click', () => setAppSidebar(false));
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') setAppSidebar(false);
});

document.getElementById('estimator-btn')?.addEventListener('click', () => {
    const service = document.getElementById('estimator-service');
    const qty = document.getElementById('estimator-qty');
    const result = document.getElementById('estimator-result');
    const total = document.getElementById('estimator-total');
    if (!service?.value) { alert('Pilih layanan terlebih dahulu.'); return; }
    const price = parseInt(service.value) * parseInt(qty?.value || 1);
    if (total) total.textContent = price.toLocaleString('id-ID');
    if (result) result.classList.remove('hidden');
});

document.querySelectorAll('[data-service-image]').forEach((image) => {
    const hideBrokenImage = () => image.classList.add('hidden');
    if (image.complete && image.naturalWidth === 0) hideBrokenImage();
    image.addEventListener('error', hideBrokenImage, {once: true});
});

const serviceTrack = document.querySelector('[data-service-track]');
document.querySelector('[data-service-prev]')?.addEventListener('click', () => serviceTrack?.scrollBy({left: -390, behavior: 'smooth'}));
document.querySelector('[data-service-next]')?.addEventListener('click', () => serviceTrack?.scrollBy({left: 390, behavior: 'smooth'}));

const aiChatHistories = new WeakMap();

const normalizeAiBranding = (content) => String(content)
    .replaceAll('Tanya Younz', 'Younz AI')
    .replaceAll('Tanya AI', 'Younz AI');

const aiPendingMessage = 'Sedang menyiapkan jawaban...';

const typingIndicatorHtml = '<span class="flex min-h-10 items-center gap-2" role="status"><span class="h-2 w-2 animate-pulse rounded-full bg-emerald-500" aria-hidden="true"></span><span class="text-xs font-medium text-slate-500">Younz AI sedang menyiapkan jawaban...</span></span>';

const renderAiAssistantAvatar = (container, avatar, thinking = false) => {
    avatar.replaceChildren();
    avatar.className = thinking
        ? 'mt-1 grid h-16 w-20 shrink-0 place-items-center overflow-hidden'
        : 'mt-1 grid h-10 w-8 shrink-0 overflow-hidden rounded-xl bg-[#cbf75e] ring-1 ring-emerald-900/10';

    if (thinking) {
        const canvas = document.createElement('canvas');
        canvas.setAttribute('data-ai-thinking-canvas', '');
        canvas.className = 'h-full w-full opacity-0 transition-opacity';
        avatar.appendChild(canvas);

        const fallback = document.createElement('span');
        fallback.className = 'ai-thinking-fallback absolute grid h-10 w-10 place-items-center rounded-full bg-[#cbf75e] text-lg font-black text-[#132016]';
        fallback.textContent = 'Y';
        avatar.appendChild(fallback);
        setupThinkingLottie(avatar).catch(() => {});
        return;
    }

    const avatarTemplate = container.closest('[data-ai-chat]')?.querySelector('[data-ai-avatar-template]');
    if (avatarTemplate instanceof HTMLTemplateElement) {
        avatar.appendChild(avatarTemplate.content.cloneNode(true));
    }
};

const restoreAiAssistantAvatar = (bubble) => {
    const row = bubble.closest('[data-ai-assistant-row]');
    const avatar = row?.querySelector('[data-ai-assistant-avatar]');
    const container = row?.closest('[data-ai-messages]');
    if (!(avatar instanceof HTMLElement) || !(container instanceof HTMLElement)) return;

    renderAiAssistantAvatar(container, avatar);
    row.querySelector('[data-ai-feedback]')?.removeAttribute('hidden');
};

const submitAiFeedback = async (bubbleWrap, feedback, label) => {
    const interactionToken = bubbleWrap.dataset.aiInteraction;
    if (!interactionToken) return;
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const response = await fetch('/tanya-ai/feedback', {
        method: 'POST',
        headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken},
        body: JSON.stringify({interaction_token: interactionToken, feedback}),
    });
    if (!response.ok) throw new Error('Feedback tidak dapat disimpan.');
    label.textContent = 'Terima kasih atas masukannya!';
};

const appendAiMessage = (container, role, content, sources) => {
    const row = document.createElement('div');
    const bubbleWrap = document.createElement('div');
    const bubble = document.createElement('p');

    if (role === 'user') {
        row.className = 'flex justify-end gap-2.5';
        bubbleWrap.className = 'flex flex-col items-end max-w-[80%]';
        bubble.className = 'whitespace-pre-wrap rounded-2xl rounded-br-sm bg-[#006c49] px-4 py-3 text-left text-sm leading-6 text-white shadow-sm';
        bubble.textContent = content;
        bubbleWrap.appendChild(bubble);

        const avatar = document.createElement('span');
        avatar.className = 'mt-1 grid h-8 w-8 shrink-0 place-items-center rounded-full bg-[#006c49] text-xs font-bold text-white';
        avatar.textContent = 'A';
        row.appendChild(bubbleWrap);
        row.appendChild(avatar);
    } else {
        const isPending = content === aiPendingMessage;
        row.className = 'flex justify-start gap-2.5';
        row.setAttribute('data-ai-assistant-row', '');
        bubbleWrap.className = isPending
            ? 'flex max-w-[68%] flex-col items-start sm:max-w-[80%]'
            : 'flex max-w-[80%] flex-col items-start';
        bubble.className = 'whitespace-pre-wrap rounded-2xl rounded-bl-sm bg-white px-4 py-3 text-left text-sm leading-6 text-slate-700 shadow-sm';
        if (isPending) {
            bubble.innerHTML = typingIndicatorHtml;
        } else {
            bubble.textContent = normalizeAiBranding(content);
        }
        bubbleWrap.appendChild(bubble);

        if (sources && sources.length > 0) {
            const src = document.createElement('div');
            src.className = 'mt-1.5 flex flex-wrap gap-1.5';
            sources.forEach((s) => {
                const tag = document.createElement('span');
                tag.className = 'rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-medium text-emerald-700';
                tag.textContent = s.title;
                src.appendChild(tag);
            });
            bubbleWrap.appendChild(src);
        }

        const feedbackRow = document.createElement('div');
        feedbackRow.className = 'mt-1.5 flex items-center gap-2';
        feedbackRow.setAttribute('data-ai-feedback', '');
        feedbackRow.hidden = isPending;
        const fbLabel = document.createElement('span');
        fbLabel.className = 'text-[10px] text-slate-400';
        fbLabel.textContent = 'Bermanfaat?';
        feedbackRow.appendChild(fbLabel);

        const thumbsUp = document.createElement('button');
        thumbsUp.type = 'button';
        thumbsUp.setAttribute('aria-label', 'Jawaban bermanfaat');
        thumbsUp.className = 'rounded-full p-1 text-slate-300 transition hover:text-emerald-600 hover:bg-emerald-50';
        thumbsUp.textContent = '👍';
        thumbsUp.addEventListener('click', async () => {
            try {
                await submitAiFeedback(bubbleWrap, 'helpful', fbLabel);
                thumbsUp.classList.add('text-emerald-600');
                thumbsUp.classList.remove('text-slate-300');
                thumbsDown.classList.remove('text-red-500');
            } catch (_) {
                fbLabel.textContent = 'Feedback gagal disimpan.';
            }
        });

        const thumbsDown = document.createElement('button');
        thumbsDown.type = 'button';
        thumbsDown.setAttribute('aria-label', 'Jawaban tidak bermanfaat');
        thumbsDown.className = 'rounded-full p-1 text-slate-300 transition hover:text-red-500 hover:bg-red-50';
        thumbsDown.textContent = '👎';
        thumbsDown.addEventListener('click', async () => {
            try {
                await submitAiFeedback(bubbleWrap, 'not_helpful', fbLabel);
                thumbsDown.classList.add('text-red-500');
                thumbsDown.classList.remove('text-slate-300');
                thumbsUp.classList.remove('text-emerald-600');
            } catch (_) {
                fbLabel.textContent = 'Feedback gagal disimpan.';
            }
        });

        feedbackRow.appendChild(thumbsUp);
        feedbackRow.appendChild(thumbsDown);
        bubbleWrap.appendChild(feedbackRow);

        const avatar = document.createElement('span');
        avatar.setAttribute('aria-hidden', 'true');
        avatar.setAttribute('data-ai-assistant-avatar', '');
        renderAiAssistantAvatar(container, avatar, isPending);
        row.appendChild(avatar);
        row.appendChild(bubbleWrap);
    }

    container.appendChild(row);
    container.scrollTop = container.scrollHeight;

    return bubble;
};

document.querySelectorAll('[data-ai-chat]').forEach((form) => {
    const historyAttr = form.getAttribute('data-ai-history');
    if (!historyAttr || historyAttr === '[]') return;
    try {
        const savedHistory = JSON.parse(historyAttr);
        if (!Array.isArray(savedHistory) || savedHistory.length === 0) return;
        const boundedHistory = savedHistory.slice(-12).map((message) => ({
            ...message,
            content: message.role === 'assistant' ? normalizeAiBranding(message.content) : message.content,
        }));
        aiChatHistories.set(form, boundedHistory);
        const container = form.querySelector('[data-ai-messages]');
        const emptyState = form.querySelector('[data-ai-empty]');
        if (!container) return;
        emptyState?.remove();
        boundedHistory.forEach((msg) => appendAiMessage(container, msg.role, msg.content));
    } catch (_) {}
});

const topupCheckout = document.querySelector('[data-topup-checkout]');

if (topupCheckout) {
    const products = [...topupCheckout.querySelectorAll('[data-topup-product]')];
    const summaryName = topupCheckout.querySelector('[data-topup-summary-name]');
    const summaryBrand = topupCheckout.querySelector('[data-topup-summary-brand]');
    const summaryPrice = topupCheckout.querySelector('[data-topup-summary-price]');
    const submit = topupCheckout.querySelector('[data-topup-submit]');

    const selectProduct = (card) => {
        products.forEach((product) => {
            const active = product === card;
            product.classList.toggle('border-emerald-500', active);
            product.classList.toggle('ring-2', active);
            product.classList.toggle('ring-emerald-100', active);
            product.classList.toggle('border-slate-200', !active);
            product.querySelector('[data-product-check]')?.classList.toggle('hidden', !active);
            product.querySelector('[data-product-check]')?.classList.toggle('grid', active);
        });

        if (summaryName) summaryName.textContent = card.dataset.name ?? 'Produk dipilih';
        if (summaryBrand) summaryBrand.textContent = card.dataset.brand ?? '';
        const isPostpaid = card.dataset.postpaid === 'true';
        if (summaryPrice) summaryPrice.textContent = isPostpaid
            ? 'Dicek setelah nomor diisi'
            : `Rp ${Number(card.dataset.price ?? 0).toLocaleString('id-ID')}`;
        if (submit) submit.firstChild.textContent = isPostpaid ? 'Cek tagihan & lanjut ' : 'Lanjut ke pembayaran ';
        if (submit && !submit.hasAttribute('data-integration-disabled')) submit.disabled = false;
    };

    products.forEach((card) => {
        card.querySelector('input[type="radio"]')?.addEventListener('change', () => selectProduct(card));
        if (card.querySelector('input[type="radio"]')?.checked) selectProduct(card);
    });
}

document.addEventListener('click', (event) => {
    const clearButton = event.target.closest('[data-ai-clear]');
    if (clearButton) {
        const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
        clearButton.disabled = true;
        fetch('/tanya-ai/history', {
            method: 'DELETE',
            headers: {'Accept': 'application/json', 'X-CSRF-TOKEN': csrfToken},
        }).then((response) => {
            if (!response.ok) throw new Error('Riwayat gagal dihapus.');
            window.location.reload();
        }).catch(() => {
            clearButton.disabled = false;
            clearButton.textContent = 'Coba lagi';
        });
        return;
    }

    const suggest = event.target.closest('[data-ai-suggest]');
    if (!suggest) return;

    const form = suggest.closest('[data-ai-chat]');
    if (!form) return;

    const input = form.elements.namedItem('message');
    if (!(input instanceof HTMLInputElement)) return;

    input.value = suggest.textContent;
    form.requestSubmit();
});

document.addEventListener('submit', async (event) => {
    const form = event.target.closest('[data-ai-chat]');
    if (!form) return;

    event.preventDefault();
    const messages = form.querySelector('[data-ai-messages]');
    const emptyState = form.querySelector('[data-ai-empty]');
    const input = form.elements.namedItem('message');
    const consent = form.elements.namedItem('ai_consent');
    const button = form.querySelector('button[type="submit"]');
    if (!messages || !(input instanceof HTMLInputElement) || !button) return;

    const question = input.value.trim();
    if (!question) return;

    const history = aiChatHistories.get(form) ?? [];
    const previousHistory = history.slice(-12);
    emptyState?.remove();
    appendAiMessage(messages, 'user', question);
    const pendingAnswer = appendAiMessage(messages, 'assistant', aiPendingMessage);
    input.value = '';
    button.disabled = true;

    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content ?? '';
    const streamUrl = form.action.replace('/tanya-ai', '/tanya-ai/stream');

    try {
        let finalAnswer = '';
        let sources = [];
        let grounding = 'policy_guard';
        let interactionToken = '';
        let streamError = '';

        const trackingMatch = question.match(/^CEK PESANAN\s+(\S+)\s+(\S+)$/i);
        if (trackingMatch) {
            const trackResponse = await fetch('/cek-pesanan-cepat', {
                method: 'POST',
                headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken},
                body: JSON.stringify({order_number: trackingMatch[1], phone: trackingMatch[2]}),
            });
            const result = await trackResponse.json();
            if (!trackResponse.ok) throw new Error(result.message ?? 'Status pesanan tidak dapat diperiksa.');
            if (result.found) {
                const d = result.data;
                finalAnswer = `Status Pesanan ${d.order_number}\nLayanan: ${d.service}\nStatus: ${d.status}\nEstimasi: ${d.estimated_price}\nFinal: ${d.final_price}\nDibuat: ${d.created_at}\nDeadline: ${d.deadline_at}`;
            } else {
                finalAnswer = result.message;
            }
            restoreAiAssistantAvatar(pendingAnswer);
            pendingAnswer.textContent = finalAnswer;
            history.push({role: 'user', content: question}, {role: 'assistant', content: finalAnswer});
            aiChatHistories.set(form, history.slice(-12));
            return;
        }

        const streamRes = await fetch(streamUrl, {
            method: 'POST',
            headers: {'Accept': 'text/event-stream', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken},
            body: JSON.stringify({message: question, history: previousHistory, ai_consent: consent?.checked ? '1' : '0'}),
        });

        if (streamRes.ok && streamRes.body) {
            const reader = streamRes.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';

            while (true) {
                const {done, value} = await reader.read();
                if (done) break;
                buffer += decoder.decode(value, {stream: true});
                const lines = buffer.split('\n');
                buffer = lines.pop() || '';
                for (const line of lines) {
                    if (!line.startsWith('data: ')) continue;
                    const data = line.slice(6);
                    if (data === '[DONE]') break;
                    try {
                        const event = JSON.parse(data);
                        if (event.type === 'text_delta' && event.delta) {
                            if (!finalAnswer) restoreAiAssistantAvatar(pendingAnswer);
                            finalAnswer += event.delta;
                            pendingAnswer.textContent = finalAnswer;
                        } else if (event.type === 'sources' && Array.isArray(event.sources)) {
                            sources = event.sources;
                        } else if (event.type === 'grounding' && event.grounding) {
                            grounding = event.grounding;
                        } else if (event.type === 'interaction' && event.interaction_token) {
                            interactionToken = event.interaction_token;
                        } else if (event.type === 'error' && event.message) {
                            streamError = event.message;
                        }
                    } catch (_) {}
                }
            }

            if (streamError) throw new Error(streamError);
            if (!finalAnswer) throw new Error('Stream menghasilkan jawaban kosong.');
        } else {
            const jsonRes = await fetch(form.action, {
                method: 'POST',
                headers: {'Accept': 'application/json', 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken},
                body: JSON.stringify({message: question, history: previousHistory, ai_consent: consent?.checked ? '1' : '0'}),
            });
            const payload = await jsonRes.json();
            if (!jsonRes.ok) throw new Error(payload.message ?? 'Permintaan tidak dapat diproses.');
            finalAnswer = payload.data?.answer ?? payload.message ?? 'Jawaban belum tersedia.';
            sources = payload.data?.sources ?? [];
            grounding = payload.data?.grounding ?? 'policy_guard';
            interactionToken = payload.data?.interaction_token ?? '';
        }

        restoreAiAssistantAvatar(pendingAnswer);
        pendingAnswer.textContent = finalAnswer;

        const bubbleWrap = pendingAnswer.parentElement;
        if (bubbleWrap && sources.length > 0) {
            const srcDiv = document.createElement('div');
            srcDiv.className = 'mt-1.5 flex flex-wrap gap-1.5';
            sources.forEach((s) => {
                const tag = document.createElement('span');
                tag.className = 'rounded-full bg-emerald-50 px-2 py-0.5 text-[10px] font-medium text-emerald-700';
                tag.textContent = s.title;
                srcDiv.appendChild(tag);
            });
            bubbleWrap.appendChild(srcDiv);
        }

        if (bubbleWrap && grounding === 'general_unverified') {
            const groundingNote = document.createElement('span');
            groundingNote.className = 'mt-1.5 rounded-full bg-amber-50 px-2 py-0.5 text-[10px] font-medium text-amber-700';
            groundingNote.textContent = 'Jawaban umum ÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â· tidak diverifikasi real-time';
            bubbleWrap.appendChild(groundingNote);
        }

        if (bubbleWrap && interactionToken) bubbleWrap.dataset.aiInteraction = interactionToken;

        history.push(
            {role: 'user', content: question},
            {role: 'assistant', content: finalAnswer},
        );
        aiChatHistories.set(form, history.slice(-12));
    } catch (error) {
        restoreAiAssistantAvatar(pendingAnswer);
        pendingAnswer.textContent = error instanceof Error ? error.message : 'Layanan sedang tidak tersedia. Silakan hubungi operator.';
    } finally {
        button.disabled = false;
        input.focus();
        messages.scrollTop = messages.scrollHeight;
    }
});

const cookieConsentBanner = document.querySelector('[data-cookie-consent]');
const cookieConsentName = 'ydc_cookie_consent';

const orderForm = document.querySelector('[data-order-form]');
if (orderForm instanceof HTMLFormElement) {
    const serviceSelect = orderForm.querySelector('[data-order-service]');
    const typeSelect = orderForm.querySelector('[data-order-type]');
    const submitButton = orderForm.querySelector('[data-order-submit]');
    const syncServiceType = () => {
        if (!(serviceSelect instanceof HTMLSelectElement) || !(typeSelect instanceof HTMLSelectElement)) return;
        const selectedType = serviceSelect.selectedOptions[0]?.dataset.serviceType;
        if (selectedType) typeSelect.value = selectedType;
    };

    serviceSelect?.addEventListener('change', syncServiceType);
    if (!typeSelect?.value) syncServiceType();
    orderForm.addEventListener('submit', () => {
        if (!(submitButton instanceof HTMLButtonElement)) return;
        submitButton.disabled = true;
        submitButton.textContent = 'Mengirim pesananÃƒÆ’Ã†â€™Ãƒâ€ Ã¢â‚¬â„¢ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ÃƒÆ’Ã‚Â¢ÃƒÂ¢Ã¢â‚¬Å¡Ã‚Â¬Ãƒâ€¦Ã‚Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¬ÃƒÆ’Ã†â€™ÃƒÂ¢Ã¢â€šÂ¬Ã…Â¡ÃƒÆ’Ã¢â‚¬Å¡Ãƒâ€šÃ‚Â¦';
    });
}

const stickyOrderCta = document.querySelector('[data-sticky-order-cta]');
if (stickyOrderCta instanceof HTMLElement) {
    const finalCta = document.querySelector('.landing-cta');
    const updateStickyOrderCta = () => {
        const finalCtaVisible = finalCta instanceof HTMLElement && finalCta.getBoundingClientRect().top < window.innerHeight;
        const cookieVisible = cookieConsentBanner instanceof HTMLElement && !cookieConsentBanner.hidden;
        const visible = window.scrollY > 520 && !finalCtaVisible && !cookieVisible;
        stickyOrderCta.classList.toggle('translate-y-24', !visible);
        stickyOrderCta.classList.toggle('opacity-0', !visible);
        stickyOrderCta.classList.toggle('pointer-events-none', !visible);
        stickyOrderCta.setAttribute('aria-hidden', String(!visible));
    };

    window.addEventListener('scroll', updateStickyOrderCta, {passive: true});
    window.addEventListener('resize', updateStickyOrderCta, {passive: true});
    window.addEventListener('ydc:cookie-consent', updateStickyOrderCta);
    updateStickyOrderCta();
}

document.querySelectorAll('[data-conversion-cta]').forEach((link) => {
    link.addEventListener('click', () => {
        const source = link.dataset.conversionCta ?? 'unknown';
        window.dispatchEvent(new CustomEvent('ydc:conversion', {detail: {source}}));
        if (readCookieConsent() === 'all' && typeof window.gtag === 'function') {
            window.gtag('event', 'begin_checkout', {source});
        }
    });
});

const readCookieConsent = () => document.cookie
    .split('; ')
    .find((cookie) => cookie.startsWith(`${cookieConsentName}=`))
    ?.split('=')[1];

const saveCookieConsent = (value) => {
    const secure = window.location.protocol === 'https:' ? '; Secure' : '';
    document.cookie = `${cookieConsentName}=${value}; Max-Age=31536000; Path=/; SameSite=Lax${secure}`;
    if (cookieConsentBanner instanceof HTMLElement) cookieConsentBanner.hidden = true;
    window.dispatchEvent(new CustomEvent('ydc:cookie-consent', {detail: {value}}));
};

if (cookieConsentBanner instanceof HTMLElement) {
    const consent = readCookieConsent();
    cookieConsentBanner.hidden = consent === 'all' || consent === 'essential';

    cookieConsentBanner.querySelector('[data-cookie-accept]')?.addEventListener('click', () => saveCookieConsent('all'));
    cookieConsentBanner.querySelector('[data-cookie-essential]')?.addEventListener('click', () => saveCookieConsent('essential'));
}

document.querySelectorAll('[data-cookie-settings]').forEach((button) => {
    button.addEventListener('click', () => {
        if (!(cookieConsentBanner instanceof HTMLElement)) return;
        cookieConsentBanner.hidden = false;
        cookieConsentBanner.querySelector('button')?.focus({preventScroll: true});
    });
});

if ('serviceWorker' in navigator && window.isSecureContext) {
    if (document.body.hasAttribute('data-service-worker-enabled')) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/service-worker.js', {
                scope: '/',
                updateViaCache: 'none',
            }).catch(() => {});
        }, {once: true});
    } else {
        navigator.serviceWorker.getRegistrations()
            .then((registrations) => Promise.all(registrations.map((registration) => registration.unregister())))
            .catch(() => {});

        if ('caches' in window) {
            caches.keys()
                .then((keys) => Promise.all(keys
                    .filter((key) => key.startsWith('younz-static-'))
                    .map((key) => caches.delete(key))))
                .catch(() => {});
        }
    }
}






