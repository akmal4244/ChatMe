@php
    $initialNotifications = collect([
        session('success') ? ['message' => session('success'), 'type' => 'success'] : null,
        session('error') ? ['message' => session('error'), 'type' => 'error'] : null,
        session('info') ? ['message' => session('info'), 'type' => 'info'] : null,
        isset($errors) && $errors->any()
            ? ['message' => 'Sila semak medan yang bertanda sebelum meneruskan.', 'type' => 'error']
            : null,
    ])->filter()->values()->all();
@endphp

<div id="toast-container" aria-live="polite" aria-atomic="false"></div>
<template id="toast-template">
    <div class="toast" data-toast>
        <i class="toast-icon ph" aria-hidden="true"></i>
        <span class="toast-message"></span>
        <button type="button" class="toast-close" aria-label="Tutup notifikasi">&times;</button>
    </div>
</template>
<script id="initial-notifications" type="application/json">{!! json_encode($initialNotifications, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
<script>
(() => {
    const container = document.getElementById('toast-container');
    const template = document.getElementById('toast-template');
    const durations = { success: 4000, info: 5000, error: 7000 };
    const icons = { success: 'ph-check-circle', info: 'ph-info', error: 'ph-x-circle' };

    window.showToast = (message, type = 'success') => {
        const text = String(message || '').trim();
        const normalizedType = ['success', 'error', 'info'].includes(type) ? type : 'info';
        if (!text || !container || !template) return null;

        const duplicate = [...container.querySelectorAll('[data-toast]')]
            .find((toast) => toast.dataset.message === text && toast.dataset.type === normalizedType);
        if (duplicate) return duplicate;

        const toast = template.content.firstElementChild.cloneNode(true);
        toast.classList.add(`toast-${normalizedType}`);
        toast.dataset.message = text;
        toast.dataset.type = normalizedType;
        toast.setAttribute('role', normalizedType === 'error' ? 'alert' : 'status');
        toast.querySelector('.toast-icon').classList.add(icons[normalizedType]);
        toast.querySelector('.toast-message').textContent = text;

        let timer;
        const remove = () => {
            window.clearTimeout(timer);
            toast.remove();
        };
        const resume = () => {
            window.clearTimeout(timer);
            timer = window.setTimeout(remove, durations[normalizedType]);
        };
        const pause = () => window.clearTimeout(timer);

        toast.addEventListener('mouseenter', pause);
        toast.addEventListener('mouseleave', resume);
        toast.addEventListener('focusin', pause);
        toast.addEventListener('focusout', (event) => {
            if (!toast.contains(event.relatedTarget)) resume();
        });
        toast.querySelector('.toast-close').addEventListener('click', remove);
        container.appendChild(toast);
        resume();

        return toast;
    };

    const initialData = document.getElementById('initial-notifications')?.textContent || '[]';
    JSON.parse(initialData).forEach(({ message, type }) => window.showToast(message, type));
})();
</script>
