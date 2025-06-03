<script setup lang="ts">
import AdminLayout from '@/layouts/AppLayout.vue';
import { Head, useForm } from '@inertiajs/vue3';
import RoleForm from './RoleForm.vue';
import { Role, Permission } from '@/types';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';

interface Props {
    role: Role & { permissions: Permission[] }; // Papel com suas permissões atuais
    rolePermissions: string[]; // Apenas IDs das permissões do papel
    allPermissions: Permission[]; // Todas as permissões disponíveis
}

const props = defineProps<Props>();

const handleFormSubmit = (form: ReturnType<typeof useForm>) => {
    form.put(route('admin.roles.update', props.role.id), {
        onError: (errors) => {
            console.error("Erro ao atualizar papel:", errors);
        },
    });
};
</script>

<template>
    <Head :title="`Editar Papel: ${props.role.name}`" />
    <AdminLayout>
        <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-5xl mx-auto">
            <div class="mb-8">
                <h1 class="text-2xl md:text-3xl text-slate-800 dark:text-slate-100 font-bold">
                    Editar Papel: {{ props.role.name }}
                </h1>
            </div>
            <RoleForm
                :role="props.role"
                :allPermissions="props.allPermissions"
                @submit="handleFormSubmit"
                submitButtonText="Salvar Alterações"
                formAction="edit"
            />
        </div>
    </AdminLayout>
</template>
