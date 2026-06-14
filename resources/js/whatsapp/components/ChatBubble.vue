<template>
  <div class="flex" :class="[isCustomer ? 'justify-start' : 'justify-end', isSequential ? 'mb-[2px]' : 'mb-2']">
    <!-- Customer Avatar -->
    <div v-if="isCustomer" class="w-8 h-8 flex items-center justify-center shrink-0 mr-2 mt-auto">
      <div v-if="!isSequential" class="w-8 h-8 rounded-full bg-zinc-200 dark:bg-zinc-700 flex items-center justify-center text-xs font-medium text-zinc-600 dark:text-zinc-300 shadow-sm">
        {{ (contactName || 'C').substring(0, 2).toUpperCase() }}
      </div>
    </div>

    <!-- Bubble -->
    <div 
      class="relative max-w-[75%] px-3 py-2 text-[14.2px] leading-[19px] break-words shadow-sm"
      :class="bubbleClasses"
    >
      <!-- Media Attachment -->
      <div v-if="msg.media_url && ['image', 'document', 'audio', 'video'].includes(msg.message_type)" class="mb-2 -mx-1 -mt-1">
        <template v-if="msg.message_type === 'image'">
          <div class="relative group rounded-md overflow-hidden bg-black/5 dark:bg-white/5 min-h-[100px] min-w-[150px] max-w-full">
            <img :src="msg.media_url.startsWith('http') ? msg.media_url : `/whatsapp/api/messages/${msg.id}/download`" alt="Image" class="max-w-[250px] max-h-[300px] object-cover cursor-pointer hover:opacity-95 transition-opacity" />
            <a :href="msg.media_url.startsWith('http') ? msg.media_url : `/whatsapp/api/messages/${msg.id}/download`" target="_blank" class="absolute bottom-2 right-2 p-1.5 rounded-full bg-black/50 text-white opacity-0 group-hover:opacity-100 transition-opacity hover:bg-black/70">
              <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
            </a>
          </div>
        </template>
        <template v-else>
          <button 
            v-if="!msg.media_url.startsWith('http')"
            @click="store.downloadAttachment(msg.id)"
            class="flex items-center gap-2 w-full text-left bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 rounded-md p-2 transition-colors text-sm font-medium mt-1 mx-1"
          >
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" /></svg>
            <span class="truncate">Download {{ msg.message_type }}</span>
          </button>
          <a 
            v-else
            :href="msg.media_url" 
            target="_blank"
            class="flex items-center gap-2 w-full text-left bg-black/5 dark:bg-white/5 hover:bg-black/10 dark:hover:bg-white/10 rounded-md p-2 transition-colors text-sm font-medium mt-1 mx-1"
          >
            <svg class="w-4 h-4 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" /></svg>
            <span class="truncate">View {{ msg.message_type }}</span>
          </a>
        </template>
      </div>
      
      <!-- Message Text -->
      <p class="whitespace-pre-wrap" :class="textClasses">{{ msg.message_text }}</p>
      
      <!-- Footer: Time & Status -->
      <div class="flex items-center justify-end gap-1 mt-1 -mr-1 -mb-1 float-right clear-both ml-4">
        <span class="text-[10px] leading-none" :class="timeClasses">{{ formatTime(msg.created_at) }}</span>
        <svg 
          v-if="!isCustomer" 
          class="w-[16px] h-[16px]" 
          :class="msg.status === 'read' ? 'text-blue-500' : 'text-zinc-400 dark:text-zinc-500'" 
          viewBox="0 0 16 15" 
          fill="none"
          xmlns="http://www.w3.org/2000/svg"
        >
          <!-- Single check for sent/delivered -->
          <path d="M10.91 3.53601L5.35299 9.09301L2.83599 6.57601L1.42199 7.99001L5.35299 11.921L12.324 4.95001L10.91 3.53601Z" fill="currentColor"/>
          <!-- Double check for delivered/read -->
          <path v-if="['delivered', 'read'].includes(msg.status)" d="M15.114 3.53601L13.699 2.12101L8.85499 6.96601L10.269 8.38001L15.114 3.53601Z" fill="currentColor"/>
          <path v-if="['delivered', 'read'].includes(msg.status)" d="M6.06099 11.921L7.47499 13.335L8.18199 12.628L6.76799 11.214L6.06099 11.921Z" fill="currentColor"/>
        </svg>
      </div>
    </div>

    <!-- Agent Avatar -->
    <div v-if="!isCustomer" class="w-8 h-8 flex items-center justify-center shrink-0 ml-2 mt-auto">
      <div v-if="!isSequential" class="w-8 h-8 rounded-full bg-zinc-200 dark:bg-zinc-800 flex items-center justify-center text-xs font-medium text-zinc-600 dark:text-zinc-300 shadow-sm">
        <!-- Read from window.__whatsapp user if available, fallback to 'Me' -->
        {{ windowUserInitials }}
      </div>
    </div>
  </div>
