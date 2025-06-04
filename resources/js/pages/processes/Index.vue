<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { Head, Link, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import Pagination from '@/components/Pagination.vue';
import { Input } from '@/components/ui/input';
import { Button } from '@/components/ui/button';
import { Separator } from '@/components/ui/separator';
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue, SelectGroup } from '@/components/ui/select';
import { Label } from '@/components/ui/label';
import { Badge } from '@/components/ui/badge';

import { Search, PlusCircle, ChevronDown, Filter, ListFilter, ArrowUpDown, SlidersHorizontal, X, CalendarIcon, Archive as ArchiveIcon, AlertTriangle, DollarSign } from 'lucide-vue-next';
import type { BreadcrumbItem } from '@/types';
import type { PaginatedResponse } from '@/types/inertia';

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

interface User {
    id: number | string;
    name: string;
}
interface RelatedContact {
    id: number | string;
    name: string;
    business_name?: string;
    type?: 'physical' | 'legal';
}

interface Process {
    id: string;
    title?: string;
    pending_tasks_count: number;
    contact: RelatedContact | null;
    responsible: User | null;
    status: string | null;
    status_label?: string;
    updated_at: string;
    tags: string[] | null;
    workflow: string;
    workflow_label?: string;
    stage: number;
    stage_label?: string;
    origin?: string | null;
    payments_sum_total_amount?: number | string | null; // ATUALIZADO AQUI
    created_at: string;
    priority?: 'low' | 'medium' | 'high';
    priority_label?: string;
    archived_at?: string | null;
}

interface WorkflowData {
    key: string;
    label: string;
    count: number;
    stages: { key: number; label: string }[];
}

interface SelectOption {
    key: string | number;
    label: string;
}

interface ProcessIndexProps {
    processes: PaginatedResponse<Process>;
    filters?: {
        search?: string;
        workflow?: Process['workflow'];
        stage?: number;
        responsible_id?: string | number;
        priority?: string;
        status?: string;
        date_from?: string;
        date_to?: string;
        archived?: string | boolean;
    };
    workflows?: WorkflowData[];
    currentWorkflowStages?: { key: number; label: string }[];
    allProcessesCount?: number;
    archivedProcessesCount?: number;
    usersForFilter?: User[];
    statusesForFilter?: SelectOption[];
    prioritiesForFilter?: SelectOption[];
}

const props = defineProps<ProcessIndexProps>();

const breadcrumbs: BreadcrumbItem[] = [
    
    { title: 'Casos', href: route('processes.index') },
];

const page = usePage();
const initialFilters = props.filters || {};

const searchTerm = ref(initialFilters.search || '');
const activeWorkflow = ref<string | null>(initialFilters.workflow || null);
const activeStage = ref<number | null>(initialFilters.stage || null);

const filterByResponsible = ref<string | null>(initialFilters.responsible_id ? String(initialFilters.responsible_id) : null);
const filterByPriority = ref<string | null>(initialFilters.priority || null);
const filterByStatus = ref<string | null>(initialFilters.status || null);
const filterByDateFrom = ref<string | null>(initialFilters.date_from || null);
const filterByDateTo = ref<string | null>(initialFilters.date_to || null);
const isShowingArchived = ref(initialFilters.archived === 'true' || initialFilters.archived === true);


const initialSortBy = (page.props.ziggy?.query?.sort_by as string) || 'updated_at';
const initialSortDirection = (page.props.ziggy?.query?.sort_direction as 'asc' | 'desc') || 'desc';

const sortColumn = ref<string>(initialSortBy);
const sortDirection = ref<'asc' | 'desc'>(initialSortDirection);

const sortableColumns: Record<string, string> = {
    pending_tasks_count: 'pending_tasks_count',
    contact_name: 'contact.name',
    responsible_name: 'responsible.name',
    stage: 'stage',
    status: 'status',
    updated_at: 'updated_at',
    title: 'title',
    workflow: 'workflow',
    priority: 'priority',
    payments_sum_total_amount: 'payments_sum_total_amount', // ATUALIZADO AQUI
    created_at: 'created_at',
};

type SortableColumnKey = keyof typeof sortableColumns | string;

