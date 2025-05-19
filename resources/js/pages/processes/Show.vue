<script setup lang="ts">
import { ref, computed } from 'vue';
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import AppLayout from '@/layouts/AppLayout.vue';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge'; // Para status de tarefa
import { Separator } from '@/components/ui/separator';
import { Edit, Trash2, PlusCircle, Paperclip, Clock, UserCircle2, MessageSquare, History } from 'lucide-vue-next';
import type { Process, ProcessAnnotation, ProcessTask, ProcessDocument, ProcessHistoryEntry, BreadcrumbItem } from '@/types/process'; // Ajuste o caminho se os tipos estiverem em outro lugar

// Helper para Ziggy
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
    // users: UserReference[]; // Para dropdown de responsáveis em Nova Tarefa
    // can: { // Exemplo de permissões
    //     edit_process: boolean;
    //     delete_process: boolean;
    //     add_task: boolean;
    //     add_annotation: boolean;
    // }
}>();

const activeMainTab = ref<'tasks' | 'documents' | 'history'>('tasks');
const newAnnotationText = ref('');
const showNewAnnotationForm = ref(false);

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
    { title: 'Painel', href: route('dashboard') },
    { title: 'Casos', href: route('processes.index') },
    { title: props.process.workflow_label || props.process.workflow, href: route('processes.index', { workflow: props.process.workflow }) },
    { title: props.process.title, href: route('processes.show', props.process.id) },
]);

const formatDate = (dateString: string | null | undefined, includeTime = false): string => {
    if (!dateString) return 'N/A';
    try {
        const date = new Date(dateString.includes('T') ? dateString : dateString + 'T00:00:00Z');
        const options: Intl.DateTimeFormatOptions = {
            day: '2-digit', month: '2-digit', year: 'numeric'
        };
        if (includeTime) {
            options.hour = '2-digit';
            options.minute = '2-digit';
        }
        return date.toLocaleDateString('pt-BR', options);
    } catch (e) {
        return dateString;
    }
};

// Formulário para nova anotação
const annotationForm = useForm({
    content: '',
    process_id: props.process.id,
});

function submitAnnotation() {
    annotationForm.post(route('process.annotations.store', props.process.id), { // Ajuste a rota
        preserveScroll: true,
        onSuccess: () => {
            annotationForm.reset('content');
            showNewAnnotationForm.value = false;
            // Idealmente, o backend retornaria o processo atualizado com a nova anotação,
            // ou você faria um router.reload({ only: ['process'] })
        },
    });
}

// Simulação de dados para abas não implementadas
const documents = ref<ProcessDocument[]>(props.process.documents || [
    // { id: 1, name: 'Petição Inicial.pdf', url: '#', uploaded_at: '2024-05-10T10:00:00Z', file_type_icon: 'pdf', size: '1.2MB' },
    // { id: 2, name: 'Procuração Assinada.docx', url: '#', uploaded_at: '2024-05-11T14:30:00Z', file_type_icon: 'doc', size: '80KB' },
]);
const historyEntries = ref<ProcessHistoryEntry[]>(props.process.history_entries || [
    // { id: 1, action: 'Caso Criado', description: 'Caso iniciado por Fernanda Loren.', user_name: 'Sistema', created_at: '2024-05-10T09:00:00Z' },
    // { id: 2, action: 'Tarefa Adicionada', description: 'Solicitar PPP junto aos empregadores.', user_name: 'Fernanda Loren', created_at: '2024-05-10T09:15:00Z' },
]);

</script>

