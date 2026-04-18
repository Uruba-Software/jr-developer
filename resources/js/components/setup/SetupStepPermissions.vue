<template>
    <div class="space-y-5">
        <div>
            <h2 class="text-base font-semibold text-gray-900">Tool Permissions</h2>
            <p class="text-sm text-gray-500 mt-1">Choose the default approval level for each tool category. You can override per-project later.</p>
        </div>

        <div class="space-y-3">
            <div
                v-for="perm in permissions"
                :key="perm.level"
                class="flex items-start justify-between rounded-lg border border-gray-200 px-4 py-3"
            >
                <div class="flex-1">
                    <div class="flex items-center gap-2">
                        <span
                            class="text-xs px-2 py-0.5 rounded-full font-semibold font-mono uppercase"
                            :class="perm.badgeClass"
                        >
                            {{ perm.level }}
                        </span>
                        <span class="text-sm font-medium text-gray-800">{{ perm.label }}</span>
                    </div>
                    <p class="text-xs text-gray-400 mt-0.5 ml-0">{{ perm.description }}</p>
                </div>
                <select
                    v-model="form[perm.level]"
                    class="ml-4 text-sm border border-gray-200 rounded-lg px-2 py-1 focus:outline-none focus:ring-2 focus:ring-indigo-300"
                >
                    <option value="auto">Auto-allow</option>
                    <option value="approval">Require approval</option>
                    <option value="block">Block</option>
                </select>
            </div>
        </div>

        <button
            class="text-sm px-4 py-2 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors"
            @click="$emit('complete')"
        >
            Finish Setup ✓
        </button>
    </div>
</template>

<script setup>
import { reactive } from 'vue';

defineEmits(['complete']);

const permissions = [
    {
        level: 'read',
        label: 'Read',
        description: 'File reads, git status, Jira reads, directory listings.',
        badgeClass: 'bg-blue-50 text-blue-700',
    },
    {
        level: 'write',
        label: 'Write',
        description: 'File edits, branch creation, running migrations.',
        badgeClass: 'bg-yellow-50 text-yellow-700',
    },
    {
        level: 'exec',
        label: 'Exec',
        description: 'Shell commands, test runs.',
        badgeClass: 'bg-orange-50 text-orange-700',
    },
    {
        level: 'deploy',
        label: 'Deploy',
        description: 'Git push, PR creation, deployment triggers.',
        badgeClass: 'bg-red-50 text-red-700',
    },
];

const form = reactive({
    read:   'auto',
    write:  'approval',
    exec:   'approval',
    deploy: 'approval',
});
</script>
