@php
    $flashes = [];
    if (session('success')) $flashes[] = ['type' => 'success', 'text' => session('success')];
    if (session('error'))   $flashes[] = ['type' => 'error',   'text' => session('error')];
    if (session('warning')) $flashes[] = ['type' => 'warning', 'text' => session('warning')];
    if (session('status') && !in_array(session('status'), ['verification-link-sent']))
        $flashes[] = ['type' => 'info', 'text' => session('status')];
    $icons = ['success' => 'fa-check-circle text-emerald-500', 'error' => 'fa-exclamation-circle text-red-500',
              'warning' => 'fa-exclamation-triangle text-amber-500', 'info' => 'fa-info-circle text-sky-500'];
    $borders = ['success' => 'border-emerald-200', 'error' => 'border-red-200',
                'warning' => 'border-amber-200', 'info' => 'border-sky-200'];
@endphp

<div id="kv-toasts" class="fixed z-[70] inset-x-0 top-4 sm:left-auto sm:right-4 sm:w-96 px-4 sm:px-0 space-y-2 pointer-events-none" aria-live="polite">
    @foreach($flashes as $flash)
        <div x-data="{ show: true }" x-init="setTimeout(() => show = false, 6000)" x-show="show"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-2"
             x-transition:enter-end="opacity-100 translate-y-0" x-transition:leave="transition ease-in duration-150"
             x-transition:leave-end="opacity-0"
             class="pointer-events-auto flex items-start gap-3 rounded-xl border bg-white p-4 shadow-pop {{ $borders[$flash['type']] }}">
            <i class="fas mt-0.5 {{ $icons[$flash['type']] }}"></i>
            <p class="flex-1 text-sm text-ink-700">{{ $flash['text'] }}</p>
            <button @click="show = false" class="text-ink-400 hover:text-ink-600" aria-label="Dismiss">
                <i class="fas fa-times text-xs"></i>
            </button>
        </div>
    @endforeach
</div>

@once
<script>
    // Global toast helper: window.kvToast('Saved!', 'success')
    window.kvToast = function (text, type = 'success') {
        const styles = {
            success: ['border-emerald-200', 'fa-check-circle text-emerald-500'],
            error:   ['border-red-200', 'fa-exclamation-circle text-red-500'],
            warning: ['border-amber-200', 'fa-exclamation-triangle text-amber-500'],
            info:    ['border-sky-200', 'fa-info-circle text-sky-500'],
        };
        const [border, icon] = styles[type] || styles.info;
        const el = document.createElement('div');
        el.className = 'pointer-events-auto flex items-start gap-3 rounded-xl border bg-white p-4 shadow-pop ' + border
            + ' transition-all duration-200 opacity-0 translate-y-2';
        el.innerHTML = '<i class="fas mt-0.5 ' + icon + '"></i>'
            + '<p class="flex-1 text-sm text-ink-700"></p>'
            + '<button class="text-ink-400 hover:text-ink-600" aria-label="Dismiss"><i class="fas fa-times text-xs"></i></button>';
        el.querySelector('p').textContent = text;
        el.querySelector('button').addEventListener('click', () => el.remove());
        document.getElementById('kv-toasts').appendChild(el);
        requestAnimationFrame(() => el.classList.remove('opacity-0', 'translate-y-2'));
        setTimeout(() => { el.classList.add('opacity-0'); setTimeout(() => el.remove(), 200); }, 6000);
    };

    // Copy-to-clipboard helper with toast feedback
    window.kvCopy = function (text, label = 'Copied to clipboard') {
        const done = () => window.kvToast(label, 'success');
        if (navigator.clipboard && window.isSecureContext) {
            navigator.clipboard.writeText(text).then(done).catch(() => window.kvToast('Copy failed', 'error'));
        } else {
            const ta = document.createElement('textarea');
            ta.value = text; ta.style.position = 'fixed'; ta.style.opacity = '0';
            document.body.appendChild(ta); ta.select();
            try { document.execCommand('copy'); done(); }
            catch (e) { window.kvToast('Copy failed', 'error'); }
            ta.remove();
        }
    };
</script>
@endonce
