<script setup lang="ts">
import { ref } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Badge } from '@/components/ui/badge';
import { DollarSign, TrendingUp, TrendingDown, CalendarClock, AlertTriangle, ListChecks } from 'lucide-vue-next';

// Supondo interfaces similares às de Show.vue e Financial/Index.vue
interface Payment {
    id: string | number;
    total_amount: number | string | null;
    payment_type: string | null;
    status_label?: string;
    status?: string;
    notes: string | null;
    first_installment_due_date: string | null;
    down_payment_date: string | null; // Data do pagamento efetivo
    process?: { id: string; title: string; };
}
interface Expense {
    id: string | number;
    description: string;
    amount: number | string | null;
    expense_date: string;
    category?: string;
    status_label?: string;
    status?: string;
    process?: { id: string; title: string; };
}
interface Receivable extends Payment { // Cobranças são baseadas em ProcessPayment
    // Campos adicionais se necessário
}


interface BreadcrumbItem {
    title: string;
    href: string;
}

const props = defineProps<{
    filters: {
        period_start: string;
        period_end: string;
    };
    kpis: {
        totalReceived: number;
        totalExpenses: number;
        netBalanceForPeriod: number;
        totalPendingToReceive: number;
    };
    recentReceipts: Payment[];
    recentExpenses: Expense[];
    upcomingReceivables: Receivable[];
    paymentTypes: Array<{ value: string; label: string }>;
    paymentStatuses: Array<{ key: string; label: string }>;
    expenseStatuses: Array<{ key: string; label: string }>;
}>();

const RGlobal = (window as any).route;
const routeHelper = (name?: string, params?: any, absolute?: boolean): string => {
    if (typeof RGlobal === 'function') { return RGlobal(name, params, absolute); }
    let url = `/${name?.replace(/\./g, '/') || ''}`;
    if (params) { /* ... */ }
    return url;
};

const filterForm = useForm({
    period_start: props.filters.period_start,
    period_end: props.filters.period_end,
});

function applyFilters() {
    filterForm.get(routeHelper('financial.dashboard'), {
        preserveState: true,
        preserveScroll: true,
    });
}

const formatDate = (dateString: string | null | undefined): string => {
    if (!dateString) return 'N/A';
    try {
        const date = new Date(dateString.includes('T') || dateString.includes('Z') ? dateString : dateString + 'T00:00:00Z');
        return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC' });
    } catch (e) { return dateString || 'N/A'; }
};

const formatCurrency = (value: number | string | null | undefined): string => {
    const numValue = Number(value);
    if (value === null || typeof value === 'undefined' || isNaN(numValue)) return 'R$ 0,00';
    return numValue.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
};

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Painel', href: routeHelper('dashboard') },
    { title: 'Dashboard Financeiro', href: routeHelper('financial.dashboard') },
];

// Função para determinar a cor do Badge com base no status
const getStatusBadgeClass = (status: string | null | undefined): string => {
    if (status === 'paid') return 'bg-green-100 text-green-800 border-green-300 dark:bg-green-700/30 dark:text-green-300 dark:border-green-600';
    if (status === 'pending') return 'bg-yellow-100 text-yellow-800 border-yellow-300 dark:bg-yellow-700/30 dark:text-yellow-300 dark:border-yellow-600';
    if (status === 'failed' || status === 'refunded' || status === 'cancelled') return 'bg-red-100 text-red-800 border-red-300 dark:bg-red-700/30 dark:text-red-300 dark:border-red-600';
    return 'border-gray-300 dark:border-gray-600';
};

</script>

