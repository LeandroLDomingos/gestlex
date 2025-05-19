<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Head, Link, usePage, useForm, router } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import { Button } from '@/components/ui/button'
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
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { Textarea } from '@/components/ui/textarea';
import { Badge } from '@/components/ui/badge';
import { Separator } from '@/components/ui/separator';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Edit, Trash2, PlusCircle, Paperclip, UserCircle2, MessageSquare, History, LinkIcon, UploadCloud, Download } from 'lucide-vue-next';

// Importando os tipos de um ficheiro dedicado (ex: @/types/index.ts ou @/types)
// Certifique-se de que o caminho para o seu ficheiro de tipos está correto.
import type {
    Address,
    ContactEmail,
    ContactPhone,
    ContactAnnotation,
    ContactDocument,
    RelatedProcess,
    Contact,
    BreadcrumbItem,
} from '@/types'; // Ou '@/types/index.ts' ou o caminho correto

const props = defineProps<{
  contact: Contact;
}>();

const activeTab = ref<'details' | 'documents' | 'cases' | 'history'>('details');
const deleteForm = useForm({});
const showNewAnnotationForm = ref(false);
const showUploadDocumentDialog = ref(false);

// Estado para exclusão de documento
const showDeleteDocumentDialog = ref(false);
const documentToDelete = ref<ContactDocument | null>(null);
const documentDeleteForm = useForm({});

// Estado para exclusão de anotação
const showDeleteAnnotationDialog = ref(false);
const annotationToDelete = ref<ContactAnnotation | null>(null);
const annotationDeleteForm = useForm({});


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

function getContactDisplayName(contact?: Contact): string {
    if (!contact) return 'N/A';
    return contact.name || contact.business_name || 'N/A';
}

const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Contatos', href: routeHelper('contacts.index') },
  {
    title: getContactDisplayName(props.contact),
    href: routeHelper('contacts.show', props.contact.id),
  },
]);

function editContact() {
  if (typeof routeHelper === 'function') {
    router.visit(routeHelper('contacts.edit', props.contact.id));
  } else {
    console.error('routeHelper function is not available for editContact');
  }
}

function confirmDeleteContact() {
  if (typeof routeHelper === 'function') {
    deleteForm.delete(routeHelper('contacts.destroy', props.contact.id), {
      preserveScroll: true,
      onSuccess: () => { /* Feedback handled by backend flash message */ },
      onError: (errors) => {
        console.error('Erro ao excluir contato:', errors);
      },
    });
  }
}

const annotationForm = useForm({
    content: '',
});

function submitAnnotation() {
    annotationForm.post(routeHelper('contacts.annotations.store', props.contact.id), {
        preserveScroll: true,
        onSuccess: () => {
            annotationForm.reset('content');
            showNewAnnotationForm.value = false;
            router.reload({ only: ['contact'], preserveScroll: true });
        },
        onError: (errors) => {
            console.error('Erro ao salvar anotação:', errors);
        }
    });
}

const documentForm = useForm<{
    file: File | null;
    description: string;
}>({
    file: null,
    description: '',
});

const fileInputRef = ref<HTMLInputElement | null>(null);

function submitDocument() {
    if (!documentForm.file) {
        documentForm.setError('file', 'Por favor, selecione um arquivo.');
        return;
    }
    documentForm.post(routeHelper('contacts.documents.store', props.contact.id), {
        preserveScroll: true,
        onSuccess: () => {
            documentForm.reset();
            if (fileInputRef.value) {
                fileInputRef.value.value = '';
            }
            showUploadDocumentDialog.value = false;
            router.reload({ only: ['contact'], preserveScroll: true });
        },
        onError: (errors) => {
            console.error('Erro ao enviar documento:', errors);
        },
        forceFormData: true,
    });
}

function openDeleteDocumentDialog(doc: ContactDocument) {
    documentToDelete.value = doc;
    showDeleteDocumentDialog.value = true;
}

