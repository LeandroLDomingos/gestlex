<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
// import { Badge } from '@/components/ui/badge'; // Não usado neste arquivo
// import { Separator } from '@/components/ui/separator'; // Não usado neste arquivo
import { Input } from '@/components/ui/input'; // Assumindo que o Input.vue está em @/components/ui/input/Input.vue
import { Label } from '@/components/ui/label'; // Assumindo que o Label.vue está em @/components/ui/label/Label.vue
// import { Checkbox } from '@/components/ui/checkbox'; // Não usado neste arquivo
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
    SelectGroup,
    SelectLabel
} from '@/components/ui/select';
// import { type User, type Contact, type SharedData } from '@/types'; // SharedData não usado diretamente aqui

// Tipos
interface BreadcrumbItem {
    title: string;
    href: string;
}
interface UserSelectItem {
    id: number | string; // Pode ser string se vier de um select que converte
    name: string;
}
interface WorkflowOption {
    key: string; // Chave do workflow é string
    label: string;
}
interface StageOption {
    key: number; // Chave do estágio é number
    label: string;
}
interface ContactSelectItem {
    id: number | string; // Pode ser string se vier de um select
    name: string | null;
    business_name?: string | null;
    type: 'physical' | 'legal' | string; // Tipo pode ser mais genérico se vier de dados não controlados
}

interface PaymentFormData {
    total_amount: number | string | null;
    advance_payment_amount: number | string | null;
    payment_type: 'a_vista' | 'parcelado' | string | null;
    payment_method: string | null;
    single_payment_date: string | null; // Usado para Data da Entrada ou Data do Pagamento Único à Vista
    number_of_installments: number | null;
    first_installment_due_date: string | null; // Data da 1ª parcela do financiamento
    notes: string | null;
}

interface ProcessCreateFormData {
    title: string;
    description: string | null;
    contact_id: string | number | null;
    responsible_id: string | number | null; // Pode ser string do select, converter para number se necessário no backend
    workflow: string | null; // Chave do workflow é string
    stage: number | null;    // MUDADO: de stage_id para stage, tipo number
    priority: string | null;
    origin: string | null;
    status: string | null;
    payment: PaymentFormData;
}

const props = defineProps<{
    auth: any; // Definir tipo mais específico se disponível (ex: SharedData['auth'])
    contact_id?: number | string | null;
    contact_name?: string | null;
    users: UserSelectItem[];
    contactsList: ContactSelectItem[];
    availableWorkflows: WorkflowOption[];
    allStages: Record<string, StageOption[]>;
    availableStatuses: Array<{ key: string; label: string }>;
    availablePriorities: Array<{ key: string; label: string }>;
    paymentMethods: string[];
    paymentTypes: Array<{ value: string; label: string }>;
    errors?: Record<string, string>; // Erros gerais passados pelo controller
}>();

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

const form = useForm<ProcessCreateFormData>({
    title: '',
    description: '',
    contact_id: props.contact_id || null,
    responsible_id: props.auth.user ? props.auth.user.id : null, // Assumindo que auth.user.id é number
    workflow: (props.availableWorkflows && props.availableWorkflows.length > 0) ? props.availableWorkflows[0].key : null,
    stage: null, // MUDADO: de stage_id para stage, inicializado como null
    priority: (props.availablePriorities && props.availablePriorities.length > 0) ? props.availablePriorities.find(p => p.key === 'medium')?.key || props.availablePriorities[0].key : null,
    origin: '',
    status: (props.availableStatuses && props.availableStatuses.length > 0) ? props.availableStatuses.find(s => s.label.toLowerCase() === 'aberto')?.key || props.availableStatuses[0].key : null,
    payment: {
        total_amount: null,
        advance_payment_amount: null,
        payment_type: (props.paymentTypes && props.paymentTypes.length > 0) ? props.paymentTypes[0].value : 'a_vista',
        payment_method: null,
        single_payment_date: null,
        number_of_installments: null,
        first_installment_due_date: null,
        notes: null,
    },
});