<template>
    <Head title="Dashboard Financeiro" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-4 sm:p-6 lg:p-8 space-y-6">
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-100">Dashboard Financeiro</h1>
                <div class="flex items-center gap-2">
                    <Input type="date" v-model="filterForm.period_start" />
                    <span class="text-gray-500">até</span>
                    <Input type="date" v-model="filterForm.period_end" />
                    <Button @click="applyFilters" :disabled="filterForm.processing">Aplicar Filtros</Button>
                </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-4 gap-6">
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Recebido (Período)</CardTitle>
                        <DollarSign class="h-5 w-5 text-green-500" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-bold text-green-600 dark:text-green-400">{{ formatCurrency(kpis.totalReceived) }}</div>
                        <p class="text-xs text-muted-foreground">De {{ formatDate(filters.period_start) }} a {{ formatDate(filters.period_end) }}</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Total Despesas (Período)</CardTitle>
                        <TrendingDown class="h-5 w-5 text-red-500" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-bold text-red-600 dark:text-red-400">{{ formatCurrency(kpis.totalExpenses) }}</div>
                         <p class="text-xs text-muted-foreground">De {{ formatDate(filters.period_start) }} a {{ formatDate(filters.period_end) }}</p>
                    </CardContent>
                </Card>
                <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Saldo (Período)</CardTitle>
                        <TrendingUp class="h-5 w-5" :class="kpis.netBalanceForPeriod >= 0 ? 'text-green-500' : 'text-red-500'" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-bold" :class="kpis.netBalanceForPeriod >= 0 ? 'text-green-600 dark:text-green-400' : 'text-red-600 dark:text-red-400'">{{ formatCurrency(kpis.netBalanceForPeriod) }}</div>
                         <p class="text-xs text-muted-foreground">De {{ formatDate(filters.period_start) }} a {{ formatDate(filters.period_end) }}</p>
                    </CardContent>
                </Card>
                 <Card>
                    <CardHeader class="flex flex-row items-center justify-between space-y-0 pb-2">
                        <CardTitle class="text-sm font-medium">Contas a Receber (Vencidas/Hoje)</CardTitle>
                        <AlertTriangle class="h-5 w-5 text-orange-500" />
                    </CardHeader>
                    <CardContent>
                        <div class="text-3xl font-bold text-orange-600 dark:text-orange-400">{{ formatCurrency(kpis.totalPendingToReceive) }}</div>
                        <Link :href="routeHelper('financial-transactions.index') + '?status_filter=pending'" class="text-xs text-indigo-600 hover:underline">Ver todas as pendentes</Link>
                    </CardContent>
                </Card>
            </div>

            <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Últimos Recebimentos</CardTitle>
                         <Button size="sm" variant="outline" @click="router.visit(routeHelper('financial-transactions.index') + '?status_filter=paid')">Ver Todos</Button>
                    </CardHeader>
                    <CardContent>
                        <Table v-if="recentReceipts.length > 0">
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Caso</TableHead>
                                    <TableHead>Data Pgto.</TableHead>
                                    <TableHead class="text-right">Valor</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="receipt in recentReceipts" :key="receipt.id">
                                    <TableCell>
                                        <Link v-if="receipt.process" :href="routeHelper('processes.show', receipt.process.id)" class="hover:underline">{{ receipt.process.title }}</Link>
                                        <span v-else>{{ receipt.notes?.substring(0,30) }}...</span>
                                    </TableCell>
                                    <TableCell>{{ formatDate(receipt.down_payment_date) }}</TableCell>
                                    <TableCell class="text-right">{{ formatCurrency(receipt.total_amount) }}</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                        <p v-else class="text-sm text-gray-500 dark:text-gray-400">Nenhum recebimento recente no período.</p>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="flex flex-row items-center justify-between">
                        <CardTitle>Últimas Despesas</CardTitle>
                        <div class="space-x-2">
                            <Button size="sm" variant="outline" @click="router.visit(routeHelper('expenses.index'))">Ver Todas</Button>
                            <Button size="sm" @click="router.visit(routeHelper('expenses.create'))">
                                <PlusCircle class="h-4 w-4 mr-2"/> Nova Despesa
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent>
                         <Table v-if="recentExpenses.length > 0">
                            <TableHeader>
                                <TableRow>
                                    <TableHead>Descrição</TableHead>
                                    <TableHead>Data</TableHead>
                                    <TableHead class="text-right">Valor</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                <TableRow v-for="expense in recentExpenses" :key="expense.id">
                                    <TableCell class="truncate max-w-xs" :title="expense.description">{{ expense.description }}</TableCell>
                                    <TableCell>{{ formatDate(expense.expense_date) }}</TableCell>
                                    <TableCell class="text-right">{{ formatCurrency(expense.amount) }}</TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                        <p v-else class="text-sm text-gray-500 dark:text-gray-400">Nenhuma despesa recente no período.</p>
                    </CardContent>
                </Card>
            </div>
            
            <Card>
                <CardHeader>
                    <CardTitle>Cobranças Vencendo (Próximos 30 dias)</CardTitle>
                    <CardDescription>Pagamentos e honorários pendentes com vencimento em breve.</CardDescription>
                </CardHeader>
                <CardContent>
                    <Table v-if="upcomingReceivables.length > 0">
                        <TableHeader>
                            <TableRow>
                                <TableHead>Caso</TableHead>
                                <TableHead>Contato</TableHead>
                                <TableHead>Descrição/Notas</TableHead>
                                <TableHead>Vencimento</TableHead>
                                <TableHead class="text-right">Valor Pendente</TableHead>
                                 <TableHead>Status</TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody>
                            <TableRow v-for="receivable in upcomingReceivables" :key="receivable.id">
                                <TableCell>
                                    <Link v-if="receivable.process" :href="routeHelper('processes.show', receivable.process.id)" class="text-indigo-600 hover:underline">
                                        {{ receivable.process.title }}
                                    </Link>
                                </TableCell>
                                 <TableCell>
                                    <Link v-if="receivable.process?.contact" :href="routeHelper('contacts.show', receivable.process.contact.id)" class="text-indigo-600 hover:underline">
                                        {{ receivable.process.contact.name || receivable.process.contact.business_name }}
                                    </Link>
                                </TableCell>
                                <TableCell class="truncate max-w-xs" :title="receivable.notes || ''">{{ receivable.notes }}</TableCell>
                                <TableCell>{{ formatDate(receivable.first_installment_due_date) }}</TableCell>
                                <TableCell class="text-right">{{ formatCurrency(receivable.total_amount) }}</TableCell>
                                 <TableCell>
                                    <Badge :variant="'outline'" :class="getStatusBadgeClass(receivable.status)">
                                        {{ receivable.status_label || getPaymentStatusLabel(receivable.status) }}
                                    </Badge>
                                </TableCell>
                            </TableRow>
                        </TableBody>
                    </Table>
                    <p v-else class="text-sm text-gray-500 dark:text-gray-400">Nenhuma cobrança vencendo nos próximos 30 dias.</p>
                </CardContent>
            </Card>

            <div class="mt-6 flex justify-center space-x-4">
                <Link :href="routeHelper('financial-transactions.index')">
                    <Button variant="secondary">Ver Todas Transações (Receitas)</Button>
                </Link>
                <Link :href="routeHelper('expenses.index')">
                    <Button variant="secondary">Ver Todas Despesas</Button>
                </Link>
            </div>

        </div>
    </AppLayout>
</template>
