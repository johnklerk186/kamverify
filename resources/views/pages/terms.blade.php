<x-public-layout>
    <x-slot name="title">Terms of Service</x-slot>

    <div class="bg-ink-50 border-b border-ink-200/70">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <h1 class="text-3xl font-extrabold text-ink-900">Terms of Service</h1>
            <p class="mt-2 text-sm text-ink-500">Last updated: {{ date('F j, Y') }}</p>
        </div>
    </div>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8 text-sm leading-relaxed text-ink-600">
        <section>
            <h2 class="text-lg font-bold text-ink-900 mb-2">1. Service description</h2>
            <p>KamVerify sells access to temporary virtual phone numbers that can receive SMS messages, including one-time verification codes. Numbers are provisioned per order and remain active for a limited activation window shown at purchase time.</p>
        </section>

        <section>
            <h2 class="text-lg font-bold text-ink-900 mb-2">2. Acceptable use</h2>
            <p>KamVerify numbers may be used for legitimate verification and privacy purposes only. You may not use the service to bypass platform security controls, evade identity verification requirements, create fraudulent or bulk fake accounts, harass or defraud others, or violate any applicable law or the terms of the platform receiving the SMS.</p>
            <p class="mt-2">We may suspend or terminate accounts that violate these terms, and may decline service where usage patterns indicate abuse.</p>
        </section>

        <section>
            <h2 class="text-lg font-bold text-ink-900 mb-2">3. Accounts and wallet</h2>
            <p>You are responsible for maintaining the confidentiality of your account credentials. Wallet balances are prepaid credits usable only on KamVerify and are not a deposit account, stored-value instrument, or bank product. Balances are denominated in XAF.</p>
        </section>

        <section>
            <h2 class="text-lg font-bold text-ink-900 mb-2">4. Orders, pricing, and refunds</h2>
            <p>The price displayed at checkout is the price charged to your wallet upon successful number assignment. Numbers are provisioned through third-party providers; availability is not guaranteed until a number is assigned to your order.</p>
            <p class="mt-2">Refunds are governed by our <a href="{{ route('pages.refund') }}" class="text-brand-600 hover:text-brand-700 font-medium">Refund Policy</a>. In short: orders that expire or fail before an SMS is received are refunded automatically; you may cancel an active order before an SMS is received for a full refund.</p>
        </section>

        <section>
            <h2 class="text-lg font-bold text-ink-900 mb-2">5. Availability and third-party providers</h2>
            <p>Numbers are provisioned through third-party providers. We do not guarantee that a given service or platform will accept a given number, that a specific sender will deliver an SMS, or that any number will remain reachable after the activation window ends.</p>
        </section>

        <section>
            <h2 class="text-lg font-bold text-ink-900 mb-2">6. Limitation of liability</h2>
            <p>To the maximum extent permitted by law, KamVerify is provided "as is" without warranties of any kind. Our aggregate liability for any claim relating to an order is limited to the amount you paid for that order.</p>
        </section>

        <section>
            <h2 class="text-lg font-bold text-ink-900 mb-2">7. Changes</h2>
            <p>We may update these terms from time to time. Continued use of the service after changes take effect constitutes acceptance.</p>
        </section>
    </div>
</x-public-layout>