onMounted(() => {
    console.log('[onMounted] Props recebidos:', JSON.parse(JSON.stringify(props)));
    // O watch com immediate:true já terá definido o stage inicial se houver workflow
    console.log('[onMounted] Estado inicial do formulário (após watch immediate):', JSON.parse(JSON.stringify(form)));
});

const currentStages = computed<StageOption[]>(() => {
    // console.log('[computed currentStages] form.workflow:', form.workflow);
    if (form.workflow && props.allStages && props.allStages[form.workflow]) {
        const stages = props.allStages[form.workflow];
        // console.log('[computed currentStages] Estágios encontrados:', JSON.parse(JSON.stringify(stages)));
        return stages;
    }
    // console.log('[computed currentStages] Nenhum estágio encontrado.');
    return [];
});

watch(() => form.workflow, (newWorkflow, oldWorkflow) => {
    // console.log(`[WATCH form.workflow] Mudança de '${oldWorkflow}' para '${newWorkflow}'`);
    const stagesForNewWorkflow = newWorkflow && props.allStages && props.allStages[newWorkflow] ? props.allStages[newWorkflow] : [];
    // console.log('[WATCH form.workflow] Estágios para o novo workflow:', JSON.parse(JSON.stringify(stagesForNewWorkflow)));

    if (stagesForNewWorkflow.length > 0) {
        form.stage = stagesForNewWorkflow[0].key; // key do estágio é number
        // console.log(`[WATCH form.workflow] form.stage DEFINIDO PARA: ${form.stage} (tipo: ${typeof form.stage})`);
    } else {
        form.stage = null;
        // console.log('[WATCH form.workflow] form.stage DEFINIDO PARA NULL');
    }
    form.clearErrors('stage');
}, { immediate: true });


