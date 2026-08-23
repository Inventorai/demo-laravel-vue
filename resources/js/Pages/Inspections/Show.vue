<script setup lang="ts">
/**
 * Inspection editor.
 *
 * Everything on this page arrives in the single GET /inspections/{id} call
 * made by InspectionController@show — areas, items, meters, keys, compliance
 * and asset checks all come from that one payload's `include`. Writes are the
 * opposite: each record type goes back through its own SDK resource, one
 * record at a time.
 */
import { computed, onMounted, onUnmounted, ref } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Accordion, AccordionContent, AccordionItem, AccordionTrigger } from '@/components/ui/accordion';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Dialog, DialogContent } from '@/components/ui/dialog';
import { InputGroup, InputGroupAddon } from '@/components/ui/input-group';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Separator } from '@/components/ui/separator';
import { Switch } from '@/components/ui/switch';
import AssetChecksCard from './Partials/AssetChecksCard.vue';
import ComplianceCard from './Partials/ComplianceCard.vue';
import KeysCard from './Partials/KeysCard.vue';
import MetersCard from './Partials/MetersCard.vue';
import PhotoGrid from './Partials/PhotoGrid.vue';
import PhraseInput from './Partials/PhraseInput.vue';
import UnsavedBadge from './Partials/UnsavedBadge.vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import { ArrowLeft, ChevronLeft, ChevronRight, Images, MapPin, Save } from '@lucide/vue';
import { useEcho } from '@/composables/useEcho';
import { useUnsavedGuard } from '@/composables/useUnsavedGuard';

const props = defineProps<{
    inspection: Record<string, any>;
}>();

const humanize = (value: string) => value.replace(/_/g, ' ');
const conditionOptions = ['poor', 'fair', 'good', 'excellent'];
const cleanlinessOptions = ['dirty', 'fair', 'clean', 'spotless'];

const areas = computed(() => props.inspection.areas ?? []);

// --- Accordion state -------------------------------------------------------
// Areas, items and compliance forms are each an independent multi-accordion.
// The "Expand all" switch drives all three at once: on if everything that can
// be open is open, and flipping it sets or clears every panel.
const areaIds = computed<string[]>(() => areas.value.map((a: any) => String(a.id)));
const itemIds = computed<string[]>(() => areas.value.flatMap((a: any) => (a.items ?? []).map((i: any) => String(i.id))));
const formIds = computed<string[]>(() => (props.inspection.compliance_forms ?? []).map((f: any) => String(f.form_id)));

// A long inspection opens on its first area only; compliance forms are few
// and the whole point of them is the outstanding count, so they start open.
const openAreas = ref<string[]>(areaIds.value.slice(0, 1));
const openItems = ref<string[]>([]);
const openForms = ref<string[]>([...formIds.value]);

const expandAll = computed<boolean>({
    get: () => {
        const total = areaIds.value.length + itemIds.value.length + formIds.value.length;
        const open = (openAreas.value?.length ?? 0) + (openItems.value?.length ?? 0) + (openForms.value?.length ?? 0);
        return total > 0 && open === total;
    },
    set: (value) => {
        openAreas.value = value ? [...areaIds.value] : [];
        openItems.value = value ? [...itemIds.value] : [];
        openForms.value = value ? [...formIds.value] : [];
    },
});

// --- Lightbox --------------------------------------------------------------
// Every photo on the page in one flat list so prev/next walks the lot.
const allPhotos = computed<string[]>(() => {
    const photos: string[] = [];
    areas.value.forEach((area: any) => {
        (area.photos ?? []).forEach((p: any) => photos.push(p.original_url ?? p.url));
        (area.items ?? []).forEach((item: any) => {
            (item.photos ?? []).forEach((p: any) => photos.push(p.original_url ?? p.url));
        });
    });
    (props.inspection.asset_checks ?? []).forEach((group: any) => {
        (group.checks ?? []).forEach((check: any) => {
            (check.photos ?? []).forEach((p: any) => photos.push(p.url));
        });
    });
    return photos;
});

const lightboxOpen = ref(false);
const lightboxIndex = ref(0);
const lightboxSrc = computed(() => allPhotos.value[lightboxIndex.value] ?? '');

