<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
    SelectGroup,
    SelectLabel,
} from '@/components/ui/select';
import { type User, type Contact, type SharedData, type ProcessPaymentData } from '@/types';

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
    name: string | null;
    business_name?: string | null;
    type: 'physical' | 'legal' | string;
}

interface ProcessDataForEdit {
    id: string;
    title: string;
    description: string | null;
    contact_id: string | number;
    responsible_id: string | number | null;
    workflow: string;
    stage: number | null;
    due_date: string | null;
    priority: 'low' | 'medium' | 'high' | string;
    status: string | null;
    origin: string | null;
    contact?: {
        id: string | number;
        name: string;
        business_name?: string;
        type?: 'physical' | 'legal';
    };
    responsible?: {
        id: string | number;
        name: string;
    };
}

interface PaymentFormData {
    charge_consultation: boolean | null;
    consultation_fee_amount: number | string | null;
    total_amount: number | string | null;
    advance_payment_amount: number | string | null;
    payment_type: 'a_vista' | 'parcelado' | string | null;
    payment_method: string | null;
    single_payment_date: string | null;
    number_of_installments: number | null;
    first_installment_due_date: string | null;
    notes: string | null;
    value_of_installment?: number | string | null;
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
    payment: PaymentFormData;
}


const props = defineProps<{
    auth: SharedData['auth'];
    process: ProcessDataForEdit;
    users?: UserSelectItem[];
    contactsList?: ContactSelectItem[];
    availableWorkflows?: WorkflowOption[];
    allStages?: Record<string, StageOption[]>;
    statusesForForm?: SelectOption[];
    prioritiesForForm?: SelectOption[];
    paymentTypes: Array<{ value: string; label: string }>;
    paymentMethods: string[];
    currentPaymentData: (ProcessPaymentData & { value_of_installment?: number | string | null }) | null;
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

const chargeConsultationOptions = ref([
    { value: 'true', label: 'Sim' },
    { value: 'false', label: 'Não' },
]);

const form = useForm<ProcessEditFormData>({
    title: props.process.title || '',
    description: props.process.description,
    contact_id: String(props.process.contact_id),
    responsible_id: props.process.responsible_id,
    workflow: props.process.workflow,
    stage: props.process.stage,
    due_date: props.process.due_date ? props.process.due_date.substring(0, 10) : null,
    priority: props.process.priority || 'medium',
    status: props.process.status,
    origin: props.process.origin,
    payment: {
        charge_consultation: props.currentPaymentData?.charge_consultation ?? false,
        consultation_fee_amount: props.currentPaymentData?.consultation_fee_amount,
        total_amount: props.currentPaymentData?.total_amount,
        advance_payment_amount: props.currentPaymentData?.advance_payment_amount,
        payment_type: props.currentPaymentData?.payment_type,
        payment_method: props.currentPaymentData?.payment_method,
        single_payment_date: props.currentPaymentData?.single_payment_date ? new Date(props.currentPaymentData.single_payment_date + 'T00:00:00').toISOString().split('T')[0] : null,
        number_of_installments: props.currentPaymentData?.number_of_installments,
        first_installment_due_date: props.currentPaymentData?.first_installment_due_date ? new Date(props.currentPaymentData.first_installment_due_date + 'T00:00:00').toISOString().split('T')[0] : null,
        notes: props.currentPaymentData?.notes,
        value_of_installment: props.currentPaymentData?.value_of_installment,
    },
});

const toUndefined = <T>(value: T | null): T | undefined => value === null ? undefined : value;

const chargeConsultationProxy = computed({
  get() { return String(form.payment.charge_consultation); },
  set(value: 'true' | 'false' | string) { form.payment.charge_consultation = value === 'true'; }
});

const responsibleIdProxy = computed({
    get() { return toUndefined(form.responsible_id === null ? null : String(form.responsible_id)); },
    set(value?: string | number) {
        form.responsible_id = (value === 'null' || value === undefined) ? null : String(value);
    }
});

const statusProxy = computed({
    get() { return toUndefined(form.status); },
    set(value?: string) { form.status = value ?? null; }
});

const stageProxy = computed({
    get() { return toUndefined(form.stage); },
    set(value?: number) { form.stage = value ?? null; }
});

const paymentTypeProxy = computed({
    get() { return toUndefined(form.payment.payment_type); },
    set(value?: string) { form.payment.payment_type = value ?? null; }
});

const paymentMethodProxy = computed({
    get() { return toUndefined(form.payment.payment_method); },
    set(value?: string) { form.payment.payment_method = value ?? null; }
});


watch(() => form.payment.charge_consultation, (isCharging) => {
    form.clearErrors('payment.consultation_fee_amount');
    if (!isCharging) {
        form.payment.consultation_fee_amount = null;
    }
});

watch(() => form.payment.payment_type, (newType) => {
    form.clearErrors('payment.single_payment_date', 'payment.number_of_installments', 'payment.first_installment_due_date');
    if (newType === 'a_vista') {
        form.payment.number_of_installments = null;
        form.payment.first_installment_due_date = null;
    } else if (newType === 'parcelado') {
        if (!form.payment.advance_payment_amount) {
            form.payment.single_payment_date = null;
        }
    }
});

const pageTitle = computed(() => `Editar Caso: ${props.process.title}`);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Casos', href: routeHelper('processes.index') },
    { title: props.process.title, href: routeHelper('processes.show', props.process.id) },
    { title: 'Editar', href: '#' },
]);

