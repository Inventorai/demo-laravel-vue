<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Head } from '@inertiajs/vue3';
import { CheckCircle } from '@lucide/vue';

defineProps<{
    connected: boolean;
    propertyCount?: number;
}>();
</script>

<template>
    <Head title="Settings" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-foreground">Settings</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <Card>
                    <CardHeader>
                        <CardTitle class="flex items-center gap-2">
                            <CheckCircle class="h-5 w-5 text-green-500" />
                            API Connection
                        </CardTitle>
                        <CardDescription>
                            Status of your Inventorai API connection.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="rounded-lg border p-4">
                            <div class="grid grid-cols-2 gap-y-2 text-sm">
                                <span class="text-muted-foreground">Status</span>
                                <span class="font-medium text-green-600">Connected</span>
                                <span class="text-muted-foreground">Properties</span>
                                <span>{{ propertyCount }}</span>
                            </div>
                        </div>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
