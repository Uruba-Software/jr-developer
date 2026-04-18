<template>
    <AppLayout title="Projects">
        <div class="space-y-4">
            <div class="flex items-center justify-between">
                <p class="text-sm text-gray-500">{{ projects.length }} project{{ projects.length !== 1 ? 's' : '' }}</p>
            </div>

            <div v-if="projects.length === 0" class="bg-white rounded-xl border border-gray-200 p-12 text-center">
                <FolderOpenIcon class="w-10 h-10 text-gray-300 mx-auto mb-3" />
                <p class="text-sm font-medium text-gray-600 mb-1">No projects yet</p>
                <p class="text-sm text-gray-400">Run <code class="bg-gray-100 px-1 rounded">php artisan jr:project:add</code> to add one.</p>
            </div>

            <div v-else class="grid grid-cols-1 gap-3">
                <div
                    v-for="project in projects"
                    :key="project.id"
                    class="bg-white rounded-xl border border-gray-200 p-5 flex items-center justify-between"
                >
                    <div>
                        <p class="font-medium text-gray-900 text-sm">{{ project.name }}</p>
                        <p class="text-xs text-gray-400 mt-0.5">{{ project.path }}</p>
                    </div>
                    <div class="flex items-center gap-2">
                        <span
                            class="text-xs px-2 py-0.5 rounded-full font-medium"
                            :class="project.is_active
                                ? 'bg-green-50 text-green-700'
                                : 'bg-gray-100 text-gray-500'"
                        >
                            {{ project.is_active ? 'active' : 'inactive' }}
                        </span>
                    </div>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<script setup>
import AppLayout from '@/layouts/AppLayout.vue';
import { FolderOpenIcon } from '@heroicons/vue/24/outline';

defineProps({
    projects: { type: Array, default: () => [] },
});
</script>
