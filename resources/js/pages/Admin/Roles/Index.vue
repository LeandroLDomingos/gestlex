<script setup lang="ts">
import { Head, useForm, usePage, Link, router } from '@inertiajs/vue3';
import { watch, computed, ref } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import type { BreadcrumbItem, Permission, Role, SharedData, PaginatedResponse } from '@/types'; // Adicionado PaginatedResponse
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectGroup, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Card, CardContent, CardDescription, CardFooter, CardHeader, CardTitle } from '@/components/ui/card';
import { ScrollArea } from '@/components/ui/scroll-area';
import { Separator } from '@/components/ui/separator';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
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
import { PlusCircle, Edit3, Save, MoreHorizontal, Trash2, ShieldCheck, Users, FilePenLine } from 'lucide-vue-next';
import Pagination from '@/components/Pagination.vue'; // Assumindo que você tem este componente

// Props via Inertia
interface Props {
    roles: PaginatedResponse<Role & { permissions: Pick<Permission, 'id'>[]; users_count?: number; permissions_count?: number }>; // Papéis paginados
    allPermissions: Permission[]; // Todas as permissões disponíveis
    filters: { search?: string; role_id?: string | null }; // Filtros, incluindo role_id para pré-seleção
    canCreateRoles: boolean;
    canUpdateRoles: boolean;
    canDeleteRoles: boolean;
    canManageRolePermissions: boolean; // Nova permissão para gerir permissões de papéis
}

const props = defineProps<Props>();
const page = usePage<SharedData>();
const { toast } = useToast();

// Form para sincronizar permissões
const permissionsForm = useForm({
    role_id: props.filters.role_id || (props.roles.data.length > 0 ? props.roles.data[0]?.id : null),
    permissions: [] as string[],
});

// Estado para diálogo de exclusão de Papel
const showDeleteRoleDialog = ref(false);
const roleToDelete = ref<Role | null>(null);

// Carrega as permissões do papel selecionado
watch(
    () => permissionsForm.role_id,
    (newRoleId) => {
        if (newRoleId) {
            const selectedRole = props.roles.data.find(r => r.id === newRoleId);
            permissionsForm.permissions = selectedRole?.permissions.map(p => p.id) || [];
        } else {
            permissionsForm.permissions = [];
        }
        // Atualiza a URL sem recarregar a página para manter o estado do filtro role_id
        router.get(route('admin.roles.index'), { role_id: newRoleId }, { preserveState: true, replace: true, preserveScroll: true });
    },
    { immediate: true }
);

// Mapeamento de prefixos de permissão para rótulos de grupo
const prefixLabelMap: Record<string, string> = {
    locations: 'Locais',
    timeslots: 'Horários',
    appointments: 'Agendamentos',
    documents: 'Documentos',
    users: 'Usuários',
    roles: 'Papéis (Roles)', // Ajustado
    permissions: 'Permissões (Sistema)', // Ajustado
    processes: 'Casos/Processos',
    contacts: 'Contatos',
    tasks: 'Tarefas',
    'financial-transactions': 'Financeiro',
    reports: 'Relatórios',
    settings: 'Configurações',
    // Adicione mais conforme necessário
};

// Agrupa as permissões para exibição
const groupedPermissions = computed(() => {
    const groups: Record<string, { label: string; permissions: Permission[] }> = {};
    props.allPermissions.forEach(p => {
        const prefix = p.name.split('.')[0] || 'outras';
        const groupLabel = prefixLabelMap[prefix] || prefix.charAt(0).toUpperCase() + prefix.slice(1);

        if (!groups[groupLabel]) {
            groups[groupLabel] = { label: groupLabel, permissions: [] };
        }
        groups[groupLabel].permissions.push(p);
    });
    return Object.values(groups).sort((a, b) => a.label.localeCompare(b.label));
});

