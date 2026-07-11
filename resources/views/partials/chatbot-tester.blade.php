@push('modals')
<div class="chatbot-tester-backdrop" id="chatbot-tester-modal" hidden>
    <section class="chatbot-tester-dialog" role="dialog" aria-modal="true" aria-labelledby="chatbot-tester-title" aria-describedby="chatbot-tester-description">
        <header class="chatbot-tester-header">
            <img id="chatbot-tester-avatar" src="{{ asset('akmal3d.png') }}" alt="" width="44" height="44">
            <div class="chatbot-tester-identity">
                <h2 id="chatbot-tester-title">Uji chatbot</h2>
                <p id="chatbot-tester-bot-name">Pembantu chatbot</p>
            </div>
            <button type="button" class="chatbot-tester-icon-button" data-chatbot-test-close aria-label="Tutup mod ujian">
                <i class="ph ph-x" aria-hidden="true"></i>
            </button>
        </header>

        <p class="chatbot-tester-mode" id="chatbot-tester-description">
            <i class="ph ph-flask" aria-hidden="true"></i>
            Mod ujian — mesej tidak dikira dalam kuota
        </p>

        <div class="chatbot-tester-messages" id="chatbot-tester-messages" role="log" aria-live="polite" aria-relevant="additions" aria-busy="false"></div>

        <form class="chatbot-tester-form" id="chatbot-tester-form">
            <label class="sr-only" for="chatbot-tester-input">Mesej ujian</label>
            <input class="chatbot-tester-input" id="chatbot-tester-input" type="text" maxlength="1000" autocomplete="off" placeholder="Taip mesej ujian..." required>
            <button class="chatbot-tester-send" id="chatbot-tester-send" type="submit" aria-label="Hantar mesej ujian">
                <i class="ph ph-paper-plane-tilt" aria-hidden="true"></i>
            </button>
        </form>

        <footer class="chatbot-tester-footer">
            <button type="button" class="chatbot-tester-clear" id="chatbot-tester-clear">
                <i class="ph ph-broom" aria-hidden="true"></i>
                Kosongkan chat
            </button>
            <span>Hanya anda boleh menggunakan mod ini.</span>
        </footer>
    </section>
</div>
@endpush

