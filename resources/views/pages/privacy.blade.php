<x-public-layout>
    <x-slot name="title">Privacy Policy</x-slot>

    <div class="bg-ink-50 border-b border-ink-200/70">
        <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-12">
            <h1 class="text-3xl font-extrabold text-ink-900">Privacy Policy</h1>
            <p class="mt-2 text-sm text-ink-500">Last updated: {{ date('F j, Y') }}</p>
        </div>
    </div>

    <div class="max-w-3xl mx-auto px-4 sm:px-6 lg:px-8 py-10 space-y-8 text-sm leading-relaxed text-ink-600">
        <section>
            <h2 class="text-lg font-bold text-ink-900 mb-2">1. What we collect</h2>
            <p>We collect the information needed to operate the service: your name, email address, and password when you register; wallet and transaction records when you deposit or purchase; order, number, and SMS records for each activation; and support correspondence when you contact us.</p>
            <p class="mt-2">We also record limited technical data such as IP address and user agent for security, fraud prevention, referral integrity, and audit logging.</p>
        </section>

        <section>
            <h2 class="text-lg font-bold text-ink-900 mb-2">2. SMS content</h2>
            <p>SMS messages delivered to numbers you purchase are stored against your order so you can read and copy them from your dashboard. We do not sell message contents and we do not use them for advertising.</p>
        </section>

        <section>
            <h2 class="text-lg font-bold text-ink-900 mb-2">3. How we use information</h2>
            <ul class="list-disc pl-5 space-y-1.5">
                <li>Providing, operating, and securing the service</li>
                <li>Processing deposits, orders, refunds, and referral rewards</li>
                <li>Sending transactional notices (order updates, deposit confirmations, support replies)</li>
                <li>Preventing fraud, abuse, and duplicate-account manipulation of referrals</li>
                <li>Meeting legal and accounting obligations</li>
            </ul>
        </section>

        <section>
            <h2 class="text-lg font-bold text-ink-900 mb-2">4. Sharing</h2>
            <p>We share data only with the infrastructure providers needed to run the service: number providers (to provision numbers and deliver SMS), payment processors (to process deposits), and hosting/email providers. We do not sell your personal information.</p>
        </section>

        <section>
            <h2 class="text-lg font-bold text-ink-900 mb-2">5. Retention</h2>
            <p>We retain account, wallet, and transaction records for as long as your account is active and as required for accounting and legal purposes. You may request account deletion from your profile settings; we may retain records we are legally required to keep.</p>
        </section>

        <section>
            <h2 class="text-lg font-bold text-ink-900 mb-2">6. Your choices</h2>
            <p>You can review and update your profile information at any time, download your transaction history from your wallet, and request account deletion from the profile settings page.</p>
        </section>

        <section>
            <h2 class="text-lg font-bold text-ink-900 mb-2">7. Contact</h2>
            <p>Privacy questions can be sent through the <a href="{{ route('pages.contact') }}" class="text-brand-600 hover:text-brand-700 font-medium">contact page</a> or by opening a support ticket from your dashboard.</p>
        </section>
    </div>
</x-public-layout>