// Rótulo de exibição para permissões individuais
const getPermissionDisplayLabel = (permission: Permission): string => {
    const parts = permission.name.split('.');
    let action = parts.pop() || permission.name;
    let resource = parts.join('.');

    const actionTranslations: Record<string, string> = {
        index: 'Listar', create: 'Criar', store: 'Salvar (Novo)', show: 'Ver Detalhes',
        edit: 'Editar', update: 'Atualizar', destroy: 'Excluir', view: 'Visualizar',
        viewAny: 'Listar Todos', manage: 'Gerenciar', sync: 'Sincronizar', assign: 'Atribuir',
        'permissions.show': 'Ver Permissões de Papel', // Específico para a rota de permissões
        'permissions.sync': 'Sincronizar Permissões de Papel',
    };
    action = actionTranslations[action] || action.charAt(0).toUpperCase() + action.slice(1);
    resource = resource.replace(/[-_]/g, ' ').split('.').map(s => s.charAt(0).toUpperCase() + s.slice(1)).join(' ');
    return permission.description ? `${permission.description}` : `${action} ${resource}`;
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Painel', href: route('dashboard') },
    { title: 'Admin', href: '#' }, // Ou link para uma página de admin geral
    { title: 'Papéis e Permissões', href: route('admin.roles.index') }
];

// Submete o formulário para atualizar as permissões do papel
function syncPermissions() {
    if (!permissionsForm.role_id) {
        toast({ title: 'Erro', description: 'Selecione um papel para atualizar as permissões.', variant: 'destructive' });
        return;
    }
    permissionsForm.put(route('admin.roles.permissions.sync', permissionsForm.role_id), {
        preserveScroll: true,
        onSuccess: () => {
             toast({ title: 'Sucesso!', description: 'Permissões atualizadas.' });
             // Para forçar a atualização da lista de permissões do papel no prop `roles` após o sucesso:
             router.reload({ only: ['roles'] });
        },
        onError: (errors) => {
            const errorMessages = Object.values(errors).join(' ');
            toast({ title: 'Erro ao Atualizar', description: errorMessages || 'Não foi possível atualizar as permissões.', variant: 'destructive' });
        }
    });
}

// Funções para o diálogo de exclusão de Papel
const openDeleteRoleDialog = (role: Role) => {
    roleToDelete.value = role;
    showDeleteRoleDialog.value = true;
};

const submitDeleteRole = () => {
    if (roleToDelete.value) {
        router.delete(route('admin.roles.destroy', roleToDelete.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                showDeleteRoleDialog.value = false;
                roleToDelete.value = null;
                if (permissionsForm.role_id === roleToDelete.value?.id) { // Se o papel excluído era o selecionado
                    permissionsForm.role_id = props.roles.data.length > 0 ? props.roles.data[0]?.id : null;
                }
            },
            onError: (errors) => {
                showDeleteRoleDialog.value = false;
                const errorMessages = Object.values(errors).join(' ');
                toast({ title: 'Erro ao Excluir', description: errorMessages || 'Não foi possível excluir o papel.', variant: 'destructive'});
            }
        });
    }
};

const isRoleSelected = computed(() => !!permissionsForm.role_id);
const selectedRoleForDisplay = computed(() => props.roles.data.find(r => r.id === permissionsForm.role_id));

const flashSuccess = computed(() => page.props.flash.success);
const flashError = computed(() => page.props.flash.error);

</script>

