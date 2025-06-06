<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue'; // Alterado para AppLayout
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription, CardFooter } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
    SelectGroup, // Adicionado para consistência
} from '@/components/ui/select';
// import InputError from '@/Components/InputError.vue'; // Mantido comentado
import { type User, type Contact, type SharedData, type ProcessPaymentData } from '@/types'; // Importando ProcessPaymentData

// Tipos
interface BreadcrumbItem {
  title: string;
  href: string;
}

interface UserSelectItem {
    id: number | string;
    name: string;
}

interface WorkflowOption {
    key: string;
    label: string;
}

interface StageOption {
    key: number;
    label: string;
}

interface SelectOption {
    key: string;
    label: string;
}

interface ContactSelectItem {
    id: number | string;
    name: string;
    business_name?: string;
    type: 'physical' | 'legal';
}

// Interface para o objeto Processo recebido como prop
interface ProcessDataForEdit { // Renomeado para evitar conflito com o tipo Process global
    id: string;
    title: string;
    description: string | null;
    contact_id: string | number;
    responsible_id: string | number | null;
    workflow: string;
    stage: number;
    due_date: string | null;
    priority: 'low' | 'medium' | 'high';
    status: string | null;
    origin: string | null;
    // negotiated_value: number | null; // Removido, pois agora está em payment
    contact?: { // Simplificado, o controller já deve passar o nome
        id: string | number;
        name: string;
        business_name?: string;
        type?: 'physical' | 'legal';
    };
    responsible?: {
        id: string | number;
        name: string;
    };
    // payments: ProcessPaymentData[]; // A prop currentPayment será usada para o primeiro pagamento
}


// Interface para os dados do formulário, incluindo a seção de pagamento
interface PaymentFormDataForEdit {
    total_amount: number | string | null;
    advance_payment_amount: number | string | null;
    payment_type: 'a_vista' | 'parcelado' | string | null;
    payment_method: string | null;
    single_payment_date: string | null;
    number_of_installments: number | null;
    first_installment_due_date: string | null;
    notes: string | null;
}

interface ProcessEditFormData {
    title: string;
    description: string | null;
    contact_id: string | number | null;
    responsible_id: string | number | null;
    workflow: string | null;
    stage: number | null;
    due_date: string | null;
    priority: string;
    origin: string | null;
    status: string | null;
    payment: PaymentFormDataForEdit;
}


const props = defineProps<{
    auth: SharedData['auth']; // Adicionado para consistência e uso potencial
    process: ProcessDataForEdit;
    users?: UserSelectItem[];
    contactsList?: ContactSelectItem[];
    availableWorkflows?: WorkflowOption[];
    allStages?: Record<string, StageOption[]>;
    statusesForForm?: SelectOption[];
    prioritiesForForm?: SelectOption[];
    paymentTypes: Array<{ value: string; label: string }>; // Adicionado
    paymentMethods: string[]; // Adicionado
    currentPaymentData: ProcessPaymentData | null; // Renomeado de currentPayment para evitar conflito com payment no form
    errors?: Record<string, string>;
}>();

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

const form = useForm<ProcessEditFormData>({
    title: props.process.title || '',
    description: props.process.description || '',
    contact_id: String(props.process.contact_id),
    responsible_id: props.process.responsible_id ? String(props.process.responsible_id) : null,
    workflow: props.process.workflow,
    stage: props.process.stage,
    due_date: props.process.due_date ? props.process.due_date.substring(0,10) : '',
    priority: props.process.priority || 'medium',
    status: props.process.status || 'Aberto',
    origin: props.process.origin || '',
    payment: {
        total_amount: props.currentPaymentData?.total_amount || null,
        advance_payment_amount: props.currentPaymentData?.advance_payment_amount || null,
        payment_type: props.currentPaymentData?.payment_type || (props.paymentTypes.length > 0 ? props.paymentTypes[0].value : 'a_vista'),
        payment_method: props.currentPaymentData?.payment_method || null,
        single_payment_date: props.currentPaymentData?.single_payment_date || null,
        number_of_installments: props.currentPaymentData?.number_of_installments || null,
        first_installment_due_date: props.currentPaymentData?.first_installment_due_date || null,
        notes: props.currentPaymentData?.notes || null,
    },
});