function submitDeleteDocument() {
    if (!documentToDelete.value) return;
    const routeParams = { contact: props.contact.id, document: documentToDelete.value.id };
    documentDeleteForm.delete(routeHelper('contacts.documents.destroy', routeParams), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteDocumentDialog.value = false;
            documentToDelete.value = null;
            router.reload({ only: ['contact'], preserveScroll: true });
        },
        onError: (errors) => {
            console.error('Erro ao excluir documento:', errors);
        }
    });
}

function openDeleteAnnotationDialog(annotation: ContactAnnotation) {
    annotationToDelete.value = annotation;
    showDeleteAnnotationDialog.value = true;
}

function submitDeleteAnnotation() {
    if (!annotationToDelete.value) return;
    const routeParams = { contact: props.contact.id, annotation: annotationToDelete.value.id };
    annotationDeleteForm.delete(routeHelper('contacts.annotations.destroy', routeParams), {
        preserveScroll: true,
        onSuccess: () => {
            showDeleteAnnotationDialog.value = false;
            annotationToDelete.value = null;
            router.reload({ only: ['contact'], preserveScroll: true });
        },
        onError: (errors) => {
            console.error('Erro ao excluir anotação:', errors);
        }
    });
}


function formatIdentifier(contact?: Contact): string {
    if (!contact || !contact.cpf_cnpj) return 'N/A';
    const cleanedCpfCnpj = String(contact.cpf_cnpj).replace(/\D/g, '');
    if (contact.type === 'physical' && cleanedCpfCnpj.length === 11) {
        return cleanedCpfCnpj.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
    } else if (contact.type === 'legal' && cleanedCpfCnpj.length === 14) {
        return cleanedCpfCnpj.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
    }
    return contact.cpf_cnpj;
}

function formatDate(dateString?: string | null, includeTime = false): string {
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
}