@push('scripts')
<script nonce="{{ Vite::cspNonce() }}">
document.addEventListener('DOMContentLoaded', () => {
    const modal = document.getElementById('chatbot-tester-modal');
    const dialog = modal?.querySelector('.chatbot-tester-dialog');
    const title = document.getElementById('chatbot-tester-title');
    const botName = document.getElementById('chatbot-tester-bot-name');
    const avatar = document.getElementById('chatbot-tester-avatar');
    const messages = document.getElementById('chatbot-tester-messages');
    const form = document.getElementById('chatbot-tester-form');
    const input = document.getElementById('chatbot-tester-input');
    const sendButton = document.getElementById('chatbot-tester-send');
    const clearButton = document.getElementById('chatbot-tester-clear');
    const appShell = document.getElementById('app-shell');
    const sidebar = document.getElementById('sidebar');
    const skipLink = document.querySelector('.skip-link');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content || '';
    let endpoint = '';
    let welcomeMessage = '';
    let activeChatbotKey = '';
    let returnFocus = null;
    let requestBusy = false;

    if (!modal || !dialog || !messages || !form || !input || !sendButton || !clearButton) return;

    const readableTextColor = (hex) => {
        const match = /^#([0-9a-f]{6})$/i.exec(hex || '');
        if (!match) return '#ffffff';

        const value = Number.parseInt(match[1], 16);
        const channels = [(value >> 16) & 255, (value >> 8) & 255, value & 255].map((channel) => {
            const normalized = channel / 255;
            return normalized <= 0.04045 ? normalized / 12.92 : ((normalized + 0.055) / 1.055) ** 2.4;
        });
        const luminance = (0.2126 * channels[0]) + (0.7152 * channels[1]) + (0.0722 * channels[2]);

        return (1.05 / (luminance + 0.05)) >= ((luminance + 0.05) / 0.05) ? '#ffffff' : '#111827';
    };

    const addMessage = (text, role) => {
        const message = document.createElement('p');
        message.className = `chatbot-tester-message is-${role}`;
        message.textContent = text;
        messages.appendChild(message);
        messages.scrollTop = messages.scrollHeight;

        return message;
    };

    const resetMessages = () => {
        messages.replaceChildren();
        addMessage(welcomeMessage || 'Helo! Apakah yang boleh saya bantu?', 'bot');
    };

    const setBackgroundInert = (inert) => {
        if (appShell) appShell.inert = inert;
        if (skipLink) skipLink.inert = inert;
        if (sidebar) {
            sidebar.inert = inert || (window.matchMedia('(max-width: 1024px)').matches && !sidebar.classList.contains('mobile-open'));
        }
    };

    const closeTester = () => {
        if (modal.hidden) return;
        modal.hidden = true;
        document.body.classList.remove('modal-open');
        setBackgroundInert(false);
        returnFocus?.focus();
        returnFocus = null;
    };

    const openTester = (trigger) => {
        const nextEndpoint = trigger.dataset.testUrl || '';
        if (!nextEndpoint) return;

        returnFocus = trigger;
        endpoint = nextEndpoint;
        welcomeMessage = trigger.dataset.testWelcome || '';
        title.textContent = `Uji ${trigger.dataset.testName || 'chatbot'}`;
        botName.textContent = trigger.dataset.testBotName || trigger.dataset.testName || 'Pembantu chatbot';
        avatar.src = trigger.dataset.testAvatar || '{{ asset('akmal3d.png') }}';
        avatar.alt = `Avatar ${botName.textContent}`;

        const color = /^#[0-9a-f]{6}$/i.test(trigger.dataset.testColor || '') ? trigger.dataset.testColor : '#4F46E5';
        dialog.style.setProperty('--tester-color', color);
        dialog.style.setProperty('--tester-contrast', readableTextColor(color));

        if (activeChatbotKey !== nextEndpoint) {
            activeChatbotKey = nextEndpoint;
            resetMessages();
        }

        modal.hidden = false;
        document.body.classList.add('modal-open');
        setBackgroundInert(true);
        window.requestAnimationFrame(() => input.focus());
    };

    document.addEventListener('click', (event) => {
        const trigger = event.target.closest('[data-chatbot-test]');
        if (trigger) openTester(trigger);
    });

    modal.addEventListener('click', (event) => {
        if (event.target === modal) closeTester();
    });
    modal.querySelectorAll('[data-chatbot-test-close]').forEach((button) => button.addEventListener('click', closeTester));
    clearButton.addEventListener('click', () => {
        if (requestBusy) return;
        resetMessages();
        input.focus();
    });

    form.addEventListener('submit', async (event) => {
        event.preventDefault();
        const text = input.value.trim();
        if (!text || requestBusy || !endpoint) return;

        addMessage(text, 'user');
        input.value = '';
        requestBusy = true;
        input.disabled = true;
        sendButton.disabled = true;
        messages.setAttribute('aria-busy', 'true');
        const pending = addMessage('Sedang mencari jawapan…', 'pending');

        try {
            const response = await fetch(endpoint, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': csrfToken,
                },
                body: JSON.stringify({ message: text }),
            });
            const payload = await response.json().catch(() => ({}));
            if (!response.ok || typeof payload.response !== 'string' || !payload.response.trim()) {
                throw new Error('Respons ujian tidak tersedia.');
            }

            pending.remove();
            addMessage(payload.response, 'bot');
        } catch (error) {
            pending.remove();
            const errorMessage = 'Mesej ujian tidak dapat dihantar. Sila cuba semula.';
            addMessage(errorMessage, 'error');
            window.showToast?.(errorMessage, 'error');
        } finally {
            requestBusy = false;
            input.disabled = false;
            sendButton.disabled = false;
            messages.setAttribute('aria-busy', 'false');
            input.focus();
        }
    });

    document.addEventListener('keydown', (event) => {
        if (modal.hidden) return;
        if (event.key === 'Escape') {
            closeTester();
            return;
        }
        if (event.key !== 'Tab') return;

        const focusable = [...dialog.querySelectorAll('button:not([disabled]), input:not([disabled]), [tabindex]:not([tabindex="-1"])')];
        if (focusable.length === 0) return;
        const first = focusable[0];
        const last = focusable[focusable.length - 1];

        if (event.shiftKey && document.activeElement === first) {
            event.preventDefault();
            last.focus();
        } else if (!event.shiftKey && document.activeElement === last) {
            event.preventDefault();
            first.focus();
        }
    });
});
</script>
@endpush
