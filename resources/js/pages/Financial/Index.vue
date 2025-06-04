<script setup lang="ts">
import { ref, watch, computed, watchEffect } from 'vue'; // Adicionado watchEffect
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
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
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter } from '@/components/ui/dialog';

import { Search, PlusCircle, ChevronDown, Filter as FilterIcon, ListFilter, ArrowUpDown, SlidersHorizontal, X, CalendarIcon, Archive as ArchiveIcon, AlertTriangle, DollarSign, Edit3, Trash2, ExternalLink, ReceiptText, CircleAlert, CheckCircle2 as CheckCircle2Icon, Hourglass, TrendingUp, TrendingDown, Wallet, CreditCard, ArrowRightLeft, CalendarDays } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedResponse } from '@/types/inertia';

// Helper function for Ziggy routes (remains unchanged)
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

// Interface for FinancialTransaction (remains unchanged)
interface FinancialTransaction {
    id: string | number;
    process_id: string | null;
    total_amount: number | string | null;
    interest_amount?: number | string | null;
    payment_type: string | null; // Assuming 'parcelado' is a possible value here
    transaction_nature?: 'income' | 'expense';
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
    supplier_contact?: { id: string; name?: string; business_name?: string; } | null;
}

interface PaginatedFinancialTransactions extends PaginatedResponse<FinancialTransaction> {}

// Props definition (remains unchanged)
const props = defineProps<{
    transactions: PaginatedFinancialTransactions;
    filters: {
        search_process?: string | null;
        search_contact?: string | null;
        search_description?: string | null;
        payment_type_filter?: string | null;
        status_filter?: string | null;
        transaction_nature_filter?: 'income' | 'expense' | null;
        sort_by?: string | null;
        sort_direction?: string | null;
        summary_date_from?: string | null;
        summary_date_to?: string | null;
    };
    paymentTypes: Array<{ value: string; label: string; nature?: 'income' | 'expense' }>; // Ensure this includes 'parcelado'
    paymentStatuses: Array<{ key: string; label: string }>;
    dashboardSummary?: {
        totalReceivedInPeriod: number;
        accountsReceivableOverdueWeekly: number;
        balanceInPeriod: number;
        totalExpensesInPeriod?: number;
        accountsPayableOverdueWeekly?: number;
        summaryCardsDateFrom?: string;
        summaryCardsDateTo?: string;
    };
    latestReceivedTransactions?: FinancialTransaction[];
    upcomingDueTransactions?: FinancialTransaction[];
    latestPaidExpenses?: FinancialTransaction[];
    upcomingDueExpenses?: FinancialTransaction[];
}>();

const page = usePage();

// Default dates and local filters setup (remains unchanged)
const defaultTableDateFrom = new Date(new Date().getFullYear(), new Date().getMonth(), 1).toISOString().split('T')[0];
const defaultTableDateTo = new Date().toISOString().split('T')[0];

const localFilters = ref({
    search_process: props.filters.search_process || '',
    search_contact: props.filters.search_contact || '',
    search_description: props.filters.search_description || '',
    payment_type_filter: props.filters.payment_type_filter || null,
    status_filter: props.filters.status_filter || null,
    transaction_nature_filter: props.filters.transaction_nature_filter || null,
    summary_date_from: props.filters.summary_date_from || defaultTableDateFrom,
    summary_date_to: props.filters.summary_date_to || defaultTableDateTo,
    sort_by: props.filters.sort_by || 'first_installment_due_date',
    sort_direction: props.filters.sort_direction || 'desc',
});

// Quick filters and popover state (remains unchanged)
const quickFilterStatusTable = ref<string | null>(props.filters.status_filter || null);
const quickFilterNatureTable = ref<'income' | 'expense' | null>(props.filters.transaction_nature_filter || null);
const showAdvancedFiltersPopover = ref(false);

// Watchers for quick filters (remains unchanged)
watch(quickFilterStatusTable, (newStatus) => {
    localFilters.value.status_filter = newStatus;
});
watch(quickFilterNatureTable, (newNature) => {
    localFilters.value.transaction_nature_filter = newNature;
});

// Debounced filter application (remains unchanged)
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