function formatPhoneNumber(phoneString?: string | null): string {
    if (!phoneString) return 'N/A';
    const cleaned = String(phoneString).replace(/\D/g, '');
    if (cleaned.length === 11) { return cleaned.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3'); }
    if (cleaned.length === 10) { return cleaned.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3'); }
    if (cleaned.length === 9 && cleaned.startsWith('9')) { return cleaned.replace(/(\d{5})(\d{4})/, '$1-$2'); }
    if (cleaned.length === 8) { return cleaned.replace(/(\d{4})(\d{4})/, '$1-$2'); }
    return phoneString;
}

const route = routeHelper;

onMounted(() => {
  // console.log('Componente Show.vue montado. Props:', props);
});

</script>

<template>
  <Head :title="`Detalhes: ${getContactDisplayName(contact)}`" />
  <AppLayout :breadcrumbs="breadcrumbs">
    <div class="container mx-auto p-4 sm:p-6 lg:p-8">
      <div class="mb-6 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <h1 class="text-2xl sm:text-3xl font-bold text-gray-800 dark:text-gray-100">
            {{ getContactDisplayName(contact) }}
          </h1>
          <p class="text-sm text-gray-600 dark:text-gray-400">
            {{ contact.type === 'physical' ? 'Pessoa Física' : 'Pessoa Jurídica' }}
            <span v-if="contact.type === 'legal' && contact.business_name && contact.name !== contact.business_name">
              (Razão Social: {{ contact.business_name }})
            </span>
          </p>
        </div>
        <div class="flex space-x-2">
          <Button @click="editContact" variant="default" size="sm">
            <Edit class="mr-2 h-4 w-4" /> Editar
          </Button>
          <Dialog>
            <DialogTrigger as-child>
              <Button variant="destructive" size="sm" :disabled="deleteForm.processing">
                <Trash2 class="mr-2 h-4 w-4" /> Deletar Contato
              </Button>
            </DialogTrigger>
            <DialogContent class="sm:max-w-md">
              <DialogHeader>
                <DialogTitle>Confirmar Exclusão do Contato</DialogTitle>
                <DialogDescription>
                  Esta ação excluirá o contato <strong class="font-medium">{{ getContactDisplayName(contact) }}</strong>
                  e todos os seus dados relacionados de forma permanente. Tem certeza de que deseja continuar?
                </DialogDescription>
              </DialogHeader>
              <DialogFooter class="mt-4 flex flex-col-reverse sm:flex-row sm:justify-end sm:space-x-2">
                <DialogClose as-child>
                  <Button variant="outline" type="button">Cancelar</Button>
                </DialogClose>
                <Button variant="destructive" :disabled="deleteForm.processing" @click="confirmDeleteContact">
                  Confirmar Exclusão
                </Button>
              </DialogFooter>
            </DialogContent>
          </Dialog>
        </div>
      </div>

      <div class="flex flex-col lg:flex-row gap-6">
        <div class="w-full lg:w-1/3 xl:w-1/4 space-y-6 flex-shrink-0">
            <Card>
                <CardHeader><CardTitle class="text-base">Informações Rápidas</CardTitle></CardHeader>
                <CardContent class="text-sm space-y-1">
                    <p><strong class="font-medium">Tipo:</strong> {{ contact.type === 'physical' ? 'Pessoa Física' : 'Pessoa Jurídica' }}</p>
                    <p v-if="contact.type === 'physical'"><strong class="font-medium">CPF:</strong> {{ formatIdentifier(contact) }}</p>
                    <p v-if="contact.type === 'legal'"><strong class="font-medium">CNPJ:</strong> {{ formatIdentifier(contact) }}</p>
                    <p v-if="contact.type === 'legal' && contact.business_name"><strong class="font-medium">Razão Social:</strong> {{ contact.business_name }}</p>
                    <p v-if="contact.phones && contact.phones.length > 0"><strong class="font-medium">Telefone:</strong> {{ formatPhoneNumber(contact.phones[0].phone) }}</p>
                    <p v-else><strong class="font-medium">Telefone:</strong> N/A</p>
                    <p v-if="contact.emails && contact.emails.length > 0"><strong class="font-medium">Email:</strong> {{ contact.emails[0].email }}</p>
                    <p v-else><strong class="font-medium">Email:</strong> N/A</p>
                </CardContent>
            </Card>

            <Card>
                <CardHeader class="pb-3">
                    <div class="flex justify-between items-center">
                        <CardTitle class="text-lg">Anotações</CardTitle>
                        <Button variant="outline" size="sm" @click="showNewAnnotationForm = !showNewAnnotationForm">
                            <PlusCircle class="h-4 w-4 mr-2" /> Nova
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

                    <div v-if="contact.annotations && contact.annotations.length > 0" class="space-y-3 max-h-96 overflow-y-auto pr-1">
                        <div v-for="annotation in contact.annotations.slice().reverse()" :key="annotation.id" class="p-3 bg-gray-50 dark:bg-gray-700/50 rounded-md text-xs relative group">
                            <p class="text-gray-800 dark:text-gray-200 whitespace-pre-wrap">{{ annotation.content }}</p>
                            <p class="text-gray-500 dark:text-gray-400 mt-1 text-right">
                                {{ annotation.user_name || annotation.user?.name || 'Sistema' }} - {{ formatDate(annotation.created_at, true) }}
                            </p>
                            <Button
                                variant="ghost"
                                size="icon"
                                class="absolute top-1 right-1 h-6 w-6 opacity-0 group-hover:opacity-100 transition-opacity"
                                @click="openDeleteAnnotationDialog(annotation)"
                                title="Excluir anotação"
                            >
                                <Trash2 class="h-3 w-3 text-gray-500 hover:text-red-600 dark:text-gray-400 dark:hover:text-red-500" />
                            </Button>
                        </div>
                    </div>
                    <p v-else-if="!showNewAnnotationForm" class="text-gray-500 dark:text-gray-400 text-center py-4">Nenhuma anotação encontrada.</p>
                </CardContent>
            </Card>
        </div>

        <div class="w-full lg:w-2/3 xl:w-3/4 bg-white dark:bg-gray-800 shadow-xl rounded-lg overflow-hidden">
          <div class="border-b border-gray-200 dark:border-gray-700">
            <nav class="-mb-px flex space-x-px" aria-label="Tabs">
              <button @click="activeTab = 'details'" :class="['flex-1 py-4 px-1 text-center border-b-2 font-medium text-sm whitespace-nowrap', activeTab === 'details' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:border-gray-600']">
                Detalhes
              </button>
              <button @click="activeTab = 'documents'" :class="['flex-1 py-4 px-1 text-center border-b-2 font-medium text-sm whitespace-nowrap', activeTab === 'documents' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:border-gray-600']">
                Documentos ({{ contact.documents?.length || 0 }})
              </button>
              <button @click="activeTab = 'cases'" :class="['flex-1 py-4 px-1 text-center border-b-2 font-medium text-sm whitespace-nowrap', activeTab === 'cases' ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400' : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:border-gray-600']">
                Casos Vinculados ({{ contact.processes?.length || 0 }})
              </button>
              </nav>
          </div>

          <div class="p-6 overflow-y-auto h-[calc(100%-theme(spacing.12))]">
            <div v-if="activeTab === 'details'" class="space-y-6">
                <div v-if="contact.type === 'physical'">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Dados Pessoais</h3>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-4">
                    <div class="sm:col-span-1"><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Nome Completo</dt><dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">{{ contact.name || 'N/A' }}</dd></div>
                    <div class="sm:col-span-1"><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">CPF</dt><dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">{{ formatIdentifier(contact) }}</dd></div>
                    <div class="sm:col-span-1"><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">RG</dt><dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">{{ contact.rg || 'N/A' }}</dd></div>
                    <div class="sm:col-span-1"><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Data de Nascimento</dt><dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">{{ formatDate(contact.date_of_birth) }}</dd></div>
                    <div class="sm:col-span-1"><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Gênero</dt><dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">{{ contact.gender_label || contact.gender || 'N/A' }}</dd></div>
                    <div class="sm:col-span-1"><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Nacionalidade</dt><dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">{{ contact.nationality || 'N/A' }}</dd></div>
                    <div class="sm:col-span-1"><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Estado Civil</dt><dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">{{ contact.marital_status_label || contact.marital_status || 'N/A' }}</dd></div>
                    <div class="sm:col-span-1"><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Profissão</dt><dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">{{ contact.profession || 'N/A' }}</dd></div>
                    </dl>
                </div>
                <div v-if="contact.type === 'legal'">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Dados da Empresa</h3>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-4">
                    <div class="sm:col-span-1"><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Nome Fantasia</dt><dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">{{ contact.name || 'N/A' }}</dd></div>
                    <div class="sm:col-span-1"><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Razão Social</dt><dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">{{ contact.business_name || 'N/A' }}</dd></div>
                    <div class="sm:col-span-1"><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">CNPJ</dt><dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">{{ formatIdentifier(contact) }}</dd></div>
                    <div class="sm:col-span-1"><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Atividade Principal</dt><dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">{{ contact.business_activity || 'N/A' }}</dd></div>
                    <div class="sm:col-span-1"><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Estado de Tributação</dt><dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">{{ contact.tax_state || 'N/A' }}</dd></div>
                    <div class="sm:col-span-1"><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Município de Tributação</dt><dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">{{ contact.tax_city || 'N/A' }}</dd></div>
                    <div v-if="contact.administrator_id" class="sm:col-span-2"><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Administrador ID</dt><dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">{{ contact.administrator_id }}</dd></div>
                    </dl>
                </div>
                <div class="pt-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Endereço</h3>
                    <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-4" v-if="contact.address">
                    <div class="sm:col-span-2"><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Logradouro</dt><dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">{{ contact.street || 'N/A' }}{{ contact.number ? ', ' + contact.number : '' }}</dd></div>
                    <div class="sm:col-span-1"><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Complemento</dt><dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">{{ contact.complement || 'N/A' }}</dd></div>
                    <div class="sm:col-span-1"><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Bairro</dt><dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">{{ contact.neighborhood || 'N/A' }}</dd></div>
                    <div class="sm:col-span-1"><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Cidade</dt><dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">{{ contact.city || 'N/A' }}</dd></div>
                    <div class="sm:col-span-1"><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Estado</dt><dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">{{ contact.state || 'N/A' }}</dd></div>
                    <div class="sm:col-span-1"><dt class="text-sm font-medium text-gray-500 dark:text-gray-400">CEP</dt><dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">{{ contact.zip_code || 'N/A' }}</dd></div>
                    </dl>
                    <p v-else class="text-sm text-gray-500 dark:text-gray-400">Endereço não cadastrado.</p>
                </div>
                <div class="pt-4">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Informações de Contato Adicionais</h3>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                    <div>
                        <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">E-mails</h4>
                        <ul v-if="contact.emails && contact.emails.length > 0" class="list-disc list-inside space-y-1">
                        <li v-for="emailObj in contact.emails" :key="emailObj.id || emailObj.email" class="text-sm text-gray-700 dark:text-gray-300 break-all">{{ emailObj.email }}</li>
                        </ul>
                        <p v-else class="text-sm text-gray-500 dark:text-gray-400">Nenhum e-mail adicional cadastrado.</p>
                    </div>
                    <div>
                        <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Telefones</h4>
                        <ul v-if="contact.phones && contact.phones.length > 0" class="list-disc list-inside space-y-1">
                        <li v-for="phoneObj in contact.phones" :key="phoneObj.id || phoneObj.phone" class="text-sm text-gray-700 dark:text-gray-300">{{ formatPhoneNumber(phoneObj.phone) }}</li>
                        </ul>
                        <p v-else class="text-sm text-gray-500 dark:text-gray-400">Nenhum telefone adicional cadastrado.</p>
                    </div>
                    </div>
                </div>
            </div>

            <div v-if="activeTab === 'documents'" class="space-y-4 py-4">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Documentos do Contato</h3>
                    <Dialog :open="showUploadDocumentDialog" @update:open="showUploadDocumentDialog = $event">
                        <DialogTrigger as-child>
                            <Button variant="outline" size="sm" @click="showUploadDocumentDialog = true">
                                <PlusCircle class="h-4 w-4 mr-2" /> Adicionar Documento
                            </Button>
                        </DialogTrigger>
                        <DialogContent class="sm:max-w-lg">
                            <DialogHeader>
                                <DialogTitle>Adicionar Novo Documento</DialogTitle>
                                <DialogDescription>
                                    Selecione um arquivo e adicione uma descrição opcional.
                                </DialogDescription>
                            </DialogHeader>
                            <form @submit.prevent="submitDocument" class="space-y-4 mt-4">
                                <div>
                                    <Label for="documentFile" class="text-sm font-medium">Arquivo</Label>
                                    <div class="mt-1 flex items-center h-10 border border-input bg-background rounded-md ring-offset-background focus-within:ring-2 focus-within:ring-ring focus-within:ring-offset-2">
                                        <Input
                                            id="documentFile"
                                            type="file"
                                            ref="fileInputRef"
                                            @input="documentForm.file = ($event.target as HTMLInputElement)?.files?.[0] || null"
                                            class="block w-full text-sm text-gray-500 file:mr-4 file:py-2 file:px-4 file:border-0 file:text-sm file:font-semibold file:bg-indigo-50 file:text-indigo-700 hover:file:bg-indigo-100 dark:file:bg-indigo-900/50 dark:file:text-indigo-300 dark:hover:file:bg-indigo-800/50 h-full focus-visible:ring-0 focus-visible:ring-offset-0 border-0 shadow-none"
                                            required
                                        />
                                    </div>
                                    <div v-if="documentForm.progress" class="w-full bg-gray-200 rounded-full h-2.5 dark:bg-gray-700 mt-2">
                                        <div class="bg-indigo-600 h-2.5 rounded-full" :style="{ width: documentForm.progress.percentage + '%' }"></div>
                                    </div>
                                    <div v-if="documentForm.errors.file" class="text-sm text-red-600 dark:text-red-400 mt-1">
                                        {{ documentForm.errors.file }}
                                    </div>
                                </div>
                                <div>
                                    <Label for="documentDescription" class="text-sm font-medium">Descrição (Opcional)</Label>
                                    <Textarea
                                        id="documentDescription"
                                        v-model="documentForm.description"
                                        placeholder="Descrição breve do documento..."
                                        rows="3"
                                        class="mt-1 text-sm"
                                    />
                                    <div v-if="documentForm.errors.description" class="text-sm text-red-600 dark:text-red-400 mt-1">
                                        {{ documentForm.errors.description }}
                                    </div>
                                </div>
                                <DialogFooter class="mt-6 flex flex-col-reverse sm:flex-row sm:justify-end sm:space-x-2">
                                    <DialogClose as-child>
                                        <Button variant="outline" type="button" @click="showUploadDocumentDialog = false; documentForm.reset(); if(fileInputRef) fileInputRef.value = ''; documentForm.clearErrors();">Cancelar</Button>
                                    </DialogClose>
                                    <Button type="submit" :disabled="documentForm.processing">
                                        <UploadCloud class="mr-2 h-4 w-4" v-if="!documentForm.processing" />
                                        <svg v-else class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        {{ documentForm.processing ? 'Enviando...' : 'Enviar Documento' }}
                                    </Button>
                                </DialogFooter>
                            </form>
                        </DialogContent>
                    </Dialog>
                </div>

                <div v-if="contact.documents && contact.documents.length > 0" class="space-y-3">
                    <Card v-for="doc in contact.documents" :key="doc.id" class="hover:shadow-md transition-shadow">
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
                                <Button variant="ghost" size="icon" class="h-8 w-8" @click="openDeleteDocumentDialog(doc)" title="Excluir documento">
                                    <Trash2 class="h-4 w-4 text-gray-500 hover:text-red-600" />
                                </Button>
                            </div>
                        </CardContent>
                    </Card>
                </div>
                <p v-else class="text-sm text-gray-500 dark:text-gray-400 text-center py-8">Nenhum documento anexado a este contato.</p>
            </div>

            <div v-if="activeTab === 'cases'" class="space-y-4 py-4">
                <div class="flex justify-between items-center">
                    <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Casos Vinculados</h3>
                    <Link :href="route('processes.create', { contact_id: contact.id })">
                        <Button variant="outline" size="sm">
                            <PlusCircle class="h-4 w-4 mr-2" /> Novo Caso para este Contato
                        </Button>
                    </Link>
                </div>
                 <div v-if="contact.processes && contact.processes.length > 0" class="space-y-3">
                    <Card v-for="process_case in contact.processes" :key="process_case.id" class="hover:shadow-md transition-shadow">
                        <CardContent class="p-3">
                             <Link :href="route('processes.show', process_case.id)" class="block group">
                                <div class="flex justify-between items-start">
                                    <p class="font-semibold text-indigo-600 dark:text-indigo-400 group-hover:underline">{{ process_case.title }}</p>
                                    <Badge :variant="process_case.status === 'Concluído' ? 'default' : (process_case.status === 'Em Andamento' ? 'secondary' : 'outline')">{{ process_case.status || 'N/A' }}</Badge>
                                </div>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    Workflow: {{ process_case.workflow_label || 'N/A' }} | Última Atualização: {{ formatDate(process_case.updated_at, true) }}
                                </p>
                            </Link>
                        </CardContent>
                    </Card>
                </div>
                <p v-else class="text-sm text-gray-500 dark:text-gray-400 text-center py-8">Nenhum caso vinculado a este contato.</p>
            </div>
            
            <div v-if="activeTab === 'history'" class="space-y-4 py-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Histórico de Alterações</h3>
                <p class="text-sm text-gray-500 dark:text-gray-400 text-center py-8">
                    Funcionalidade de histórico ainda não implementada.
                </p>
                </div>
          </div>
        </div>
      </div>
    </div>

    <Dialog :open="showDeleteDocumentDialog" @update:open="showDeleteDocumentDialog = $event">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Confirmar Exclusão de Documento</DialogTitle>
                <DialogDescription v-if="documentToDelete">
                    Tem certeza de que deseja excluir o documento <strong class="font-medium">{{ documentToDelete.name }}</strong>? Esta ação não poderá ser desfeita.
                </DialogDescription>
                 <DialogDescription v-else>
                    Tem certeza de que deseja excluir este documento? Esta ação não poderá ser desfeita.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="mt-4 flex flex-col-reverse sm:flex-row sm:justify-end sm:space-x-2">
                <Button variant="outline" type="button" @click="showDeleteDocumentDialog = false; documentToDelete = null;">Cancelar</Button>
                <Button variant="destructive" :disabled="documentDeleteForm.processing" @click="submitDeleteDocument">
                     <svg v-if="documentDeleteForm.processing" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ documentDeleteForm.processing ? 'Excluindo...' : 'Confirmar Exclusão' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

    <Dialog :open="showDeleteAnnotationDialog" @update:open="showDeleteAnnotationDialog = $event">
        <DialogContent class="sm:max-w-md">
            <DialogHeader>
                <DialogTitle>Confirmar Exclusão de Anotação</DialogTitle>
                <DialogDescription v-if="annotationToDelete">
                    Tem certeza de que deseja excluir esta anotação?
                    <blockquote class="mt-2 p-2 border-l-4 border-gray-300 bg-gray-50 dark:bg-gray-700 dark:border-gray-600 text-xs text-gray-600 dark:text-gray-300">
                        {{ annotationToDelete.content.substring(0, 100) }}{{ annotationToDelete.content.length > 100 ? '...' : '' }}
                    </blockquote>
                    Esta ação não poderá ser desfeita.
                </DialogDescription>
                 <DialogDescription v-else>
                    Tem certeza de que deseja excluir esta anotação? Esta ação não poderá ser desfeita.
                </DialogDescription>
            </DialogHeader>
            <DialogFooter class="mt-4 flex flex-col-reverse sm:flex-row sm:justify-end sm:space-x-2">
                <Button variant="outline" type="button" @click="showDeleteAnnotationDialog = false; annotationToDelete = null;">Cancelar</Button>
                <Button variant="destructive" :disabled="annotationDeleteForm.processing" @click="submitDeleteAnnotation">
                     <svg v-if="annotationDeleteForm.processing" class="animate-spin -ml-1 mr-3 h-5 w-5 text-white" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                    {{ annotationDeleteForm.processing ? 'Excluindo...' : 'Confirmar Exclusão' }}
                </Button>
            </DialogFooter>
        </DialogContent>
    </Dialog>

  </AppLayout>
</template>

<style scoped>
ul li::marker { color: #6b7280; /* gray-500 */ }
.dark ul li::marker { color: #9ca3af; /* dark:gray-400 */ }

.max-h-96::-webkit-scrollbar { width: 6px; }
.max-h-96::-webkit-scrollbar-track { background: transparent; }
.max-h-96::-webkit-scrollbar-thumb { background-color: #cbd5e1; /* gray-300 */ border-radius: 3px; }
.dark .max-h-96::-webkit-scrollbar-thumb { background-color: #4b5563; /* dark:gray-600 */ }

.tab-content-container > div {
  min-height: 300px; 
}
</style>
