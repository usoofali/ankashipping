<x-layouts::auth.card :title="__('Terms of service')">
    <div class="prose prose-sm prose-zinc max-w-none dark:prose-invert">
        <p class="text-sm text-stone-600 dark:text-stone-400">
            {{ __('This is a placeholder terms of service. Replace this content with your legal text before production.') }}
        </p>
        <flux:heading size="sm" level="3" class="mt-6">{{ __('1. Acceptance') }}</flux:heading>
        <flux:text class="text-sm">
            {{ __('By using this application, you agree to these terms.') }}
        </flux:text>
        <flux:heading size="sm" level="3" class="mt-6">{{ __('2. Accounts') }}</flux:heading>
        <flux:text class="text-sm">
            {{ __('You are responsible for maintaining the confidentiality of your account credentials.') }}
        </flux:text>
    </div>
    <div class="mt-8 text-center">
        <flux:link :href="route('register')" wire:navigate>{{ __('Back to registration') }}</flux:link>
    </div>
</x-layouts::auth.card>
