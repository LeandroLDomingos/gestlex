<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Head, Link, router, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue'; // Your main application layout
import Pagination from '@/components/Pagination.vue'; // Your pagination component
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter, DialogClose } from '@/components/ui/dialog';
import { Textarea } from '@/components/ui/textarea';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card'; // Assuming you have Card components from shadcn/ui
import { Badge } from '@/components/ui/badge'; // Assuming you have Badge component
import { PlusCircle, Search, Edit2, Trash2, TrendingUp, TrendingDown, Filter as FilterIcon, ExternalLink, ThumbsUp, AlertTriangle, Info } from 'lucide-vue-next';
import type { BreadcrumbItem, PaginatedResponse, User, Process as MinimalProcess, Contact as MinimalContact } from '@/types';

// Interface for Financial Transaction (from your provided code)
interface FinancialTransaction {
    id: string | number;
    description: string;
    amount: number;
    type: 'income' | 'expense';
    type_label?: string;
    transaction_date: string;
    process_id?: string | number | null;
    process?: { id: string | number; title: string; } | null;
    contact_id?: string | number | null;
    contact?: { id: string | number; name?: string | null; business_name?: string | null; display_name?: string; } | null;
    created_by_user_id?: string | number | null;
    createdBy?: { id: string | number; name: string; } | null;
    notes?: string | null;
    category?: string | null; // Added from previous suggestions
    status?: string | null;   // Added from previous suggestions
    created_at: string;
    deleted_at?: string | null;
}

// Props interface (from your provided code, with additions for dashboard)
interface FinanceIndexProps {
    transactions: PaginatedResponse<FinancialTransaction>;
    filters: {
        type?: 'income' | 'expense' | null;
        date_from?: string | null;
        date_to?: string | null;
        search?: string | null;
        status?: string | null; // Added for consistency
    };
    transactionTypes: Record<'income' | 'expense', string>;
    transactionStatuses?: Record<string, string>; // Optional, if you pass statuses
    processes?: Array<{ id: string | number; title: string }>;
    contacts?: Array<{ id: string | number; display_name: string }>;
    users?: User[];
    // Props for dashboard summary data (can be calculated or passed from backend)
    totalIncome?: number;
    totalExpense?: number;
    currentBalance?: number; // Saldo confirmado
    projectedBalance?: number; // Saldo projetado (se disponível)
    balanceAsOfDate?: string; // Data do saldo (ex: "31 mai")
}

const props = defineProps<FinanceIndexProps>();

// Ziggy route helper (from your provided code)
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

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Painel', href: route('dashboard') },
    { title: 'Visão Geral Financeira', href: route('financial-transactions.index') },
];

// Filters state (from your provided code)
const filterSearchTerm = ref(props.filters.search || '');
const filterType = ref(props.filters.type || null);
const filterDateFrom = ref(props.filters.date_from || '');
const filterDateTo = ref(props.filters.date_to || '');
const filterStatus = ref(props.filters.status || null); // Added status filter

let searchTimeout: number | undefined;
watch([filterSearchTerm, filterType, filterDateFrom, filterDateTo, filterStatus], () => {
    clearTimeout(searchTimeout);
    searchTimeout = window.setTimeout(() => {
        applyFilters();
    }, 300);
});

function applyFilters() {
    const queryParams: Record<string, string | undefined | null> = {};
    if (filterSearchTerm.value) queryParams.search = filterSearchTerm.value;
    if (filterType.value) queryParams.type = filterType.value;
    if (filterDateFrom.value) queryParams.date_from = filterDateFrom.value;
    if (filterDateTo.value) queryParams.date_to = filterDateTo.value;
    if (filterStatus.value) queryParams.status = filterStatus.value;


    router.get(route('financial-transactions.index'), queryParams as any, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}

function resetFilters() {
    filterSearchTerm.value = '';
    filterType.value = null;
    filterDateFrom.value = '';
    filterDateTo.value = '';
    filterStatus.value = null;
    // applyFilters(); // watch will trigger this
}

// New Transaction Dialog state (from your provided code)
const showNewTransactionDialog = ref(false);
const transactionForm = useForm({
    description: '',
    amount: null as number | null,
    type: 'expense' as 'income' | 'expense',
    transaction_date: new Date().toISOString().split('T')[0],
    process_id: null as string | number | null,
    contact_id: null as string | number | null,
    notes: '',
    category: '', // Added from previous suggestions
    status: 'Confirmado', // Default status
    payment_method: '', // Added
    due_date: null as string | null, // Added
    paid_at: null as string | null, // Added
});

function submitNewTransaction() {
    transactionForm.post(route('financial-transactions.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showNewTransactionDialog.value = false;
            transactionForm.reset();
            transactionForm.clearErrors();
        },
        onError: (errors) => {
            console.error("Erro ao criar transação:", errors);
        }
    });
}

