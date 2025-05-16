<script setup lang="ts">
import { ref, computed, onMounted } from 'vue'
import { Head, Link, usePage, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Button from '@/components/ui/button/Button.vue'
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

// Defina um tipo para o contato, ajuste conforme a sua estrutura de dados
interface Address {
  street: string
  number?: string
  complement?: string
  neighborhood: string
  city: string
  state: string
  zip_code: string
}

interface Contact {
  id: number | string
  type: 'physical' | 'legal'
  name?: string // Para Pessoa Física
  trade_name?: string // Nome Fantasia para Pessoa Jurídica
  business_name?: string // Razão Social para Pessoa Jurídica
  cpf_cnpj: string
  rg?: string
  date_of_birth?: string
  gender?: string
  nationality?: string
  marital_status?: string
  profession?: string
  business_activity?: string
  tax_state?: string
  tax_city?: string
  administrator_id?: string | number | null;
  emails: { id?: number; email: string }[]
  phones: { id?: number; phone: string }[] // Espera-se que 'phone' seja uma string de dígitos
  address: Address | null;
  annotations?: { id: number; text: string; created_at: string }[]
  related_calculations?: { id: number; title: string; date: string; type: string }[]
}

interface BreadcrumbItem {
  title: string
  href: string
}

const props = defineProps<{
  contact: Contact
}>()

const activeTab = ref('details')
const deleteForm = useForm({});

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


const breadcrumbs = computed<BreadcrumbItem[]>(() => [
  { title: 'Contatos', href: route('contacts.index') },
  {
    title: getContactDisplayName(props.contact),
    href: route('contacts.show', props.contact.id),
  },
])

const contactDocuments = ref([
  { id: 1, name: 'Contrato Social.pdf', date: '10/05/2025', size: '2MB', url: '#' },
  { id: 2, name: 'Procuração.docx', date: '11/05/2025', size: '150KB', url: '#' },
])

const contactCases = ref([
    { id: 1, number: '0012345-67.2024.8.26.0001', description: 'Ação de Cobrança', status: 'Em andamento', url: '#'},
    { id: 2, number: '9876543-21.2023.8.13.0145', description: 'Revisão de Contrato', status: 'Concluído', url: '#'},
])

function editContact() {
  if (typeof route === 'function') {
    (usePage().props.inertia as any).visit(route('contacts.edit', props.contact.id));
  } else {
    console.warn("Função 'route' não está disponível. Verifique a configuração do Ziggy.");
    alert(`Editar contato: ${props.contact.id} (route() helper não encontrado)`);
  }
}

function confirmDeleteContact() {
  if (typeof route === 'function') {
    deleteForm.delete(route('contacts.destroy', props.contact.id), {
      preserveScroll: true,
      onSuccess: () => {
         // O feedback de sucesso será tratado pelo Alert.vue via flash message
      },
      onError: (errors) => {
        console.error('Erro ao excluir contato:', errors);
        // O feedback de erro também pode ser via flash message se o backend enviar
        (usePage().props.flash as any).error = 'Erro ao excluir o contato.'; // Exemplo de como definir manualmente se necessário
      },
    });
  } else {
     console.warn("Função 'route' não está disponível. Verifique a configuração do Ziggy.");
     alert(`Excluir contato: ${props.contact.id} (route() helper não encontrado)`);
  }
}

const newAnnotationText = ref('');

function addAnnotation() {
  const annotationText = prompt('Digite a sua anotação:')
  if (annotationText && annotationText.trim() !== '') {
    const newAnnotation = {
        id: Date.now(),
        text: annotationText,
        created_at: new Date().toISOString()
    };
    if (props.contact.annotations) {
        props.contact.annotations.push(newAnnotation);
    } else {
        props.contact.annotations = [newAnnotation];
    }
    // Idealmente, esta ação faria uma chamada API para salvar a anotação
    // e o backend retornaria 'success' para o toast.
    (usePage().props.flash as any).success = "Anotação adicionada (simulado)!";
  }
}

function getContactDisplayName(contact?: typeof props.contact): string {
    if (!contact) return 'N/A';
    if (contact.type === 'physical') {
        return contact.name || 'N/A';
    }
    return contact.trade_name || contact.business_name || contact.name || 'N/A';
}

function formatIdentifier(contact?: typeof props.contact): string {
    if (!contact || !contact.cpf_cnpj) return 'N/A';
    const cleanedCpfCnpj = String(contact.cpf_cnpj).replace(/\D/g, '');

    if (contact.type === 'physical' && cleanedCpfCnpj.length === 11) {
        return cleanedCpfCnpj.replace(/(\d{3})(\d{3})(\d{3})(\d{2})/, '$1.$2.$3-$4');
    } else if (contact.type === 'legal' && cleanedCpfCnpj.length === 14) {
        return cleanedCpfCnpj.replace(/(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})/, '$1.$2.$3/$4-$5');
    }
    return contact.cpf_cnpj;
}

