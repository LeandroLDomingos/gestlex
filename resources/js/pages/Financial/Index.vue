<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3'; // Certifique-se que useForm está aqui
import AppLayout from '@/layouts/AppLayout.vue';
import Pagination from '@/components/Pagination.vue';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
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
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue, SelectGroup } from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/card';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog'; // Adicionado Dialog

import { Search, PlusCircle, ChevronDown, Filter as FilterIcon, ListFilter, ArrowUpDown, SlidersHorizontal, X, CalendarIcon, Archive as ArchiveIcon, AlertTriangle, DollarSign, Edit3, Trash2, ExternalLink, ReceiptText, CircleAlert, CheckCircle2 as CheckCircle2Icon, Hourglass, TrendingUp, TrendingDown, Wallet } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedResponse } from '@/types/inertia';

const RGlobal = (window as any).route;
const routeHelper = (name?: string, params?: any, absolute?: boolean): string => {
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

interface User { id: number | string; name: string; }
interface RelatedContact { id: number | string; name: string; business_name?: string; type?: 'physical' | 'legal';}

interface FinancialTransaction {
    id: string | number;
    process_id: string;
    total_amount: number | string | null;
    interest_amount?: number | string | null;
    payment_type: string | null;
    payment_method: string | null;
    down_payment_date: string | null;
    first_installment_due_date: string | null;
    status: string | null;
    status_label?: string;
    notes: string | null;
    created_at: string;
    value_of_installment: number | string | null;
    number_of_installments: number | null;
    down_payment_amount: number | string | null;
    process: { id: string; title: string; contact?: { id: string; name?: string; business_name?: string; }} | null;
}

interface PaginatedFinancialTransactions {
    data: FinancialTransaction[];
    links: Array<{ url: string | null; label: string; active: boolean }>;
    current_page: number; last_page: number; from: number | null; to: number | null; total: number; path: string; per_page: number;
}

const props = defineProps<{
    transactions: PaginatedFinancialTransactions;
    filters: {
        search_process?: string | null;
        search_contact?: string | null;
        payment_type_filter?: string | null;
        status_filter?: string | null;
        sort_by?: string | null;
        sort_direction?: string | null;
        summary_date_from?: string | null;
        summary_date_to?: string | null;
    };
    paymentTypes: Array<{ value: string; label: string }>;
    paymentStatuses: Array<{ key: string; label: string }>;
    dashboardSummary?: {
        totalReceivedInPeriod: number;
        accountsReceivableOverdueToday: number;
        balanceInPeriod: number;
    };
    latestReceivedTransactions?: FinancialTransaction[];
    upcomingDueTransactions?: FinancialTransaction[];
}>();

const page = usePage();

const localFilters = ref({
    search_process: props.filters.search_process || '',
    search_contact: props.filters.search_contact || '',
    payment_type_filter: props.filters.payment_type_filter || null,
    status_filter: props.filters.status_filter || null,
    summary_date_from: props.filters.summary_date_from || new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0],
    summary_date_to: props.filters.summary_date_to || new Date().toISOString().split('T')[0],
    sort_by: props.filters.sort_by || 'first_installment_due_date',
    sort_direction: props.filters.sort_direction || 'desc',
});

const quickFilterStatusTable = ref<string | null>(props.filters.status_filter || null);
const showAdvancedFiltersPopover = ref(false);

watch(quickFilterStatusTable, (newStatus) => {
    localFilters.value.status_filter = newStatus;
});

let filterTimeout: number | undefined = undefined;
const debouncedApplyFilters = () => {
    if (filterTimeout) clearTimeout(filterTimeout);
    filterTimeout = window.setTimeout(() => {
        applyFilters();
    }, 400);
};

watch(localFilters, () => {
    debouncedApplyFilters();
}, { deep: true });

function applyFilters() {
    const activeFilters: Record<string, any> = {};
    for (const key in localFilters.value) {
        const filterValue = localFilters.value[key as keyof typeof localFilters.value];
        if (filterValue !== null && filterValue !== '') {
            activeFilters[key] = filterValue;
        }
    }
    router.get(routeHelper('financial-transactions.index'), activeFilters, {
        preserveState: true,
        replace: true,
        preserveScroll: true,
    });
}

