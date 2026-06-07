<script setup lang="ts">
import { onMounted, onUnmounted, ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Head, Link, router } from '@inertiajs/vue3';
import { Eye, Search, X, Home, ChevronLeft, ChevronRight } from '@lucide/vue';
import { useDebounceFn } from '@vueuse/core';
import { useEcho } from '@/composables/useEcho';

const props = defineProps<{
    properties: Record<string, any>[];
    meta?: Record<string, any>;
    filters?: Record<string, any>;
    teamId?: string | null;
    error?: string;
}>();

const search = ref(props.filters?.search ?? '');
const propertyType = ref(props.filters?.property_type ?? '');

const applyFilters = useDebounceFn(() => {
    const params: Record<string, any> = {};
    if (search.value) params.search = search.value;
    if (propertyType.value) params.property_type = propertyType.value;

    router.get(route('properties.index'), params, {
        preserveState: true,
        preserveScroll: true,
    });
}, 300);

watch(search, applyFilters);
watch(propertyType, applyFilters);

const clearFilters = () => {
    search.value = '';
    propertyType.value = '';
    router.get(route('properties.index'), {}, { preserveState: true });
};

const goToPage = (page: number) => {
    const params: Record<string, any> = { page };
    if (search.value) params.search = search.value;
    if (propertyType.value) params.property_type = propertyType.value;

    router.get(route('properties.index'), params, {
        preserveState: true,
        preserveScroll: true,
    });
};

// Realtime: the Inventorai API broadcasts property changes (created, updated,
// address edits, cover/image changes, deletes) on the team.{teamId} channel.
// Subscribe and refresh the list when one arrives. Auth is proxied through
// /broadcasting/auth so the API token stays server-side.
const echo = useEcho();

onMounted(() => {
    if (!echo || !props.teamId) return;

    const refresh = () => router.reload({ only: ['properties', 'meta'] });

    echo.private(`team.${props.teamId}`)
        .listen('.property.created', refresh)
        .listen('.property.updated', refresh)
        .listen('.property.deleted', refresh)
        .listen('.property.image-updated', refresh);
});

onUnmounted(() => {
    if (props.teamId) {
        echo?.leave(`team.${props.teamId}`);
    }
});
</script>

<template>
    <Head title="Properties" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-foreground">Properties</h2>
                <span v-if="meta?.total" class="text-sm text-muted-foreground">
                    {{ meta.total }} total
                </span>
            </div>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl sm:px-6 lg:px-8">
                <div v-if="error" class="mb-6 rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200">
                    {{ error }}
                </div>

                <Card v-else>
                    <CardHeader>
                        <div class="flex flex-col gap-4 sm:flex-row sm:items-center sm:justify-between">
                            <CardTitle>Properties</CardTitle>
                            <div class="flex items-center gap-2">
                                <div class="relative">
                                    <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                    <Input
                                        v-model="search"
                                        placeholder="Search address..."
                                        class="pl-8 w-[200px]"
                                    />
                                </div>
                                <Select v-model="propertyType">
                                    <SelectTrigger class="w-[160px]">
                                        <SelectValue placeholder="Property type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="house">House</SelectItem>
                                        <SelectItem value="flat">Flat</SelectItem>
                                        <SelectItem value="bungalow">Bungalow</SelectItem>
                                        <SelectItem value="maisonette">Maisonette</SelectItem>
                                        <SelectItem value="studio">Studio</SelectItem>
                                        <SelectItem value="room">Room</SelectItem>
                                        <SelectItem value="commercial">Commercial</SelectItem>
                                        <SelectItem value="other">Other</SelectItem>
                                    </SelectContent>
                                </Select>
                                <Button
                                    v-if="search || propertyType"
                                    variant="ghost"
                                    size="icon"
                                    @click="clearFilters"
                                >
                                    <X class="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </CardHeader>
                    <CardContent>
                        <div v-if="properties.length === 0" class="text-center py-12">
                            <Home class="mx-auto h-12 w-12 text-muted-foreground" />
                            <h3 class="mt-4 text-lg font-medium text-foreground">No properties found</h3>
                            <p class="mt-2 text-sm text-muted-foreground">
                                {{ search || propertyType ? 'Try adjusting your filters.' : 'Properties from your Inventorai account will appear here.' }}
                            </p>
                        </div>

                        <template v-else>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead class="w-[50px]"></TableHead>
                                        <TableHead>Address</TableHead>
                                        <TableHead>City</TableHead>
                                        <TableHead>Postcode</TableHead>
                                        <TableHead>Type</TableHead>
                                        <TableHead>Category</TableHead>
                                        <TableHead class="w-[50px]"></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="property in properties" :key="property.id">
                                        <TableCell>
                                            <img
                                                v-if="property.image"
                                                :src="property.image"
                                                :alt="property.address?.line_1 ?? ''"
                                                class="h-8 w-8 rounded object-cover"
                                            />
                                            <div v-else class="h-8 w-8 rounded bg-muted" />
                                        </TableCell>
                                        <TableCell class="font-medium">
                                            {{ property.address?.line_1 ?? '—' }}
                                        </TableCell>
                                        <TableCell>{{ property.address?.city ?? '—' }}</TableCell>
                                        <TableCell>{{ property.address?.postcode ?? '—' }}</TableCell>
                                        <TableCell>
                                            <Badge variant="secondary" class="capitalize">
                                                {{ property.property_type ?? '—' }}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <Badge :variant="property.residential ? 'default' : 'outline'">
                                                {{ property.residential ? 'Residential' : 'Commercial' }}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <Button variant="ghost" size="icon" as-child>
                                                <Link :href="route('properties.show', property.id)">
                                                    <Eye class="h-4 w-4" />
                                                </Link>
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>

                            <div v-if="meta && meta.last_page > 1" class="mt-4 flex items-center justify-between">
                                <p class="text-sm text-muted-foreground">
                                    Page {{ meta.current_page }} of {{ meta.last_page }}
                                </p>
                                <div class="flex items-center gap-2">
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        :disabled="meta.current_page <= 1"
                                        @click="goToPage(meta!.current_page - 1)"
                                    >
                                        <ChevronLeft class="h-4 w-4" />
                                        Previous
                                    </Button>
                                    <Button
                                        variant="outline"
                                        size="sm"
                                        :disabled="meta.current_page >= meta.last_page"
                                        @click="goToPage(meta!.current_page + 1)"
                                    >
                                        Next
                                        <ChevronRight class="h-4 w-4" />
                                    </Button>
                                </div>
                            </div>
                        </template>
                    </CardContent>
                </Card>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