<template>
    <Head title="Papéis e Permissões" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto px-4 py-8 sm:px-6 lg:px-8">
            <div class="max-w-7xl mx-auto space-y-8">

                <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                    <h1 class="text-2xl md:text-3xl text-slate-800 dark:text-slate-100 font-bold">
                        Papéis e Permissões
                    </h1>
                    <div class="flex gap-2">
                        <Link v-if="props.canCreateRoles" :href="route('admin.roles.create')">
                            <Button variant="outline">
                                <PlusCircle class="w-4 h-4 mr-2" />
                                Novo Papel
                            </Button>
                        </Link>
                        <Button v-if="props.canManageRolePermissions" @click="syncPermissions" :disabled="permissionsForm.processing || !isRoleSelected">
                            <Save class="w-4 h-4 mr-2" />
                            {{ permissionsForm.processing ? 'Salvando...' : 'Salvar Permissões' }}
                        </Button>
                    </div>
                </div>

                <div v-if="flashSuccess" class="mb-4 p-3 bg-green-100 dark:bg-green-700 border border-green-400 dark:border-green-600 text-green-700 dark:text-green-100 rounded-md shadow-sm">{{ flashSuccess }}</div>
                <div v-if="flashError" class="mb-4 p-3 bg-red-100 dark:bg-red-700 border border-red-400 dark:border-red-600 text-red-700 dark:text-red-100 rounded-md shadow-sm">{{ flashError }}</div>

                <!-- Secção de Listagem e Seleção de Papéis -->
                <Card class="shadow-xl">
                    <CardHeader>
                        <CardTitle>Listagem de Papéis</CardTitle>
                        <CardDescription>Selecione um papel abaixo para gerenciar suas permissões ou clique em "Novo Papel".</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="mb-4">
                            <Label for="role-select-table" class="block text-sm font-medium text-slate-700 dark:text-slate-300 mb-1">
                                Selecionar Papel para Editar Permissões:
                            </Label>
                            <Select v-model="permissionsForm.role_id">
                                <SelectTrigger id="role-select-table" class="w-full sm:w-1/2 md:w-1/3">
                                    <SelectValue placeholder="-- Escolha um papel --" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem v-if="!props.roles.data || props.roles.data.length === 0" value="no-roles-available" disabled>
                                            Nenhum papel cadastrado
                                        </SelectItem>
                                        <SelectItem v-for="role in props.roles.data" :key="`select-${role.id}`" :value="role.id">
                                            {{ role.name }} (Nível: {{ role.level }})
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>

                        <div class="border rounded-md overflow-hidden">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Nome</TableHead>
                                        <TableHead>Descrição</TableHead>
                                        <TableHead class="text-center">Nível</TableHead>
                                        <TableHead class="text-center">Usuários</TableHead>
                                        <TableHead class="text-center">Permissões</TableHead>
                                        <TableHead class="text-right">Ações</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-if="!props.roles.data || props.roles.data.length === 0">
                                        <TableCell :colspan="6" class="text-center py-4 text-slate-500 dark:text-slate-400">Nenhum papel cadastrado.</TableCell>
                                    </TableRow>
                                    <TableRow v-for="role in props.roles.data" :key="role.id"
                                              :class="{'bg-slate-100 dark:bg-slate-700/50': permissionsForm.role_id === role.id}"
                                              class="hover:bg-slate-50 dark:hover:bg-slate-700/30 cursor-pointer"
                                              @click="permissionsForm.role_id = role.id">
                                        <TableCell class="font-medium">{{ role.name }}</TableCell>
                                        <TableCell class="text-sm text-slate-600 dark:text-slate-300">{{ role.description || '-' }}</TableCell>
                                        <TableCell class="text-center text-sm">{{ role.level }}</TableCell>
                                        <TableCell class="text-center text-sm">
                                            <div class="inline-flex items-center"><Users class="w-3.5 h-3.5 mr-1 text-slate-400" />{{ role.users_count }}</div>
                                        </TableCell>
                                        <TableCell class="text-center text-sm">
                                            <div class="inline-flex items-center"><ShieldCheck class="w-3.5 h-3.5 mr-1 text-slate-400" />{{ role.permissions_count }}</div>
                                        </TableCell>
                                        <TableCell class="text-right">
                                            <DropdownMenu>
                                                <DropdownMenuTrigger as-child>
                                                    <Button variant="ghost" size="icon" class="h-8 w-8">
                                                        <MoreHorizontal class="w-4 h-4" />
                                                    </Button>
                                                </DropdownMenuTrigger>
                                                <DropdownMenuContent align="end">
                                                    <DropdownMenuItem v-if="props.canUpdateRoles" @click.stop="router.get(route('admin.roles.edit', role.id))">
                                                        <FilePenLine class="w-4 h-4 mr-2" />Editar Detalhes
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem v-if="props.canManageRolePermissions" @click.stop="permissionsForm.role_id = role.id">
                                                        <ShieldCheck class="w-4 h-4 mr-2" />Gerir Permissões
                                                    </DropdownMenuItem>
                                                    <DropdownMenuItem v-if="props.canDeleteRoles" @click.stop="openDeleteRoleDialog(role)" class="text-red-500 hover:!text-red-500">
                                                        <Trash2 class="w-4 h-4 mr-2" />Excluir Papel
                                                    </DropdownMenuItem>
                                                </DropdownMenuContent>
                                            </DropdownMenu>
                                        </TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                        </div>
                        <Pagination v-if="props.roles.total > props.roles.per_page" :pagination="props.roles" class="mt-6" />
                    </CardContent>
                </Card>

                <Separator class="my-10" />

                <!-- Secção de Edição de Permissões -->
                <div v-if="isRoleSelected" class="mt-8">
                    <Card class="shadow-xl">
                        <CardHeader>
                            <CardTitle>Permissões para: {{ selectedRoleForDisplay?.name }}</CardTitle>
                            <CardDescription>
                                Selecione as permissões que este papel deverá ter.
                            </CardDescription>
                        </CardHeader>
                        <CardContent>
                            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-x-6 gap-y-8">
                                <div v-for="group in groupedPermissions" :key="group.label">
                                    <h3 class="text-md font-semibold mb-3 text-slate-700 dark:text-slate-200 border-b pb-2 border-slate-200 dark:border-slate-700">
                                        {{ group.label }}
                                    </h3>
                                    <ScrollArea class="h-auto max-h-72 pr-3"> {/* Ajuste max-h */}
                                        <div class="space-y-3">
                                            <div v-for="permission in group.permissions" :key="permission.id" class="flex items-start space-x-3 py-1">
                                                <Checkbox
                                                    :id="`perm-${permission.id}`"
                                                    :value="permission.id"
                                                    :checked="permissionsForm.permissions.includes(permission.id)"
                                                    @update:checked="(checkedState) => {
                                                        if (checkedState) {
                                                            if (!permissionsForm.permissions.includes(permission.id)) {
                                                                permissionsForm.permissions.push(permission.id);
                                                            }
                                                        } else {
                                                            permissionsForm.permissions = permissionsForm.permissions.filter(pId => pId !== permission.id);
                                                        }
                                                    }"
                                                    class="mt-1 shrink-0"
                                                />
                                                <Label :for="`perm-${permission.id}`" class="flex flex-col cursor-pointer text-sm">
                                                    <span class="font-normal text-slate-700 dark:text-slate-200 leading-tight">
                                                        {{ getPermissionDisplayLabel(permission) }}
                                                    </span>
                                                    <span class="text-xs text-slate-500 dark:text-slate-400">
                                                        (Permissão: {{ permission.name }})
                                                    </span>
                                                </Label>
                                            </div>
                                        </div>
                                    </ScrollArea>
                                </div>
                            </div>
                            <div v-if="permissionsForm.errors.permissions" class="mt-4 text-sm text-red-600">{{ permissionsForm.errors.permissions }}</div>
                        </CardContent>
                        <CardFooter class="mt-6 flex justify-end">
                            <Button v-if="props.canManageRolePermissions" @click="syncPermissions" :disabled="permissionsForm.processing || !isRoleSelected">
                                <Save class="w-4 h-4 mr-2" />
                                {{ permissionsForm.processing ? 'Salvando...' : 'Salvar Permissões para este Papel' }}
                            </Button>
                        </CardFooter>
                    </Card>
                </div>
                <div v-else class="mt-8 text-center text-slate-500 dark:text-slate-400 py-10">
                    <ShieldCheck class="w-12 h-12 mx-auto text-slate-400 mb-4" />
                    <p class="text-lg">Selecione um papel na lista acima para gerenciar suas permissões.</p>
                    <p class="text-sm mt-2">Ou <Link :href="route('admin.roles.create')" class="text-indigo-600 hover:underline">crie um novo papel</Link> se necessário.</p>
                </div>
            </div>
        </div>

        <!-- Diálogo de Confirmação de Exclusão de Papel -->
        <Dialog :open="showDeleteRoleDialog" @update:open="showDeleteRoleDialog = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Confirmar Exclusão de Papel</DialogTitle>
                    <DialogDescription>
                        Tem certeza que deseja excluir o papel "{{ roleToDelete?.name }}"? Esta ação não pode ser desfeita.
                        Papéis que estão atualmente associados a usuários não poderão ser excluídos.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="mt-4">
                    <DialogClose as-child>
                        <Button variant="outline" @click="showDeleteRoleDialog = false; roleToDelete = null;">Cancelar</Button>
                    </DialogClose>
                    <Button variant="destructive" @click="submitDeleteRole" :disabled="router.processing">
                        <Trash2 v-if="!router.processing" class="w-4 h-4 mr-2" />
                        <svg v-else class="animate-spin -ml-1 mr-2 h-4 w-4 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ router.processing ? 'Excluindo...' : 'Excluir Papel' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

    </AppLayout>
</template>
