<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { Head, Link, router, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue, SelectGroup } from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { Card, CardHeader, CardTitle, CardDescription, CardContent, CardFooter } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea'; // Assuming you have a Textarea component
// import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert'; // Commented out


import { Save, ArrowLeft, ListOrdered, Info } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';

// Helper function for Ziggy routes
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

interface FinancialTransaction {
    id: string | number;
    process_id: string | null;
    total_amount: number | string | null; // For an installment, this is the value_of_installment
    interest_amount?: number | string | null;
    payment_type: string | null;
    transaction_nature?: 'income' | 'expense';
    transaction_group_id?: string | null; // ID do grupo de parcelas
    payment_method: string | null;
    down_payment_date: string | null;
    first_installment_due_date: string | null; // Data de vencimento desta parcela
    status: string | null;
    status_label?: string;
    notes: string | null;
    created_at: string;
    value_of_installment: number | string | null; // Valor original da parcela (se aplicável)
    number_of_installments: number | null; // Número total de parcelas no grupo
    down_payment_amount: number | string | null;
    process: { id: string; title: string; contact?: { id: string; name?: string; business_name?: string; }} | null;
    supplier_contact?: { id: string; name?: string; business_name?: string; } | null;
}

interface SelectOption {
    value: string;
    label: string;
}

interface PaymentStatusOption {
    key: string;
    label: string;
}

const props = defineProps<{
    transaction: FinancialTransaction;
    groupedInstallments?: FinancialTransaction[] | null; // Outras parcelas do mesmo grupo
    paymentTypes: Array<{ value: string; label: string; nature?: 'income' | 'expense' }>;
    paymentStatuses: PaymentStatusOption[];
    transactionNatures: SelectOption[];
    errors?: Record<string, string>;
}>();

const page = usePage();

// Helper function to format date string for <input type="date">
const formatDateForInput = (dateString: string | null | undefined): string => {
    if (!dateString || typeof dateString !== 'string') {
        return ''; // Return empty if no date or not a string
    }
    try {
        // Attempt to parse the date. If it's just YYYY-MM-DD, append time to ensure UTC.
        // If it already has T or Z, use it as is.
        const dateToParse = (dateString.includes('T') || dateString.includes('Z'))
            ? dateString
            : `${dateString}T00:00:00Z`; // Treat as UTC midnight if only date part

        const dateObj = new Date(dateToParse);
        
        // Check if the constructed date is valid
        if (isNaN(dateObj.getTime())) {
            console.warn('Invalid date value received for input formatting:', dateString);
            return ''; // Return empty for invalid dates
        }
        return dateObj.toISOString().split('T')[0]; // Format as YYYY-MM-DD
    } catch (e) {
        console.error('Error formatting date for input:', dateString, e);
        return ''; // Fallback in case of an unexpected error
    }
};

const form = useForm({
    _method: 'PUT',
    notes: props.transaction.notes || '',
    total_amount: props.transaction.total_amount !== null ? Number(props.transaction.total_amount) : null,
    payment_type: props.transaction.payment_type,
    first_installment_due_date: formatDateForInput(props.transaction.first_installment_due_date),
    transaction_nature: props.transaction.transaction_nature,
    status: props.transaction.status,
    process_id: props.transaction.process_id,
    supplier_contact_id: props.transaction.supplier_contact_id,
    number_of_installments: props.transaction.number_of_installments,
    down_payment_amount: props.transaction.down_payment_amount !== null ? Number(props.transaction.down_payment_amount) : null,
    payment_method: props.transaction.payment_method,
    interest_amount: props.transaction.interest_amount !== null ? Number(props.transaction.interest_amount) : null,
    transaction_group_id: props.transaction.transaction_group_id,
});

const isInstallment = computed(() => {
    const installmentTypeValueFromProps = props.paymentTypes.find(pt => pt.label.toLowerCase().includes('parcelado'))?.value;
    return props.transaction.payment_type === installmentTypeValueFromProps && installmentTypeValueFromProps !== undefined;
});

const overallTotalAmountForGroup = computed(() => {
    if (isInstallment.value && props.transaction.number_of_installments && props.transaction.value_of_installment) {
        return (Number(props.transaction.value_of_installment) * props.transaction.number_of_installments);
    }
    return Number(props.transaction.total_amount);
});


