<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Head } from '@inertiajs/vue3';
import { Home, ClipboardCheck, CheckCircle, Clock } from '@lucide/vue';
import { VisDonut, VisSingleContainer, VisGroupedBar, VisAxis, VisXYContainer } from '@unovis/vue';

const props = defineProps<{
    stats: {
        totalProperties: number;
        totalInspections: number;
        completedInspections: number;
        inProgressInspections: number;
    };
    charts: {
        propertyTypes: { type: string; count: number }[];
        inspectionTypes: { type: string; count: number }[];
        inspectionStatuses: { status: string; count: number }[];
    };
}>();

const humanize = (value: string) => value.replace(/_/g, ' ');

const statusColors: Record<string, string> = {
    completed: 'var(--color-chart-2)',
    in_progress: 'var(--color-chart-1)',
    draft: 'var(--color-chart-4)',
    in_review: 'var(--color-chart-5)',
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-foreground">Dashboard</h2>
        </template>

        <div class="py-12">
            <div class="mx-auto max-w-7xl space-y-6 sm:px-6 lg:px-8">
                <!-- Stat cards -->
                <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between pb-2">
                            <CardDescription>Properties</CardDescription>
                            <Home class="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">{{ stats.totalProperties }}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between pb-2">
                            <CardDescription>Inspections</CardDescription>
                            <ClipboardCheck class="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">{{ stats.totalInspections }}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between pb-2">
                            <CardDescription>Completed</CardDescription>
                            <CheckCircle class="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">{{ stats.completedInspections }}</div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader class="flex flex-row items-center justify-between pb-2">
                            <CardDescription>In Progress</CardDescription>
                            <Clock class="h-4 w-4 text-muted-foreground" />
                        </CardHeader>
                        <CardContent>
                            <div class="text-2xl font-bold">{{ stats.inProgressInspections }}</div>
                        </CardContent>
                    </Card>
                </div>

                <!-- Charts -->
                <div class="grid gap-6 lg:grid-cols-2">
                    <!-- Property Types Donut -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Property Types</CardTitle>
                            <CardDescription>Distribution across your portfolio</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="flex items-center gap-8">
                                <div class="h-[160px] w-[160px] shrink-0 overflow-hidden">
                                    <VisSingleContainer :height="160" :data="{ items: charts.propertyTypes }">
                                        <VisDonut
                                            :value="(d: any) => d.count"
                                            :arcWidth="30"
                                            :padAngle="0.02"
                                            :cornerRadius="4"
                                        />
                                    </VisSingleContainer>
                                </div>
                                <div class="space-y-2">
                                    <div
                                        v-for="(item, i) in charts.propertyTypes"
                                        :key="item.type"
                                        class="flex items-center justify-between gap-4 text-sm"
                                    >
                                        <span class="capitalize text-muted-foreground">{{ humanize(item.type) }}</span>
                                        <span class="font-medium tabular-nums">{{ item.count }}</span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Inspection Statuses Donut -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Inspection Status</CardTitle>
                            <CardDescription>Current status breakdown</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="flex items-center gap-8">
                                <div class="h-[160px] w-[160px] shrink-0 overflow-hidden">
                                    <VisSingleContainer :height="160" :data="{ items: charts.inspectionStatuses }">
                                        <VisDonut
                                            :value="(d: any) => d.count"
                                            :color="(d: any) => statusColors[d.status] ?? 'var(--color-chart-3)'"
                                            :arcWidth="30"
                                            :padAngle="0.02"
                                            :cornerRadius="4"
                                        />
                                    </VisSingleContainer>
                                </div>
                                <div class="space-y-2">
                                    <div
                                        v-for="item in charts.inspectionStatuses"
                                        :key="item.status"
                                        class="flex items-center justify-between gap-4 text-sm"
                                    >
                                        <div class="flex items-center gap-2">
                                            <div
                                                class="h-3 w-3 rounded-full"
                                                :style="{ backgroundColor: statusColors[item.status] ?? 'var(--color-chart-3)' }"
                                            />
                                            <span class="capitalize text-muted-foreground">{{ humanize(item.status) }}</span>
                                        </div>
                                        <span class="font-medium tabular-nums">{{ item.count }}</span>
                                    </div>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Inspection Types Bar -->
                    <Card class="lg:col-span-2">
                        <CardHeader>
                            <CardTitle>Inspections by Type</CardTitle>
                            <CardDescription>Breakdown of inspection types across your account</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="h-[300px]">
                                <VisXYContainer :data="charts.inspectionTypes">
                                    <VisGroupedBar
                                        :x="(_: any, i: number) => i"
                                        :y="(d: any) => d.count"
                                        :roundedCorners="4"
                                        :barPadding="0.3"
                                        :color="'var(--color-chart-1)'"
                                    />
                                    <VisAxis
                                        type="x"
                                        :tickFormat="(i: number) => charts.inspectionTypes[i] ? humanize(charts.inspectionTypes[i].type) : ''"
                                        :tickTextWidth="80"
                                    />
                                    <VisAxis type="y" :tickFormat="(v: number) => String(Math.round(v))" />
                                </VisXYContainer>
                            </div>
                        </CardContent>
                    </Card>
                </div>

            </div>
        </div>
    </AuthenticatedLayout>
</template>
