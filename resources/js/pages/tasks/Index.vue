<script setup lang="ts">
import { ref, computed, onMounted, watch } from 'vue';
import { Head, Link, useForm, router, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Badge } from '@/components/ui/badge';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogDescription, DialogFooter, DialogClose } from '@/components/ui/dialog';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Label } from '@/components/ui/label';
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select';
import { Popover, PopoverContent, PopoverTrigger } from '@/components/ui/popover';
import { AlertTriangle, CalendarDays, CheckCircle2, User, Briefcase, LinkIcon, GripVertical, PlusCircle, Trash2, Edit2, Filter as FilterIcon, SlidersHorizontal, X } from 'lucide-vue-next';
import draggable from 'vuedraggable';

import type { Task, User as TaskUser, Process as TaskProcess, Contact as TaskContactType, BreadcrumbItem, TaskStatus, TaskPriority } from '@/types';

interface KanbanColumn {
    id: TaskStatus;
    title: string;
    tasks: Task[];
}

interface ContactFilterOption {
    id: string | number;
    display_name: string;
}

interface TasksIndexProps {
    tasks: Task[];
    taskStatuses: Record<TaskStatus, string>;
    taskPriorities: Record<TaskPriority, string>;
    users: TaskUser[];
    processes: Array<{ id: string | number; title: string }>;
    contacts: ContactFilterOption[];
    filters: {
        status?: string;
        responsible_user_id?: string | number;
        contact_id?: string | number;
        due_date_from?: string;
        due_date_to?: string;
    }
}

const props = defineProps<TasksIndexProps>();
const page = usePage();

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


const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Painel', href: route('dashboard') },
    { title: 'Quadro de Tarefas', href: route('tasks.index') },
];

// --- Formulário de Nova Tarefa ---
const showNewTaskDialog = ref(false);
const taskForm = useForm({
    title: '',
    description: '',
    due_date: null as string | null,
    priority: 'Média' as TaskPriority,
    status: 'Pendente' as TaskStatus,
    responsible_user_id: null as (string | number | null),
    responsible_ids: [] as (string | number)[],
    process_id: null as (string | number | null),
    contact_id: null as (string | number | null),
    link_type: 'none' as 'none' | 'process' | 'contact',
});

// --- Formulário de Edição de Tarefa ---
const showEditTaskDialog = ref(false);
const taskToEdit = ref<Task | null>(null);
const editTaskForm = useForm({
    id: '' as string | number, // Guardar o ID da tarefa a ser editada
    title: '',
    description: '',
    due_date: null as string | null,
    priority: 'Média' as TaskPriority,
    status: 'Pendente' as TaskStatus,
    responsible_user_id: null as (string | number | null),
    responsible_ids: [] as (string | number)[],
    process_id: null as (string | number | null),
    contact_id: null as (string | number | null),
    link_type: 'none' as 'none' | 'process' | 'contact',
});


const localKanbanColumns = ref<KanbanColumn[]>([]);

function updateLocalKanbanColumns(tasksFromProps: Task[]) {
    const newColumns: KanbanColumn[] = [];
    const preferredStatusOrder: TaskStatus[] = ['Pendente', 'Em Andamento', 'Concluída', 'Cancelada'];

    preferredStatusOrder.forEach(statusKey => {
        newColumns.push({
            id: statusKey,
            title: props.taskStatuses[statusKey] || statusKey,
            tasks: [],
        });
    });
    
    (tasksFromProps || []).forEach(task => {
        if (task.status && !newColumns.find(col => col.id === task.status)) {
            if(!preferredStatusOrder.includes(task.status)){
                 newColumns.push({
                    id: task.status,
                    title: props.taskStatuses[task.status] || task.status,
                    tasks: [],
                });
            }
        }
    });
    
    (tasksFromProps || []).forEach(task => {
        const columnIndex = newColumns.findIndex(col => col.id === task.status);
        if (columnIndex !== -1) {
            newColumns[columnIndex].tasks.push({...task});
        } else {
            let otherColumn = newColumns.find(col => col.id === ('outros_status' as TaskStatus));
            if (!otherColumn) {
                otherColumn = { id: 'outros_status' as TaskStatus, title: 'Outros Status', tasks: [] };
                newColumns.push(otherColumn);
            }
            otherColumn.tasks.push({...task});
        }
    });
    localKanbanColumns.value = newColumns.filter(col => preferredStatusOrder.includes(col.id) || col.tasks.length > 0);
}

watch(() => props.tasks, (newTasks) => {
    updateLocalKanbanColumns(newTasks || []);
}, { immediate: true, deep: true });


