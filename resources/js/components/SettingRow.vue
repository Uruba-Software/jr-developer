<template>
    <div class="flex items-center justify-between py-2 border-b border-gray-100 last:border-0">
        <span class="text-sm text-gray-600">{{ label }}</span>
        <span class="text-sm font-medium text-gray-900 font-mono">{{ value || '—' }}</span>
    </div>
</template>

<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: String, default: '' },
});
</script>
