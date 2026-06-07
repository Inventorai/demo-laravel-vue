<script setup lang="ts">
import { ref, computed, onMounted, onUnmounted } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { InputGroup, InputGroupAddon, InputGroupInput } from '@/components/ui/input-group';
import {
    Select, SelectContent, SelectItem, SelectTrigger, SelectValue,
} from '@/components/ui/select';
import {
    Dialog, DialogContent,
} from '@/components/ui/dialog';
import { Separator } from '@/components/ui/separator';
import { Head, Link, useForm, router } from '@inertiajs/vue3';
import { ArrowLeft, Save, MapPin, Upload, ChevronLeft, ChevronRight } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import axios from 'axios';
import { useEcho } from '@/composables/useEcho';

const props = defineProps<{
    inspection: Record<string, any>;
}>();

const humanize = (value: string) => value.replace(/_/g, ' ');
const conditionOptions = ['poor', 'fair', 'good', 'excellent'];
const cleanlinessOptions = ['dirty', 'fair', 'clean', 'spotless'];

// Lightbox — collects all photos into a flat array for prev/next navigation
const allPhotos = computed(() => {
    const photos: { thumbnail: string; full: string }[] = [];
    (props.inspection.areas ?? []).forEach((area: any) => {
        (area.photos ?? []).forEach((p: any) => photos.push({ thumbnail: p.thumbnail_url ?? p.url, full: p.original_url ?? p.url }));
        (area.items ?? []).forEach((item: any) => {
            (item.photos ?? []).forEach((p: any) => photos.push({ thumbnail: p.thumbnail_url ?? p.url, full: p.original_url ?? p.url }));
        });
    });
    return photos;
});

const lightboxOpen = ref(false);
const lightboxIndex = ref(0);
const lightboxSrc = computed(() => allPhotos.value[lightboxIndex.value]?.full ?? '');

const openLightbox = (url: string) => {
    const idx = allPhotos.value.findIndex(p => p.full === url);
    lightboxIndex.value = idx >= 0 ? idx : 0;
    lightboxOpen.value = true;
};
const lightboxPrev = () => { if (lightboxIndex.value > 0) lightboxIndex.value--; };
const lightboxNext = () => { if (lightboxIndex.value < allPhotos.value.length - 1) lightboxIndex.value++; };
const onLightboxKeydown = (e: KeyboardEvent) => {
    if (e.key === 'ArrowLeft') lightboxPrev();
    else if (e.key === 'ArrowRight') lightboxNext();
};

// Phrases autocomplete — grouped by subcategory with multi-select
interface Phrase { id: string; text: string; category: string; context: string | null; subcategory?: string; type: string; source: string; usage_count?: number; }
interface PhraseGroup { label: string; icon: string; color: string; phrases: Phrase[]; }

const subcategoryConfig: Record<string, { icon: string; color: string; order: number }> = {
    'Condition': { icon: 'check-circle', color: 'text-emerald-500', order: 0 },
    'Cleanliness': { icon: 'droplet', color: 'text-blue-500', order: 1 },
    'Defects': { icon: 'alert-triangle', color: 'text-amber-500', order: 2 },
    'Attributes': { icon: 'tag', color: 'text-violet-500', order: 3 },
};

const phraseSuggestions = ref<Record<string, PhraseGroup[]>>({});
const phraseOpen = ref<Record<string, boolean>>({});
const selectedPhrases = ref<Record<string, Set<string>>>({});

const groupPhrases = (phrases: Phrase[]): PhraseGroup[] => {
    const groups: Record<string, Phrase[]> = {};
    phrases.forEach(p => {
        const key = p.subcategory ?? p.context ?? 'General';
        (groups[key] ??= []).push(p);
    });

    return Object.entries(groups)
        .sort(([a], [b]) => {
            const aOrder = subcategoryConfig[a]?.order ?? 99;
            const bOrder = subcategoryConfig[b]?.order ?? 99;
            return aOrder - bOrder || a.localeCompare(b);
        })
        .map(([key, phrases]) => ({
            label: key,
            icon: subcategoryConfig[key]?.icon ?? 'database',
            color: subcategoryConfig[key]?.color ?? 'text-muted-foreground',
            phrases,
        }));
};