function submitNewTask() {
    let targetRoute = route('tasks.store.general');
    if (taskForm.link_type === 'contact' && taskForm.contact_id) {
        targetRoute = route('contacts.tasks.store', { contact: taskForm.contact_id });
    }

    taskForm.transform((data) => {
        const payload: Record<string, any> = {
            title: data.title,
            description: data.description,
            due_date: data.due_date,
            priority: data.priority,
            status: data.status,
            responsible_user_id: data.responsible_user_id,
            responsible_ids: data.responsible_ids,
            process_id: data.link_type === 'process' ? data.process_id : null,
            contact_id: data.link_type === 'contact' ? data.contact_id : null,
        };
        return payload;
    }).post(targetRoute, {
        preserveScroll: true,
        onSuccess: () => {
            showNewTaskDialog.value = false;
            taskForm.reset(); 
            taskForm.clearErrors(); 
        },
        onError: (errors) => {
            console.error("Erro ao criar tarefa (onError do Inertia):", errors);
        },
        onFinish: () => {
            taskForm.transform(data => data);
        }
    });
}

function openEditTaskDialog(task: Task) {
    taskToEdit.value = task; // Guarda a tarefa original para referência, se necessário
    editTaskForm.id = task.id;
    editTaskForm.title = task.title;
    editTaskForm.description = task.description || '';
    editTaskForm.due_date = task.due_date || null;
    editTaskForm.priority = task.priority;
    editTaskForm.status = task.status;
    editTaskForm.responsible_user_id = task.responsible_user_id || null;
    editTaskForm.responsible_ids = task.responsibles ? task.responsibles.map(u => u.id) : [];
    
    if (task.process_id) {
        editTaskForm.link_type = 'process';
        editTaskForm.process_id = task.process_id;
        editTaskForm.contact_id = null;
    } else if (task.contact_id) {
        editTaskForm.link_type = 'contact';
        editTaskForm.contact_id = task.contact_id;
        editTaskForm.process_id = null;
    } else {
        editTaskForm.link_type = 'none';
        editTaskForm.process_id = null;
        editTaskForm.contact_id = null;
    }
    
    editTaskForm.clearErrors(); // Limpa erros de validação anteriores
    showEditTaskDialog.value = true;
}

function submitEditTask() {
    if (!taskToEdit.value) return;

    editTaskForm.transform((data) => {
        const payload: Record<string, any> = {
            title: data.title,
            description: data.description,
            due_date: data.due_date,
            priority: data.priority,
            status: data.status,
            responsible_user_id: data.responsible_user_id,
            responsible_ids: data.responsible_ids,
            process_id: data.link_type === 'process' ? data.process_id : null,
            contact_id: data.link_type === 'contact' ? data.contact_id : null,
        };
        // Não enviar link_type para o backend
        return payload;
    }).put(route('tasks.update', editTaskForm.id), { // Usa o ID guardado no form
        preserveScroll: true,
        onSuccess: () => {
            showEditTaskDialog.value = false;
            editTaskForm.reset();
            taskToEdit.value = null;
            // A prop 'tasks' deve ser recarregada pelo Inertia, acionando o watch
        },
        onError: (errors) => {
            console.error("Erro ao atualizar tarefa:", errors);
        },
        onFinish: () => {
            editTaskForm.transform(data => data); // Reseta a transformação
        }
    });
}


function onDragEnd(event: any) {
    const { item, to } = event;
    const taskId = item.dataset.taskId;
    const newStatus = to.dataset.statusId as TaskStatus | undefined;
    
    const originalTask = props.tasks.find(t => String(t.id) === String(taskId));

    if (originalTask && newStatus && originalTask.status !== newStatus) {
        updateTaskStatusOnBackend(originalTask, newStatus);
    }
}

function updateTaskStatusOnBackend(task: Task, newStatus: TaskStatus) {
    router.put(route('tasks.update', task.id), {
        status: newStatus,
        // Enviar apenas o status para esta atualização específica de drag-and-drop
        // Se quiser enviar outros campos, eles devem ser incluídos aqui.
        // Para manter simples, o TaskController@update deve tratar `sometimes` para outros campos.
    }, {
        preserveScroll: true,
        preserveState: (page) => Object.keys(page.props.errors).length > 0,
        onSuccess: () => {},
        onError: (errors) => {
            console.error(`Erro ao atualizar status da tarefa ${task.id}:`, errors);
            router.reload({ only: ['tasks'], preserveScroll: true });
        }
    });
}

