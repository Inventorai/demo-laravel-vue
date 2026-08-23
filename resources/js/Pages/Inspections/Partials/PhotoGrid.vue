<script setup lang="ts">
/**
 * Thumbnail grid with an upload tile in the first cell.
 *
 * The parent owns the upload request — every record type posts to its own
 * SDK endpoint — so this only hands back the chosen File.
 */
import { Upload } from '@lucide/vue';

withDefaults(defineProps<{
    photos?: Record<string, any>[];
    uploading?: boolean;
    columns?: number;
    readonly?: boolean;
}>(), {
    photos: () => [],
    uploading: false,
    columns: 6,
    readonly: false,
});

const emit = defineEmits<{ select: [file: File]; open: [url: string] }>();

const thumb = (photo: Record<string, any>) => photo.thumbnail_url ?? photo.url;
const full = (photo: Record<string, any>) => photo.original_url ?? photo.url;

const onChange = (event: Event) => {
    const input = event.target as HTMLInputElement;
    if (!input.files?.length) return;
    emit('select', input.files[0]);
    input.value = '';
};
</script>

<template>
    <div class="grid gap-1.5" :style="{ gridTemplateColumns: `repeat(${columns}, minmax(0, 1fr))` }">
        <label
            v-if="!readonly"
            class="flex aspect-square w-full cursor-pointer flex-col items-center justify-center rounded-md border-2 border-dashed text-muted-foreground transition-colors hover:bg-muted/50"
        >
            <Upload class="h-4 w-4" />
            <span class="mt-1 text-[10px]">{{ uploading ? 'Uploading' : 'Upload' }}</span>
            <input type="file" accept="image/*" class="hidden" :disabled="uploading" @change="onChange" />
        </label>
        <img
            v-for="photo in photos"
            :key="photo.id"
            :src="thumb(photo)"
            class="aspect-square w-full cursor-pointer rounded-md object-cover transition-opacity hover:opacity-80"
            @click="emit('open', full(photo))"
        />
    </div>
</template>
