<script setup lang="ts">
import { defineProps, ref, h } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import AlertProvider from '@/components/AlertProvider.vue'
// Componentes de tabela shadcn‑vue
import {
    Table,
    TableBody,
    TableCell,
    TableHead,
    TableHeader,
    TableRow,
} from '@/components/ui/table'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Pencil, ChevronsUpDown } from 'lucide-vue-next'

// Componentes e utilitários do TanStack Vue Table
import {
    createColumnHelper,
    FlexRender,
    useVueTable,
    getCoreRowModel,
    getSortedRowModel,
    getPaginationRowModel,
    getFilteredRowModel,
} from '@tanstack/vue-table'

// Tipos de usuário e breadcrumb (de acordo com sua estrutura)
import type { BreadcrumbItem, Contact } from '@/types'

// Recebendo os dados vindos da controller via props
const props = defineProps<{
    contacts: Contact[]
}>()

// Definição dos breadcrumbs para o layout
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Contatos', href: '/contacts' },
]

// Define um dicionário para traduzir os nomes dos papéis e dos status
const translations: Record<string, string> = {
    male: 'Homem',
    female: 'Mulher',
    physical: 'Física',
    legal: 'Júridica',
}



// Define uma mascara mara CPF/CNPJ
function maskCpfCnpj(raw: string): string {
    const digits = raw.replace(/\D/g, '');
    if (digits.length <= 11) {
        // CPF: 000.000.000-00
        return digits
            .padStart(11, '0')
            .replace(/^(\d{3})(\d{3})(\d{3})(\d{2})$/, '$1.$2.$3-$4');
    } else {
        // CNPJ: 00.000.000/0000-00
        return digits
            .padStart(14, '0')
            .replace(
                /^(\d{2})(\d{3})(\d{3})(\d{4})(\d{2})$/,
                '$1.$2.$3/$4-$5'
            );
    }
}


// Cria o helper tipado para definir as colunas (ajuda a manter o código mais seguro em relação aos tipos)
const columnHelper = createColumnHelper<Contact>()

// Definição das colunas da tabela
// Ajuste os campos conforme sua estrutura de dados.
const columns = [
    {
        id: 'global', // coluna virtual invisível
        accessorFn: row => {
            const typeTranslated = translations[row.type] || row.gender;
            return [
                row.name,
                row.cpf_cnpj,
                row.rg,
                row.nationality,
                row.trade_name,
                row.trade_name,
                typeTranslated,
            ].join(' ').toLowerCase();
        },
        filterFn: (row, columnId, filterValue) => {
            const value = row.getValue(columnId);
            return value.includes(filterValue.toLowerCase());
        },
        header: () => null, // oculta o cabeçalho
        cell: () => null,   // oculta a célula
        enableSorting: false, // evita tentar ordenar essa coluna
    },
    // Coluna 'name' com botão no header para ordenar
    columnHelper.accessor('name', {
        header: ({ column }) =>
            h(
                Button,
                {
                    variant: 'ghost',
                    onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
                },
                () => [
                    'Nome',
                    h(ChevronsUpDown, { class: 'ml-2 h-4 w-4' })
                ]
            ),
        cell: ({ row }) =>
            h('div', { class: 'font-medium' }, row.getValue('name')),
    }),
    // Coluna 'name' com botão no header para ordenar
    columnHelper.accessor('cpf_cnpj', {
        header: ({ column }) =>
            h(
                Button,
                {
                    variant: 'ghost',
                    onClick: () =>
                        column.toggleSorting(column.getIsSorted() === 'asc'),
                },
                () => [
                    'CPF ou CNPJ',
                    h(ChevronsUpDown, { class: 'ml-2 h-4 w-4' }),
                ]
            ),
        cell: ({ row }) => {
            const raw = row.getValue('cpf_cnpj') as string;
            const formatted = maskCpfCnpj(raw);
            return h('div', { class: 'font-mono' }, formatted);
        },
    }),

    columnHelper.accessor('type', {
        header: ({ column }) =>
            h(
                Button,
                {
                    variant: 'ghost',
                    onClick: () => column.toggleSorting(column.getIsSorted() === 'asc'),
                },
                () => [
                    'Tipo',
                    h(ChevronsUpDown, { class: 'ml-2 h-4 w-4' })
                ]
            ),
        cell: ({ row }) => {
            const type = row.getValue('type')
            const typeNames = translations[type?.toLowerCase()] || '-'
            return h('div', { class: 'capitalize' }, typeNames)
        },
    }),

]

// Estado reativo para ordenação
const sorting = ref([])
// Estado reativo para a paginação, armazenando o índice e o tamanho da página
const pagination = ref({
    pageIndex: 0,
    pageSize: 10,  // valor inicial: 10 usuários por página
})
// Estado reativo para filtragem
const columnFilters = ref([])