function resetTableFilters() {
    localFilters.value.search_process = '';
    localFilters.value.search_contact = '';
    localFilters.value.payment_type_filter = null;
    localFilters.value.status_filter = null; 
    quickFilterStatusTable.value = null; // Também reseta o filtro rápido da tabela
}

// Função para aplicar filtro rápido e atualizar o filtro local da tabela
function applyQuickFilter(status: string | null) {
    quickFilterStatusTable.value = status;
    // localFilters.value.status_filter já será atualizado pelo watch em quickFilterStatusTable
    // A função applyFilters será chamada pelo watch em localFilters
}


const formatDate = (dateString: string | null | undefined): string => {
    if (!dateString) return 'N/A';
    try {
        // Garante que a data seja interpretada como UTC para evitar problemas de fuso horário na formatação
        const date = new Date(dateString.includes('T') || dateString.includes('Z') ? dateString : dateString + 'T00:00:00Z');
        return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC' });
    } catch (e) {
        // console.error("Erro ao formatar data:", dateString, e);
        return dateString || 'N/A'; // Retorna a string original em caso de erro
    }
};

const formatCurrency = (value: number | string | null | undefined): string => {
    const numValue = Number(value);
    if (value === null || typeof value === 'undefined' || isNaN(numValue)) return 'R$ 0,00';
    return numValue.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
};

const getPaymentTypeLabel = (typeKey: string | null): string => {
    if (!typeKey) return 'N/A';
    const foundType = props.paymentTypes?.find(pt => pt.value === typeKey);
    if (foundType) return foundType.label;
    // Fallback para formatar o typeKey se não encontrado
    return typeKey.replace(/_/g, ' ').split(' ').map(word => word.charAt(0).toUpperCase() + word.slice(1)).join(' ');
};

const getPaymentStatusLabel = (statusKey: string | null): string => {
    if (!statusKey) return 'N/A';
    const foundStatus = props.paymentStatuses?.find(s => s.key === statusKey);
    if (foundStatus) return foundStatus.label;
    return statusKey.charAt(0).toUpperCase() + statusKey.slice(1); // Capitaliza a primeira letra
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Painel', href: routeHelper('dashboard') },
    { title: 'Dashboard Financeiro', href: routeHelper('financial-transactions.index') },
];

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
            // Opcional: recarregar apenas os dados da transação ou mostrar uma notificação
            // router.reload({ only: ['transactions'] }); // Pode ser útil se a lista não atualizar automaticamente
            // Em vez de reload, pode ser melhor que o backend retorne os dados atualizados ou que você remova o item localmente
            // se a lista for grande e o reload for pesado.
            // Por ora, vamos assumir que o Inertia.js lida bem com a atualização da prop 'transactions'
        },
        onError: (errors) => {
            console.error('Erro ao excluir transação:', errors);
            // Substituir alert por um componente de notificação mais elegante se disponível
            alert('Falha ao excluir transação. Verifique o console para mais detalhes.');
        }
    });
}

function editTransaction(transaction: FinancialTransaction) {
    // Lógica para redirecionar para a edição da transação
    // Exemplo:
    if (transaction.payment_type === 'honorario' && transaction.process?.id) {
        // Se for um honorário e tiver um processo associado, edita na tela do processo
         router.visit(routeHelper('processes.show', transaction.process.id) + `?tab=payments&fee_to_edit=${transaction.id}`);
    } else if (transaction.process?.id) {
        // Se for outro tipo de pagamento com processo, também edita na tela do processo
         router.visit(routeHelper('processes.show', transaction.process.id) + `?tab=payments&payment_to_edit=${transaction.id}`);
    } else {
        // Para transações avulsas ou outros cenários, pode ter uma rota de edição específica
        // router.visit(routeHelper('financial-transactions.edit', transaction.id));
        alert('Não é possível editar este tipo de transação diretamente aqui ou falta um caso associado.');
    }
}

