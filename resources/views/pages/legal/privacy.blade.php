<x-layouts::auth.card :title="__('Privacy Policy')">
    <div class="prose prose-sm prose-zinc max-w-none dark:prose-invert">
        <flux:text class="mb-4">
            {{ __('At Anka Shipping and Logistics(FMC # 034480N), we are committed to protecting your privacy and ensuring the security of your personal and business data. This Privacy Policy explains how we collect, use, and safeguard your information when you use our logistics management platform.') }}
        </flux:text>

        <flux:heading size="sm" level="3" class="mt-6">{{ __('1. Information We Collect') }}</flux:heading>
        <flux:text class="text-sm">
            {{ __('We collect several types of information from and about users of our platform, including:') }}
        </flux:text>
        <ul class="list-disc pl-5 text-sm text-zinc-600 dark:text-zinc-400 mt-2 space-y-1">
            <li><strong>{{ __('Account Information') }}:</strong> {{ __('Name, email address, phone number, and company details.') }}</li>
            <li><strong>{{ __('Logistics & Shipping Data') }}:</strong> {{ __('Vehicle details (VIN, make, model), shipment documentation, titles, consignee information, and vehicle photos.') }}</li>
            <li><strong>{{ __('Financial Data') }}:</strong> {{ __('Wallet transaction history and payment method information used for settlement of logistics fees.') }}</li>
            <li><strong>{{ __('Communication Data') }}:</strong> {{ __('Logs of communications sent via WhatsApp, email, or in-app notifications.') }}</li>
        </ul>

        <flux:heading size="sm" level="3" class="mt-6">{{ __('2. How We Use Your Information') }}</flux:heading>
        <flux:text class="text-sm">
            {{ __('We use the information we collect to:') }}
        </flux:text>
        <ul class="list-disc pl-5 text-sm text-zinc-600 dark:text-zinc-400 mt-2 space-y-1">
            <li>{{ __('Provide and manage our logistics and shipping services.') }}</li>
            <li>{{ __('Facilitate communication between shippers, carriers, and warehouses.') }}</li>
            <li>{{ __('Process payments and manage your digital wallet.') }}</li>
            <li>{{ __('Send transactional notifications and updates regarding your shipments.') }}</li>
            <li>{{ __('Ensure compliance with international shipping regulations and customs requirements.') }}</li>
        </ul>

        <flux:heading size="sm" level="3" class="mt-6">{{ __('3. WhatsApp & Meta Integration') }}</flux:heading>
        <flux:text class="text-sm">
            {{ __('Our platform integrates with Meta services to provide automated notifications via WhatsApp. By providing your phone number, you agree to receive transactional messages including but not limited to:') }}
        </flux:text>
        <ul class="list-disc pl-5 text-sm text-zinc-600 dark:text-zinc-400 mt-2 space-y-1">
            <li>{{ __('Booking and pre-alert confirmations.') }}</li>
            <li>{{ __('Real-time shipment tracking updates.') }}</li>
            <li>{{ __('Invoice delivery and payment reminders.') }}</li>
            <li>{{ __('Operational alerts regarding your vehicles and documentation.') }}</li>
        </ul>

        <flux:heading size="sm" level="3" class="mt-6">{{ __('4. Data Sharing & Disclosure') }}</flux:heading>
        <flux:text class="text-sm">
            {{ __('We may share your information with third parties only in the following circumstances:') }}
        </flux:text>
        <ul class="list-disc pl-5 text-sm text-zinc-600 dark:text-zinc-400 mt-2 space-y-1">
            <li><strong>{{ __('Service Providers') }}:</strong> {{ __('With carriers, shipping lines, warehouses, and customs brokers necessary to fulfill your shipping requests.') }}</li>
            <li><strong>{{ __('Legal Compliance') }}:</strong> {{ __('When required by law, such as to comply with a subpoena or similar legal process.') }}</li>
            <li><strong>{{ __('Business Transfers') }}:</strong> {{ __('In connection with a merger, sale of assets, or reorganization.') }}</li>
        </ul>

        <flux:heading size="sm" level="3" class="mt-6">{{ __('5. Data Security') }}</flux:heading>
        <flux:text class="text-sm">
            {{ __('We implement industry-standard security measures to protect your data from unauthorized access, alteration, or destruction. However, no method of transmission over the Internet is 100% secure.') }}
        </flux:text>

        <flux:heading size="sm" level="3" class="mt-6">{{ __('6. Your Rights') }}</flux:heading>
        <flux:text class="text-sm">
            {{ __('You have the right to access, correct, or request the deletion of your personal information. You may update your account details directly through the platform or by contacting our support team.') }}
        </flux:text>

        <flux:heading size="sm" level="3" class="mt-6">{{ __('7. Contact Us') }}</flux:heading>
        <flux:text class="text-sm">
            {{ __('If you have any questions about this Privacy Policy, please contact us at support@ankshipping.com.') }}
        </flux:text>
    </div>

    <div class="mt-12 pt-6 border-t border-zinc-200 dark:border-zinc-800 text-center">
        <flux:link :href="route('register')" wire:navigate icon="arrow-left" variant="subtle">{{ __('Back to registration') }}</flux:link>
    </div>
</x-layouts::auth.card>