function formatDate(dateString?: string | null): string {
    if (!dateString) return 'N/A';
    try {
        const date = new Date(dateString);
        if (isNaN(date.getTime())) {
            const parts = dateString.split(/[-/T]/);
            if (parts.length >= 3) {
                let year = parseInt(parts[0]);
                let month = parseInt(parts[1]) -1;
                let day = parseInt(parts[2]);

                if (year <= 31 && month <=11 && parseInt(parts[2]) >=1000) {
                    year = parseInt(parts[2]);
                    month = parseInt(parts[1]) -1;
                    day = parseInt(parts[0]);
                } else if (parseInt(parts[0]) > 31) {
                     year = parseInt(parts[0]);
                     month = parseInt(parts[1]) -1;
                     day = parseInt(parts[2]);
                }
                if (year < 1000 || year > 3000) return dateString;

                const adjustedDate = new Date(Date.UTC(year, month, day));
                if (isNaN(adjustedDate.getTime())) return dateString;
                return adjustedDate.toLocaleDateString('pt-BR', { timeZone: 'UTC' });
            }
            return dateString;
        }
        return new Date(date.getUTCFullYear(), date.getUTCMonth(), date.getUTCDate()).toLocaleDateString('pt-BR', { timeZone: 'UTC' });
    } catch (e) {
        console.error("Erro ao formatar data:", dateString, e);
        return dateString;
    }
}