// Instancia o objeto da tabela, passando os dados vindos da controller e as configurações necessárias
const table = useVueTable({
    data: props.contacts,
    columns,
    getCoreRowModel: getCoreRowModel(),
    getSortedRowModel: getSortedRowModel(), // habilita ordenação
    getPaginationRowModel: getPaginationRowModel(), // habilita paginação
    getFilteredRowModel: getFilteredRowModel(), // habilita filtragem
    state: {
        get sorting() {
            return sorting.value
        },
        get pagination() {
            return pagination.value
        },
        get columnFilters() {
            return columnFilters.value
        }
    },
    onSortingChange: updaterOrValue => {
        sorting.value = typeof updaterOrValue === 'function'
            ? updaterOrValue(sorting.value)
            : updaterOrValue
    },
    onPaginationChange: updaterOrValue => {
        pagination.value = typeof updaterOrValue === 'function'
            ? updaterOrValue(pagination.value)
            : updaterOrValue
    },
    onColumnFiltersChange: updaterOrValue => {
        columnFilters.value =
            typeof updaterOrValue === 'function'
                ? updaterOrValue(columnFilters.value)
                : updaterOrValue
    },
})
</script>

<template>

    <Head title="Contatos" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6 rounded-lg shadow-md">
            <div class="flex justify-end mb-4">
                <Link :href="route('contacts.create')">
                <Button>Criar Contato</Button>
                </Link>
            </div>
            <div class="mb-4">
                <Input placeholder="Filtrar..." :model-value="table.getColumn('global')?.getFilterValue() || ''"
                    @update:model-value="value => {
                        // Atualiza o filtro da coluna 'name'
                        table.getColumn('global')?.setFilterValue(value)
                    }" />
            </div>

            <!-- Container da Tabela -->
            <div class="rounded-md border overflow-x-auto">
                <Table>
                    <!-- Cabeçalho da Tabela -->
                    <TableHeader>
                        <TableRow v-for="headerGroup in table.getHeaderGroups()" :key="headerGroup.id">
                            <TableHead v-for="header in headerGroup.headers" :key="header.id" :class="[
                                // Oculta colunas em telas pequenas
                                header.id === 'email' && 'hidden sm:table-cell',
                                header.id === 'roles' && 'hidden sm:table-cell',
                            ]">
                                <!-- Renderiza o conteúdo do header com FlexRender -->
                                <FlexRender :render="header.column.columnDef.header" :props="header.getContext()" />
                            </TableHead>
                        </TableRow>
                    </TableHeader>

                    <!-- Corpo da Tabela -->
                    <TableBody>
                        <!-- Verifica se há linhas para exibir -->
                        <template v-if="table.getRowModel().rows?.length">
                            <TableRow v-for="row in table.getRowModel().rows" :key="row.id">
                                <TableCell v-for="cell in row.getVisibleCells()" :key="cell.id" :class="[
                                    // Oculta células em telas pequenas
                                    cell.column.id === 'email' && 'hidden sm:table-cell',
                                    cell.column.id === 'roles' && 'hidden sm:table-cell',
                                ]">
                                    <!-- Renderiza o conteúdo das células -->
                                    <FlexRender :render="cell.column.columnDef.cell" :props="cell.getContext()" />
                                </TableCell>
                            </TableRow>
                        </template>

                        <!-- Exibe uma mensagem caso não haja registros -->
                        <TableRow v-else>
                            <TableCell :colspan="columns.length" class="text-center h-24">
                                Nenhum registro encontrado.
                            </TableCell>
                        </TableRow>
                    </TableBody>
                </Table>
            </div>

            <!-- Controles de Paginação -->
            <div class="flex flex-col md:flex-row items-center justify-between mt-4">
                <!-- Seletor de Quantos usuários por página -->
                <div class="flex items-center space-x-2">
                    <span class="text-sm">Exibir:</span>
                    <select class="border rounded p-1 text-sm bg-white text-black dark:bg-gray-950 dark:text-white"
                        :value="table.getState().pagination.pageSize"
                        @change="event => table.setPageSize(Number(event.target.value))">
                        <option value="10">10</option>
                        <option value="25">25</option>
                        <option value="50">50</option>
                        <option value="100">100</option>
                    </select>
                    <span class="text-sm">usuários por página</span>
                </div>

                <!-- Botões de navegação entre as páginas -->
                <div class="flex items-center space-x-2 mt-2 md:mt-0">
                    <Button variant="outline" size="sm" :disabled="!table.getCanPreviousPage()"
                        @click="table.previousPage()">
                        Previous
                    </Button>
                    <span class="text-sm">
                        Página {{ table.getState().pagination.pageIndex + 1 }} de {{ table.getPageCount() }}
                    </span>
                    <Button variant="outline" size="sm" :disabled="!table.getCanNextPage()" @click="table.nextPage()">
                        Next
                    </Button>
                </div>
            </div>
        </div>
    </AppLayout>
</template>

<style scoped>
/* Estilos adicionais, se necessário */
</style>
