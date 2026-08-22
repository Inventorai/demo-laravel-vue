<script setup lang="ts">
import { onMounted, onUnmounted, ref, watch } from 'vue';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectLabel,
    SelectSeparator,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Head, Link, router } from '@inertiajs/vue3';
import { X, ClipboardCheck, ChevronLeft, ChevronRight, Eye } from '@lucide/vue';
import { Skeleton } from '@/components/ui/skeleton';
import { useEcho } from '@/composables/useEcho';

const humanize = (value: string) => value.replace(/_/g, ' ');

const props = defineProps<{
    inspections: Record<string, any>[];
    meta?: Record<string, any>;
    filters?: Record<string, any>;
    error?: string;
    teamId?: string | null;
}>();

// Live updates: the API broadcasts inspection changes on the team's private
// channel, so the list refreshes itself instead of waiting for a reload.
// Auth is proxied through /broadcasting/auth to keep the API token server-side.
const echo = useEcho();

onMounted(() => {
    if (!echo || !props.teamId) return;

    const refresh = () => router.reload({ only: ['inspections', 'meta'] });

    echo.private(`team.${props.teamId}`)
        .listen('.inspection.created', refresh)
        .listen('.inspection.updated', refresh)
        .listen('.inspection.deleted', refresh)
        .listen('.inspection.auto-completed', refresh)
        .listen('.inspections.archived', refresh);
});

onUnmounted(() => {
    if (props.teamId) {
        echo?.leave(`team.${props.teamId}`);
    }
});

const status = ref(props.filters?.status ?? '');
const type = ref(props.filters?.type ?? '');
const loading = ref(false);

const applyFilters = () => {
    const params: Record<string, any> = {};
    if (status.value) params.status = status.value;
    if (type.value) params.type = type.value;

    router.get(route('inspections.index'), params, {
        preserveState: true,
        preserveScroll: true,
        onStart: () => (loading.value = true),
        onFinish: () => (loading.value = false),
    });
};

watch(status, applyFilters);
watch(type, applyFilters);

const clearFilters = () => {
    status.value = '';
    type.value = '';
    router.get(route('inspections.index'), {}, {
        preserveState: true,
        onStart: () => (loading.value = true),
        onFinish: () => (loading.value = false),
    });
};

const goToPage = (page: number) => {
    const params: Record<string, any> = { page };
    if (status.value) params.status = status.value;
    if (type.value) params.type = type.value;

    router.get(route('inspections.index'), params, {
        preserveState: true,
        preserveScroll: true,
        onStart: () => (loading.value = true),
        onFinish: () => (loading.value = false),
    });
};
</script>

