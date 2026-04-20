<template>
    <div class="space-y-4">
        <div class="flex items-center justify-between">
            <div class="flex rounded-lg border border-gray-200 overflow-hidden text-sm">
                <button
                    class="px-3 py-1.5 transition-colors"
                    :class="tab === 'edit' ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-50'"
                    @click="tab = 'edit'"
                >
                    Edit
                </button>
                <button
                    class="px-3 py-1.5 transition-colors"
                    :class="tab === 'preview' ? 'bg-indigo-600 text-white' : 'text-gray-600 hover:bg-gray-50'"
                    @click="tab = 'preview'"
                >
                    Preview
                </button>
            </div>

            <div class="flex items-center gap-2">
                <span v-if="saveStatus" class="text-xs" :class="saveStatus === 'saved' ? 'text-green-600' : 'text-red-500'">
                    {{ saveStatus === 'saved' ? '✓ Saved' : 'Save failed' }}
                </span>
                <button
                    :disabled="saving || !dirty"
                    class="text-sm px-4 py-1.5 bg-indigo-600 text-white rounded-lg hover:bg-indigo-700 transition-colors disabled:opacity-40 disabled:cursor-not-allowed"
                    @click="save"
                >
                    {{ saving ? 'Saving…' : 'Save' }}
                </button>
            </div>
        </div>

        <!-- Editor pane -->
        <div v-show="tab === 'edit'" class="rounded-xl border border-gray-200 overflow-hidden">
            <div class="bg-gray-800 px-4 py-2 text-xs text-gray-400 flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-yellow-400" />
                <span class="font-mono">project rules (Markdown)</span>
            </div>
            <codemirror
                v-model="localValue"
                :extensions="extensions"
                class="text-sm"
                style="min-height: 320px; max-height: 600px;"
            />
        </div>

        <!-- Preview pane -->
        <div
            v-show="tab === 'preview'"
            class="rounded-xl border border-gray-200 bg-white p-6 prose prose-sm max-w-none min-h-80"
            v-html="renderedPreview"
        />

        <!-- System prompt preview -->
        <details class="rounded-xl border border-gray-200 overflow-hidden">
            <summary class="px-4 py-3 text-sm font-medium text-gray-700 cursor-pointer bg-gray-50 hover:bg-gray-100">
                How this appears in the AI system prompt
            </summary>
            <pre class="p-4 text-xs text-gray-600 bg-gray-50 overflow-x-auto whitespace-pre-wrap">{{ systemPromptPreview }}</pre>
        </details>
    </div>
</template>

<script setup>
import { ref, computed, watch } from 'vue';
import { Codemirror } from 'vue-codemirror';
import { markdown } from '@codemirror/lang-markdown';
import { oneDark } from '@codemirror/theme-one-dark';
import axios from 'axios';

const props = defineProps({
    projectId: { type: Number, required: true },
    modelValue: { type: String, default: '' },
});

const emit = defineEmits(['update:modelValue']);

const tab        = ref('edit');
const saving     = ref(false);
const saveStatus = ref(null);
const dirty      = ref(false);
const localValue = ref(props.modelValue);

const extensions = [markdown(), oneDark];

watch(localValue, (val) => {
    dirty.value = val !== props.modelValue;
    emit('update:modelValue', val);
});

const renderedPreview = computed(() => {
    return localValue.value
        .replace(/^### (.+)$/gm, '<h3>$1</h3>')
        .replace(/^## (.+)$/gm, '<h2>$1</h2>')
        .replace(/^# (.+)$/gm, '<h1>$1</h1>')
        .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
        .replace(/\*(.+?)\*/g, '<em>$1</em>')
        .replace(/^- (.+)$/gm, '<li>$1</li>')
        .replace(/(<li>.*<\/li>)/s, '<ul>$1</ul>')
        .replace(/\n/g, '<br>');
});

const systemPromptPreview = computed(() => {
    if (!localValue.value.trim()) return '(no rules set)';
    return `## Project Rules\n\n${localValue.value.trim()}\n\n---`;
});

async function save() {
    saving.value     = true;
    saveStatus.value = null;

    try {
        await axios.patch(route('projects.rules.update', props.projectId), {
            rules: localValue.value,
        });
        saveStatus.value = 'saved';
        dirty.value      = false;
        emit('update:modelValue', localValue.value);
        setTimeout(() => { saveStatus.value = null; }, 3000);
    } catch {
        saveStatus.value = 'error';
    } finally {
        saving.value = false;
    }
}
</script>
