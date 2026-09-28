<nav x-data="{ open: false }" class="bg-white border-b border-gray-200 shadow-sm">
    <!-- Primary Navigation Menu -->
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
        <div class="flex justify-between h-16">
            <div class="flex">
                <!-- Logo -->
                <div class="shrink-0 flex items-center">
                    <a href="{{ route('dashboard') }}" class="flex items-center">
                        <div class="w-8 h-8 bg-gradient-to-r from-purple-600 to-blue-600 rounded-lg flex items-center justify-center">
                            <i class="fas fa-shield-alt text-white"></i>
                        </div>
                        <span class="ml-2 text-xl font-bold text-gray-900">KamVerify</span>
                    </a>
                </div>

                <!-- Navigation Links -->
                <div class="hidden space-x-1 sm:-my-px sm:ms-10 sm:flex">
                    <x-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                        <i class="fas fa-home mr-2"></i> Dashboard
                    </x-nav-link>
                    <x-nav-link :href="route('orders.create')" :active="request()->routeIs('orders.*')">
                        <i class="fas fa-phone-alt mr-2"></i> Buy Number
                    </x-nav-link>
                    <x-nav-link :href="route('orders.index')" :active="request()->routeIs('orders.index')">
                        <i class="fas fa-list mr-2"></i> Orders
                    </x-nav-link>
                    <x-nav-link :href="route('wallet.index')" :active="request()->routeIs('wallet.*')">
                        <i class="fas fa-wallet mr-2"></i> Wallet
                    </x-nav-link>
                    <x-nav-link :href="route('referral.index')" :active="request()->routeIs('referral.*')">
                        <i class="fas fa-users mr-2"></i> Referrals
                    </x-nav-link>
                    <x-nav-link :href="route('support.index')" :active="request()->routeIs('support.*')">
                        <i class="fas fa-headset mr-2"></i> Support
                    </x-nav-link>
                </div>
            </div>

            <!-- Settings Dropdown -->
            <div class="hidden sm:flex sm:items-center sm:ms-6">
                <div class="flex items-center mr-4">
                    <span class="text-sm text-gray-600">Balance:</span>
                    <span class="ml-1 font-semibold text-green-600">{{ xaf(Auth::user()->wallet->balance ?? 0) }}</span>
                </div>
                <x-dropdown align="right" width="48">
                    <x-slot name="trigger">
                        <button class="inline-flex items-center px-3 py-2 border border-transparent text-sm leading-4 font-medium rounded-md text-gray-500 bg-white hover:text-gray-700 focus:outline-none transition ease-in-out duration-150">
                            <div class="w-8 h-8 bg-purple-100 rounded-full flex items-center justify-center">
                                <span class="text-purple-600 font-semibold">{{ substr(Auth::user()->name, 0, 1) }}</span>
                            </div>

                            <div class="ms-2">
                                <svg class="fill-current h-4 w-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
                                    <path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd" />
                                </svg>
                            </div>
                        </button>
                    </x-slot>

                    <x-slot name="content">
                        <x-dropdown-link :href="route('profile.edit')">
                            <i class="fas fa-user mr-2"></i> Profile
                        </x-dropdown-link>
                        <x-dropdown-link :href="route('wallet.deposit')">
                            <i class="fas fa-plus-circle mr-2"></i> Deposit
                        </x-dropdown-link>

                        <!-- Authentication -->
                        <form method="POST" action="{{ route('logout') }}">
                            @csrf

                            <x-dropdown-link :href="route('logout')"
                                    onclick="event.preventDefault();
                                                this.closest('form').submit();">
                                <i class="fas fa-sign-out-alt mr-2"></i> Log Out
                            </x-dropdown-link>
                        </form>
                    </x-slot>
                </x-dropdown>
            </div>

            <!-- Hamburger -->
            <div class="-me-2 flex items-center sm:hidden">
                <button @click="open = ! open" class="inline-flex items-center justify-center p-2 rounded-md text-gray-400 hover:text-gray-500 hover:bg-gray-100 focus:outline-none focus:bg-gray-100 focus:text-gray-500 transition duration-150 ease-in-out">
                    <svg class="h-6 w-6" stroke="currentColor" fill="none" viewBox="0 0 24 24">
                        <path :class="{'hidden': open, 'inline-flex': ! open }" class="inline-flex" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16" />
                        <path :class="{'hidden': ! open, 'inline-flex': open }" class="hidden" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </button>
            </div>
        </div>
    </div>

    <!-- Responsive Navigation Menu -->
    <div :class="{'block': open, 'hidden': ! open}" class="hidden sm:hidden">
        <div class="pt-2 pb-3 space-y-1">
            <x-responsive-nav-link :href="route('dashboard')" :active="request()->routeIs('dashboard')">
                <i class="fas fa-home mr-2"></i> Dashboard
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('orders.create')" :active="request()->routeIs('orders.*')">
                <i class="fas fa-phone-alt mr-2"></i> Buy Number
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('orders.index')" :active="request()->routeIs('orders.index')">
                <i class="fas fa-list mr-2"></i> Orders
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('wallet.index')" :active="request()->routeIs('wallet.*')">
                <i class="fas fa-wallet mr-2"></i> Wallet
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('referral.index')" :active="request()->routeIs('referral.*')">
                <i class="fas fa-users mr-2"></i> Referrals
            </x-responsive-nav-link>
            <x-responsive-nav-link :href="route('support.index')" :active="request()->routeIs('support.*')">
                <i class="fas fa-headset mr-2"></i> Support
            </x-responsive-nav-link>
        </div>

        <!-- Responsive Settings Options -->
        <div class="pt-4 pb-1 border-t border-gray-200">
            <div class="px-4">
                <div class="font-medium text-base text-gray-800">{{ Auth::user()->name }}</div>
                <div class="font-medium text-sm text-gray-500">{{ Auth::user()->email }}</div>
                <div class="mt-2 text-sm">
                    <span class="text-gray-600">Balance:</span>
                    <span class="font-semibold text-green-600">{{ xaf(Auth::user()->wallet->balance ?? 0) }}</span>
                </div>
            </div>

            <div class="mt-3 space-y-1">
                <x-responsive-nav-link :href="route('profile.edit')">
                    <i class="fas fa-user mr-2"></i> Profile
                </x-responsive-nav-link>
                <x-responsive-nav-link :href="route('wallet.deposit')">
                    <i class="fas fa-plus-circle mr-2"></i> Deposit
                </x-responsive-nav-link>

                <!-- Authentication -->
                <form method="POST" action="{{ route('logout') }}">
                    @csrf

                    <x-responsive-nav-link :href="route('logout')"
                            onclick="event.preventDefault();
                                        this.closest('form').submit();">
                        <i class="fas fa-sign-out-alt mr-2"></i> Log Out
                    </x-responsive-nav-link>
                </form>
            </div>
        </div>
    </div>
</nav>
