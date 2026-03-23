<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Head, Link } from '@inertiajs/vue3';
import { ArrowLeft } from 'lucide-vue-next';

defineProps<{
    property: Record<string, any>;
}>();

const humanize = (value: string) => value.replace(/_/g, ' ');
</script>

<template>
    <Head :title="property.address?.line_1 ?? `Property #${property.id}`" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center gap-4">
                <Button variant="ghost" size="icon" as-child>
                    <Link :href="route('properties.index')">
                        <ArrowLeft class="h-4 w-4" />
                    </Link>
                </Button>
                <h2 class="text-xl font-semibold leading-tight text-foreground">
                    {{ property.address?.full_address ?? `Property #${property.id}` }}
                </h2>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <!-- Cover image + details -->
                <Card>
                    <CardContent class="pt-6">
                        <div class="flex flex-col gap-6 sm:flex-row">
                            <div v-if="property.image" class="shrink-0">
                                <img
                                    :src="property.image"
                                    :alt="property.address?.full_address ?? ''"
                                    class="h-48 w-72 rounded-lg object-cover"
                                />
                            </div>
                            <div class="flex-1 space-y-4">
                                <div>
                                    <h3 class="text-lg font-semibold">{{ property.address?.line_1 }}</h3>
                                    <p v-if="property.address?.line_2" class="text-sm text-muted-foreground">{{ property.address.line_2 }}</p>
                                    <p class="text-sm text-muted-foreground">
                                        {{ [property.address?.city, property.address?.county, property.address?.postcode].filter(Boolean).join(', ') }}
                                    </p>
                                </div>
                                <div class="flex flex-wrap gap-2">
                                    <Badge variant="secondary" class="capitalize">{{ property.property_type }}</Badge>
                                    <Badge :variant="property.residential ? 'default' : 'outline'">
                                        {{ property.residential ? 'Residential' : 'Commercial' }}
                                    </Badge>
                                </div>
                                <div v-if="property.created_at" class="text-xs text-muted-foreground">
                                    Added {{ property.created_at }}
                                </div>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Landlord -->
                <Card v-if="property.landlord">
                    <CardHeader>
                        <CardTitle>Landlord</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="grid gap-3 sm:grid-cols-2">
                            <template v-for="(value, key) in property.landlord" :key="key">
                                <template v-if="typeof value !== 'object' || value === null">
                                    <div class="text-sm text-muted-foreground capitalize">{{ humanize(String(key)) }}</div>
                                    <div class="text-sm">{{ value ?? '—' }}</div>
                                </template>
                            </template>
                        </div>
                    </CardContent>
                </Card>

                <!-- Inspections -->
                <Card v-if="property.inspections?.length">
                    <CardHeader>
                        <CardTitle>Inspections ({{ property.inspections.length }})</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <div class="space-y-3">
                            <div
                                v-for="inspection in property.inspections"
                                :key="inspection.id"
                                class="flex items-center justify-between rounded-lg border p-3"
                            >
                                <div>
                                    <p class="text-sm font-medium capitalize">{{ humanize(inspection.type ?? `Inspection #${inspection.id}`) }}</p>
                                    <p class="text-xs text-muted-foreground">
                                        {{ inspection.inspection_date ?? inspection.created_at ?? '—' }}
                                    </p>
                                </div>
                                <Badge variant="secondary" class="capitalize">
                                    {{ humanize(inspection.status ?? '—') }}
                                </Badge>
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <!-- Raw JSON -->
                <Card>
                    <CardHeader>
                        <CardTitle>Raw JSON</CardTitle>
                        <CardDescription>Full API response</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <pre class="max-h-96 overflow-auto rounded-lg bg-muted p-4 text-xs"><code>{{ JSON.stringify(property, null, 2) }}</code></pre>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