// Filter application and reset functions (remains unchanged)
function applyFilters() {
    const activeFilters: Record<string, any> = {};
    for (const key in localFilters.value) {
        const filterValue = localFilters.value[key as keyof typeof localFilters.value];
        if (filterValue !== null && filterValue !== '' && String(filterValue).length > 0) {
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
    localFilters.value.search_description = '';
    localFilters.value.payment_type_filter = null;
    localFilters.value.status_filter = null; 
    localFilters.value.transaction_nature_filter = null;
    localFilters.value.summary_date_from = defaultTableDateFrom;
    localFilters.value.summary_date_to = defaultTableDateTo;
    quickFilterStatusTable.value = null;
    quickFilterNatureTable.value = null;
}

function applyQuickTableFilter(status: string | null, nature?: 'income' | 'expense' | null) {
    localFilters.value.status_filter = status; 
    if (typeof nature !== 'undefined') {
        localFilters.value.transaction_nature_filter = nature;
    } else {
        localFilters.value.transaction_nature_filter = null; 
    }
    quickFilterStatusTable.value = status;
    quickFilterNatureTable.value = localFilters.value.transaction_nature_filter;
}

// Formatting functions (remains unchanged)
const formatDate = (dateString: string | null | undefined): string => {
    if (!dateString) return 'N/A';
    try {
        const date = new Date(dateString.includes('T') || dateString.includes('Z') ? dateString : dateString + 'T00:00:00Z');
        return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC' });
    } catch (e) {
        return dateString || 'N/A';
    }
};

const formatCurrency = (value: number | string | null | undefined, nature?: 'income' | 'expense' | null): string => {
    const numValue = Number(value);
    if (value === null || typeof value === 'undefined' || isNaN(numValue)) return 'R$ 0,00';
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

// Computed property for summary cards period text (remains unchanged)
const summaryCardsPeriodText = computed(() => {
    if (props.dashboardSummary?.summaryCardsDateFrom && props.dashboardSummary?.summaryCardsDateTo) {
        return `${formatDate(props.dashboardSummary.summaryCardsDateFrom)} - ${formatDate(props.dashboardSummary.summaryCardsDateTo)}`;
    }
    return "Período Padrão"; 
});

// Breadcrumbs (remains unchanged)
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Financeiro', href: routeHelper('financial-transactions.index') },
];

// Delete transaction dialog logic (remains unchanged)
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
        },
        onError: (errors) => {
            console.error('Erro ao excluir transação:', errors);
            alert('Falha ao excluir transação. Verifique o console para mais detalhes.');
        }
    });
}

// Edit transaction logic (remains unchanged)
function editTransaction(transaction: FinancialTransaction) {
    if (getTransactionNature(transaction) === 'expense') {
        router.visit(routeHelper('financial-transactions.edit', transaction.id));
        return;
    }
    if (transaction.payment_type === 'honorario' && transaction.process?.id) {
         router.visit(routeHelper('processes.show', transaction.process.id) + `?tab=payments&fee_to_edit=${transaction.id}`);
    } else if (transaction.process?.id) {
         router.visit(routeHelper('processes.show', transaction.process.id) + `?tab=payments&payment_to_edit=${transaction.id}`);
    } else {
        alert('Não é possível editar este tipo de transação diretamente aqui ou falta um caso associado.');
    }
}

// Table headers definition (remains unchanged)
const tableHeaders: { key: string; label: string; sortable: boolean, class?: string, align?: 'left' | 'center' | 'right' }[] = [
    { key: 'transaction_nature', label: 'Natureza', sortable: true, class: 'w-[8%]' },
    { key: 'process.title', label: 'Caso/Origem', sortable: true, class: 'w-[18%]' },
    { key: 'contact', label: 'Contato/Fornecedor', sortable: true, class: 'w-[15%]' },
    { key: 'notes', label: 'Descrição/Notas', sortable: false, class: 'w-[20%] max-w-xs truncate' },
    { key: 'total_value_with_interest', label: 'Valor Total', sortable: true, class: 'w-[10%]', align: 'right' },
    { key: 'payment_type', label: 'Tipo', sortable: true, class: 'w-[8%]' },
    { key: 'first_installment_due_date', label: 'Vencimento', sortable: true, class: 'w-[8%]' },
    { key: 'down_payment_date', label: 'Data Pgto./Recb.', sortable: true, class: 'w-[8%]' },
    { key: 'status', label: 'Status', sortable: true, class: 'w-[8%]', align: 'center' },
    { key: 'actions', label: 'Ações', sortable: false, class: 'w-[7%]', align: 'right' },
];

// Sort handling (remains unchanged)
const handleSort = (columnKey: string) => {
    if (localFilters.value.sort_by === columnKey) {
        localFilters.value.sort_direction = localFilters.value.sort_direction === 'asc' ? 'desc' : 'asc';
    } else {
        localFilters.value.sort_by = columnKey;
        localFilters.value.sort_direction = 'asc';
    }
};

