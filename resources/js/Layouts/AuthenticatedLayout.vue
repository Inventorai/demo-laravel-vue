<script setup lang="ts">
import { ref } from 'vue';
import ApplicationLogo from '@/components/ApplicationLogo.vue';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Sheet,
    SheetContent,
    SheetHeader,
    SheetTitle,
    SheetTrigger,
} from '@/components/ui/sheet';
import { Separator } from '@/components/ui/separator';
import { Link, router, usePage } from '@inertiajs/vue3';
import { Menu, ChevronDown, User, LogOut, Settings } from '@lucide/vue';
import { useEcho } from '@/composables/useEcho';

const echo = useEcho();
const page = usePage();
const teamId = (page.props as any).team_id;

if (echo && teamId) {
    echo.private(`team.${teamId}`).listen('.test.ping', (e: any) => {
        console.log('Received event:', e);
    });
}

const mobileOpen = ref(false);

const logout = () => {
    router.post(route('logout'));
};
</script>

<template>
    <div class="min-h-screen bg-background">
        <nav class="border-b bg-card">
            <div class="mx-auto flex h-16 max-w-7xl items-center justify-between px-4 sm:px-6 lg:px-8">
                <div class="flex items-center gap-8">
                    <Link :href="route('dashboard')">
                        <ApplicationLogo class="h-12 rounded-md" />
                    </Link>

                    <div class="hidden items-center gap-1 sm:flex">
                        <Button variant="ghost" as-child>
                            <Link :href="route('dashboard')" :class="route().current('dashboard') ? 'bg-accent' : ''">
                                Dashboard
                            </Link>
                        </Button>
                        <Button variant="ghost" as-child>
                            <Link :href="route('properties.index')" :class="route().current('properties.*') ? 'bg-accent' : ''">
                                Properties
                            </Link>
                        </Button>
                        <Button variant="ghost" as-child>
                            <Link :href="route('inspections.index')" :class="route().current('inspections.*') ? 'bg-accent' : ''">
                                Inspections
                            </Link>
                        </Button>
                    </div>
                </div>

                <div class="hidden sm:flex sm:items-center sm:gap-2">

                    <DropdownMenu>
                        <DropdownMenuTrigger as-child>
                            <Button variant="ghost" class="gap-2">
                                {{ $page.props.auth.user.name }}
                                <ChevronDown class="h-4 w-4" />
                            </Button>
                        </DropdownMenuTrigger>
                        <DropdownMenuContent align="end" class="w-48">
                            <DropdownMenuItem as-child>
                                <Link :href="route('profile.edit')" class="flex items-center gap-2">
                                    <User class="h-4 w-4" />
                                    Profile
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuItem as-child>
                                <Link :href="route('settings')" class="flex items-center gap-2">
                                    <Settings class="h-4 w-4" />
                                    Settings
                                </Link>
                            </DropdownMenuItem>
                            <DropdownMenuSeparator />
                            <DropdownMenuItem @click="logout" class="flex items-center gap-2">
                                <LogOut class="h-4 w-4" />
                                Log Out
                            </DropdownMenuItem>
                        </DropdownMenuContent>
                    </DropdownMenu>
                </div>

                <div class="sm:hidden">
                    <Sheet v-model:open="mobileOpen">
                        <SheetTrigger as-child>
                            <Button variant="ghost" size="icon">
                                <Menu class="h-5 w-5" />
                            </Button>
                        </SheetTrigger>
                        <SheetContent side="right" class="w-72">
                            <SheetHeader>
                                <SheetTitle>Navigation</SheetTitle>
                            </SheetHeader>
                            <div class="mt-6 flex flex-col gap-2">
                                <Button variant="ghost" class="justify-start" as-child>
                                    <Link :href="route('dashboard')" @click="mobileOpen = false">Dashboard</Link>
                                </Button>
                                <Button variant="ghost" class="justify-start" as-child>
                                    <Link :href="route('properties.index')" @click="mobileOpen = false">Properties</Link>
                                </Button>
                                <Button variant="ghost" class="justify-start" as-child>
                                    <Link :href="route('inspections.index')" @click="mobileOpen = false">Inspections</Link>
                                </Button>

                                <Separator class="my-2" />

                                <div class="px-3 py-2">
                                    <p class="text-sm font-medium">{{ $page.props.auth.user.name }}</p>
                                    <p class="text-sm text-muted-foreground">{{ $page.props.auth.user.email }}</p>
                                </div>

                                <Button variant="ghost" class="justify-start" as-child>
                                    <Link :href="route('profile.edit')" @click="mobileOpen = false">Profile</Link>
                                </Button>
                                <Button variant="ghost" class="justify-start" as-child>
                                    <Link :href="route('settings')" @click="mobileOpen = false">Settings</Link>
                                </Button>
                                <Button variant="ghost" class="justify-start" @click="logout">
                                    Log Out
                                </Button>
                            </div>
                        </SheetContent>
                    </Sheet>
                </div>
            </div>
        </nav>

        <header v-if="$slots.header" class="border-b bg-card">
            <div class="mx-auto max-w-7xl px-4 py-6 sm:px-6 lg:px-8">
                <slot name="header" />
            </div>
        </header>

        <div v-if="$page.props.flash?.error" class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
            <div class="rounded-lg border border-red-200 bg-red-50 p-4 text-sm text-red-800 dark:border-red-800 dark:bg-red-950 dark:text-red-200">
                {{ $page.props.flash.error }}
            </div>
        </div>

        <div v-if="$page.props.flash?.success" class="mx-auto max-w-7xl px-4 pt-6 sm:px-6 lg:px-8">
            <div class="rounded-lg border border-green-200 bg-green-50 p-4 text-sm text-green-800 dark:border-green-800 dark:bg-green-950 dark:text-green-200">
                {{ $page.props.flash.success }}
            </div>
        </div>

        <main>
            <slot />
        </main>
    </div>
</template>
