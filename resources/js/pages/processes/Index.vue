<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Pagination from '@/components/Pagination.vue';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import { Search, PlusCircle, ChevronDown, Filter, ListFilter } from 'lucide-vue-next'; // Adicionado Filter, ListFilter, ChevronDown
import type { BreadcrumbItem } from '@/types';
import type { PaginatedResponse } from '@/types/inertia';

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
                    const paramValueString = String(params[key]);
                    if (url.split('/').pop() !== paramValueString) {
                        url += `/${paramValueString}`;
                    }
                }
            });
        } else if (typeof params !== 'object') {
             url += `/${params}`;
        }
    }
    return url;
};

// Tipos específicos para Processos
interface User {
    id: number | string;
    name: string;
}
// Supondo que um processo pode ter um contato associado
interface RelatedContact {
    id: number | string;
    name: string; // Ou o campo relevante para exibir o nome do contato
}
interface Process {
    id: string;
    title?: string; // Título pode ser opcional se as colunas mudarem
    pendencies?: string | number | null; // Ex: "3 pendências" ou número
    contact: RelatedContact | null; // Contato associado ao processo
    responsible: User | null;
    status: string | null; // Situação
    last_update: string; // Última atualização (data)
    tags: string[] | null; // Array de tags
    workflow: 'prospecting' | 'consultative' | 'administrative' | 'judicial'; // Mantido para filtro
    stage_name?: string; // Nome do estágio atual
    // Outros campos que você possa precisar
    origin?: string | null;
    negotiated_value?: number | string | null;
    created_at: string;
}

interface ProcessIndexProps {
    processes: PaginatedResponse<Process>;
    filters?: {
        search?: string;
        workflow?: Process['workflow'];
        stage?: string; // Ou número, dependendo de como os estágios são identificados
    };
    sortBy?: string;
    sortDirection?: 'asc' | 'desc';
    // Dados para os fluxos e estágios (viriam do backend ou seriam definidos aqui)
    workflows?: { key: Process['workflow']; label: string; count: number; stages: {key: string; label: string}[] }[];
}

const props = withDefaults(defineProps<ProcessIndexProps>(), {
    filters: () => ({}),
    workflows: () => [ // Dados de exemplo para workflows e estágios
        { key: 'prospecting', label: 'Prospecção', count: 0, stages: [
            { key: 'initial_contact', label: 'Contato inicial'},
            { key: 'document_collection', label: 'Coleta documental'},
            { key: 'legal_assessment', label: 'Avaliação jurídica'},
            { key: 'proposal_submission', label: 'Envio de proposta'},
            { key: 'negotiation', label: 'Negociação'},
        ]},
        { key: 'consultative', label: 'Consultivo', count: 0, stages: [
            { key: 'briefing', label: 'Briefing'},
            { key: 'analysis', label: 'Análise'},
            { key: 'opinion', label: 'Parecer'},
        ]},
        { key: 'administrative', label: 'Administrativo', count: 19, stages: [ /* ... */ ]},
        { key: 'judicial', label: 'Judicial', count: 2, stages: [ /* ... */ ]},
    ]
});

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Painel', href: route('dashboard') },
    { title: 'Casos', href: route('processes.index') }, // Mudado para "Casos"
];

const searchTerm = ref(props.filters?.search || '');
const activeWorkflow = ref<Process['workflow'] | null>(props.filters?.workflow || props.workflows?.[0]?.key || null);
const activeStage = ref<string | null>(props.filters?.stage || null);

const page = usePage();
// A ordenação padrão agora é por "Contato" (nome do contato associado) ou "Última atualização"
const sortColumn = ref<string>(props.sortBy || (page.props.ziggy?.query?.sort_by as string) || 'last_update');
const sortDirection = ref<'asc' | 'desc'>((props.sortDirection || (page.props.ziggy?.query?.sort_direction as 'asc' | 'desc') || 'desc'));

// Colunas ordenáveis (chave do frontend -> nome da coluna no backend)
// Ajustar para as novas colunas. Ordenar por "Contato" (nome) ou "Responsável" (nome) exigirá joins no backend.
const sortableColumns = {
    // 'pendencies': 'pendencies_count', // Exemplo, se pendencies for um contador
    contact_name: 'contact.name', // Exemplo, se o backend suportar ordenação por relacionamento
    responsible_name: 'responsible.name', // Exemplo
    status: 'status',
    last_update: 'last_update',
    // 'tag': 'tags', // Ordenar por tags pode ser complexo
} as const;

type SortableColumnKey = keyof typeof sortableColumns | string; // Permitir string para colunas não diretamente em sortableColumns

// --- FUNÇÕES AUXILIARES ---
const displayValue = (value: any, fallback: string = 'N/A') => {
    if (value === null || value === undefined || value === '' || (Array.isArray(value) && value.length === 0)) {
        return fallback;
    }
    if (Array.isArray(value)) return value.join(', ');
    return value;
};

