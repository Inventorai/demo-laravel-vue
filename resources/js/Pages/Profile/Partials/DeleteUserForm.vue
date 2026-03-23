<script setup lang="ts">
import InputError from '@/components/InputError.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    AlertDialog,
    AlertDialogAction,
    AlertDialogCancel,
    AlertDialogContent,
    AlertDialogDescription,
    AlertDialogFooter,
    AlertDialogHeader,
    AlertDialogTitle,
    AlertDialogTrigger,
} from '@/components/ui/alert-dialog';
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';

const dialogOpen = ref(false);
const passwordInput = ref<HTMLInputElement | null>(null);

const form = useForm({
    password: '',
});

const deleteUser = () => {
    form.delete(route('profile.destroy'), {
        preserveScroll: true,
        onSuccess: () => {
            dialogOpen.value = false;
        },
        onError: () => passwordInput.value?.focus(),
        onFinish: () => {
            form.reset();
        },
    });
};

const closeDialog = () => {
    dialogOpen.value = false;
    form.clearErrors();
    form.reset();
};
</script>

<template>
    <div>
        <p class="text-sm text-muted-foreground">
            Before deleting your account, please download any data or information that you wish to retain.
        </p>

        <AlertDialog v-model:open="dialogOpen">
            <AlertDialogTrigger as-child>
                <Button variant="destructive" class="mt-4">
                    Delete Account
                </Button>
            </AlertDialogTrigger>
            <AlertDialogContent>
                <AlertDialogHeader>
                    <AlertDialogTitle>Are you sure you want to delete your account?</AlertDialogTitle>
                    <AlertDialogDescription>
                        Once your account is deleted, all of its resources and data
                        will be permanently deleted. Please enter your password to
                        confirm you would like to permanently delete your account.
                    </AlertDialogDescription>
                </AlertDialogHeader>

                <div class="space-y-2">
                    <Label for="delete-password" class="sr-only">Password</Label>
                    <Input
                        id="delete-password"
                        ref="passwordInput"
                        v-model="form.password"
                        type="password"
                        placeholder="Password"
                        @keyup.enter="deleteUser"
                    />
                    <InputError :message="form.errors.password" />
                </div>

                <AlertDialogFooter>
                    <AlertDialogCancel @click="closeDialog">Cancel</AlertDialogCancel>
                    <AlertDialogAction
                        @click="deleteUser"
                        :disabled="form.processing"
                        class="bg-destructive text-destructive-foreground hover:bg-destructive/90"
                    >
                        Delete Account
                    </AlertDialogAction>
                </AlertDialogFooter>
            </AlertDialogContent>
        </AlertDialog>
    </div>
</template>
