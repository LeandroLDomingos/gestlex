<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, useForm, usePage, router } from '@inertiajs/vue3'; // Adicionado router
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
} from '@/components/ui/dialog'; // Para diálogos de confirmação
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'; // Para o menu de 3 pontos
import {
    Edit, Trash2, PlusCircle, Paperclip, Clock, UserCircle2,
    MessageSquare, History, Briefcase, DollarSign, Users,
    CalendarDays, AlertTriangle, CheckCircle, Zap, MoreVertical, Archive // Ícones adicionados
} from 'lucide-vue-next';
import type { Process, ProcessAnnotation, ProcessTask, ProcessDocument, ProcessHistoryEntry, BreadcrumbItem } from '@/types/process';

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

const props = defineProps<{
    process: Process;
    // users: UserReference[];
    // can: { ... }
}>();

const activeMainTab = ref<'tasks' | 'documents' | 'history'>('tasks');
const showNewAnnotationForm = ref(false);

// Estado para diálogo de exclusão do processo
const showDeleteProcessDialog = ref(false);
const processDeleteForm = useForm({});

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Painel', href: route('dashboard') },
    { title: 'Casos', href: route('processes.index') },
    { title: props.process.workflow_label || props.process.workflow, href: route('processes.index', { workflow: props.process.workflow }) },
    { title: props.process.title, href: route('processes.show', props.process.id) },
]);

const formatDate = (dateString: string | null | undefined, includeTime = false): string => {
    if (!dateString) return 'N/A';
    try {
        const date = new Date(dateString.includes('T') || dateString.includes('Z') ? dateString : dateString + 'T00:00:00Z');
        const options: Intl.DateTimeFormatOptions = {
            day: '2-digit', month: '2-digit', year: 'numeric', timeZone: 'UTC'
        };
        if (includeTime) {
            options.hour = '2-digit';
            options.minute = '2-digit';
        }
        return date.toLocaleDateString('pt-BR', options);
    } catch (e) {
        console.error("Erro ao formatar data:", dateString, e);
        return dateString;
    }
};

const formatCurrency = (value: number | null | undefined): string => {
    if (value === null || typeof value === 'undefined') return 'N/A';
    return value.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
};

const priorityLabel = computed(() => {
    if (!props.process.priority) return 'N/A';
    switch (props.process.priority.toLowerCase()) {
        case 'high': return 'Alta';
        case 'medium': return 'Média';
        case 'low': return 'Baixa';
        default: return props.process.priority;
    }
});

const priorityVariant = computed(() => {
    if (!props.process.priority) return 'outline';
    switch (props.process.priority.toLowerCase()) {
        case 'high': return 'destructive';
        case 'medium': return 'secondary';
        case 'low': return 'outline';
        default: return 'outline';
    }
});

const annotationForm = useForm({
    content: '',
    process_id: props.process.id,
});

function submitAnnotation() {
    annotationForm.post(route('processes.annotations.store', props.process.id), { // Corrigido para 'processes.annotations.store'
        preserveScroll: true,
        onSuccess: () => {
            annotationForm.reset('content');
            showNewAnnotationForm.value = false;
            router.reload({ only: ['process'] }); // Recarrega a prop 'process' para atualizar anotações
        },
        onError: (errors) => {
            console.error("Erro ao salvar anotação:", errors);
        }
    });
}

// Funções para o menu de ações do Processo
function editProcess() {
    router.visit(route('processes.edit', props.process.id));
}

function archiveProcess() {
    // Lógica para arquivar o processo (ex: chamada API)
    // Por enquanto, apenas um console.log
    console.log('Arquivar processo:', props.process.id);
    // Exemplo de como poderia ser com useForm:
    // const archiveForm = useForm({});
    // archiveForm.patch(route('processes.archive', props.process.id), {
    //   onSuccess: () => { /* router.reload() ou feedback */ }
    // });
    alert('Funcionalidade de arquivar ainda não implementada no backend.');
}

function openDeleteProcessDialog() {
    showDeleteProcessDialog.value = true;
}

function submitDeleteProcess() {
    processDeleteForm.delete(route('processes.destroy', props.process.id), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteProcessDialog.value = false;
            // O Inertia deve redirecionar para a lista de processos após a exclusão bem-sucedida (configurado no controller)
        },
        onError: (errors) => {
            console.error('Erro ao excluir processo:', errors);
            // Adicionar feedback de erro, se necessário
        }
    });
}


const documents = ref<ProcessDocument[]>(props.process.documents || []);
const historyEntries = ref<ProcessHistoryEntry[]>(props.process.history_entries || []);

</script>

