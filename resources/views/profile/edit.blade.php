<x-app-layout>
    <x-slot name="title">Profile</x-slot>

    <div class="max-w-3xl mx-auto space-y-6">
        <div>
            <h1 class="text-2xl font-extrabold text-ink-900 tracking-tight">Profile settings</h1>
            <p class="mt-1 text-sm text-ink-500">Manage your account details and security.</p>
        </div>

        {{-- Account card --}}
        <div class="kv-card p-6 flex items-center gap-4">
            <div class="w-14 h-14 rounded-2xl bg-brand-600 text-white grid place-items-center text-xl font-extrabold">
                {{ strtoupper(substr($user->name, 0, 1)) }}
            </div>
            <div class="min-w-0 flex-1">
                <p class="font-bold text-ink-900 truncate">{{ $user->name }}</p>
                <p class="text-sm text-ink-500 truncate">{{ $user->email }}</p>
                <p class="mt-0.5 text-xs text-ink-400">Member since {{ $user->created_at->format('F Y') }}</p>
            </div>
            <div class="hidden sm:flex flex-col items-end gap-1">
                @if($user->hasVerifiedEmail())
                    <span class="kv-badge bg-emerald-100 text-emerald-700"><i class="fas fa-circle-check"></i> Verified</span>
                @else
                    <span class="kv-badge bg-amber-100 text-amber-700"><i class="fas fa-clock"></i> Unverified</span>
                @endif
            </div>
        </div>

        @include('profile.partials.update-profile-information-form')
        @include('profile.partials.update-password-form')
        @include('profile.partials.delete-user-form')
    </div>
</x-app-layout>
