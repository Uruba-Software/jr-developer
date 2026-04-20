<template>
    <span class="relative inline-block ml-1 group">
        <button
            type="button"
            class="w-4 h-4 rounded-full bg-gray-200 text-gray-500 text-xs font-bold leading-none hover:bg-indigo-100 hover:text-indigo-600 transition-colors"
        >?</button>
        <span
            class="absolute z-10 left-6 top-0 w-64 bg-gray-900 text-gray-100 text-xs rounded-lg px-3 py-2 shadow-lg
                   opacity-0 group-hover:opacity-100 pointer-events-none transition-opacity"
        >
            {{ text }}
        </span>
    </span>
</template>

<script setup>
defineProps({
    text: { type: String, required: true },
});
</script>
