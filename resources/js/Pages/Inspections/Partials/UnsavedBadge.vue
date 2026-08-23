<script setup lang="ts">
/**
 * Marks edits that exist only in the browser until their Save button is used.
 *
 * Nothing on this page saves as you type — every record type posts to its own
 * SDK endpoint on demand — and an accordion can hide a pending edit behind a
 * closed panel, so the count is surfaced on the trigger as well as in place.
 */
withDefaults(defineProps<{ count?: number }>(), { count: 1 });
</script>

<template>
    <span
        v-if="count > 0"
        class="flex shrink-0 items-center gap-1 rounded-full bg-amber-500/15 px-2 py-0.5 text-[10px] font-medium whitespace-nowrap text-amber-700 dark:text-amber-400"
        :title="count === 1 ? 'One unsaved change' : `${count} unsaved changes`"
    >
        <span class="size-1.5 rounded-full bg-amber-500" />
        {{ count === 1 ? 'Unsaved' : `${count} unsaved` }}
    </span>
</template>
