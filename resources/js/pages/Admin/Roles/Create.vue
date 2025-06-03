<script setup lang="ts">
import AdminLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import RoleForm from './RoleForm.vue';
import { Permission } from '@/types';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';

interface Props {
    allPermissions: Permission[];
}
const props = defineProps<Props>();

const handleFormSubmit = (form: ReturnType<typeof useForm>) => {
    form.post(route('admin.roles.store'), {
        onError: (errors) => {
            // A mensagem de erro geral será tratada pelo formulário ou por um toast global
            console.error("Erro ao criar papel:", errors);
        },
    });
};
</script>

<template>
    <Head title="Novo Papel" />
    <AdminLayout>
        <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-5xl mx-auto">
            <div class="mb-8">
                <h1 class="text-2xl md:text-3xl text-slate-800 dark:text-slate-100 font-bold">Criar Novo Papel</h1>
            </div>
            <RoleForm
                :allPermissions="props.allPermissions"
                @submit="handleFormSubmit"
                submitButtonText="Criar Papel"
                formAction="create"
            />
        </div>
    </AdminLayout>
</template>
