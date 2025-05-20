<script setup lang="ts">
import { ref, watch } from 'vue'
import { Head, Link, router, usePage } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue' // Ajuste o caminho se o seu layout estiver em outro lugar
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table' // Ajuste o caminho para seus componentes de UI da tabela
import type { BreadcrumbItem, Contact, ContactPhone } from '@/types' // Adicionado ContactPhone
import Button from '@/components/ui/button/Button.vue' // Ajuste o caminho para seu componente Button
import Input from '@/components/ui/input/Input.vue'; // Supondo que você tenha um componente Input, ajuste o caminho

// Props
const props = defineProps<{
    contacts: {
        data: (Contact & { phones?: ContactPhone[] })[] // Adicionado phones à tipagem de Contact em data
        links: { url: string | null; label: string; active: boolean }[]
        meta: {
            current_page: number
            last_page: number
            per_page: number
            total: number
        }
    }
    filters?: {
        search?: string;
    }
    sortBy?: string
    sortDirection?: 'asc' | 'desc'
}>()

// Breadcrumbs
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Contatos', href: route('contacts.index') }
]

// Lógica de Busca
const searchTerm = ref(props.filters?.search || '');

// Lógica de Ordenação
const page = usePage()
// @ts-ignore
const sortColumn = ref<string>(props.sortBy || page.props.ziggy?.query?.sort_by || 'name');
// @ts-ignore
const sortDirection = ref<'asc' | 'desc'>((props.sortDirection || page.props.ziggy?.query?.sort_direction || 'asc') as 'asc' | 'desc');

const sortableColumns = {
    name: 'name',
    date_of_birth: 'date_of_birth',
} as const;

type SortableColumnKey = keyof typeof sortableColumns;

// --- FUNÇÕES AUXILIARES ---
const displayValue = (value: any, fallback: string = 'Não informado') => {
    if (value === null || value === undefined || value === '' || (Array.isArray(value) && value.length === 0)) {
        return fallback;
    }
    return value;
};

const formatPhone = (value: string | null | undefined): string => {
    if (!value) return 'Não informado'; // Se for exibir "Não informado" diretamente na tabela
    const cleaned = String(value).replace(/\D/g, '');

    if (cleaned.length === 10) {
        return cleaned.replace(/(\d{2})(\d{4})(\d{4})/, '($1) $2-$3');
    }
    if (cleaned.length === 11) {
        return cleaned.replace(/(\d{2})(\d{5})(\d{4})/, '($1) $2-$3');
    }
    // Se não for um formato esperado, retorna o valor limpo ou original,
    // ou um fallback se preferir tratar números "inválidos" de forma diferente.
    return displayValue(value); // Ou apenas value, se não quiser o fallback "Não informado" aqui
};
// --- FIM DAS FUNÇÕES AUXILIARES ---

const handleSort = (columnKey: SortableColumnKey) => {
    const backendColumnName = sortableColumns[columnKey];
    if (sortColumn.value === backendColumnName) {
        sortDirection.value = sortDirection.value === 'asc' ? 'desc' : 'asc'
    } else {
        sortColumn.value = backendColumnName
        sortDirection.value = 'asc'
    }
    applySortAndFilters()
}

const applySortAndFilters = () => {
    // @ts-ignore
    const currentQueryParams = { ...(page.props.ziggy?.query || {}) };
    delete currentQueryParams.page;

    const queryParams: Record<string, string | undefined> = {
        ...currentQueryParams,
        sort_by: sortColumn.value,
        sort_direction: sortDirection.value,
        search: searchTerm.value || undefined,
    };

    if (!queryParams.search) {
        delete queryParams.search;
    }

    router.get(
        route('contacts.index'),
        queryParams as any,
        {
            preserveState: true,
            preserveScroll: true,
            replace: true,
        }
    )
}

let searchTimeout: number | undefined;
watch(searchTerm, () => { // Removido newValue, não é usado diretamente
    if (searchTimeout) clearTimeout(searchTimeout);
    searchTimeout = setTimeout(() => {
        applySortAndFilters();
    }, 300);
});