watch(() => form.payment.payment_type, (newType) => {
    form.clearErrors(
        'payment.single_payment_date',
        'payment.number_of_installments',
        'payment.first_installment_due_date'
    );
    if (newType === 'a_vista') {
        form.payment.number_of_installments = null;
        form.payment.first_installment_due_date = null;
    } else if (newType === 'parcelado') {
        if (!(form.payment.advance_payment_amount && parseFloat(String(form.payment.advance_payment_amount)) > 0)) {
            // Limpa single_payment_date apenas se não houver entrada, pois ele seria para o pagamento à vista
            // form.payment.single_payment_date = null; // Opcional, já que o campo não será mostrado
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

function submit() {
    const dataToSubmit: ProcessCreateFormData = JSON.parse(JSON.stringify(form.data()));

    if (dataToSubmit.contact_id !== null && dataToSubmit.contact_id !== undefined) {
        dataToSubmit.contact_id = String(dataToSubmit.contact_id);
    }
    if (dataToSubmit.responsible_id !== null && dataToSubmit.responsible_id !== undefined && String(dataToSubmit.responsible_id).trim() !== "") {
        dataToSubmit.responsible_id = Number(dataToSubmit.responsible_id); // Converter para número se for string
    } else {
        dataToSubmit.responsible_id = null;
    }

    // Lógica para limpar/ajustar campos de pagamento antes do envio
    if (dataToSubmit.payment && dataToSubmit.payment.total_amount && parseFloat(String(dataToSubmit.payment.total_amount)) > 0) {
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
        dataToSubmit.payment = {} as PaymentFormData;
    }
    
    // console.log('Dados finais a serem enviados:', dataToSubmit);

    form.transform(() => dataToSubmit)
        .post(route('processes.store'), {
            onSuccess: () => {
                // O backend redireciona
            },
            onError: (errors) => {
                console.error("Erros do backend na submissão:", errors);
            },
        });
}

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Casos', href: route('processes.index') },
    { title: 'Novo Caso', href: route('processes.create') },
]);

const contactOptions = computed(() => {
    return props.contactsList.map(contact => ({
        value: contact.id, // Mantém como number se for number
        label: `${contact.name || contact.business_name || 'Nome não disponível'} (${contact.type === 'physical' ? 'PF' : 'PJ'})`
    }));
});

const responsibleOptions = computed(() => {
    return props.users.map(user => ({
        value: user.id, // Mantém como number
        label: user.name
    }));
});

const priorityOptions = computed(() => props.availablePriorities);
const statusOptions = computed(() => props.availableStatuses);

</script>

<template>
    <AppLayout :breadcrumbs="breadcrumbs">
        <Head title="Novo Caso" />
        <div class="container mx-auto p-4 sm:p-6 lg:p-8">
            <Card class="max-w-3xl mx-auto dark:bg-gray-800">
                <CardHeader>
                    <CardTitle class="text-2xl">{{ pageTitle }}</CardTitle>
                    <CardDescription>
                        Preencha os detalhes abaixo para criar um novo caso/processo.
                    </CardDescription>
                </CardHeader>
                <CardContent>
                    <form @submit.prevent="submit" class="space-y-6">

                        <div>
                            <Label for="title">Título do Caso <span class="text-red-500">*</span></Label>
                            <Input id="title" v-model="form.title" required placeholder="Defina um título para o caso" />
                            <div v-if="form.errors.title" class="text-sm text-red-600 dark:text-red-400 mt-1">
                                {{ form.errors.title }}
                            </div>
                        </div>

                        <div>
                            <Label for="contact_id">Contato Principal <span class="text-red-500">*</span></Label>
                            <Select v-model="form.contact_id" :disabled="!!props.contact_id">
                                <SelectTrigger id="contact_id">
                                    <SelectValue :placeholder="props.contact_name || 'Selecione um contato'" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectLabel>Contatos</SelectLabel>
                                        <SelectItem v-if="props.contact_id && props.contact_name" :value="props.contact_id">
                                            {{ props.contact_name }}
                                        </SelectItem>
                                        <template v-else>
                                            <SelectItem v-for="contact in contactOptions" :key="contact.value"
                                                :value="contact.value">
                                                {{ contact.label }}
                                            </SelectItem>
                                        </template>
                                        <SelectItem v-if="(!props.contactsList || props.contactsList.length === 0) && !(props.contact_id && props.contact_name)" value="null_placeholder_disabled" disabled>
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
                            <Textarea id="description" v-model="form.description" rows="4" class="mt-1 block w-full"
                                placeholder="Detalhes sobre o caso, histórico, próximos passos..." />
                            <div v-if="form.errors.description" class="text-sm text-red-600 dark:text-red-400 mt-1">
                                {{ form.errors.description }}
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <Label for="workflow">Workflow <span class="text-red-500">*</span></Label>
                                <Select v-model="form.workflow">
                                    <SelectTrigger id="workflow">
                                        <SelectValue placeholder="Selecione um workflow" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectLabel>Workflows Disponíveis</SelectLabel>
                                            <SelectItem v-for="workflowOpt in availableWorkflows" :key="workflowOpt.key"
                                                :value="workflowOpt.key">
                                                {{ workflowOpt.label }}
                                            </SelectItem>
                                            <SelectItem v-if="!availableWorkflows || availableWorkflows.length === 0" value="no_workflow_placeholder" disabled>
                                                Nenhum workflow configurado
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
                                <Select v-model="form.stage" :disabled="!form.workflow || currentStages.length === 0">
                                    <SelectTrigger id="stage">
                                        <SelectValue placeholder="Selecione um estágio" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectLabel>Estágios do Workflow</SelectLabel>
                                            <SelectItem v-for="stageOpt in currentStages" :key="stageOpt.key" :value="stageOpt.key">
                                                {{ stageOpt.label }}
                                            </SelectItem>
                                            <SelectItem v-if="form.workflow && currentStages.length === 0" value="no_stage_placeholder" disabled>
                                                Nenhum estágio para este workflow
                                            </SelectItem>
                                             <SelectItem v-if="!form.workflow" value="select_workflow_first" disabled>
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
                                <Select v-model="form.responsible_id">
                                    <SelectTrigger id="responsible_id">
                                        <SelectValue placeholder="Selecione um responsável" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectLabel>Usuários</SelectLabel>
                                            <SelectItem :value="null">Ninguém (Não atribuído)</SelectItem>
                                            <SelectItem v-for="user in responsibleOptions" :key="user.value"
                                                :value="user.value">
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
                                <Input id="origin" v-model="form.origin" placeholder="Ex: Indicação, Website, Telefone" />
                                <div v-if="form.errors.origin" class="text-sm text-red-600 dark:text-red-400 mt-1">
                                    {{ form.errors.origin }}
                                </div>
                            </div>
                        </div>

                        <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                            <div>
                                <Label for="priority">Prioridade <span class="text-red-500">*</span></Label>
                                <Select v-model="form.priority">
                                    <SelectTrigger id="priority">
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
                                <Select v-model="form.status">
                                    <SelectTrigger id="status">
                                        <SelectValue placeholder="Selecione o status" />
                                    </SelectTrigger>
                                    <SelectContent>
                                        <SelectGroup>
                                            <SelectLabel>Status Disponíveis</SelectLabel>
                                            <SelectItem v-for="stat in statusOptions" :key="stat.key" :value="stat.key">
                                                {{ stat.label }}
                                            </SelectItem>
                                        </SelectGroup>
                                    </SelectContent>
                                </Select>
                                <div v-if="form.errors.status" class="text-sm text-red-600 dark:text-red-400 mt-1">
                                    {{ form.errors.status }}
                                </div>
                            </div>
                        </div>

                        <div class="pt-6 mt-6 border-t border-gray-200 dark:border-gray-700">
                            <h3 class="text-lg font-medium leading-6 text-gray-900 dark:text-gray-100 mb-6">Detalhes Financeiros</h3>
                            <div class="space-y-6">
                                <div>
                                    <Label for="payment_total_amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                        Valor Total do Contrato/Serviço (R$)
                                    </Label>
                                    <Input id="payment_total_amount" type="number" step="0.01" min="0"
                                        v-model="form.payment.total_amount" class="mt-1 block w-full"
                                        placeholder="Ex: 3000.00 (deixe em branco se não aplicável)" />
                                    <div v-if="form.errors['payment.total_amount']" class="text-sm text-red-600 dark:text-red-400 mt-1">
                                        {{ form.errors['payment.total_amount'] }}
                                    </div>
                                </div>

                                <template v-if="form.payment.total_amount && parseFloat(String(form.payment.total_amount)) > 0">
                                    <div>
                                        <Label for="advance_payment_amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
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
                                            <Label for="payment_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                Forma de Pagamento (Restante)
                                            </Label>
                                            <Select v-model="form.payment.payment_type">
                                                <SelectTrigger id="payment_type" class="mt-1 w-full">
                                                    <SelectValue placeholder="Selecione a forma" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectGroup>
                                                        <SelectItem v-if="!props.paymentTypes || props.paymentTypes.length === 0" value="null_placeholder_type" disabled>
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
                                            <div v-if="form.errors['payment.payment_type']" class="text-sm text-red-600 dark:text-red-400 mt-1">
                                                {{ form.errors['payment.payment_type'] }}
                                            </div>
                                        </div>
                                        <div>
                                            <Label for="payment_method" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                Meio de Pagamento
                                            </Label>
                                            <Select v-model="form.payment.payment_method">
                                                <SelectTrigger id="payment_method" class="mt-1 w-full">
                                                    <SelectValue placeholder="Selecione o meio" />
                                                </SelectTrigger>
                                                <SelectContent>
                                                    <SelectGroup>
                                                        <SelectItem v-if="!props.paymentMethods || props.paymentMethods.length === 0" value="null_placeholder_method" disabled>
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
                                            <div v-if="form.errors['payment.payment_method']" class="text-sm text-red-600 dark:text-red-400 mt-1">
                                                {{ form.errors['payment.payment_method'] }}
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="form.payment.advance_payment_amount && parseFloat(String(form.payment.advance_payment_amount)) > 0" class="mt-4">
                                        <div>
                                            <Label for="entry_payment_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                Data da Entrada
                                            </Label>
                                            <Input id="entry_payment_date" type="date"
                                                v-model="form.payment.single_payment_date" class="mt-1 block w-full" />
                                            <div v-if="form.errors['payment.single_payment_date']" class="text-sm text-red-600 dark:text-red-400 mt-1">
                                                {{ form.errors['payment.single_payment_date'] }}
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="form.payment.payment_type === 'a_vista' && !(form.payment.advance_payment_amount && parseFloat(String(form.payment.advance_payment_amount)) > 0) && amount_to_be_paid_or_installed > 0" class="mt-4">
                                        <div>
                                            <Label for="single_payment_date_cash" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                Data do Pagamento à Vista
                                            </Label>
                                            <Input id="single_payment_date_cash" type="date"
                                                v-model="form.payment.single_payment_date" class="mt-1 block w-full" />
                                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                                Valor a ser pago nesta data: R$ {{ amount_to_be_paid_or_installed.toFixed(2) }}
                                            </p>
                                            <div v-if="form.errors['payment.single_payment_date']" class="text-sm text-red-600 dark:text-red-400 mt-1">
                                                {{ form.errors['payment.single_payment_date'] }}
                                            </div>
                                        </div>
                                    </div>

                                    <div v-if="form.payment.payment_type === 'parcelado' && amount_to_be_paid_or_installed > 0" class="space-y-6 mt-4">
                                        <div class="grid grid-cols-1 md:grid-cols-2 gap-x-6 gap-y-6">
                                            <div>
                                                <Label for="number_of_installments" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                    Número de Parcelas (Restante)
                                                </Label>
                                                <Input id="number_of_installments" type="number" min="1"
                                                    v-model.number="form.payment.number_of_installments"
                                                    class="mt-1 block w-full" placeholder="Ex: 3" />
                                                <div v-if="form.errors['payment.number_of_installments']" class="text-sm text-red-600 dark:text-red-400 mt-1">
                                                    {{ form.errors['payment.number_of_installments'] }}
                                                </div>
                                            </div>
                                            <div>
                                                <Label for="first_installment_due_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                    Data da 1ª Parcela (Restante)
                                                </Label>
                                                <Input id="first_installment_due_date" type="date"
                                                    v-model="form.payment.first_installment_due_date" class="mt-1 block w-full" />
                                                <div v-if="form.errors['payment.first_installment_due_date']" class="text-sm text-red-600 dark:text-red-400 mt-1">
                                                    {{ form.errors['payment.first_installment_due_date'] }}
                                                </div>
                                            </div>
                                        </div>
                                        <div>
                                            <Label for="installment_amount_display" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                                Valor da Parcela (R$)
                                            </Label>
                                            <Input id="installment_amount_display" type="text"
                                                :value="installmentAmount ? `R$ ${installmentAmount}` : (amount_to_be_paid_or_installed > 0 && form.payment.number_of_installments ? 'Calculando...' : 'R$ 0.00')"
                                                class="mt-1 block w-full bg-gray-100 dark:bg-slate-700 cursor-not-allowed"
                                                readonly />
                                        </div>
                                    </div>
                                    <div class="mt-4">
                                        <Label for="payment_notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                            Observações Financeiras
                                        </Label>
                                        <Textarea id="payment_notes" v-model="form.payment.notes" rows="3"
                                            class="mt-1 block w-full"
                                            placeholder="Detalhes sobre a entrada, datas das parcelas, etc." />
                                        <div v-if="form.errors['payment.notes']" class="text-sm text-red-600 dark:text-red-400 mt-1">
                                            {{ form.errors['payment.notes'] }}
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </div>

                        <div class="flex justify-end space-x-3 pt-8">
                            <Link :href="props.contact_id ? route('contacts.show', props.contact_id) : route('processes.index')">
                                <Button type="button" variant="outline">Cancelar</Button>
                            </Link>
                            <Button type="submit" :disabled="form.processing">
                                 <svg v-if="form.processing" class="animate-spin -ml-1 mr-3 h-5 w-5" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                </svg>
                                {{ form.processing ? 'Salvando...' : 'Salvar Caso' }}
                            </Button>
                        </div>
                    </form>
                </CardContent>
            </Card>
        </div>
    </AppLayout>
</template>