const tableHeaders: { key: string; label: string; sortable: boolean, class?: string, align?: 'left' | 'center' | 'right' }[] = [
    { key: 'process.title', label: 'Caso', sortable: true, class: 'w-[20%]' },
    { key: 'process.contact.name', label: 'Contato', sortable: true, class: 'w-[15%]' },
    { key: 'notes', label: 'Descrição/Notas', sortable: false, class: 'w-[20%] max-w-xs truncate' }, // Adicionado truncate
    { key: 'total_value_with_interest', label: 'Valor (c/ Juros)', sortable: true, class: 'w-[12%]', align: 'right' },
    { key: 'payment_type', label: 'Tipo', sortable: true, class: 'w-[8%]' },
    { key: 'first_installment_due_date', label: 'Vencimento', sortable: true, class: 'w-[10%]' },
    { key: 'down_payment_date', label: 'Data Pgto.', sortable: true, class: 'w-[10%]' }, // Usado para data de pagamento efetivo
    { key: 'status', label: 'Status', sortable: true, class: 'w-[8%]', align: 'center' },
    { key: 'actions', label: 'Ações', sortable: false, class: 'w-[7%]', align: 'right' },
];

const handleSort = (columnKey: string) => {
    if (localFilters.value.sort_by === columnKey) {
        localFilters.value.sort_direction = localFilters.value.sort_direction === 'asc' ? 'desc' : 'asc';
    } else {
        localFilters.value.sort_by = columnKey;
        localFilters.value.sort_direction = 'asc';
    }
    // applyFilters(); // O watch em localFilters já chama debouncedApplyFilters
};

</script>

