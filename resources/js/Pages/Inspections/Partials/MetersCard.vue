<script setup lang="ts">
/**
 * Meter readings — Inventorai::meterReadings().
 *
 * Readings arrive on the inspection payload via the `meterReadings` include;
 * writes go one at a time through the app's own routes, which wrap
 * meterReadings()->create/update/delete.
 *
 * Prepaid meters carry a credit balance alongside the reading, so the balance
 * field only appears once "Prepaid" is on.
 */
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/components/ui/input-group';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Switch } from '@/components/ui/switch';
import { Droplet, Flame, Gauge, Plus, Save, Trash2, Zap } from '@lucide/vue';
import { router } from '@inertiajs/vue3';
import { useDrafts } from '@/composables/useDrafts';
import { useUnsavedSource } from '@/composables/useUnsavedGuard';
import UnsavedBadge from './UnsavedBadge.vue';

const props = defineProps<{
    inspectionId: string;
    readings?: Record<string, any>[];
}>();

const meterTypes = ['gas', 'electricity', 'water', 'other'] as const;

const typeIcon: Record<string, any> = {
    gas: Flame,
    electricity: Zap,
    water: Droplet,
    other: Gauge,
};

const typeColor: Record<string, string> = {
    gas: 'text-orange-500',
    electricity: 'text-amber-500',
    water: 'text-blue-500',
    other: 'text-muted-foreground',
};

const blank = () => ({
    meter_type: 'electricity',
    meter_location: '',
    meter_serial: '',
    reading: '',
    reading_unit: '',
    meter_balance: '',
    is_prepaid: false,
    notes: '',
});

const { drafts, isDirty, dirtyCount } = useDrafts(() => props.readings, (r) => ({
    meter_type: r.meter_type ?? 'other',
    meter_location: r.meter_location ?? '',
    meter_serial: r.meter_serial ?? '',
    reading: r.reading ?? '',
    reading_unit: r.reading_unit ?? '',
    meter_balance: r.meter_balance ?? '',
    is_prepaid: Boolean(r.is_prepaid),
    notes: r.notes ?? '',
}));

useUnsavedSource(dirtyCount);

const busy = ref<Record<string, boolean>>({});
const adding = ref(false);
const newReading = ref(blank());

const payload = (draft: Record<string, any>) => ({
    ...draft,
    reading: draft.reading === '' ? null : Number(draft.reading),
    meter_balance: draft.meter_balance === '' ? null : Number(draft.meter_balance),
});

const save = (id: string) => {
    busy.value[id] = true;
    router.patch(route('inspections.meters.update', { inspectionId: props.inspectionId, meterId: id }), payload(drafts.value[id]), {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => { busy.value[id] = false; },
    });
};

const remove = (id: string) => {
    busy.value[id] = true;
    router.delete(route('inspections.meters.destroy', { inspectionId: props.inspectionId, meterId: id }), {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => { busy.value[id] = false; },
    });
};

const create = () => {
    busy.value.new = true;
    router.post(route('inspections.meters.store', { inspectionId: props.inspectionId }), payload(newReading.value), {
        preserveScroll: true,
        preserveState: true,
        onSuccess: () => { newReading.value = blank(); adding.value = false; },
        onFinish: () => { busy.value.new = false; },
    });
};

const prepaidCount = computed(() => (props.readings ?? []).filter((r) => r.is_prepaid).length);

/** `captured_at` comes back as an ISO string; only the inspection's own dates are pre-formatted. */
const capturedOn = (value?: string | null) => {
    if (!value) return null;
    const date = new Date(value);
    return Number.isNaN(date.getTime()) ? null : date.toLocaleDateString('en-GB', { day: 'numeric', month: 'short', year: 'numeric' });
};
</script>

