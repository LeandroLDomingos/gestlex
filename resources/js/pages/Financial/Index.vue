<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue, SelectGroup } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import Pagination from '@/components/Pagination.vue'; // Seu componente de paginação
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import { Edit3, Trash2, ExternalLink } from 'lucide-vue-next'; // Ícones para ações

// Interface para cada transação financeira (ProcessPayment)
interface FinancialTransaction {
    id: string | number;
    process_id: string;
    total_amount: number | string | null;
    payment_type: string | null;
    payment_method: string | null;
    down_payment_date: string | null; // Data da entrada ou pagamento do honorário
    first_installment_due_date: string | null; // Data de vencimento da parcela/pgto único/serviço do honorário
    status: string | null;
    status_label?: string;
    notes: string | null;
    created_at: string;
    value_of_installment: number | string | null;
    number_of_installments: number | null;
    down_payment_amount: number | string | null; // Se > 0, indica que é uma entrada
    process: {
        id: string;
        title: string;
        contact?: {
            id: string;
            name?: string;
            business_name?: string;
        }
    } | null;
}

// Interface para os dados paginados
interface PaginatedFinancialTransactions {
    data: FinancialTransaction[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    current_page: number;
    last_page: number;
    from: number | null;
    to: number | null;
    total: number;
    path: string;
    per_page: number;
}

// Props recebidas do FinancialTransactionController@index
const props = defineProps<{
    transactions: PaginatedFinancialTransactions;
    filters: Record<string, string | null>;
    paymentTypes: Array<{ value: string; label: string }>; // Todos os tipos, incluindo 'honorario'
    paymentStatuses: Array<{ key: string; label: string }>;
    summary?: { // Opcional, se o backend enviar
        totalReceived: number;
        totalPending: number;
        totalFees: number;
    };
}>();

const RGlobal = (window as any).route;
const routeHelper = (name?: string, params?: any, absolute?: boolean): string => {
    if (typeof RGlobal === 'function') { return RGlobal(name, params, absolute); }
    let url = `/${name?.replace(/\./g, '/') || ''}`;
    if (params) { /* ... (fallback do seu helper) ... */ }
    return url;
};

// Filtros locais reativos
const localFilters = ref({
    search_process: props.filters.search_process || '',
    search_contact: props.filters.search_contact || '',
    payment_type_filter: props.filters.payment_type_filter || null,
    status_filter: props.filters.status_filter || null,
    date_from_filter: props.filters.date_from_filter || '', // Para first_installment_due_date ou down_payment_date
    date_to_filter: props.filters.date_to_filter || '',
    sort_by: props.filters.sort_by || 'created_at',
    sort_direction: props.filters.sort_direction || 'desc',
});

// Observador para aplicar filtros quando mudam
let searchTimeout: number | undefined = undefined;
watch(localFilters, (newFilters) => {
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = window.setTimeout(() => {
        router.get(routeHelper('financial-transactions.index'), newFilters, {
            preserveState: true,
            replace: true,
        });
    }, 300); // Debounce de 300ms
}, { deep: true });

const formatDate = (dateString: string | null | undefined): string => {
    if (!dateString) return 'N/A';
    try {
        const date = new Date(dateString.includes('T') || dateString.includes('Z') ? dateString : dateString + 'T00:00:00Z');
        return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC' });
    } catch (e) { return dateString || 'N/A'; }
};

const formatCurrency = (value: number | string | null | undefined): string => {
    const numValue = Number(value);
    if (value === null || typeof value === 'undefined' || isNaN(numValue)) return 'N/A';
    return numValue.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
};

const getPaymentTypeLabel = (typeKey: string | null): string => {
    if (!typeKey) return 'N/A';
    const foundType = props.paymentTypes?.find(pt => pt.value === typeKey);
    if (foundType) return foundType.label;
    return typeKey.replace(/_/g, ' ').split(' ').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
};

const getPaymentStatusLabel = (statusKey: string | null): string => {
    if (!statusKey) return 'N/A';
    const foundStatus = props.paymentStatuses?.find(s => s.key === statusKey);
    if (foundStatus) return foundStatus.label;
    return statusKey.charAt(0).toUpperCase() + statusKey.slice(1);
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Painel', href: routeHelper('dashboard') },
    { title: 'Controle Financeiro', href: routeHelper('financial-transactions.index') },
];

// Lógica para exclusão
const showDeleteTransactionDialog = ref(false);
const transactionToDelete = ref<FinancialTransaction | null>(null);
const deleteTransactionForm = useForm({});

function openDeleteTransactionDialog(transaction: FinancialTransaction) {
    transactionToDelete.value = transaction;
    showDeleteTransactionDialog.value = true;
}

function submitDeleteTransaction() {
    if (!transactionToDelete.value) return;
    deleteTransactionForm.delete(routeHelper('financial-transactions.destroy', transactionToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteTransactionDialog.value = false;
            transactionToDelete.value = null;
            router.reload({ only: ['transactions'] }); // Recarrega a lista de transações
        },
        onError: (errors) => {
            console.error('Erro ao excluir transação:', errors);
            alert('Falha ao excluir transação.');
        }
    });
}

