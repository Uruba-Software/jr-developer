<template>
    <div class="inline-flex items-center gap-2">
        <button
            :disabled="loading || !canTest"
            class="inline-flex items-center gap-2 text-sm px-4 py-2 rounded-lg border font-medium transition-colors"
            :class="buttonClass"
            @click="runTest"
        >
            <ArrowPathIcon v-if="loading" class="w-4 h-4 animate-spin" />
            <CheckCircleIcon v-else-if="status === 'success'" class="w-4 h-4" />
            <XCircleIcon v-else-if="status === 'error'" class="w-4 h-4" />
            <SignalIcon v-else class="w-4 h-4" />
            {{ label }}
        </button>

        <span v-if="message" class="text-xs" :class="status === 'success' ? 'text-green-600' : 'text-red-500'">
            {{ message }}
        </span>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { router } from '@inertiajs/vue3';
import {
    ArrowPathIcon,
    CheckCircleIcon,
    XCircleIcon,
    SignalIcon,
} from '@heroicons/vue/24/outline';
import axios from 'axios';

const props = defineProps({
    service: { type: String, required: true },
    payload: { type: Object, default: () => ({}) },
    canTest: { type: Boolean, default: true },
});

const emit = defineEmits(['success', 'error']);

const loading = ref(false);
const status  = ref(null);
const message = ref('');

const label = computed(() => {
    if (loading.value)           return 'Testing...';
    if (status.value === 'success') return 'Connected';
    if (status.value === 'error')   return 'Retry';
    return 'Test Connection';
});

const buttonClass = computed(() => {
    if (status.value === 'success') return 'border-green-300 bg-green-50 text-green-700';
    if (status.value === 'error')   return 'border-red-300 bg-red-50 text-red-600';
    return 'border-gray-200 text-gray-600 hover:border-indigo-400 hover:text-indigo-600 disabled:opacity-40 disabled:cursor-not-allowed';
});

async function runTest() {
    loading.value = true;
    status.value  = null;
    message.value = '';

    try {
        const response = await axios.post(
            route(`connection-test.${props.service}`),
            props.payload,
            { headers: { 'X-Requested-With': 'XMLHttpRequest' } },
        );
        status.value  = 'success';
        message.value = response.data.message ?? 'Connection successful';
        emit('success', response.data);
    } catch (err) {
        status.value  = 'error';
        message.value = err.response?.data?.message ?? 'Connection failed';
        emit('error', err.response?.data);
    } finally {
        loading.value = false;
    }
}
</script>
