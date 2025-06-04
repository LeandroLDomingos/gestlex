<script setup lang="ts">
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Checkbox } from '@/components/ui/checkbox'; // Se for usar checkboxes para papéis
import { Separator } from '@/components/ui/separator';
import { Save, ArrowLeft, ShieldCheck } from 'lucide-vue-next';
import type { BreadcrumbItem, Role, SharedData } from '@/types';
import { useToast } from '@/components/ui/toast/use-toast';

// Helper function for Ziggy routes
const RGlobal = (window as any).route;
const route = (name?: string, params?: any, absolute?: boolean): string => {
    if (typeof RGlobal === 'function') { return RGlobal(name, params, absolute); }
    console.warn(`Helper de rota Ziggy não encontrado para a rota: ${name}. Usando fallback.`);
    let url = `/${name?.replace(/\./g, '/') || ''}`;
    if (params) {
        if (typeof params === 'object' && params !== null && !Array.isArray(params)) {
            Object.keys(params).forEach(key => {
                const paramPlaceholder = `:${key}`; const paramPlaceholderBraces = `{${key}}`;
                if (url.includes(paramPlaceholder)) { url = url.replace(paramPlaceholder, String(params[key])); }
                else if (url.includes(paramPlaceholderBraces)) { url = url.replace(paramPlaceholderBraces, String(params[key])); }
                else if (Object.keys(params).length === 1 && !url.includes(String(params[key]))) {
                    const paramValueString = String(params[key]);
                    if (url.split('/').pop() !== paramValueString) { url += `/${paramValueString}`; }
                }
            });
        } else if (typeof params !== 'object') { url += `/${params}`; }
    }
    return url;
};

interface Props {
    roles: Pick<Role, 'id' | 'name'>[]; // Lista de todos os papéis disponíveis
    // canAssignRoles?: boolean; // Prop removida do controller, assumindo que a atribuição é sempre permitida aqui
    errors?: Record<string, string>;
}

const props = defineProps<Props>();
const { toast } = useToast();

const form = useForm({
    name: '',
    email: '',
    password: '',
    password_confirmation: '',
    roles: [] as string[], // Array de IDs dos papéis selecionados
});

const submitForm = () => {
    form.post(route('admin.users.store'), {
        onSuccess: () => {
            toast({ title: 'Sucesso!', description: 'Utilizador criado com sucesso.' });
            // form.reset(); // Opcional: limpar formulário após sucesso
        },
        onError: (formErrors) => {
            console.error("Erros ao criar utilizador:", formErrors);
            // O Inertia já trata de exibir os erros, mas pode adicionar um toast geral
            if (Object.keys(formErrors).length > 0) {
                 toast({ title: 'Erro de Validação', description: 'Por favor, corrija os erros no formulário.', variant: 'destructive' });
            } else {
                 toast({ title: 'Erro Inesperado', description: 'Não foi possível criar o utilizador.', variant: 'destructive' });
            }
        }
    });
};

const breadcrumbs: BreadcrumbItem[] = [
    
    { title: 'Admin', href: '#' },
    { title: 'Utilizadores', href: route('admin.users.index') },
    { title: 'Novo Utilizador' }
];

</script>

<template>
    <Head title="Novo Utilizador" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto px-4 py-8 sm:px-6 lg:px-8">
            <div class="max-w-2xl mx-auto">
                <div class="flex justify-between items-center mb-6">
                    <h1 class="text-2xl md:text-3xl text-slate-800 dark:text-slate-100 font-bold">
                        Criar Novo Utilizador
                    </h1>
                    <Link :href="route('admin.users.index')">
                        <Button variant="outline">
                            <ArrowLeft class="w-4 h-4 mr-2" />
                            Voltar para Lista
                        </Button>
                    </Link>
                </div>

                <Card class="shadow-xl">
                    <form @submit.prevent="submitForm">
                        <CardHeader>
                            <CardTitle>Detalhes do Utilizador</CardTitle>
                            <CardDescription>Preencha as informações abaixo para criar um novo utilizador.</CardDescription>
                        </CardHeader>
                        <CardContent class="space-y-6">
                            <div>
                                <Label for="name">Nome Completo</Label>
                                <Input id="name" v-model="form.name" type="text" class="mt-1 block w-full" required />
                                <div v-if="form.errors.name" class="text-sm text-red-600 mt-1">{{ form.errors.name }}</div>
                            </div>

                            <div>
                                <Label for="email">Email</Label>
                                <Input id="email" v-model="form.email" type="email" class="mt-1 block w-full" required />
                                <div v-if="form.errors.email" class="text-sm text-red-600 mt-1">{{ form.errors.email }}</div>
                            </div>

                            <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                                <div>
                                    <Label for="password">Senha</Label>
                                    <Input id="password" v-model="form.password" type="password" class="mt-1 block w-full" required />
                                    <div v-if="form.errors.password" class="text-sm text-red-600 mt-1">{{ form.errors.password }}</div>
                                </div>
                                <div>
                                    <Label for="password_confirmation">Confirmar Senha</Label>
                                    <Input id="password_confirmation" v-model="form.password_confirmation" type="password" class="mt-1 block w-full" required />
                                </div>
                            </div>
                            
                            <Separator />

                            <div>
                                <h3 class="text-lg font-medium text-slate-800 dark:text-slate-100 mb-2 flex items-center">
                                    <ShieldCheck class="w-5 h-5 mr-2 text-blue-600 dark:text-blue-400" />
                                    Atribuir Papéis
                                </h3>
                                <p class="text-sm text-muted-foreground mb-4">Selecione os papéis que este utilizador terá.</p>
                                <div v-if="props.roles && props.roles.length > 0" class="space-y-3 max-h-60 overflow-y-auto p-1 rounded-md border">
                                    <div v-for="role in props.roles" :key="role.id" class="flex items-center space-x-3 p-2 hover:bg-slate-50 dark:hover:bg-slate-700/50 rounded">
                                        <input
                                            type="checkbox"
                                            :id="`role-${role.id}`"
                                            :value="role.id"
                                            v-model="form.roles"
                                            class="h-4 w-4 rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500 dark:border-gray-600 dark:bg-gray-700 dark:checked:bg-indigo-500 dark:focus:ring-indigo-600 dark:ring-offset-gray-800"
                                        />
                                        <Label :for="`role-${role.id}`" class="text-sm font-medium text-slate-700 dark:text-slate-200 cursor-pointer">
                                            {{ role.name }}
                                        </Label>
                                    </div>
                                </div>
                                <p v-else class="text-sm text-slate-500 italic">Nenhum papel disponível para atribuição.</p>
                                <div v-if="form.errors.roles" class="text-sm text-red-600 mt-1">{{ form.errors.roles }}</div>
                            </div>

                        </CardContent>
                        <CardFooter class="flex justify-end pt-6">
                            <Button type="submit" :disabled="form.processing">
                                <Save class="w-4 h-4 mr-2" />
                                {{ form.processing ? 'Criando...' : 'Criar Utilizador' }}
                            </Button>
                        </CardFooter>
                    </form>
                </Card>
            </div>
        </div>
    </AppLayout>
</template>