// Função para formatar números de telefone para exibição
function formatPhoneNumber(phoneString?: string | null): string {
    if (!phoneString) return 'N/A';
    // Assume que o phoneString já vem do backend apenas com dígitos
    const cleaned = String(phoneString).replace(/\D/g, '');

    if (cleaned.length === 11) { // Celular com 9º dígito + DDD: (XX) XXXXX-XXXX
        return cleaned.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
    } else if (cleaned.length === 10) { // Fixo ou celular sem 9º dígito + DDD: (XX) XXXX-XXXX
        return cleaned.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
    } else if (cleaned.length === 9) { // Celular com 9º dígito sem DDD (ex: 9XXXX-XXXX)
        return cleaned.replace(/(\d{5})(\d{4})/, '$1-$2');
    } else if (cleaned.length === 8) { // Fixo sem DDD (ex: XXXX-XXXX)
        return cleaned.replace(/(\d{4})(\d{4})/, '$1-$2');
    }
    // Se não corresponder a nenhum formato esperado, retorna o número limpo ou original
    return phoneString; // Ou `cleaned` se preferir mostrar apenas dígitos em caso de formato desconhecido
}

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
          </p>
        </div>
        <div class="flex space-x-2">
          <Button @click="editContact" variant="default" size="sm">
            <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none"
              stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
              class="mr-2 h-4 w-4">
              <path d="M17 3a2.85 2.83 0 1 1 4 4L7.5 20.5 2 22l1.5-5.5Z" />
              <path d="m15 5 4 4" />
            </svg>
            Editar
          </Button>
          <Dialog>
            <DialogTrigger as-child>
              <Button variant="destructive" size="sm" :disabled="deleteForm.processing">
                <svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none"
                  stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"
                  class="mr-2 h-4 w-4">
                  <path d="M3 6h18" />
                  <path d="M19 6v14c0 1-1 2-2 2H7c-1 0-2-1-2-2V6" />
                  <path d="M8 6V4c0-1 1-2 2-2h4c1 0 2 1 2 2v2" />
                  <line x1="10" x2="10" y1="11" y2="17" />
                  <line x1="14" x2="14" y1="11" y2="17" />
                </svg>
                Deletar
              </Button>
            </DialogTrigger>
            <DialogContent class="sm:max-w-md">
              <DialogHeader>
                <DialogTitle>Confirmar Exclusão</DialogTitle>
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
        <div class="w-full lg:w-2/3 bg-white dark:bg-gray-800 shadow-xl rounded-lg overflow-hidden">
          <div class="border-b border-gray-200 dark:border-gray-700">
            <nav class="-mb-px flex space-x-px" aria-label="Tabs">
              <button @click="activeTab = 'details'" :class="[
                  'flex-1 py-4 px-1 text-center border-b-2 font-medium text-sm whitespace-nowrap',
                  activeTab === 'details'
                    ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:border-gray-600',
                ]">
                Detalhes
              </button>
              <button @click="activeTab = 'calculations'" :class="[
                  'flex-1 py-4 px-1 text-center border-b-2 font-medium text-sm whitespace-nowrap',
                  activeTab === 'calculations'
                    ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:border-gray-600',
                ]">
                Cálculos ({{ contact.related_calculations?.length || 0 }})
              </button>
              <button @click="activeTab = 'documents'" :class="[
                  'flex-1 py-4 px-1 text-center border-b-2 font-medium text-sm whitespace-nowrap',
                  activeTab === 'documents'
                    ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:border-gray-600',
                ]">
                Documentos ({{ contactDocuments.length }})
              </button>
               <button @click="activeTab = 'cases'" :class="[
                  'flex-1 py-4 px-1 text-center border-b-2 font-medium text-sm whitespace-nowrap',
                  activeTab === 'cases'
                    ? 'border-indigo-500 text-indigo-600 dark:text-indigo-400'
                    : 'border-transparent text-gray-500 hover:text-gray-700 hover:border-gray-300 dark:text-gray-400 dark:hover:text-gray-200 dark:hover:border-gray-600',
                ]">
                Casos ({{ contactCases.length }})
              </button>
            </nav>
          </div>

          <div class="p-6">
            <div v-if="activeTab === 'details'" class="space-y-6">
              <div v-if="contact.type === 'physical'">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Dados Pessoais</h3>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-4">
                  <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Nome Completo</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">{{ contact.name || 'N/A' }}</dd>
                  </div>
                  <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">CPF</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">{{ formatIdentifier(contact) }}</dd>
                  </div>
                  <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">RG</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">{{ contact.rg || 'N/A' }}</dd>
                  </div>
                  <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Data de Nascimento</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">{{ formatDate(contact.date_of_birth) }}
                    </dd>
                  </div>
                  <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Gênero</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">{{ contact.gender || 'N/A' }}</dd>
                  </div>
                  <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Nacionalidade</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">{{ contact.nationality || 'N/A' }}</dd>
                  </div>
                  <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Estado Civil</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">{{ contact.marital_status || 'N/A' }}</dd>
                  </div>
                  <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Profissão</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">{{ contact.profession || 'N/A' }}</dd>
                  </div>
                </dl>
              </div>

              <div v-if="contact.type === 'legal'">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Dados da Empresa</h3>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-4">
                  <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Nome Fantasia</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">{{ contact.trade_name || contact.name || 'N/A' }}</dd>
                  </div>
                  <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Razão Social</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">{{ contact.business_name || 'N/A' }}</dd>
                  </div>
                  <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">CNPJ</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">{{ formatIdentifier(contact) }}</dd>
                  </div>
                  <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Atividade Principal</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">{{ contact.business_activity || 'N/A' }}
                    </dd>
                  </div>
                  <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Estado de Tributação</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">{{ contact.tax_state || 'N/A' }}</dd>
                  </div>
                  <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Município de Tributação</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">{{ contact.tax_city || 'N/A' }}</dd>
                  </div>
                  <div v-if="contact.administrator_id" class="sm:col-span-2">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Administrador Responsável ID</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">{{ contact.administrator_id }}</dd>
                  </div>
                </dl>
              </div>

              <div class="pt-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Endereço</h3>
                <dl class="grid grid-cols-1 sm:grid-cols-2 gap-x-4 gap-y-4" v-if="contact.address">
                  <div class="sm:col-span-2">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Logradouro</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">
                      {{ contact.address.street || 'N/A' }}{{ contact.address.number ? ', ' + contact.address.number : '' }}
                    </dd>
                  </div>
                  <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Complemento</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">{{ contact.address.complement || 'N/A' }}
                    </dd>
                  </div>
                  <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Bairro</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">{{ contact.address.neighborhood || 'N/A' }}</dd>
                  </div>
                  <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Cidade</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">{{ contact.address.city || 'N/A' }}</dd>
                  </div>
                  <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">Estado</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">{{ contact.address.state || 'N/A' }}</dd>
                  </div>
                  <div class="sm:col-span-1">
                    <dt class="text-sm font-medium text-gray-500 dark:text-gray-400">CEP</dt>
                    <dd class="mt-1 text-sm text-gray-900 dark:text-gray-200">{{ contact.address.zip_code || 'N/A' }}
                    </dd>
                  </div>
                </dl>
                <p v-else class="text-sm text-gray-500 dark:text-gray-400">Endereço não cadastrado.</p>
              </div>

              <div class="pt-4">
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 mb-2">Informações de Contato</h3>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-6">
                  <div>
                    <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">E-mails</h4>
                    <ul v-if="contact.emails && contact.emails.length > 0" class="list-disc list-inside space-y-1">
                      <li v-for="(emailObj, index) in contact.emails" :key="`email-${index}`"
                        class="text-sm text-gray-700 dark:text-gray-300 break-all">
                        {{ emailObj.email }}
                      </li>
                    </ul>
                    <p v-else class="text-sm text-gray-500 dark:text-gray-400">Nenhum e-mail cadastrado.</p>
                  </div>
                  <div>
                    <h4 class="text-sm font-medium text-gray-500 dark:text-gray-400 mb-1">Telefones</h4>
                    <ul v-if="contact.phones && contact.phones.length > 0" class="list-disc list-inside space-y-1">
                      <li v-for="(phoneObj, index) in contact.phones" :key="`phone-${index}`"
                        class="text-sm text-gray-700 dark:text-gray-300">
                        {{ formatPhoneNumber(phoneObj.phone) }} </li>
                    </ul>
                    <p v-else class="text-sm text-gray-500 dark:text-gray-400">Nenhum telefone cadastrado.</p>
                  </div>
                </div>
              </div>
            </div>

            <div v-if="activeTab === 'calculations'" class="space-y-4">
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Cálculos Relacionados</h3>
              <ul v-if="contact.related_calculations && contact.related_calculations.length > 0" class="space-y-3">
                <li v-for="calc in contact.related_calculations" :key="calc.id"
                  class="p-4 bg-gray-50 dark:bg-gray-700 rounded-md shadow-sm hover:shadow-md transition-shadow">
                  <div class="flex justify-between items-center">
                    <div>
                      <p class="font-semibold text-gray-800 dark:text-gray-200">{{ calc.title }}</p>
                      <p class="text-sm text-gray-600 dark:text-gray-400">{{ calc.type }} - {{ formatDate(calc.date) }}
                      </p>
                    </div>
                    <Link :href="route('calculations.show', calc.id)"
                      class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 text-sm font-medium">
                    Ver Detalhes &rarr;
                    </Link>
                  </div>
                </li>
              </ul>
              <p v-else class="text-sm text-gray-500 dark:text-gray-400">Nenhum cálculo relacionado encontrado.</p>
            </div>

            <div v-if="activeTab === 'documents'" class="space-y-4">
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Documentos Anexados</h3>
              <ul v-if="contactDocuments.length > 0" class="space-y-3">
                <li v-for="doc in contactDocuments" :key="doc.id"
                  class="p-4 bg-gray-50 dark:bg-gray-700 rounded-md shadow-sm hover:shadow-md transition-shadow">
                  <div class="flex justify-between items-center">
                    <div>
                      <p class="font-semibold text-gray-800 dark:text-gray-200">{{ doc.name }}</p>
                      <p class="text-sm text-gray-600 dark:text-gray-400">Adicionado em: {{ formatDate(doc.date) }} -
                        Tamanho: {{ doc.size }}</p>
                    </div>
                     <Link :href="doc.url || '#'"
                      class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 text-sm font-medium">
                    Baixar &rarr;
                    </Link>
                  </div>
                </li>
              </ul>
              <p v-else class="text-sm text-gray-500 dark:text-gray-400">Nenhum documento anexado.</p>
            </div>

            <div v-if="activeTab === 'cases'" class="space-y-4">
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Casos Vinculados</h3>
              <ul v-if="contactCases.length > 0" class="space-y-3">
                <li v-for="caso in contactCases" :key="caso.id"
                  class="p-4 bg-gray-50 dark:bg-gray-700 rounded-md shadow-sm hover:shadow-md transition-shadow">
                  <div class="flex justify-between items-center">
                    <div>
                      <p class="font-semibold text-gray-800 dark:text-gray-200">Processo: {{ caso.number }}</p>
                      <p class="text-sm text-gray-600 dark:text-gray-400">{{ caso.description }} - Status: <span
                          :class="{ 'text-green-600 dark:text-green-400': caso.status === 'Concluído', 'text-yellow-600 dark:text-yellow-400': caso.status === 'Em andamento', 'text-red-600 dark:text-red-400': caso.status !== 'Concluído' && caso.status !== 'Em andamento' }">{{
                            caso.status }}</span></p>
                    </div>
                    <Link :href="caso.url || '#'"
                      class="text-indigo-600 hover:text-indigo-800 dark:text-indigo-400 dark:hover:text-indigo-300 text-sm font-medium">
                    Ver Detalhes &rarr;
                    </Link>
                  </div>
                </li>
              </ul>
              <p v-else class="text-sm text-gray-500 dark:text-gray-400">Nenhum caso vinculado encontrado.</p>
            </div>

          </div>
        </div>

        <div class="w-full lg:w-1/3 space-y-6">
          <div class="bg-white dark:bg-gray-800 shadow-xl rounded-lg p-6">
            <div class="flex justify-between items-center mb-4">
              <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100">Anotações</h3>
              <button @click="addAnnotation"
                class="px-3 py-1.5 text-xs font-medium text-white bg-green-600 rounded-md hover:bg-green-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-green-500 dark:bg-green-500 dark:hover:bg-green-600">
                + Nova
              </button>
            </div>
            <div class="space-y-4 max-h-96 overflow-y-auto pr-2">
              <div v-if="contact.annotations && contact.annotations.length > 0">
                <div v-for="note in contact.annotations.slice().reverse()" :key="note.id"
                  class="p-3 bg-gray-50 dark:bg-gray-700 rounded-md shadow-sm">
                  <p class="text-sm text-gray-700 dark:text-gray-300 whitespace-pre-wrap break-words">{{ note.text }}
                  </p>
                  <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">Em: {{ formatDate(note.created_at) }}</p>
                </div>
              </div>
              <p v-else class="text-sm text-gray-500 dark:text-gray-400">Nenhuma anotação encontrada.</p>
            </div>
          </div>
        </div>
      </div>
    </div>
  </AppLayout>
</template>

<style scoped>
ul li::marker {
  color: #6b7280;
}

.dark ul li::marker {
  color: #9ca3af;
}

.max-h-96::-webkit-scrollbar {
  width: 6px;
}

.max-h-96::-webkit-scrollbar-track {
  background: transparent;
}

.max-h-96::-webkit-scrollbar-thumb {
  background-color: #cbd5e1;
  border-radius: 3px;
}

.dark .max-h-96::-webkit-scrollbar-thumb {
  background-color: #4b5563;
}
</style>