// Delete Transaction Dialog state (from your provided code)
const showDeleteTransactionDialog = ref(false);
const transactionToDelete = ref<FinancialTransaction | null>(null);
const deleteTransactionForm = useForm({});

function openDeleteTransactionDialog(transaction: FinancialTransaction) {
    transactionToDelete.value = transaction;
    showDeleteTransactionDialog.value = true;
}

function confirmDeleteTransaction() {
    if (!transactionToDelete.value) return;
    deleteTransactionForm.delete(route('financial-transactions.destroy', transactionToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteTransactionDialog.value = false;
            transactionToDelete.value = null;
        },
        onError: (errors) => console.error("Erro ao excluir transação:", errors)
    });
}

// Formatting functions (from your provided code)
function formatCurrency(value: number | null | undefined): string {
    if (value === null || value === undefined) return 'R$ 0,00'; // Default to R$ 0,00 for undefined
    return new Intl.NumberFormat('pt-BR', { style: 'currency', currency: 'BRL' }).format(value);
}

function formatDate(dateString?: string | null): string {
    if (!dateString) return 'N/A';
    try {
        const date = new Date(dateString.includes('T') || dateString.includes('Z') ? dateString : dateString + 'T00:00:00Z');
        if (isNaN(date.getTime())) return dateString;
        return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC' });
    } catch (e) {
        return dateString;
    }
}

// Computed properties for dashboard cards
const confirmedBalance = computed(() => props.currentBalance ?? props.transactions.data.reduce((acc, t) => acc + t.amount, 0));
const projectedBalanceDisplay = computed(() => props.projectedBalance !== undefined ? formatCurrency(props.projectedBalance) : 'N/D');
const balanceDateDisplay = computed(() => props.balanceAsOfDate || `em ${formatDate(new Date().toISOString())}`);

const recentTransactions = computed(() => props.transactions.data.slice(0, 5));

const showFilterSection = ref(false);

</script>