function submitForm() {
    form.put(routeHelper('financial-transactions.update', props.transaction.id), {
        preserveScroll: true,
        onSuccess: () => {
            // router.visit(routeHelper('financial-transactions.index'));
        },
        onError: (formErrors) => {
            console.error("Erros ao atualizar:", formErrors);
        }
    });
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Painel', href: routeHelper('dashboard') },
    { title: 'Financeiro', href: routeHelper('financial-transactions.index') },
    { title: `Editar Transação #${props.transaction.id.toString().substring(0, 8)}...` },
];

const formatDate = (dateString: string | null | undefined): string => {
    if (!dateString) return 'N/A';
    try {
        const date = new Date(dateString.includes('T') || dateString.includes('Z') ? dateString : dateString + 'T00:00:00Z');
        return date.toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC' });
    } catch (e) {
        return dateString || 'N/A';
    }
};

const formatCurrency = (value: number | string | null | undefined): string => {
    const numValue = Number(value);
    if (value === null || typeof value === 'undefined' || isNaN(numValue)) return 'R$ 0,00';
    return numValue.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
};

const originalInstallmentValue = computed(() => {
    if (isInstallment.value && props.transaction.value_of_installment) {
        return Number(props.transaction.value_of_installment);
    }
    return null;
});

const currentInstallmentNumber = computed(() => {
    if (isInstallment.value && props.groupedInstallments && props.groupedInstallments.length > 0) {
        const sortedInstallments = [...props.groupedInstallments].sort((a, b) => 
            new Date(a.first_installment_due_date + 'T00:00:00Z').getTime() - new Date(b.first_installment_due_date + 'T00:00:00Z').getTime()
        );
        const currentIndex = sortedInstallments.findIndex(inst => inst.id === props.transaction.id);
        return currentIndex !== -1 ? currentIndex + 1 : null;
    }
    return null;
});

</script>