<template>
    <Head :title="`Caso: ${process.title}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col lg:flex-row gap-6 p-4 md:p-6 h-[calc(100vh-theme(spacing.16)-theme(spacing.1))] overflow-hidden">
            <div class="w-full lg:w-1/3 xl:w-1/4 space-y-6 flex-shrink-0 overflow-y-auto pr-2 no-scrollbar">
                <Card class="overflow-hidden shadow-lg">
                    <div class="bg-gradient-to-br from-blue-500 to-indigo-600 dark:from-blue-700 dark:to-indigo-800 h-32 sm:h-40 md:h-48 flex items-center justify-center p-4 relative">
                        <Briefcase v-if="process.workflow_label?.toLowerCase().includes('judicial')" class="h-16 w-16 text-white opacity-75" />
                        <MessageSquare v-else-if="process.workflow_label?.toLowerCase().includes('consultivo')" class="h-16 w-16 text-white opacity-75" />
                        <Zap v-else-if="process.workflow_label?.toLowerCase().includes('prospecção')" class="h-16 w-16 text-white opacity-75" />
                        <Paperclip v-else class="h-16 w-16 text-white opacity-75" />

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
                                    <DropdownMenuItem @click="editProcess">
                                        <Edit class="mr-2 h-4 w-4" />
                                        <span>Editar</span>
                                    </DropdownMenuItem>
                                    <DropdownMenuItem @click="archiveProcess">
                                        <Archive class="mr-2 h-4 w-4" />
                                        <span>Arquivar</span>
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
                        <CardTitle class="text-lg font-semibold text-gray-800 dark:text-gray-100 truncate" :title="process.title">
                            {{ process.title }}
                        </CardTitle>
                        <CardDescription class="text-xs text-gray-600 dark:text-gray-400">
                            {{ process.workflow_label || process.workflow }} - Estágio: {{ process.stage_label || process.stage || 'N/A' }}
                        </CardDescription>
                        
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
                             <div v-if="process.status" class="flex items-center">
                                <CheckCircle class="h-4 w-4 mr-2 text-gray-500 dark:text-gray-400 flex-shrink-0" />
                                <span class="font-medium mr-1">Status:</span> {{ process.status }}
                            </div>
                            <div class="flex items-center">
                                <AlertTriangle class="h-4 w-4 mr-2 text-gray-500 dark:text-gray-400 flex-shrink-0" />
                                <span class="font-medium mr-1">Prioridade:</span>
                                <Badge :variant="priorityVariant" class="ml-1 text-xs">{{ priorityLabel }}</Badge>
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
                            <Button variant="outline" size="sm" @click="showNewAnnotationForm = !showNewAnnotationForm">
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
                            />
                             <div v-if="annotationForm.errors.content" class="text-sm text-red-600 dark:text-red-400 mt-1">
                                {{ annotationForm.errors.content }}
                            </div>
                            <div class="flex justify-end space-x-2">
                                <Button type="button" variant="ghost" size="sm" @click="showNewAnnotationForm = false; annotationForm.reset('content'); annotationForm.clearErrors();">Cancelar</Button>
                                <Button type="submit" size="sm" :disabled="annotationForm.processing">Salvar</Button>
                            </div>
                        </form>

                        <div v-if="process.annotations && process.annotations.length > 0" class="space-y-3 max-h-96 overflow-y-auto pr-1">
                            <div v-for="annotation in process.annotations.slice().reverse()" :key="annotation.id" class="p-3 bg-gray-50 dark:bg-gray-700/50 rounded-md text-xs relative group">
                                <p class="text-gray-800 dark:text-gray-200 whitespace-pre-wrap">{{ annotation.content }}</p>
                                <p class="text-gray-500 dark:text-gray-400 mt-1 text-right">
                                    {{ annotation.user_name || annotation.user?.name || 'Sistema' }} - {{ formatDate(annotation.created_at, true) }}
                                </p>
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
                            HISTÓRICO ({{ historyEntries.length }})
                        </button>
                    </nav>
                </div>

                <div class="flex-grow overflow-y-auto p-1 pr-2 no-scrollbar">
                    <div v-if="activeMainTab === 'tasks'" class="space-y-4 py-4">
                        <div class="flex justify-between items-center">
                            <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Lista de Tarefas</h3>
                            <Button variant="outline" size="sm" @click="console.log('Abrir modal nova tarefa')">
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
                             <Button variant="outline" size="sm" @click="console.log('Abrir modal upload documento para processo')">
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
                                        <Button variant="ghost" size="icon" class="h-8 w-8" @click="console.log('Abrir modal deletar documento ' + doc.id)" title="Excluir documento">
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
                         <div v-if="historyEntries.length > 0" class="space-y-3">
                            <Card v-for="entry in historyEntries" :key="entry.id" class="bg-gray-50 dark:bg-gray-800/60">
                                <CardContent class="p-3 text-xs">
                                   <p><span class="font-semibold">{{ entry.user_name }}</span> {{ entry.action.toLowerCase() }}: <span class="text-gray-700 dark:text-gray-300">{{ entry.description }}</span></p>
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
