<template>
  <div class="flex flex-col h-full bg-white dark:bg-zinc-900">
    <!-- Chat Header -->
    <div class="shrink-0 p-4 border-b border-zinc-200 dark:border-zinc-800 flex justify-between items-center bg-white dark:bg-zinc-900">
      <div class="flex items-center gap-3">
        <button class="lg:hidden p-2 -ml-2 text-zinc-500 hover:text-zinc-700 dark:hover:text-zinc-300" @click="store.deselectConversation">
          <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
          </svg>
        </button>

        <div class="w-10 h-10 shrink-0 rounded-full bg-indigo-100 dark:bg-indigo-900/30 flex items-center justify-center text-indigo-600 dark:text-indigo-400 font-bold text-lg">
          {{ store.currentConversation?.name?.charAt(0)?.toUpperCase() || '?' }}
        </div>
        <div>
          <div class="flex items-center gap-2">
            <h3 class="text-base font-semibold text-zinc-900 dark:text-zinc-100">{{ store.currentConversation?.name }}</h3>
          </div>
          <p class="text-xs text-zinc-500 dark:text-zinc-400">{{ store.currentConversation?.phone_number }}</p>
        </div>
      </div>
      
      <div class="flex items-center gap-2">
        <button 
          @click="store.showClearModal = true"
          class="inline-flex items-center justify-center rounded-lg px-3 py-1.5 text-sm font-medium transition-colors text-rose-600 hover:bg-rose-50 dark:text-rose-400 dark:hover:bg-rose-900/20"
        >
          <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" /></svg>
          <span class="hidden lg:inline">Clear</span>
        </button>

        <button 
          v-if="!store.currentConversation?.agent_id"
          @click="store.claimConversation"
          class="inline-flex items-center justify-center rounded-lg bg-indigo-600 px-3 py-1.5 text-sm font-medium text-white shadow-sm transition-colors hover:bg-indigo-700"
        >
          <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 11.5V14m0-2.5v-6a1.5 1.5 0 113 0m-3 6a1.5 1.5 0 00-3 0v2a7.5 7.5 0 0015 0v-5a1.5 1.5 0 00-3 0m-6-3V11m0-5.5v-1a1.5 1.5 0 013 0v1m0 0V11" /></svg>
          <span class="hidden lg:inline">Claim</span>
        </button>
        <button 
          v-else
          @click="store.resolveConversation"
          class="inline-flex items-center justify-center rounded-lg bg-zinc-800 px-3 py-1.5 text-sm font-medium text-white shadow-sm transition-colors hover:bg-zinc-700 dark:bg-zinc-100 dark:text-zinc-900 dark:hover:bg-zinc-200"
        >
          <svg class="w-4 h-4 mr-1.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" /></svg>
          <span class="hidden lg:inline">Done</span>
        </button>
      </div>
    </div>

    <!-- Messages Area -->
    <div 
      class="flex-1 overflow-y-auto p-4 md:p-6 space-y-4 whatsapp-bg min-h-0 relative"
      ref="messagesContainer"
      @scroll="handleScroll"
    >
      <div v-if="store.loading" class="flex justify-center items-center h-full">
        <svg class="animate-spin h-8 w-8 text-indigo-500" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
          <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
          <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
        </svg>
      </div>
      
      <template v-else>
        <template v-for="msg in processedMessages" :key="msg.id">
          <!-- Date Separator -->
          <div v-if="msg.showDate" class="flex justify-center my-4">
            <span class="px-3 py-1 bg-[#f1f2f6] dark:bg-[#182229] text-[#54656f] dark:text-[#8696a0] text-xs font-semibold rounded-lg shadow-sm border border-black/5 dark:border-white/5 tracking-wide">
              {{ msg.showDate }}
            </span>
          </div>

          <ChatBubble 
            :msg="msg" 
            :contact-name="store.currentConversation?.name"
            :is-sequential="msg.isSequential"
          />
        </template>
      </template>

      <!-- Scroll to bottom FAB -->
      <button 
        v-show="showScrollButton"
        @click="scrollToBottom(true)"
        class="absolute bottom-4 right-4 p-2 bg-white dark:bg-zinc-800 rounded-full shadow-lg border border-zinc-200 dark:border-zinc-700 text-zinc-600 dark:text-zinc-300 hover:text-indigo-600 transition-colors z-10"
      >
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 14l-7 7m0 0l-7-7m7 7V3" />
        </svg>
      </button>
    </div>

    <!-- Window Status Warning -->
    <div v-if="!store.isWindowOpen" class="px-4 py-2 bg-amber-50 dark:bg-amber-900/30 border-t border-amber-200 dark:border-amber-800/50 text-amber-800 dark:text-amber-400 text-[11px] flex items-center gap-2 shrink-0">
      <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" /></svg>
      <span>24-hour window is closed. Free-form messages will not be delivered.</span>
    </div>

    <!-- Input Area -->
    <div class="shrink-0 p-3 bg-[#f0f2f5] dark:bg-[#202c33] flex items-end gap-2 border-t border-zinc-200 dark:border-zinc-800/50">
      <!-- Attachment Icon (Mock) -->
      <button type="button" class="shrink-0 p-2 text-[#54656f] dark:text-[#8696a0] hover:text-[#111b21] dark:hover:text-[#d1d7db] transition-colors h-[42px] flex items-center justify-center">
        <svg class="w-[26px] h-[26px]" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13" /></svg>
      </button>

      <form @submit.prevent="sendMessage" class="flex-1 flex items-end gap-2 relative">
        <div class="flex-1 relative bg-white dark:bg-[#2a3942] rounded-xl overflow-hidden border border-transparent focus-within:border-[#8696a0]/30 shadow-sm transition-colors">
          <textarea
            v-model="store.messageText"
            rows="1"
            @keydown.enter.prevent="handleEnter"
            @input="adjustTextareaHeight"
            ref="messageInput"
            placeholder="Type a message"
            class="w-full pl-4 pr-10 py-2.5 text-[15px] bg-transparent text-[#111b21] dark:text-[#d1d7db] placeholder-[#8696a0] focus:ring-0 focus:outline-none border-none resize-none max-h-32 overflow-y-auto block"
            style="min-height: 42px;"
          ></textarea>
          
          <button type="button" @click="insertEmoji('👍')" class="absolute right-2 bottom-1 p-2 text-[#8696a0] hover:text-[#54656f] dark:hover:text-[#d1d7db] transition-colors">
            <svg class="w-[24px] h-[24px]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14.828 14.828a4 4 0 01-5.656 0M9 10h.01M15 10h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
          </button>
        </div>
        
        <button 
          type="submit" 
          :disabled="store.sending || !store.messageText.trim()"
          class="shrink-0 flex items-center justify-center rounded-full bg-[#00a884] w-[42px] h-[42px] text-white shadow-sm transition-transform active:scale-95 disabled:opacity-50 disabled:cursor-not-allowed hover:bg-[#00a884]/90"
        >
          <svg v-if="store.sending" class="animate-spin w-5 h-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
          <svg v-else class="w-[20px] h-[20px] ml-1" fill="currentColor" viewBox="0 0 24 24">
            <path d="M2.01 21L23 12 2.01 3 2 10l15 2-15 2z"/>
          </svg>
        </button>
      </form>
    </div>
  </div>
