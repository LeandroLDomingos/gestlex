<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
// import InputError from '@/Components/InputError.vue'; // Descomente se tiver este componente

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
    key: string; // Chave do workflow (ex: 'prospecting')
    label: string; // Rótulo amigável (ex: 'Prospecção')
}

interface StageOption {
    key: number; // Chave do estágio (inteiro, conforme definido nas constantes do modelo)
    label: string; // Rótulo amigável do estágio
}

interface ContactSelectItem {
    id: number | string;
    name: string; // Nome para PF ou Nome Fantasia para PJ
    business_name?: string; // Razão Social para PJ
    type: 'physical' | 'legal';
}

// --- ALTERAÇÃO 1: Atualizar Props ---
const props = defineProps<{
    contact_id?: number | string | null;
    contact_name?: string | null;
    users?: UserSelectItem[];
    contactsList?: ContactSelectItem[];
    availableWorkflows?: WorkflowOption[];
    allStages?: Record<string, StageOption[]>;
    paymentMethods?: string[]; // Adicionado para receber os métodos de pagamento
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

// --- ALTERAÇÃO 2: Modificar useForm ---
const form = useForm({
    title: '',
    description: '',
    contact_id: props.contact_id || null,
    responsible_id: null as (number | string | null),
    workflow: null as (string | null),
    stage: null as (number | null),
    due_date: '', // Data de vencimento do CASO
    priority: 'medium',
    origin: '',
    // negotiated_value: null as (number | null), // Removido

    // Adicionado objeto de pagamento
    payment: {
        amount: null as (number | null),
        method: null as (string | null),
        date: '', // Data do pagamento
        notes: '', // Observações sobre o pagamento
    },
    // status: 'Aberto', // Status inicial pode ser definido aqui ou no backend
});

const pageTitle = computed(() => {
    return props.contact_name ? `Novo Caso para ${props.contact_name}` : 'Novo Caso';
});

const breadcrumbs = computed<BreadcrumbItem[]>(() => {
    const crumbs: BreadcrumbItem[] = [{ title: 'Casos', href: routeHelper('processes.index') }];
    if (props.contact_id && props.contact_name) {
        crumbs.unshift({ title: 'Contatos', href: routeHelper('contacts.index') });
        crumbs.splice(1, 0, {
            title: props.contact_name,
            href: routeHelper('contacts.show', props.contact_id),
        });
    }
    crumbs.push({ title: 'Novo Caso', href: '#' });
    return crumbs;
});

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

const currentStages = computed<StageOption[]>(() => {
    if (form.workflow && props.allStages && props.allStages[form.workflow]) {
        return props.allStages[form.workflow];
    }
    return [];
});

watch(() => form.workflow, (newWorkflowSelected, oldWorkflowSelected) => {
    if (newWorkflowSelected !== oldWorkflowSelected) {
        form.stage = null;
        form.clearErrors('stage');
    }
});

function submitProcess() {
    const dataToSubmit = {
        ...form.data(),
        responsible_id: form.responsible_id === 'null' ? null : form.responsible_id,
        contact_id: form.contact_id === 'null' || form.contact_id === '' ? null : form.contact_id,
    };

    form.transform(() => dataToSubmit)
        .post(routeHelper('processes.store'), {
        onSuccess: () => {
            // O backend deve redirecionar
        },
        onError: (formErrors) => {
            console.error('Erro ao criar caso:', formErrors);
        }
    });
}

</script>

<template>
  <Head :title="pageTitle" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="container mx-auto p-4 sm:p-6 lg:p-8">
      <Card class="max-w-3xl mx-auto">
        <CardHeader>
          <CardTitle class="text-2xl">{{ pageTitle }}</CardTitle>
          <CardDescription>
            Preencha os detalhes abaixo para criar um novo caso/processo.
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
              />
              <div v-if="form.errors.title" class="text-sm text-red-600 dark:text-red-400 mt-1">
                {{ form.errors.title }}
              </div>
            </div>

            <div>
              <Label for="contact_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Contato Principal <span class="text-red-500">*</span></Label>
              <Select v-model="form.contact_id" :disabled="!!props.contact_id" required>
                <SelectTrigger id="contact_id" class="mt-1 w-full">
                  <SelectValue placeholder="Selecione um contato" />
                </SelectTrigger>
                <SelectContent>
                   <SelectItem v-if="!props.contactsList || props.contactsList.length === 0" value="null" disabled>
                    Nenhum contato disponível
                  </SelectItem>
                  <template v-else>
                    <SelectItem v-for="contact in props.contactsList" :key="contact.id" :value="String(contact.id)">
                      {{ getContactDisplayForSelect(contact) }}
                    </SelectItem>
                  </template>
                </SelectContent>
              </Select>
              <div v-if="form.errors.contact_id" class="text-sm text-red-600 dark:text-red-400 mt-1">
                {{ form.errors.contact_id }}
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
              <div v-if="form.errors.description" class="text-sm text-red-600 dark:text-red-400 mt-1">
                {{ form.errors.description }}
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
                    <SelectItem v-if="!props.availableWorkflows || props.availableWorkflows.length === 0" value="null" disabled>
                        Nenhum workflow disponível
                    </SelectItem>
                    <template v-else>
                        <SelectItem v-for="wf in props.availableWorkflows" :key="wf.key" :value="wf.key">
                            {{ wf.label }}
                        </SelectItem>
                    </template>
                  </SelectContent>
                </Select>
                <div v-if="form.errors.workflow" class="text-sm text-red-600 dark:text-red-400 mt-1">
                  {{ form.errors.workflow }}
                </div>
              </div>

              <div>
                <Label for="stage" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Estágio <span class="text-red-500">*</span></Label>
                <Select v-model="form.stage" :disabled="!form.workflow || currentStages.length === 0" required>
                  <SelectTrigger id="stage" class="mt-1 w-full">
                    <SelectValue placeholder="Selecione um estágio" />
                  </SelectTrigger>
                  <SelectContent>
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
                  </SelectContent>
                </Select>
                <div v-if="form.errors.stage" class="text-sm text-red-600 dark:text-red-400 mt-1">
                  {{ form.errors.stage }}
                </div>
              </div>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                 <div>
                    <Label for="responsible_user_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Responsável</Label>
                    <Select v-model="form.responsible_id">
                        <SelectTrigger id="responsible_user_id" class="mt-1 w-full">
                            <SelectValue placeholder="Selecione um responsável" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="null">Ninguém (Não atribuído)</SelectItem>
                            <SelectItem v-if="!usersToSelect || usersToSelect.length === 0 && !props.users" value="null" disabled>
                                Nenhum usuário disponível
                            </SelectItem>
                            <template v-else>
                                <SelectItem v-for="user in usersToSelect" :key="user.id" :value="String(user.id)">
                                {{ user.name }}
                                </SelectItem>
                            </template>
                        </SelectContent>
                    </Select>
                    <div v-if="form.errors.responsible_id" class="text-sm text-red-600 dark:text-red-400 mt-1">
                    {{ form.errors.responsible_id }}
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
                    />
                    <div v-if="form.errors.origin" class="text-sm text-red-600 dark:text-red-400 mt-1">
                        {{ form.errors.origin }}
                    </div>
                </div>
            </div>

            <fieldset class="border-t border-gray-200 dark:border-gray-700 pt-6 mt-6">
                <legend class="text-base font-medium text-gray-900 dark:text-gray-100 mb-4">Detalhes do Pagamento (Opcional)</legend>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                    <div>
                        <Label for="payment_amount" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Valor do Pagamento (R$)</Label>
                        <Input
                            id="payment_amount"
                            type="number"
                            step="0.01"
                            v-model.number="form.payment.amount"
                            class="mt-1 block w-full"
                            placeholder="Ex: 1500.50"
                        />
                        <div v-if="form.errors['payment.amount']" class="text-sm text-red-600 dark:text-red-400 mt-1">
                            {{ form.errors['payment.amount'] }}
                        </div>
                    </div>

                    <div>
                        <Label for="payment_method" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Método de Pagamento</Label>
                        <Select v-model="form.payment.method">
                            <SelectTrigger id="payment_method" class="mt-1 w-full">
                                <SelectValue placeholder="Selecione um método" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectItem value="null">Nenhum / Não Especificado</SelectItem>
                                <SelectItem v-if="!props.paymentMethods || props.paymentMethods.length === 0" value="null" disabled>
                                    Nenhum método configurado
                                </SelectItem>
                                <template v-else>
                                    <SelectItem v-for="method in props.paymentMethods" :key="method" :value="method">
                                        {{ method }}
                                    </SelectItem>
                                </template>
                            </SelectContent>
                        </Select>
                        <div v-if="form.errors['payment.method']" class="text-sm text-red-600 dark:text-red-400 mt-1">
                            {{ form.errors['payment.method'] }}
                        </div>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mt-6">
                    <div>
                        <Label for="payment_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Data do Pagamento</Label>
                        <Input
                            id="payment_date"
                            type="date"
                            v-model="form.payment.date"
                            class="mt-1 block w-full"
                        />
                        <div v-if="form.errors['payment.date']" class="text-sm text-red-600 dark:text-red-400 mt-1">
                            {{ form.errors['payment.date'] }}
                        </div>
                    </div>
                    <div>
                        <Label for="due_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Data de Vencimento do Caso</Label>
                        <Input
                            id="due_date"
                            type="date"
                            v-model="form.due_date"
                            class="mt-1 block w-full"
                        />
                        <div v-if="form.errors.due_date" class="text-sm text-red-600 dark:text-red-400 mt-1">
                            {{ form.errors.due_date }}
                        </div>
                    </div>
                </div>
                 <div class="mt-6">
                    <Label for="payment_notes" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Observações do Pagamento</Label>
                    <Textarea
                        id="payment_notes"
                        v-model="form.payment.notes"
                        rows="3"
                        class="mt-1 block w-full"
                        placeholder="Detalhes adicionais sobre o pagamento, condições, etc."
                    />
                    <div v-if="form.errors['payment.notes']" class="text-sm text-red-600 dark:text-red-400 mt-1">
                        {{ form.errors['payment.notes'] }}
                    </div>
                </div>
            </fieldset>

            <div class="mt-6"> 
              <Label for="priority" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Prioridade <span class="text-red-500">*</span></Label>
              <Select v-model="form.priority" required>
                <SelectTrigger id="priority" class="mt-1 w-full">
                  <SelectValue placeholder="Selecione a prioridade" />
                </SelectTrigger>
                <SelectContent>
                  <SelectItem value="low">Baixa</SelectItem>
                  <SelectItem value="medium">Média</SelectItem>
                  <SelectItem value="high">Alta</SelectItem>
                </SelectContent>
              </Select>
              <div v-if="form.errors.priority" class="text-sm text-red-600 dark:text-red-400 mt-1">
                {{ form.errors.priority }}
              </div>
            </div>


            <div class="flex justify-end space-x-3 pt-8">
              <Link :href="props.contact_id ? routeHelper('contacts.show', props.contact_id) : routeHelper('processes.index')">
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
