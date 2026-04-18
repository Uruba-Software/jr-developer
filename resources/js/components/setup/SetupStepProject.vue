<template>
    <div class="space-y-5">
        <div>
            <h2 class="text-base font-semibold text-gray-900">Project Path</h2>
            <p class="text-sm text-gray-500 mt-1">Tell jr-developer where your codebase lives on the server.</p>
        </div>

        <div>
            <label class="text-xs font-medium text-gray-600 mb-1 block">
                Project Name
            </label>
            <input
                v-model="form.name"
                type="text"
                placeholder="my-app"
                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm focus:outline-none focus:ring-2 focus:ring-indigo-300"
            />
        </div>

        <div>
            <label class="text-xs font-medium text-gray-600 mb-1 block">
                Absolute Path
                <HelpTooltip text="The full path to your project on this server, e.g. /var/www/my-app" />
            </label>
            <input
                v-model="form.path"
                type="text"
                placeholder="/var/www/my-app"
                class="w-full rounded-lg border border-gray-200 px-3 py-2 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-indigo-300"
            />
        </div>

        <div v-if="form.path" class="rounded-lg bg-gray-50 border border-gray-200 px-4 py-3">
            <p class="text-xs font-medium text-gray-600 mb-1">Detected project type</p>
            <p class="text-sm font-semibold text-gray-900">{{ detectedType }}</p>
        </div>

        <button
            :disabled="!form.name || !form.path"
            class="text-sm px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
            @click="$emit('complete')"
        >
            Continue →
        </button>
    </div>
</template>

<script setup>
import { reactive, computed } from 'vue';
import HelpTooltip from '@/components/HelpTooltip.vue';

defineEmits(['complete']);

const form = reactive({ name: '', path: '' });

const detectedType = computed(() => {
    const p = form.path.toLowerCase();
    if (p.includes('laravel') || p.endsWith('laravel')) return 'Laravel (PHP)';
    return 'Unknown — will auto-detect from project files';
});
</script>
