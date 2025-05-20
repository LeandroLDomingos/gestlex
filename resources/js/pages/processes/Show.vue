<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogHeader,
    DialogTitle,
    DialogTrigger,
} from '@/components/ui/dialog';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
    DropdownMenuRadioGroup,
    DropdownMenuRadioItem,
} from '@/components/ui/dropdown-menu';
import {
    Edit, Trash2, PlusCircle, Paperclip, Clock, UserCircle2,
    MessageSquare, History, Briefcase, DollarSign, Users,
    CalendarDays, AlertTriangle, CheckCircle, Zap, MoreVertical, Archive, FileText, ChevronDownIcon, ArchiveRestore
} from 'lucide-vue-next';

import type { Process, ProcessAnnotation, ProcessTask, ProcessDocument, ProcessHistoryEntry, BreadcrumbItem } from '@/types/process';
// import InputError from '@/Components/InputError.vue';

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

interface SelectOption {
    key: string;
    label: string;
}

interface StageOption {
    key: number;
    label: string;
}

const props = defineProps<{
    process: Process & { archived_at?: string | null }; // Adicionado archived_at à interface Process aqui
    availableStages?: StageOption[];
    availablePriorities?: SelectOption[];
    availableStatuses?: SelectOption[];
}>();

const activeMainTab = ref<'tasks' | 'documents' | 'history'>('tasks');
const showNewAnnotationForm = ref(false);
const showDeleteProcessDialog = ref(false);
const processDeleteForm = useForm({});
const showDeleteProcessAnnotationDialog = ref(false);
const processAnnotationToDelete = ref<ProcessAnnotation | null>(null);
const processAnnotationDeleteForm = useForm({});

const stageUpdateForm = useForm({
    stage: props.process.stage,
});

const statusUpdateForm = useForm({
    status: props.process.status,
});
const priorityUpdateForm = useForm({
    priority: props.process.priority,
});

// Formulário para arquivar/restaurar
const archiveForm = useForm({});

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Painel', href: route('dashboard') },
    { title: 'Casos', href: route('processes.index') },
    { title: props.process.workflow_label || props.process.workflow, href: route('processes.index', { workflow: props.process.workflow }) },
    { title: props.process.title || 'Detalhes do Caso', href: route('processes.show', props.process.id) },
]);

const formatDate = (dateString: string | null | undefined, includeTime = false): string => {
    if (!dateString) return 'N/A';
    try {
        const date = new Date(dateString.includes('T') || dateString.includes('Z') ? dateString : dateString + 'T00:00:00Z');
        const options: Intl.DateTimeFormatOptions = {
            day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC'
        };
        if (includeTime) { options.hour = '2-digit'; options.minute = '2-digit'; }
        return date.toLocaleDateString('pt-BR', options);
    } catch (e) { console.error("Erro ao formatar data:", dateString, e); return dateString; }
};

const formatCurrency = (value: number | string | null | undefined): string => {
    const numValue = Number(value);
    if (value === null || typeof value === 'undefined' || isNaN(numValue)) return 'N/A';
    return numValue.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
};

const priorityLabelForDisplay = computed(() => props.process.priority_label || props.process.priority || 'N/A');
const priorityVariantForDisplay = computed((): 'destructive' | 'secondary' | 'outline' | 'default' => {
    if (!props.process.priority) return 'outline';
    switch (props.process.priority.toLowerCase()) {
        case 'high': return 'destructive';
        case 'medium': return 'secondary';
        case 'low': return 'outline';
        default: return 'outline';
    }
});

const annotationForm = useForm({ content: '' });

function submitAnnotation() {
    annotationForm.post(route('processes.annotations.store', props.process.id), {
        preserveScroll: true,
        onSuccess: () => {
            annotationForm.reset('content');
            showNewAnnotationForm.value = false;
            router.reload({ only: ['process'] });
        },
        onError: (errors) => console.error("Erro ao salvar anotação:", errors)
    });
}

function editProcess() { router.visit(route('processes.edit', props.process.id)); }

function toggleArchiveProcess() {
    if (props.process.archived_at) {
        archiveForm.patch(route('processes.unarchive', props.process.id), {
            preserveScroll: true,
            onSuccess: () => router.reload({ only: ['process'] }),
            onError: (errors) => console.error('Erro ao restaurar processo:', errors)
        });
    } else {
        archiveForm.patch(route('processes.archive', props.process.id), {
            preserveScroll: true,
            onSuccess: () => {
                router.reload({ only: ['process'] });
            },
            onError: (errors) => console.error('Erro ao arquivar processo:', errors)
        });
    }
}

