<script setup lang="ts">
import AdminLayout from '@/layouts/AppLayout.vue'; // Presume que você tem um AdminLayout
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { MoreHorizontal, PlusCircle, Trash2, FilePenLine, ShieldCheck, Users } from 'lucide-vue-next';
import { PaginatedResponse, Role, Permission } from '@/types'; // Certifique-se que Role e Permission estão em types
import Pagination from '@/components/Pagination.vue'; // Componente de paginação, se você tiver um
import { ref, computed } from 'vue';
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

interface Props {
    roles: PaginatedResponse<Role & { users_count?: number; permissions_count?: number }>;
    filters: { search?: string };
    canCreateRoles: boolean;
    canUpdateRoles: boolean;
    canDeleteRoles: boolean;
}

const props = defineProps<Props>();
const { toast } = useToast();
const page = usePage();

const term = ref(props.filters.search || '');

const showDeleteDialog = ref(false);
const roleToDelete = ref<Role | null>(null);

const confirmDeleteRole = (role: Role) => {
    roleToDelete.value = role;
    showDeleteDialog.value = true;
};

const deleteRole = () => {
    if (roleToDelete.value) {
        router.delete(route('admin.roles.destroy', roleToDelete.value.id), {
            preserveScroll: true,
            onSuccess: () => {
                showDeleteDialog.value = false;
                roleToDelete.value = null;
                // A mensagem flash será exibida automaticamente se configurada no HandleInertiaRequests
            },
            onError: (errors) => {
                showDeleteDialog.value = false;
                const errorMessages = Object.values(errors).join(' ');
                toast({
                    title: 'Erro ao Excluir Papel',
                    description: errorMessages || 'Não foi possível excluir o papel.',
                    variant: 'destructive',
                });
            }
        });
    }
};

</script>

<template>
    <Head title="Gerenciar Papéis" />
    <AdminLayout>
        <div class="px-4 sm:px-6 lg:px-8 py-8 w-full max-w-9xl mx-auto">
            <!-- Cabeçalho da Página -->
            <div class="sm:flex sm:justify-between sm:items-center mb-8">
                <div class="mb-4 sm:mb-0">
                    <h1 class="text-2xl md:text-3xl text-slate-800 dark:text-slate-100 font-bold">Gerenciar Papéis</h1>
                </div>
                <div v-if="canCreateRoles" class="grid grid-flow-col sm:auto-cols-max justify-start sm:justify-end gap-2">
                    <Link :href="route('admin.roles.create')">
                        <Button variant="default">
                            <PlusCircle class="w-4 h-4 mr-2" />
                            Novo Papel
                        </Button>
                    </Link>
                </div>
            </div>



            <!-- Tabela de Papéis -->
            <div class="bg-white dark:bg-slate-800 shadow-lg rounded-sm border border-slate-200 dark:border-slate-700 relative">
                <div class="overflow-x-auto">
                    <Table>
                        <TableHeader>
                            <TableRow>
                                <TableHead class="px-2 first:pl-5 last:pr-5 py-3 whitespace-nowrap">Nome</TableHead>
                                <TableHead class="px-2 first:pl-5 last:pr-5 py-3 whitespace-nowrap">Descrição</TableHead>
                                <TableHead class="px-2 first:pl-5 last:pr-5 py-3 whitespace-nowrap text-center">Nível</TableHead>
                                <TableHead class="px-2 first:pl-5 last:pr-5 py-3 whitespace-nowrap text-center">Usuários</TableHead>
                                <TableHead class="px-2 first:pl-5 last:pr-5 py-3 whitespace-nowrap text-center">Permissões</TableHead>
                                <TableHead class="px-2 first:pl-5 last:pr-5 py-3 whitespace-nowrap text-right">Ações</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-if="roles.data.length === 0">
                                <TableCell colspan="6" class="px-2 first:pl-5 last:pr-5 py-3 whitespace-nowrap text-center">
                                    Nenhum papel encontrado.
                                </TableCell>
                            </TableRow>
                            <TableRow v-for="role in roles.data" :key="role.id">
                                <TableCell class="px-2 first:pl-5 last:pr-5 py-3 whitespace-nowrap">
                                    <div class="font-medium text-slate-800 dark:text-slate-100">{{ role.name }}</div>
                                </TableCell>
                                <TableCell class="px-2 first:pl-5 last:pr-5 py-3 whitespace-nowrap">
                                    <div>{{ role.description || '-' }}</div>
                                </TableCell>
                                <TableCell class="px-2 first:pl-5 last:pr-5 py-3 whitespace-nowrap text-center">
                                    <div class="text-center">{{ role.level }}</div>
                                </TableCell>
                                <TableCell class="px-2 first:pl-5 last:pr-5 py-3 whitespace-nowrap text-center">
                                     <div class="inline-flex items-center justify-center">
                                        <Users class="w-4 h-4 mr-1 text-slate-400 dark:text-slate-500" />
                                        {{ role.users_count }}
                                    </div>
                                </TableCell>
                                <TableCell class="px-2 first:pl-5 last:pr-5 py-3 whitespace-nowrap text-center">
                                    <div class="inline-flex items-center justify-center">
                                        <ShieldCheck class="w-4 h-4 mr-1 text-slate-400 dark:text-slate-500" />
                                        {{ role.permissions_count }}
                                    </div>
                                </TableCell>
                                <TableCell class="px-2 first:pl-5 last:pr-5 py-3 whitespace-nowrap text-right">
                                    <DropdownMenu v-if="canUpdateRoles || canDeleteRoles">
                                        <DropdownMenuTrigger as-child>
                                            <Button variant="ghost" size="sm">
                                                <MoreHorizontal class="w-4 h-4" />
                                            </Button>
                                        </DropdownMenuTrigger>
                                        <DropdownMenuContent align="end">
                                            <DropdownMenuItem v-if="canUpdateRoles" @click="router.get(route('admin.roles.edit', role.id))">
                                                <FilePenLine class="w-4 h-4 mr-2" />
                                                Editar
                                            </DropdownMenuItem>
                                            <DropdownMenuItem v-if="canDeleteRoles" @click="confirmDeleteRole(role)" class="text-red-600 hover:!text-red-600 dark:text-red-500 dark:hover:!text-red-500">
                                                <Trash2 class="w-4 h-4 mr-2" />
                                                Excluir
                                            </DropdownMenuItem>
                                        </DropdownMenuContent>
                                    </DropdownMenu>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                </div>
            </div>
            <!-- Paginação -->
            <Pagination v-if="roles.total > roles.per_page" :pagination="roles" class="mt-6" />
        </div>

        <!-- Diálogo de Confirmação de Exclusão -->
        <Dialog :open="showDeleteDialog" @update:open="showDeleteDialog = $event">
            <DialogContent>
                <DialogHeader>
                    <DialogTitle>Confirmar Exclusão</DialogTitle>
                    <DialogDescription>
                        Tem certeza que deseja excluir o papel "{{ roleToDelete?.name }}"? Esta ação não pode ser desfeita.
                        Papéis associados a usuários não podem ser excluídos.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter>
                    <DialogClose as-child>
                        <Button variant="outline" @click="showDeleteDialog = false">Cancelar</Button>
                    </DialogClose>
                    <Button variant="destructive" @click="deleteRole">Excluir</Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

    </AdminLayout>
</template>