const pageTitle = computed(() => `Editar Caso: ${props.process.title}`);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Casos', href: routeHelper('processes.index') },
    { title: props.process.title, href: routeHelper('processes.show', props.process.id) },
    { title: 'Editar', href: '#' },
]);

function getContactDisplayForSelect(contact: ContactSelectItem): string {
    if (contact.type === 'physical') {
        return contact.name || 'N/A';
    }
    let displayName = contact.name || 'Empresa sem Nome Fantasia';
    if (contact.business_name && contact.business_name !== contact.name) {
        displayName += ` (${contact.business_name})`;
    }
    return `${displayName} (PJ)`;
}

const usersToSelect = computed(() => props.users || []);
const statusesToSelect = computed(() => props.statusesForForm || []);
const prioritiesToSelect = computed(() => props.prioritiesForForm || []);

const currentStages = computed<StageOption[]>(() => {
    if (form.workflow && props.allStages && props.allStages[form.workflow]) {
        return props.allStages[form.workflow];
    }
    return [];
});

watch(() => form.workflow, (newWorkflowSelected, oldWorkflowSelected) => {
    if (newWorkflowSelected !== oldWorkflowSelected) {
        if (oldWorkflowSelected !== null) {
             form.stage = null;
        }
        form.clearErrors('stage');
         if (newWorkflowSelected && currentStages.value.length > 0 && form.stage === null) { // Só define se stage for null
           form.stage = currentStages.value[0].key;
        }
    }
});

onMounted(() => {
    if (form.workflow && props.process.stage) {
        const stagesForCurrentWorkflow = props.allStages && props.allStages[form.workflow] 
            ? props.allStages[form.workflow] 
            : [];
        if (!stagesForCurrentWorkflow.some(s => s.key === form.stage)) {
            form.stage = null;
        }
    }
});

const amount_to_be_paid_or_installed = computed(() => {
    const total = parseFloat(String(form.payment.total_amount)) || 0;
    const advance = parseFloat(String(form.payment.advance_payment_amount)) || 0;
    const remaining = total - advance;
    return remaining > 0 ? remaining : 0;
});

const installmentAmount = computed(() => {
    if (form.payment.payment_type === 'parcelado' && amount_to_be_paid_or_installed.value > 0 && form.payment.number_of_installments && form.payment.number_of_installments > 0) {
        const installments = parseInt(String(form.payment.number_of_installments));
        if (!isNaN(installments) && installments > 0) {
            return (amount_to_be_paid_or_installed.value / installments).toFixed(2);
        }
    }
    return null;
});


function submitProcess() {
    const dataToSubmit: Record<string, any> = { ...form.data() };

    if (dataToSubmit.contact_id !== null && dataToSubmit.contact_id !== undefined) {
        dataToSubmit.contact_id = String(dataToSubmit.contact_id);
    }
    if (dataToSubmit.responsible_id !== null && dataToSubmit.responsible_id !== undefined) {
        dataToSubmit.responsible_id = String(dataToSubmit.responsible_id);
    }
    if (dataToSubmit.status === 'null') dataToSubmit.status = null;
    if (dataToSubmit.priority === 'null') dataToSubmit.priority = null;


    if (!form.payment.advance_payment_amount || parseFloat(String(form.payment.advance_payment_amount)) === 0) {
        dataToSubmit.payment.advance_payment_amount = null;
    }

    if (form.payment.payment_type === 'a_vista') {
        dataToSubmit.payment.number_of_installments = null;
        dataToSubmit.payment.first_installment_due_date = null;
    } else if (form.payment.payment_type === 'parcelado') {
        dataToSubmit.payment.single_payment_date = null;
    }
     if (!form.payment.total_amount || parseFloat(String(form.payment.total_amount)) === 0) {
        // Se o valor total for 0 ou nulo, talvez limpar todos os dados de pagamento ou enviar nulos
        dataToSubmit.payment = {
            total_amount: null,
            advance_payment_amount: null,
            payment_type: null,
            payment_method: null,
            single_payment_date: null,
            number_of_installments: null,
            first_installment_due_date: null,
            notes: form.payment.notes, // Manter notas se houver
        };
    }


    form.transform(() => dataToSubmit)
        .put(routeHelper('processes.update', props.process.id), {
        onSuccess: () => {
            // O backend deve redirecionar.
        },
        onError: (formErrors) => {
            console.error('Erro ao atualizar caso:', formErrors);
        }
    });
}