// Value text class and transaction nature determination (remains unchanged)
const valueTextClass = (transaction: FinancialTransaction) => {
    if (getTransactionNature(transaction) === 'expense') {
        return 'text-red-600 dark:text-red-400';
    }
    if (getTransactionNature(transaction) === 'income') {
        return 'text-green-600 dark:text-green-400';
    }
    return 'text-gray-800 dark:text-gray-200';
};

const getTransactionNature = (transaction: FinancialTransaction): 'income' | 'expense' | 'unknown' => {
    if (transaction.transaction_nature) return transaction.transaction_nature;
    const expenseTypes = ['despesa_operacional', 'compra_material', 'pagamento_fornecedor', 'custas_processuais', 'adiantamento_despesa']; 
    if (transaction.payment_type && expenseTypes.includes(transaction.payment_type)) {
        return 'expense';
    }
    return 'income'; 
}

// Logic for "Add Expense" Modal
const showAddExpenseModal = ref(false);
const addExpenseForm = useForm({
    notes: '',
    total_amount: null as number | string | null,
    payment_type: null as string | null,
    first_installment_due_date: new Date().toISOString().split('T')[0],
    transaction_nature: 'expense' as const,
    status: 'pending',
    number_of_installments: null as number | null,
    value_of_installment: null as number | string | null,
    supplier_contact_id: null as string | number | null,
    payment_method: null as string | null,
    down_payment_amount: null as number | string | null,
    interest_amount: null as number | string | null,
});

const isInstallmentPaymentType = computed(() => {
    // **IMPORTANTE**: Ajuste 'parcelado' para o valor exato do seu Enum/backend que representa "Parcelado"
    // Exemplo: Se o valor no backend for 'installments', use 'installments' aqui.
    const installmentTypeValueFromProps = props.paymentTypes.find(pt => pt.label.toLowerCase().includes('parcelado'))?.value;
    return addExpenseForm.payment_type === installmentTypeValueFromProps && installmentTypeValueFromProps !== undefined;
});


function openAddExpenseModal() {
    addExpenseForm.reset();
    addExpenseForm.first_installment_due_date = new Date().toISOString().split('T')[0];
    addExpenseForm.status = 'pending';
    addExpenseForm.transaction_nature = 'expense';
    showAddExpenseModal.value = true;
}

function submitAddExpense() {
    addExpenseForm.post(routeHelper('financial-transactions.store'), {
        preserveScroll: true,
        onSuccess: () => {
            showAddExpenseModal.value = false;
            addExpenseForm.reset();
        },
        onError: (errors) => {
            console.error('Erro ao adicionar despesa:', errors);
        }
    });
}

// Watch for changes in payment_type to clear installment fields if not applicable
// Also, automatically calculate installment value
watchEffect(() => {
    const paymentType = addExpenseForm.payment_type;
    const totalAmount = parseFloat(String(addExpenseForm.total_amount));
    const numInstallments = parseInt(String(addExpenseForm.number_of_installments), 10);

    if (isInstallmentPaymentType.value) {
        if (!isNaN(totalAmount) && totalAmount > 0 && !isNaN(numInstallments) && numInstallments > 0) {
            const calculatedInstallmentValue = totalAmount / numInstallments;
            // Arredonda para 2 casas decimais e converte para string para o input
            addExpenseForm.value_of_installment = calculatedInstallmentValue.toFixed(2);
        } else if (numInstallments <= 0 && addExpenseForm.value_of_installment !== '') {
             // Se o número de parcelas for inválido, mas já havia um valor, limpa.
             // Ou você pode optar por não limpar automaticamente e deixar a validação do backend tratar.
             // addExpenseForm.value_of_installment = ''; // Descomente se quiser limpar
        }
    } else {
        addExpenseForm.number_of_installments = null;
        addExpenseForm.value_of_installment = null;
    }
});

</script>

