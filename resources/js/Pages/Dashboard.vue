<script setup lang="ts">
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout.vue';
import { Card, CardAction, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Head } from '@inertiajs/vue3';
import { Home, ClipboardCheck, CheckCircle, Clock, TrendingUp } from '@lucide/vue';
import { VisDonut, VisSingleContainer, VisGroupedBar, VisAxis, VisXYContainer, VisTooltip } from '@unovis/vue';
import { Donut, GroupedBar } from '@unovis/ts';
import { computed, type Component } from 'vue';

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

const rate = (part: number, total: number) => (total > 0 ? Math.round((part / total) * 100) : 0);
const completionRate = computed(() => rate(props.stats.completedInspections, props.stats.totalInspections));
const inProgressRate = computed(() => rate(props.stats.inProgressInspections, props.stats.totalInspections));

// Real totals from the API (meta.total), not the per_page=100 chart sample
const propertyTotal = computed(() => props.stats.totalProperties);
const inspectionStatusTotal = computed(() => props.stats.totalInspections);

type StatCard = {
    label: string;
    value: string;
    icon: Component;
    badge: string;
    trend: boolean;
    footer: string;
    sub: string;
};

const statCards = computed<StatCard[]>(() => [
    {
        label: 'Properties',
        value: props.stats.totalProperties.toLocaleString(),
        icon: Home,
        badge: 'Portfolio',
        trend: false,
        footer: 'Across your managed portfolio',
        sub: 'Total properties on record',
    },
    {
        label: 'Inspections',
        value: props.stats.totalInspections.toLocaleString(),
        icon: ClipboardCheck,
        badge: 'All time',
        trend: false,
        footer: 'Every inspection created',
        sub: 'Across all properties',
    },
    {
        label: 'Completed',
        value: props.stats.completedInspections.toLocaleString(),
        icon: CheckCircle,
        badge: `${completionRate.value}%`,
        trend: true,
        footer: 'Healthy completion rate',
        sub: `${completionRate.value}% of all inspections`,
    },
    {
        label: 'In Progress',
        value: props.stats.inProgressInspections.toLocaleString(),
        icon: Clock,
        badge: `${inProgressRate.value}%`,
        trend: true,
        footer: 'Currently being worked on',
        sub: `${inProgressRate.value}% of all inspections`,
    },
]);

const statusColors: Record<string, string> = {
    completed: 'var(--color-chart-2)',
    in_progress: 'var(--color-chart-1)',
    draft: 'var(--color-chart-4)',
    in_review: 'var(--color-chart-5)',
};

// Green palette for the property-types donut (light → dark)
const greenRamp = ['#bbf7d0', '#86efac', '#4ade80', '#22c55e', '#16a34a', '#15803d', '#166534'];

// Hover tooltips for the charts
const propertyTypeTriggers = {
    [Donut.selectors.segment]: (d: any) => `${humanize(d.data.type)}: ${d.data.count}`,
};
const inspectionStatusTriggers = {
    [Donut.selectors.segment]: (d: any) => `${humanize(d.data.status)}: ${d.data.count}`,
};
const inspectionTypeTriggers = {
    [GroupedBar.selectors.bar]: (d: any) => `${humanize(d.type)}: ${d.count}`,
};
</script>