<template>
    <Head title="Dashboard Financeiro" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-4 sm:p-6 lg:p-8 space-y-6">
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100">Dashboard Financeiro</h1>
                <div class="flex items-center gap-2">
                    <Label for="summary_date_from" class="text-sm shrink-0">Período:</Label>
                    <Input id="summary_date_from" type="date" v-model="localFilters.summary_date_from" class="h-9 w-40 text-sm" />
                    <Label for="summary_date_to" class="text-sm shrink-0">até</Label>
                    <Input id="summary_date_to" type="date" v-model="localFilters.summary_date_to" class="h-9 w-40 text-sm" />
                </div>
            </div>

            <div v-if="props.dashboardSummary" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-4">
                <Card class="shadow-lg hover:shadow-xl transition-shadow">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Recebido (Período)</CardTitle>
                        <CheckCircle2Icon class="h-5 w-5 text-green-500" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-bold text-green-600 dark:text-green-400">{{ formatCurrency(props.dashboardSummary.totalReceivedInPeriod) }}</div>
                        <p class="text-xs text-muted-foreground">
                            {{ localFilters.summary_date_from ? formatDate(localFilters.summary_date_from) : 'Desde o início' }} - {{ localFilters.summary_date_to ? formatDate(localFilters.summary_date_to) : 'Hoje' }}
                        </p>
                    </CardContent>
                </Card>
                <Card class="shadow-lg hover:shadow-xl transition-shadow">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Contas a Receber (Vencidas/Hoje)</CardTitle>
                        <AlertTriangle class="h-5 w-5 text-red-500" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-bold text-red-600 dark:text-red-400">{{ formatCurrency(props.dashboardSummary.accountsReceivableOverdueToday) }}</div>
                        <Link :href="routeHelper('financial-transactions.index', { status_filter: 'pending', sort_by: 'first_installment_due_date', sort_direction: 'asc' })" class="text-xs text-indigo-600 hover:underline">
                            Ver todas as pendentes
                        </Link>
                    </CardContent>
                </Card>
                <Card class="shadow-lg hover:shadow-xl transition-shadow">
                     <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Saldo (Período)</CardTitle>
                        <Wallet class="h-5 w-5 text-blue-500" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-bold text-blue-600 dark:text-blue-400">{{ formatCurrency(props.dashboardSummary.balanceInPeriod) }}</div>
                         <p class="text-xs text-muted-foreground">Receitas no período</p>
                    </CardContent>
                </Card>
                <Card class="shadow-lg hover:shadow-xl transition-shadow bg-gray-50 dark:bg-gray-800/50 border-dashed">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Despesas (Período)</CardTitle>
                        <TrendingDown class="h-5 w-5 text-muted-foreground" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-bold text-gray-500 dark:text-gray-400">R$ 0,00</div>
                        <p class="text-xs text-muted-foreground">Funcionalidade de despesas a implementar</p>
                    </CardContent>
                </Card>
            </div>
            <Separator class="my-8"/>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <h2 class="text-xl font-semibold">Últimos Recebimentos</h2>
                        <Button variant="ghost" size="sm" @click="applyQuickFilter('paid')">Ver Todos</Button>
                    </div>
                    <Card class="shadow-md">
                        <CardContent class="p-0">
                            <Table v-if="props.latestReceivedTransactions && props.latestReceivedTransactions.length > 0">
                                <TableHeader>
                                    <TableRow>
                                        <TableHead class="w-[40%]">Caso</TableHead>
                                        <TableHead class="w-[30%]">Data Pgto.</TableHead>
                                        <TableHead class="w-[30%] text-right">Valor Recebido</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="transaction in props.latestReceivedTransactions" :key="`latest-${transaction.id}`">
                                        <TableCell>
                                            <Link v-if="transaction.process" :href="routeHelper('processes.show', transaction.process.id)" class="text-indigo-600 hover:underline font-medium text-sm">
                                                {{ transaction.process.title || 'N/A' }}
                                            </Link>
                                        </TableCell>
                                        <TableCell class="text-sm">{{ formatDate(transaction.down_payment_date) }}</TableCell>
                                        <TableCell class="text-right text-sm font-medium">{{ formatCurrency((Number(transaction.total_amount) || 0) + (Number(transaction.interest_amount) || 0)) }}</TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                            <p v-else class="p-6 text-sm text-center text-gray-500 dark:text-gray-400">Nenhum recebimento recente no período selecionado.</p>
                        </CardContent>
                    </Card>
                </div>

                <div class="space-y-3">
                     <div class="flex justify-between items-center">
                        <h2 class="text-xl font-semibold">Cobranças Vencendo (Próx. 30 dias)</h2>
                         <Button variant="ghost" size="sm" @click="applyQuickFilter('pending')">Ver Todas Pendentes</Button>
                    </div>
                    <Card class="shadow-md">
                        <CardContent class="p-0">
                            <Table v-if="props.upcomingDueTransactions && props.upcomingDueTransactions.length > 0">
                                <TableHeader>
                                    <TableRow>
                                        <TableHead class="w-[40%]">Caso</TableHead>
                                        <TableHead class="w-[30%]">Vencimento</TableHead>
                                        <TableHead class="w-[30%] text-right">Valor Pendente</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-for="transaction in props.upcomingDueTransactions" :key="`upcoming-${transaction.id}`">
                                        <TableCell>
                                            <Link v-if="transaction.process" :href="routeHelper('processes.show', transaction.process.id)" class="text-indigo-600 hover:underline font-medium text-sm">
                                                {{ transaction.process.title || 'N/A' }}
                                            </Link>
                                        </TableCell>
                                        <TableCell class="text-sm">{{ formatDate(transaction.first_installment_due_date) }}</TableCell>
                                        <TableCell class="text-right text-sm font-medium">{{ formatCurrency(transaction.total_amount) }}</TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                             <p v-else class="p-6 text-sm text-center text-gray-500 dark:text-gray-400">Nenhuma cobrança vencendo nos próximos 30 dias.</p>
                        </CardContent>
                    </Card>
                </div>
            </div>
            
            <Separator class="my-8"/>

            <Card class="shadow-md">
                <CardContent class="p-4 md:p-6"> 
                    <div class="space-y-4">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">Todas as Transações Registradas</h2>
                            <div class="flex items-center gap-2 flex-wrap">
                                <Button :variant="quickFilterStatusTable === null ? 'secondary' : 'outline'" size="sm" @click="applyQuickFilter(null)" class="h-9">Todas</Button>
                                <Button :variant="quickFilterStatusTable === 'pending' ? 'secondary' : 'outline'" size="sm" @click="applyQuickFilter('pending')" class="h-9">A Receber</Button>
                                <Button :variant="quickFilterStatusTable === 'paid' ? 'secondary' : 'outline'" size="sm" @click="applyQuickFilter('paid')" class="h-9">Recebidas</Button>
                                </div>
                        </div>

                        <div class="flex flex-col md:flex-row gap-3 items-end">
                            <div class="flex-grow">
                                <Label for="search_process_table" class="text-xs">Buscar por Caso (na tabela)</Label>
                                <Input id="search_process_table" v-model="localFilters.search_process" placeholder="Título do caso..." class="mt-1 h-9" />
                            </div>
                            <div class="flex-grow">
                                <Label for="search_contact_table" class="text-xs">Buscar por Contato (na tabela)</Label>
                                <Input id="search_contact_table" v-model="localFilters.search_contact" placeholder="Nome do contato..." class="mt-1 h-9" />
                            </div>
                            <Popover v-model:open="showAdvancedFiltersPopover">
                                <PopoverTrigger as-child>
                                    <Button variant="outline" size="sm" class="h-9 mt-auto">
                                        <SlidersHorizontal class="h-4 w-4 mr-2" />
                                        Filtros Tabela
                                        <ChevronDown class="h-4 w-4 ml-1 opacity-70" />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-72 p-4 space-y-3" align="end">
                                    <div>
                                        <Label for="payment_type_filter_popover" class="text-xs">Tipo de Transação</Label>
                                        <Select v-model="localFilters.payment_type_filter">
                                            <SelectTrigger id="payment_type_filter_popover" class="mt-1 h-9"><SelectValue placeholder="Todos" /></SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    <SelectItem :value="null">Todos os Tipos</SelectItem>
                                                    <SelectItem v-for="type in paymentTypes" :key="type.value" :value="type.value">{{ type.label }}</SelectItem>
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div>
                                        <Label for="status_filter_popover_table" class="text-xs">Status (Avançado)</Label>
                                        <Select v-model="localFilters.status_filter">
                                            <SelectTrigger id="status_filter_popover_table" class="mt-1 h-9"><SelectValue placeholder="Filtrado por botões rápidos" /></SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    <SelectItem :value="null">Todos (controlado pelos botões)</SelectItem>
                                                    <SelectItem v-for="statusOpt in paymentStatuses" :key="statusOpt.key" :value="statusOpt.key">{{ statusOpt.label }}</SelectItem>
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                     <div class="flex justify-end pt-2">
                                        <Button variant="ghost" size="sm" @click="resetTableFilters(); showAdvancedFiltersPopover = false" class="text-xs h-8">Limpar Filtros da Tabela</Button>
                                    </div>
                                </PopoverContent>
                            </Popover>
                        </div>

                        <div class="border rounded-md overflow-x-auto shadow-sm">
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead v-for="header in tableHeaders" :key="header.key"
                                            @click="header.sortable ? handleSort(header.key) : null"
                                            :class="[
                                                'px-4 py-3 text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider',
                                                header.sortable ? 'cursor-pointer select-none hover:bg-gray-100 dark:hover:bg-gray-700/80 transition-colors duration-150 group' : '',
                                                header.class,
                                                header.align === 'right' ? 'text-right' : (header.align === 'center' ? 'text-center' : 'text-left')
                                            ]">
                                            {{ header.label }}
                                            <ArrowUpDown v-if="header.sortable && localFilters.sort_by === header.key" class="inline h-3 w-3 ml-1 align-middle" :class="{'transform rotate-180': localFilters.sort_direction === 'desc'}" />
                                            <ArrowUpDown v-else-if="header.sortable" class="inline h-3 w-3 ml-1 align-middle opacity-30 group-hover:opacity-70" />
                                        </TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    <TableRow v-if="!transactions.data || transactions.data.length === 0">
                                        <TableCell :colspan="tableHeaders.length" class="text-center py-8 text-gray-500 dark:text-gray-400">Nenhuma transação financeira encontrada para os filtros aplicados.</TableCell>
                                    </TableRow>
                                    <TableRow v-for="transaction in transactions.data" :key="transaction.id" class="hover:bg-gray-50/50 dark:hover:bg-gray-700/30">
                                        <TableCell class="px-4 py-3 text-sm">
                                            <Link v-if="transaction.process" :href="routeHelper('processes.show', transaction.process.id)" class="text-indigo-600 hover:underline font-medium">
                                                {{ transaction.process.title || 'N/A' }}
                                            </Link>
                                            <span v-else class="text-gray-500">N/A</span>
                                        </TableCell>
                                        <TableCell class="px-4 py-3 text-sm">
                                            <Link v-if="transaction.process?.contact" :href="routeHelper('contacts.show', transaction.process.contact.id)" class="text-indigo-600 hover:underline">
                                                {{ transaction.process.contact.name || transaction.process.contact.business_name || 'N/A' }}
                                            </Link>
                                            <span v-else class="text-gray-500">N/A</span>
                                        </TableCell>
                                        <TableCell class="px-4 py-3 text-sm max-w-xs truncate" :title="transaction.notes || ''">{{ transaction.notes || 'N/A' }}</TableCell>
                                        <TableCell class="px-4 py-3 text-sm text-right font-medium">
                                            <span>
                                                {{ formatCurrency( (Number(transaction.total_amount) || 0) + (Number(transaction.interest_amount) || 0) ) }}
                                            </span>
                                            <div v-if="transaction.interest_amount && parseFloat(String(transaction.interest_amount)) > 0" 
                                                 class="text-xs text-red-500 dark:text-red-400 mt-0.5">
                                                (Principal: {{ formatCurrency(transaction.total_amount) }} + Juros: {{ formatCurrency(transaction.interest_amount) }})
                                            </div>
                                        </TableCell>
                                        <TableCell class="px-4 py-3 text-sm">{{ getPaymentTypeLabel(transaction.payment_type) }}</TableCell>
                                        <TableCell class="px-4 py-3 text-sm">{{ formatDate(transaction.first_installment_due_date) }}</TableCell>
                                        <TableCell class="px-4 py-3 text-sm">{{ transaction.status === 'paid' ? formatDate(transaction.down_payment_date) : 'N/A' }}</TableCell>
                                        <TableCell class="px-4 py-3 text-sm text-center">
                                            <Badge :variant="'outline'"
                                               :class="[
                                                   'text-xs capitalize',
                                                   transaction.status === 'paid' ? 'bg-green-100 text-green-800 border-green-300 dark:bg-green-700/30 dark:text-green-300 dark:border-green-600' : '',
                                                   transaction.status === 'pending' ? 'bg-yellow-100 text-yellow-800 border-yellow-300 dark:bg-yellow-700/30 dark:text-yellow-300 dark:border-yellow-600' : '',
                                                   (transaction.status === 'failed' || transaction.status === 'refunded' || transaction.status === 'cancelled') ? 'bg-red-100 text-red-800 border-red-300 dark:bg-red-700/30 dark:text-red-300 dark:border-red-600' : '',
                                                   !(transaction.status === 'paid' || transaction.status === 'pending' || transaction.status === 'failed' || transaction.status === 'refunded' || transaction.status === 'cancelled') ? 'border-gray-300 dark:border-gray-600' : ''
                                               ]">
                                                {{ transaction.status_label || getPaymentStatusLabel(transaction.status) }}
                                            </Badge>
                                        </TableCell>
                                        <TableCell class="px-4 py-3 text-sm text-right">
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
                    </div>
                </CardContent>
            </Card>
            </div>

    <Dialog :open="showDeleteTransactionDialog" @update:open="showDeleteTransactionDialog = $event">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Confirmar Exclusão de Transação</DialogTitle>
                <DialogDescription v-if="transactionToDelete">
                    Tem certeza de que deseja excluir esta transação financeira no valor de {{ formatCurrency( (Number(transactionToDelete.total_amount) || 0) + (Number(transactionToDelete.interest_amount) || 0) ) }}
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

<style scoped>
/* Estilos para remover a barra de rolagem, se necessário em algum elemento específico */
.select-none { user-select: none; }
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }

/* Adicionar quaisquer outros estilos específicos aqui */
</style>
