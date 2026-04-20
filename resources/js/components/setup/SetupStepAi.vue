<template>
    <div class="space-y-5">
        <div>
            <h2 class="text-base font-semibold text-gray-900">AI Provider</h2>
            <p class="text-sm text-gray-500 mt-1">jr-developer uses your own API key — you pay your provider directly.</p>
        </div>

        <div>
            <label class="text-xs font-medium text-gray-600 mb-2 block">Provider</label>
            <div class="flex gap-2">
                <button
                    v-for="p in providers"
                    :key="p.value"
                    class="px-4 py-2 rounded-lg border text-sm font-medium transition-colors"
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
                API Key
                <HelpTooltip text="Anthropic: console.anthropic.com → API Keys. OpenAI: platform.openai.com → API Keys." />
            </label>
            <input
                v-model="form.apiKey"
                type="password"
                placeholder="sk-ant-..."
                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300"
            />
        </div>

        <div class="flex items-center gap-3">
            <button
                class="text-sm px-4 py-2 text-gray-600 border border-gray-200 rounded-lg hover:border-gray-300 transition-colors"
                @click="$emit('complete')"
            >
                Skip (Manual Mode)
            </button>
            <ConnectionTestButton
                v-if="form.apiKey"
                service="ai"
                :payload="{ provider: form.provider, api_key: form.apiKey }"
                @success="$emit('complete')"
            />
        </div>
    </div>
</template>

<script setup>
import { reactive } from 'vue';
import ConnectionTestButton from '@/components/ConnectionTestButton.vue';
import HelpTooltip from '@/components/HelpTooltip.vue';

defineEmits(['complete']);

const providers = [
    { value: 'anthropic', label: 'Anthropic' },
    { value: 'openai',    label: 'OpenAI' },
    { value: 'gemini',    label: 'Gemini' },
];

const form = reactive({ provider: 'anthropic', apiKey: '' });
</script>
