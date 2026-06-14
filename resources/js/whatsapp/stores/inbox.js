import { defineStore } from 'pinia';
import axios from 'axios';

const api = axios.create({
    headers: {
        'X-Requested-With': 'XMLHttpRequest',
        'Accept': 'application/json',
    },
});

// Attach CSRF token from the meta tag
api.interceptors.request.use((config) => {
    const token = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
    if (token) {
        config.headers['X-CSRF-TOKEN'] = token;
    }
    return config;
});

export const useInboxStore = defineStore('inbox', {
    state: () => ({
        conversations: [],
        selectedConversationId: null,
        selectedConversation: null,
        messages: [],
        filter: 'all',
        searchQuery: '',
        messageText: '',
        loading: false,
        sending: false,
        showClearModal: false,
        pollTimer: null,
        messagePollTimer: null,
    }),

    getters: {
        filteredConversations(state) {
            if (!state.searchQuery.trim()) {
                return state.conversations;
            }
            const q = state.searchQuery.toLowerCase();
            return state.conversations.filter(
                (c) =>
                    c.name.toLowerCase().includes(q) ||
                    c.phone_number.includes(q)
            );
        },

        currentConversation(state) {
            return state.selectedConversation;
        },

        isWindowOpen(state) {
            return state.selectedConversation?.is_window_open ?? true;
        },
    },

    actions: {
        async fetchConversations() {
            try {
                const { data } = await api.get('/whatsapp/api/conversations', {
                    params: { filter: this.filter },
                });
                this.conversations = data;
            } catch (e) {
                console.error('Failed to fetch conversations', e);
            }
        },

        async selectConversation(id) {
            this.selectedConversationId = id;
            this.messageText = '';
            this.loading = true;

            try {
                const { data } = await api.get(`/whatsapp/api/conversations/${id}/messages`);
                this.selectedConversation = data.conversation;
                this.messages = data.messages;

                // Mark as read
                await api.post(`/whatsapp/api/conversations/${id}/read`);

                // Update unread count in list
                const conv = this.conversations.find((c) => c.id === id);
                if (conv) {
                    conv.unread_count = 0;
                }
            } catch (e) {
                console.error('Failed to load messages', e);
            } finally {
                this.loading = false;
            }
        },

        async refreshMessages() {
            if (!this.selectedConversationId) return;

            try {
                const { data } = await api.get(
                    `/whatsapp/api/conversations/${this.selectedConversationId}/messages`
                );
                this.selectedConversation = data.conversation;
                this.messages = data.messages;
            } catch (e) {
                console.error('Failed to refresh messages', e);
            }
        },

        async sendMessage() {
            if (!this.messageText.trim() || !this.selectedConversationId) return;

            this.sending = true;
            try {
                await api.post(`/whatsapp/api/conversations/${this.selectedConversationId}/send`, {
                    message: this.messageText,
                });
                this.messageText = '';
                await this.refreshMessages();
                await this.fetchConversations();
            } catch (e) {
                console.error('Failed to send message', e);
            } finally {
                this.sending = false;
            }
        },

        async claimConversation() {
            if (!this.selectedConversationId) return;
            try {
                await api.post(`/whatsapp/api/conversations/${this.selectedConversationId}/claim`);
                await this.refreshMessages();
                await this.fetchConversations();
            } catch (e) {
                console.error('Failed to claim', e);
            }
        },

        async resolveConversation() {
            if (!this.selectedConversationId) return;
            try {
                await api.post(`/whatsapp/api/conversations/${this.selectedConversationId}/resolve`);
                this.selectedConversationId = null;
                this.selectedConversation = null;
                this.messages = [];
                await this.fetchConversations();
            } catch (e) {
                console.error('Failed to resolve', e);
            }
        },

        async clearConversation() {
            if (!this.selectedConversationId) return;
            try {
                await api.post(`/whatsapp/api/conversations/${this.selectedConversationId}/clear`);
                this.selectedConversationId = null;
                this.selectedConversation = null;
                this.messages = [];
                this.showClearModal = false;
                await this.fetchConversations();
            } catch (e) {
                console.error('Failed to clear', e);
            }
        },

        downloadAttachment(messageId) {
            // Open in new tab for download
            window.open(`/whatsapp/api/messages/${messageId}/download`, '_blank');
        },

        deselectConversation() {
            this.selectedConversationId = null;
            this.selectedConversation = null;
            this.messages = [];
        },

        startPolling() {
            this.stopPolling();
            this.fetchConversations();

            this.pollTimer = setInterval(() => {
                this.fetchConversations();
            }, 4000);

            this.messagePollTimer = setInterval(() => {
                if (this.selectedConversationId) {
                    this.refreshMessages();
                }
            }, 3000);
        },

        stopPolling() {
            if (this.pollTimer) {
                clearInterval(this.pollTimer);
                this.pollTimer = null;
            }
            if (this.messagePollTimer) {
                clearInterval(this.messagePollTimer);
                this.messagePollTimer = null;
            }
        },
    },
});
