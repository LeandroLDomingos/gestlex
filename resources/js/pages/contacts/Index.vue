<script setup lang="ts">
import { ref, computed } from 'vue' // Adicionado computed
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table'
import type { BreadcrumbItem, Contact } from '@/types' // Supondo que suas tipagens estejam corretas

// Props: contatos paginados vindo do servidor
const props = defineProps<{
    contacts: {
        data: Contact[]
        links: { url: string | null; label: string; active: boolean }[]
        meta: {
            current_page: number
            last_page: number
            per_page: number
            total: number
            // O backend DEVE enviar os parâmetros de ordenação atuais se quiser
            // que o estado inicial seja refletido corretamente na UI.
            // Exemplo:
            // path: string;
            // query_params: Record<string, string>; // Para pegar sort_by e sort_direction
        }
    }
    // Opcional, mas recomendado: passar os filtros atuais e parâmetros de ordenação do backend
    // para inicializar o estado do frontend corretamente.
    filters?: Record<string, string> // Ex: { search: '...', status: 'active' }
    sortBy?: string
    sortDirection?: 'asc' | 'desc'
}>()

// Breadcrumbs estáticos
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Contatos', href: route('contacts.index') } // Usar route() para gerar URLs
]

// --- Lógica de Ordenação ---
const page = usePage()

// Estado da ordenação:
// Tenta inicializar com os valores passados como props (do backend) ou da URL.
// Se não vierem como props diretas, tentamos extrair da URL via page.props.ziggy.query
// @ts-ignore - page.props.ziggy pode não estar totalmente tipado ou disponível
const initialSortBy = props.sortBy || page.props.ziggy?.query?.sort_by || 'name';
// @ts-ignore
const initialSortDirection = props.sortDirection || page.props.ziggy?.query?.sort_direction || 'asc';

const sortColumn = ref<string>(initialSortBy)
const sortDirection = ref<'asc' | 'desc'>(initialSortDirection as 'asc' | 'desc')

// Mapeamento de colunas do frontend para o backend (nomes que o backend espera)
const sortableColumns = {
    name: 'name',
    phone: 'phone', // Ajuste se o backend espera um nome diferente, ex: 'main_phone' ou se precisa de lógica especial
    email: 'email', // Ajuste se o backend espera um nome diferente, ex: 'main_email'
    date_of_birth: 'date_of_birth',
} as const; // Use 'as const' para tipagem mais estrita das chaves

type SortableColumnKey = keyof typeof sortableColumns;

const handleSort = (columnKey: SortableColumnKey) => {
    const backendColumnName = sortableColumns[columnKey];

    if (sortColumn.value === backendColumnName) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
    } else {
        sortColumn.value = backendColumnName
        sortDirection.value = 'asc'
    }
    applySort()
}

const applySort = () => {
    // Pega os query params atuais da URL para preservar filtros existentes.
    // @ts-ignore
    const currentQueryParams = { ...(page.props.ziggy?.query || {}) };

    // Ao ordenar, geralmente voltamos para a primeira página.
    // Se você quiser manter a página atual, remova a linha abaixo.
    delete currentQueryParams.page;

    router.get(
        route('contacts.index'), // Certifique-se que 'contacts.index' é a rota correta
        {
            ...currentQueryParams, // Mantém outros filtros/queries existentes
            sort_by: sortColumn.value,
            sort_direction: sortDirection.value,
        },
        {
            preserveState: true, // Mantém o estado local do componente Vue (ex: campos de busca não relacionados à tabela)
            preserveScroll: true, // Mantém a posição do scroll
            replace: true,        // Evita adicionar múltiplas entradas no histórico do navegador para ordenação
            // Opcional: Para forçar o reload dos dados da prop 'contacts'
            // only: ['contacts'], // Descomente se `preserveState: true` não recarregar os dados como esperado
        }
    )
}

// Função helper para exibir ícones de ordenação
const getSortIcon = (columnKey: SortableColumnKey) => {
    const backendColumnName = sortableColumns[columnKey];
    if (sortColumn.value === backendColumnName) {
        return sortDirection.value === 'asc' ? '🔼' : '🔽'; // Simples setas de texto
        // Para ícones melhores, use SVGs ou uma biblioteca de ícones:
        // return sortDirection.value === 'asc' ? '<svg>...</svg>' : '<svg>...</svg>';
    }
    return '↕️'; // Ícone padrão para colunas ordenáveis, mas não ativas
}

