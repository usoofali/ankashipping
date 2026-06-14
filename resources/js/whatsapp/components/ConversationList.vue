<template>
  <div class="flex flex-col h-full">
    <div class="p-4 border-b border-zinc-200 dark:border-zinc-800 space-y-4">
      <div class="flex items-center justify-between">
        <h2 class="text-lg font-semibold text-zinc-900 dark:text-zinc-100">Conversations</h2>
        <div class="flex items-center gap-2">
          <span class="w-2 h-2 rounded-full bg-green-500 animate-pulse"></span>
          <span class="text-[10px] font-bold text-zinc-400 uppercase tracking-wider">Live</span>
        </div>
      </div>

      <div class="relative">
        <input
          v-model="store.searchQuery"
          type="text"
          placeholder="Search name or phone..."
          class="w-full px-3 py-1.5 text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
        />
        <svg v-if="!store.searchQuery" class="absolute right-3 top-2 w-4 h-4 text-zinc-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
        </svg>
        <button v-else @click="store.searchQuery = ''" class="absolute right-3 top-2 text-zinc-400 hover:text-zinc-600">
          <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
          </svg>
        </button>
      </div>

      <select
        v-model="store.filter"
        @change="store.fetchConversations"
        class="w-full px-3 py-1.5 text-sm rounded-lg border border-zinc-300 dark:border-zinc-700 bg-white dark:bg-zinc-800 text-zinc-900 dark:text-zinc-100 focus:ring-2 focus:ring-indigo-500 focus:border-indigo-500"
      >
        <option value="all">All Conversations</option>
        <option value="unassigned">Open (Unassigned)</option>
        <option value="escalated">Urgent (Escalated)</option>
        <option value="bot">Bot Handled</option>
      </select>
    </div>

    <div class="flex-1 overflow-y-auto">
      <div v-if="filteredConversations.length === 0" class="p-6 text-center text-sm text-zinc-500">
        No conversations found.
      </div>
      
      <div
        v-for="conv in filteredConversations"
        :key="conv.id"
        @click="store.selectConversation(conv.id)"
        :class="[
          'flex items-center gap-3 px-4 py-2.5 transition-colors cursor-pointer group relative border-b border-zinc-100 dark:border-zinc-800/60 last:border-b-0',
          store.selectedConversationId === conv.id
            ? 'bg-zinc-100 dark:bg-zinc-800/80'
            : 'bg-white dark:bg-zinc-900 hover:bg-zinc-50 dark:hover:bg-zinc-800/40'
        ]"
      >
        <div class="relative shrink-0">
          <div class="w-12 h-12 rounded-full bg-zinc-200 dark:bg-zinc-700 flex items-center justify-center text-base font-medium text-zinc-600 dark:text-zinc-300">
            {{ getInitials(conv.name) }}
          </div>
          <div class="absolute -bottom-0.5 -right-0.5 bg-white dark:bg-zinc-900 rounded-full p-[2px]">
            <div class="w-4 h-4 bg-[#25D366] rounded-full flex items-center justify-center">
              <svg class="w-2.5 h-2.5 text-white" fill="currentColor" viewBox="0 0 24 24"><path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51h-.57c-.198 0-.52.074-.792.372s-1.04 1.016-1.04 2.479 1.065 2.876 1.213 3.074c.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/></svg>
            </div>
          </div>
          
          <div v-if="conv.unread_count > 0" class="absolute -top-1 -right-1 flex h-5 min-w-[20px] items-center justify-center rounded-full bg-[#25D366] px-1.5 text-[11px] font-bold text-white shadow-sm ring-2 ring-white dark:ring-zinc-900">
            {{ conv.unread_count }}
          </div>
        </div>

        <div class="flex-1 min-w-0 py-1">
          <div class="flex justify-between items-baseline mb-0.5">
            <span class="font-medium text-[16px] text-zinc-900 dark:text-zinc-100 truncate">
              {{ conv.name }}
            </span>
            <span class="text-[12px] shrink-0 ml-2" :class="conv.unread_count > 0 ? 'text-[#25D366] font-medium' : 'text-zinc-500 dark:text-zinc-400'">
              {{ formatTime(conv.last_message_at) }}
            </span>
          </div>
          
          <div class="flex justify-between items-center gap-2 mt-0.5">
            <div class="text-[14px] text-zinc-500 dark:text-zinc-400 truncate flex-1">
              {{ conv.last_message_text || 'No messages yet' }}
            </div>

            <div class="flex flex-wrap gap-1.5 shrink-0">
              <span v-if="conv.category" class="px-1.5 py-0.5 rounded text-[10px] font-medium border border-zinc-200 dark:border-zinc-700 text-zinc-500 dark:text-zinc-400">
                #{{ conv.category.hashtag }}
              </span>
              <span v-if="!conv.agent_id" class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-rose-50 text-rose-600 dark:bg-rose-900/20 dark:text-rose-400">
                Unassigned
              </span>
              <span v-if="conv.status === 'escalated'" class="px-1.5 py-0.5 rounded text-[10px] font-medium bg-amber-50 text-amber-600 dark:bg-amber-900/20 dark:text-amber-400">
                Escalated
              </span>
            </div>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useInboxStore } from '../stores/inbox';

const store = useInboxStore();

const filteredConversations = computed(() => store.filteredConversations);

const getInitials = (name) => {
  if (!name) return '?';
  if (/^\+?[0-9]+$/.test(name)) return '?';
  return name.substring(0, 2).toUpperCase();
};

const formatTime = (isoString) => {
  if (!isoString) return '';
  const date = new Date(isoString);
  const now = new Date();
  
  if (date.toDateString() === now.toDateString()) {
    return date.toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
  }
  return date.toLocaleDateString([], { month: 'short', day: 'numeric' });
};
</script>
