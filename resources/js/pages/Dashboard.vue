<template>
    <AppLayout title="Dashboard">
        <div class="space-y-6">
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
                <StatCard label="Projects" :value="String(stats.projects)" />
                <StatCard label="Active Sessions" :value="String(stats.activeSessions)" />
                <StatCard label="Tokens Today" :value="formatTokens(stats.tokensToday)" />
            </div>

            <div class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-sm font-medium text-gray-700 mb-4">Recent Activity</h2>
                <p class="text-sm text-gray-400">No recent activity yet.</p>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import StatCard from '@/components/StatCard.vue';

defineProps({
    stats: {
        type: Object,
        default: () => ({ projects: 0, activeSessions: 0, tokensToday: 0 }),
    },
});

function formatTokens(n) {
    if (n >= 1000) return `${(n / 1000).toFixed(1)}k`;
    return String(n);
}
</script>