<template>
    <Card>
        <CardHeader class="pb-3">
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2">
                    <CardTitle>Meters</CardTitle>
                    <Badge variant="secondary">{{ readings?.length ?? 0 }}</Badge>
                    <Badge v-if="prepaidCount" variant="outline">{{ prepaidCount }} prepaid</Badge>
                    <UnsavedBadge :count="dirtyCount" />
                </div>
                <Button size="sm" variant="outline" @click="adding = !adding">
                    <Plus class="mr-1.5 h-3 w-3" /> Add reading
                </Button>
            </div>
        </CardHeader>
        <CardContent class="space-y-3">
            <!-- New reading -->
            <div v-if="adding" class="rounded-lg border border-dashed p-3">
                <div class="grid gap-2 sm:grid-cols-2">
                    <InputGroup>
                        <InputGroupAddon class="w-28 shrink-0">Type</InputGroupAddon>
                        <Select v-model="newReading.meter_type">
                            <SelectTrigger class="w-full rounded-none border-0 shadow-none focus:ring-0"><SelectValue /></SelectTrigger>
                            <SelectContent><SelectItem v-for="t in meterTypes" :key="t" :value="t" class="capitalize">{{ t }}</SelectItem></SelectContent>
                        </Select>
                    </InputGroup>
                    <InputGroup>
                        <InputGroupAddon class="w-28 shrink-0">Location</InputGroupAddon>
                        <InputGroupInput v-model="newReading.meter_location" placeholder="Under the stairs" />
                    </InputGroup>
                    <InputGroup>
                        <InputGroupAddon class="w-28 shrink-0">Serial</InputGroupAddon>
                        <InputGroupInput v-model="newReading.meter_serial" placeholder="Meter serial number" />
                    </InputGroup>
                    <InputGroup>
                        <InputGroupAddon class="w-28 shrink-0">Reading</InputGroupAddon>
                        <InputGroupInput v-model="newReading.reading" type="number" step="0.01" placeholder="0.00" />
                        <InputGroupAddon align="inline-end" class="pr-2">
                            <Input v-model="newReading.reading_unit" placeholder="kWh" class="h-7 w-20 text-xs" />
                        </InputGroupAddon>
                    </InputGroup>
                    <InputGroup class="sm:col-span-2">
                        <InputGroupAddon class="w-28 shrink-0">Notes</InputGroupAddon>
                        <InputGroupInput v-model="newReading.notes" placeholder="Anything worth recording" />
                    </InputGroup>
                </div>
                <div class="mt-3 flex items-center justify-between">
                    <label class="flex cursor-pointer items-center gap-2 text-sm">
                        <Switch v-model="newReading.is_prepaid" />
                        <span>Prepaid</span>
                    </label>
                    <div class="flex items-center gap-2">
                        <InputGroup v-if="newReading.is_prepaid" class="w-56">
                            <InputGroupAddon class="w-20 shrink-0">Balance</InputGroupAddon>
                            <InputGroupInput v-model="newReading.meter_balance" type="number" step="0.01" placeholder="0.00" />
                        </InputGroup>
                        <Button size="sm" variant="ghost" @click="adding = false">Cancel</Button>
                        <Button size="sm" :disabled="busy.new" @click="create">
                            <Save class="mr-1.5 h-3 w-3" /> Add
                        </Button>
                    </div>
                </div>
            </div>

            <!-- Existing readings -->
            <div v-for="reading in readings" :key="reading.id" class="rounded-lg border p-3">
                <div class="mb-2 flex items-center justify-between">
                    <div class="flex items-center gap-2">
                        <component :is="typeIcon[reading.meter_type] ?? Gauge" class="h-4 w-4" :class="typeColor[reading.meter_type] ?? 'text-muted-foreground'" />
                        <p class="text-sm font-semibold capitalize">{{ reading.meter_type }}</p>
                        <Badge v-if="reading.is_prepaid" variant="outline" class="text-[10px]">Prepaid</Badge>
                        <span v-if="reading.meter_location" class="text-xs text-muted-foreground">{{ reading.meter_location }}</span>
                        <span v-if="capturedOn(reading.captured_at)" class="text-xs text-muted-foreground">· {{ capturedOn(reading.captured_at) }}</span>
                    </div>
                    <div class="flex items-center gap-1.5">
                        <UnsavedBadge v-if="isDirty(reading.id)" />
                        <Button variant="outline" size="sm" class="h-7 text-xs" :disabled="busy[reading.id]" @click="save(reading.id)">
                            <Save class="mr-1.5 h-3 w-3" /> Save
                        </Button>
                        <Button variant="ghost" size="sm" class="h-7 text-xs text-destructive hover:text-destructive" :disabled="busy[reading.id]" @click="remove(reading.id)">
                            <Trash2 class="h-3 w-3" />
                        </Button>
                    </div>
                </div>

                <div v-if="drafts[reading.id]" class="grid gap-2 sm:grid-cols-2">
                    <InputGroup>
                        <InputGroupAddon class="w-28 shrink-0">Type</InputGroupAddon>
                        <Select v-model="drafts[reading.id].meter_type">
                            <SelectTrigger class="w-full rounded-none border-0 shadow-none focus:ring-0"><SelectValue /></SelectTrigger>
                            <SelectContent><SelectItem v-for="t in meterTypes" :key="t" :value="t" class="capitalize">{{ t }}</SelectItem></SelectContent>
                        </Select>
                    </InputGroup>
                    <InputGroup>
                        <InputGroupAddon class="w-28 shrink-0">Location</InputGroupAddon>
                        <InputGroupInput v-model="drafts[reading.id].meter_location" placeholder="Not set" />
                    </InputGroup>
                    <InputGroup>
                        <InputGroupAddon class="w-28 shrink-0">Serial</InputGroupAddon>
                        <InputGroupInput v-model="drafts[reading.id].meter_serial" placeholder="Not set" />
                    </InputGroup>
                    <InputGroup>
                        <InputGroupAddon class="w-28 shrink-0">Reading</InputGroupAddon>
                        <InputGroupInput v-model="drafts[reading.id].reading" type="number" step="0.01" placeholder="0.00" />
                        <InputGroupAddon align="inline-end" class="pr-2">
                            <Input v-model="drafts[reading.id].reading_unit" placeholder="unit" class="h-7 w-20 text-xs" />
                        </InputGroupAddon>
                    </InputGroup>
                    <div class="flex items-center gap-3">
                        <label class="flex cursor-pointer items-center gap-2 text-sm">
                            <Switch v-model="drafts[reading.id].is_prepaid" />
                            <span>Prepaid</span>
                        </label>
                        <InputGroup v-if="drafts[reading.id].is_prepaid" class="flex-1">
                            <InputGroupAddon class="w-20 shrink-0">Balance</InputGroupAddon>
                            <InputGroupInput v-model="drafts[reading.id].meter_balance" type="number" step="0.01" placeholder="0.00" />
                        </InputGroup>
                    </div>
                    <InputGroup>
                        <InputGroupAddon class="w-28 shrink-0">Notes</InputGroupAddon>
                        <InputGroupInput v-model="drafts[reading.id].notes" placeholder="Not set" />
                    </InputGroup>
                </div>
            </div>

            <p v-if="!readings?.length && !adding" class="py-6 text-center text-sm text-muted-foreground">
                No meter readings recorded.
            </p>
        </CardContent>
    </Card>
</template>