const formatDateForTable = (dateString: string | null | undefined): string => {
    if (!dateString) return 'N/A';
    try {
        const date = new Date(dateString.includes('T') ? dateString : dateString + 'T00:00:00Z');
        return date.toLocaleDateString('pt-BR', {
            day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit'
        });
    } catch (e) {
        return dateString;
    }
};
// --- FIM DAS FUNÇÕES AUXILIARES ---

const currentStages = computed(() => {
    const wf = props.workflows?.find(w => w.key === activeWorkflow.value);
    return wf ? wf.stages : [];
});

function selectWorkflow(workflowKey: Process['workflow']) {
    activeWorkflow.value = workflowKey;
    activeStage.value = null; // Resetar estágio ao mudar workflow
    applyAllFilters();
}

function selectStage(stageKey: string | null) {
    activeStage.value = stageKey;
    applyAllFilters();
}

const handleSort = (columnKey: SortableColumnKey) => {
    const backendColumnName = (sortableColumns as Record<string, string>)[columnKey] || columnKey;
    if (sortColumn.value === backendColumnName) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortColumn.value = backendColumnName;
        sortDirection.value = 'asc';
    }
    applyAllFilters();
};

const applyAllFilters = () => {
    const queryParams: Record<string, string | number | undefined> = {
        ...(page.props.ziggy?.query || {}),
        sort_by: sortColumn.value,
        sort_direction: sortDirection.value,
        search: searchTerm.value || undefined,
        workflow: activeWorkflow.value || undefined,
        stage: activeStage.value || undefined,
    };
    delete queryParams.page;

    Object.keys(queryParams).forEach(key => {
        if (queryParams[key] === undefined || queryParams[key] === '') {
            delete queryParams[key];
        }
    });

    router.get(
        route('processes.index'),
        queryParams as any,
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    );
};

let searchTimeout: number | undefined;
watch(searchTerm, () => {
    clearTimeout(searchTimeout);
    searchTimeout = window.setTimeout(() => {
        applyAllFilters();
    }, 300);
});

// Definição dos cabeçalhos da tabela conforme a imagem
const tableHeaders: { key: string; label: string; sortable: boolean, class?: string }[] = [
    { key: 'pendencies', label: 'Pendências', sortable: true, class: 'w-[15%]' },
    { key: 'contact', label: 'Contato', sortable: true, class: 'w-[25%]' }, // Ordenar por contact.name
    { key: 'responsible', label: 'Responsável', sortable: true, class: 'w-[15%]' }, // Ordenar por responsible.name
    { key: 'status', label: 'Situação', sortable: true, class: 'w-[15%]' },
    { key: 'last_update', label: 'Última atualização', sortable: true, class: 'w-[20%]' },
    { key: 'tags', label: 'Tag', sortable: false, class: 'w-[10%]' }, // Ordenar por tags é complexo
];

</script>