const getSortIcon = (columnKey: SortableColumnKey) => {
    const backendColumnName = sortableColumns[columnKey];
    if (sortColumn.value === backendColumnName) {
        return sortDirection.value === 'asc'
            ? '<svg viewBox="0 0 20 20" fill="currentColor" width="1em" height="1em" class="inline-block ml-1"><path fill-rule="evenodd" d="M14.707 12.707a1 1 0 01-1.414 0L10 9.414l-3.293 3.293a1 1 0 01-1.414-1.414l4-4a1 1 0 011.414 0l4 4a1 1 0 010 1.414z" clip-rule="evenodd"></path></svg>'
            : '<svg viewBox="0 0 20 20" fill="currentColor" width="1em" height="1em" class="inline-block ml-1"><path fill-rule="evenodd" d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z" clip-rule="evenodd"></path></svg>';
    }
    return '<svg xmlns="http://www.w3.org/2000/svg" width="1em" height="1em" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" class="inline-block ml-1 opacity-50"><path d="M7 15l5 5 5-5M7 9l5-5 5 5"/></svg>';
}

const tableHeaders: { key: SortableColumnKey | string; label: string; sortable: boolean }[] = [
    { key: 'name', label: 'Nome', sortable: true },
    { key: 'phone', label: 'Telefone', sortable: false },
    { key: 'email', label: 'Email', sortable: false },
    { key: 'date_of_birth', label: 'Data de Nasc.', sortable: true },
];

</script>