const displayValue = (value: any, fallback: string = 'N/A') => {
    if (value === null || value === undefined || value === '' || (Array.isArray(value) && value.length === 0)) {
        return fallback;
    }
    if (Array.isArray(value)) return value.join(', ');
    return String(value);
};

const formatDateForTable = (dateString: string | null | undefined): string => {
    if (!dateString) return 'N/A';
    try {
        const date = new Date(dateString.includes('T') || dateString.includes('Z') ? dateString : dateString.replace(/-/g, '/') + ' GMT');
        if (isNaN(date.getTime())) return dateString; 

        return date.toLocaleDateString('pt-BR', {
            day: '2-digit', month: '2-digit', year: 'numeric',
        });
    } catch (e) { return dateString; }
};

const formatCurrency = (value: number | string | null | undefined): string => {
    const numericValue = typeof value === 'string' ? parseFloat(value) : value;
    if (numericValue === null || numericValue === undefined || isNaN(numericValue)) {
        return '---';
    }
    return new Intl.NumberFormat('pt-BR', {
        style: 'currency',
        currency: 'BRL',
    }).format(numericValue);
};

const currentStagesForFilter = computed(() => {
    if (activeWorkflow.value && props.workflows) {
        const wf = props.workflows.find(w => w.key === activeWorkflow.value);
        return wf ? wf.stages : [];
    }
    return props.currentWorkflowStages || [];
});

const getPriorityVariant = (priorityValue?: 'low' | 'medium' | 'high' | null): 'destructive' | 'secondary' | 'outline' | 'default' => {
    if (!priorityValue) return 'outline';
    switch (priorityValue.toLowerCase()) {
        case 'high': return 'destructive';
        case 'medium': return 'secondary';
        case 'low': return 'outline';
        default: return 'outline';
    }
};

function selectAllCases() {
    activeWorkflow.value = null;
    activeStage.value = null;
    isShowingArchived.value = false;
    applyAllFilters();
}

function selectWorkflow(workflowKey: string) {
    if (activeWorkflow.value === workflowKey && !isShowingArchived.value) {
        activeWorkflow.value = null;
        activeStage.value = null;
    } else {
        activeWorkflow.value = workflowKey;
        activeStage.value = null;
    }
    isShowingArchived.value = false;
    applyAllFilters();
}

function selectArchivedCases() {
    activeWorkflow.value = null;
    activeStage.value = null;
    isShowingArchived.value = true;
    applyAllFilters();
}


function selectStage(stageKey: number | null) {
    activeStage.value = stageKey;
    applyAllFilters();
}

const handleSort = (columnKey: SortableColumnKey) => {
    const backendColumnName = sortableColumns[columnKey] || columnKey;
    if (sortColumn.value === backendColumnName) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc';
    } else {
        sortColumn.value = backendColumnName;
        sortDirection.value = 'asc';
    }
    applyAllFilters();
};