<template>
    <Head title="Financeiro" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-4 sm:p-6 lg:p-8 space-y-6">
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                <h1 class="text-3xl font-bold text-gray-800 dark:text-gray-100">Painel Financeiro</h1>
                <Button variant="default" size="sm" @click="openAddExpenseModal">
                    <PlusCircle class="h-4 w-4 mr-2" />
                    Adicionar Despesa
                </Button>
            </div>

            <div v-if="props.dashboardSummary" class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-5 gap-4">
                <Card class="shadow-lg hover:shadow-xl transition-shadow">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Recebido</CardTitle>
                        <TrendingUp class="h-5 w-5 text-green-500" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-bold text-green-600 dark:text-green-400">{{ formatCurrency(props.dashboardSummary.totalReceivedInPeriod, 'income') }}</div>
                        <p class="text-xs text-muted-foreground">
                            {{ summaryCardsPeriodText }}
                        </p>
                    </CardContent>
                </Card>
                
                <Card class="shadow-lg hover:shadow-xl transition-shadow">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Despesas</CardTitle>
                        <TrendingDown class="h-5 w-5 text-red-500" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-bold text-red-600 dark:text-red-400">{{ formatCurrency(props.dashboardSummary.totalExpensesInPeriod, 'expense') }}</div>
                        <p class="text-xs text-muted-foreground">
                            {{ summaryCardsPeriodText }}
                        </p>
                    </CardContent>
                </Card>

                <Card class="shadow-lg hover:shadow-xl transition-shadow">
                        <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                            <CardTitle class="text-sm font-medium">Saldo</CardTitle>
                            <Wallet class="h-5 w-5 text-blue-500" />
                        </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-bold" :class="[ (props.dashboardSummary.balanceInPeriod || 0) >= 0 ? 'text-blue-600 dark:text-blue-400' : 'text-orange-600 dark:text-orange-400' ]">
                            {{ formatCurrency(props.dashboardSummary.balanceInPeriod) }}
                        </div>
                          <p class="text-xs text-muted-foreground">Saldo geral acumulado</p>
                    </CardContent>
                </Card>

                <Card class="shadow-lg hover:shadow-xl transition-shadow">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">A Receber (Vencidas/Semana)</CardTitle>
                        <Hourglass class="h-5 w-5 text-yellow-500" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-bold text-yellow-600 dark:text-yellow-400">{{ formatCurrency(props.dashboardSummary.accountsReceivableOverdueWeekly, 'income') }}</div>
                        <Link :href="routeHelper('financial-transactions.index', { status_filter: 'pending', transaction_nature_filter: 'income', sort_by: 'first_installment_due_date', sort_direction: 'asc' })" class="text-xs text-indigo-600 hover:underline">
                            Ver pendentes de receita
                        </Link>
                    </CardContent>
                </Card>

                <Card class="shadow-lg hover:shadow-xl transition-shadow">
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">A Pagar (Vencidas/Semana)</CardTitle>
                        <AlertTriangle class="h-5 w-5 text-orange-500" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-bold text-orange-600 dark:text-orange-400">{{ formatCurrency(props.dashboardSummary.accountsPayableOverdueWeekly, 'expense') }}</div>
                        <Link :href="routeHelper('financial-transactions.index', { status_filter: 'pending', transaction_nature_filter: 'expense', sort_by: 'first_installment_due_date', sort_direction: 'asc' })" class="text-xs text-indigo-600 hover:underline">
                            Ver pendentes de despesa
                        </Link>
                    </CardContent>
                </Card>
            </div>
            <Separator class="my-8"/>

            <div class="space-y-8"> 
                <div class="space-y-3">
                    <div class="flex justify-between items-center">
                        <h2 class="text-xl font-semibold">Contas a Pagar Vencendo <span class="text-sm font-normal text-muted-foreground">(Próx. 30 dias)</span></h2>
                        <Button variant="link" size="sm" @click="applyQuickTableFilter('pending', 'expense')" class="text-indigo-600">Ver Todas as Pendentes</Button>
                    </div>
                    <Card class="shadow-md">
                        <CardContent class="p-0">
                            <Table v-if="props.upcomingDueExpenses && props.upcomingDueExpenses.length > 0">
                                <TableHeader><TableRow><TableHead class="w-[40%]">Descrição/Fornecedor</TableHead><TableHead class="w-[30%]">Vencimento</TableHead><TableHead class="w-[30%] text-right">Valor Pendente</TableHead></TableRow></TableHeader>
                                <TableBody>
                                    <TableRow v-for="expense in props.upcomingDueExpenses" :key="`upcoming-exp-${expense.id}`">
                                        <TableCell class="text-sm">{{ expense.notes || expense.supplier_contact?.name || expense.supplier_contact?.business_name || 'Despesa avulsa' }}</TableCell>
                                        <TableCell class="text-sm">{{ formatDate(expense.first_installment_due_date) }}</TableCell>
                                        <TableCell class="text-right text-sm font-medium" :class="valueTextClass(expense)">{{ formatCurrency(expense.total_amount) }}</TableCell>
                                    </TableRow>
                                </TableBody>
                            </Table>
                            <p v-else class="p-6 text-sm text-center text-gray-500 dark:text-gray-400">Nenhuma conta a pagar vencendo nos próximos 30 dias.</p>
                        </CardContent>
                    </Card>
                </div>
            </div>
            <Separator class="my-8"/>

            <Card class="shadow-md">
                <CardContent class="p-4 md:p-6"> 
                    <div class="space-y-4">
                        <div class="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
                            <h2 class="text-xl font-semibold text-gray-800 dark:text-gray-100">Todas as Transações Financeiras</h2>
                                <div class="flex items-center gap-2 flex-wrap">
                                 <Button :variant="quickFilterNatureTable === null && quickFilterStatusTable === null ? 'secondary' : 'outline'" size="sm" @click="applyQuickTableFilter(null, null)" class="h-9">Todas</Button>
                                 <Button :variant="quickFilterNatureTable === 'income' && quickFilterStatusTable === 'pending' ? 'secondary' : 'outline'" size="sm" @click="applyQuickTableFilter('pending', 'income')" class="h-9">A Receber</Button>
                                 <Button :variant="quickFilterNatureTable === 'income' && quickFilterStatusTable === 'paid' ? 'secondary' : 'outline'" size="sm" @click="applyQuickTableFilter('paid', 'income')" class="h-9">Recebidas</Button>
                                 <Button :variant="quickFilterNatureTable === 'expense' && quickFilterStatusTable === 'pending' ? 'secondary' : 'outline'" size="sm" @click="applyQuickTableFilter('pending', 'expense')" class="h-9">A Pagar</Button>
                                 <Button :variant="quickFilterNatureTable === 'expense' && quickFilterStatusTable === 'paid' ? 'secondary' : 'outline'" size="sm" @click="applyQuickTableFilter('paid', 'expense')" class="h-9">Pagas</Button>
                                </div>
                        </div>
                        
                        <Card class="bg-slate-50 dark:bg-slate-800/50 border shadow-sm">
                            <CardContent class="p-3">
                                <div class="flex flex-col sm:flex-row items-center gap-3">
                                    <div class="flex items-center gap-2">
                                        <CalendarDays class="h-5 w-5 text-muted-foreground"/>
                                        <Label for="summary_date_from_table" class="text-sm font-medium shrink-0">Filtrar Tabela por Período:</Label>
                                    </div>
                                    <div class="flex items-center gap-2 flex-grow sm:flex-grow-0">
                                        <Input id="summary_date_from_table" type="date" v-model="localFilters.summary_date_from" class="h-9 w-full sm:w-40 text-sm" />
                                        <Label for="summary_date_to_table" class="text-sm shrink-0">até</Label>
                                        <Input id="summary_date_to_table" type="date" v-model="localFilters.summary_date_to" class="h-9 w-full sm:w-40 text-sm" />
                                    </div>
                                </div>
                            </CardContent>
                        </Card>
                        <div class="flex flex-col md:flex-row gap-3 items-end pt-2">
                            <div class="flex-grow">
                                <Label for="search_process_table" class="text-xs">Buscar por Caso/Origem</Label>
                                <Input id="search_process_table" v-model="localFilters.search_process" placeholder="Título do caso, origem..." class="mt-1 h-9" />
                            </div>
                            <div class="flex-grow">
                                <Label for="search_contact_table" class="text-xs">Buscar por Contato/Fornecedor</Label>
                                <Input id="search_contact_table" v-model="localFilters.search_contact" placeholder="Nome do contato/fornecedor..." class="mt-1 h-9" />
                            </div>
                                <div class="flex-grow"> <Label for="search_description_table" class="text-xs">Buscar por Descrição/Notas</Label>
                                <Input id="search_description_table" v-model="localFilters.search_description" placeholder="Termos na descrição..." class="mt-1 h-9" />
                            </div>
                            <Popover v-model:open="showAdvancedFiltersPopover">
                                <PopoverTrigger as-child>
                                    <Button variant="outline" size="sm" class="h-9 mt-auto">
                                        <SlidersHorizontal class="h-4 w-4 mr-2" />
                                        Mais Filtros
                                        <ChevronDown class="h-4 w-4 ml-1 opacity-70" />
                                    </Button>
                                </PopoverTrigger>
                                <PopoverContent class="w-80 p-4 space-y-3" align="end">
                                    <div> <Label for="transaction_nature_filter_popover" class="text-xs">Natureza da Transação</Label>
                                        <Select v-model="localFilters.transaction_nature_filter">
                                            <SelectTrigger id="transaction_nature_filter_popover" class="mt-1 h-9"><SelectValue placeholder="Todas as Naturezas" /></SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    <SelectItem :value="null">Todas</SelectItem>
                                                    <SelectItem value="income">Receitas</SelectItem>
                                                    <SelectItem value="expense">Despesas</SelectItem>
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div>
                                        <Label for="payment_type_filter_popover" class="text-xs">Tipo de Transação</Label>
                                        <Select v-model="localFilters.payment_type_filter">
                                            <SelectTrigger id="payment_type_filter_popover" class="mt-1 h-9"><SelectValue placeholder="Todos os Tipos" /></SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    <SelectItem :value="null">Todos os Tipos</SelectItem>
                                                    <SelectItem v-for="type in paymentTypes" :key="type.value" :value="type.value">{{ type.label }} <span v-if="type.nature" class="text-xs opacity-70">({{ type.nature === 'income' ? 'Receita' : 'Despesa' }})</span></SelectItem>
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div>
                                        <Label for="status_filter_popover_table" class="text-xs">Status da Transação</Label>
                                        <Select v-model="localFilters.status_filter">
                                            <SelectTrigger id="status_filter_popover_table" class="mt-1 h-9"><SelectValue placeholder="Todos os Status" /></SelectTrigger>
                                            <SelectContent>
                                                <SelectGroup>
                                                    <SelectItem :value="null">Todos os Status</SelectItem>
                                                    <SelectItem v-for="statusOpt in paymentStatuses" :key="statusOpt.key" :value="statusOpt.key">{{ statusOpt.label }}</SelectItem>
                                                </SelectGroup>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                        <div class="flex justify-end pt-2">
                                         <Button variant="ghost" size="sm" @click="resetTableFilters(); showAdvancedFiltersPopover = false" class="text-xs h-8">Limpar Filtros</Button>
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
                                        <TableCell class="px-4 py-3 text-sm text-center">
                                            <Badge :variant="getTransactionNature(transaction) === 'income' ? 'default' : 'destructive'" class="capitalize text-xs">
                                                <TrendingUp v-if="getTransactionNature(transaction) === 'income'" class="h-3 w-3 mr-1"/>
                                                <TrendingDown v-else-if="getTransactionNature(transaction) === 'expense'" class="h-3 w-3 mr-1"/>
                                                {{ getTransactionNature(transaction) === 'income' ? 'Receita' : (getTransactionNature(transaction) === 'expense' ? 'Despesa' : 'N/D') }}
                                            </Badge>
                                        </TableCell>
                                        <TableCell class="px-4 py-3 text-sm">
                                            <Link v-if="transaction.process" :href="routeHelper('processes.show', transaction.process.id)" class="text-indigo-600 hover:underline font-medium">
                                                {{ transaction.process.title || 'N/A' }}
                                            </Link>
                                            <span v-else-if="getTransactionNature(transaction) === 'expense'" class="text-gray-500 italic">Despesa Avulsa</span>
                                            <span v-else class="text-gray-500 italic">Receita Avulsa</span>
                                        </TableCell>
                                        <TableCell class="px-4 py-3 text-sm">
                                            <Link v-if="transaction.process?.contact" :href="routeHelper('contacts.show', transaction.process.contact.id)" class="text-indigo-600 hover:underline">
                                                {{ transaction.process.contact.name || transaction.process.contact.business_name || 'N/A' }}
                                            </Link>
                                            <Link v-else-if="transaction.supplier_contact" :href="routeHelper('contacts.show', transaction.supplier_contact.id)" class="text-indigo-600 hover:underline">
                                                {{ transaction.supplier_contact.name || transaction.supplier_contact.business_name || 'N/A' }} (Fornecedor)
                                            </Link>
                                            <span v-else class="text-gray-500">N/A</span>
                                        </TableCell>
                                        <TableCell class="px-4 py-3 text-sm max-w-xs truncate" :title="transaction.notes || ''">{{ transaction.notes || 'N/A' }}</TableCell>
                                        <TableCell class="px-4 py-3 text-sm text-right font-medium" :class="valueTextClass(transaction)">
                                            <span>
                                                {{ formatCurrency( (Number(transaction.total_amount) || 0) + (Number(transaction.interest_amount) || 0) ) }}
                                            </span>
                                            <div v-if="transaction.interest_amount && parseFloat(String(transaction.interest_amount)) > 0" 
                                                 class="text-xs mt-0.5"
                                                 :class="getTransactionNature(transaction) === 'expense' ? 'text-red-500 dark:text-red-400 opacity-80' : 'text-green-500 dark:text-green-400 opacity-80'">
                                                (Principal: {{ formatCurrency(transaction.total_amount) }} + Juros: {{ formatCurrency(transaction.interest_amount) }})
                                            </div>
                                        </TableCell>
                                        <TableCell class="px-4 py-3 text-sm">{{ getPaymentTypeLabel(transaction.payment_type) }}</TableCell>
                                        <TableCell class="px-4 py-3 text-sm">{{ formatDate(transaction.first_installment_due_date) }}</TableCell>
                                        <TableCell class="px-4 py-3 text-sm">{{ (transaction.status === 'paid' || transaction.status === 'received') ? formatDate(transaction.down_payment_date) : 'N/A' }}</TableCell>
                                        <TableCell class="px-4 py-3 text-sm text-center">
                                            <Badge :variant="'outline'"
                                               :class="[
                                                    'text-xs capitalize',
                                                    (transaction.status === 'paid' || transaction.status === 'received') ? 'bg-green-100 text-green-800 border-green-300 dark:bg-green-700/30 dark:text-green-300 dark:border-green-600' : '',
                                                    transaction.status === 'pending' ? 'bg-yellow-100 text-yellow-800 border-yellow-300 dark:bg-yellow-700/30 dark:text-yellow-300 dark:border-yellow-600' : '',
                                                    transaction.status === 'overdue' ? 'bg-red-100 text-red-800 border-red-300 dark:bg-red-700/30 dark:text-red-300 dark:border-red-600' : '',
                                                    (transaction.status === 'failed' || transaction.status === 'refunded' || transaction.status === 'cancelled') ? 'bg-red-100 text-red-800 border-red-300 dark:bg-red-700/30 dark:text-red-300 dark:border-red-600' : '',
                                                    !['paid', 'received', 'pending', 'overdue', 'failed', 'refunded', 'cancelled'].includes(transaction.status || '') ? 'border-gray-300 dark:border-gray-600' : ''
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
                        <Pagination v-if="transactions.data && transactions.data.length > 0" :links="transactions.links" :from="transactions.from" :to="transactions.to" :total="transactions.total" />
                    </div>
                </CardContent>
            </Card>
        </div>

    <Dialog :open="showAddExpenseModal" @update:open="showAddExpenseModal = $event">
        <DialogContent class="sm:max-w-lg">
            <DialogHeader>
                <DialogTitle>Adicionar Nova Despesa</DialogTitle>
                <DialogDescription>
                    Preencha os detalhes da nova despesa abaixo.
                </DialogDescription>
            </DialogHeader>
            <form @submit.prevent="submitAddExpense">
                <div class="grid gap-4 py-4">
                    <div class="grid grid-cols-4 items-center gap-x-4 gap-y-2">
                        <Label for="expense-notes" class="text-right col-span-4 sm:col-span-1">Descrição</Label>
                        <Input id="expense-notes" v-model="addExpenseForm.notes" class="col-span-4 sm:col-span-3" placeholder="Ex: Compra de material de escritório" />
                        <div v-if="addExpenseForm.errors.notes" class="col-span-4 sm:col-start-2 sm:col-span-3 text-red-500 text-xs">{{ addExpenseForm.errors.notes }}</div>
                    </div>

                    <div class="grid grid-cols-4 items-center gap-x-4 gap-y-2">
                        <Label for="expense-total_amount" class="text-right col-span-4 sm:col-span-1">Valor Total (R$)</Label>
                        <Input id="expense-total_amount" v-model="addExpenseForm.total_amount" type="number" step="0.01" class="col-span-4 sm:col-span-3" placeholder="Ex: 150.75" />
                        <div v-if="addExpenseForm.errors.total_amount" class="col-span-4 sm:col-start-2 sm:col-span-3 text-red-500 text-xs">{{ addExpenseForm.errors.total_amount }}</div>
                    </div>
                    
                    <div class="grid grid-cols-4 items-center gap-x-4 gap-y-2">
                        <Label for="expense-payment_type" class="text-right col-span-4 sm:col-span-1">Tipo de Pagamento</Label>
                        <Select v-model="addExpenseForm.payment_type">
                            <SelectTrigger id="expense-payment_type" class="col-span-4 sm:col-span-3">
                                <SelectValue placeholder="Selecione o tipo" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectItem v-for="type in props.paymentTypes" :key="`exp-type-${type.value}`" :value="type.value">
                                        {{ type.label }}
                                        <span v-if="type.nature" class="text-xs opacity-70 ml-1">
                                            ({{ type.nature === 'income' ? 'Receita' : (type.nature === 'expense' ? 'Despesa' : type.nature) }})
                                        </span>
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <div v-if="addExpenseForm.errors.payment_type" class="col-span-4 sm:col-start-2 sm:col-span-3 text-red-500 text-xs">{{ addExpenseForm.errors.payment_type }}</div>
                    </div>

                    <!-- Campos de Parcelamento - Condicional -->
                    <template v-if="isInstallmentPaymentType">
                        <div class="grid grid-cols-4 items-center gap-x-4 gap-y-2">
                            <Label for="expense-number_of_installments" class="text-right col-span-4 sm:col-span-1">Nº de Parcelas</Label>
                            <Input id="expense-number_of_installments" v-model="addExpenseForm.number_of_installments" type="number" step="1" min="1" class="col-span-4 sm:col-span-3" placeholder="Ex: 3" />
                            <div v-if="addExpenseForm.errors.number_of_installments" class="col-span-4 sm:col-start-2 sm:col-span-3 text-red-500 text-xs">{{ addExpenseForm.errors.number_of_installments }}</div>
                        </div>
                        <div class="grid grid-cols-4 items-center gap-x-4 gap-y-2">
                            <Label for="expense-value_of_installment" class="text-right col-span-4 sm:col-span-1">Valor da Parcela (R$)</Label>
                            <Input id="expense-value_of_installment" v-model="addExpenseForm.value_of_installment" type="number" step="0.01" class="col-span-4 sm:col-span-3" placeholder="Calculado automaticamente" />
                            <div v-if="addExpenseForm.errors.value_of_installment" class="col-span-4 sm:col-start-2 sm:col-span-3 text-red-500 text-xs">{{ addExpenseForm.errors.value_of_installment }}</div>
                        </div>
                    </template>

                    <div class="grid grid-cols-4 items-center gap-x-4 gap-y-2">
                        <Label for="expense-first_installment_due_date" class="text-right col-span-4 sm:col-span-1">Data de Vencimento</Label>
                        <Input id="expense-first_installment_due_date" v-model="addExpenseForm.first_installment_due_date" type="date" class="col-span-4 sm:col-span-3" />
                        <div v-if="addExpenseForm.errors.first_installment_due_date" class="col-span-4 sm:col-start-2 sm:col-span-3 text-red-500 text-xs">{{ addExpenseForm.errors.first_installment_due_date }}</div>
                    </div>
                    
                    <div class="grid grid-cols-4 items-center gap-x-4 gap-y-2">
                        <Label for="expense-status" class="text-right col-span-4 sm:col-span-1">Status</Label>
                        <Select v-model="addExpenseForm.status">
                            <SelectTrigger id="expense-status" class="col-span-4 sm:col-span-3">
                                <SelectValue placeholder="Selecione o status" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                     <SelectItem v-for="statusOpt in props.paymentStatuses.filter(s => ['pending', 'paid', 'overdue'].includes(s.key))" :key="`exp-status-${statusOpt.key}`" :value="statusOpt.key">
                                        {{ statusOpt.label }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <div v-if="addExpenseForm.errors.status" class="col-span-4 sm:col-start-2 sm:col-span-3 text-red-500 text-xs">{{ addExpenseForm.errors.status }}</div>
                    </div>
                     <!-- Outros campos opcionais podem ser adicionados aqui, como Fornecedor, Método de Pagamento, etc. -->
                </div>
                <DialogFooter class="mt-6">
                    <Button variant="outline" type="button" @click="showAddExpenseModal = false">Cancelar</Button>
                    <Button type="submit" :disabled="addExpenseForm.processing">
                        <svg v-if="addExpenseForm.processing" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ addExpenseForm.processing ? 'Salvando...' : 'Salvar Despesa' }}
                    </Button>
                </DialogFooter>
            </form>
        </DialogContent>
    </Dialog>

    <Dialog :open="showDeleteTransactionDialog" @update:open="showDeleteTransactionDialog = $event">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Confirmar Exclusão de Transação</DialogTitle>
                <DialogDescription v-if="transactionToDelete">
                    Tem certeza de que deseja excluir esta transação financeira ({{ getTransactionNature(transactionToDelete) === 'income' ? 'Receita' : 'Despesa' }})
                    no valor de {{ formatCurrency( (Number(transactionToDelete.total_amount) || 0) + (Number(transactionToDelete.interest_amount) || 0) ) }}
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
.select-none { user-select: none; }
.no-scrollbar::-webkit-scrollbar { display: none; }
.no-scrollbar { -ms-overflow-style: none; scrollbar-width: none; }
</style>