const openLightbox = (url: string) => {
    const index = allPhotos.value.indexOf(url);
    lightboxIndex.value = index >= 0 ? index : 0;
    lightboxOpen.value = true;
};
const lightboxPrev = () => { if (lightboxIndex.value > 0) lightboxIndex.value--; };
const lightboxNext = () => { if (lightboxIndex.value < allPhotos.value.length - 1) lightboxIndex.value++; };
const onLightboxKeydown = (e: KeyboardEvent) => {
    if (e.key === 'ArrowLeft') lightboxPrev();
    else if (e.key === 'ArrowRight') lightboxNext();
};

// --- Areas and items -------------------------------------------------------
// Saves keep the page's own state (open panels, drafts elsewhere) rather than
// remounting, so a save in one panel doesn't collapse the rest of the page.
const visitOptions = { preserveScroll: true, preserveState: true } as const;

interface AreaFields { condition: string; cleanliness: string; description: string }

const areaForms = Object.fromEntries(
    areas.value.map((area: any) => [
        area.id,
        useForm({ condition: area.condition ?? '', cleanliness: area.cleanliness ?? '', description: area.notes ?? '' }),
    ]),
);

const saveArea = (areaId: string) => {
    // The API calls this field `notes`; the UI calls it description. Going
    // through the form rather than router.patch is what lets Inertia clear
    // `isDirty` and drive `processing` when the save lands.
    areaForms[areaId]
        .transform((data: AreaFields) => ({ condition: data.condition, cleanliness: data.cleanliness, notes: data.description }))
        .patch(route('inspections.areas.update', { inspectionId: props.inspection.id, areaId }), visitOptions);
};

const itemForms: Record<string, any> = {};
areas.value.forEach((area: any) => {
    (area.items ?? []).forEach((item: any) => {
        itemForms[item.id] = useForm({ condition: item.condition ?? '', cleanliness: item.cleanliness ?? '', description: item.description ?? '' });
    });
});

const saveItem = (itemId: string) => {
    itemForms[itemId].patch(route('inspections.items.update', { inspectionId: props.inspection.id, itemId }), visitOptions);
};

// Nothing saves as you type, and a closed panel hides whatever is pending
// inside it — so every level reports what it is holding. Inertia clears
// `isDirty` itself once a form's own save succeeds.
const dirtyAreaIds = computed(() => new Set(
    Object.entries(areaForms).filter(([, form]) => form.isDirty).map(([id]) => id),
));
const dirtyItemIds = computed(() => new Set(
    Object.entries(itemForms).filter(([, form]) => form.isDirty).map(([id]) => id),
));

const unsavedInArea = (area: any) =>
    (dirtyAreaIds.value.has(String(area.id)) ? 1 : 0)
    + (area.items ?? []).filter((item: any) => dirtyItemIds.value.has(String(item.id))).length;

const unsavedTotal = computed(() => dirtyAreaIds.value.size + dirtyItemIds.value.size);

// Markers only help while you are looking at the page; the guard covers
// leaving it. The cards register their own counts (see useUnsavedGuard).
useUnsavedGuard(unsavedTotal);

// --- Photo uploads ---------------------------------------------------------
const uploadingPhotos = ref<Record<string, boolean>>({});

const uploadPhoto = (routeName: string, params: Record<string, string>, key: string, file: File) => {
    uploadingPhotos.value[key] = true;
    router.post(route(routeName, params), { file } as any, {
        ...visitOptions,
        forceFormData: true,
        onFinish: () => { uploadingPhotos.value[key] = false; },
    });
};

const uploadAreaPhoto = (areaId: string, file: File) =>
    uploadPhoto('inspections.areas.uploadPhoto', { inspectionId: props.inspection.id, areaId }, areaId, file);

const uploadItemPhoto = (itemId: string, file: File) =>
    uploadPhoto('inspections.items.uploadPhoto', { inspectionId: props.inspection.id, itemId }, itemId, file);

// --- Real-time updates -----------------------------------------------------
const echo = useEcho();
const channelName = `inspection.${props.inspection.id}`;
onMounted(() => {
    echo?.private(channelName).listen('.inspection-photo-uploaded', () => router.reload());
});
onUnmounted(() => {
    echo?.leave(channelName);
});

// --- Summary ---------------------------------------------------------------
const requirements = computed(() => {
    const req = props.inspection.completion_requirements ?? {};
    return [
        { label: 'Ratings', required: req.require_ratings },
        { label: 'Photos', required: req.require_photos },
        { label: 'Meters', required: req.require_meters },
        { label: 'Keys', required: req.require_keys },
        { label: 'Compliance', required: req.require_compliance },
    ].filter((r) => r.required);
});

