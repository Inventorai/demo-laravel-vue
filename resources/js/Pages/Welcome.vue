<script setup lang="ts">
import ApplicationLogo from '@/components/ApplicationLogo.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Head, Link } from '@inertiajs/vue3';

defineProps<{
    canLogin?: boolean;
    canRegister?: boolean;
    laravelVersion: string;
    phpVersion: string;
}>();
</script>

<template>
    <Head title="Welcome" />

    <div class="flex min-h-screen flex-col items-center justify-center bg-background px-4">
        <div class="w-full max-w-2xl space-y-8">
            <div class="flex flex-col items-center gap-4">
                <div>
                    <img src="/images/logo/iai_logo.svg" alt="Inventorai" class="h-16 rounded-full" />
                </div>
                <h1 class="text-3xl font-bold tracking-tight text-foreground">
                    Inventorai SDK Demo App
                </h1>
                <p class="text-muted-foreground">
                    Explore the Inventorai SDK integration with Laravel
                </p>
            </div>

            <div v-if="canLogin" class="flex items-center justify-center gap-4">
                <Button as-child>
                    <Link :href="route('login')">Log in</Link>
                </Button>
                <Button v-if="canRegister" variant="outline" as-child>
                    <Link :href="route('register')">Register</Link>
                </Button>
            </div>

            <div class="grid gap-6 sm:grid-cols-2">
                <Card>
                    <CardHeader>
                        <CardTitle>Documentation</CardTitle>
                        <CardDescription>
                            Read the Inventorai SDK documentation for guides, API references, and integration examples.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Button variant="link" class="h-auto p-0" as-child>
                            <a href="https://docs.inventorai.co.uk" target="_blank" rel="noopener noreferrer">
                                Read the docs &rarr;
                            </a>
                        </Button>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Inventorai</CardTitle>
                        <CardDescription>
                            Learn more about Inventorai and how it can help streamline your property inventory workflows.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <Button variant="link" class="h-auto p-0" as-child>
                            <a href="https://inventorai.co.uk" target="_blank" rel="noopener noreferrer">
                                Visit our website &rarr;
                            </a>
                        </Button>
                    </CardContent>
                </Card>
            </div>
        </div>
    </div>
</template>