<template>
    <Head title="Dashboard" />

    <AuthenticatedLayout>
        <template #header>
            <h2 class="text-xl font-semibold leading-tight text-foreground">Dashboard</h2>
        </template>

        <div class="py-8">
            <div class="mx-auto max-w-7xl space-y-6 px-4 sm:px-6 lg:px-8">
                <!-- Stat cards -->
                <div class="*:data-[slot=card]:from-primary/5 *:data-[slot=card]:to-card dark:*:data-[slot=card]:bg-card grid grid-cols-1 gap-4 *:data-[slot=card]:bg-gradient-to-t *:data-[slot=card]:shadow-xs grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                    <Card v-for="card in statCards" :key="card.label" class="@container/card">
                        <CardHeader>
                            <CardDescription>{{ card.label }}</CardDescription>
                            <CardTitle class="text-2xl font-semibold tabular-nums @[250px]/card:text-3xl">{{ card.value }}</CardTitle>
                            <CardAction>
                                <Badge variant="outline">
                                    <TrendingUp v-if="card.trend" />
                                    <component :is="card.icon" v-else />
                                    {{ card.badge }}
                                </Badge>
                            </CardAction>
                        </CardHeader>
                        <CardFooter class="flex-col items-start gap-1.5 text-sm">
                            <div class="line-clamp-1 flex items-center gap-2 font-medium">
                                {{ card.footer }}
                                <component :is="card.icon" class="size-4 text-muted-foreground" />
                            </div>
                            <div class="text-muted-foreground">{{ card.sub }}</div>
                        </CardFooter>
                    </Card>
                </div>

                <!-- Charts -->
                <div class="grid gap-6 lg:grid-cols-2">
                    <!-- Donuts (combined, charts only) -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Distribution</CardTitle>
                            <CardDescription>Properties by type and inspections by status</CardDescription>
                        </CardHeader>
                        <CardContent class="flex h-full items-center">
                            <div class="grid w-full grid-cols-1 gap-6 sm:grid-cols-2">
                                <div class="flex flex-col items-center gap-3">
                                    <div class="h-[180px] w-[180px] overflow-hidden">
                                        <VisSingleContainer :height="180" :data="charts.propertyTypes">
                                            <VisDonut
                                                :value="(d: any) => d.count"
                                                :color="(_d: any, i: number) => greenRamp[i % greenRamp.length]"
                                                :arc-width="30"
                                                :central-label-offset-y="10"
                                                :centralLabel="String(propertyTotal)"
                                                :centralSubLabel="'Properties'"
                                            />
                                            <VisTooltip :triggers="propertyTypeTriggers" />
                                        </VisSingleContainer>
                                    </div>
                                    <span class="text-sm font-medium text-muted-foreground">Property Types</span>
                                </div>
                                <div class="flex flex-col items-center gap-3">
                                    <div class="h-[180px] w-[180px] overflow-hidden">
                                        <VisSingleContainer :height="180" :data="charts.inspectionStatuses">
                                            <VisDonut
                                                :value="(d: any) => d.count"
                                                :color="(d: any) => statusColors[d.status] ?? 'var(--color-chart-3)'"
                                                :arc-width="30"
                                                :central-label-offset-y="10"
                                                :centralLabel="String(inspectionStatusTotal)"
                                                :centralSubLabel="'Inspections'"
                                            />
                                            <VisTooltip :triggers="inspectionStatusTriggers" />
                                        </VisSingleContainer>
                                    </div>
                                    <span class="text-sm font-medium text-muted-foreground">Inspection Status</span>
                                </div>
                            </div>
                        </CardContent>
                    </Card>

                    <!-- Inspections by Type Bar -->
                    <Card>
                        <CardHeader>
                            <CardTitle>Inspections by Type</CardTitle>
                            <CardDescription>Breakdown of inspection types across your account</CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="h-[300px]">
                                <VisXYContainer 
                                :data="charts.inspectionTypes" 
                                :height="300"
                                :y-domain="[0, undefined]"
                                >
                                    <VisGroupedBar
                                        :x="(_: any, i: number) => i"
                                        :y="(d: any) => d.count"
                                        :roundedCorners="10"
                                        :color="'var(--color-primary)'"
                                    />
                                    <VisTooltip :triggers="inspectionTypeTriggers" />
                                    <VisAxis
                                        type="x"
                                        :gridLine="false"
                                        :tickFormat="(i: number) => charts.inspectionTypes[i] ? humanize(charts.inspectionTypes[i].type) : ''"
                                        :tickTextWidth="80"
                                    />
                                    <VisAxis type="y" :tickFormat="(v: number) => String(Math.round(v))"
                                    />
                                </VisXYContainer>
                            </div>
                        </CardContent>
                    </Card>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>
</template>
