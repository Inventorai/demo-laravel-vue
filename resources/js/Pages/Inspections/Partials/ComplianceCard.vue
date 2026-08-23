<script setup lang="ts">
/**
 * Compliance — Inventorai::compliance().
 *
 * When a form is attached to an inspection the API snapshots the team's
 * template, so what arrives here is that inspection's own copy: forms →
 * sections → fields, each field carrying the response for this inspection.
 * The `complianceForms` include flattens that into `items`, which is the
 * shape the mobile app works from and the shape used below.
 *
 * Answers save one field at a time via
 * compliance()->updateResponse($inspectionId, $fieldId, ['value' => ...]).
 * The API types the stored value from the field's own field_type, so a yes_no
 * field is sent a boolean and a date field an ISO date string.
 */
import { computed, ref, watch } from 'vue';
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { CircleAlert, CircleCheck, CircleDashed, ExternalLink, Save } from '@lucide/vue';
import UnsavedBadge from './UnsavedBadge.vue';
import { router } from '@inertiajs/vue3';
import { useUnsavedSource } from '@/composables/useUnsavedGuard';

const props = defineProps<{
    inspectionId: string;
    forms?: Record<string, any>[];
    open: string[];
}>();

const emit = defineEmits<{ 'update:open': [value: string[]] }>();

const openForms = computed({
    get: () => props.open,
    set: (value: string[]) => emit('update:open', value ?? []),
});

/** Answer choices for the yes/no family. Everything else gets a text control. */
const choicesFor = (type: string): { label: string; value: boolean | null }[] | null => {
    switch (type) {
        case 'pass_fail':
            return [{ label: 'Pass', value: true }, { label: 'Fail', value: false }];
        case 'yes_no_na':
            return [{ label: 'Yes', value: true }, { label: 'No', value: false }, { label: 'N/A', value: null }];
        case 'yes_no':
        case 'boolean':
            return [{ label: 'Yes', value: true }, { label: 'No', value: false }];
        default:
            return null;
    }
};

const inputTypeFor = (type: string) => (type === 'date' ? 'date' : type === 'number' ? 'number' : 'text');

/** Fields the API fills from an upload or an external link — read-only here. */
const isReadOnly = (type: string) => type === 'file' || type === 'link';

// One draft per field, rebuilt from the payload after every save.
const drafts = ref<Record<string, any>>({});
watch(() => props.forms, (list) => {
    const next: Record<string, any> = {};
    (list ?? []).forEach((form) => {
        (form.items ?? []).forEach((item: Record<string, any>) => {
            next[item.id] = item.value ?? '';
        });
    });
    drafts.value = next;
}, { immediate: true });

const busy = ref<Record<string, boolean>>({});

const answer = (fieldId: string, value: string | number | boolean | null) => {
    busy.value[fieldId] = true;
    router.patch(route('inspections.compliance.update', { inspectionId: props.inspectionId, fieldId }), { value }, {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => { busy.value[fieldId] = false; },
    });
};

const saveDraft = (item: Record<string, any>) => {
    const draft = drafts.value[item.id];
    answer(item.id, draft === '' ? null : item.item_type === 'number' ? Number(draft) : draft);
};

const isDirty = (item: Record<string, any>) => String(drafts.value[item.id] ?? '') !== String(item.value ?? '');

// Yes/no answers save on click, so only the typed field types can go stale —
// and a closed form hides them, hence the count on the trigger.
const dirtyCount = (form: Record<string, any>) => (form.items ?? []).filter((item: Record<string, any>) => isDirty(item)).length;

const dirtyTotal = computed(() => (props.forms ?? []).reduce((sum, form) => sum + dirtyCount(form), 0));
useUnsavedSource(dirtyTotal);

/** unanswered → outstanding; answered → compliant or not, per the API's own verdict. */
const statusOf = (item: Record<string, any>) => {
    if (!item.has_response) return item.is_required ? 'required' : 'unanswered';
    return item.is_compliant ? 'compliant' : 'issue';
};

const statusIcon: Record<string, any> = {
    compliant: CircleCheck,
    issue: CircleAlert,
    required: CircleAlert,
    unanswered: CircleDashed,
};

const statusColor: Record<string, string> = {
    compliant: 'text-emerald-500',
    issue: 'text-red-500',
    required: 'text-amber-500',
    unanswered: 'text-muted-foreground',
};

/** Items keep their form order; grouping preserves the section they came from. */
const sectionsOf = (form: Record<string, any>) => {
    const groups: { id: string; name: string; items: Record<string, any>[] }[] = [];
    (form.items ?? []).forEach((item: Record<string, any>) => {
        const key = String(item.section_id ?? item.section_name ?? '');
        const existing = groups.find((g) => g.id === key);
        if (existing) existing.items.push(item);
        else groups.push({ id: key, name: item.section_name ?? 'Questions', items: [item] });
    });
    return groups;
};

const outstanding = (form: Record<string, any>) => (form.total_items ?? 0) - (form.completed_items ?? 0);

const totals = computed(() => (props.forms ?? []).reduce((acc, form) => ({
    total: acc.total + (form.total_items ?? 0),
    completed: acc.completed + (form.completed_items ?? 0),
}), { total: 0, completed: 0 }));
</script>

