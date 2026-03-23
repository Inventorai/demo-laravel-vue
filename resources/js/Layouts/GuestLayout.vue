<script setup lang="ts">
import ApplicationLogo from '@/components/ApplicationLogo.vue';
import { Card, CardContent } from '@/components/ui/card';
import { Link } from '@inertiajs/vue3';
</script>

<template>
    <div class="flex min-h-screen flex-col items-center justify-center bg-background px-4">
        <div class="mb-6">
            <Link href="/">
                <ApplicationLogo class="h-16 w-16 text-foreground rounded-lg" />
            </Link>
        </div>

        <Card class="w-full max-w-md">
            <CardContent class="pt-6">
                <slot />
            </CardContent>
        </Card>
    </div>
</template>