const searchPhrases = useDebounceFn(async (fieldId: string, query: string, category: string = 'item', context?: string, itemName?: string) => {
    if (query.length < 2) { phraseSuggestions.value[fieldId] = []; phraseOpen.value[fieldId] = false; return; }
    try {
        const params: Record<string, string> = { q: query, category };
        if (context) params.context = context;
        if (itemName) params.item_name = itemName;
        const { data } = await axios.get(route('api.phrases.search'), { params });
        const grouped = groupPhrases(data ?? []);
        phraseSuggestions.value[fieldId] = grouped;
        phraseOpen.value[fieldId] = grouped.length > 0;
    } catch { phraseSuggestions.value[fieldId] = []; }
}, 300);

const closePhrases = (fieldId: string) => {
    globalThis.setTimeout(() => { phraseOpen.value[fieldId] = false; }, 200);
};

const togglePhrase = (fieldId: string, text: string) => {
    if (!selectedPhrases.value[fieldId]) selectedPhrases.value[fieldId] = new Set();
    const set = selectedPhrases.value[fieldId];
    if (set.has(text)) set.delete(text);
    else set.add(text);
};

const isPhraseSelected = (fieldId: string, text: string) => {
    return selectedPhrases.value[fieldId]?.has(text) ?? false;
};

const confirmPhrases = (fieldId: string, form: any, field: string) => {
    const set = selectedPhrases.value[fieldId];
    if (set?.size) {
        const existing = form[field] ? form[field] + '. ' : '';
        form[field] = existing + Array.from(set).join('. ');
        selectedPhrases.value[fieldId] = new Set();
    }
    phraseOpen.value[fieldId] = false;
};

const selectPhrase = (fieldId: string, text: string, form: any, field: string) => {
    form[field] = text;
    phraseOpen.value[fieldId] = false;
};

// Area forms
const areaForms = Object.fromEntries(
    (props.inspection.areas ?? []).map((area: any) => [
        area.id,
        useForm({ condition: area.condition ?? '', cleanliness: area.cleanliness ?? '', description: area.notes ?? '' }),
    ]),
);
const saveArea = (areaId: string) => {
    const form = areaForms[areaId];
    // Map description back to notes for the API
    const data = { condition: form.condition, cleanliness: form.cleanliness, notes: form.description };
    router.patch(route('inspections.areas.update', { inspectionId: props.inspection.id, areaId }), data, { preserveScroll: true });
};

// Item forms
const itemForms: Record<string, any> = {};
(props.inspection.areas ?? []).forEach((area: any) => {
    (area.items ?? []).forEach((item: any) => {
        itemForms[item.id] = useForm({ condition: item.condition ?? '', cleanliness: item.cleanliness ?? '', description: item.description ?? '' });
    });
});
const saveItem = (itemId: string) => {
    itemForms[itemId].patch(route('inspections.items.update', { inspectionId: props.inspection.id, itemId }), { preserveScroll: true });
};

// Real-time updates via Echo
const echo = useEcho();
const channelName = `inspection.${props.inspection.id}`;
onMounted(() => {
    echo?.private(channelName)
        .listen('.inspection-photo-uploaded', () => {
            router.reload();
        });
});
onUnmounted(() => {
    echo?.leave(channelName);
});