function openDeleteProcessDialog() { showDeleteProcessDialog.value = true; }
function submitDeleteProcess() {
    processDeleteForm.delete(route('processes.destroy', props.process.id), {
        preserveScroll: false,
        onSuccess: () => showDeleteProcessDialog.value = false,
        onError: (errors) => console.error('Erro ao excluir processo:', errors)
    });
}

function openDeleteProcessAnnotationDialog(annotation: ProcessAnnotation) {
    processAnnotationToDelete.value = annotation;
    showDeleteProcessAnnotationDialog.value = true;
}
function submitDeleteProcessAnnotation() {
    if (!processAnnotationToDelete.value) return;
    processAnnotationDeleteForm.delete(route('processes.annotations.destroy', {
        process: props.process.id,
        annotation: processAnnotationToDelete.value.id
    }), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteProcessAnnotationDialog.value = false;
            processAnnotationToDelete.value = null;
            router.reload({ only: ['process'] });
        },
        onError: (errors) => console.error('Erro ao excluir anotação do processo:', errors)
    });
}

function updateStage(newStageKey: number) {
    stageUpdateForm.patch(route('processes.updateStage', props.process.id), {
        preserveScroll: true,
        onSuccess: () => router.reload({ only: ['process'] }),
        onError: (errors) => {
            console.error('Erro ao atualizar estágio:', errors);
            stageUpdateForm.stage = props.process.stage;
            alert(errors.stage || 'Ocorreu um erro ao tentar atualizar o estágio.');
        }
    });
}

function updateProcessStatus(newStatusKey: string) {
    statusUpdateForm.patch(route('processes.updateStatus', props.process.id), {
        preserveScroll: true,
        onSuccess: () => router.reload({ only: ['process'] }),
        onError: (errors) => {
            console.error('Erro ao atualizar status:', errors);
            statusUpdateForm.status = props.process.status;
            alert(errors.status || 'Ocorreu um erro ao tentar atualizar o status.');
        }
    });
}

function updateProcessPriority(newPriorityKey: string) {
    priorityUpdateForm.patch(route('processes.updatePriority', props.process.id), {
        preserveScroll: true,
        onSuccess: () => router.reload({ only: ['process'] }),
        onError: (errors) => {
            console.error('Erro ao atualizar prioridade:', errors);
            priorityUpdateForm.priority = props.process.priority;
            alert(errors.priority || 'Ocorreu um erro ao tentar atualizar a prioridade.');
        }
    });
}

const documents = computed(() => props.process.documents || []);
const historyEntries = computed(() => props.process.history_entries || []); // Usar diretamente da prop process

const isArchived = computed(() => !!props.process.archived_at);

</script>