function formatDate(dateString?: string | null, options?: Intl.DateTimeFormatOptions): string {
    if (!dateString) return 'N/A';
    try {
        const date = new Date(dateString.includes('T') || dateString.includes('Z') ? dateString : dateString + 'T00:00:00Z');
        if (isNaN(date.getTime())) return dateString;
        const defaultOptions: Intl.DateTimeFormatOptions = { day: '2-digit', month: 'short', year: 'numeric', timeZone: 'UTC' };
        return date.toLocaleDateString('pt-BR', { ...defaultOptions, ...options });
    } catch (e) { return dateString; }
}

function getPriorityBadgeVariant(priority?: TaskPriority | null): 'destructive' | 'secondary' | 'outline' {
    if (!priority) return 'outline';
    if (priority === 'Alta') return 'destructive';
    if (priority === 'Média') return 'secondary';
    return 'outline';
}

const filterResponsibleId = ref(props.filters.responsible_user_id ? String(props.filters.responsible_user_id) : null);
const filterStatus = ref(props.filters.status || null);
const filterContactId = ref(props.filters.contact_id ? String(props.filters.contact_id) : null);
const filterDueDateFrom = ref(props.filters.due_date_from || null);
const filterDueDateTo = ref(props.filters.due_date_to || null);

const showAdvancedFilters = ref(false);

function applyFilters() {
    const queryParams: Record<string, string | number | undefined> = {};
    if (filterResponsibleId.value && filterResponsibleId.value !== 'null') {
        queryParams.responsible_user_id = filterResponsibleId.value;
    }
    if (filterStatus.value && filterStatus.value !== 'null') {
        queryParams.status = filterStatus.value;
    }
    if (filterContactId.value && filterContactId.value !== 'null') {
        queryParams.contact_id = filterContactId.value;
    }
    if (filterDueDateFrom.value) {
        queryParams.due_date_from = filterDueDateFrom.value;
    }
    if (filterDueDateTo.value) {
        queryParams.due_date_to = filterDueDateTo.value;
    }

    router.get(route('tasks.index'), queryParams, {
        preserveState: true,
        preserveScroll: true,
        replace: true,
    });
}
function resetFilters() {
    filterResponsibleId.value = null;
    filterStatus.value = null;
    filterContactId.value = null;
    filterDueDateFrom.value = null;
    filterDueDateTo.value = null;
    applyFilters();
}

const showDeleteTaskDialog = ref(false);
const taskToDelete = ref<Task | null>(null);
const deleteTaskForm = useForm({});

function openDeleteTaskDialog(task: Task) {
    taskToDelete.value = task;
    showDeleteTaskDialog.value = true;
}

function confirmDeleteTask() {
    if (!taskToDelete.value) return;
    deleteTaskForm.delete(route('tasks.destroy', taskToDelete.value.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteTaskDialog.value = false;
            taskToDelete.value = null;
        },
        onError: (errors) => console.error("Erro ao excluir tarefa:", errors)
    });
}

onMounted(() => {
    const urlParams = new URLSearchParams(window.location.search);
    const contactIdParam = urlParams.get('contact_id');
    const processIdParam = urlParams.get('process_id');

    if (contactIdParam) {
        taskForm.link_type = 'contact';
        taskForm.contact_id = contactIdParam;
        taskForm.process_id = null;
    } else if (processIdParam) {
        taskForm.link_type = 'process';
        taskForm.process_id = processIdParam;
        taskForm.contact_id = null;
    }

    filterContactId.value = props.filters.contact_id ? String(props.filters.contact_id) : null;
    filterDueDateFrom.value = props.filters.due_date_from || null;
    filterDueDateTo.value = props.filters.due_date_to || null;

    updateLocalKanbanColumns(props.tasks || []);
});

</script>

