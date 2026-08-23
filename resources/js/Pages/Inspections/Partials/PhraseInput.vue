<script setup lang="ts">
/**
 * Description field backed by the phrase library.
 *
 * Typing two or more characters hits GET /phrases/search through the app's
 * own proxy (see InspectionController@searchPhrases). Results come back
 * tagged with a subcategory — Condition, Cleanliness, Defects, Attributes —
 * so they are grouped rather than listed flat, and several can be ticked and
 * appended to the description in one go.
 */
import { ref } from 'vue';
import { Button } from '@/components/ui/button';
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/components/ui/input-group';
import { Check } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import axios from 'axios';

interface Phrase {
    id: string;
    text: string;
    category: string;
    context: string | null;
    subcategory?: string;
    usage_count?: number;
}

interface PhraseGroup {
    label: string;
    color: string;
    phrases: Phrase[];
}

const props = withDefaults(defineProps<{
    modelValue: string;
    label?: string;
    category?: 'area' | 'item' | 'element';
    context?: string;
    itemName?: string;
    placeholder?: string;
}>(), {
    label: 'Description',
    category: 'item',
    placeholder: 'Type to search phrases...',
});

const emit = defineEmits<{ 'update:modelValue': [value: string] }>();

const subcategoryConfig: Record<string, { color: string; order: number }> = {
    Condition: { color: 'text-emerald-500', order: 0 },
    Cleanliness: { color: 'text-blue-500', order: 1 },
    Defects: { color: 'text-amber-500', order: 2 },
    Attributes: { color: 'text-violet-500', order: 3 },
};

const groups = ref<PhraseGroup[]>([]);
const open = ref(false);
const selected = ref<Set<string>>(new Set());

const groupPhrases = (phrases: Phrase[]): PhraseGroup[] => {
    const buckets: Record<string, Phrase[]> = {};
    phrases.forEach((p) => {
        const key = p.subcategory ?? p.context ?? 'General';
        (buckets[key] ??= []).push(p);
    });

    return Object.entries(buckets)
        .sort(([a], [b]) => (subcategoryConfig[a]?.order ?? 99) - (subcategoryConfig[b]?.order ?? 99) || a.localeCompare(b))
        .map(([label, items]) => ({
            label,
            color: subcategoryConfig[label]?.color ?? 'text-muted-foreground',
            phrases: items,
        }));
};

const search = useDebounceFn(async (query: string) => {
    if (query.length < 2) {
        groups.value = [];
        open.value = false;
        return;
    }

    try {
        const params: Record<string, string> = { q: query, category: props.category };
        if (props.context) params.context = props.context;
        if (props.itemName) params.item_name = props.itemName;

        const { data } = await axios.get(route('api.phrases.search'), { params });
        groups.value = groupPhrases(data ?? []);
        open.value = groups.value.length > 0;
    } catch {
        groups.value = [];
    }
}, 300);

const onInput = (value: string | number) => {
    emit('update:modelValue', String(value));
    search(String(value));
};

const toggle = (text: string) => {
    const next = new Set(selected.value);
    next.has(text) ? next.delete(text) : next.add(text);
    selected.value = next;
};

const confirm = () => {
    if (selected.value.size) {
        const existing = props.modelValue ? `${props.modelValue}. ` : '';
        emit('update:modelValue', existing + Array.from(selected.value).join('. '));
        selected.value = new Set();
    }
    open.value = false;
};

// The dropdown closes on blur, but only after the click that caused the blur
// has had a chance to land on one of its buttons.
const close = () => globalThis.setTimeout(() => { open.value = false; }, 200);
</script>

<template>
    <div class="relative">
        <InputGroup>
            <InputGroupAddon class="w-28 shrink-0">{{ label }}</InputGroupAddon>
            <InputGroupInput
                :model-value="modelValue"
                :placeholder="placeholder"
                @update:model-value="onInput"
                @focus="groups.length && (open = true)"
                @blur="close"
            />
        </InputGroup>

        <div
            v-if="open && groups.length"
            class="absolute z-50 mt-1 max-h-72 w-full overflow-y-auto rounded-md border bg-popover shadow-md"
        >
            <template v-for="group in groups" :key="group.label">
                <div class="flex items-center gap-1.5 px-3 pt-2.5 pb-1">
                    <span :class="group.color" class="text-xs">&#9679;</span>
                    <span class="text-xs font-semibold" :class="group.color">{{ group.label }}</span>
                    <span class="text-xs text-muted-foreground">({{ group.phrases.length }})</span>
                </div>
                <button
                    v-for="phrase in group.phrases"
                    :key="phrase.id"
                    class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm hover:bg-accent hover:text-accent-foreground"
                    @mousedown.prevent="toggle(phrase.text)"
                >
                    <div
                        class="flex h-4 w-4 shrink-0 items-center justify-center rounded border"
                        :class="selected.has(phrase.text) ? 'border-primary bg-primary text-primary-foreground' : 'border-input'"
                    >
                        <Check v-if="selected.has(phrase.text)" class="h-3 w-3" />
                    </div>
                    <span class="flex-1">{{ phrase.text }}</span>
                    <span v-if="phrase.usage_count" class="shrink-0 text-xs text-muted-foreground">{{ phrase.usage_count }}x</span>
                </button>
            </template>

            <div v-if="selected.size" class="sticky bottom-0 flex items-center justify-between border-t bg-popover p-2">
                <span class="text-xs text-muted-foreground">{{ selected.size }} selected</span>
                <Button size="sm" class="h-7 text-xs" @mousedown.prevent="confirm">
                    Add to {{ label.toLowerCase() }}
                </Button>
            </div>
        </div>
    </div>
</template>
