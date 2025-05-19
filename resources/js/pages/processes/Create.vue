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

// Props esperadas do controller
const props = defineProps<{
    contact_id?: number | string | null;
    contact_name?: string | null;
    users?: UserSelectItem[];
    contactsList?: ContactSelectItem[];
    availableWorkflows?: WorkflowOption[]; // Workflows formatados
    allStages?: Record<string, StageOption[]>; // Todos os estágios agrupados por workflow key
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
    title: '',
    description: '',
    contact_id: props.contact_id || null,
    responsible_id: null as (number | string | null), // Corrigido para responsible_id
    workflow: null as (string | null), // Armazena a CHAVE do workflow (ex: 'prospecting')
    stage: null as (number | null), // Armazena a CHAVE do estágio (inteiro)
    due_date: '',
    priority: 'medium',
    origin: '',
    negotiated_value: null as (number | null),
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
    // Para PJ, pode ser útil mostrar Nome Fantasia e Razão Social se diferentes
    let displayName = contact.name || 'Empresa sem Nome Fantasia';
    if (contact.business_name && contact.business_name !== contact.name) {
        displayName += ` (${contact.business_name})`;
    }
    return `${displayName} (PJ)`;
}

const usersToSelect = computed(() => props.users || []);

// Estágios disponíveis baseados no workflow selecionado
const currentStages = computed<StageOption[]>(() => {
    if (form.workflow && props.allStages && props.allStages[form.workflow]) {
        return props.allStages[form.workflow];
    }
    return [];
});

// Observa mudanças no workflow para resetar o estágio
watch(() => form.workflow, (newWorkflowSelected, oldWorkflowSelected) => {
    // Só reseta se o workflow realmente mudou para um valor diferente (ou de/para null)
    if (newWorkflowSelected !== oldWorkflowSelected) {
        form.stage = null;
        form.clearErrors('stage'); // Limpa erros do estágio anterior
    }
    // Opcional: auto-selecionar o primeiro estágio se houver estágios para o novo workflow
    // if (newWorkflowSelected && currentStages.value.length > 0) {
    //     form.stage = currentStages.value[0].key;
    // }
});


function submitProcess() {
    const dataToSubmit = {
        ...form.data(),
        responsible_id: form.responsible_id === 'null' ? null : form.responsible_id, // Trata o valor 'null' do select
        // Garante que contact_id seja enviado como null se não selecionado
        contact_id: form.contact_id === 'null' || form.contact_id === '' ? null : form.contact_id,
    };

    form.transform(() => dataToSubmit)
        .post(routeHelper('processes.store'), {
        onSuccess: () => {
            // O backend deve redirecionar, o Inertia tratará a atualização da página.
            // Ex: return redirect()->route('processes.show', $process->id)->with('success', 'Caso criado!');
        },
        onError: (formErrors) => {
            console.error('Erro ao criar caso:', formErrors);
            // Os erros de validação serão automaticamente populados em form.errors
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
              <Select v-model="form.contact_id" :disabled="!!props.contact_id" required> <SelectTrigger id="contact_id" class="mt-1 w-full">
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
                    <Select v-model="form.responsible_id"> <SelectTrigger id="responsible_user_id" class="mt-1 w-full">
                            <SelectValue placeholder="Selecione um responsável" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="null">Ninguém (Não atribuído)</SelectItem> <SelectItem v-if="!usersToSelect || usersToSelect.length === 0 && !props.users" value="null" disabled>
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
             <div>
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


            <div class="flex justify-end space-x-3 pt-4">
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