<template>

    <Head title="Contatos" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4 md:p-6 lg:p-8 space-y-6">
            <div class="flex flex-col sm:flex-row justify-between items-center gap-4">
                <h1 class="text-2xl font-semibold text-gray-800 dark:text-gray-100">
                    Lista de Contatos
                </h1>
                <div class="flex items-center gap-2 w-full sm:w-auto">
                    <div class="relative flex-grow sm:flex-grow-0">
                        <Search
                            class="absolute left-3 top-1/2 -translate-y-1/2 h-4 w-4 text-gray-400 dark:text-gray-500" />
                        <Input type="text" v-model="searchTerm" placeholder="Buscar contatos..."
                            class="block w-full sm:w-64 pl-10 pr-3 py-2 h-10" />
                    </div>
                    <Link :href="route('contacts.create')">
                    <Button variant="default" size="default">
                        <svg xmlns="http://www.w3.org/2000/svg" width="1.2em" height="1.2em" viewBox="0 0 24 24"
                            fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round"
                            stroke-linejoin="round" class="mr-2 h-4 w-4">
                            <path d="M5 12h14" />
                            <path d="M12 5v14" />
                        </svg>
                        Criar Contato
                    </Button>
                    </Link>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 shadow-md rounded-lg overflow-hidden">
                <div class="overflow-x-auto">
                    <Table class="min-w-full">
                        <TableHeader class="bg-gray-50 dark:bg-gray-700/50">
                            <TableRow>
                                <TableHead v-for="header in tableHeaders" :key="header.key"
                                    @click="header.sortable ? handleSort(header.key as SortableColumnKey) : null"
                                    :class="[
                                        'px-6 py-3 text-left text-xs font-medium text-gray-500 dark:text-gray-300 uppercase tracking-wider',
                                        header.sortable ? 'cursor-pointer select-none hover:bg-gray-100 dark:hover:bg-gray-600/50 transition-colors duration-150' : ''
                                    ]">
                                    {{ header.label }}
                                    <span v-if="header.sortable"
                                        v-html="getSortIcon(header.key as SortableColumnKey)"></span>
                                </TableHead>
                            </TableRow>
                        </TableHeader>
                        <TableBody class="divide-y divide-gray-200 dark:divide-gray-700">
                            <template v-if="props.contacts && props.contacts.data.length">
                                <Link as="tr" v-for="contact in props.contacts.data" :key="contact.id"
                                    :href="route('contacts.show', contact.id)"
                                    class="hover:bg-gray-50 dark:hover:bg-gray-700/30 cursor-pointer transition-colors duration-150">
                                <TableCell
                                    class="px-6 py-4 whitespace-nowrap text-sm font-medium text-gray-900 dark:text-gray-100">
                                    {{ contact.name }}
                                </TableCell>
                                <TableCell class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ formatPhone(contact.phones?.[0]?.phone) || '-' }}
                                </TableCell>
                                <TableCell class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ contact.emails?.[0]?.email || '-' }}
                                </TableCell>
                                <TableCell class="px-6 py-4 whitespace-nowrap text-sm text-gray-500 dark:text-gray-400">
                                    {{ contact.date_of_birth
                                        ? new Date(contact.date_of_birth + 'T00:00:00').toLocaleDateString('pt-BR', {
                                            day:
                                                '2-digit', month: '2-digit', year: 'numeric'
                                        })
                                        : '-' }}
                                </TableCell>
                                </Link>
                            </template>
                            <TableRow v-else>
                                <TableCell :colspan="tableHeaders.length" class="px-6 py-12 text-center">
                                    <div
                                        class="flex flex-col items-center justify-center text-gray-500 dark:text-gray-400">
                                        <svg xmlns="http://www.w3.org/2000/svg" width="3em" height="3em"
                                            viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5"
                                            stroke-linecap="round" stroke-linejoin="round" class="mb-2 opacity-50">
                                            <circle cx="12" cy="12" r="10" />
                                            <line x1="8" y1="15" x2="8" y2="15" />
                                            <line x1="16" y1="15" x2="16" y2="15" />
                                            <path d="M9 9a3 3 0 0 1 6 0" />
                                        </svg>
                                        <p class="text-lg font-medium">Nenhum contato encontrado.</p>
                                        <p class="text-sm">
                                            {{ searchTerm ? 'Tente refinar sua busca ou ' : 'Você pode ' }}
                                            <Link v-if="searchTerm" :href="route('contacts.index')"
                                                @click="searchTerm = ''"
                                                class="text-blue-600 dark:text-blue-400 hover:underline">limpar a busca
                                            </Link>
                                            {{ searchTerm ? ' para ver todos os contatos, ou ' : '' }}
                                            <Link :href="route('contacts.create')"
                                                class="text-indigo-600 dark:text-indigo-400 hover:underline">
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

            <div v-if="props.contacts && props.contacts.links.length > 3"
                class="flex items-center justify-center mt-6 pt-4 border-t dark:border-gray-700">
                <nav class="relative z-0 inline-flex rounded-md shadow-sm -space-x-px" aria-label="Paginação">
                    <template v-for="(link, index) in props.contacts.links" :key="`${link.label}-${index}`">
                        <Link v-if="link.url" :href="link.url" v-html="link.label" :class="[
                            'relative inline-flex items-center px-4 py-2 border text-sm font-medium transition-colors duration-150 ease-in-out',
                            link.active
                                ? 'z-10 bg-blue-600 border-blue-600 text-white hover:bg-blue-700'
                                : 'bg-white dark:bg-gray-800 border-gray-300 dark:border-gray-600 text-gray-700 dark:text-gray-300 hover:bg-gray-50 dark:hover:bg-gray-700',
                            { 'opacity-75 cursor-not-allowed': !link.url },
                            index === 0 ? 'rounded-l-md' : '',
                            index === props.contacts.links.length - 1 ? 'rounded-r-md' : ''
                        ]" preserve-scroll preserve-state="replace" />
                        <span v-else :key="`span-${link.label}-${index}`" v-html="link.label" :class="[
                            'relative inline-flex items-center px-4 py-2 border border-gray-300 dark:border-gray-600 bg-white dark:bg-gray-800 text-sm font-medium text-gray-400 dark:text-gray-500',
                            index === 0 ? 'rounded-l-md' : '',
                            index === props.contacts.links.length - 1 ? 'rounded-r-md' : ''
                        ]" />
                    </template>
                </nav>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
.select-none {
    user-select: none;
}
</style>
