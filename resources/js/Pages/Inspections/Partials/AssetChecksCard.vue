<script setup lang="ts">
/**
 * Alarms & safety equipment — Inventorai::assetChecks().
 *
 * Checks hang off the property's assets rather than off an inspection area,
 * so the API groups them by asset type (smoke alarm, CO alarm, and so on) and
 * carries the asset's make/model/serial through with each check. Only the
 * result fields are writable; the asset itself belongs to the property.
 */
import { computed, ref } from 'vue';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/components/ui/input-group';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { MapPin, Save, ShieldCheck } from '@lucide/vue';
import { router } from '@inertiajs/vue3';
import { useDrafts } from '@/composables/useDrafts';
import { useUnsavedSource } from '@/composables/useUnsavedGuard';
import UnsavedBadge from './UnsavedBadge.vue';

const props = defineProps<{
    inspectionId: string;
    groups?: Record<string, any>[];
}>();

const emit = defineEmits<{ openPhoto: [url: string] }>();

const testedOptions = ['yes', 'no', 'not_accessible'];
const resultOptions = ['pass', 'fail', 'na'];
const conditionOptions = ['good', 'fair', 'poor', 'replace'];

const humanize = (value: string) => value.replace(/_/g, ' ');

const checks = computed(() => (props.groups ?? []).flatMap((g) => g.checks ?? []));

const { drafts, isDirty, dirtyCount } = useDrafts(() => checks.value, (c) => ({
    tested: c.tested ?? '',
    test_result: c.test_result ?? '',
    condition: c.condition ?? '',
    notes: c.notes ?? '',
}));

useUnsavedSource(dirtyCount);

const busy = ref<Record<string, boolean>>({});

const save = (id: string) => {
    busy.value[id] = true;
    router.patch(route('inspections.assetChecks.update', { inspectionId: props.inspectionId, checkId: id }), drafts.value[id], {
        preserveScroll: true,
        preserveState: true,
        onFinish: () => { busy.value[id] = false; },
    });
};

const resultVariant = (result: string | null) => (result === 'pass' ? 'default' : result === 'fail' ? 'destructive' : 'outline');

const spec = (check: Record<string, any>) => [check.make, check.model, check.serial_number].filter(Boolean).join(' · ');
</script>

<template>
    <Card>
        <CardHeader class="pb-3">
            <div class="flex items-center gap-2">
                <CardTitle>Alarms &amp; Safety</CardTitle>
                <Badge variant="secondary">{{ checks.length }}</Badge>
                <UnsavedBadge :count="dirtyCount" />
            </div>
        </CardHeader>
        <CardContent class="space-y-4">
            <div v-for="group in groups" :key="group.asset_type">
                <div class="mb-2 flex items-center gap-2">
                    <ShieldCheck class="h-4 w-4 text-muted-foreground" />
                    <p class="text-sm font-semibold capitalize">{{ group.asset_type_label ?? humanize(group.asset_type ?? '') }}</p>
                    <Badge variant="outline" class="text-[10px]">{{ group.tested_count ?? 0 }} / {{ group.total ?? 0 }} tested</Badge>
                    <Badge v-if="group.passed_count" variant="outline" class="text-[10px] text-emerald-600">{{ group.passed_count }} passed</Badge>
                </div>

                <div class="space-y-2">
                    <div v-for="check in group.checks" :key="check.id" class="rounded-lg border p-3">
                        <div class="mb-2 flex flex-wrap items-center gap-2">
                            <span v-if="check.location_description" class="flex items-center gap-1 text-sm font-medium">
                                <MapPin class="h-3 w-3 shrink-0 text-muted-foreground" />{{ check.location_description }}
                            </span>
                            <span v-if="spec(check)" class="text-xs text-muted-foreground">{{ spec(check) }}</span>
                            <Badge v-if="check.test_result" :variant="resultVariant(check.test_result)" class="text-[10px] uppercase">{{ check.test_result }}</Badge>
                            <UnsavedBadge v-if="isDirty(check.id)" class="ml-auto" />
                            <Button
                                variant="outline"
                                size="sm"
                                :class="isDirty(check.id) ? 'h-7 text-xs' : 'ml-auto h-7 text-xs'"
                                :disabled="busy[check.id]"
                                @click="save(check.id)"
                            >
                                <Save class="mr-1.5 h-3 w-3" /> Save
                            </Button>
                        </div>

                        <div v-if="drafts[check.id]" class="grid gap-2 sm:grid-cols-3">
                            <InputGroup>
                                <InputGroupAddon class="w-24 shrink-0">Tested</InputGroupAddon>
                                <Select v-model="drafts[check.id].tested">
                                    <SelectTrigger class="w-full rounded-none border-0 shadow-none focus:ring-0"><SelectValue placeholder="—" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="o in testedOptions" :key="o" :value="o" class="capitalize">{{ humanize(o) }}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </InputGroup>
                            <InputGroup>
                                <InputGroupAddon class="w-24 shrink-0">Result</InputGroupAddon>
                                <Select v-model="drafts[check.id].test_result">
                                    <SelectTrigger class="w-full rounded-none border-0 shadow-none focus:ring-0"><SelectValue placeholder="—" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="o in resultOptions" :key="o" :value="o" class="uppercase">{{ o }}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </InputGroup>
                            <InputGroup>
                                <InputGroupAddon class="w-24 shrink-0">Condition</InputGroupAddon>
                                <Select v-model="drafts[check.id].condition">
                                    <SelectTrigger class="w-full rounded-none border-0 shadow-none focus:ring-0"><SelectValue placeholder="—" /></SelectTrigger>
                                    <SelectContent>
                                        <SelectItem v-for="o in conditionOptions" :key="o" :value="o" class="capitalize">{{ o }}</SelectItem>
                                    </SelectContent>
                                </Select>
                            </InputGroup>
                            <InputGroup class="sm:col-span-3">
                                <InputGroupAddon class="w-24 shrink-0">Notes</InputGroupAddon>
                                <InputGroupInput v-model="drafts[check.id].notes" placeholder="Not set" />
                            </InputGroup>
                        </div>

                        <div v-if="check.photos?.length" class="mt-2 flex flex-wrap gap-1.5">
                            <img
                                v-for="photo in check.photos"
                                :key="photo.id"
                                :src="photo.thumbnail_url ?? photo.url"
                                class="h-14 w-14 cursor-pointer rounded-md object-cover transition-opacity hover:opacity-80"
                                @click="emit('openPhoto', photo.url)"
                            />
                        </div>
                    </div>
                </div>
            </div>

            <p v-if="!checks.length" class="py-6 text-center text-sm text-muted-foreground">
                No alarm or safety checks on this inspection.
            </p>
        </CardContent>
    </Card>
</template>
