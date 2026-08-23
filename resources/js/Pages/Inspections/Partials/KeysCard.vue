<script setup lang="ts">
/**
 * Keys and fobs — Inventorai::keysFobs().
 *
 * A typed count of what was handed over. `quantity` is what matters at
 * check-in/check-out time, so the total across every row is shown up front.
 */
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/components/ui/input-group';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { KeyRound, Plus, Save, Trash2 } from '@lucide/vue';
import { router } from '@inertiajs/vue3';
import { useDrafts } from '@/composables/useDrafts';
import { useUnsavedSource } from '@/composables/useUnsavedGuard';
import UnsavedBadge from './UnsavedBadge.vue';

const props = defineProps<{
    inspectionId: string;
    keys?: Record<string, any>[];
}>();

const itemTypes = [
    'front_door_key', 'back_door_key', 'mailbox_key', 'window_key',
    'entry_fob', 'garage_remote', 'gate_remote', 'other',
] as const;

const humanize = (value: string) => value.replace(/_/g, ' ');

const blank = () => ({ item_type: 'front_door_key', description: '', quantity: 1, notes: '' });

const { drafts, isDirty, dirtyCount } = useDrafts(() => props.keys, (k) => ({
    item_type: k.item_type ?? 'other',
    description: k.description ?? '',
    quantity: k.quantity ?? 1,
    notes: k.notes ?? '',
}));

useUnsavedSource(dirtyCount);

const busy = ref<Record<string, boolean>>({});
const adding = ref(false);
const newKey = ref(blank());

const payload = (draft: Record<string, any>) => ({ ...draft, quantity: Number(draft.quantity) || 1 });

const save = (id: string) => {
    busy.value[id] = true;
    router.patch(route('inspections.keys.update', { inspectionId: props.inspectionId, keyId: id }), payload(drafts.value[id]), {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => { busy.value[id] = false; },
    });
};

const remove = (id: string) => {
    busy.value[id] = true;
    router.delete(route('inspections.keys.destroy', { inspectionId: props.inspectionId, keyId: id }), {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => { busy.value[id] = false; },
    });
};

const create = () => {
    busy.value.new = true;
    router.post(route('inspections.keys.store', { inspectionId: props.inspectionId }), payload(newKey.value), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { newKey.value = blank(); adding.value = false; },
        onFinish: () => { busy.value.new = false; },
    });
};

const total = computed(() => (props.keys ?? []).reduce((sum, k) => sum + (Number(k.quantity) || 0), 0));
</script>

<template>
    <Card>
        <CardHeader class="pb-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <CardTitle>Keys &amp; Fobs</CardTitle>
                    <Badge variant="secondary">{{ total }} item{{ total === 1 ? '' : 's' }}</Badge>
                    <UnsavedBadge :count="dirtyCount" />
                </div>
                <Button size="sm" variant="outline" @click="adding = !adding">
                    <Plus class="mr-1.5 h-3 w-3" /> Add key
                </Button>
            </div>
        </CardHeader>
        <CardContent class="space-y-2">
            <!-- New key -->
            <div v-if="adding" class="rounded-lg border border-dashed p-3">
                <div class="grid gap-2 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_7rem]">
                    <InputGroup>
                        <InputGroupAddon class="w-24 shrink-0">Type</InputGroupAddon>
                        <Select v-model="newKey.item_type">
                            <SelectTrigger class="w-full rounded-none border-0 shadow-none focus:ring-0"><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="t in itemTypes" :key="t" :value="t" class="capitalize">{{ humanize(t) }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </InputGroup>
                    <InputGroup>
                        <InputGroupAddon class="w-24 shrink-0">Description</InputGroupAddon>
                        <InputGroupInput v-model="newKey.description" placeholder="Yale, brass" />
                    </InputGroup>
                    <InputGroup>
                        <InputGroupAddon class="w-12 shrink-0">Qty</InputGroupAddon>
                        <InputGroupInput v-model="newKey.quantity" type="number" min="1" />
                    </InputGroup>
                </div>
                <div class="mt-2 flex items-center gap-2">
                    <InputGroup class="flex-1">
                        <InputGroupAddon class="w-24 shrink-0">Notes</InputGroupAddon>
                        <InputGroupInput v-model="newKey.notes" placeholder="Anything worth recording" />
                    </InputGroup>
                    <Button size="sm" variant="ghost" @click="adding = false">Cancel</Button>
                    <Button size="sm" :disabled="busy.new" @click="create">
                        <Save class="mr-1.5 h-3 w-3" /> Add
                    </Button>
                </div>
            </div>

            <!-- Existing keys -->
            <div v-for="key in keys" :key="key.id" class="rounded-lg border p-3">
                <div v-if="drafts[key.id]" class="grid items-start gap-2 lg:grid-cols-[minmax(0,1fr)_minmax(0,1fr)_6rem_auto]">
                    <InputGroup>
                        <InputGroupAddon class="w-24 shrink-0"><KeyRound class="h-3.5 w-3.5" /></InputGroupAddon>
                        <Select v-model="drafts[key.id].item_type">
                            <SelectTrigger class="w-full rounded-none border-0 shadow-none focus:ring-0"><SelectValue /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="t in itemTypes" :key="t" :value="t" class="capitalize">{{ humanize(t) }}</SelectItem>
                            </SelectContent>
                        </Select>
                    </InputGroup>
                    <InputGroup>
                        <InputGroupAddon class="w-24 shrink-0">Description</InputGroupAddon>
                        <InputGroupInput v-model="drafts[key.id].description" placeholder="Not set" />
                    </InputGroup>
                    <InputGroup>
                        <InputGroupAddon class="w-12 shrink-0">Qty</InputGroupAddon>
                        <InputGroupInput v-model="drafts[key.id].quantity" type="number" min="1" />
                    </InputGroup>
                    <div class="flex items-center gap-1.5">
                        <UnsavedBadge v-if="isDirty(key.id)" />
                        <Button variant="outline" size="sm" class="h-9 text-xs" :disabled="busy[key.id]" @click="save(key.id)">
                            <Save class="mr-1.5 h-3 w-3" /> Save
                        </Button>
                        <Button variant="ghost" size="sm" class="h-9 text-xs text-destructive hover:text-destructive" :disabled="busy[key.id]" @click="remove(key.id)">
                            <Trash2 class="h-3 w-3" />
                        </Button>
                    </div>
                    <InputGroup class="lg:col-span-4">
                        <InputGroupAddon class="w-24 shrink-0">Notes</InputGroupAddon>
                        <InputGroupInput v-model="drafts[key.id].notes" placeholder="Not set" />
                    </InputGroup>
                </div>
            </div>

            <p v-if="!keys?.length && !adding" class="py-6 text-center text-sm text-muted-foreground">
                No keys or fobs recorded.
            </p>
        </CardContent>
    </Card>
</template>
