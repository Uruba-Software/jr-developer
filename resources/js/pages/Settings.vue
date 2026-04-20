<template>
    <AppLayout title="Settings">
        <div class="max-w-2xl space-y-6">
            <section class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-sm font-semibold text-gray-800 mb-4">General</h2>
                <div class="space-y-4">
                    <SettingRow label="Application URL" :value="appUrl" />
                    <SettingRow label="Environment" :value="environment" />
                    <SettingRow label="Queue Connection" :value="queueConnection" />
                </div>
            </section>

            <section class="bg-white rounded-xl border border-gray-200 p-6">
                <h2 class="text-sm font-semibold text-gray-800 mb-4">Connection Status</h2>
                <div class="space-y-3">
                    <ConnectionStatusRow label="GitHub" :connected="connections.github" />
                    <ConnectionStatusRow label="Slack" :connected="connections.slack" />
                    <ConnectionStatusRow label="Jira" :connected="connections.jira" />
                    <ConnectionStatusRow label="AI Provider" :connected="connections.ai" />
                </div>
            </section>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import SettingRow from '@/components/SettingRow.vue';
import ConnectionStatusRow from '@/components/ConnectionStatusRow.vue';

defineProps({
    appUrl:          { type: String, default: '' },
    environment:     { type: String, default: '' },
    queueConnection: { type: String, default: '' },
    connections: {
        type: Object,
        default: () => ({ github: false, slack: false, jira: false, ai: false }),
    },
});
</script>