// Colunas da tabela para o template
const tableHeaders: { key: SortableColumnKey; label: string }[] = [
    { key: 'name', label: 'Nome' },
    { key: 'phone', label: 'Telefone' },
    { key: 'email', label: 'Email' },
    { key: 'date_of_birth', label: 'Data de Nasc.' },
];

</script>

<template>

    <Head title="Contatos" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4 md:p-6 bg-white dark:bg-gray-800 rounded-lg shadow-md">
            <div class="flex justify-end mb-4">
                <Link :href="route('contacts.create')">
                <button
                    class="px-4 py-2 text-sm font-medium text-white bg-blue-600 rounded-md hover:bg-blue-700 focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition duration-150 ease-in-out">
                    Criar Contato
                </button>
                </Link>
            </div>

            <div class="rounded-md border dark:border-gray-700 overflow-x-auto">
                <Table class="min-w-full">
                    <TableHeader class="bg-gray-50 dark:bg-gray-700">
                        <TableRow>
                            <TableHead v-for="header in tableHeaders" :key="header.key" @click="handleSort(header.key)"
                                class="px-4 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider cursor-pointer select-none hover:bg-gray-100 dark:hover:bg-gray-600 transition-colors duration-150">
                                {{ header.label }}
                                <span class="ml-1">{{ getSortIcon(header.key) }}</span>
                            </TableHead>
                        </TableRow>
                    </TableHeader>
                    <TableBody class="bg-white dark:bg-gray-800 divide-y divide-gray-200 dark:divide-gray-700">
                        <template v-if="props.contacts && props.contacts.data.length">
                            <Link as="tr" v-for="contact in props.contacts.data" :key="contact.id"
                                :href="route('contacts.show', contact.id)"
                                class="hover:bg-gray-50 dark:hover:bg-gray-700/50 cursor-pointer transition-colors duration-150">
                            <TableCell class="px-4 py-3 whitespace-nowrap text-sm text-gray-700 dark:text-gray-200">{{
                                contact.name }}</TableCell>
                            <TableCell class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{
                                contact.phones?.[0]?.phone || '-' }}</TableCell>
                            <TableCell class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">{{
                                contact.emails?.[0]?.email || '-' }}</TableCell>
                            <TableCell class="px-4 py-3 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                {{ contact.date_of_birth
                                    ? new Date(contact.date_of_birth + 'T00:00:00').toLocaleDateString('pt-BR') // Adicionar
                                T00:00:00 para evitar problemas de fuso horário com toLocaleDateString
                                : '-' }}
                            </TableCell>
                            </Link>
                        </template>
                        <TableRow v-else>
                            <TableCell colspan="4"
                                class="px-4 py-12 text-center text-sm text-gray-500 dark:text-gray-400 h-24">
                                Nenhum registro encontrado.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <div v-if="props.contacts && props.contacts.links.length > 3"
                class="flex items-center justify-center mt-6 space-x-1">
                <template v-for="(link, index) in props.contacts.links" :key="`${link.label}-${index}`">
                    <Link v-if="link.url" :href="link.url" v-html="link.label" :class="[
                        'px-3 py-2 text-sm leading-4 border rounded-md transition-colors duration-150 ease-in-out',
                        link.active
                            ? 'bg-blue-600 text-white border-blue-600 hover:bg-blue-700'
                            : 'bg-white dark:bg-gray-700 text-gray-700 dark:text-gray-300 border-gray-300 dark:border-gray-600 hover:bg-gray-50 dark:hover:bg-gray-600',
                        { 'opacity-50 cursor-not-allowed': !link.url }
                    ]" preserve-scroll preserve-state="replace" />
                    <span v-else :key="`span-${link.label}-${index}`"
                        class="px-3 py-2 text-sm leading-4 border rounded-md bg-white dark:bg-gray-700 text-gray-400 dark:text-gray-500 border-gray-300 dark:border-gray-600"
                        v-html="link.label" />
                </template>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Estilos personalizados podem ser adicionados aqui, se necessário.
   As classes do Tailwind CSS já devem fornecer uma boa base. */
.select-none {
    user-select: none;
}
</style>
