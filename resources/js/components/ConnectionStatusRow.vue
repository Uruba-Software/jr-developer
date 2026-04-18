<template>
    <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
        <span class="text-sm text-gray-600">{{ label }}</span>
        <div class="flex items-center gap-1.5">
            <span
                class="w-2 h-2 rounded-full"
                :class="connected ? 'bg-green-500' : 'bg-gray-300'"
            />
            <span class="text-xs text-gray-500">{{ connected ? 'Connected' : 'Not configured' }}</span>
        </div>
    </div>
</template>

<script setup>
defineProps({
    label:     { type: String,  required: true },
    connected: { type: Boolean, default: false },
});
</script>
