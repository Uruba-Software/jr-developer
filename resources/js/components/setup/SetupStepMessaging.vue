<template>
    <div class="space-y-5">
        <div>
            <h2 class="text-base font-semibold text-gray-900">Messaging Platform</h2>
            <p class="text-sm text-gray-500 mt-1">Choose how your team will communicate with jr-developer.</p>
        </div>

        <div>
            <label class="text-xs font-medium text-gray-600 mb-2 block">Platform</label>
            <div class="flex gap-2">
                <button
                    v-for="p in platforms"
                    :key="p.value"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg border text-sm font-medium transition-colors"
                    :class="form.platform === p.value
                        ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                        : 'border-gray-200 text-gray-600 hover:border-gray-300'"
                    @click="form.platform = p.value"
                >
                    {{ p.label }}
                </button>
            </div>
        </div>

        <div>
            <label class="text-xs font-medium text-gray-600 mb-1 block">
                Bot Token
                <HelpTooltip text="Slack: go to api.slack.com → Your Apps → OAuth & Permissions → Bot User OAuth Token (xoxb-...)." />
            </label>
            <input
                v-model="form.token"
                type="password"
                placeholder="xoxb-..."
                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300"
            />
        </div>

        <div>
            <label class="text-xs font-medium text-gray-600 mb-1 block">
                Default Channel ID
                <HelpTooltip text="Right-click the channel in Slack → View channel details → Copy Channel ID at the bottom." />
            </label>
            <input
                v-model="form.channel"
                type="text"
                placeholder="C01234ABCDE"
                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300"
            />
        </div>

        <div class="flex items-center gap-3">
            <ConnectionTestButton
                service="slack"
                :payload="{ platform: form.platform, token: form.token, channel: form.channel }"
                @success="tested = true"
            />
            <button
                v-if="tested"
                class="text-sm px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors"
                @click="$emit('complete')"
            >
                Continue →
            </button>
        </div>
    </div>
</template>

<script setup>
import { ref, reactive } from 'vue';
import ConnectionTestButton from '@/components/ConnectionTestButton.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';

defineEmits(['complete']);

const platforms = [
    { value: 'slack',   label: 'Slack' },
    { value: 'discord', label: 'Discord' },
];

const form   = reactive({ platform: 'slack', token: '', channel: '' });
const tested = ref(false);
</script>