<template>
    <Head title="Quadro de Tarefas" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container mx-auto p-4 sm:p-6 lg:p-8 h-full flex flex-col">
            <div class="flex flex-col sm:flex-row justify-between items-center mb-6 gap-4">
                <h1 class="text-2xl sm:text-3xl font-bold text-gray-800 dark:text-gray-100">
                    Quadro de Tarefas (Kanban)
                </h1>
                <div class="flex items-center gap-2 flex-wrap">
                     <Select v-model="filterResponsibleId" @update:modelValue="applyFilters">
                        <SelectTrigger class="h-9 w-auto min-w-[160px] text-xs">
                            <SelectValue placeholder="Responsável" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="null">Todos Responsáveis</SelectItem>
                            <SelectItem v-for="user in users" :key="user.id" :value="String(user.id)">{{ user.name }}</SelectItem>
                        </SelectContent>
                    </Select>
                     <Select v-model="filterStatus" @update:modelValue="applyFilters">
                        <SelectTrigger class="h-9 w-auto min-w-[150px] text-xs">
                            <SelectValue placeholder="Status" />
                        </SelectTrigger>
                        <SelectContent>
                            <SelectItem value="null">Todos Status</SelectItem>
                            <SelectItem v-for="(label, key) in taskStatuses" :key="key" :value="key">{{ label }}</SelectItem>
                        </SelectContent>
                    </Select>

                    <Popover v-model:open="showAdvancedFilters">
                        <PopoverTrigger as-child>
                            <Button variant="outline" size="sm" class="h-9 text-xs">
                                <SlidersHorizontal class="h-3.5 w-3.5 mr-1.5" /> Filtros Avançados
                                 <span v-if="filterContactId || filterDueDateFrom || filterDueDateTo" class="ml-2 h-2 w-2 rounded-full bg-sky-500 animate-pulse"></span>
                            </Button>
                        </PopoverTrigger>
                        <PopoverContent class="w-80 p-4 space-y-3" align="end">
                            <div class="space-y-1">
                                 <h4 class="font-medium leading-none text-sm">Filtros Adicionais</h4>
                                 <p class="text-xs text-muted-foreground">
                                    Refine sua busca por tarefas.
                                </p>
                            </div>
                            <hr class="dark:border-gray-700"/>
                            <div class="grid gap-3">
                                <div class="space-y-1.5">
                                    <Label for="filterContact" class="text-xs">Contato Vinculado</Label>
                                    <Select v-model="filterContactId">
                                        <SelectTrigger id="filterContact" class="h-9 text-xs">
                                            <SelectValue placeholder="Todos Contatos" />
                                        </SelectTrigger>
                                        <SelectContent>
                                            <SelectItem value="null">Todos Contatos</SelectItem>
                                            <SelectItem v-for="contact_item_option in props.contacts" :key="contact_item_option.id" :value="String(contact_item_option.id)">
                                                {{ contact_item_option.display_name }}
                                            </SelectItem>
                                        </SelectContent>
                                    </Select>
                                </div>
                                <div class="grid grid-cols-2 gap-2">
                                    <div class="space-y-1.5">
                                        <Label for="filterDueDateFrom" class="text-xs">Prazo De</Label>
                                        <Input id="filterDueDateFrom" type="date" v-model="filterDueDateFrom" class="h-9 text-xs" />
                                    </div>
                                    <div class="space-y-1.5">
                                        <Label for="filterDueDateTo" class="text-xs">Prazo Até</Label>
                                        <Input id="filterDueDateTo" type="date" v-model="filterDueDateTo" class="h-9 text-xs" />
                                    </div>
                                </div>
                            </div>
                             <div class="flex justify-end space-x-2 pt-2">
                                <Button variant="ghost" size="sm" @click="resetFilters(); showAdvancedFilters = false;" class="text-xs h-8">Limpar</Button>
                                <Button @click="applyFilters(); showAdvancedFilters = false;" size="sm" class="text-xs h-8">Aplicar</Button>
                            </div>
                        </PopoverContent>
                    </Popover>

                    <Button @click="showNewTaskDialog = true" size="sm" class="h-9">
                        <PlusCircle class="h-4 w-4 mr-2" /> Nova Tarefa
                    </Button>
                </div>
            </div>

            <div class="flex-1 grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-4 overflow-x-auto pb-4">
                <div v-for="column in localKanbanColumns" :key="column.id"
                     class="bg-gray-100 dark:bg-gray-800/50 p-3 rounded-lg flex flex-col min-w-[300px] h-full">
                    <h2 class="text-lg font-semibold text-gray-700 dark:text-gray-200 mb-3 px-1 sticky top-0 bg-gray-100 dark:bg-gray-800/50 py-2 z-10">
                        {{ column.title }} ({{ column.tasks.length }})
                    </h2>
                    <draggable
                        v-model="column.tasks"
                        :group="{ name: 'tasks', pull: true, put: true }"
                        item-key="id"
                        class="space-y-3 flex-1 overflow-y-auto min-h-[200px] pr-1"
                        :data-status-id="column.id"
                        @end="onDragEnd"
                        ghost-class="ghost-card"
                        drag-class="dragging-card"
                        :animation="150"
                        handle=".drag-handle"
                    >
                        <template #item="{element: task}">
                             <Card :key="task.id" 
                                  class="bg-white dark:bg-gray-700 hover:shadow-lg transition-shadow duration-150 ease-in-out group"
                                  :data-task-id="task.id">
                                <CardContent class="p-3 text-sm relative">
                                     <div class="absolute top-1 right-1 opacity-0 group-hover:opacity-100 transition-opacity drag-handle cursor-move p-1 active:cursor-grabbing">
                                        <GripVertical class="h-4 w-4 text-gray-400 dark:text-gray-500" />
                                    </div>
                                    <div class="flex justify-between items-start mb-1 pr-6">
                                        <h3 class="font-semibold text-gray-800 dark:text-gray-100 break-words">{{ task.title }}</h3>
                                        <Badge :variant="getPriorityBadgeVariant(task.priority)" class="ml-2 text-xs whitespace-nowrap">
                                            {{ props.taskPriorities[task.priority] || task.priority }}
                                        </Badge>
                                    </div>
                                    <p v-if="task.description" class="text-xs text-gray-600 dark:text-gray-400 mb-2 break-words whitespace-pre-wrap">{{ task.description.substring(0,100) }}{{ task.description.length > 100 ? '...' : ''}}</p>
                                    
                                    <div class="text-xs text-gray-500 dark:text-gray-400 space-y-0.5">
                                        <div v-if="task.due_date" class="flex items-center" :title="`Prazo: ${formatDate(task.due_date)}`">
                                            <CalendarDays class="h-3.5 w-3.5 mr-1.5 flex-shrink-0" 
                                                          :class="{'text-red-500': task.is_overdue && task.status !== 'Concluída'}" />
                                            <span :class="{'text-red-500 font-semibold': task.is_overdue && task.status !== 'Concluída'}">
                                                {{ formatDate(task.due_date) }}
                                            </span>
                                            <span v-if="task.is_overdue && task.status !== 'Concluída'" class="ml-1 text-red-500 font-semibold">(Atrasada)</span>
                                        </div>
                                        <div v-if="task.responsibleUser" class="flex items-center" :title="`Responsável: ${task.responsibleUser.name}`">
                                            <User class="h-3.5 w-3.5 mr-1.5 flex-shrink-0" />
                                            <span class="truncate">{{ task.responsibleUser.name }}</span>
                                        </div>
                                         <div v-else-if="task.responsibles && task.responsibles.length > 0" class="flex items-center" :title="`Responsáveis: ${task.responsibles.map(r => r.name).join(', ')}`">
                                            <User class="h-3.5 w-3.5 mr-1.5 flex-shrink-0" />
                                            <span class="truncate">{{ task.responsibles.map(r => r.name).join(', ') }}</span>
                                        </div>
                                        <div v-if="task.process" class="flex items-center mt-1 pt-1 border-t border-gray-200 dark:border-gray-600">
                                            <Briefcase class="h-3.5 w-3.5 mr-1.5 flex-shrink-0 text-blue-500" />
                                            <Link :href="route('processes.show', task.process.id)" class="text-blue-600 hover:underline dark:text-blue-400 truncate" :title="task.process.title">
                                                Caso: {{ task.process.title }}
                                            </Link>
                                        </div>
                                        <div v-if="task.contact" class="flex items-center mt-1 pt-1 border-t border-gray-200 dark:border-gray-600">
                                            <LinkIcon class="h-3.5 w-3.5 mr-1.5 flex-shrink-0 text-green-500" />
                                            <Link :href="route('contacts.show', task.contact.id)" class="text-green-600 hover:underline dark:text-green-400 truncate" :title="task.contact.name || task.contact.business_name">
                                                Contato: {{ task.contact.name || task.contact.business_name }}
                                            </Link>
                                        </div>
                                    </div>
                                    <div class="mt-2 flex justify-end space-x-1">
                                        <Button @click="openEditTaskDialog(task)" variant="ghost" size="icon" class="h-7 w-7" title="Editar Tarefa">
                                            <Edit2 class="h-3.5 w-3.5 text-gray-500 hover:text-blue-600" />
                                        </Button>
                                        <Button @click="openDeleteTaskDialog(task)" variant="ghost" size="icon" class="h-7 w-7" title="Excluir Tarefa">
                                            <Trash2 class="h-3.5 w-3.5 text-gray-500 hover:text-red-600" />
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        </template>
                    </draggable>
                     <p v-if="column.tasks.length === 0" class="text-center text-sm text-gray-400 dark:text-gray-500 mt-4 flex-1 flex items-center justify-center">
                        Nenhuma tarefa aqui.
                    </p>
                </div>
            </div>
        </div>

        <Dialog :open="showNewTaskDialog" @update:open="showNewTaskDialog = $event">
            <DialogContent class="sm:max-w-lg md:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Criar Nova Tarefa</DialogTitle>
                    <DialogDescription>Preencha os detalhes da nova tarefa abaixo.</DialogDescription>
                </DialogHeader>
                <form @submit.prevent="submitNewTask" class="space-y-4 py-2 max-h-[70vh] overflow-y-auto pr-2">
                    <div>
                        <Label for="newTaskTitle" class="text-sm">Título <span class="text-red-500">*</span></Label>
                        <Input id="newTaskTitle" v-model="taskForm.title" required />
                        <div v-if="taskForm.errors.title" class="text-xs text-red-500 mt-1">{{ taskForm.errors.title }}</div>
                    </div>
                    <div>
                        <Label for="newTaskDescription" class="text-sm">Descrição</Label>
                        <Textarea id="newTaskDescription" v-model="taskForm.description" rows="3" />
                         <div v-if="taskForm.errors.description" class="text-xs text-red-500 mt-1">{{ taskForm.errors.description }}</div>
                    </div>

                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <Label for="newTaskDueDate" class="text-sm">Prazo</Label>
                            <Input id="newTaskDueDate" type="date" v-model="taskForm.due_date" />
                            <div v-if="taskForm.errors.due_date" class="text-xs text-red-500 mt-1">{{ taskForm.errors.due_date }}</div>
                        </div>
                        <div>
                            <Label for="newTaskPriority" class="text-sm">Prioridade <span class="text-red-500">*</span></Label>
                            <Select v-model="taskForm.priority" required>
                                <SelectTrigger id="newTaskPriority"><SelectValue placeholder="Selecione" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="(label, key) in props.taskPriorities" :key="key" :value="key">{{ label }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <div v-if="taskForm.errors.priority" class="text-xs text-red-500 mt-1">{{ taskForm.errors.priority }}</div>
                        </div>
                    </div>
                     <div>
                        <Label for="newTaskStatus" class="text-sm">Status <span class="text-red-500">*</span></Label>
                        <Select v-model="taskForm.status" required>
                            <SelectTrigger id="newTaskStatus"><SelectValue placeholder="Selecione" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="(label, key) in props.taskStatuses" :key="key" :value="key">{{ label }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <div v-if="taskForm.errors.status" class="text-xs text-red-500 mt-1">{{ taskForm.errors.status }}</div>
                    </div>

                    <div>
                        <Label for="newTaskResponsible" class="text-sm">Responsável Principal</Label>
                        <Select v-model="taskForm.responsible_user_id">
                            <SelectTrigger id="newTaskResponsible"><SelectValue placeholder="Ninguém" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="null">Ninguém</SelectItem>
                                <SelectItem v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <div v-if="taskForm.errors.responsible_user_id" class="text-xs text-red-500 mt-1">{{ taskForm.errors.responsible_user_id }}</div>
                    </div>
                    
                    <div>
                        <Label class="text-sm">Vincular a:</Label>
                        <div class="mt-1 flex space-x-4">
                            <label class="flex items-center">
                                <input type="radio" v-model="taskForm.link_type" value="none" name="linkTypeOption" class="form-radio h-4 w-4 text-indigo-600 transition duration-150 ease-in-out" />
                                <span class="ml-2 text-sm">Nenhum</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" v-model="taskForm.link_type" value="process" name="linkTypeOption" class="form-radio h-4 w-4 text-indigo-600 transition duration-150 ease-in-out" />
                                <span class="ml-2 text-sm">Processo/Caso</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" v-model="taskForm.link_type" value="contact" name="linkTypeOption" class="form-radio h-4 w-4 text-indigo-600 transition duration-150 ease-in-out" />
                                <span class="ml-2 text-sm">Contato</span>
                            </label>
                        </div>
                    </div>

                    <div v-if="taskForm.link_type === 'process'">
                        <Label for="newTaskProcess" class="text-sm">Processo/Caso</Label>
                        <Select v-model="taskForm.process_id">
                            <SelectTrigger id="newTaskProcess"><SelectValue placeholder="Selecione um Processo" /></SelectTrigger>
                            <SelectContent class="max-h-60">
                                <SelectItem :value="null">Nenhum</SelectItem>
                                <SelectItem v-for="process_item_option in props.processes" :key="process_item_option.id" :value="process_item_option.id">{{ process_item_option.title }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <div v-if="taskForm.errors.process_id" class="text-xs text-red-500 mt-1">{{ taskForm.errors.process_id }}</div>
                    </div>

                    <div v-if="taskForm.link_type === 'contact'">
                        <Label for="newTaskContact" class="text-sm">Contato</Label>
                        <Select v-model="taskForm.contact_id">
                            <SelectTrigger id="newTaskContact"><SelectValue placeholder="Selecione um Contato" /></SelectTrigger>
                            <SelectContent class="max-h-60">
                                <SelectItem :value="null">Nenhum</SelectItem>
                                <SelectItem v-for="contact_item_option in props.contacts" :key="contact_item_option.id" :value="contact_item_option.id">{{ contact_item_option.display_name }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <div v-if="taskForm.errors.contact_id" class="text-xs text-red-500 mt-1">{{ taskForm.errors.contact_id }}</div>
                    </div>
                     <div v-if="taskForm.errors.general" class="text-xs text-red-500 mt-1">{{ taskForm.errors.general }}</div>


                    <DialogFooter class="pt-4">
                         <DialogClose as-child>
                            <Button type="button" variant="outline" @click="showNewTaskDialog = false; taskForm.reset(); taskForm.clearErrors();">Cancelar</Button>
                        </DialogClose>
                        <Button type="submit" :disabled="taskForm.processing">
                            {{ taskForm.processing ? 'Salvando...' : 'Salvar Tarefa' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog :open="showEditTaskDialog" @update:open="showEditTaskDialog = $event">
            <DialogContent class="sm:max-w-lg md:max-w-xl">
                <DialogHeader>
                    <DialogTitle>Editar Tarefa</DialogTitle>
                    <DialogDescription>Modifique os detalhes da tarefa abaixo.</DialogDescription>
                </DialogHeader>
                <form @submit.prevent="submitEditTask" class="space-y-4 py-2 max-h-[70vh] overflow-y-auto pr-2">
                    <div>
                        <Label for="editTaskTitle" class="text-sm">Título <span class="text-red-500">*</span></Label>
                        <Input id="editTaskTitle" v-model="editTaskForm.title" required />
                        <div v-if="editTaskForm.errors.title" class="text-xs text-red-500 mt-1">{{ editTaskForm.errors.title }}</div>
                    </div>
                    <div>
                        <Label for="editTaskDescription" class="text-sm">Descrição</Label>
                        <Textarea id="editTaskDescription" v-model="editTaskForm.description" rows="3" />
                        <div v-if="editTaskForm.errors.description" class="text-xs text-red-500 mt-1">{{ editTaskForm.errors.description }}</div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <Label for="editTaskDueDate" class="text-sm">Prazo</Label>
                            <Input id="editTaskDueDate" type="date" v-model="editTaskForm.due_date" />
                            <div v-if="editTaskForm.errors.due_date" class="text-xs text-red-500 mt-1">{{ editTaskForm.errors.due_date }}</div>
                        </div>
                        <div>
                            <Label for="editTaskPriority" class="text-sm">Prioridade <span class="text-red-500">*</span></Label>
                            <Select v-model="editTaskForm.priority" required>
                                <SelectTrigger id="editTaskPriority"><SelectValue placeholder="Selecione" /></SelectTrigger>
                                <SelectContent>
                                    <SelectItem v-for="(label, key) in props.taskPriorities" :key="key" :value="key">{{ label }}</SelectItem>
                                </SelectContent>
                            </Select>
                            <div v-if="editTaskForm.errors.priority" class="text-xs text-red-500 mt-1">{{ editTaskForm.errors.priority }}</div>
                        </div>
                    </div>
                    <div>
                        <Label for="editTaskStatus" class="text-sm">Status <span class="text-red-500">*</span></Label>
                        <Select v-model="editTaskForm.status" required>
                            <SelectTrigger id="editTaskStatus"><SelectValue placeholder="Selecione" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem v-for="(label, key) in props.taskStatuses" :key="key" :value="key">{{ label }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <div v-if="editTaskForm.errors.status" class="text-xs text-red-500 mt-1">{{ editTaskForm.errors.status }}</div>
                    </div>
                    <div>
                        <Label for="editTaskResponsible" class="text-sm">Responsável Principal</Label>
                        <Select v-model="editTaskForm.responsible_user_id">
                            <SelectTrigger id="editTaskResponsible"><SelectValue placeholder="Ninguém" /></SelectTrigger>
                            <SelectContent>
                                <SelectItem :value="null">Ninguém</SelectItem>
                                <SelectItem v-for="user in users" :key="user.id" :value="user.id">{{ user.name }}</SelectItem>
                            </SelectContent>
                        </Select>
                         <div v-if="editTaskForm.errors.responsible_user_id" class="text-xs text-red-500 mt-1">{{ editTaskForm.errors.responsible_user_id }}</div>
                    </div>
                     <div>
                        <Label class="text-sm">Vincular a:</Label>
                        <div class="mt-1 flex space-x-4">
                            <label class="flex items-center">
                                <input type="radio" v-model="editTaskForm.link_type" value="none" name="editLinkTypeOption" class="form-radio"/>
                                <span class="ml-2 text-sm">Nenhum</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" v-model="editTaskForm.link_type" value="process" name="editLinkTypeOption" class="form-radio"/>
                                <span class="ml-2 text-sm">Processo/Caso</span>
                            </label>
                            <label class="flex items-center">
                                <input type="radio" v-model="editTaskForm.link_type" value="contact" name="editLinkTypeOption" class="form-radio"/>
                                <span class="ml-2 text-sm">Contato</span>
                            </label>
                        </div>
                    </div>
                    <div v-if="editTaskForm.link_type === 'process'">
                        <Label for="editTaskProcess" class="text-sm">Processo/Caso</Label>
                        <Select v-model="editTaskForm.process_id">
                            <SelectTrigger id="editTaskProcess"><SelectValue placeholder="Selecione um Processo" /></SelectTrigger>
                            <SelectContent class="max-h-60">
                                <SelectItem :value="null">Nenhum</SelectItem>
                                <SelectItem v-for="process_item_option in props.processes" :key="process_item_option.id" :value="process_item_option.id">{{ process_item_option.title }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <div v-if="editTaskForm.errors.process_id" class="text-xs text-red-500 mt-1">{{ editTaskForm.errors.process_id }}</div>
                    </div>
                    <div v-if="editTaskForm.link_type === 'contact'">
                        <Label for="editTaskContact" class="text-sm">Contato</Label>
                        <Select v-model="editTaskForm.contact_id">
                            <SelectTrigger id="editTaskContact"><SelectValue placeholder="Selecione um Contato" /></SelectTrigger>
                            <SelectContent class="max-h-60">
                                <SelectItem :value="null">Nenhum</SelectItem>
                                <SelectItem v-for="contact_item_option in props.contacts" :key="contact_item_option.id" :value="contact_item_option.id">{{ contact_item_option.display_name }}</SelectItem>
                            </SelectContent>
                        </Select>
                        <div v-if="editTaskForm.errors.contact_id" class="text-xs text-red-500 mt-1">{{ editTaskForm.errors.contact_id }}</div>
                    </div>
                     <div v-if="editTaskForm.errors.general" class="text-xs text-red-500 mt-1">{{ editTaskForm.errors.general }}</div>

                    <DialogFooter class="pt-4">
                        <DialogClose as-child>
                            <Button type="button" variant="outline" @click="showEditTaskDialog = false; editTaskForm.reset(); editTaskForm.clearErrors(); taskToEdit = null;">Cancelar</Button>
                        </DialogClose>
                        <Button type="submit" :disabled="editTaskForm.processing">
                            {{ editTaskForm.processing ? 'Salvando...' : 'Salvar Alterações' }}
                        </Button>
                    </DialogFooter>
                </form>
            </DialogContent>
        </Dialog>

        <Dialog :open="showDeleteTaskDialog" @update:open="showDeleteTaskDialog = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Confirmar Exclusão de Tarefa</DialogTitle>
                    <DialogDescription v-if="taskToDelete">
                        Tem certeza que deseja excluir a tarefa "{{ taskToDelete.title }}"? Esta ação não pode ser desfeita.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="mt-4">
                    <Button variant="outline" @click="showDeleteTaskDialog = false; taskToDelete = null;">Cancelar</Button>
                    <Button variant="destructive" @click="confirmDeleteTask" :disabled="deleteTaskForm.processing">
                        {{ deleteTaskForm.processing ? 'Excluindo...' : 'Excluir Tarefa' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

    </AppLayout>
</template>

<style scoped>
.ghost-card {
    opacity: 0.5;
    background: #f0f9ff; 
    border: 1px dashed #0ea5e9;
}
.dragging-card {
    /* opacity: 0.8; */
    /* transform: rotate(2deg); */
    /* box-shadow: 0 10px 15px -3px rgba(0,0,0,0.1), 0 4px 6px -2px rgba(0,0,0,0.05); */
}

.overflow-y-auto::-webkit-scrollbar {
    width: 6px;
}
.overflow-y-auto::-webkit-scrollbar-track {
    background: transparent;
}
.overflow-y-auto::-webkit-scrollbar-thumb {
    background-color: #cbd5e1;
    border-radius: 3px;
}
.dark .overflow-y-auto::-webkit-scrollbar-thumb {
    background-color: #4b5563;
}
.cursor-grab {
    cursor: grab;
}
.active\:cursor-grabbing:active { 
    cursor: grabbing;
}
</style>