<template>
    <Head :title="`Editar Transação #${transaction.id.toString().substring(0,8)}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-4 sm:p-6 lg:p-8">
            <div class="flex justify-between items-center mb-6">
                <h1 class="text-2xl font-bold text-gray-800 dark:text-gray-100">
                    Editar Transação Financeira
                    <span v-if="transaction.transaction_nature" class="text-lg font-normal text-muted-foreground">
                        ({{ transaction.transaction_nature === 'income' ? 'Receita' : 'Despesa' }})
                    </span>
                </h1>
                <Link :href="routeHelper('financial-transactions.index')">
                    <Button variant="outline">
                        <ArrowLeft class="h-4 w-4 mr-2" />
                        Voltar para Financeiro
                    </Button>
                </Link>
            </div>

            <Card class="shadow-lg">
                <CardHeader>
                    <CardTitle>Detalhes da Transação</CardTitle>
                    <CardDescription v-if="isInstallment && currentInstallmentNumber && transaction.number_of_installments">
                        Editando Parcela {{ currentInstallmentNumber }} de {{ transaction.number_of_installments }}
                        (Valor Total do Grupo: {{ formatCurrency(overallTotalAmountForGroup) }})
                    </CardDescription>
                     <CardDescription v-else-if="isInstallment">
                        Editando Parcela (Grupo de {{ transaction.number_of_installments || 'N/A' }} parcelas)
                    </CardDescription>
                    <CardDescription v-else>
                        Editando transação de pagamento único.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submitForm" class="space-y-6">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <Label for="notes">Descrição / Notas</Label>
                                <Textarea id="notes" v-model="form.notes" class="mt-1" placeholder="Detalhes da transação..." rows="3" />
                                <InputError :message="form.errors.notes" class="mt-1" />
                            </div>

                            <div>
                                <Label for="total_amount">
                                    Valor {{ isInstallment ? 'desta Parcela' : 'Total' }} (R$)
                                </Label>
                                <Input id="total_amount" v-model="form.total_amount" type="number" step="0.01" class="mt-1" placeholder="Ex: 150.75" />
                                <small v-if="isInstallment && originalInstallmentValue !== null && originalInstallmentValue !== form.total_amount" class="text-xs text-muted-foreground">
                                    Valor original da parcela: {{ formatCurrency(originalInstallmentValue) }}
                                </small>
                                <InputError :message="form.errors.total_amount" class="mt-1" />
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div>
                                <Label for="payment_type">Tipo de Pagamento</Label>
                                <Select v-model="form.payment_type" :disabled="isInstallment">
                                    <SelectTrigger id="payment_type" class="mt-1">
                                        <SelectValue placeholder="Selecione o tipo" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem v-for="type in props.paymentTypes" :key="`edit-type-${type.value}`" :value="type.value">
                                                {{ type.label }}
                                            </SelectItem>
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                                 <small v-if="isInstallment" class="text-xs text-muted-foreground">
                                    Tipo não pode ser alterado para parcelas existentes.
                                </small>
                                <InputError :message="form.errors.payment_type" class="mt-1" />
                            </div>

                            <div>
                                <Label for="first_installment_due_date">Data de Vencimento {{ isInstallment ? 'desta Parcela' : '' }}</Label>
                                <Input id="first_installment_due_date" v-model="form.first_installment_due_date" type="date" class="mt-1" />
                                <InputError :message="form.errors.first_installment_due_date" class="mt-1" />
                            </div>
                            
                            <div>
                                <Label for="status">Status</Label>
                                <Select v-model="form.status">
                                    <SelectTrigger id="status" class="mt-1">
                                        <SelectValue placeholder="Selecione o status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem v-for="statusOpt in props.paymentStatuses" :key="`edit-status-${statusOpt.key}`" :value="statusOpt.key">
                                                {{ statusOpt.label }}
                                            </SelectItem>
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                                <InputError :message="form.errors.status" class="mt-1" />
                            </div>

                             <div>
                                <Label for="transaction_nature">Natureza da Transação</Label>
                                <Select v-model="form.transaction_nature" :disabled="isInstallment">
                                    <SelectTrigger id="transaction_nature" class="mt-1">
                                        <SelectValue placeholder="Selecione a natureza" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectItem v-for="nature in props.transactionNatures" :key="`edit-nature-${nature.value}`" :value="nature.value">
                                                {{ nature.label }}
                                            </SelectItem>
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                                <small v-if="isInstallment" class="text-xs text-muted-foreground">
                                    Natureza não pode ser alterada para parcelas existentes.
                                </small>
                                <InputError :message="form.errors.transaction_nature" class="mt-1" />
                            </div>

                            <div v-if="isInstallment">
                                <Label for="number_of_installments">Nº Total de Parcelas (Grupo)</Label>
                                <Input id="number_of_installments" :value="form.number_of_installments" type="number" class="mt-1 bg-gray-100 dark:bg-gray-700" readonly placeholder="N/A" />
                                <InputError :message="form.errors.number_of_installments" class="mt-1" />
                            </div>
                        </div>
                        
                        <Separator class="my-4"/>
                        <h3 class="text-md font-semibold text-gray-700 dark:text-gray-300">Informações Adicionais (Opcional)</h3>
                        <div class="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-6">
                            <div>
                                <Label for="payment_method">Método de Pagamento</Label>
                                <Input id="payment_method" v-model="form.payment_method" class="mt-1" placeholder="Ex: Cartão de Crédito, Boleto" />
                                <InputError :message="form.errors.payment_method" class="mt-1" />
                            </div>
                            <div>
                                <Label for="interest_amount">Juros (R$)</Label>
                                <Input id="interest_amount" v-model="form.interest_amount" type="number" step="0.01" class="mt-1" placeholder="Ex: 5.00" />
                                <InputError :message="form.errors.interest_amount" class="mt-1" />
                            </div>
                            <div>
                                <Label for="down_payment_amount">Valor da Entrada (R$)</Label>
                                <Input id="down_payment_amount" v-model="form.down_payment_amount" type="number" step="0.01" class="mt-1" placeholder="Ex: 100.00" :disabled="isInstallment && currentInstallmentNumber !== 1" />
                                 <small v-if="isInstallment && currentInstallmentNumber !== 1" class="text-xs text-muted-foreground">
                                    Entrada aplicável apenas à primeira parcela.
                                </small>
                                <InputError :message="form.errors.down_payment_amount" class="mt-1" />
                            </div>
                        </div>
                        <div v-if="transaction.process || transaction.supplier_contact" class="mt-4 space-y-2">
                             <Separator/>
                             <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Vinculado a:</p>
                            <p v-if="transaction.process" class="text-sm text-muted-foreground">
                                Caso/Origem: <Link :href="routeHelper('processes.show', transaction.process.id)" class="text-indigo-600 hover:underline">{{ transaction.process.title }}</Link>
                            </p>
                            <p v-if="transaction.supplier_contact" class="text-sm text-muted-foreground">
                                Fornecedor: <Link :href="routeHelper('contacts.show', transaction.supplier_contact.id)" class="text-indigo-600 hover:underline">{{ transaction.supplier_contact.name || transaction.supplier_contact.business_name }}</Link>
                            </p>
                        </div>


                        <CardFooter class="px-0 pt-8 flex justify-end">
                            <Button type="submit" :disabled="form.processing">
                                <Save class="h-4 w-4 mr-2" />
                                {{ form.processing ? 'Salvando Alterações...' : 'Salvar Alterações' }}
                            </Button>
                        </CardFooter>
                    </form>
                </CardContent>
            </Card>

            <div v-if="isInstallment && groupedInstallments && groupedInstallments.length > 1" class="mt-8">
                <Card class="shadow-md">
                    <CardHeader>
                        <CardTitle class="flex items-center">
                            <ListOrdered class="h-5 w-5 mr-2 text-blue-600 dark:text-blue-400" />
                            Outras Parcelas deste Grupo
                        </CardTitle>
                        <CardDescription>
                            Esta transação faz parte de um parcelamento. Abaixo estão as outras parcelas do mesmo grupo.
                        </CardDescription>
                    </CardHeader>
                    <CardContent>
                        <div class="border rounded-md overflow-x-auto">
                            <table class="min-w-full divide-y divide-gray-200 dark:divide-gray-700">
                                <thead class="bg-gray-50 dark:bg-gray-700/50">
                                    <tr>
                                        <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Parcela</th>
                                        <th scope="col" class="px-4 py-2 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Vencimento</th>
                                        <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Valor</th>
                                        <th scope="col" class="px-4 py-2 text-center text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Status</th>
                                        <th scope="col" class="px-4 py-2 text-right text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider">Ações</th>
                                    </tr>
                                </thead>
                                <tbody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                                    <tr v-for="(installment, index) in groupedInstallments.sort((a,b) => new Date(a.first_installment_due_date + 'T00:00:00Z').getTime() - new Date(b.first_installment_due_date + 'T00:00:00Z').getTime())" 
                                        :key="`group-inst-${installment.id}`"
                                        :class="{'bg-blue-50 dark:bg-blue-900/30': installment.id === transaction.id}">
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">{{ index + 1 }} / {{ transaction.number_of_installments }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">{{ formatDate(installment.first_installment_due_date) }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-right font-medium" :class="installment.transaction_nature === 'expense' ? 'text-red-600 dark:text-red-400' : 'text-green-600 dark:text-green-400'">{{ formatCurrency(installment.total_amount) }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-center text-gray-700 dark:text-gray-300">{{ installment.status_label || installment.status }}</td>
                                        <td class="px-4 py-2 whitespace-nowrap text-sm text-right">
                                            <Link :href="routeHelper('financial-transactions.edit', installment.id)" v-if="installment.id !== transaction.id">
                                                <Button variant="outline" size="xs">Editar esta</Button>
                                            </Link>
                                             <span v-else class="text-xs text-muted-foreground italic">Editando</span>
                                        </td>
                                    </tr>
                                </tbody>
                            </table>
                        </div>
                    </CardContent>
                </Card>
            </div>
            <!-- 
             <Alert v-else-if="isInstallment" variant="info" class="mt-6">
                <Info class="h-4 w-4" />
                <AlertTitle>Parcela Única</AlertTitle>
                <AlertDescription>
                   Esta é a única parcela registrada para este grupo de transação ou as outras parcelas não puderam ser carregadas.
                </AlertDescription>
            </Alert>
            -->

        </div>
    </AppLayout>
</template>

<style scoped>
/* Adicione estilos personalizados se necessário */
</style>
