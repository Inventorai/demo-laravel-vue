<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Link, useForm, usePage } from '@inertiajs/vue3';

defineProps<{
    mustVerifyEmail?: Boolean;
    status?: String;
}>();

const user = usePage().props.auth.user;

const form = useForm({
    name: user.name,
    email: user.email,
});
</script>

<template>
    <form
        @submit.prevent="form.patch(route('profile.update'))"
        class="space-y-6"
    >
        <div class="space-y-2">
            <Label for="name">Name</Label>
            <Input
                id="name"
                type="text"
                v-model="form.name"
                required
                autofocus
                autocomplete="name"
            />
            <InputError :message="form.errors.name" />
        </div>

        <div class="space-y-2">
            <Label for="email">Email</Label>
            <Input
                id="email"
                type="email"
                v-model="form.email"
                required
                autocomplete="username"
            />
            <InputError :message="form.errors.email" />
        </div>

        <div v-if="mustVerifyEmail && user.email_verified_at === null">
            <p class="text-sm text-foreground">
                Your email address is unverified.
                <Link
                    :href="route('verification.send')"
                    method="post"
                    as="button"
                    class="text-sm text-muted-foreground underline-offset-4 hover:underline"
                >
                    Click here to re-send the verification email.
                </Link>
            </p>

            <div
                v-show="status === 'verification-link-sent'"
                class="mt-2 text-sm font-medium text-green-600"
            >
                A new verification link has been sent to your email address.
            </div>
        </div>

        <div class="flex items-center gap-4">
            <Button type="submit" :disabled="form.processing">Save</Button>

            <Transition
                enter-active-class="transition ease-in-out"
                enter-from-class="opacity-0"
                leave-active-class="transition ease-in-out"
                leave-to-class="opacity-0"
            >
                <p
                    v-if="form.recentlySuccessful"
                    class="text-sm text-muted-foreground"
                >
                    Saved.
                </p>
            </Transition>
        </div>
    </form>
</template>
