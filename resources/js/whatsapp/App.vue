<template>
  <div class="flex h-full lg:gap-6 overflow-hidden min-h-0 bg-sky-50/50 dark:bg-zinc-800">
    <!-- Conversation List -->
    <div
      :class="[
        'flex-1 lg:w-[380px] lg:flex-none flex-col bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 overflow-hidden h-full',
        store.selectedConversationId ? 'hidden lg:flex' : 'flex',
      ]"
    >
      <ConversationList />
    </div>

    <!-- Chat Area -->
    <div
      :class="[
        'flex-1 lg:flex-1 flex-col bg-white dark:bg-zinc-900 rounded-xl border border-zinc-200 dark:border-zinc-800 overflow-hidden h-full min-h-0',
        store.selectedConversationId ? 'flex' : 'hidden lg:flex',
      ]"
    >
      <ChatWindow v-if="store.selectedConversationId" />
      <EmptyState v-else />
    </div>

    <!-- Clear Modal -->
    <ClearModal v-if="store.showClearModal" @close="store.showClearModal = false" @confirm="store.clearConversation" />
  </div>
</template>

<script setup>
import { onMounted, onUnmounted } from 'vue';
import { useInboxStore } from './stores/inbox';
import ConversationList from './components/ConversationList.vue';
import ChatWindow from './components/ChatWindow.vue';
import EmptyState from './components/EmptyState.vue';
import ClearModal from './components/ClearModal.vue';

const store = useInboxStore();

onMounted(() => {
  store.startPolling();
});

onUnmounted(() => {
  store.stopPolling();
});
</script>