const applyAllFilters = () => {
    const queryParams: { [key: string]: string | number | boolean | undefined | null } = { // Permitir null
        sort_by: sortColumn.value,
        sort_direction: sortDirection.value,
    };

    if (searchTerm.value) queryParams.search = searchTerm.value;
    
    if (isShowingArchived.value) {
        queryParams.archived = true;
        // Limpa filtros de workflow e estágio ao mostrar arquivados, se desejado
        // queryParams.workflow = null; 
        // queryParams.stage = null;
    } else {
        queryParams.archived = undefined; // ou false, dependendo de como o backend trata
        if (activeWorkflow.value) queryParams.workflow = activeWorkflow.value;
        if (activeStage.value !== null) queryParams.stage = activeStage.value;
    }

    if (filterByResponsible.value && filterByResponsible.value !== 'null') {
        queryParams.responsible_id = filterByResponsible.value;
    }
    if (filterByPriority.value && filterByPriority.value !== 'null') {
        queryParams.priority = filterByPriority.value;
    }
    if (filterByStatus.value && filterByStatus.value !== 'null') {
        queryParams.status = filterByStatus.value;
    }
    if (filterByDateFrom.value) {
        queryParams.date_from = filterByDateFrom.value;
    }
    if (filterByDateTo.value) {
        queryParams.date_to = filterByDateTo.value;
    }
    
    // Remove chaves com valor null ou undefined para não poluir a URL e usar os defaults do backend
    Object.keys(queryParams).forEach(key => (queryParams[key as keyof typeof queryParams] == null) && delete queryParams[key as keyof typeof queryParams]);


    router.get(route('processes.index'), queryParams as any, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
};

function resetAdvancedFilters() {
    filterByResponsible.value = null;
    filterByPriority.value = null;
    filterByStatus.value = null;
    filterByDateFrom.value = null;
    filterByDateTo.value = null;
    applyAllFilters(); // Aplica para limpar os filtros na URL também
}

let searchTimeout: number | undefined;
watch(searchTerm, () => {
    clearTimeout(searchTimeout);
    searchTimeout = window.setTimeout(() => {
        applyAllFilters();
    }, 300);
});

const tableHeaders: { key: string; label: string; sortable: boolean, class?: string }[] = [
    { key: 'title', label: 'Título do Caso', sortable: true, class: 'w-[20%]' },
    { key: 'pending_tasks_count', label: 'Pendências', sortable: true, class: 'w-[10%] text-center' },
    { key: 'contact_name', label: 'Contato', sortable: true, class: 'w-[15%]' },
    { key: 'responsible_name', label: 'Responsável', sortable: true, class: 'w-[15%]' },
    { key: 'stage', label: 'Estágio', sortable: true, class: 'w-[10%]' },
    { key: 'payments_sum_total_amount', label: 'Valor Total', sortable: true, class: 'w-[10%] text-right' }, // ATUALIZADO AQUI
    { key: 'updated_at', label: 'Última atualização', sortable: true, class: 'w-[10%]' },
    { key: 'status', label: 'Status', sortable: true, class: 'w-[10%] text-center' },
];

</script>

<template>
    <Head title="Casos" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex h-full">
            <aside class="w-72 bg-gray-50 dark:bg-gray-800 p-4 space-y-1 border-r dark:border-gray-700 flex-shrink-0 overflow-y-auto">
                <h2 class="text-sm font-semibold text-gray-500 dark:text-gray-400 uppercase tracking-wider px-2 mb-2">Filtros</h2>
                <Button
                    @click="selectAllCases"
                    :variant="!activeWorkflow && !isShowingArchived ? 'default' : 'ghost'"
                    class="w-full justify-between text-sm h-9 mb-1"
                >
                    <span>Todos os Ativos</span>
                    <span
                        :class="[
                            'ml-auto text-xs px-1.5 py-0.5 rounded-full',
                            !activeWorkflow && !isShowingArchived
                                ? 'bg-white/20 text-white dark:bg-black/30 dark:text-gray-200'
                                : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200'
                        ]"
                    >
                        {{ props.allProcessesCount ?? '...' }}
                    </span>
                </Button>

                <Button
                    v-for="wf in props.workflows"
                    :key="wf.key"
                    @click="selectWorkflow(wf.key)"
                    :variant="activeWorkflow === wf.key && !isShowingArchived ? 'default' : 'ghost'"
                    class="w-full justify-between text-sm h-9 mb-1"
                >
                    <span>{{ wf.label }}</span>
                    <span
                        :class="[
                            'ml-auto text-xs px-1.5 py-0.5 rounded-full',
                            activeWorkflow === wf.key && !isShowingArchived
                                ? 'bg-white/20 text-white dark:bg-black/30 dark:text-gray-200'
                                : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200'
                        ]"
                    >
                        {{ wf.count }}
                    </span>
                </Button>
                <Separator class="my-2"/>
                 <Button
                    @click="selectArchivedCases"
                    :variant="isShowingArchived ? 'secondary' : 'ghost'"
                    class="w-full justify-between text-sm h-9 mb-1"
                >
                    <span>
                        <ArchiveIcon class="h-4 w-4 mr-2 inline-block" />
                        Arquivados
                    </span>
                    <span
                        :class="[
                            'ml-auto text-xs px-1.5 py-0.5 rounded-full',
                            isShowingArchived
                                ? 'bg-secondary-foreground/20 text-secondary-foreground dark:bg-secondary/30 dark:text-secondary-foreground'
                                : 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-200'
                        ]"
                    >
                        {{ props.archivedProcessesCount ?? '...' }}
                    </span>
                </Button>
            </aside>

            <main class="flex-1 p-6 space-y-6 overflow-y-auto">
                <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                    <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-100">
                        <span v-if="isShowingArchived">Casos Arquivados</span>
                        <span v-else>Casos Ativos</span>
                        <span v-if="activeWorkflow && props.workflows && !isShowingArchived" class="text-lg text-gray-600 dark:text-gray-400 font-normal ml-2">
                            ({{ props.workflows.find(w => w.key === activeWorkflow)?.label || activeWorkflow }})
                        </span>
                         <span v-if="activeStage && currentStagesForFilter.length && !isShowingArchived" class="text-lg text-gray-500 dark:text-gray-500 font-normal ml-1">
                            / {{ currentStagesForFilter.find(s => s.key == activeStage)?.label || activeStage }}
                        </span>
                    </h1>
                    <div class="flex items-center gap-2 w-full sm:w-auto">
                        <div class="relative flex-grow sm:flex-grow-0">
                            <Search class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 dark:text-gray-500" />
                            <Input type="text" v-model="searchTerm" placeholder="Buscar casos..."
                                class="block w-full sm:w-64 pl-10 pr-3 py-2 h-10" />
                        </div>
                        <Link :href="route('processes.create')">
                            <Button variant="default" size="default" class="h-10">
                                <PlusCircle class="mr-2 h-4 w-4" />
                                Novo Caso
                            </Button>
                        </Link>
                    </div>
                </div>

                <div v-if="activeWorkflow && currentStagesForFilter.length && !isShowingArchived" class="bg-white dark:bg-gray-800 p-2 rounded-md shadow flex items-center space-x-2 overflow-x-auto no-scrollbar">
                    <span class="text-sm font-medium text-gray-700 dark:text-gray-300 self-center mr-2 whitespace-nowrap pl-1">
                        Estágios:
                    </span>
                    <Button
                        v-for="stageItem in currentStagesForFilter"
                        :key="stageItem.key"
                        @click="selectStage(stageItem.key)"
                        :variant="activeStage === stageItem.key ? 'secondary' : 'ghost'"
                        size="sm"
                        class="whitespace-nowrap h-8 text-xs"
                    >
                        {{ stageItem.label }}
                    </Button>
                     <Button
                        v-if="activeStage !== null"
                        @click="selectStage(null)"
                        variant="ghost"
                        size="sm"
                        class="whitespace-nowrap h-8 text-xs text-muted-foreground hover:text-accent-foreground"
                        title="Limpar filtro de estágio"
                    >
                        <X class="h-3 w-3 mr-1" /> Limpar Estágio
                    </Button>
                </div>

                <div class="flex flex-col sm:flex-row justify-between items-center mb-0 -mt-2 gap-2">
                    <div>
                        <DropdownMenu>
                            <DropdownMenuTrigger as-child>
                                <Button variant="outline" size="sm" class="h-9 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-700">
                                    <SlidersHorizontal class="h-4 w-4 mr-2" />
                                    Filtros Avançados
                                    <ChevronDown class="h-4 w-4 ml-1 opacity-70" />
                                </Button>
                            </DropdownMenuTrigger>
                            <DropdownMenuContent class="w-80 p-3 space-y-3" align="start">
                                <DropdownMenuLabel>Filtros Adicionais</DropdownMenuLabel>
                                <DropdownMenuSeparator />
                                <div class="space-y-4 px-1">
                                    <div>
                                        <Label for="filterResponsible" class="text-xs font-medium mb-1 block">Responsável</Label>
                                        <Select v-model="filterByResponsible">
                                            <SelectTrigger id="filterResponsible" class="h-9">
                                                <SelectValue placeholder="Todos" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="null">Todos</SelectItem>
                                                <SelectItem v-for="user in props.usersForFilter" :key="user.id" :value="String(user.id)">
                                                    {{ user.name }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div>
                                        <Label for="filterPriority" class="text-xs font-medium mb-1 block">Prioridade</Label>
                                        <Select v-model="filterByPriority">
                                            <SelectTrigger id="filterPriority" class="h-9">
                                                <SelectValue placeholder="Todas" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="null">Todas</SelectItem>
                                                <SelectItem v-for="prio in props.prioritiesForFilter" :key="prio.key" :value="String(prio.key)">
                                                    {{ prio.label }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div>
                                        <Label for="filterStatus" class="text-xs font-medium mb-1 block">Status do Caso</Label>
                                        <Select v-model="filterByStatus">
                                            <SelectTrigger id="filterStatus" class="h-9">
                                                <SelectValue placeholder="Todos" />
                                            </SelectTrigger>
                                            <SelectContent>
                                                <SelectItem value="null">Todos</SelectItem>
                                                 <SelectItem v-for="stat in props.statusesForFilter" :key="stat.key" :value="String(stat.key)">
                                                    {{ stat.label }}
                                                </SelectItem>
                                            </SelectContent>
                                        </Select>
                                    </div>
                                    <div class="grid grid-cols-2 gap-3">
                                        <div>
                                            <Label for="filterDateFrom" class="text-xs font-medium mb-1 block">Criado de:</Label>
                                            <Input type="date" id="filterDateFrom" v-model="filterByDateFrom" class="h-9 text-sm"/>
                                        </div>
                                        <div>
                                            <Label for="filterDateTo" class="text-xs font-medium mb-1 block">Criado até:</Label>
                                            <Input type="date" id="filterDateTo" v-model="filterByDateTo" class="h-9 text-sm"/>
                                        </div>
                                    </div>
                                </div>
                                <DropdownMenuSeparator />
                                <div class="flex justify-end space-x-2 px-1 pt-2">
                                     <Button variant="ghost" size="sm" @click="resetAdvancedFilters" class="text-xs h-8">Limpar</Button>
                                     <DropdownMenuItem as-child>
                                        <Button @click="applyAllFilters()" size="sm" class="text-xs h-8">Aplicar Filtros</Button>
                                     </DropdownMenuItem>
                                </div>
                            </DropdownMenuContent>
                        </DropdownMenu>
                    </div>
                    <div class="flex items-center">
                        <span class="text-sm text-gray-600 dark:text-gray-400">Ordenar por: </span>
                        <select
                            v-model="sortColumn"
                            @change="applyAllFilters()"
                            class="ml-2 text-sm p-1.5 border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-200 rounded-md focus:ring-indigo-500 focus:border-indigo-500 h-9"
                        >
                            <option value="updated_at">Última Atualização</option>
                            <option value="created_at">Data de Criação</option>
                            <option value="contact.name">Contato</option>
                            <option value="responsible.name">Responsável</option>
                            <option value="stage">Estágio</option>
                            <option value="title">Título</option>
                            <option value="workflow">Workflow</option>
                            <option value="priority">Prioridade</option>
                            <option value="status">Status do Caso</option>
                            <option value="payments_sum_total_amount">Valor Total</option> <option value="pending_tasks_count">Pendências</option>
                        </select>
                        <Button variant="ghost" size="icon" @click="sortDirection = sortDirection === 'asc' ? 'desc' : 'asc'; applyAllFilters();" class="ml-1 h-9 w-9">
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
                                            'px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider',
                                            header.sortable ? 'cursor-pointer select-none hover:bg-gray-100 dark:hover:bg-gray-600/50 transition-colors duration-150 group' : '',
                                            header.class
                                        ]">
                                        {{ header.label }}
                                        <ArrowUpDown v-if="header.sortable && sortColumn === (sortableColumns[header.key as SortableColumnKey] || header.key)" class="inline h-3 w-3 ml-1 align-middle" :class="{'transform rotate-180': sortDirection === 'desc'}" />
                                        <ArrowUpDown v-else-if="header.sortable" class="inline h-3 w-3 ml-1 align-middle opacity-30 group-hover:opacity-70" />
                                    </TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody class="divide-y divide-gray-200 dark:divide-gray-700">
                                <template v-if="props.processes && props.processes.data.length">
                                    <Link as="tr" v-for="process_item in props.processes.data" :key="process_item.id"
                                        :href="route('processes.show', process_item.id)"
                                        class="hover:bg-gray-50 dark:hover:bg-gray-700/30 cursor-pointer transition-colors duration-150">
                                        
                                        <TableCell class="px-4 py-3 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                            {{ displayValue(process_item.title) }}
                                            <div v-if="process_item.workflow_label" class="text-xs text-gray-500 dark:text-gray-400">{{ process_item.workflow_label }}</div>
                                        </TableCell>
                                        <TableCell class="px-4 py-3 whitespace-nowrap text-sm text-center">
                                            <Link :href="route('processes.show', { process: process_item.id, tab: 'tasks' })"
                                                  v-if="process_item.pending_tasks_count > 0"
                                                  class="inline-flex items-center justify-center px-2.5 py-1 rounded-full text-xs font-semibold"
                                                  :class="process_item.pending_tasks_count > 0 ? 'bg-yellow-100 text-yellow-800 dark:bg-yellow-700 dark:text-yellow-100 hover:bg-yellow-200 dark:hover:bg-yellow-600' : 'bg-green-100 text-green-800 dark:bg-green-700 dark:text-green-100'"
                                                  :title="`${process_item.pending_tasks_count} tarefa(s) pendente(s)`">
                                                <AlertTriangle v-if="process_item.pending_tasks_count > 0" class="h-3.5 w-3.5 mr-1.5" />
                                                {{ process_item.pending_tasks_count }}
                                            </Link>
                                            <span v-else class="text-xs text-gray-500 dark:text-gray-400">
                                                Nenhuma
                                            </span>
                                        </TableCell>
                                        <TableCell class="px-4 py-3 whitespace-nowrap text-sm text-gray-700 dark:text-gray-300">
                                            {{ displayValue(process_item.contact?.name || process_item.contact?.business_name) }}
                                        </TableCell>
                                        <TableCell class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ displayValue(process_item.responsible?.name) }}
                                        </TableCell>
                                        <TableCell class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ displayValue(process_item.stage_label || process_item.stage) }}
                                        </TableCell>
                                        
                                        <TableCell class="px-4 py-3 whitespace-nowrap text-sm text-right font-mono text-gray-700 dark:text-gray-300">
                                            {{ formatCurrency(process_item.payments_sum_total_amount) }} </TableCell>
                                        
                                        <TableCell class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                            {{ formatDateForTable(process_item.updated_at) }}
                                        </TableCell>
                                        <TableCell class="px-4 py-3 whitespace-nowrap text-sm text-center">
                                            <Badge v-if="process_item.status_label" :variant="getPriorityVariant(process_item.priority)">
                                                {{ process_item.status_label }}
                                            </Badge>
                                            <span v-else>{{ displayValue(process_item.status) }}</span>
                                        </TableCell>
                                    </Link>
                                </template>
                                <TableRow v-else>
                                    <TableCell :colspan="tableHeaders.length" class="px-6 py-12 text-center">
                                        <div class="flex flex-col items-center justify-center text-gray-500 dark:text-gray-400">
                                            <Search class="mb-2 h-12 w-12 opacity-50" />
                                            <p class="text-lg font-medium">Nenhum caso encontrado.</p>
                                            <p class="text-sm">
                                                {{ searchTerm || activeWorkflow || activeStage || filterByResponsible || filterByPriority || filterByStatus || filterByDateFrom || filterByDateTo ? 'Tente refinar seus filtros ou ' : 'Você pode ' }}
                                                <Link v-if="searchTerm || activeWorkflow || activeStage || filterByResponsible || filterByPriority || filterByStatus || filterByDateFrom || filterByDateTo" :href="route('processes.index')"
                                                      @click.prevent="searchTerm = ''; activeWorkflow = null; activeStage = null; filterByResponsible = null; filterByPriority = null; filterByStatus = null; filterByDateFrom = null; filterByDateTo = null; applyAllFilters();"
                                                      class="text-indigo-600 dark:text-indigo-400 hover:underline">limpar os filtros
                                                </Link>
                                                {{ searchTerm || activeWorkflow || activeStage || filterByResponsible || filterByPriority || filterByStatus || filterByDateFrom || filterByDateTo ? ' para ver todos os casos, ou ' : '' }}
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
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;  /* IE and Edge */
    scrollbar-width: none;  /* Firefox */
}
</style>