const areaPhotoCount = (area: any) =>
    (area.photos?.length ?? 0) + (area.items ?? []).reduce((sum: number, item: any) => sum + (item.photos?.length ?? 0), 0);
</script>

<template>
    <Head :title="`Edit — ${humanize(inspection.type ?? 'Inspection')}`" />

    <!-- Photo lightbox -->
    <Dialog v-model:open="lightboxOpen">
        <DialogContent class="!w-auto !max-w-[90vw] overflow-hidden !p-0" @keydown="onLightboxKeydown">
            <img :src="lightboxSrc" class="max-h-[85vh] max-w-[90vw] object-contain" />
            <button
                v-if="lightboxIndex > 0"
                class="absolute top-1/2 left-3 -translate-y-1/2 rounded-full bg-black/50 p-2 text-white transition-colors hover:bg-black/70"
                @click="lightboxPrev"
            >
                <ChevronLeft class="h-6 w-6" />
            </button>
            <button
                v-if="lightboxIndex < allPhotos.length - 1"
                class="absolute top-1/2 right-3 -translate-y-1/2 rounded-full bg-black/50 p-2 text-white transition-colors hover:bg-black/70"
                @click="lightboxNext"
            >
                <ChevronRight class="h-6 w-6" />
            </button>
            <div class="absolute bottom-3 left-1/2 -translate-x-1/2 rounded-full bg-black/50 px-3 py-1 text-xs text-white">
                {{ lightboxIndex + 1 }} / {{ allPhotos.length }}
            </div>
        </DialogContent>
    </Dialog>

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="icon" as-child>
                    <Link :href="route('inspections.index')"><ArrowLeft class="h-4 w-4" /></Link>
                </Button>
                <div class="flex-1">
                    <h2 class="text-xl leading-tight font-semibold text-foreground capitalize">{{ humanize(inspection.type ?? 'Inspection') }}</h2>
                    <p v-if="inspection.property?.address" class="text-sm text-muted-foreground">{{ inspection.property.address.full_address }}</p>
                </div>
                <Badge :variant="inspection.status === 'completed' ? 'default' : 'secondary'" class="text-sm capitalize">{{ humanize(inspection.status ?? '—') }}</Badge>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <!-- Summary -->
                <Card>
                    <CardContent class="pt-6">
                        <div class="flex flex-col gap-6 sm:flex-row">
                            <img
                                v-if="inspection.property?.image"
                                :src="inspection.property.image"
                                class="h-32 w-48 shrink-0 cursor-pointer rounded-lg object-cover"
                                @click="openLightbox(inspection.property.image)"
                            />
                            <div v-else class="flex h-32 w-48 shrink-0 items-center justify-center rounded-lg bg-muted text-xs text-muted-foreground">No image</div>
                            <div class="grid flex-1 grid-cols-2 gap-x-8 gap-y-3 text-sm sm:grid-cols-4">
                                <div><p class="text-muted-foreground">Date</p><p class="font-medium">{{ inspection.scheduled_at ?? '—' }}</p></div>
                                <div><p class="text-muted-foreground">Inspector</p><p class="font-medium">{{ inspection.inspector?.name ?? '—' }}</p></div>
                                <div><p class="text-muted-foreground">Type</p><p class="font-medium capitalize">{{ humanize(inspection.type ?? '—') }}</p></div>
                                <div><p class="text-muted-foreground">Depth</p><p class="font-medium capitalize">{{ humanize(inspection.inspection_depth ?? '—') }}</p></div>
                                <div>
                                    <p class="text-muted-foreground">Defects</p>
                                    <p class="font-medium">
                                        {{ inspection.statistics?.total_defects ?? 0 }}
                                        <span v-if="inspection.statistics?.critical_defects" class="text-red-500">({{ inspection.statistics.critical_defects }} critical)</span>
                                    </p>
                                </div>
                                <div><p class="text-muted-foreground">Photos</p><p class="font-medium">{{ inspection.statistics?.total_photos ?? 0 }}</p></div>
                                <div><p class="text-muted-foreground">Areas</p><p class="font-medium">{{ areas.length }}</p></div>
                                <div>
                                    <p class="text-muted-foreground">Property</p>
                                    <p class="flex items-center gap-1 font-medium"><MapPin class="h-3 w-3 shrink-0" />{{ inspection.property?.address?.line_1 ?? '—' }}</p>
                                </div>
                            </div>
                        </div>

                        <!-- What this inspection type has to have before it can be completed -->
                        <div v-if="requirements.length" class="mt-5 flex flex-wrap items-center gap-2 border-t pt-4">
                            <span class="text-xs text-muted-foreground">Required to complete:</span>
                            <Badge v-for="requirement in requirements" :key="requirement.label" variant="outline" class="text-[10px]">
                                {{ requirement.label }}
                            </Badge>
                        </div>
                    </CardContent>
                </Card>

                <!-- Areas and items -->
                <Card>
                    <CardContent class="pt-6">
                        <div class="mb-3 flex items-center justify-between">
                            <div class="flex items-center gap-2">
                                <h3 class="text-base font-semibold">Areas &amp; Items</h3>
                                <Badge variant="secondary">{{ areas.length }} area{{ areas.length === 1 ? '' : 's' }}</Badge>
                                <Badge variant="outline">{{ itemIds.length }} item{{ itemIds.length === 1 ? '' : 's' }}</Badge>
                                <UnsavedBadge :count="unsavedTotal" />
                            </div>
                            <label class="flex cursor-pointer items-center gap-2 text-sm">
                                <Switch v-model="expandAll" />
                                <span class="text-muted-foreground">Expand all</span>
                            </label>
                        </div>

                        <Accordion v-if="areas.length" v-model="openAreas" type="multiple" class="rounded-lg border">
                            <AccordionItem v-for="area in areas" :key="area.id" :value="String(area.id)">
                                <AccordionTrigger>
                                    <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                                        <span class="truncate font-semibold">{{ area.name }}</span>
                                        <Badge v-if="area.condition" variant="outline" class="text-[10px] capitalize">{{ area.condition }}</Badge>
                                        <Badge v-if="area.cleanliness" variant="outline" class="text-[10px] capitalize">{{ area.cleanliness }}</Badge>
                                        <UnsavedBadge :count="unsavedInArea(area)" />
                                        <span class="ml-auto flex shrink-0 items-center gap-3 pr-2 text-xs font-normal text-muted-foreground">
                                            <span>{{ area.items?.length ?? 0 }} item{{ area.items?.length === 1 ? '' : 's' }}</span>
                                            <span v-if="areaPhotoCount(area)" class="flex items-center gap-1">
                                                <Images class="h-3 w-3" />{{ areaPhotoCount(area) }}
                                            </span>
                                        </span>
                                    </div>
                                </AccordionTrigger>

                                <AccordionContent>
                                    <!-- Area itself -->
                                    <div class="grid gap-6 lg:grid-cols-2">
                                        <div class="space-y-2">
                                            <InputGroup>
                                                <InputGroupAddon class="w-28 shrink-0">Condition</InputGroupAddon>
                                                <Select v-model="areaForms[area.id].condition">
                                                    <SelectTrigger class="w-full rounded-none border-0 shadow-none focus:ring-0"><SelectValue placeholder="Not set" /></SelectTrigger>
                                                    <SelectContent><SelectItem v-for="c in conditionOptions" :key="c" :value="c" class="capitalize">{{ c }}</SelectItem></SelectContent>
                                                </Select>
                                            </InputGroup>
                                            <InputGroup>
                                                <InputGroupAddon class="w-28 shrink-0">Cleanliness</InputGroupAddon>
                                                <Select v-model="areaForms[area.id].cleanliness">
                                                    <SelectTrigger class="w-full rounded-none border-0 shadow-none focus:ring-0"><SelectValue placeholder="Not set" /></SelectTrigger>
                                                    <SelectContent><SelectItem v-for="c in cleanlinessOptions" :key="c" :value="c" class="capitalize">{{ c }}</SelectItem></SelectContent>
                                                </Select>
                                            </InputGroup>
                                            <PhraseInput
                                                v-model="areaForms[area.id].description"
                                                category="area"
                                                :context="area.name"
                                            />
                                            <div class="flex justify-end pt-1">
                                                <Button size="sm" :disabled="areaForms[area.id]?.processing" @click="saveArea(area.id)">
                                                    <Save class="mr-2 h-3 w-3" /> Save area
                                                </Button>
                                            </div>
                                        </div>
                                        <PhotoGrid
                                            :photos="area.photos"
                                            :uploading="uploadingPhotos[area.id]"
                                            @select="uploadAreaPhoto(area.id, $event)"
                                            @open="openLightbox"
                                        />
                                    </div>

                                    <Separator class="my-4" />

                                    <!-- Items, each its own accordion -->
                                    <Accordion v-if="area.items?.length" v-model="openItems" type="multiple" class="rounded-lg border">
                                        <AccordionItem v-for="item in area.items" :key="item.id" :value="String(item.id)">
                                            <AccordionTrigger class="py-2.5">
                                                <div class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                                                    <span class="truncate">{{ item.name }}</span>
                                                    <Badge v-if="item.condition" variant="outline" class="text-[10px] capitalize">{{ item.condition }}</Badge>
                                                    <Badge v-if="item.cleanliness" variant="outline" class="text-[10px] capitalize">{{ item.cleanliness }}</Badge>
                                                    <UnsavedBadge v-if="dirtyItemIds.has(String(item.id))" />
                                                    <span v-if="item.photos?.length" class="ml-auto flex shrink-0 items-center gap-1 pr-2 text-xs font-normal text-muted-foreground">
                                                        <Images class="h-3 w-3" />{{ item.photos.length }}
                                                    </span>
                                                </div>
                                            </AccordionTrigger>
                                            <AccordionContent>
                                                <div class="grid gap-4 lg:grid-cols-2">
                                                    <div class="space-y-1.5">
                                                        <InputGroup>
                                                            <InputGroupAddon class="w-28 shrink-0">Condition</InputGroupAddon>
                                                            <Select v-model="itemForms[item.id].condition">
                                                                <SelectTrigger class="w-full rounded-none border-0 shadow-none focus:ring-0"><SelectValue placeholder="—" /></SelectTrigger>
                                                                <SelectContent><SelectItem v-for="c in conditionOptions" :key="c" :value="c" class="capitalize">{{ c }}</SelectItem></SelectContent>
                                                            </Select>
                                                        </InputGroup>
                                                        <InputGroup>
                                                            <InputGroupAddon class="w-28 shrink-0">Cleanliness</InputGroupAddon>
                                                            <Select v-model="itemForms[item.id].cleanliness">
                                                                <SelectTrigger class="w-full rounded-none border-0 shadow-none focus:ring-0"><SelectValue placeholder="—" /></SelectTrigger>
                                                                <SelectContent><SelectItem v-for="c in cleanlinessOptions" :key="c" :value="c" class="capitalize">{{ c }}</SelectItem></SelectContent>
                                                            </Select>
                                                        </InputGroup>
                                                        <PhraseInput
                                                            v-model="itemForms[item.id].description"
                                                            category="item"
                                                            :context="area.name"
                                                            :item-name="item.name"
                                                        />
                                                        <div class="flex justify-end pt-1">
                                                            <Button variant="outline" size="sm" class="h-7 text-xs" :disabled="itemForms[item.id]?.processing" @click="saveItem(item.id)">
                                                                <Save class="mr-1.5 h-3 w-3" /> Save
                                                            </Button>
                                                        </div>
                                                    </div>
                                                    <PhotoGrid
                                                        :photos="item.photos"
                                                        :uploading="uploadingPhotos[item.id]"
                                                        @select="uploadItemPhoto(item.id, $event)"
                                                        @open="openLightbox"
                                                    />
                                                </div>
                                            </AccordionContent>
                                        </AccordionItem>
                                    </Accordion>

                                    <p v-else class="py-3 text-center text-sm text-muted-foreground">No items in this area.</p>
                                </AccordionContent>
                            </AccordionItem>
                        </Accordion>

                        <p v-else class="py-6 text-center text-sm text-muted-foreground">This inspection has no areas to edit.</p>
                    </CardContent>
                </Card>

                <MetersCard :inspection-id="String(inspection.id)" :readings="inspection.meter_readings" />

                <KeysCard :inspection-id="String(inspection.id)" :keys="inspection.keys_fobs" />

                <ComplianceCard
                    v-model:open="openForms"
                    :inspection-id="String(inspection.id)"
                    :forms="inspection.compliance_forms"
                />

                <AssetChecksCard
                    :inspection-id="String(inspection.id)"
                    :groups="inspection.asset_checks"
                    @open-photo="openLightbox"
                />
            </div>
        </div>
    </AuthenticatedLayout>
</template>
