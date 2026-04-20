<template>
    <div class="min-h-screen bg-gradient-to-br from-indigo-50 to-gray-100 flex items-center justify-center p-6">
        <div class="w-full max-w-2xl">
            <div class="text-center mb-8">
                <h1 class="text-2xl font-bold text-gray-900">jr-developer Setup</h1>
                <p class="text-gray-500 text-sm mt-1">Connect your tools and configure your first project</p>
            </div>
            <SetupWizard :initial-config="config" />
        </div>
    </div>
</template>

<script setup>
import SetupWizard from '@/components/SetupWizard.vue';

defineProps({
    config: { type: Object, default: () => ({}) },
});
</script>
