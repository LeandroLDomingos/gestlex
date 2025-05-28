<script setup lang="ts">
import { computed, watch, onMounted } from 'vue' // Adicionado onMounted para logs
import { Head, useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Select, SelectTrigger, SelectValue, SelectContent, SelectGroup, SelectLabel, SelectItem } from '@/components/ui/select'
import Label from '@/components/ui/label/Label.vue'
import Input from '@/components/ui/input/Input.vue'
import InputError from '@/components/InputError.vue'
import Button from '@/components/ui/button/Button.vue'
import type { BreadcrumbItem } from '@/types';

// Helper para Ziggy
const RGlobal = (window as any).route;
const route = (name?: string, params?: any, absolute?: boolean): string => {
  if (typeof RGlobal === 'function') {
    return RGlobal(name, params, absolute);
  }
  console.warn(`Helper de rota Ziggy não encontrado para a rota: ${name}. Usando fallback.`);
  let url = `/${name?.replace(/\./g, '/') || ''}`;
  if (params) {
    if (typeof params === 'object' && params !== null && !Array.isArray(params)) {
      Object.keys(params).forEach(key => {
        const paramPlaceholder = `:${key}`;
        const paramPlaceholderBraces = `{${key}}`;
        if (url.includes(paramPlaceholder)) {
          url = url.replace(paramPlaceholder, String(params[key]));
        } else if (url.includes(paramPlaceholderBraces)) {
          url = url.replace(paramPlaceholderBraces, String(params[key]));
        } else if (Object.keys(params).length === 1 && !url.includes(String(params[key]))) {
          url += `/${params[key]}`;
        }
      });
    } else if (typeof params !== 'object') {
      url += `/${params}`;
    }
  }
  return url;
};

const props = defineProps<{
    contact_id?: number | null,
    contact_name?: string | null,
    users: { id: number, name: string }[],
    contactsList: { id: number, name: string | null, business_name: string | null, type: string }[],
    availableWorkflows: { key: string, label: string }[],
    allStages: Record<string, { key: number, label: string }[]>, // key do estágio é number
    availableStatuses: { key: string, label: string }[],
    availablePriorities: { key: string, label: string }[],
    paymentMethods: string[],
    paymentTypes: { value: string, label: string }[],
}>()

const form = useForm({
    title: '',
    contact_id: props.contact_id || null,
    workflow: props.availableWorkflows?.[0]?.key || '', // key do workflow é string
    stage_id: null as number | null, // key do estágio é number, inicializado como null
    responsible_id: null as number | null,
    origin: '',
    priority: props.availablePriorities?.[0]?.key || '',
    status: props.availableStatuses?.[0]?.key || '',
    total_value: '',
    description: '',
});

// Log inicial dos props e do formulário
onMounted(() => {
    console.log('[onMounted] Props recebidos:', JSON.parse(JSON.stringify(props)));
    console.log('[onMounted] Estado inicial do formulário:', JSON.parse(JSON.stringify(form)));
    // O watch com immediate:true já terá sido executado uma vez aqui.
    // Vamos verificar o stage_id após a primeira execução do watch.
    console.log('[onMounted] form.workflow após init:', form.workflow);
    console.log('[onMounted] form.stage_id após init e primeiro watch:', form.stage_id, '(tipo:', typeof form.stage_id, ')');
});

const currentStages = computed(() => {
    console.log('[computed currentStages] form.workflow:', form.workflow);
    if (form.workflow && props.allStages && props.allStages[form.workflow]) {
        const stages = props.allStages[form.workflow];
        console.log('[computed currentStages] Estágios encontrados para workflow atual:', JSON.parse(JSON.stringify(stages)));
        return stages;
    }
    console.log('[computed currentStages] Nenhum estágio encontrado, retornando array vazio.');
    return [];
})

watch(() => form.workflow, (newWorkflow, oldWorkflow) => {
    console.log(`[WATCH form.workflow] Mudança de '${oldWorkflow}' para '${newWorkflow}'`);
    const stagesForNewWorkflow = newWorkflow && props.allStages && props.allStages[newWorkflow] ? props.allStages[newWorkflow] : [];
    console.log('[WATCH form.workflow] Estágios para o novo workflow (`${newWorkflow}`):', JSON.parse(JSON.stringify(stagesForNewWorkflow)));

    if (stagesForNewWorkflow && stagesForNewWorkflow.length > 0) {
        const firstStageKey = stagesForNewWorkflow[0].key; // This is a number
        form.stage_id = firstStageKey;
        console.log(`[WATCH form.workflow] form.stage_id DEFINIDO PARA: ${form.stage_id} (tipo: ${typeof form.stage_id})`);
    } else {
        form.stage_id = null;
        console.log('[WATCH form.workflow] form.stage_id DEFINIDO PARA NULL (nenhum estágio encontrado)');
    }
}, {
    immediate: true // Executa imediatamente na montagem do componente
})

// Adicionamos um watch para form.stage_id para ver quando ele é alterado e qual o seu valor
watch(() => form.stage_id, (newStageId, oldStageId) => {
    console.log(`[WATCH form.stage_id] Mudança de '${oldStageId}' para '${newStageId}' (tipo: ${typeof newStageId})`);
});


function submit() {
    console.log('Submetendo formulário:', JSON.parse(JSON.stringify(form.data())));
    form.post(route('processes.store'), { // Substitua 'processes.store' pela sua rota correta
        onSuccess: () => {
            console.log('Formulário submetido com sucesso!');
            // form.reset(); // Opcional
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
        value: contact.id,
        label: `${contact.name || contact.business_name || 'Nome não disponível'} (${contact.type === 'physical' ? 'PF' : 'PJ'})`
    }));
});

