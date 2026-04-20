<template>
    <div class="space-y-5">
        <div>
            <h2 class="text-base font-semibold text-gray-900">Version Control</h2>
            <p class="text-sm text-gray-500 mt-1">Connect your Git hosting provider so jr-developer can manage branches and pull requests.</p>
        </div>

        <div>
            <label class="text-xs font-medium text-gray-600 mb-2 block">Provider</label>
            <div class="flex gap-2">
                <button
                    v-for="p in providers"
                    :key="p.value"
                    class="flex items-center gap-2 px-4 py-2 rounded-lg border text-sm font-medium transition-colors"
                    :class="form.provider === p.value
                        ? 'border-indigo-500 bg-indigo-50 text-indigo-700'
                        : 'border-gray-200 text-gray-600 hover:border-gray-300'"
                    @click="form.provider = p.value"
                >
                    {{ p.label }}
                </button>
            </div>
        </div>

        <div>
            <label class="text-xs font-medium text-gray-600 mb-1 block">
                Personal Access Token
                <HelpTooltip text="Go to GitHub → Settings → Developer Settings → Personal Access Tokens. Required scopes: repo, workflow." />
            </label>
            <input
                v-model="form.token"
                type="password"
                placeholder="ghp_..."
                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300"
            />
        </div>

        <div class="flex items-center gap-3">
            <ConnectionTestButton
                service="github"
                :payload="{ provider: form.provider, token: form.token }"
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

const providers = [
    { value: 'github',    label: 'GitHub' },
    { value: 'gitlab',    label: 'GitLab' },
    { value: 'bitbucket', label: 'Bitbucket' },
];

const form   = reactive({ provider: 'github', token: '' });
const tested = ref(false);
</script>
