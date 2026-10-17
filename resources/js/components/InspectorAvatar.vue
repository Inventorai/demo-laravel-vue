<script setup lang="ts">
import { computed } from 'vue';
import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar';

const props = defineProps<{
    name?: string | null;
    avatar?: string | null;
    initials?: string | null;
    online?: boolean;
}>();

// The API sends initials with an inspector, but presence members only carry a
// name, so fall back to building them from it.
const fallback = computed(() => props.initials
    || (props.name ?? '').split(/\s+/).filter(Boolean).slice(0, 2).map((part) => part[0]).join('').toUpperCase()
    || '?');
</script>

<template>
    <span class="relative inline-flex shrink-0">
        <Avatar class="size-6">
            <AvatarImage v-if="avatar" :src="avatar" :alt="name ?? ''" />
            <AvatarFallback class="text-[10px]">{{ fallback }}</AvatarFallback>
        </Avatar>
        <span
            v-if="online"
            class="absolute -bottom-0.5 -right-0.5 size-2.5 rounded-full bg-green-500 ring-2 ring-background"
            title="Online"
        />
    </span>
</template>