const contactOptions = computed(() => {
    return (props.contactsList || []).map(contact => ({
        value: String(contact.id),
        label: `${contact.name || contact.business_name || 'Nome não disponível'} (${contact.type === 'physical' ? 'PF' : 'PJ'})`
    }));
});

const responsibleOptions = computed(() => {
    return (props.users || []).map(user => ({
        value: String(user.id),
        label: user.name
    }));
});

const priorityOptions = computed(() => props.prioritiesForForm || []);
const statusOptions = computed(() => props.statusesForForm || []);

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
          if (newWorkflowSelected && currentStages.value.length > 0 && form.stage === null) {
            form.stage = currentStages.value[0].key;
         }
    }
}, { immediate: true });

// <<< INÍCIO DA CORREÇÃO >>>
const installmentAmountDisplay = ref<string | null>(null);

function calculateInstallmentAmount() {
    const { total_amount, advance_payment_amount, number_of_installments, payment_type } = form.payment;
    if (payment_type === 'parcelado' && number_of_installments && number_of_installments > 0) {
        const totalAmount = parseFloat(String(total_amount)) || 0;
        const advanceAmount = parseFloat(String(advance_payment_amount)) || 0;
        const numInstallments = parseInt(String(number_of_installments));
        const remaining = totalAmount - advanceAmount;

        if (remaining > 0 && !isNaN(numInstallments) && numInstallments > 0) {
            installmentAmountDisplay.value = (remaining / numInstallments).toFixed(2);
        } else {
            installmentAmountDisplay.value = null;
        }
    } else {
        installmentAmountDisplay.value = null;
    }
}

watch(
    () => [form.payment.total_amount, form.payment.advance_payment_amount, form.payment.number_of_installments, form.payment.payment_type],
    calculateInstallmentAmount,
    { deep: true }
);

onMounted(() => {
    const initialInstallmentValue = props.currentPaymentData?.value_of_installment;
    if (initialInstallmentValue) {
        installmentAmountDisplay.value = parseFloat(String(initialInstallmentValue)).toFixed(2);
    } else {
        calculateInstallmentAmount();
    }
});
// <<< FIM DA CORREÇÃO >>>

