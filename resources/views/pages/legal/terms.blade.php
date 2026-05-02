<x-layouts::auth.card :title="__('Terms of Service')">
    <div class="prose prose-sm prose-zinc max-w-none dark:prose-invert">
        <flux:text class="mb-4">
            {{ __('Welcome to Anka Shipping and Logistics(FMC # 034480N). These Terms of Service govern your use of our platform and services. By accessing our platform, you agree to be bound by these terms.') }}
        </flux:text>

        <flux:heading size="sm" level="3" class="mt-6">{{ __('1. Acceptance of Terms') }}</flux:heading>
        <flux:text class="text-sm">
            {{ __('By registering an account or using our logistics services, you agree to comply with and be bound by these Terms of Service, our Privacy Policy, and all applicable international shipping laws.') }}
        </flux:text>

        <flux:heading size="sm" level="3" class="mt-6">{{ __('2. Account Responsibilities') }}</flux:heading>
        <flux:text class="text-sm">
            {{ __('You are responsible for maintaining the confidentiality of your account credentials and for all activities that occur under your account. You must provide accurate and complete information during registration and keep this information updated.') }}
        </flux:text>

        <flux:heading size="sm" level="3" class="mt-6">{{ __('3. Shipping & Logistics Terms') }}</flux:heading>
        <flux:text class="text-sm">
            {{ __('All shipping operations are conducted under the following specific conditions:') }}
        </flux:text>
        <ul class="list-disc pl-5 text-sm text-zinc-600 dark:text-zinc-400 mt-2 space-y-2">
            <li><strong>{{ __('Governing Law') }}:</strong> {{ __('All operations are conducted under the terms and conditions of COGSA (Carriage of Goods by Sea Act).') }}</li>
            <li><strong>{{ __('Contractual Agreement') }}:</strong> {{ __('The dock receipt and the specific agency’s terms and conditions of service constitute the full contract under which the goods are received.') }}</li>
            <li><strong>{{ __('Vehicle Restrictions') }}:</strong> 
                <ul class="list-circle pl-5 mt-1 space-y-1">
                    <li>{{ __('Carriers (specifically Grimaldi and Sallaum Lines) will not accept any vehicles with a "FLOOD" title or evidence of water damage.') }}</li>
                    <li>{{ __('No electric or hybrid vehicles are accepted.') }}</li>
                    <li>{{ __('All batteries must be disconnected and, in some cases, removed prior to delivery to the terminal.') }}</li>
                </ul>
            </li>
            <li><strong>{{ __('Documentation') }}:</strong> {{ __('Original titles are mandatory; if a lien exists, an original lien release must be attached. High heavy machinery without titles requires a notarized Bill of Sale.') }}</li>
        </ul>

        <flux:heading size="sm" level="3" class="mt-6">{{ __('4. Storage & Unclaimed Cargo') }}</flux:heading>
        <flux:text class="text-sm">
            {{ __('Vehicles exceeding the free time duration incur daily storage charges. Effective June 1, 2024, daily rates after 30 days are:') }}
        </flux:text>
        <ul class="list-disc pl-5 text-sm text-zinc-600 dark:text-zinc-400 mt-2 space-y-1">
            <li>{{ __('$8.00 for cars/vans') }}</li>
            <li>{{ __('$18.00 for trucks/static cargo') }}</li>
            <li>{{ __('$28.00 for high heavy cargo') }}</li>
        </ul>
        <flux:text class="text-sm mt-2">
            {{ __('The carrier reserves the right to dispose of, sell, or discard any cargo not accepted or claimed by the consignee within the allowed timeframe.') }}
        </flux:text>

        <flux:heading size="sm" level="3" class="mt-6">{{ __('5. Payments & Wallet') }}</flux:heading>
        <flux:text class="text-sm">
            {{ __('Fees for logistics services must be settled through your platform wallet or via approved payment methods. Funds in the wallet are intended for service settlement and may be subject to specific refund policies depending on the stage of the shipping process.') }}
        </flux:text>

        <flux:heading size="sm" level="3" class="mt-6">{{ __('6. Communications') }}</flux:heading>
        <flux:text class="text-sm">
            {{ __('By using the platform, you consent to receive transactional communications via WhatsApp and Email regarding your bookings, shipment tracking, and invoices.') }}
        </flux:text>

        <flux:heading size="sm" level="3" class="mt-6">{{ __('7. Limitation of Liability') }}</flux:heading>
        <flux:text class="text-sm">
            {{ __('Anka Shipping and Logistics acts as a logistics coordinator. We are not liable for delays caused by shipping lines, port authorities, customs, or force majeure events. Our liability is limited as defined by COGSA and the specific carrier terms.') }}
        </flux:text>

        <flux:heading size="sm" level="3" class="mt-6">{{ __('8. Termination') }}</flux:heading>
        <flux:text class="text-sm">
            {{ __('We reserve the right to suspend or terminate your access to the platform for any breach of these terms or for illegal conduct.') }}
        </flux:text>
    </div>

    <div class="mt-12 pt-6 border-t border-zinc-200 dark:border-zinc-800 text-center">
        <flux:link :href="route('register')" wire:navigate icon="arrow-left" variant="subtle">{{ __('Back to registration') }}</flux:link>
    </div>
</x-layouts::auth.card>