</template>

<script setup>
import { ref, computed, watch, nextTick } from 'vue';
import { useInboxStore } from '../stores/inbox';
import ChatBubble from './ChatBubble.vue';

const store = useInboxStore();
const messagesContainer = ref(null);
const messageInput = ref(null);
const showScrollButton = ref(false);

const processedMessages = computed(() => {
  if (!store.messages) return [];
  
  return store.messages.map((msg, index) => {
    const prevMsg = store.messages[index - 1];
    
    // Check if we need to show a date separator
    let showDate = false;
    const msgDate = new Date(msg.created_at);
    
    if (!prevMsg) {
      showDate = formatDateSeparator(msg.created_at);
    } else {
      const prevDate = new Date(prevMsg.created_at);
      if (msgDate.toDateString() !== prevDate.toDateString()) {
        showDate = formatDateSeparator(msg.created_at);
      }
    }
    
    // Check if sequential (same sender and within 5 minutes)
    let isSequential = false;
    if (prevMsg && prevMsg.sender_type === msg.sender_type && prevMsg.is_internal === msg.is_internal) {
      const diffMs = msgDate.getTime() - new Date(prevMsg.created_at).getTime();
      const diffMins = diffMs / (1000 * 60);
      if (diffMins < 5 && !showDate) {
        isSequential = true;
      }
    }
    
    return {
      ...msg,
      showDate,
      isSequential
    };
  });
});

