<template>
    <div class="bg-white rounded-xl border border-gray-200 p-5">
        <p class="text-xs text-gray-500 font-medium uppercase tracking-wide">{{ label }}</p>
        <p class="text-2xl font-semibold text-gray-900 mt-1">{{ value }}</p>
    </div>
</template>

<script setup>
defineProps({
    label: { type: String, required: true },
    value: { type: String, required: true },
});
</script>
