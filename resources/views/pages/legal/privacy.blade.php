<x-layouts::auth.card :title="__('Privacy policy')">
    <div class="prose prose-sm prose-zinc max-w-none dark:prose-invert">
        <p class="text-sm text-stone-600 dark:text-stone-400">
            {{ __('This is a placeholder privacy policy. Replace this content with your legal text before production.') }}
        </p>
        <flux:heading size="sm" level="3" class="mt-6">{{ __('1. Data we collect') }}</flux:heading>
        <flux:text class="text-sm">
            {{ __('We collect information you provide when registering and using the service.') }}
        </flux:text>
        <flux:heading size="sm" level="3" class="mt-6">{{ __('2. How we use data') }}</flux:heading>
        <flux:text class="text-sm">
            {{ __('We use your data to operate the platform, communicate with you, and improve our services.') }}
        </flux:text>
    </div>
    <div class="mt-8 text-center">
        <flux:link :href="route('register')" wire:navigate>{{ __('Back to registration') }}</flux:link>
    </div>
</x-layouts::auth.card>
