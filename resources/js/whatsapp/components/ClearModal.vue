<template>
  <div class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-zinc-900/50 backdrop-blur-sm transition-opacity">
    <div class="bg-white dark:bg-zinc-900 rounded-2xl shadow-xl w-full max-w-md overflow-hidden transform transition-all border border-zinc-200 dark:border-zinc-800">
      <div class="p-6 space-y-6">
        <div>
          <h3 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Clear Conversation?</h3>
          <p class="mt-2 text-sm text-zinc-500 dark:text-zinc-400">
            This will permanently delete all messages and reset the bot state for this contact. This action cannot be undone.
          </p>
        </div>

        <div class="flex gap-3 justify-end mt-6">
          <button 
            @click="$emit('close')"
            class="rounded-lg px-4 py-2 text-sm font-medium text-zinc-700 hover:bg-zinc-100 dark:text-zinc-300 dark:hover:bg-zinc-800 transition-colors"
          >
            Cancel
          </button>
          <button 
            @click="$emit('confirm')"
            class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white hover:bg-rose-700 shadow-sm transition-colors"
          >
            Clear Everything
          </button>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
defineEmits(['close', 'confirm']);
</script>
