<x-admin-layout>
    <x-slot name="title">Audit Logs</x-slot>
    <x-slot name="header">Audit logs</x-slot>

    <div class="space-y-5">
        <form method="GET" action="{{ route('admin.audit-logs.index') }}" class="kv-card p-4 flex flex-col sm:flex-row gap-3">
            <div class="relative flex-1">
                <i class="fas fa-search absolute left-3.5 top-1/2 -translate-y-1/2 text-ink-400 text-xs"></i>
                <input type="text" name="search" value="{{ request('search') }}" placeholder="Action or admin email…" class="kv-input !pl-9">
            </div>
            <select name="action" class="kv-input sm:w-56">
                <option value="">All actions</option>
                @foreach($actions as $action)
                    <option value="{{ $action }}" @selected(request('action') === $action)>{{ $action }}</option>
                @endforeach
            </select>
            <button type="submit" class="kv-btn-secondary shrink-0"><i class="fas fa-filter"></i> Filter</button>
        </form>

        <div class="kv-card overflow-hidden">
            @if($logs->isEmpty())
                <x-empty-state icon="fa-clipboard-list" title="No audit logs" message="Admin actions are recorded here automatically." />
            @else
                <ul class="divide-y divide-ink-100">
                    @foreach($logs as $log)
                        <li class="px-5 sm:px-6 py-4" x-data="{ open: false }">
                            <div class="flex items-start gap-4">
                                <div class="w-9 h-9 rounded-xl bg-ink-100 text-ink-500 grid place-items-center shrink-0">
                                    <i class="fas fa-shield-halved text-sm"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-center gap-2 flex-wrap">
                                        <p class="text-sm font-semibold text-ink-900 font-mono">{{ $log->action }}</p>
                                        @if($log->model_type)
                                            <span class="kv-badge bg-ink-100 text-ink-600">{{ class_basename($log->model_type) }} #{{ $log->model_id }}</span>
                                        @endif
                                    </div>
                                    <p class="mt-1 text-xs text-ink-500">
                                        {{ $log->user->email ?? 'system' }} · {{ $log->ip_address ?? '—' }} · {{ $log->created_at->format('M d, Y H:i:s') }}
                                    </p>
                                    @if($log->old_values || $log->new_values)
                                        <button @click="open = !open" class="mt-2 text-xs font-semibold text-brand-600 hover:text-brand-700">
                                            <i class="fas" :class="open ? 'fa-chevron-up' : 'fa-chevron-down'"></i> Changes
                                        </button>
                                        <div x-show="open" x-cloak class="mt-2 grid sm:grid-cols-2 gap-2">
                                            <pre class="text-[11px] bg-ink-900 text-red-300 rounded-xl p-3 overflow-x-auto font-mono">{{ json_encode($log->old_values, JSON_PRETTY_PRINT) }}</pre>
                                            <pre class="text-[11px] bg-ink-900 text-emerald-300 rounded-xl p-3 overflow-x-auto font-mono">{{ json_encode($log->new_values, JSON_PRETTY_PRINT) }}</pre>
                                        </div>
                                    @endif
                                </div>
                            </div>
                        </li>
                    @endforeach
                </ul>
            @endif
        </div>

        @if($logs->hasPages())
            <div>{{ $logs->links() }}</div>
        @endif
    </div>
</x-admin-layout>