<template>
    <Card>
        <CardHeader class="pb-3">
            <div class="flex items-center gap-2">
                <CardTitle>Compliance</CardTitle>
                <Badge variant="secondary">{{ forms?.length ?? 0 }} form{{ forms?.length === 1 ? '' : 's' }}</Badge>
                <Badge v-if="totals.total" :variant="totals.completed === totals.total ? 'default' : 'outline'">
                    {{ totals.completed }} / {{ totals.total }} answered
                </Badge>
            </div>
        </CardHeader>
        <CardContent>
            <Accordion v-if="forms?.length" v-model="openForms" type="multiple" class="rounded-lg border">
                <AccordionItem v-for="form in forms" :key="form.form_id" :value="String(form.form_id)">
                    <AccordionTrigger>
                        <div class="flex min-w-0 flex-1 items-center gap-2">
                            <span class="truncate">{{ form.form_name }}</span>
                            <Badge v-if="form.category" variant="outline" class="shrink-0 text-[10px] capitalize">{{ form.category }}</Badge>
                            <UnsavedBadge :count="dirtyCount(form)" />
                            <span class="ml-auto shrink-0 pr-2 text-xs font-normal text-muted-foreground">
                                {{ form.completed_items ?? 0 }} / {{ form.total_items ?? 0 }}
                                <span v-if="outstanding(form) > 0" class="text-amber-500">· {{ outstanding(form) }} outstanding</span>
                            </span>
                        </div>
                    </AccordionTrigger>
                    <AccordionContent>
                        <div v-if="!form.items?.length" class="py-3 text-sm text-muted-foreground">
                            This form has no questions.
                        </div>

                        <div v-for="section in sectionsOf(form)" :key="section.id" class="mb-4 last:mb-0">
                            <p class="mb-2 text-xs font-semibold tracking-wide text-muted-foreground uppercase">{{ section.name }}</p>
                            <div class="space-y-2">
                                <div
                                    v-for="item in section.items"
                                    :key="item.id"
                                    class="flex flex-col gap-2 rounded-md border p-2.5 sm:flex-row sm:items-center sm:justify-between"
                                >
                                    <div class="flex min-w-0 items-start gap-2">
                                        <component
                                            :is="statusIcon[statusOf(item)]"
                                            class="mt-0.5 h-4 w-4 shrink-0"
                                            :class="statusColor[statusOf(item)]"
                                        />
                                        <div class="min-w-0">
                                            <p class="text-sm">
                                                {{ item.item_name }}
                                                <span v-if="item.is_required" class="text-red-500">*</span>
                                            </p>
                                            <p v-if="item.help_text" class="text-xs text-muted-foreground">{{ item.help_text }}</p>
                                        </div>
                                    </div>

                                    <div class="flex shrink-0 items-center gap-1.5 sm:pl-4">
                                        <!-- Yes / No / Pass / Fail -->
                                        <template v-if="choicesFor(item.item_type)">
                                            <Button
                                                v-for="choice in choicesFor(item.item_type)"
                                                :key="String(choice.value)"
                                                size="sm"
                                                class="h-7 min-w-14 text-xs"
                                                :variant="item.has_response && item.value === choice.value ? 'default' : 'outline'"
                                                :disabled="busy[item.id]"
                                                @click="answer(item.id, choice.value)"
                                            >
                                                {{ choice.label }}
                                            </Button>
                                        </template>

                                        <!-- Uploads and links are set elsewhere; show what the API holds -->
                                        <template v-else-if="isReadOnly(item.item_type)">
                                            <a
                                                v-if="item.display_value"
                                                :href="item.display_value"
                                                target="_blank"
                                                rel="noopener"
                                                class="flex items-center gap-1 text-xs text-muted-foreground underline"
                                            >
                                                <ExternalLink class="h-3 w-3" /> View
                                            </a>
                                            <span v-else class="text-xs text-muted-foreground">Not provided</span>
                                        </template>

                                        <!-- Free text -->
                                        <template v-else-if="item.item_type === 'textarea'">
                                            <Textarea v-model="drafts[item.id]" class="min-h-9 w-64 py-1.5 text-sm" placeholder="Not answered" />
                                            <Button v-if="isDirty(item)" size="sm" class="h-7 text-xs" :disabled="busy[item.id]" @click="saveDraft(item)">
                                                <Save class="h-3 w-3" />
                                            </Button>
                                        </template>

                                        <template v-else>
                                            <Input
                                                v-model="drafts[item.id]"
                                                :type="inputTypeFor(item.item_type)"
                                                class="h-8 w-48 text-sm"
                                                placeholder="Not answered"
                                                @keyup.enter="saveDraft(item)"
                                            />
                                            <Button v-if="isDirty(item)" size="sm" class="h-7 text-xs" :disabled="busy[item.id]" @click="saveDraft(item)">
                                                <Save class="h-3 w-3" />
                                            </Button>
                                        </template>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </AccordionContent>
                </AccordionItem>
            </Accordion>

            <p v-else class="py-6 text-center text-sm text-muted-foreground">
                No compliance forms attached to this inspection.
            </p>
        </CardContent>
    </Card>
</template>
