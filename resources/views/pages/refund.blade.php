<x-public-layout>
    <x-slot name="title">Refund Policy</x-slot>

    <div class="bg-ink-50 border-b border-ink-200/70">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <h1 class="text-3xl font-extrabold text-ink-900">Refund Policy</h1>
            <p class="mt-2 text-sm text-ink-500">Last updated: {{ date('F j, Y') }}</p>
        </div>
    </div>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8 text-sm leading-relaxed text-ink-600">
        <section>
            <h2 class="text-lg font-bold text-ink-900 mb-2">1. Automatic refunds</h2>
            <p>You receive a <strong class="text-ink-900">full, automatic wallet refund</strong> when:</p>
            <ul class="list-disc pl-5 mt-2 space-y-1.5">
                <li>Your order expires before any SMS is received on the assigned number.</li>
                <li>The provider fails to assign a number after you confirm purchase.</li>
                <li>You cancel an active order before any SMS is received.</li>
            </ul>
            <p class="mt-2">Automatic refunds are credited to your wallet immediately and appear in your transaction history.</p>
        </section>

        <section>
            <h2 class="text-lg font-bold text-ink-900 mb-2">2. Non-refundable orders</h2>
            <p>Orders where at least one SMS was successfully delivered to your number are considered fulfilled and are not refundable. Completing an order marks it as fulfilled.</p>
        </section>

        <section>
            <h2 class="text-lg font-bold text-ink-900 mb-2">3. Deposits</h2>
            <p>Wallet deposits are prepaid credits for use on KamVerify and are generally non-refundable to the original payment method. If a deposit was charged but never credited, or was charged in error, open a support ticket and we will investigate and correct it.</p>
        </section>

        <section>
            <h2 class="text-lg font-bold text-ink-900 mb-2">4. Disputes</h2>
            <p>If you believe a refund was missed or applied incorrectly, open a support ticket from your dashboard and reference the order ID. We review refund disputes manually.</p>
        </section>

        <section>
            <h2 class="text-lg font-bold text-ink-900 mb-2">5. Processing time</h2>
            <p>Wallet refunds are credited immediately. Corrections to original payment methods, where applicable, depend on the payment provider's processing times.</p>
        </section>
    </div>
</x-public-layout>
