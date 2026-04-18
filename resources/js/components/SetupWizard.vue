<template>
    <div class="bg-white rounded-2xl border border-gray-200 shadow-sm overflow-hidden">
        <!-- Progress bar -->
        <div class="h-1 bg-gray-100">
            <div
                class="h-1 bg-indigo-500 transition-all duration-300"
                :style="{ width: `${((currentStep + 1) / steps.length) * 100}%` }"
            />
        </div>

        <!-- Step indicators -->
        <div class="flex border-b border-gray-100 px-6 pt-5 pb-4 gap-2 overflow-x-auto">
            <button
                v-for="(step, i) in steps"
                :key="i"
                class="flex items-center gap-2 text-xs whitespace-nowrap px-2 py-1 rounded-full transition-colors"
                :class="stepClass(i)"
                :disabled="i > furthestReached"
                @click="goToStep(i)"
            >
                <span
                    class="w-5 h-5 rounded-full flex items-center justify-center text-xs font-semibold flex-shrink-0"
                    :class="stepBadgeClass(i)"
                >
                    <CheckIcon v-if="completedSteps.includes(i)" class="w-3 h-3" />
                    <span v-else>{{ i + 1 }}</span>
                </span>
                {{ step.title }}
            </button>
        </div>

        <!-- Step body -->
        <div class="px-6 py-6">
            <component
                :is="steps[currentStep].component"
                v-bind="steps[currentStep].props ?? {}"
                @complete="handleStepComplete"
            />
        </div>

        <!-- Footer navigation -->
        <div class="flex items-center justify-between px-6 py-4 border-t border-gray-100 bg-gray-50">
            <button
                v-if="currentStep > 0"
                class="text-sm text-gray-500 hover:text-gray-700 transition-colors"
                @click="prev"
            >
                ← Back
            </button>
            <span v-else />

            <div class="text-xs text-gray-400">
                Step {{ currentStep + 1 }} of {{ steps.length }}
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';
import { CheckIcon } from '@heroicons/vue/24/solid';
import SetupStepVcs from '@/components/setup/SetupStepVcs.vue';
import SetupStepMessaging from '@/components/setup/SetupStepMessaging.vue';
import SetupStepAi from '@/components/setup/SetupStepAi.vue';
import SetupStepProject from '@/components/setup/SetupStepProject.vue';
import SetupStepPermissions from '@/components/setup/SetupStepPermissions.vue';

defineProps({
    initialConfig: { type: Object, default: () => ({}) },
});

const steps = [
    { title: 'Version Control',    component: SetupStepVcs },
    { title: 'Messaging',          component: SetupStepMessaging },
    { title: 'AI Provider',        component: SetupStepAi },
    { title: 'Project Path',       component: SetupStepProject },
    { title: 'Tool Permissions',   component: SetupStepPermissions },
];

const currentStep    = ref(0);
const furthestReached = ref(0);
const completedSteps  = ref([]);

function stepClass(i) {
    if (i === currentStep.value) return 'bg-indigo-50 text-indigo-700 font-medium';
    if (completedSteps.value.includes(i)) return 'text-green-600';
    if (i <= furthestReached.value) return 'text-gray-500 hover:text-gray-700 cursor-pointer';
    return 'text-gray-300 cursor-not-allowed';
}

function stepBadgeClass(i) {
    if (completedSteps.value.includes(i)) return 'bg-green-100 text-green-700';
    if (i === currentStep.value) return 'bg-indigo-600 text-white';
    return 'bg-gray-100 text-gray-400';
}

function handleStepComplete() {
    if (!completedSteps.value.includes(currentStep.value)) {
        completedSteps.value.push(currentStep.value);
    }
    if (currentStep.value < steps.length - 1) {
        currentStep.value++;
        if (currentStep.value > furthestReached.value) {
            furthestReached.value = currentStep.value;
        }
    }
}

function prev() {
    if (currentStep.value > 0) currentStep.value--;
}

function goToStep(i) {
    if (i <= furthestReached.value) currentStep.value = i;
}
</script>