</script>

<template>
  <Head :title="pageTitle" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="container mx-auto p-4 sm:p-6 lg:p-8">
      <Card class="max-w-3xl mx-auto dark:bg-gray-800">
        <CardHeader>
          <CardTitle class="text-2xl">{{ pageTitle }}</CardTitle>
          <CardDescription>
            Modifique os detalhes do caso abaixo.
          </CardDescription>
        </CardHeader>
        <CardContent>
          <form @submit.prevent="submitProcess" class="space-y-6">

            <div>
              <Label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Título do Caso <span class="text-red-500">*</span></Label>
              <Input
                id="title"
                type="text"
                v-model="form.title"
                class="mt-1 block w-full"
                required
                autocomplete="off"
              />
              <div v-if="form.errors.title || props.errors?.title" class="text-sm text-red-600 dark:text-red-400 mt-1">
                {{ form.errors.title || props.errors?.title }}
              </div>
            </div>

            <div>
              <Label for="contact_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Contato Principal <span class="text-red-500">*</span></Label>
               <Select v-model="form.contact_id" required>
                    <SelectTrigger id="contact_id" class="mt-1 w-full">
                        <SelectValue placeholder="Selecione um contato" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectItem v-if="!props.contactsList || props.contactsList.length === 0" value="null" disabled>
                                Nenhum contato disponível
                            </SelectItem>
                            <template v-else>
                                <SelectItem v-for="contact in props.contactsList" :key="contact.id" :value="String(contact.id)">
                                    {{ getContactDisplayForSelect(contact) }}
                                </SelectItem>
                            </template>
                        </SelectGroup>
                    </SelectContent>
                </Select>
              <div v-if="form.errors.contact_id || props.errors?.contact_id" class="text-sm text-red-600 dark:text-red-400 mt-1">
                {{ form.errors.contact_id || props.errors?.contact_id }}
              </div>
            </div>

            <div>
              <Label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Descrição</Label>
              <Textarea
                id="description"
                v-model="form.description"
                rows="4"
                class="mt-1 block w-full"
                placeholder="Detalhes sobre o caso, histórico, próximos passos..."
              />
              <div v-if="form.errors.description || props.errors?.description" class="text-sm text-red-600 dark:text-red-400 mt-1">
                {{ form.errors.description || props.errors?.description }}
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <Label for="workflow" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Workflow <span class="text-red-500">*</span></Label>
                <Select v-model="form.workflow" required>
                    <SelectTrigger id="workflow" class="mt-1 w-full">
                        <SelectValue placeholder="Selecione um workflow" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectItem v-if="!props.availableWorkflows || props.availableWorkflows.length === 0" value="null" disabled>
                                Nenhum workflow disponível
                            </SelectItem>
                            <template v-else>
                                <SelectItem v-for="wf in props.availableWorkflows" :key="wf.key" :value="wf.key">
                                    {{ wf.label }}
                                </SelectItem>
                            </template>
                        </SelectGroup>
                    </SelectContent>
                </Select>
                <div v-if="form.errors.workflow || props.errors?.workflow" class="text-sm text-red-600 dark:text-red-400 mt-1">
                  {{ form.errors.workflow || props.errors?.workflow }}
                </div>
              </div>

              <div>
                <Label for="stage" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Estágio <span class="text-red-500">*</span></Label>
                <Select v-model="form.stage" :disabled="!form.workflow || currentStages.length === 0" required>
                    <SelectTrigger id="stage" class="mt-1 w-full">
                        <SelectValue placeholder="Selecione um estágio" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                             <SelectItem v-if="!form.workflow" value="null" disabled>
                                Selecione um workflow primeiro
                            </SelectItem>
                            <SelectItem v-else-if="currentStages.length === 0" value="null" disabled>
                                Nenhum estágio para este workflow
                            </SelectItem>
                            <template v-else>
                                <SelectItem v-for="st in currentStages" :key="st.key" :value="st.key"> {{ st.label }}
                                </SelectItem>
                            </template>
                        </SelectGroup>
                    </SelectContent>
                </Select>
                <div v-if="form.errors.stage || props.errors?.stage" class="text-sm text-red-600 dark:text-red-400 mt-1">
                  {{ form.errors.stage || props.errors?.stage }}
                </div>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                 <div>
                    <Label for="responsible_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Responsável</Label>
                    <Select v-model="form.responsible_id">
                        <SelectTrigger id="responsible_id" class="mt-1 w-full">
                            <SelectValue placeholder="Selecione um responsável" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem value="null">Ninguém (Não atribuído)</SelectItem>
                                <SelectItem v-if="!usersToSelect || usersToSelect.length === 0" value="null" disabled> {/* Removido !props.users */}
                                    Nenhum usuário disponível
                                </SelectItem>
                                <template v-else>
                                    <SelectItem v-for="user in usersToSelect" :key="user.id" :value="String(user.id)">
                                        {{ user.name }}
                                    </SelectItem>
                                </template>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <div v-if="form.errors.responsible_id || props.errors?.responsible_id" class="text-sm text-red-600 dark:text-red-400 mt-1">
                      {{ form.errors.responsible_id || props.errors?.responsible_id }}
                    </div>
                </div>
                <div>
                    <Label for="origin" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Origem do Caso</Label>
                    <Input
                        id="origin"
                        type="text"
                        v-model="form.origin"
                        class="mt-1 block w-full"
                        placeholder="Ex: Indicação, Website, Telefone"
                        autocomplete="off"
                    />
                    <div v-if="form.errors.origin || props.errors?.origin" class="text-sm text-red-600 dark:text-red-400 mt-1">
                        {{ form.errors.origin || props.errors?.origin }}
                    </div>
                </div>
            </div>


            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <Label for="priority" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Prioridade <span class="text-red-500">*</span></Label>
                    <Select v-model="form.priority" required>
                        <SelectTrigger id="priority" class="mt-1 w-full">
                            <SelectValue placeholder="Selecione a prioridade" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectItem v-if="!prioritiesToSelect || prioritiesToSelect.length === 0" value="null" disabled>
                                    Nenhuma prioridade disponível
                                </SelectItem>
                                <template v-else>
                                    <SelectItem v-for="prio in prioritiesToSelect" :key="prio.key" :value="prio.key">
                                        {{ prio.label }}
                                    </SelectItem>
                                </template>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <div v-if="form.errors.priority || props.errors?.priority" class="text-sm text-red-600 dark:text-red-400 mt-1">
                        {{ form.errors.priority || props.errors?.priority }}
                    </div>
                </div>
                <div>
                    <Label for="due_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Data de Vencimento</Label>
                    <Input
                        id="due_date"
                        type="date"
                        v-model="form.due_date"
                        class="mt-1 block w-full"
                    />
                    <div v-if="form.errors.due_date || props.errors?.due_date" class="text-sm text-red-600 dark:text-red-400 mt-1">
                        {{ form.errors.due_date || props.errors?.due_date }}
                    </div>
                </div>
            </div>
             <div>
                <Label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status</Label>
                <Select v-model="form.status">
                    <SelectTrigger id="status" class="mt-1 w-full">
                        <SelectValue placeholder="Selecione o status" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectItem v-if="!statusesToSelect || statusesToSelect.length === 0" value="null" disabled>
                                Nenhum status disponível
                            </SelectItem>
                            <template v-else>
                                <SelectItem v-for="st in statusesToSelect" :key="st.key" :value="st.key">
                                    {{ st.label }}
                                </SelectItem>
                            </template>
                        </SelectGroup>
                    </SelectContent>
                </Select>
                <div v-if="form.errors.status || props.errors?.status" class="text-sm text-red-600 dark:text-red-400 mt-1">
                    {{ form.errors.status || props.errors?.status }}
                </div>
            </div>

            <div class="pt-6 mt-6 border-t border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100 mb-6">Detalhes Financeiros</h3>
                <div class="space-y-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-6">
                        <div>
                            <Label for="payment_total_amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Valor Total do Contrato/Serviço (R$)</Label>
                            <Input
                                id="payment_total_amount"
                                type="number"
                                step="0.01"
                                v-model="form.payment.total_amount"
                                class="mt-1 block w-full"
                                placeholder="Ex: 3000.00"
                            />
                            <div v-if="form.errors['payment.total_amount'] || props.errors?.['payment.total_amount']" class="text-sm text-red-600 dark:text-red-400 mt-1">
                                {{ form.errors['payment.total_amount'] || props.errors?.['payment.total_amount'] }}
                            </div>
                        </div>
                        <div>
                            <Label for="advance_payment_amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Valor da Entrada (R$) (Opcional)</Label>
                            <Input
                                id="advance_payment_amount"
                                type="number"
                                step="0.01"
                                v-model="form.payment.advance_payment_amount"
                                class="mt-1 block w-full"
                                placeholder="Ex: 500.00 (deixe 0 se não houver)"
                            />
                            <div v-if="form.errors['payment.advance_payment_amount'] || props.errors?.['payment.advance_payment_amount']" class="text-sm text-red-600 dark:text-red-400 mt-1">
                                {{ form.errors['payment.advance_payment_amount'] || props.errors?.['payment.advance_payment_amount'] }}
                            </div>
                        </div>
                    </div>
                    
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-6">
                        <div>
                            <Label for="payment_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Forma de Pagamento (Restante)</Label>
                            <Select v-model="form.payment.payment_type">
                                <SelectTrigger id="payment_type" class="mt-1 w-full">
                                    <SelectValue placeholder="Selecione a forma" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem v-if="!props.paymentTypes || props.paymentTypes.length === 0" value="null" disabled>
                                            Nenhuma forma disponível
                                        </SelectItem>
                                        <template v-else>
                                            <SelectItem v-for="type in props.paymentTypes" :key="type.value" :value="type.value">
                                                {{ type.label }}
                                            </SelectItem>
                                        </template>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <div v-if="form.errors['payment.payment_type'] || props.errors?.['payment.payment_type']" class="text-sm text-red-600 dark:text-red-400 mt-1">
                                {{ form.errors['payment.payment_type'] || props.errors?.['payment.payment_type'] }}
                            </div>
                        </div>
                        <div>
                            <Label for="payment_method" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Meio de Pagamento</Label>
                            <Select v-model="form.payment.payment_method">
                                <SelectTrigger id="payment_method" class="mt-1 w-full">
                                    <SelectValue placeholder="Selecione o meio" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectItem v-if="!props.paymentMethods || props.paymentMethods.length === 0" value="null" disabled>
                                            Nenhum meio disponível
                                        </SelectItem>
                                        <template v-else>
                                            <SelectItem v-for="method in props.paymentMethods" :key="method" :value="method">
                                                {{ method }}
                                            </SelectItem>
                                        </template>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <div v-if="form.errors['payment.payment_method'] || props.errors?.['payment.payment_method']" class="text-sm text-red-600 dark:text-red-400 mt-1">
                                {{ form.errors['payment.payment_method'] || props.errors?.['payment.payment_method'] }}
                            </div>
                        </div>
                    </div>

                    <div v-if="form.payment.payment_type === 'a_vista'" class="mt-0">
                        <div>
                            <Label for="single_payment_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Data do Pagamento Restante</Label>
                            <Input
                                id="single_payment_date"
                                type="date"
                                v-model="form.payment.single_payment_date"
                                class="mt-1 block w-full"
                            />
                            <p v-if="amount_to_be_paid_or_installed > 0" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                Valor a ser pago nesta data: R$ {{ amount_to_be_paid_or_installed.toFixed(2) }}
                            </p>
                             <p v-else-if="parseFloat(String(form.payment.advance_payment_amount)) > 0 && amount_to_be_paid_or_installed <= 0 && parseFloat(String(form.payment.total_amount)) > 0" class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                O valor da entrada cobre o total. Nenhum valor restante a pagar.
                            </p>
                            <div v-if="form.errors['payment.single_payment_date'] || props.errors?.['payment.single_payment_date']" class="text-sm text-red-600 dark:text-red-400 mt-1">
                                {{ form.errors['payment.single_payment_date'] || props.errors?.['payment.single_payment_date'] }}
                            </div>
                        </div>
                    </div>

                    <div v-if="form.payment.payment_type === 'parcelado'" class="space-y-6 mt-0">
                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-6">
                            <div>
                                <Label for="number_of_installments" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Número de Parcelas (Restante)</Label>
                                <Input
                                    id="number_of_installments"
                                    type="number"
                                    min="1"
                                    v-model.number="form.payment.number_of_installments"
                                    class="mt-1 block w-full"
                                    placeholder="Ex: 3"
                                />
                                <div v-if="form.errors['payment.number_of_installments'] || props.errors?.['payment.number_of_installments']" class="text-sm text-red-600 dark:text-red-400 mt-1">
                                    {{ form.errors['payment.number_of_installments'] || props.errors?.['payment.number_of_installments'] }}
                                </div>
                            </div>
                            <div>
                                <Label for="installment_amount_display" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Valor da Parcela (R$)</Label>
                                <Input
                                    id="installment_amount_display"
                                    type="text"
                                    :value="installmentAmount ? installmentAmount : (amount_to_be_paid_or_installed > 0 ? 'Calculando...' : 'R$ 0.00')"
                                    class="mt-1 block w-full bg-gray-100 dark:bg-slate-700 cursor-not-allowed" 
                                    readonly
                                />
                            </div>
                        </div>
                        <div>
                            <Label for="first_installment_due_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Data de Venc. da 1ª Parcela</Label>
                            <Input
                                id="first_installment_due_date"
                                type="date"
                                v-model="form.payment.first_installment_due_date"
                                class="mt-1 block w-full"
                            />
                            <div v-if="form.errors['payment.first_installment_due_date'] || props.errors?.['payment.first_installment_due_date']" class="text-sm text-red-600 dark:text-red-400 mt-1">
                                {{ form.errors['payment.first_installment_due_date'] || props.errors?.['payment.first_installment_due_date'] }}
                            </div>
                        </div>
                    </div>

                    <div class="mt-4"> 
                        <Label for="payment_notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Observações Financeiras</Label>
                        <Textarea
                            id="payment_notes"
                            v-model="form.payment.notes"
                            rows="3"
                            class="mt-1 block w-full"
                            placeholder="Detalhes sobre a entrada, datas das parcelas, etc."
                        />
                        <div v-if="form.errors['payment.notes'] || props.errors?.['payment.notes']" class="text-sm text-red-600 dark:text-red-400 mt-1">
                            {{ form.errors['payment.notes'] || props.errors?.['payment.notes'] }}
                        </div>
                    </div>
                </div>
            </div>

            <div class="flex justify-end space-x-3 pt-4">
              <Link :href="routeHelper('processes.show', props.process.id)">
                <Button type="button" variant="outline">Cancelar</Button>
              </Link>
              <Button type="submit" :disabled="form.processing">
                <svg v-if="form.processing" class="animate-spin -ml-1 mr-3 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                </svg>
                {{ form.processing ? 'Atualizando...' : 'Salvar Alterações' }}
              </Button>
            </div>
          </form>
        </CardContent>
      </Card>
    </div>
  </AppLayout>
</template>