<template>
    <Head title="Visão Geral Financeira" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-4 sm:p-6 lg:p-8 relative">
            <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-800 dark:text-gray-100">
                    Visão Geral Financeira
                </h1>
                <Button @click="showFilterSection = !showFilterSection" variant="outline" size="sm">
                    <FilterIcon class="h-4 w-4 mr-2" />
                    {{ showFilterSection ? 'Ocultar Filtros' : 'Mostrar Filtros' }}
                </Button>
            </div>

            <Card v-if="showFilterSection" class="mb-6 bg-gray-50 dark:bg-gray-800/50">
                <CardHeader>
                    <CardTitle class="text-lg">Filtros Avançados</CardTitle>
                </CardHeader>
                <CardContent class="p-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 md:grid-cols-3 lg:grid-cols-3 gap-4 items-end">
                        <div>
                            <Label for="search" class="text-xs">Buscar Descrição/Categoria</Label>
                            <Input id="search" type="text" v-model="filterSearchTerm" placeholder="Ex: Honorários, Custas..." class="h-9 text-sm" />
                        </div>
                        <div>
                            <Label for="filterType" class="text-xs">Tipo</Label>
                            <Select v-model="filterType">
                                <SelectTrigger id="filterType" class="h-9 text-sm">
                                    <SelectValue placeholder="Todos os Tipos" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">Todos os Tipos</SelectItem>
                                    <SelectItem value="income">Entrada</SelectItem>
                                    <SelectItem value="expense">Saída</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                         <div>
                            <Label for="filterStatus" class="text-xs">Status</Label>
                            <Select v-model="filterStatus">
                                <SelectTrigger id="filterStatus" class="h-9 text-sm">
                                    <SelectValue placeholder="Todos os Status" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectItem value="">Todos os Status</SelectItem>
                                    <SelectItem v-for="(label, key) in props.transactionStatuses" :key="key" :value="key">{{ label }}</SelectItem>
                                </SelectContent>
                            </Select>
                        </div>
                        <div>
                            <Label for="filterDateFrom" class="text-xs">Data De</Label>
                            <Input id="filterDateFrom" type="date" v-model="filterDateFrom" class="h-9 text-sm" />
                        </div>
                        <div>
                            <Label for="filterDateTo" class="text-xs">Data Até</Label>
                            <Input id="filterDateTo" type="date" v-model="filterDateTo" class="h-9 text-sm" />
                        </div>
                        <div class="flex items-end">
                             <Button @click="resetFilters" variant="ghost" size="sm" class="w-full sm:w-auto h-9 text-xs">Limpar Filtros</Button>
                        </div>
                    </div>
                </CardContent>
            </Card>

            <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">
                <div class="lg:col-span-2 space-y-6">
                    <Card class="h-[300px]"> <CardHeader>
                            <CardTitle>Fluxo de Caixa</CardTitle>
                            <CardDescription>Visualização do fluxo de caixa ao longo do tempo.</CardDescription>
                        </CardHeader>
                        <CardContent class="flex items-center justify-center h-full text-gray-400 dark:text-gray-600">
                            <div class="text-center">
                                <TrendingUp class="h-16 w-16 mx-auto mb-2 opacity-50" />
                                <p>(Placeholder para Gráfico de Fluxo de Caixa)</p>
                            </div>
                        </CardContent>
                    </Card>

                    <Card class="h-[300px]"> <CardHeader>
                            <CardTitle>Balanço Patrimonial</CardTitle>
                            <CardDescription>Resumo dos ativos e passivos.</CardDescription>
                        </CardHeader>
                        <CardContent class="flex items-center justify-center h-full text-gray-400 dark:text-gray-600">
                             <div class="text-center">
                                <Info class="h-16 w-16 mx-auto mb-2 opacity-50" />
                                <p>(Placeholder para Balanço Patrimonial)</p>
                                <p class="text-sm mt-2">Seu controle financeiro pode ficar ainda melhor.</p>
                                <Button variant="outline" size="sm" class="mt-3 bg-teal-500 hover:bg-teal-600 text-white">Adicionar este recurso agora</Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>

                <div class="lg:col-span-1 space-y-6">
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base">Saldos de Caixa</CardTitle>
                        </CardHeader>
                        <CardContent class="space-y-2 text-sm">
                            <div class="flex justify-between">
                                <span>Confirmado:</span>
                                <span class="font-semibold text-green-600 dark:text-green-400">{{ formatCurrency(confirmedBalance) }}</span>
                            </div>
                            <div class="flex justify-between text-gray-500 dark:text-gray-400">
                                <span>Projetado:</span>
                                <span class="font-semibold">{{ projectedBalanceDisplay }}</span>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base">Resultados de Caixa</CardTitle>
                        </CardHeader>
                        <CardContent class="text-sm">
                            <div class="flex justify-between">
                                <span>Saldo {{ balanceDateDisplay }}:</span>
                                <span class="font-semibold" :class="confirmedBalance >= 0 ? 'text-blue-600 dark:text-blue-400' : 'text-red-600 dark:text-red-400'">
                                    {{ formatCurrency(confirmedBalance) }}
                                </span>
                            </div>
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle class="text-base">Últimos Lançamentos</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul v-if="recentTransactions.length > 0" class="space-y-3 text-sm">
                                <li v-for="transaction in recentTransactions" :key="transaction.id" class="flex justify-between items-center">
                                    <div>
                                        <p class="font-medium truncate w-40" :title="transaction.description">{{ transaction.description }}</p>
                                        <p class="text-xs text-gray-500 dark:text-gray-400">{{ formatDate(transaction.transaction_date) }}</p>
                                    </div>
                                    <span :class="transaction.type === 'income' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'" class="font-semibold">
                                        {{ formatCurrency(transaction.amount) }}
                                    </span>
                                </li>
                            </ul>
                            <p v-else class="text-sm text-gray-500 dark:text-gray-400">Nenhum lançamento recente.</p>
                             <Link :href="route('financial-transactions.index')" class="mt-3 inline-block text-sm text-teal-600 hover:text-teal-700 dark:text-teal-400 dark:hover:text-teal-300">
                                Ver todos <ExternalLink class="h-3 w-3 inline-block ml-1" />
                            </Link>
                        </CardContent>
                    </Card>
                     <Card>
                        <CardHeader>
                            <CardTitle class="text-base">Contas a Receber</CardTitle>
                        </CardHeader>
                        <CardContent class="flex flex-col items-center justify-center text-gray-400 dark:text-gray-600 py-6">
                            <ThumbsUp class="h-12 w-12 opacity-50 mb-2" />
                            <p class="text-sm">(Placeholder)</p>
                        </CardContent>
                    </Card>
                </div>
            </div>

            <Card class="mt-8">
                 <CardHeader class="flex flex-row justify-between items-center">
                    <CardTitle>Histórico de Transações</CardTitle>
                    </CardHeader>
                <CardContent class="p-0"> <div class="overflow-x-auto">
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead class="w-[100px]">Data</TableHead>
                                    <TableHead>Descrição</TableHead>
                                    <TableHead class="text-right">Valor</TableHead>
                                    <TableHead class="text-center">Tipo</TableHead>
                                    <TableHead class="text-center">Status</TableHead>
                                    <TableHead>Vinculado a</TableHead>
                                    <TableHead>Registrado por</TableHead>
                                    <TableHead class="text-right w-[100px]">Ações</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <template v-if="props.transactions.data.length > 0">
                                    <TableRow v-for="transaction in props.transactions.data" :key="transaction.id" class="hover:bg-gray-50 dark:hover:bg-gray-700/30">
                                        <TableCell class="text-xs">{{ formatDate(transaction.transaction_date) }}</TableCell>
                                        <TableCell>
                                            <div class="font-medium text-gray-800 dark:text-gray-100">{{ transaction.description }}</div>
                                            <div v-if="transaction.category" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5">Cat: {{ transaction.category }}</div>
                                            <div v-if="transaction.notes" class="text-xs text-gray-500 dark:text-gray-400 mt-0.5 truncate" :title="transaction.notes">
                                                Obs: {{ transaction.notes.substring(0, 40) }}{{ transaction.notes.length > 40 ? '...' : '' }}
                                            </div>
                                        </TableCell>
                                        <TableCell class="text-right font-semibold" :class="transaction.type === 'income' ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">
                                            {{ formatCurrency(transaction.amount) }}
                                        </TableCell>
                                        <TableCell class="text-center">
                                            <Badge :variant="transaction.type === 'income' ? 'default' : 'destructive'" class="text-xs bg-opacity-75 dark:bg-opacity-75">
                                                <TrendingUp v-if="transaction.type === 'income'" class="h-3.5 w-3.5 mr-1" />
                                                <TrendingDown v-else class="h-3.5 w-3.5 mr-1" />
                                                {{ props.transactionTypes[transaction.type] }}
                                            </Badge>
                                        </TableCell>
                                        <TableCell class="text-center">
                                            <Badge v-if="transaction.status" variant="outline" class="text-xs">
                                                {{ transaction.status }}
                                            </Badge>
                                            <span v-else class="text-xs text-gray-400 italic">N/A</span>
                                        </TableCell>
                                        <TableCell class="text-xs">
                                            <div v-if="transaction.process">
                                                <Link :href="route('processes.show', transaction.process.id)" class="text-blue-600 hover:underline dark:text-blue-400">
                                                    Caso: {{ transaction.process.title }}
                                                </Link>
                                            </div>
                                            <div v-if="transaction.contact">
                                                <Link :href="route('contacts.show', transaction.contact.id)" class="text-green-600 hover:underline dark:text-green-400">
                                                    Contato: {{ transaction.contact.display_name || transaction.contact.name || transaction.contact.business_name }}
                                                </Link>
                                            </div>
                                            <span v-if="!transaction.process && !transaction.contact" class="text-gray-400 italic">N/A</span>
                                        </TableCell>
                                        <TableCell class="text-xs text-gray-500 dark:text-gray-400">{{ transaction.createdBy?.name || 'N/A' }}</TableCell>
                                        <TableCell class="text-right">
                                            <Link :href="route('financial-transactions.edit', transaction.id)">
                                                <Button variant="ghost" size="icon" class="h-8 w-8">
                                                    <Edit2 class="h-4 w-4" />
                                                </Button>
                                            </Link>
                                            <Button @click="openDeleteTransactionDialog(transaction)" variant="ghost" size="icon" class="h-8 w-8 text-red-500 hover:text-red-700">
                                                <Trash2 class="h-4 w-4" />
                                            </Button>
                                        </TableCell>
                                    </TableRow>
                                </template>
                                <TableRow v-else>
                                    <TableCell :colspan="8" class="text-center py-10 text-gray-500 dark:text-gray-400">
                                        <AlertTriangle class="h-12 w-12 mx-auto text-gray-400 dark:text-gray-500 mb-2" />
                                        Nenhuma transação financeira encontrada com os filtros atuais.
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </CardContent>
            </Card>
            <Pagination v-if="props.transactions.data.length > 0 && props.transactions.links.length > 3" :links="props.transactions.links" class="mt-6" />

            <div class="fixed bottom-8 right-8 z-50">
                <Button @click="showNewTransactionDialog = true" size="lg" class="rounded-full shadow-lg bg-teal-600 hover:bg-teal-700 text-white w-14 h-14 p-0">
                    <PlusCircle class="h-7 w-7" />
                    <span class="sr-only">Nova Transação</span>
                </Button>
            </div>
        </div>

        <Dialog :open="showNewTransactionDialog" @update:open="showNewTransactionDialog = $event">
            <DialogContent class="sm:max-w-lg">
                <DialogHeader>
                    <DialogTitle>Nova Transação Financeira</DialogTitle>
                    <DialogDescription>Registre uma nova entrada ou saída.</DialogDescription>
                </DialogHeader>
                <form @submit.prevent="submitNewTransaction" class="space-y-4 py-2 max-h-[70vh] overflow-y-auto pr-2">
                    <div>
                        <Label for="trans_description" class="text-sm">Descrição <span class="text-red-500">*</span></Label>
                        <Input id="trans_description" v-model="transactionForm.description" required />
                        <div v-if="transactionForm.errors.description" class="text-xs text-red-500 mt-1">{{ transactionForm.errors.description }}</div>
                    </div>
                    <div class="grid grid-cols-2 gap-4">
                        <div>
                            <Label for="trans_amount" class="text-sm">Valor (R$) <span class="text-red-500">*</span></Label>
                            <Input id="trans_amount" type="number" step="0.01" v-model="transactionForm.amount" required />
                            <div v-if="transactionForm.errors.amount" class="text-xs text-red-500 mt-1">{{ transactionForm.errors.amount }}</div>
                        </div>
                        <div>
                            <Label for="trans_type" class="text-sm">Tipo <span class="text-red-500">*</span></Label>
                            <Select v-model="transactionForm.type" required>
                                <SelectTrigger id="trans_type"><SelectValue placeholder="Selecione" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="(label, key) in props.transactionTypes" :key="key" :value="key">{{ label }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <div v-if="transactionForm.errors.type" class="text-xs text-red-500 mt-1">{{ transactionForm.errors.type }}</div>
                        </div>
                    </div>
                     <div class="grid grid-cols-2 gap-4">
                        <div>
                            <Label for="trans_date" class="text-sm">Data da Transação <span class="text-red-500">*</span></Label>
                            <Input id="trans_date" type="date" v-model="transactionForm.transaction_date" required />
                            <div v-if="transactionForm.errors.transaction_date" class="text-xs text-red-500 mt-1">{{ transactionForm.errors.transaction_date }}</div>
                        </div>
                        <div>
                            <Label for="trans_status" class="text-sm">Status <span class="text-red-500">*</span></Label>
                            <Select v-model="transactionForm.status" required>
                                <SelectTrigger id="trans_status"><SelectValue placeholder="Selecione o Status" /></SelectTrigger>
                                <SelectContent>
                                     <SelectItem v-for="(label, key) in props.transactionStatuses" :key="key" :value="key">{{ label }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <div v-if="transactionForm.errors.status" class="text-xs text-red-500 mt-1">{{ transactionForm.errors.status }}</div>
                        </div>
                    </div>
                     <div>
                        <Label for="trans_category" class="text-sm">Categoria</Label>
                        <Input id="trans_category" v-model="transactionForm.category" />
                        <div v-if="transactionForm.errors.category" class="text-xs text-red-500 mt-1">{{ transactionForm.errors.category }}</div>
                    </div>
                    <div>
                        <Label for="trans_payment_method" class="text-sm">Método de Pagamento</Label>
                        <Input id="trans_payment_method" v-model="transactionForm.payment_method" />
                        <div v-if="transactionForm.errors.payment_method" class="text-xs text-red-500 mt-1">{{ transactionForm.errors.payment_method }}</div>
                    </div>


                    <div>
                        <Label for="trans_process_id" class="text-sm">Vincular ao Caso (Opcional)</Label>
                        <Select v-model="transactionForm.process_id">
                            <SelectTrigger id="trans_process_id"><SelectValue placeholder="Nenhum" /></SelectTrigger>
                            <SelectContent class="max-h-40">
                                <SelectItem value="">Nenhum</SelectItem> <SelectItem v-for="process in props.processes" :key="process.id" :value="String(process.id)">{{ process.title }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <div v-if="transactionForm.errors.process_id" class="text-xs text-red-500 mt-1">{{ transactionForm.errors.process_id }}</div>
                    </div>
                     <div>
                        <Label for="trans_contact_id" class="text-sm">Vincular ao Contato (Opcional)</Label>
                        <Select v-model="transactionForm.contact_id">
                            <SelectTrigger id="trans_contact_id"><SelectValue placeholder="Nenhum" /></SelectTrigger>
                            <SelectContent class="max-h-40">
                                <SelectItem value="">Nenhum</SelectItem> <SelectItem v-for="contact in props.contacts" :key="contact.id" :value="String(contact.id)">{{ contact.display_name }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <div v-if="transactionForm.errors.contact_id" class="text-xs text-red-500 mt-1">{{ transactionForm.errors.contact_id }}</div>
                    </div>
                    <div>
                        <Label for="trans_notes" class="text-sm">Observações</Label>
                        <Textarea id="trans_notes" v-model="transactionForm.notes" rows="3" />
                        <div v-if="transactionForm.errors.notes" class="text-xs text-red-500 mt-1">{{ transactionForm.errors.notes }}</div>
                    </div>
                    <DialogFooter class="pt-4">
                        <DialogClose as-child><Button type="button" variant="outline" @click="showNewTransactionDialog = false; transactionForm.reset(); transactionForm.clearErrors();">Cancelar</Button></DialogClose>
                        <Button type="submit" :disabled="transactionForm.processing">{{ transactionForm.processing ? 'Salvando...' : 'Salvar Transação' }}</Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog :open="showDeleteTransactionDialog" @update:open="showDeleteTransactionDialog = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Confirmar Exclusão</DialogTitle>
                    <DialogDescription v-if="transactionToDelete">
                        Tem certeza que deseja excluir a transação "{{ transactionToDelete.description }}" de {{ formatCurrency(transactionToDelete.amount) }}?
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="mt-4">
                    <Button variant="outline" @click="showDeleteTransactionDialog = false">Cancelar</Button>
                    <Button variant="destructive" @click="confirmDeleteTransaction" :disabled="deleteTransactionForm.processing">
                        {{ deleteTransactionForm.processing ? 'Excluindo...' : 'Excluir' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

    </AppLayout>
</template>

<style scoped>
/* Adicione estilos personalizados se necessário, por exemplo: */
.container {
    /* background-color: #f0f2f5; /* Um cinza claro para o fundo geral, se AppLayout não o fizer */
}
/* Para o FAB se precisar de mais ajustes além do Tailwind */
/* .fixed.bottom-8.right-8 { ... } */
</style>