<template>
    <Head title="Casos" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full">
            <aside class="w-64 bg-gray-50 dark:bg-gray-800 p-4 space-y-2 border-r dark:border-gray-700 flex-shrink-0">
                <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider px-2">Fluxos</h2>
                <Button
                    v-for="wf in props.workflows"
                    :key="wf.key"
                    @click="selectWorkflow(wf.key)"
                    :variant="activeWorkflow === wf.key ? 'default' : 'ghost'"
                    class="w-full justify-start"
                >
                    {{ wf.label }}
                    <span class="ml-auto text-xs bg-gray-200 dark:bg-gray-700 px-1.5 py-0.5 rounded-full">{{ wf.count }}</span>
                </Button>
            </aside>

            <main class="flex-1 p-6 space-y-6 overflow-y-auto">
                <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                    <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-100">
                        Casos
                    </h1>
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <div class="relative flex-grow sm:flex-grow-0">
                            <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 dark:text-gray-500" />
                            <Input type="text" v-model="searchTerm" placeholder="Buscar casos..."
                                class="block w-full sm:w-64 pl-10 pr-3 py-2" />
                        </div>
                        <Link :href="route('processes.create')">
                            <Button variant="default" size="default">
                                <PlusCircle class="mr-2 h-4 w-4" />
                                Novo Caso
                            </Button>
                        </Link>
                    </div>
                </div>

                <div v-if="activeWorkflow && currentStages.length" class="bg-white dark:bg-gray-800 p-3 rounded-md shadow flex space-x-2 overflow-x-auto">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300 self-center mr-2 whitespace-nowrap">
                        Fluxo {{ props.workflows?.find(w => w.key === activeWorkflow)?.label }}:
                    </span>
                    <Button
                        v-for="stage in currentStages"
                        :key="stage.key"
                        @click="selectStage(stage.key)"
                        :variant="activeStage === stage.key ? 'secondary' : 'outline'"
                        size="sm"
                        class="whitespace-nowrap"
                    >
                        {{ stage.label }}
                    </Button>
                </div>

                <div class="flex justify-between items-center mb-0">
                    <div>
                        <Button variant="ghost" size="sm">
                            <ListFilter class="h-4 w-4 mr-2" />
                            Filtros
                        </Button>
                    </div>
                    <div>
                        <span class="text-sm text-gray-600 dark:text-gray-400">Ordenar por: </span>
                        <select
                            v-model="sortColumn"
                            @change="applyAllFilters"
                            class="ml-1 text-sm p-1 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 rounded-md focus:ring-indigo-500 focus:border-indigo-500"
                        >
                            <option value="contact.name">Contato</option>
                            <option value="last_update">Última Atualização</option>
                            <option value="status">Situação</option>
                            </select>
                        <Button variant="ghost" size="icon" @click="sortDirection = sortDirection === 'asc' ? 'desc' : 'asc'; applyAllFilters();" class="ml-1">
                            <ArrowUpDown class="h-4 w-4" :class="{'transform rotate-180': sortDirection === 'desc'}" />
                        </Button>
                    </div>
                </div>


                <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg overflow-hidden">
                    <div class="overflow-x-auto">
                        <Table class="min-w-full">
                            <TableHeader class="bg-gray-50 dark:bg-gray-700/50">
                                <TableRow>
                                    <TableHead v-for="header in tableHeaders" :key="header.key"
                                        @click="header.sortable ? handleSort(header.key) : null"
                                        :class="[
                                            'px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider',
                                            header.sortable ? 'cursor-pointer select-none hover:bg-gray-100 dark:hover:bg-gray-600/50 transition-colors duration-150 group' : '',
                                            header.class
                                        ]">
                                        {{ header.label }}
                                        <ArrowUpDown v-if="header.sortable && sortColumn === (sortableColumns[header.key as SortableColumnKey] || header.key)" class="inline h-4 w-4 ml-1 align-middle" :class="{'transform rotate-180': sortDirection === 'desc'}" />
                                        <ArrowUpDown v-else-if="header.sortable" class="inline h-4 w-4 ml-1 align-middle opacity-30 group-hover:opacity-70" />
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <template v-if="props.processes && props.processes.data.length">
                                    <Link as="tr" v-for="process in props.processes.data" :key="process.id"
                                        :href="route('processes.show', process.id)"
                                        class="hover:bg-gray-50 dark:hover:bg-gray-700/30 cursor-pointer transition-colors duration-150">
                                        
                                        <TableCell class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ displayValue(process.pendencies, 'Nenhuma') }}
                                        </TableCell>
                                        <TableCell class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {{ displayValue(process.contact?.name) }}
                                        </TableCell>
                                        <TableCell class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ displayValue(process.responsible?.name) }}
                                        </TableCell>
                                        <TableCell class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ displayValue(process.status) }}
                                        </TableCell>
                                        <TableCell class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ formatDateForTable(process.last_update) }}
                                        </TableCell>
                                        <TableCell class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            <span v-for="(tag, index) in process.tags" :key="index"
                                                  class="mr-1 mb-1 inline-block px-2 py-0.5 text-xs font-medium bg-blue-100 text-blue-800 dark:bg-blue-900 dark:text-blue-200 rounded-full">
                                                {{ tag }}
                                            </span>
                                            <span v-if="!process.tags || process.tags.length === 0">N/A</span>
                                        </TableCell>
                                    </Link>
                                </template>
                                <TableRow v-else>
                                    <TableCell :colspan="tableHeaders.length" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center justify-center text-gray-500 dark:text-gray-400">
                                            <Search class="mb-2 h-12 w-12 opacity-50" />
                                            <p class="text-lg font-medium">Nenhum caso encontrado.</p>
                                            <p class="text-sm">
                                                {{ searchTerm || activeWorkflow || activeStage ? 'Tente refinar seus filtros ou ' : 'Você pode ' }}
                                                <Link v-if="searchTerm || activeWorkflow || activeStage" :href="route('processes.index')"
                                                    @click="searchTerm = ''; activeWorkflow = null; activeStage = null; applyAllFilters();"
                                                    class="text-indigo-600 dark:text-indigo-400 hover:underline">limpar os filtros
                                                </Link>
                                                {{ searchTerm || activeWorkflow || activeStage ? ' para ver todos os casos, ou ' : '' }}
                                                <Link :href="route('processes.create')" class="text-indigo-600 dark:text-indigo-400 hover:underline">
                                                    crie um novo caso
                                                </Link>.
                                            </p>
                                        </div>
                                    </TableCell>
                                </TableRow>
                            </TableBody>
                        </Table>
                    </div>
                </div>

                <Pagination
                    v-if="props.processes && props.processes.data.length > 0 && props.processes.links.length > 3"
                    :links="props.processes.links"
                    class="mt-6"
                />
            </main>
        </div>
    </AppLayout>
</template>

<style scoped>
.select-none {
    user-select: none;
}
</style>
