<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Pagination from '@/components/Pagination.vue';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import {
    Dialog,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogClose,
} from '@/components/ui/dialog';
import { useToast } from '@/components/ui/toast/use-toast';
import { PlusCircle, Edit3, Trash2, MoreHorizontal, Search, Users as UsersIcon, ShieldCheck } from 'lucide-vue-next';
import type { BreadcrumbItem, User, Role, PaginatedResponse, SharedData } from '@/types';

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


interface UserWithRoles extends User {
    roles: Pick<Role, 'id' | 'name'>[];
}

interface Props {
    users: PaginatedResponse<UserWithRoles>;
    filters: { search?: string | null };
    // Props de permissão removidas do controller, mas o frontend pode precisar delas
    // Se a lógica de exibição de botões depender delas, elas precisarão ser passadas
    // ou a lógica do frontend ajustada. Por agora, vou assumir que os botões são sempre visíveis.
    // canCreateUsers?: boolean;
    // canUpdateUsers?: boolean;
    // canDeleteUsers?: boolean;
}

const props = defineProps<Props>();
const page = usePage<SharedData>();
const { toast } = useToast();

const localSearchTerm = ref(props.filters.search || '');
const showDeleteUserDialog = ref(false);
const userToDelete = ref<UserWithRoles | null>(null);

watch(localSearchTerm, (newTerm) => {
    debouncedApplySearch();
});

let searchTimeout: number | undefined = undefined;
const debouncedApplySearch = () => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = window.setTimeout(() => {
        applySearch();
    }, 400);
};

function applySearch() {
    router.get(route('admin.users.index'), { search: localSearchTerm.value || undefined }, {
        preserveState: true,
        replace: true,
        preserveScroll: true,
    });
}

const breadcrumbs: BreadcrumbItem[] = [
    
    { title: 'Admin', href: '#' },
    { title: 'Utilizadores', href: route('admin.users.index') }
];

const formatDate = (dateString: string | null | undefined): string => {
    if (!dateString) return 'N/A';
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric' });
    } catch (e) {
        return dateString || 'N/A';
    }
};

const openDeleteUserDialog = (user: UserWithRoles) => {
    userToDelete.value = user;
    showDeleteUserDialog.value = true;
};

const submitDeleteUser = () => {
    if (userToDelete.value) {
        router.delete(route('admin.users.destroy', userToDelete.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                showDeleteUserDialog.value = false;
                userToDelete.value = null;
                toast({ title: 'Sucesso!', description: 'Utilizador excluído com sucesso.' });
                // router.reload({ only: ['users'] }); // Opcional, se a lista não atualizar
            },
            onError: (errors) => {
                showDeleteUserDialog.value = false;
                const errorMessages = Object.values(errors).join(' ');
                toast({ title: 'Erro ao Excluir', description: errorMessages || 'Não foi possível excluir o utilizador.', variant: 'destructive' });
            }
        });
    }
};

const flashSuccess = computed(() => page.props.flash.success);
const flashError = computed(() => page.props.flash.error);

</script>

<template>
    <Head title="Gerir Utilizadores" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto px-4 py-8 sm:px-6 lg:px-8">
            <div class="max-w-7xl mx-auto space-y-6">
                <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                    <h1 class="text-2xl md:text-3xl text-slate-800 dark:text-slate-100 font-bold">
                        Gerir Utilizadores
                    </h1>
                    <!-- Assumindo que o botão é sempre visível ou a prop canCreateUsers é passada -->
                    <Link :href="route('admin.users.create')">
                        <Button variant="default">
                            <PlusCircle class="w-4 h-4 mr-2" />
                            Novo Utilizador
                        </Button>
                    </Link>
                </div>


                <Card class="shadow-xl">
                    <CardHeader>
                        <CardTitle>Lista de Utilizadores</CardTitle><br>
                        <CardDescription>Procure, visualize e gira os utilizadores do sistema.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="mb-4">
                            <div class="relative">
                                <Search class="absolute left-2.5 top-2.5 h-4 w-4 text-muted-foreground" />
                                <Input
                                    v-model="localSearchTerm"
                                    placeholder="Procurar por nome ou email..."
                                    class="pl-8 w-full sm:w-80 h-9"
                                />
                            </div>
                        </div>

                        <div class="border rounded-md overflow-hidden">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Nome</TableHead>
                                        <TableHead>Email</TableHead>
                                        <TableHead>Papéis</TableHead>
                                        <TableHead>Criado Em</TableHead>
                                        <TableHead class="text-right">Ações</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-if="!props.users.data || props.users.data.length === 0">
                                        <TableCell :colspan="5" class="text-center py-8 text-slate-500 dark:text-slate-400">
                                            Nenhum utilizador encontrado.
                                        </TableCell>
                                    </TableRow>
                                    <TableRow v-for="user in props.users.data" :key="user.id" class="hover:bg-slate-50 dark:hover:bg-slate-700/30">
                                        <TableCell class="font-medium">{{ user.name }}</TableCell>
                                        <TableCell>{{ user.email }}</TableCell>
                                        <TableCell>
                                            <div v-if="user.roles && user.roles.length > 0" class="flex flex-wrap gap-1">
                                                <span v-for="role in user.roles" :key="role.id"
                                                      class="px-2 py-0.5 text-xs rounded-full bg-blue-100 text-blue-800 dark:bg-blue-700/30 dark:text-blue-300">
                                                    {{ role.name }}
                                                </span>
                                            </div>
                                            <span v-else class="text-xs text-slate-500 italic">Nenhum papel</span>
                                        </TableCell>
                                        <TableCell class="text-sm text-slate-500 dark:text-slate-400">{{ formatDate(user.created_at) }}</TableCell>
                                        <TableCell class="text-right">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger as-child>
                                                    <Button variant="ghost" size="icon" class="h-8 w-8">
                                                        <MoreHorizontal class="w-4 h-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <!-- Assumindo que os botões são sempre visíveis ou as props can... são passadas -->
                                                    <DropdownMenuItem @click="router.get(route('admin.users.edit', user.id))">
                                                        <Edit3 class="w-4 h-4 mr-2" />Editar
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem @click="openDeleteUserDialog(user)" class="text-red-500 hover:!text-red-500">
                                                        <Trash2 class="w-4 h-4 mr-2" />Excluir
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                        <Pagination v-if="props.users.total > props.users.per_page" :pagination="props.users" class="mt-6" />
                    </CardContent>
                </Card>
            </div>
        </div>

        <Dialog :open="showDeleteUserDialog" @update:open="showDeleteUserDialog = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Confirmar Exclusão de Utilizador</DialogTitle>
                    <DialogDescription>
                        Tem certeza que deseja excluir o utilizador "{{ userToDelete?.name }}" ({{ userToDelete?.email }})? Esta ação não pode ser desfeita.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="mt-4">
                    <DialogClose as-child>
                        <Button variant="outline" @click="showDeleteUserDialog = false; userToDelete = null;">Cancelar</Button>
                    </DialogClose>
                    <Button variant="destructive" @click="submitDeleteUser" :disabled="router.processing"> <!-- Use router.processing se for uma ação do Inertia router -->
                        <Trash2 v-if="!router.processing" class="w-4 h-4 mr-2" />
                        <svg v-else class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ router.processing ? 'Excluindo...' : 'Excluir Utilizador' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

    </AppLayout>
</template>