</template>

<script setup>
import { computed } from 'vue';
import { useInboxStore } from '../stores/inbox';

const props = defineProps({
  msg: {
    type: Object,
    required: true
  },
  contactName: {
    type: String,
    default: ''
  },
  isSequential: {
    type: Boolean,
    default: false
  }
});

const store = useInboxStore();

const isCustomer = computed(() => props.msg.sender_type === 'customer');
const isInternal = computed(() => props.msg.is_internal);

const windowUserInitials = computed(() => {
  const name = window.__whatsapp?.user?.name || 'Me';
  return name.substring(0, 2).toUpperCase();
});

const bubbleClasses = computed(() => {
  if (isInternal.value) {
    return `rounded-[7.5px] bg-[#fff4ce] dark:bg-[#5c4b00] ${props.isSequential ? '' : 'rounded-tr-none'}`;
  }
  if (isCustomer.value) {
    return `rounded-[7.5px] bg-white dark:bg-[#202c33] ${props.isSequential ? '' : 'rounded-tl-none msg-bubble-in'}`;
  }
  return `rounded-[7.5px] bg-[#d9fdd3] dark:bg-[#005c4b] ${props.isSequential ? '' : 'rounded-tr-none msg-bubble-out'}`;
});

const textClasses = computed(() => {
  if (isInternal.value) return 'text-amber-900 dark:text-amber-100';
  if (isCustomer.value) return 'text-[#111b21] dark:text-[#e9edef]';
  return 'text-[#111b21] dark:text-[#e9edef]';
});

const timeClasses = computed(() => {
  if (isInternal.value) return 'text-amber-700/70 dark:text-amber-300/70';
  return 'text-[#667781] dark:text-[#8696a0]';
});

const formatTime = (isoString) => {
  if (!isoString) return '';
  return new Date(isoString).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' });
};
</script>

<style scoped>
/* WhatsApp style bubble tails using pseudo-elements */
.msg-bubble-in::before {
  content: '';
  position: absolute;
  top: 0;
  left: -8px;
  width: 8px;
  height: 13px;
  background-image: radial-gradient(circle at top left, transparent 0, transparent 70%, currentcolor 70%);
  color: #ffffff; /* Light mode bg-white */
}
@media (prefers-color-scheme: dark) {
  .msg-bubble-in::before { color: #202c33; }
}
html.dark .msg-bubble-in::before { color: #202c33; }

.msg-bubble-out::before {
  content: '';
  position: absolute;
  top: 0;
  right: -8px;
  width: 8px;
  height: 13px;
  background-image: radial-gradient(circle at top right, transparent 0, transparent 70%, currentcolor 70%);
  color: #d9fdd3;
}
@media (prefers-color-scheme: dark) {
  .msg-bubble-out::before { color: #005c4b; }
}
html.dark .msg-bubble-out::before { color: #005c4b; }
</style>
