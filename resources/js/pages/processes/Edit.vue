<script setup lang="ts">
import { ref, computed, watch, onMounted } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
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
    key: string;
    label: string;
}

interface StageOption {
    key: number;
    label: string;
}

interface SelectOption { // Para Status e Prioridade
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
// Deve corresponder à estrutura do seu modelo Process no backend, incluindo os acessores
interface ProcessData {
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
    negotiated_value: number | null;
    // Adicione os labels se eles vierem do backend como parte do objeto process
    workflow_label?: string;
    stage_label?: string;
    priority_label?: string;
    status_label?: string;
    contact?: RelatedContact; // Para exibir o nome do contato
    // Outros campos...
}

interface RelatedContact { // Já definido no seu Show.vue, mas repetindo para clareza
    id: number | string;
    name: string;
    business_name?: string;
    type?: 'physical' | 'legal';
}


// Props esperadas do controller
const props = defineProps<{
    process: ProcessData; // O processo a ser editado
    users?: UserSelectItem[];
    contactsList?: ContactSelectItem[];
    availableWorkflows?: WorkflowOption[];
    allStages?: Record<string, StageOption[]>; // Todos os estágios agrupados por workflow key
    statusesForForm?: SelectOption[]; // Nome da prop vinda do controller
    prioritiesForForm?: SelectOption[]; // Nome da prop vinda do controller
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

const form = useForm({
    title: props.process.title || '',
    description: props.process.description || '',
    contact_id: String(props.process.contact_id), // Garante que seja string para o Select
    responsible_id: props.process.responsible_id ? String(props.process.responsible_id) : null,
    workflow: props.process.workflow,
    stage: props.process.stage, // Deve ser a chave numérica
    due_date: props.process.due_date ? props.process.due_date.substring(0,10) : '', // Formato YYYY-MM-DD para input date
    priority: props.process.priority || 'medium',
    status: props.process.status || 'Aberto',
    origin: props.process.origin || '',
    negotiated_value: props.process.negotiated_value || null,
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
        // Apenas reseta o estágio se o novo workflow for diferente e não for o inicial
        // Se o formulário está sendo inicializado, props.process.stage já deve estar correto
        if (oldWorkflowSelected !== null) { // Evita resetar na carga inicial se workflow já estiver definido
             form.stage = null;
        }
        form.clearErrors('stage');
    }
});

// Garante que o estágio inicial seja definido corretamente se o workflow já estiver preenchido
onMounted(() => {
    if (form.workflow && props.process.stage) {
        // Verifica se o estágio atual é válido para o workflow atual
        const stagesForCurrentWorkflow = props.allStages && props.allStages[form.workflow] 
            ? props.allStages[form.workflow] 
            : [];
        if (!stagesForCurrentWorkflow.some(s => s.key === form.stage)) {
            form.stage = null; // Reseta se o estágio não for válido para o workflow
        }
    }
});


function submitProcess() {
    const dataToSubmit = {
        ...form.data(),
        responsible_id: form.responsible_id === 'null' ? null : form.responsible_id,
        contact_id: form.contact_id === 'null' || form.contact_id === '' ? null : form.contact_id,
        status: form.status === 'null' ? null : form.status,
        priority: form.priority === 'null' ? null : form.priority,
    };

    form.transform(() => dataToSubmit)
        .put(routeHelper('processes.update', props.process.id), { // Usar PUT para update
        onSuccess: () => {
            // O backend deve redirecionar para processes.show
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
      <Card class="max-w-3xl mx-auto">
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
              />
              <div v-if="form.errors.title" class="text-sm text-red-600 dark:text-red-400 mt-1">
                {{ form.errors.title }}
              </div>
            </div>

            <div>
              <Label for="contact_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Contato Principal <span class="text-red-500">*</span></Label>
              <Select v-model="form.contact_id" required>
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
                            <SelectItem v-for="st in currentStages" :key="st.key" :value="st.key">
                                {{ st.label }}
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
                            <SelectItem v-if="!usersToSelect || usersToSelect.length === 0" value="null" disabled>
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

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                 <div>
                    <Label for="negotiated_value" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Valor Negociado (R$)</Label>
                    <Input
                        id="negotiated_value"
                        type="number"
                        step="0.01"
                        v-model.number="form.negotiated_value"
                        class="mt-1 block w-full"
                        placeholder="Ex: 1500.50"
                    />
                    <div v-if="form.errors.negotiated_value" class="text-sm text-red-600 dark:text-red-400 mt-1">
                        {{ form.errors.negotiated_value }}
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
                    <div v-if="form.errors.due_date" class="text-sm text-red-600 dark:text-red-400 mt-1">
                        {{ form.errors.due_date }}
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
                             <SelectItem v-if="!prioritiesToSelect || prioritiesToSelect.length === 0" value="null" disabled>
                                Nenhuma prioridade disponível
                            </SelectItem>
                            <template v-else>
                                <SelectItem v-for="prio in prioritiesToSelect" :key="prio.key" :value="prio.key">
                                    {{ prio.label }}
                                </SelectItem>
                            </template>
                        </SelectContent>
                    </Select>
                    <div v-if="form.errors.priority" class="text-sm text-red-600 dark:text-red-400 mt-1">
                        {{ form.errors.priority }}
                    </div>
                </div>
                 <div>
                    <Label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300">Status do Caso</Label>
                    <Select v-model="form.status">
                        <SelectTrigger id="status" class="mt-1 w-full">
                            <SelectValue placeholder="Selecione um status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem v-if="!statusesToSelect || statusesToSelect.length === 0" value="null" disabled>
                                Nenhum status disponível
                            </SelectItem>
                            <template v-else>
                                <SelectItem v-for="stat in statusesToSelect" :key="stat.key" :value="stat.key">
                                    {{ stat.label }}
                                </SelectItem>
                            </template>
                        </SelectContent>
                    </Select>
                    <div v-if="form.errors.status" class="text-sm text-red-600 dark:text-red-400 mt-1">
                        {{ form.errors.status }}
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