<template>
    <Head title="Inspections" />

    <AuthenticatedLayout>
        <template #header>
            <div class="flex items-center justify-between">
                <h2 class="text-xl font-semibold leading-tight text-foreground">Inspections</h2>
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
                            <CardTitle>Inspections</CardTitle>
                            <div class="flex items-center gap-2">
                                <Select v-model="type">
                                    <SelectTrigger class="w-[180px]">
                                        <SelectValue placeholder="Type" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectLabel class="text-xs text-muted-foreground">Tenancy</SelectLabel>
                                        <SelectItem value="move_in">Move In</SelectItem>
                                        <SelectItem value="periodic">Periodic</SelectItem>
                                        <SelectItem value="move_out">Move Out</SelectItem>
                                        <SelectSeparator />
                                        <SelectLabel class="text-xs text-muted-foreground">Non-Tenancy</SelectLabel>
                                        <SelectItem value="vacant">Vacant</SelectItem>
                                        <SelectItem value="pre_tenancy">Pre-Tenancy</SelectItem>
                                        <SelectItem value="landlord_only">Landlord Inventory</SelectItem>
                                        <SelectItem value="between_tenancies">Between Tenancies</SelectItem>
                                    </SelectContent>
                                </Select>
                                <Select v-model="status">
                                    <SelectTrigger class="w-[180px]">
                                        <SelectValue placeholder="Status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectItem value="draft">Draft</SelectItem>
                                        <SelectItem value="in_progress">In Progress</SelectItem>
                                        <SelectItem value="in_review">In Review</SelectItem>
                                        <SelectItem value="completed">Completed</SelectItem>
                                    </SelectContent>
                                </Select>
                                <Button
                                    v-if="type || status"
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
                        <div v-if="!loading && inspections.length === 0" class="text-center py-12">
                            <ClipboardCheck class="mx-auto h-12 w-12 text-muted-foreground" />
                            <h3 class="mt-4 text-lg font-medium text-foreground">No inspections found</h3>
                            <p class="mt-2 text-sm text-muted-foreground">
                                {{ type || status ? 'Try adjusting your filters.' : 'Inspections from your Inventorai account will appear here.' }}
                            </p>
                        </div>

                        <div v-else-if="loading">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead class="w-[50px]"></TableHead>
                                        <TableHead>Property</TableHead>
                                        <TableHead>Type</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Inspector</TableHead>
                                        <TableHead>Depth</TableHead>
                                        <TableHead>Defects</TableHead>
                                        <TableHead class="w-[50px]"></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="n in 8" :key="`sk-${n}`">
                                        <TableCell><Skeleton class="h-8 w-8 rounded" /></TableCell>
                                        <TableCell><Skeleton class="h-4 w-40" /></TableCell>
                                        <TableCell><Skeleton class="h-5 w-20 rounded-full" /></TableCell>
                                        <TableCell><Skeleton class="h-5 w-20 rounded-full" /></TableCell>
                                        <TableCell><Skeleton class="h-4 w-24" /></TableCell>
                                        <TableCell><Skeleton class="h-4 w-24" /></TableCell>
                                        <TableCell><Skeleton class="h-5 w-16 rounded-full" /></TableCell>
                                        <TableCell><Skeleton class="h-4 w-12" /></TableCell>
                                        <TableCell><Skeleton class="h-8 w-8 rounded" /></TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>

                        <template v-else>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead class="w-[50px]"></TableHead>
                                        <TableHead>Property</TableHead>
                                        <TableHead>Type</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Inspector</TableHead>
                                        <TableHead>Depth</TableHead>
                                        <TableHead>Defects</TableHead>
                                        <TableHead class="w-[50px]"></TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="inspection in inspections" :key="inspection.id">
                                        <TableCell>
                                            <img
                                                v-if="inspection.property?.image"
                                                :src="inspection.property.image"
                                                :alt="inspection.property?.address?.line_1 ?? ''"
                                                class="h-8 w-8 rounded object-cover"
                                            />
                                            <div v-else class="h-8 w-8 rounded bg-muted" />
                                        </TableCell>
                                        <TableCell>
                                            <div class="font-medium">{{ inspection.property?.address?.line_1 ?? '—' }}</div>
                                            <div class="text-xs text-muted-foreground">
                                                {{ [inspection.property?.address?.city, inspection.property?.address?.postcode].filter(Boolean).join(', ') }}
                                            </div>
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline" class="capitalize">
                                                {{ humanize(inspection.type ?? '—') }}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <Badge
                                                :variant="inspection.status === 'completed' ? 'default' : 'secondary'"
                                                class="capitalize"
                                            >
                                                {{ humanize(inspection.status ?? '—') }}
                                            </Badge>
                                        </TableCell>
                                        <TableCell class="whitespace-nowrap">
                                            {{ inspection.scheduled_at ?? '—' }}
                                        </TableCell>
                                        <TableCell>
                                            {{ inspection.inspector?.name ?? '—' }}
                                        </TableCell>
                                        <TableCell>
                                            <Badge variant="outline" class="capitalize">
                                                {{ humanize(inspection.inspection_depth ?? '—') }}
                                            </Badge>
                                        </TableCell>
                                        <TableCell>
                                            <span v-if="inspection.statistics">
                                                {{ inspection.statistics.total_defects }}
                                                <span v-if="inspection.statistics.critical_defects" class="text-red-500 text-xs">
                                                    ({{ inspection.statistics.critical_defects }} critical)
                                                </span>
                                            </span>
                                            <span v-else>—</span>
                                        </TableCell>
                                        <TableCell>
                                            <Button variant="ghost" size="icon" as-child>
                                                <Link :href="route('inspections.show', inspection.id)">
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