const formatDateSeparator = (isoString) => {
  const date = new Date(isoString);
  const now = new Date();
  
  if (date.toDateString() === now.toDateString()) {
    return 'TODAY';
  }
  
  const yesterday = new Date(now);
  yesterday.setDate(yesterday.getDate() - 1);
  if (date.toDateString() === yesterday.toDateString()) {
    return 'YESTERDAY';
  }
  
  return date.toLocaleDateString([], { year: 'numeric', month: 'short', day: 'numeric' }).toUpperCase();
};

const contactTypeClass = computed(() => {
  const type = store.currentConversation?.contact_type;
  if (type === 'Customer') return 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400';
  if (type === 'Driver') return 'bg-orange-100 text-orange-700 dark:bg-orange-900/30 dark:text-orange-400';
  if (type === 'Staff') return 'bg-teal-100 text-teal-700 dark:bg-teal-900/30 dark:text-teal-400';
  return 'bg-zinc-100 text-zinc-700 dark:bg-zinc-800 dark:text-zinc-300';
});

const scrollToBottom = (smooth = false) => {
  if (messagesContainer.value) {
    messagesContainer.value.scrollTo({
      top: messagesContainer.value.scrollHeight,
      behavior: smooth ? 'smooth' : 'auto'
    });
    showScrollButton.value = false;
  }
};

const handleScroll = () => {
  if (!messagesContainer.value) return;
  const { scrollTop, scrollHeight, clientHeight } = messagesContainer.value;
  // Show button if we're more than 100px from the bottom
  showScrollButton.value = scrollHeight - scrollTop - clientHeight > 100;
};

// Scroll to bottom when messages change
watch(() => store.messages, () => {
  nextTick(() => {
    // Only auto-scroll if we're already near the bottom, or if it's the initial load
    if (!messagesContainer.value) return;
    const { scrollTop, scrollHeight, clientHeight } = messagesContainer.value;
    const isNearBottom = scrollHeight - scrollTop - clientHeight < 150;
    
    if (isNearBottom || store.messages.length <= 1) {
      scrollToBottom();
    }
  });
}, { deep: true });

// Scroll to bottom immediately when switching conversations
watch(() => store.selectedConversationId, () => {
  showScrollButton.value = false;
  nextTick(() => scrollToBottom());
});

const adjustTextareaHeight = () => {
  const el = messageInput.value;
  if (el) {
    el.style.height = '42px'; // Reset to min-height
    el.style.height = (el.scrollHeight) + 'px';
  }
};

const handleEnter = (e) => {
  if (e.shiftKey) return; // Allow shift+enter for new lines
  sendMessage();
};

const sendMessage = async () => {
  await store.sendMessage();
  nextTick(() => {
    adjustTextareaHeight();
    scrollToBottom(true);
  });
};

const insertEmoji = (emoji) => {
  store.messageText += emoji;
  messageInput.value?.focus();
};
</script>