<template>
    <Head :title="`Caso: ${process.title}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="flex flex-col lg:flex-row gap-6 p-4 md:p-6 h-[calc(100vh-theme(spacing.16)-theme(spacing.1))] overflow-hidden">
            <div class="w-full lg:w-1/3 xl:w-1/4 space-y-6 flex-shrink-0 overflow-y-auto pr-2 no-scrollbar">
                <Card class="overflow-hidden">
                    <div class="bg-blue-500 dark:bg-blue-700 h-48 flex items-center justify-center">
                        <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1" stroke-linecap="round" stroke-linejoin="round" class="text-white opacity-50"><rect x="2" y="2" width="20" height="20" rx="2.18" ry="2.18"></rect><line x1="7" y1="2" x2="7" y2="22"></line><line x1="17" y1="2" x2="17" y2="22"></line><line x1="2" y1="12" x2="22" y2="12"></line><line x1="2" y1="7" x2="7" y2="7"></line><line x1="2" y1="17" x2="7" y2="17"></line><line x1="17" y1="17" x2="22" y2="17"></line><line x1="17" y1="7" x2="22" y2="7"></line></svg>
                    </div>
                    <CardContent class="p-4">
                        <CardTitle class="text-base">{{ process.title }}</CardTitle>
                        <CardDescription class="text-xs">
                            {{ process.workflow_label || process.workflow }} - Estágio: {{ process.stage_label || process.stage || 'N/A' }}
                        </CardDescription>
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
                            <InputError :message="annotationForm.errors.content" class="mt-1" />
                            <div class="flex justify-end space-x-2">
                                <Button type="button" variant="ghost" size="sm" @click="showNewAnnotationForm = false; annotationForm.reset('content');">Cancelar</Button>
                                <Button type="submit" size="sm" :disabled="annotationForm.processing">Salvar</Button>
                            </div>
                        </form>

                        <div v-if="process.annotations && process.annotations.length > 0" class="space-y-3 max-h-96 overflow-y-auto">
                            <div v-for="annotation in process.annotations.slice().reverse()" :key="annotation.id" class="p-3 bg-gray-50 dark:bg-gray-700/50 rounded-md text-xs">
                                <p class="text-gray-800 dark:text-gray-200 whitespace-pre-wrap">{{ annotation.content }}</p>
                                <p class="text-gray-500 dark:text-gray-400 mt-1 text-right">
                                    {{ annotation.user_name }} - {{ formatDate(annotation.created_at, true) }}
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
                            <Button variant="outline" size="sm">
                                <PlusCircle class="h-4 w-4 mr-2" /> Nova Tarefa
                            </Button>
                        </div>
                        <div v-if="process.tasks && process.tasks.length > 0" class="space-y-3">
                            <Card v-for="task in process.tasks" :key="task.id" class="hover:shadow-md transition-shadow">
                                <CardContent class="p-4 flex items-start justify-between gap-3">
                                    <div>
                                        <p class="font-semibold text-gray-800 dark:text-gray-100">{{ task.title }}</p>
                                        <p v-if="task.description" class="text-xs text-gray-600 dark:text-gray-400 mt-0.5">{{ task.description }}</p>
                                        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1.5 flex items-center gap-2">
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
                             <Button variant="outline" size="sm">
                                <PlusCircle class="h-4 w-4 mr-2" /> Adicionar Documento
                            </Button>
                        </div>
                         <div v-if="documents.length > 0" class="space-y-3">
                            <Card v-for="doc in documents" :key="doc.id" class="hover:shadow-md transition-shadow">
                                <CardContent class="p-3 flex items-center justify-between gap-3">
                                    <div class="flex items-center gap-3">
                                        <Paperclip class="h-5 w-5 text-gray-500 dark:text-gray-400" />
                                        <div>
                                            <a :href="doc.url" target="_blank" class="font-medium text-indigo-600 dark:text-indigo-400 hover:underline">{{ doc.name }}</a>
                                            <p class="text-xs text-gray-500 dark:text-gray-400">
                                                Enviado em: {{ formatDate(doc.uploaded_at) }} {{ doc.size ? `(${doc.size})` : '' }}
                                            </p>
                                        </div>
                                    </div>
                                    <Button variant="ghost" size="icon" class="h-8 w-8">
                                        <Trash2 class="h-4 w-4 text-gray-500 hover:text-red-600" />
                                    </Button>
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
    </AppLayout>
</template>

<style scoped>
/* Para esconder a barra de rolagem se necessário */
.no-scrollbar::-webkit-scrollbar {
    display: none;
}
.no-scrollbar {
    -ms-overflow-style: none;  /* IE and Edge */
    scrollbar-width: none;  /* Firefox */
}
</style>