const responsibleOptions = computed(() => {
    return props.users.map(user => ({
        value: user.id,
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
            <div class="bg-white dark:bg-gray-800 p-6 rounded-lg shadow-xl space-y-6">
                <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-200">
                    Novo Caso {{ props.contact_name ? `para ${props.contact_name}` : '' }}
                </h1>
                <p class="text-gray-600 dark:text-gray-400">
                    Preencha os detalhes abaixo para criar um novo caso/processo.
                </p>

                <form @submit.prevent="submit" class="space-y-6">
                    <div>
                        <Label for="title">Título do Caso <span class="text-red-500">*</span></Label>
                        <Input id="title" v-model="form.title" required placeholder="Defina um título para o caso" />
                        <InputError :message="form.errors.title" />
                    </div>

                    <div>
                        <Label for="contact_id">Contato Principal <span class="text-red-500">*</span></Label>
                        <Select v-model="form.contact_id" :disabled="!!props.contact_id">
                             <SelectTrigger>
                                <SelectValue :placeholder="props.contact_name || 'Selecione um contato'" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectLabel>Contatos</SelectLabel>
                                    <SelectItem v-if="props.contact_id && props.contact_name" :value="props.contact_id">
                                        {{ props.contact_name }}
                                    </SelectItem>
                                    <template v-else>
                                         <SelectItem v-for="contact in contactOptions" :key="contact.value" :value="contact.value">
                                            {{ contact.label }}
                                        </SelectItem>
                                    </template>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <InputError :message="form.errors.contact_id" />
                    </div>

                     <div>
                        <Label for="description">Descrição</Label>
                        <textarea id="description" v-model="form.description" rows="3"
                            class="mt-1 block w-full rounded-md border-gray-300 shadow-sm focus:border-indigo-500 focus:ring-indigo-500 dark:bg-gray-700 dark:border-gray-600 dark:text-gray-200 dark:focus:ring-offset-gray-800 sm:text-sm"
                            placeholder="Detalhes sobre o caso, histórico, próximos passos..."></textarea>
                        <InputError :message="form.errors.description" />
                    </div>


                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <Label for="workflow">Workflow <span class="text-red-500">*</span></Label>
                            <Select v-model="form.workflow">
                                <SelectTrigger>
                                    <SelectValue placeholder="Selecione um workflow" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectLabel>Workflows Disponíveis</SelectLabel>
                                        <SelectItem v-for="workflowOpt in availableWorkflows" :key="workflowOpt.key" :value="workflowOpt.key">
                                            {{ workflowOpt.label }}
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.workflow" />
                        </div>

                        <div>
                            <Label for="stage">Estágio <span class="text-red-500">*</span></Label>
                            <Select
                                v-model="form.stage_id"
                                :disabled="!form.workflow || currentStages.length === 0"
                                :key="`stage-select-${form.workflow}-${form.stage_id}`"
                            >
                                <SelectTrigger>
                                    <SelectValue placeholder="Selecione um estágio" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectLabel>Estágios do Workflow</SelectLabel>
                                        <SelectItem v-for="stage in currentStages" :key="stage.key" :value="stage.key">
                                            {{ stage.label }}
                                        </SelectItem>
                                        <SelectItem v-if="form.workflow && currentStages.length === 0 && form.stage_id === null" :value="null" disabled>
                                            Nenhum estágio para este workflow
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.stage_id" />
                        </div>
                    </div>

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <Label for="responsible_id">Responsável</Label>
                             <Select v-model="form.responsible_id">
                                <SelectTrigger>
                                    <SelectValue placeholder="Selecione um responsável" />
                                </SelectTrigger>
                                <SelectContent>
                                    <SelectGroup>
                                        <SelectLabel>Usuários</SelectLabel>
                                        <SelectItem v-for="user in responsibleOptions" :key="user.value" :value="user.value">
                                            {{ user.label }}
                                        </SelectItem>
                                    </SelectGroup>
                                </SelectContent>
                            </Select>
                            <InputError :message="form.errors.responsible_id" />
                        </div>
                        <div>
                            <Label for="origin">Origem do Caso</Label>
                            <Input id="origin" v-model="form.origin" placeholder="Ex: Indicação, Website, Telefone" />
                            <InputError :message="form.errors.origin" />
                        </div>
                    </div>

                     <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <Label for="priority">Prioridade <span class="text-red-500">*</span></Label>
                            <Select v-model="form.priority">
                                <SelectTrigger>
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
                            <InputError :message="form.errors.priority" />
                        </div>
                        <div>
                            <Label for="status">Status <span class="text-red-500">*</span></Label>
                            <Select v-model="form.status">
                                <SelectTrigger>
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
                            <InputError :message="form.errors.status" />
                        </div>
                    </div>

                    <div class="pt-4">
                        <h3 class="text-lg font-medium text-gray-700 dark:text-gray-300">Detalhes Financeiros</h3>
                        <div>
                            <Label for="total_value">Valor Total do Contrato/Serviço (R$)</Label>
                            <Input id="total_value" v-model="form.total_value" type="text" placeholder="Ex: 3000.00 (deixe em branco se não aplicável)" />
                            <InputError :message="form.errors.total_value" />
                        </div>
                    </div>


                    <div class="flex justify-end space-x-3 pt-6">
                        <Button type="button" variant="outline" @click="() => router.visit(route('processes.index'))">
                            Cancelar
                        </Button>
                        <Button type="submit" :disabled="form.processing">
                            Salvar Caso
                        </Button>
                    </div>
                </form>
            </div>
        </div>
    </AppLayout>
</template>
