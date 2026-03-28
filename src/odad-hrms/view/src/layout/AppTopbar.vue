<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import { useLayout } from '@/layout/composables/layout';
import { request } from '@/api/client';
import AppConfigurator from './AppConfigurator.vue';

const { toggleMenu, toggleDarkMode, isDarkTheme } = useLayout();

const notifications = ref([]);
const notifPanelRef = ref(null);
let pollInterval = null;

const unreadCount = computed(() => notifications.value.filter(n => !parseInt(n.is_read)).length);

async function loadNotifications() {
    try {
        const res = await request('notifications?is_read=0');
        if (!res.error && res.data?.items) {
            notifications.value = res.data.items;
        }
    } catch {
        // silent fail for polling
    }
}

async function markAllRead() {
    try {
        await request('notifications/read-all', { method: 'POST', body: JSON.stringify({}) });
        notifications.value = notifications.value.map(n => ({ ...n, is_read: '1' }));
    } catch {
        // silent
    }
}

async function markRead(notif) {
    if (parseInt(notif.is_read)) return;
    try {
        await request(`notifications/${notif.id}/read`, { method: 'POST', body: JSON.stringify({}) });
        const idx = notifications.value.findIndex(n => n.id === notif.id);
        if (idx !== -1) notifications.value[idx] = { ...notifications.value[idx], is_read: '1' };
    } catch {
        // silent
    }
}

function formatTime(dateStr) {
    if (!dateStr) return '';
    const d = new Date(dateStr.replace(' ', 'T'));
    const now = new Date();
    const diff = Math.floor((now - d) / 1000);
    if (diff < 60) return 'just now';
    if (diff < 3600) return `${Math.floor(diff / 60)}m ago`;
    if (diff < 86400) return `${Math.floor(diff / 3600)}h ago`;
    return `${Math.floor(diff / 86400)}d ago`;
}

onMounted(() => {
    loadNotifications();
    pollInterval = setInterval(loadNotifications, 60000);
});

onUnmounted(() => {
    if (pollInterval) clearInterval(pollInterval);
});
</script>

<template>
    <div class="layout-topbar">
        <div class="layout-topbar-logo-container">
            <router-link to="/" class="layout-topbar-logo">
                <span>WP Employee Manager</span>
            </router-link>
        </div>

        <div class="layout-topbar-actions">
            <div class="layout-config-menu">
                <button type="button" class="layout-topbar-action" @click="toggleDarkMode">
                    <i :class="['pi', { 'pi-moon': isDarkTheme, 'pi-sun': !isDarkTheme }]"></i>
                </button>
                <div class="relative">
                    <button
                        v-styleclass="{ selector: '@next', enterFromClass: 'hidden', enterActiveClass: 'p-anchored-overlay-enter-active', leaveToClass: 'hidden', leaveActiveClass: 'p-anchored-overlay-leave-active', hideOnOutsideClick: true }"
                        type="button"
                        class="layout-topbar-action layout-topbar-action-highlight"
                    >
                        <i class="pi pi-palette"></i>
                    </button>
                    <AppConfigurator />
                </div>
            </div>

            <!-- Notification Bell -->
            <div class="relative">
                <button
                    v-styleclass="{ selector: '@next', enterFromClass: 'hidden', enterActiveClass: 'p-anchored-overlay-enter-active', leaveToClass: 'hidden', leaveActiveClass: 'p-anchored-overlay-leave-active', hideOnOutsideClick: true }"
                    type="button"
                    class="layout-topbar-action relative"
                    aria-label="Notifications"
                >
                    <i class="pi pi-bell"></i>
                    <span
                        v-if="unreadCount > 0"
                        class="absolute -top-1 -right-1 bg-red-500 text-white text-xs rounded-full w-4 h-4 flex items-center justify-center font-bold leading-none"
                    >{{ unreadCount > 9 ? '9+' : unreadCount }}</span>
                </button>

                <!-- Notification Panel -->
                <div class="hidden absolute right-0 top-full mt-2 z-50 w-80 bg-surface-0 border border-surface-200 rounded-lg shadow-xl overflow-hidden">
                    <div class="flex justify-between items-center px-4 py-3 border-b border-surface-200">
                        <span class="font-semibold text-sm">Notifications</span>
                        <button
                            v-if="unreadCount > 0"
                            type="button"
                            class="text-xs text-primary hover:underline"
                            @click="markAllRead"
                        >Mark all read</button>
                    </div>
                    <div class="max-h-80 overflow-y-auto">
                        <!-- <div v-if="!notifications.length" class="px-4 py-6 text-center text-surface-400 text-sm">
                            No notifications
                        </div> -->
                        <div
                            v-for="n in notifications"
                            :key="n.id"
                            class="px-4 py-3 border-b border-surface-100 last:border-0 hover:bg-surface-50 cursor-pointer transition-colors"
                            :class="{ 'bg-blue-50': !parseInt(n.is_read) }"
                            @click="markRead(n)"
                        >
                            <div class="flex justify-between items-start gap-2">
                                <span class="text-sm font-medium text-surface-700 leading-snug">{{ n.title }}</span>
                                <span class="text-xs text-surface-400 shrink-0">{{ formatTime(n.created_at) }}</span>
                            </div>
                            <p class="text-xs text-surface-500 mt-0.5 leading-snug">{{ n.message }}</p>
                        </div>
                    </div>
                </div>
            </div>

            <button
                class="layout-topbar-menu-button layout-topbar-action"
                v-styleclass="{ selector: '@next', enterFromClass: 'hidden', enterActiveClass: 'p-anchored-overlay-enter-active', leaveToClass: 'hidden', leaveActiveClass: 'p-anchored-overlay-leave-active', hideOnOutsideClick: true }"
            >
                <i class="pi pi-ellipsis-v"></i>
            </button>

            <div class="layout-topbar-menu hidden lg:block">
                <div class="layout-topbar-menu-content">
                    <button type="button" class="layout-topbar-action">
                        <i class="pi pi-calendar"></i>
                        <span>Calendar</span>
                    </button>
                    <button type="button" class="layout-topbar-action">
                        <i class="pi pi-inbox"></i>
                        <span>Messages</span>
                    </button>
                    <button type="button" class="layout-topbar-action">
                        <i class="pi pi-user"></i>
                        <span>Profile</span>
                    </button>
                </div>
            </div>
        </div>
    </div>
</template>