// Photo uploads
const uploadingPhotos = ref<Record<string, boolean>>({});
const uploadAreaPhoto = (areaId: string, event: Event) => {
    const input = event.target as HTMLInputElement;
    if (!input.files?.length) return;
    uploadingPhotos.value[areaId] = true;
    router.post(route('inspections.areas.uploadPhoto', { inspectionId: props.inspection.id, areaId }), { file: input.files[0] } as any, {
        preserveScroll: true, forceFormData: true,
        onFinish: () => { uploadingPhotos.value[areaId] = false; input.value = ''; },
    });
};
const uploadItemPhoto = (itemId: string, event: Event) => {
    const input = event.target as HTMLInputElement;
    if (!input.files?.length) return;
    uploadingPhotos.value[itemId] = true;
    router.post(route('inspections.items.uploadPhoto', { inspectionId: props.inspection.id, itemId }), { file: input.files[0] } as any, {
        preserveScroll: true, forceFormData: true,
        onFinish: () => { uploadingPhotos.value[itemId] = false; input.value = ''; },
    });
};
</script>

<template>
    <Head :title="`Edit — ${humanize(inspection.type ?? 'Inspection')}`" />

    <!-- Photo lightbox -->
    <Dialog v-model:open="lightboxOpen">
        <DialogContent class="!max-w-[90vw] !w-auto !p-0 overflow-hidden" @keydown="onLightboxKeydown">
            <img :src="lightboxSrc" class="max-h-[85vh] max-w-[90vw] object-contain" />
            <button
                v-if="lightboxIndex > 0"
                class="absolute left-3 top-1/2 -translate-y-1/2 rounded-full bg-black/50 p-2 text-white hover:bg-black/70 transition-colors"
                @click="lightboxPrev"
            >
                <ChevronLeft class="h-6 w-6" />
            </button>
            <button
                v-if="lightboxIndex < allPhotos.length - 1"
                class="absolute right-3 top-1/2 -translate-y-1/2 rounded-full bg-black/50 p-2 text-white hover:bg-black/70 transition-colors"
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
                    <h2 class="text-xl font-semibold leading-tight text-foreground capitalize">{{ humanize(inspection.type ?? 'Inspection') }}</h2>
                    <p v-if="inspection.property?.address" class="text-sm text-muted-foreground">{{ inspection.property.address.full_address }}</p>
                </div>
                <Badge :variant="inspection.status === 'completed' ? 'default' : 'secondary'" class="capitalize text-sm">{{ humanize(inspection.status ?? '—') }}</Badge>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <!-- Summary -->
                <Card>
                    <CardContent class="pt-6">
                        <div class="flex flex-col gap-6 sm:flex-row">
                            <img v-if="inspection.property?.image" :src="inspection.property.image" class="h-32 w-48 rounded-lg object-cover shrink-0 cursor-pointer" @click="openLightbox(inspection.property.image)" />
                            <div v-else class="h-32 w-48 rounded-lg bg-muted shrink-0 flex items-center justify-center text-muted-foreground text-xs">No image</div>
                            <div class="flex-1 grid grid-cols-2 gap-x-8 gap-y-3 text-sm sm:grid-cols-4">
                                <div><p class="text-muted-foreground">Date</p><p class="font-medium">{{ inspection.inspection_date ?? '—' }}</p></div>
                                <div><p class="text-muted-foreground">Inspector</p><p class="font-medium">{{ inspection.inspector?.name ?? '—' }}</p></div>
                                <div><p class="text-muted-foreground">Type</p><p class="font-medium capitalize">{{ humanize(inspection.type ?? '—') }}</p></div>
                                <div><p class="text-muted-foreground">Depth</p><p class="font-medium capitalize">{{ humanize(inspection.inspection_depth ?? '—') }}</p></div>
                                <div><p class="text-muted-foreground">Defects</p><p class="font-medium">{{ inspection.statistics?.total_defects ?? 0 }}<span v-if="inspection.statistics?.critical_defects" class="text-red-500"> ({{ inspection.statistics.critical_defects }} critical)</span></p></div>
                                <div><p class="text-muted-foreground">Photos</p><p class="font-medium">{{ inspection.statistics?.total_photos ?? 0 }}</p></div>
                                <div><p class="text-muted-foreground">Areas</p><p class="font-medium">{{ inspection.areas?.length ?? 0 }}</p></div>
                                <div><p class="text-muted-foreground">Property</p><p class="font-medium flex items-center gap-1"><MapPin class="h-3 w-3 shrink-0" />{{ inspection.property?.address?.line_1 ?? '—' }}</p></div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Areas -->
                <Card v-for="area in inspection.areas" :key="area.id">
                    <CardHeader class="pb-3">
                        <div class="flex items-center justify-between">
                            <CardTitle>{{ area.name }}</CardTitle>
                            <Button size="sm" :disabled="areaForms[area.id]?.processing" @click="saveArea(area.id)">
                                <Save class="mr-2 h-3 w-3" /> Save Area
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div class="grid grid-cols-2 gap-6">
                            <!-- Left: fields -->
                            <div class="space-y-2">
                                <InputGroup>
                                    <InputGroupAddon class="w-28 shrink-0">Condition</InputGroupAddon>
                                    <Select v-model="areaForms[area.id].condition">
                                        <SelectTrigger class="w-full border-0 shadow-none rounded-none focus:ring-0"><SelectValue placeholder="Not set" /></SelectTrigger>
                                        <SelectContent><SelectItem v-for="c in conditionOptions" :key="c" :value="c" class="capitalize">{{ c }}</SelectItem></SelectContent>
                                    </Select>
                                </InputGroup>
                                <InputGroup>
                                    <InputGroupAddon class="w-28 shrink-0">Cleanliness</InputGroupAddon>
                                    <Select v-model="areaForms[area.id].cleanliness">
                                        <SelectTrigger class="w-full border-0 shadow-none rounded-none focus:ring-0"><SelectValue placeholder="Not set" /></SelectTrigger>
                                        <SelectContent><SelectItem v-for="c in cleanlinessOptions" :key="c" :value="c" class="capitalize">{{ c }}</SelectItem></SelectContent>
                                    </Select>
                                </InputGroup>
                                <div class="relative">
                                    <InputGroup>
                                        <InputGroupAddon class="w-28 shrink-0">Description</InputGroupAddon>
                                        <InputGroupInput
                                            v-model="areaForms[area.id].description"
                                            placeholder="Type to search phrases..."
                                            @input="searchPhrases(`area-${area.id}`, areaForms[area.id].description, 'area', area.name)"
                                            @focus="phraseSuggestions[`area-${area.id}`]?.length && (phraseOpen[`area-${area.id}`] = true)"
                                            @blur="closePhrases(`area-${area.id}`)"
                                        />
                                    </InputGroup>
                                    <div
                                        v-if="phraseOpen[`area-${area.id}`] && phraseSuggestions[`area-${area.id}`]?.length"
                                        class="absolute z-50 mt-1 w-full max-h-72 overflow-y-auto rounded-md border bg-popover shadow-md"
                                    >
                                        <template v-for="group in phraseSuggestions[`area-${area.id}`]" :key="group.label">
                                            <div class="flex items-center gap-1.5 px-3 pt-2.5 pb-1">
                                                <span :class="group.color" class="text-xs">&#9679;</span>
                                                <span class="text-xs font-semibold" :class="group.color">{{ group.label }}</span>
                                                <span class="text-xs text-muted-foreground">({{ group.phrases.length }})</span>
                                            </div>
                                            <button
                                                v-for="phrase in group.phrases"
                                                :key="phrase.id"
                                                class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm hover:bg-accent hover:text-accent-foreground"
                                                @mousedown.prevent="togglePhrase(`area-${area.id}`, phrase.text)"
                                            >
                                                <div class="flex h-4 w-4 shrink-0 items-center justify-center rounded border" :class="isPhraseSelected(`area-${area.id}`, phrase.text) ? 'bg-primary border-primary text-primary-foreground' : 'border-input'">
                                                    <svg v-if="isPhraseSelected(`area-${area.id}`, phrase.text)" class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6L9 17l-5-5"/></svg>
                                                </div>
                                                <span class="flex-1">{{ phrase.text }}</span>
                                                <span v-if="phrase.usage_count" class="text-xs text-muted-foreground shrink-0">{{ phrase.usage_count }}x</span>
                                            </button>
                                        </template>
                                        <div v-if="selectedPhrases[`area-${area.id}`]?.size" class="sticky bottom-0 border-t bg-popover p-2 flex items-center justify-between">
                                            <span class="text-xs text-muted-foreground">{{ selectedPhrases[`area-${area.id}`].size }} selected</span>
                                            <Button size="sm" class="h-7 text-xs" @mousedown.prevent="confirmPhrases(`area-${area.id}`, areaForms[area.id], 'description')">
                                                Add to description
                                            </Button>
                                        </div>
                                    </div>
                                </div>
                            </div>
                            <!-- Right: photos -->
                            <div class="grid grid-cols-6 gap-1.5">
                                <label class="aspect-square w-full rounded-md border-2 border-dashed flex flex-col items-center justify-center cursor-pointer text-muted-foreground hover:bg-muted/50 transition-colors">
                                    <Upload class="h-4 w-4" />
                                    <span class="text-[10px] mt-1">{{ uploadingPhotos[area.id] ? 'Uploading' : 'Upload' }}</span>
                                    <input type="file" accept="image/*" class="hidden" :disabled="uploadingPhotos[area.id]" @change="uploadAreaPhoto(area.id, $event)" />
                                </label>
                                <img
                                    v-for="photo in (area.photos ?? [])"
                                    :key="photo.id"
                                    :src="photo.thumbnail_url ?? photo.url"
                                    class="aspect-square w-full rounded-md object-cover cursor-pointer hover:opacity-80 transition-opacity"
                                    @click="openLightbox(photo.original_url ?? photo.url)"
                                />
                            </div>
                        </div>

                        <Separator class="my-4" />

                        <!-- Items -->
                        <div v-if="area.items?.length" class="space-y-4">
                            <div v-for="item in area.items" :key="item.id" class="rounded-lg border p-3">
                                <div class="flex items-center justify-between mb-2">
                                    <p class="text-sm font-semibold">{{ item.name }}</p>
                                    <Button variant="outline" size="sm" class="h-7 text-xs" :disabled="itemForms[item.id]?.processing" @click="saveItem(item.id)">
                                        <Save class="mr-1.5 h-3 w-3" /> Save
                                    </Button>
                                </div>
                                <div class="grid grid-cols-2 gap-4">
                                    <!-- Left: fields -->
                                    <div class="space-y-1.5">
                                        <InputGroup>
                                            <InputGroupAddon class="w-28 shrink-0">Condition</InputGroupAddon>
                                            <Select v-model="itemForms[item.id].condition">
                                                <SelectTrigger class="w-full border-0 shadow-none rounded-none focus:ring-0"><SelectValue placeholder="—" /></SelectTrigger>
                                                <SelectContent><SelectItem v-for="c in conditionOptions" :key="c" :value="c" class="capitalize">{{ c }}</SelectItem></SelectContent>
                                            </Select>
                                        </InputGroup>
                                        <InputGroup>
                                            <InputGroupAddon class="w-28 shrink-0">Cleanliness</InputGroupAddon>
                                            <Select v-model="itemForms[item.id].cleanliness">
                                                <SelectTrigger class="w-full border-0 shadow-none rounded-none focus:ring-0"><SelectValue placeholder="—" /></SelectTrigger>
                                                <SelectContent><SelectItem v-for="c in cleanlinessOptions" :key="c" :value="c" class="capitalize">{{ c }}</SelectItem></SelectContent>
                                            </Select>
                                        </InputGroup>
                                        <div class="relative">
                                            <InputGroup>
                                                <InputGroupAddon class="w-28 shrink-0">Description</InputGroupAddon>
                                                <InputGroupInput
                                                    v-model="itemForms[item.id].description"
                                                    placeholder="Type to search phrases..."
                                                    @input="searchPhrases(`item-${item.id}`, itemForms[item.id].description, 'item', area.name, item.name)"
                                                    @focus="phraseSuggestions[`item-${item.id}`]?.length && (phraseOpen[`item-${item.id}`] = true)"
                                                    @blur="closePhrases(`item-${item.id}`)"
                                                />
                                            </InputGroup>
                                            <div
                                                v-if="phraseOpen[`item-${item.id}`] && phraseSuggestions[`item-${item.id}`]?.length"
                                                class="absolute z-50 mt-1 w-full max-h-72 overflow-y-auto rounded-md border bg-popover shadow-md"
                                            >
                                                <template v-for="group in phraseSuggestions[`item-${item.id}`]" :key="group.label">
                                                    <div class="flex items-center gap-1.5 px-3 pt-2.5 pb-1">
                                                        <span :class="group.color" class="text-xs">&#9679;</span>
                                                        <span class="text-xs font-semibold" :class="group.color">{{ group.label }}</span>
                                                        <span class="text-xs text-muted-foreground">({{ group.phrases.length }})</span>
                                                    </div>
                                                    <button
                                                        v-for="phrase in group.phrases"
                                                        :key="phrase.id"
                                                        class="flex w-full items-center gap-2 px-3 py-1.5 text-left text-sm hover:bg-accent hover:text-accent-foreground"
                                                        @mousedown.prevent="togglePhrase(`item-${item.id}`, phrase.text)"
                                                    >
                                                        <div class="flex h-4 w-4 shrink-0 items-center justify-center rounded border" :class="isPhraseSelected(`item-${item.id}`, phrase.text) ? 'bg-primary border-primary text-primary-foreground' : 'border-input'">
                                                            <svg v-if="isPhraseSelected(`item-${item.id}`, phrase.text)" class="h-3 w-3" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3"><path d="M20 6L9 17l-5-5"/></svg>
                                                        </div>
                                                        <span class="flex-1">{{ phrase.text }}</span>
                                                        <span v-if="phrase.usage_count" class="text-xs text-muted-foreground shrink-0">{{ phrase.usage_count }}x</span>
                                                    </button>
                                                </template>
                                                <div v-if="selectedPhrases[`item-${item.id}`]?.size" class="sticky bottom-0 border-t bg-popover p-2 flex items-center justify-between">
                                                    <span class="text-xs text-muted-foreground">{{ selectedPhrases[`item-${item.id}`].size }} selected</span>
                                                    <Button size="sm" class="h-7 text-xs" @mousedown.prevent="confirmPhrases(`item-${item.id}`, itemForms[item.id], 'description')">
                                                        Add to description
                                                    </Button>
                                                </div>
                                            </div>
                                        </div>
                                    </div>
                                    <!-- Right: photos -->
                                    <div class="grid grid-cols-6 gap-1.5">
                                        <label class="aspect-square w-full rounded-md border-2 border-dashed flex flex-col items-center justify-center cursor-pointer text-muted-foreground hover:bg-muted/50 transition-colors">
                                            <Upload class="h-4 w-4" />
                                            <span class="text-[10px] mt-1">{{ uploadingPhotos[item.id] ? 'Uploading' : 'Upload' }}</span>
                                            <input type="file" accept="image/*" class="hidden" :disabled="uploadingPhotos[item.id]" @change="uploadItemPhoto(item.id, $event)" />
                                        </label>
                                        <img
                                            v-for="photo in (item.photos ?? [])"
                                            :key="photo.id"
                                            :src="photo.thumbnail_url ?? photo.url"
                                            class="aspect-square w-full rounded-md object-cover cursor-pointer hover:opacity-80 transition-opacity"
                                            @click="openLightbox(photo.original_url ?? photo.url)"
                                        />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-else class="text-center py-4 text-sm text-muted-foreground">No items in this area.</div>
                    </CardContent>
                </Card>

                <Card v-if="!inspection.areas?.length">
                    <CardContent class="py-12 text-center">
                        <h3 class="text-lg font-medium">No areas</h3>
                        <p class="mt-2 text-sm text-muted-foreground">This inspection has no areas to edit.</p>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