<template>
    <Head :title="`Caso: ${process.title || 'Detalhes do Caso'}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col lg:flex-row gap-6 p-4 md:p-6 h-[calc(100vh-theme(spacing.16)-theme(spacing.1))] overflow-hidden">
            <div class="w-full lg:w-1/3 xl:w-1/4 space-y-6 flex-shrink-0 overflow-y-auto pr-2 no-scrollbar">
                <Card class="overflow-hidden shadow-lg">
                    <div class="bg-gradient-to-br from-blue-500 to-indigo-600 dark:from-blue-700 dark:to-indigo-800 h-32 sm:h-40 md:h-48 flex items-center justify-center p-4 relative">
                        <Briefcase v-if="process.workflow_label?.toLowerCase().includes('judicial')" class="h-16 w-16 text-white opacity-75" />
                        <MessageSquare v-else-if="process.workflow_label?.toLowerCase().includes('consultivo')" class="h-16 w-16 text-white opacity-75" />
                        <Zap v-else-if="process.workflow_label?.toLowerCase().includes('prospecção')" class="h-16 w-16 text-white opacity-75" />
                        <FileText v-else class="h-16 w-16 text-white opacity-75" />

                        <div class="absolute top-2 right-2">
                            <DropdownMenu>
                                <DropdownMenuTrigger as-child>
                                    <Button variant="ghost" size="icon" class="h-8 w-8 text-white hover:bg-white/20 focus-visible:ring-white/50">
                                        <MoreVertical class="h-5 w-5" />
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="end" class="w-48">
                                    <DropdownMenuLabel>Ações do Caso</DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem @click="editProcess" :disabled="isArchived">
                                        <Edit class="mr-2 h-4 w-4" />
                                        <span>Editar</span>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem @click="toggleArchiveProcess">
                                        <Archive v-if="!isArchived" class="mr-2 h-4 w-4" />
                                        <ArchiveRestore v-else class="mr-2 h-4 w-4" />
                                        <span>{{ isArchived ? 'Restaurar' : 'Arquivar' }}</span>
                                    </DropdownMenuItem>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuItem @click="openDeleteProcessDialog" class="text-red-600 focus:bg-red-50 dark:focus:bg-red-900/50 focus:text-red-600 dark:focus:text-red-400">
                                        <Trash2 class="mr-2 h-4 w-4" />
                                        <span>Excluir</span>
                                    </DropdownMenuItem>
                                </DropdownMenuContent>
                            </DropdownMenu>
                        </div>
                    </div>
                    <CardContent class="p-4 space-y-2">
                        <div v-if="isArchived" class="mb-2 p-2 bg-yellow-100 dark:bg-yellow-900/50 border-l-4 border-yellow-500 text-yellow-700 dark:text-yellow-300 text-xs rounded">
                            <p class="font-medium">Este caso está arquivado.</p>
                            <p>Arquivado em: {{ formatDate(process.archived_at, true) }}</p>
                        </div>
                        <CardTitle class="text-lg font-semibold text-gray-800 dark:text-gray-100 truncate" :title="process.title">
                            {{ process.title || 'Caso sem Título' }}
                        </CardTitle>
                        <CardDescription class="text-xs text-gray-600 dark:text-gray-400 flex items-center flex-wrap">
                            <span>{{ process.workflow_label || process.workflow }} - Estágio:</span>
                            <DropdownMenu v-if="props.availableStages && props.availableStages.length > 0 && !isArchived">
                                <DropdownMenuTrigger as-child>
                                    <Button variant="link" class="p-0 h-auto ml-1 text-xs text-indigo-600 dark:text-indigo-400 hover:underline focus-visible:ring-0 focus-visible:ring-offset-0 inline-flex items-center" :disabled="stageUpdateForm.processing">
                                        {{ process.stage_label || process.stage || 'N/A' }}
                                        <ChevronDownIcon class="h-3 w-3 ml-0.5 opacity-70" v-if="!stageUpdateForm.processing" />
                                        <svg v-else class="animate-spin ml-1 h-3 w-3 text-indigo-600 dark:text-indigo-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                    </Button>
                                </DropdownMenuTrigger>
                                <DropdownMenuContent align="start">
                                    <DropdownMenuLabel>Mudar Estágio</DropdownMenuLabel>
                                    <DropdownMenuSeparator />
                                    <DropdownMenuRadioGroup v-model="stageUpdateForm.stage" @update:modelValue="updateStage">
                                        <DropdownMenuRadioItem
                                            v-for="stageOption in props.availableStages"
                                            :key="stageOption.key"
                                            :value="stageOption.key"
                                            class="text-xs"
                                            :disabled="stageUpdateForm.processing || stageUpdateForm.stage === stageOption.key"
                                        >
                                            {{ stageOption.label }}
                                        </DropdownMenuRadioItem>
                                    </DropdownMenuRadioGroup>
                                </DropdownMenuContent>
                            </DropdownMenu>
                            <span v-else class="ml-1">{{ process.stage_label || process.stage || 'N/A' }}</span>
                        </CardDescription>
                        <div v-if="stageUpdateForm.errors.stage" class="text-xs text-red-500 mt-1">
                            {{ stageUpdateForm.errors.stage }}
                        </div>
                        
                        <Separator class="my-3" />

                        <div class="text-sm space-y-1.5 text-gray-700 dark:text-gray-300">
                            <div v-if="process.contact" class="flex items-center">
                                <Users class="h-4 w-4 mr-2 text-gray-500 dark:text-gray-400 flex-shrink-0" />
                                <span class="font-medium mr-1">Contato:</span>
                                <Link :href="route('contacts.show', process.contact.id)" class="text-indigo-600 dark:text-indigo-400 hover:underline truncate">
                                    {{ process.contact.name || process.contact.business_name || 'N/A' }}
                                </Link>
                            </div>
                             <div v-if="process.responsible" class="flex items-center">
                                <UserCircle2 class="h-4 w-4 mr-2 text-gray-500 dark:text-gray-400 flex-shrink-0" />
                                <span class="font-medium mr-1">Responsável:</span> {{ process.responsible.name || 'N/A' }}
                            </div>
                            
                            <div class="flex items-center">
                                <CheckCircle class="h-4 w-4 mr-2 text-gray-500 dark:text-gray-400 flex-shrink-0" />
                                <span class="font-medium mr-1">Status:</span>
                                <DropdownMenu v-if="props.availableStatuses && props.availableStatuses.length > 0 && !isArchived">
                                    <DropdownMenuTrigger as-child>
                                        <Button variant="link" class="p-0 h-auto text-sm text-indigo-600 dark:text-indigo-400 hover:underline focus-visible:ring-0 focus-visible:ring-offset-0 inline-flex items-center" :disabled="statusUpdateForm.processing">
                                            {{ process.status_label || process.status || 'N/A' }}
                                            <ChevronDownIcon class="h-3 w-3 ml-0.5 opacity-70" v-if="!statusUpdateForm.processing" />
                                            <svg v-else class="animate-spin ml-1 h-3 w-3 text-indigo-600 dark:text-indigo-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="start">
                                        <DropdownMenuLabel>Mudar Status</DropdownMenuLabel>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuRadioGroup v-model="statusUpdateForm.status" @update:modelValue="updateProcessStatus">
                                            <DropdownMenuRadioItem
                                                v-for="statusOption in props.availableStatuses"
                                                :key="statusOption.key"
                                                :value="statusOption.key"
                                                class="text-xs"
                                                :disabled="statusUpdateForm.processing || statusUpdateForm.status === statusOption.key"
                                            >
                                                {{ statusOption.label }}
                                            </DropdownMenuRadioItem>
                                        </DropdownMenuRadioGroup>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                                <span v-else class="ml-1">{{ process.status_label || process.status || 'N/A' }}</span>
                                <div v-if="statusUpdateForm.errors.status" class="text-xs text-red-500 ml-2">
                                    {{ statusUpdateForm.errors.status }}
                                </div>
                            </div>

                            <div class="flex items-center">
                                <AlertTriangle class="h-4 w-4 mr-2 text-gray-500 dark:text-gray-400 flex-shrink-0" />
                                <span class="font-medium mr-1">Prioridade:</span>
                                <DropdownMenu v-if="props.availablePriorities && props.availablePriorities.length > 0 && !isArchived">
                                    <DropdownMenuTrigger as-child>
                                        <Button variant="link" class="p-0 h-auto text-sm focus-visible:ring-0 focus-visible:ring-offset-0 inline-flex items-center" :disabled="priorityUpdateForm.processing">
                                            <Badge :variant="priorityVariantForDisplay" class="text-xs">
                                                {{ priorityLabelForDisplay }}
                                            </Badge>
                                            <ChevronDownIcon class="h-3 w-3 ml-0.5 opacity-70 text-gray-600 dark:text-gray-400" v-if="!priorityUpdateForm.processing" />
                                            <svg v-else class="animate-spin ml-1 h-3 w-3 text-gray-600 dark:text-gray-400" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                            </svg>
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="start">
                                        <DropdownMenuLabel>Mudar Prioridade</DropdownMenuLabel>
                                        <DropdownMenuSeparator />
                                        <DropdownMenuRadioGroup v-model="priorityUpdateForm.priority" @update:modelValue="updateProcessPriority">
                                            <DropdownMenuRadioItem
                                                v-for="priorityOption in props.availablePriorities"
                                                :key="priorityOption.key"
                                                :value="priorityOption.key"
                                                class="text-xs"
                                                :disabled="priorityUpdateForm.processing || priorityUpdateForm.priority === priorityOption.key"
                                            >
                                                {{ priorityOption.label }}
                                            </DropdownMenuRadioItem>
                                        </DropdownMenuRadioGroup>
                                    </DropdownMenuContent>
                                </DropdownMenu>
                                <Badge v-else :variant="priorityVariantForDisplay" class="ml-1 text-xs">{{ priorityLabelForDisplay }}</Badge>
                                <div v-if="priorityUpdateForm.errors.priority" class="text-xs text-red-500 ml-2">
                                    {{ priorityUpdateForm.errors.priority }}
                                </div>
                            </div>

                            <div class="flex items-center">
                                <CalendarDays class="h-4 w-4 mr-2 text-gray-500 dark:text-gray-400 flex-shrink-0" />
                                <span class="font-medium mr-1">Criado em:</span> {{ formatDate(process.created_at) }}
                            </div>
                            <div v-if="process.due_date" class="flex items-center">
                                <Clock class="h-4 w-4 mr-2 text-gray-500 dark:text-gray-400 flex-shrink-0" />
                                <span class="font-medium mr-1">Vencimento:</span> {{ formatDate(process.due_date) }}
                            </div>
                             <div v-if="process.negotiated_value !== null && typeof process.negotiated_value !== 'undefined'" class="flex items-center">
                                <DollarSign class="h-4 w-4 mr-2 text-gray-500 dark:text-gray-400 flex-shrink-0" />
                                <span class="font-medium mr-1">Valor:</span> {{ formatCurrency(process.negotiated_value) }}
                            </div>
                             <div v-if="process.origin" class="flex items-center">
                                <LinkIcon class="h-4 w-4 mr-2 text-gray-500 dark:text-gray-400 flex-shrink-0" />
                                <span class="font-medium mr-1">Origem:</span> {{ process.origin }}
                            </div>
                        </div>
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader class="pb-3">
                        <div class="flex justify-between items-center">
                            <CardTitle class="text-lg">Anotações</CardTitle>
                            <Button variant="outline" size="sm" @click="showNewAnnotationForm = !showNewAnnotationForm" :disabled="isArchived">
                                <PlusCircle class="h-4 w-4 mr-2" /> Nova Anotação
                            </Button>
                        </div>
                    </CardHeader>
                    <CardContent class="space-y-3 text-sm">
                        <form v-if="showNewAnnotationForm" @submit.prevent="submitAnnotation" class="space-y-2 mb-4">
                            <Textarea
                                v-model="annotationForm.content"
                                placeholder="Digite sua anotação aqui..."
                                rows="3"
                                class="text-sm"
                                :disabled="isArchived"
                            />
                             <div v-if="annotationForm.errors.content" class="text-sm text-red-600 dark:text-red-400 mt-1">
                                {{ annotationForm.errors.content }}
                            </div>
                            <div class="flex justify-end space-x-2">
                                <Button type="button" variant="ghost" size="sm" @click="showNewAnnotationForm = false; annotationForm.reset('content'); annotationForm.clearErrors();">Cancelar</Button>
                                <Button type="submit" size="sm" :disabled="annotationForm.processing || isArchived">Salvar</Button>
                            </div>
                        </form>
                        
                        <div v-if="process.annotations && process.annotations.length > 0" class="space-y-3 max-h-96 overflow-y-auto pr-1">
                            <div v-for="annotation in process.annotations.slice().reverse()" :key="annotation.id" class="p-3 bg-gray-50 dark:bg-gray-700/50 rounded-md text-xs relative group">
                                <p class="text-gray-800 dark:text-gray-200 whitespace-pre-wrap">{{ annotation.content }}</p>
                                <p class="text-gray-500 dark:text-gray-400 mt-1 text-right">
                                    {{ annotation.user_name || annotation.user?.name || 'Sistema' }} - {{ formatDate(annotation.created_at, true) }}
                                </p>
                                 <Button
                                    variant="ghost"
                                    size="icon"
                                    class="absolute top-1 right-1 h-6 w-6 opacity-0 group-hover:opacity-100 transition-opacity"
                                    @click="openDeleteProcessAnnotationDialog(annotation)"
                                    title="Excluir anotação"
                                    :disabled="isArchived"
                                >
                                    <Trash2 class="h-3 w-3 text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-500" />
                                </Button>
                            </div>
                        </div>
                        <p v-else-if="!showNewAnnotationForm" class="text-gray-500 dark:text-gray-400 text-center py-4">Nenhuma anotação encontrada.</p>
                    </CardContent>
                </Card>
            </div>

            <div class="w-full lg:w-2/3 xl:w-3/4 flex flex-col overflow-hidden">
                 <div class="flex-shrink-0 border-b border-gray-200 dark:border-gray-700">
                    <nav class="-mb-px flex space-x-2 px-1" aria-label="Tabs">
                        <button @click="activeMainTab = 'tasks'"
                            :class="['whitespace-nowrap py-3 px-3 border-b-2 font-medium text-sm', activeMainTab === 'tasks' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200']">
                            TAREFAS ({{ process.tasks?.length || 0 }})
                        </button>
                        <button @click="activeMainTab = 'documents'"
                            :class="['whitespace-nowrap py-3 px-3 border-b-2 font-medium text-sm', activeMainTab === 'documents' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200']">
                            DOCUMENTOS ({{ documents.length }})
                        </button>
                        <button @click="activeMainTab = 'history'"
                            :class="['whitespace-nowrap py-3 px-3 border-b-2 font-medium text-sm', activeMainTab === 'history' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200']">
                            HISTÓRICO ({{ process.history_entries?.length || 0 }})
                        </button>
                    </nav>
                </div>
                
                <div class="flex-grow overflow-y-auto p-1 pr-2 no-scrollbar">
                    <div v-if="activeMainTab === 'tasks'" class="space-y-4 py-4">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Lista de Tarefas</h3>
                            <Button variant="outline" size="sm" @click="console.log('Abrir modal nova tarefa para processo ID:', process.id)" :disabled="isArchived">
                                <PlusCircle class="h-4 w-4 mr-2" /> Nova Tarefa
                            </Button>
                        </div>
                        <div v-if="process.tasks && process.tasks.length > 0" class="space-y-3">
                            <Card v-for="task in process.tasks" :key="task.id" class="hover:shadow-md transition-shadow">
                                <CardContent class="p-4 flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-gray-800 dark:text-gray-100">{{ task.title }}</p>
                                        <p v-if="task.description" class="text-xs text-gray-600 dark:text-gray-400 mt-0.5">{{ task.description }}</p>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1.5 flex items-center gap-2 flex-wrap">
                                            <span class="flex items-center"><UserCircle2 class="h-3.5 w-3.5 mr-1"/> {{ task.responsible_user?.name || 'N/A' }}</span>
                                            <span class="flex items-center"><Clock class="h-3.5 w-3.5 mr-1"/> {{ formatDate(task.due_date) }}</span>
                                        </div>
                                    </div>
                                    <div class="text-right flex-shrink-0">
                                        <Badge :variant="task.is_overdue ? 'destructive' : (task.status === 'Concluída' ? 'default' : 'outline')"
                                               class="text-xs whitespace-nowrap">
                                            {{ task.is_overdue ? 'Atrasada' : task.status }}
                                        </Badge>
                                       </div>
                                </CardContent>
                            </Card>
                        </div>
                        <p v-else class="text-sm text-gray-500 dark:text-gray-400 text-center py-8">Nenhuma tarefa para este caso.</p>
                    </div>

                    <div v-if="activeMainTab === 'documents'" class="space-y-4 py-4">
                         <div class="flex justify-between items-center">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Documentos</h3>
                             <Button variant="outline" size="sm" @click="console.log('Abrir modal upload documento para processo ID:', process.id)" :disabled="isArchived">
                                <PlusCircle class="h-4 w-4 mr-2" /> Adicionar Documento
                            </Button>
                        </div>
                         <div v-if="documents.length > 0" class="space-y-3">
                            <Card v-for="doc in documents" :key="doc.id" class="hover:shadow-md transition-shadow">
                                <CardContent class="p-3 flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-3 min-w-0">
                                        <Paperclip class="h-5 w-5 text-gray-500 dark:text-gray-400 flex-shrink-0" />
                                        <div class="flex-grow min-w-0">
                                            <a :href="doc.url" target="_blank" :download="doc.name" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline break-all">{{ doc.name }}</a>
                                            <p class="text-xs text-gray-500 dark:text-gray-400 truncate">
                                                Enviado em: {{ formatDate(doc.uploaded_at) }} {{ doc.size ? `(${doc.size})` : '' }}
                                            </p>
                                             <p v-if="doc.description" class="text-xs text-gray-600 dark:text-gray-400 mt-0.5 break-words">{{ doc.description }}</p>
                                        </div>
                                    </div>
                                    <div class="flex-shrink-0 space-x-1">
                                        <a :href="doc.url" target="_blank" :download="doc.name">
                                            <Button variant="ghost" size="icon" class="h-8 w-8" title="Baixar documento">
                                                <Download class="h-4 w-4 text-gray-500 hover:text-indigo-600" />
                                            </Button>
                                        </a>
                                        <Button variant="ghost" size="icon" class="h-8 w-8" @click="console.log('Abrir modal deletar documento ' + doc.id + ' para processo ID: ' + process.id)" title="Excluir documento" :disabled="isArchived">
                                            <Trash2 class="h-4 w-4 text-gray-500 hover:text-red-600" />
                                        </Button>
                                    </div>
                                </CardContent>
                            </Card>
                        </div>
                        <p v-else class="text-sm text-gray-500 dark:text-gray-400 text-center py-8">Nenhum documento anexado.</p>
                    </div>

                    <div v-if="activeMainTab === 'history'" class="space-y-4 py-4">
                         <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Histórico de Atividades</h3>
                         <div v-if="process.history_entries && process.history_entries.length > 0" class="space-y-3">
                            <Card v-for="entry in process.history_entries" :key="entry.id" class="bg-gray-50 dark:bg-gray-800/60">
                                <CardContent class="p-3 text-xs">
                                   <p><span class="font-semibold">{{ entry.user?.name || entry.user_name || 'Sistema' }}</span> {{ entry.action?.toLowerCase() || 'realizou uma ação' }}: <span class="text-gray-700 dark:text-gray-300">{{ entry.description }}</span></p>
                                   <p class="text-gray-500 dark:text-gray-400 mt-0.5">{{ formatDate(entry.created_at, true) }}</p>
                                </CardContent>
                            </Card>
                        </div>
                        <p v-else class="text-sm text-gray-500 dark:text-gray-400 text-center py-8">Nenhum histórico de atividades.</p>
                    </div>
                </div>
            </div>
        </div>

        <Dialog :open="showDeleteProcessDialog" @update:open="showDeleteProcessDialog = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Confirmar Exclusão do Caso</DialogTitle>
                    <DialogDescription>
                        Tem certeza de que deseja excluir o caso <strong class="font-medium">"{{ process.title }}"</strong>?
                        Esta ação não poderá ser desfeita e todos os dados associados (tarefas, documentos, anotações) também poderão ser afetados.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="mt-4 flex flex-col-reverse sm:flex-row sm:justify-end sm:space-x-2">
                    <Button variant="outline" type="button" @click="showDeleteProcessDialog = false">Cancelar</Button>
                    <Button variant="destructive" :disabled="processDeleteForm.processing" @click="submitDeleteProcess">
                        <svg v-if="processDeleteForm.processing" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ processDeleteForm.processing ? 'Excluindo...' : 'Confirmar Exclusão' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

        <Dialog :open="showDeleteProcessAnnotationDialog" @update:open="showDeleteProcessAnnotationDialog = $event">
            <DialogContent class="sm:max-w-md">
                <DialogHeader>
                    <DialogTitle>Confirmar Exclusão de Anotação</DialogTitle>
                    <DialogDescription v-if="processAnnotationToDelete">
                        Tem certeza de que deseja excluir esta anotação?
                        <blockquote class="mt-2 p-2 border-l-4 border-gray-300 bg-gray-50 dark:bg-gray-700 dark:border-gray-600 text-xs text-gray-600 dark:text-gray-300">
                            {{ processAnnotationToDelete.content.substring(0, 100) }}{{ processAnnotationToDelete.content.length > 100 ? '...' : '' }}
                        </blockquote>
                        Esta ação não poderá ser desfeita.
                    </DialogDescription>
                    <DialogDescription v-else>
                        Tem certeza de que deseja excluir esta anotação? Esta ação não poderá ser desfeita.
                    </DialogDescription>
                </DialogHeader>
                <DialogFooter class="mt-4 flex flex-col-reverse sm:flex-row sm:justify-end sm:space-x-2">
                    <Button variant="outline" type="button" @click="showDeleteProcessAnnotationDialog = false; processAnnotationToDelete = null;">Cancelar</Button>
                    <Button variant="destructive" :disabled="processAnnotationDeleteForm.processing" @click="submitDeleteProcessAnnotation">
                         <svg v-if="processAnnotationDeleteForm.processing" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                        </svg>
                        {{ processAnnotationDeleteForm.processing ? 'Excluindo...' : 'Confirmar Exclusão' }}
                    </Button>
                </DialogFooter>
            </DialogContent>
        </Dialog>

    </AppLayout>
</template>

<style scoped>
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;
    scrollbar-width: none;
}
</style>