// Função para navegar para a página de edição de uma transação
function editTransaction(transaction: FinancialTransaction) {
    // Se for um honorário, e você tiver uma rota/modal específico para editar honorários,
    // você pode redirecionar para lá ou abrir o modal.
    // Por enquanto, vamos assumir uma rota de edição genérica para FinancialTransaction.
    // Ou, se a edição de pagamentos de contrato/entrada deve ser feita na tela do processo:
    if (transaction.payment_type !== 'honorario' && transaction.process?.id) {
         router.visit(routeHelper('processes.edit', transaction.process.id) + `?payment_to_edit=${transaction.id}`);
    } else {
        // Para honorários ou transações sem processo vinculado (se houver)
        router.visit(routeHelper('financial-transactions.edit', transaction.id));
    }
}

</script>

<template>
    <Head title="Controle Financeiro" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-4 sm:p-6 lg:p-8">
            <Card>
                <CardHeader>
                    <CardTitle>Controle Financeiro</CardTitle>
                    <CardDescription>Visualize e filtre todas as transações financeiras.</CardDescription>
                </CardHeader>
                <CardContent class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 p-4 border dark:border-gray-700 rounded-lg shadow-sm">
                        <div>
                            <Label for="search_process">Buscar por Caso</Label>
                            <Input id="search_process" v-model="localFilters.search_process" placeholder="Título do caso..." class="mt-1" />
                        </div>
                        <div>
                            <Label for="search_contact">Buscar por Contato</Label>
                            <Input id="search_contact" v-model="localFilters.search_contact" placeholder="Nome do contato..." class="mt-1" />
                        </div>
                        <div>
                            <Label for="payment_type_filter">Tipo de Transação</Label>
                            <Select v-model="localFilters.payment_type_filter">
                                <SelectTrigger id="payment_type_filter" class="mt-1"><SelectValue placeholder="Todos os Tipos" /></SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem :value="null">Todos os Tipos</SelectItem>
                                        <SelectItem v-for="type in paymentTypes" :key="type.value" :value="type.value">{{ type.label }}</SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label for="status_filter">Status</Label>
                            <Select v-model="localFilters.status_filter">
                                <SelectTrigger id="status_filter" class="mt-1"><SelectValue placeholder="Todos os Status" /></SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem :value="null">Todos os Status</SelectItem>
                                        <SelectItem v-for="statusOpt in paymentStatuses" :key="statusOpt.key" :value="statusOpt.key">{{ statusOpt.label }}</SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label for="date_from_filter">Data Venc./Pgto De:</Label>
                            <Input id="date_from_filter" type="date" v-model="localFilters.date_from_filter" class="mt-1" />
                        </div>
                        <div>
                            <Label for="date_to_filter">Data Venc./Pgto Até:</Label>
                            <Input id="date_to_filter" type="date" v-model="localFilters.date_to_filter" class="mt-1" />
                        </div>
                    </div>

                    <div v-if="summary" class="grid grid-cols-1 md:grid-cols-3 gap-4 mt-6">
                        <Card>
                            <CardHeader class="pb-2">
                                <CardDescription>Total Recebido</CardDescription>
                                <CardTitle class="text-2xl text-green-600 dark:text-green-400">{{ formatCurrency(summary.totalReceived) }}</CardTitle>
                            </CardHeader>
                        </Card>
                        <Card>
                            <CardHeader class="pb-2">
                                <CardDescription>Total Pendente</CardDescription>
                                <CardTitle class="text-2xl text-yellow-600 dark:text-yellow-400">{{ formatCurrency(summary.totalPending) }}</CardTitle>
                            </CardHeader>
                        </Card>
                         <Card>
                            <CardHeader class="pb-2">
                                <CardDescription>Total Honorários (Todos Status)</CardDescription>
                                <CardTitle class="text-2xl">{{ formatCurrency(summary.totalFees) }}</CardTitle>
                            </CardHeader>
                        </Card>
                    </div>

                    <div class="border rounded-md overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Caso</TableHead>
                                    <TableHead>Contato</TableHead>
                                    <TableHead>Descrição/Notas</TableHead>
                                    <TableHead class="text-right">Valor</TableHead>
                                    <TableHead>Tipo</TableHead>
                                    <TableHead>Vencimento</TableHead>
                                    <TableHead>Data Pagamento</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead class="text-right">Ações</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-if="!transactions.data || transactions.data.length === 0">
                                    <TableCell :colspan="9" class="text-center py-8 text-gray-500 dark:text-gray-400">Nenhuma transação financeira encontrada.</TableCell>
                                </TableRow>
                                <TableRow v-for="transaction in transactions.data" :key="transaction.id">
                                    <TableCell>
                                        <Link v-if="transaction.process" :href="routeHelper('processes.show', transaction.process.id)" class="text-indigo-600 hover:underline font-medium">
                                            {{ transaction.process.title || 'N/A' }}
                                        </Link>
                                        <span v-else class="text-gray-500">N/A</span>
                                    </TableCell>
                                    <TableCell>
                                        <Link v-if="transaction.process?.contact" :href="routeHelper('contacts.show', transaction.process.contact.id)" class="text-indigo-600 hover:underline">
                                            {{ transaction.process.contact.name || transaction.process.contact.business_name || 'N/A' }}
                                        </Link>
                                        <span v-else class="text-gray-500">N/A</span>
                                    </TableCell>
                                    <TableCell class="max-w-xs truncate" :title="transaction.notes || ''">{{ transaction.notes || 'N/A' }}</TableCell>
                                    <TableCell class="text-right font-medium">{{ formatCurrency(transaction.total_amount) }}</TableCell>
                                    <TableCell>{{ getPaymentTypeLabel(transaction.payment_type) }}</TableCell>
                                    <TableCell>{{ formatDate(transaction.first_installment_due_date) }}</TableCell>
                                    <TableCell>{{ transaction.status === 'paid' ? formatDate(transaction.down_payment_date) : 'N/A' }}</TableCell>
                                    <TableCell>
                                        <Badge :variant="'outline'"
                                               :class="[
                                                   'text-xs capitalize',
                                                   transaction.status === 'paid' ? 'bg-green-100 text-green-800 border-green-300 dark:bg-green-700/30 dark:text-green-300 dark:border-green-600' : '',
                                                   transaction.status === 'pending' ? 'bg-yellow-100 text-yellow-800 border-yellow-300 dark:bg-yellow-700/30 dark:text-yellow-300 dark:border-yellow-600' : '',
                                                   (transaction.status === 'failed' || transaction.status === 'refunded') ? 'bg-red-100 text-red-800 border-red-300 dark:bg-red-700/30 dark:text-red-300 dark:border-red-600' : '',
                                                   !(transaction.status === 'paid' || transaction.status === 'pending' || transaction.status === 'failed' || transaction.status === 'refunded') ? 'border-gray-300 dark:border-gray-600' : ''
                                               ]">
                                            {{ transaction.status_label || getPaymentStatusLabel(transaction.status) }}
                                        </Badge>
                                    </TableCell>
                                    <TableCell class="text-right">
                                        <div class="flex items-center justify-end space-x-1">
                                            <Button @click="editTransaction(transaction)" variant="ghost" size="icon" class="h-8 w-8" title="Editar Transação">
                                                <Edit3 class="h-4 w-4 text-blue-600 hover:text-blue-800" />
                                            </Button>
                                            <Button @click="openDeleteTransactionDialog(transaction)" variant="ghost" size="icon" class="h-8 w-8" title="Excluir Transação">
                                                <Trash2 class="h-4 w-4 text-red-600 hover:text-red-800" />
                                            </Button>
                                            <Link v-if="transaction.process" :href="routeHelper('processes.show', transaction.process.id)" title="Ver Caso">
                                                <Button variant="ghost" size="icon" class="h-8 w-8">
                                                    <ExternalLink class="h-4 w-4 text-gray-500 hover:text-gray-700" />
                                                </Button>
                                            </Link>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                    <Pagination v-if="transactions.data.length > 0" :links="transactions.links" :from="transactions.from" :to="transactions.to" :total="transactions.total" />
                </CardContent>
            </Card>
        </div>

         <Dialog :open="showDeleteTransactionDialog" @update:open="showDeleteTransactionDialog = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Confirmar Exclusão de Transação</DialogTitle>
                    <DialogDescription v-if="transactionToDelete">
                        Tem certeza de que deseja excluir esta transação financeira no valor de {{ formatCurrency(transactionToDelete.total_amount) }}
                        (Notas: {{ transactionToDelete.notes || 'Sem notas' }})? Esta ação não poderá ser desfeita.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="mt-4 flex flex-col-reverse sm:flex-row sm:justify-end sm:space-x-2">
                    <Button variant="outline" type="button" @click="showDeleteTransactionDialog = false; transactionToDelete = null;">Cancelar</Button>
                    <Button variant="destructive" :disabled="deleteTransactionForm.processing" @click="submitDeleteTransaction">
                        <svg v-if="deleteTransactionForm.processing" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ deleteTransactionForm.processing ? 'Excluindo...' : 'Confirmar Exclusão' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>
    </AppLayout>
</template>