function submitProcess() {
    const dataToSubmit: ProcessEditFormData = JSON.parse(JSON.stringify(form.data()));

    if (dataToSubmit.responsible_id === 'null') {
      dataToSubmit.responsible_id = null;
    }

    if (dataToSubmit.payment) {
        if (!dataToSubmit.payment.charge_consultation || !(parseFloat(String(dataToSubmit.payment.consultation_fee_amount)) > 0)) {
            dataToSubmit.payment.consultation_fee_amount = null;
        }

        const hasTotalAmount = dataToSubmit.payment.total_amount && parseFloat(String(dataToSubmit.payment.total_amount)) > 0;

        if (hasTotalAmount) {
            const hasAdvancePayment = dataToSubmit.payment.advance_payment_amount && parseFloat(String(dataToSubmit.payment.advance_payment_amount)) > 0;

            if (dataToSubmit.payment.payment_type === 'a_vista') {
                dataToSubmit.payment.number_of_installments = null;
                dataToSubmit.payment.first_installment_due_date = null;
            } else if (dataToSubmit.payment.payment_type === 'parcelado') {
                if (!hasAdvancePayment) {
                    dataToSubmit.payment.single_payment_date = null;
                }
            }

            if (!hasAdvancePayment) {
                dataToSubmit.payment.advance_payment_amount = null;
            }
        } else {
            dataToSubmit.payment.total_amount = null;
            dataToSubmit.payment.advance_payment_amount = null;
            dataToSubmit.payment.payment_type = null;
            dataToSubmit.payment.payment_method = null;
            dataToSubmit.payment.single_payment_date = null;
            dataToSubmit.payment.number_of_installments = null;
            dataToSubmit.payment.first_installment_due_date = null;
        }
    }

    form.transform(() => dataToSubmit)
        .put(routeHelper('processes.update', props.process.id), {
        onSuccess: () => {},
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
              <Label for="title">Título do Caso <span class="text-red-500">*</span></Label>
              <Input
                id="title"
                v-model="form.title"
                required
                class="mt-1"
                placeholder="Defina um título para o caso" />
              <div v-if="form.errors.title" class="text-sm text-red-600 dark:text-red-400 mt-1">
                {{ form.errors.title }}
              </div>
            </div>

            <div>
              <Label for="contact_id">Contato Principal <span class="text-red-500">*</span></Label>
               <Select v-model="form.contact_id" required>
                  <SelectTrigger id="contact_id" class="mt-1">
                      <SelectValue placeholder="Selecione um contato" />
                  </SelectTrigger>
                  <SelectContent>
                      <SelectGroup>
                          <SelectLabel>Contatos</SelectLabel>
                          <SelectItem v-for="contact in contactOptions" :key="contact.value" :value="contact.value">
                              {{ contact.label }}
                          </SelectItem>
                           <SelectItem v-if="contactOptions.length === 0" value="no-contacts" disabled>
                              Nenhum contato disponível
                          </SelectItem>
                      </SelectGroup>
                  </SelectContent>
               </Select>
              <div v-if="form.errors.contact_id" class="text-sm text-red-600 dark:text-red-400 mt-1">
                {{ form.errors.contact_id }}
              </div>
            </div>

            <div>
              <Label for="description">Descrição</Label>
              <Textarea
                id="description"
                v-model="form.description"
                rows="4"
                class="mt-1"
                placeholder="Detalhes sobre o caso, histórico, próximos passos..."
              />
              <div v-if="form.errors.description" class="text-sm text-red-600 dark:text-red-400 mt-1">
                {{ form.errors.description }}
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
              <div>
                <Label for="workflow">Workflow <span class="text-red-500">*</span></Label>
                <Select v-model="form.workflow" required>
                    <SelectTrigger id="workflow" class="mt-1">
                        <SelectValue placeholder="Selecione um workflow" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectLabel>Workflows Disponíveis</SelectLabel>
                            <SelectItem v-for="wf in availableWorkflows" :key="wf.key" :value="wf.key">
                                {{ wf.label }}
                            </SelectItem>
                             <SelectItem v-if="!availableWorkflows || availableWorkflows.length === 0" value="no-workflows" disabled>
                                Nenhum workflow disponível
                            </SelectItem>
                        </SelectGroup>
                    </SelectContent>
                </Select>
                <div v-if="form.errors.workflow" class="text-sm text-red-600 dark:text-red-400 mt-1">
                  {{ form.errors.workflow }}
                </div>
              </div>

              <div>
                <Label for="stage">Estágio <span class="text-red-500">*</span></Label>
                <Select v-model="stageProxy" :disabled="!form.workflow || currentStages.length === 0" required>
                    <SelectTrigger id="stage" class="mt-1">
                        <SelectValue placeholder="Selecione um estágio" />
                    </SelectTrigger>
                    <SelectContent>
                        <SelectGroup>
                            <SelectLabel>Estágios do Workflow</SelectLabel>
                            <SelectItem v-for="st in currentStages" :key="st.key" :value="st.key">
                                {{ st.label }}
                            </SelectItem>
                            <SelectItem v-if="form.workflow && currentStages.length === 0" value="no-stages" disabled>
                                Nenhum estágio para este workflow
                            </SelectItem>
                             <SelectItem v-if="!form.workflow" value="select-workflow" disabled>
                                Selecione um workflow primeiro
                            </SelectItem>
                        </SelectGroup>
                    </SelectContent>
                </Select>
                <div v-if="form.errors.stage" class="text-sm text-red-600 dark:text-red-400 mt-1">
                  {{ form.errors.stage }}
                </div>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                 <div>
                    <Label for="responsible_id">Responsável</Label>
                    <Select v-model="responsibleIdProxy">
                        <SelectTrigger id="responsible_id" class="mt-1">
                            <SelectValue placeholder="Selecione um responsável" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectLabel>Usuários</SelectLabel>
                                <SelectItem value="null">Ninguém (Não atribuído)</SelectItem>
                                <SelectItem v-for="user in responsibleOptions" :key="user.value" :value="user.value">
                                    {{ user.label }}
                                </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <div v-if="form.errors.responsible_id" class="text-sm text-red-600 dark:text-red-400 mt-1">
                      {{ form.errors.responsible_id }}
                    </div>
                </div>
                <div>
                    <Label for="origin">Origem do Caso</Label>
                    <Input
                        id="origin"
                        v-model="form.origin"
                        class="mt-1"
                        placeholder="Ex: Indicação, Website, Telefone"
                    />
                    <div v-if="form.errors.origin" class="text-sm text-red-600 dark:text-red-400 mt-1">
                        {{ form.errors.origin }}
                    </div>
                </div>
            </div>


            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <div>
                    <Label for="priority">Prioridade <span class="text-red-500">*</span></Label>
                    <Select v-model="form.priority" required>
                        <SelectTrigger id="priority" class="mt-1">
                            <SelectValue placeholder="Selecione a prioridade" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectLabel>Níveis de Prioridade</SelectLabel>
                                <SelectItem v-for="prio in priorityOptions" :key="prio.key" :value="prio.key">
                                    {{ prio.label }}
                                </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <div v-if="form.errors.priority" class="text-sm text-red-600 dark:text-red-400 mt-1">
                        {{ form.errors.priority }}
                    </div>
                </div>
                 <div>
                    <Label for="status">Status <span class="text-red-500">*</span></Label>
                    <Select v-model="statusProxy" required>
                        <SelectTrigger id="status" class="mt-1">
                            <SelectValue placeholder="Selecione o status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectGroup>
                                <SelectLabel>Status Disponíveis</SelectLabel>
                                <SelectItem v-for="st in statusOptions" :key="st.key" :value="st.key">
                                    {{ st.label }}
                                </SelectItem>
                            </SelectGroup>
                        </SelectContent>
                    </Select>
                    <div v-if="form.errors.status" class="text-sm text-red-600 dark:text-red-400 mt-1">
                        {{ form.errors.status }}
                    </div>
                </div>
            </div>

            <!-- Detalhes Financeiros -->
            <div class="pt-6 mt-6 border-t border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100 mb-6">Detalhes Financeiros</h3>
                <div class="space-y-6">
                    <div class="space-y-2">
                        <Label for="charge_consultation_select">Cobrar Consulta Inicial?</Label>
                        <Select v-model="chargeConsultationProxy">
                            <SelectTrigger id="charge_consultation_select" class="mt-1">
                                <SelectValue placeholder="Selecione uma opção" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectLabel>Opções</SelectLabel>
                                    <SelectItem v-for="option in chargeConsultationOptions" :key="option.value" :value="option.value">
                                        {{ option.label }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <div v-if="form.errors['payment.charge_consultation']" class="text-sm text-red-600 dark:text-red-400">
                            {{ form.errors['payment.charge_consultation'] }}
                        </div>
                    </div>

                    <div v-if="form.payment.charge_consultation" class="pl-2">
                        <Label for="consultation_fee_amount">
                            Valor da Consulta (R$) <span class="text-red-500">*</span>
                        </Label>
                        <Input id="consultation_fee_amount" type="number" step="0.01" min="0"
                            v-model="form.payment.consultation_fee_amount" class="mt-1 block w-full"
                            placeholder="Ex: 150.00" />
                        <div v-if="form.errors['payment.consultation_fee_amount']" class="text-sm text-red-600 dark:text-red-400 mt-1">
                            {{ form.errors['payment.consultation_fee_amount'] }}
                        </div>
                    </div>

                    <div>
                        <Label for="payment_total_amount">
                            Valor Total do Contrato/Serviço (R$) (Opcional)
                        </Label>
                        <Input id="payment_total_amount" type="number" step="0.01" min="0"
                            v-model="form.payment.total_amount" class="mt-1 block w-full"
                            placeholder="Ex: 3000.00 (deixe em branco se não aplicável)" />
                        <div v-if="form.errors['payment.total_amount']" class="text-sm text-red-600 dark:text-red-400 mt-1">
                            {{ form.errors['payment.total_amount'] }}
                        </div>
                    </div>

                    <template v-if="form.payment.total_amount && parseFloat(String(form.payment.total_amount)) > 0">
                        <div class="space-y-6">
                            <div>
                                <Label for="advance_payment_amount">
                                    Valor da Entrada (R$) (Opcional)
                                </Label>
                                <Input id="advance_payment_amount" type="number" step="0.01" min="0"
                                    v-model="form.payment.advance_payment_amount" class="mt-1 block w-full"
                                    placeholder="Ex: 500.00" />
                                <div v-if="form.errors['payment.advance_payment_amount']" class="text-sm text-red-600 dark:text-red-400 mt-1">
                                    {{ form.errors['payment.advance_payment_amount'] }}
                                </div>
                            </div>

                            <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-6">
                                <div>
                                    <Label for="payment_type">
                                        Forma de Pagamento (Restante)
                                    </Label>
                                    <Select v-model="paymentTypeProxy">
                                        <SelectTrigger id="payment_type" class="mt-1 w-full">
                                            <SelectValue placeholder="Selecione a forma" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                <SelectLabel>Formas de Pagamento</SelectLabel>
                                                <SelectItem v-for="type in props.paymentTypes" :key="type.value" :value="type.value">
                                                    {{ type.label }}
                                                </SelectItem>
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                    <div v-if="form.errors['payment.payment_type']" class="text-sm text-red-600 dark:text-red-400 mt-1">
                                      {{ form.errors['payment.payment_type'] }}
                                    </div>
                                </div>
                                <div>
                                    <Label for="payment_method">
                                        Meio de Pagamento
                                    </Label>
                                    <Select v-model="paymentMethodProxy">
                                        <SelectTrigger id="payment_method" class="mt-1 w-full">
                                            <SelectValue placeholder="Selecione o meio" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectGroup>
                                                 <SelectLabel>Meios de Pagamento</SelectLabel>
                                                 <SelectItem v-for="method in props.paymentMethods" :key="method" :value="method">
                                                    {{ method }}
                                                </SelectItem>
                                            </SelectGroup>
                                        </SelectContent>
                                    </Select>
                                     <div v-if="form.errors['payment.payment_method']" class="text-sm text-red-600 dark:text-red-400 mt-1">
                                      {{ form.errors['payment.payment_method'] }}
                                    </div>
                                </div>
                            </div>

                            <div v-if="form.payment.advance_payment_amount && parseFloat(String(form.payment.advance_payment_amount)) > 0" class="mt-4">
                                <div>
                                    <Label for="entry_payment_date"> Data da Entrada </Label>
                                    <Input id="entry_payment_date" type="date" v-model="form.payment.single_payment_date" class="mt-1 block w-full" />
                                    <div v-if="form.errors['payment.single_payment_date']" class="text-sm text-red-600 dark:text-red-400 mt-1">
                                        {{ form.errors['payment.single_payment_date'] }}
                                    </div>
                                </div>
                            </div>

                            <div v-if="form.payment.payment_type === 'a_vista' && !(form.payment.advance_payment_amount && parseFloat(String(form.payment.advance_payment_amount)) > 0) && (parseFloat(String(form.payment.total_amount)) - parseFloat(String(form.payment.advance_payment_amount) || '0')) > 0" class="mt-4">
                                <div>
                                    <Label for="single_payment_date_cash"> Data do Pagamento à Vista </Label>
                                    <Input id="single_payment_date_cash" type="date" v-model="form.payment.single_payment_date" class="mt-1 block w-full" />
                                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                        Valor a ser pago nesta data: R$ {{ (parseFloat(String(form.payment.total_amount)) - parseFloat(String(form.payment.advance_payment_amount) || '0')).toFixed(2) }}
                                    </p>
                                    <div v-if="form.errors['payment.single_payment_date']" class="text-sm text-red-600 dark:text-red-400 mt-1">
                                        {{ form.errors['payment.single_payment_date'] }}
                                    </div>
                                </div>
                            </div>

                            <div v-if="form.payment.payment_type === 'parcelado' && (parseFloat(String(form.payment.total_amount)) - parseFloat(String(form.payment.advance_payment_amount) || '0')) > 0" class="space-y-6 mt-4">
                                <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-6">
                                    <div>
                                        <Label for="number_of_installments"> Número de Parcelas (Restante) </Label>
                                        <Input id="number_of_installments" type="number" min="1" v-model.number="form.payment.number_of_installments" class="mt-1 block w-full" placeholder="Ex: 3" />
                                        <div v-if="form.errors['payment.number_of_installments']" class="text-sm text-red-600 dark:text-red-400 mt-1">
                                            {{ form.errors['payment.number_of_installments'] }}
                                        </div>
                                    </div>
                                    <div>
                                        <Label for="first_installment_due_date"> Data da 1ª Parcela (Restante) </Label>
                                        <Input id="first_installment_due_date" type="date" v-model="form.payment.first_installment_due_date" class="mt-1 block w-full" />
                                        <div v-if="form.errors['payment.first_installment_due_date']" class="text-sm text-red-600 dark:text-red-400 mt-1">
                                            {{ form.errors['payment.first_installment_due_date'] }}
                                        </div>
                                    </div>
                                </div>
                                <div>
                                    <Label for="installment_amount_display"> Valor da Parcela (R$) </Label>
                                    <Input id="installment_amount_display" type="text"
                                        :value="installmentAmountDisplay ? `R$ ${installmentAmountDisplay}` : 'R$ 0.00'"
                                        class="mt-1 block w-full bg-gray-100 dark:bg-slate-700 cursor-not-allowed"
                                        readonly />
                                </div>
                            </div>

                            <div class="mt-4">
                                <Label for="payment_notes"> Observações Financeiras </Label>
                                <Textarea id="payment_notes" v-model="form.payment.notes" rows="3"
                                    class="mt-1 block w-full"
                                    placeholder="Detalhes sobre a entrada, datas das parcelas, etc." />
                                <div v-if="form.errors['payment.notes']" class="text-sm text-red-600 dark:text-red-400 mt-1">
                                    {{ form.errors['payment.notes'] }}
                                </div>
                            </div>
                        </div>
                    </template>
                    <p v-else class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                        Preencha o "Valor Total do Contrato/Serviço" para habilitar opções de pagamento detalhadas.
                    </p>
                </div>
            </div>

            <div class="flex justify-end space-x-3 pt-8">
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
