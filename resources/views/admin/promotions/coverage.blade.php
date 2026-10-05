<x-admin-layout>
    <x-slot name="title">Promotion Margin Check</x-slot>
    <x-slot name="header">Margin check — {{ $promotion->name }}</x-slot>

    <div class="space-y-6">
        <div class="rounded-xl bg-ink-50 border border-ink-200/70 px-4 py-3 text-sm text-ink-600">
            <i class="fas fa-shield-halved text-brand-600 mr-1.5"></i>
            Effective promo price per routed service+country, computed from live provider costs. <strong>Clamped</strong> rows are combinations where the promo target fell below provider cost — those sell at cost (zero margin, never a loss) instead of the headline price.
        </div>

        <div class="kv-card overflow-hidden">
            @if(empty($rows))
                <div class="p-10 text-center text-sm text-ink-400">No sellable combinations found for the promoted services.</div>
            @else
                <div class="overflow-x-auto">
                    <table class="w-full">
                        <thead class="bg-ink-50/70 border-b border-ink-100">
                            <tr>
                                <th class="kv-th">Service</th>
                                <th class="kv-th">Country</th>
                                <th class="kv-th">Normal price</th>
                                <th class="kv-th">Promo price</th>
                                <th class="kv-th">Status</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-ink-100">
                            @foreach($rows as $row)
                                <tr class="{{ $row['clamped'] ? 'bg-amber-50/40' : '' }}">
                                    <td class="kv-td font-medium">{{ $row['service'] }}</td>
                                    <td class="kv-td">{{ $row['country'] }} <span class="text-ink-400 text-xs">({{ $row['code'] }})</span></td>
                                    <td class="kv-td">{{ xaf($row['normal']) }}</td>
                                    <td class="kv-td font-semibold {{ $row['clamped'] ? 'text-amber-700' : 'text-emerald-700' }}">{{ xaf($row['promo']) }}</td>
                                    <td class="kv-td">
                                        @if($row['clamped'])
                                            <span class="kv-badge bg-amber-100 text-amber-800"><i class="fas fa-shield-halved mr-1"></i>Floored at cost</span>
                                        @else
                                            <span class="kv-badge bg-emerald-100 text-emerald-700">Promo</span>
                                        @endif
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            @endif
        </div>

        <a href="{{ route('admin.promotions.index') }}" class="kv-btn-ghost text-xs"><i class="fas fa-arrow-left"></i> Back to promotions</a>
    </div>
</x-admin-layout>
