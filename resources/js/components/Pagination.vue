<script setup lang="ts">
import { Link } from '@inertiajs/vue3';
import { computed, PropType } from 'vue';
import { ChevronLeft, ChevronRight } from 'lucide-vue-next'; // Ícones para Próximo/Anterior

// Define a estrutura esperada para cada link da paginação do Laravel
interface PaginationLink {
  url: string | null;
  label: string;
  active: boolean;
}

const props = defineProps({
  links: {
    type: Array as PropType<PaginationLink[]>,
    required: true,
  },
  containerClass: {
    type: String,
    default: 'flex items-center justify-center space-x-1 mt-8',
  },
  itemClass: {
    type: String,
    default: 'px-3 py-2 text-sm font-medium rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 dark:focus:ring-offset-gray-800',
  },
  activeClass: {
    type: String,
    default: 'bg-indigo-600 text-white hover:bg-indigo-700 dark:bg-indigo-500 dark:hover:bg-indigo-600',
  },
  inactiveClass: {
    type: String,
    default: 'bg-white text-gray-700 hover:bg-gray-100 dark:bg-gray-800 dark:text-gray-300 dark:hover:bg-gray-700 border border-gray-300 dark:border-gray-600',
  },
  disabledClass: {
    type: String,
    default: 'bg-gray-100 text-gray-400 cursor-not-allowed dark:bg-gray-700 dark:text-gray-500',
  },
});

const previousLink = computed(() => props.links[0] || null);
const nextLink = computed(() => props.links[props.links.length - 1] || null);

const pageLinks = computed(() => {
    return props.links.slice(1, -1);
});

// Função para limpar e traduzir labels de "Previous" e "Next"
function cleanLabel(label: string): string {
    // Verifica se o label já está traduzido pelo Laravel (comum em pt_BR)
    if (label === '&laquo; Anterior') {
        return 'Anterior';
    }
    if (label === 'Próximo &raquo;') {
        return 'Próximo';
    }
    // Fallback para o caso de os labels virem em inglês
    if (label.toLowerCase().includes('previous')) {
        return 'Anterior';
    }
    if (label.toLowerCase().includes('next')) {
        return 'Próximo';
    }
    // Para números de página e "...", retorna o label como está
    return label;
}
</script>

<template>
    <nav v-if="links.length > 3" :class="containerClass" aria-label="Paginação">
        <Link
            v-if="previousLink"
            :href="previousLink.url || '#'"
            :class="[
                itemClass,
                previousLink.url ? inactiveClass : disabledClass,
                'flex items-center'
            ]"
            :disabled="!previousLink.url"
            preserve-scroll
            aria-label="Página Anterior"
        >
            <ChevronLeft class="h-4 w-4 mr-1" />
            {{ cleanLabel(previousLink.label) }}
        </Link>

        <template v-for="(link, index) in pageLinks" :key="`page-link-${index}`">
            <span
                v-if="link.label === '...'"
                :class="[itemClass, disabledClass, 'px-1.5']"
            >
                ...
            </span>
            <Link
                v-else
                :href="link.url || '#'"
                :class="[
                    itemClass,
                    link.active ? activeClass : inactiveClass,
                    !link.url ? disabledClass : ''
                ]"
                :disabled="!link.url"
                preserve-scroll
                v-html="link.label" :aria-current="link.active ? 'page' : undefined"
            />
        </template>

        <Link
            v-if="nextLink"
            :href="nextLink.url || '#'"
            :class="[
                itemClass,
                nextLink.url ? inactiveClass : disabledClass,
                'flex items-center'
            ]"
            :disabled="!nextLink.url"
            preserve-scroll
            aria-label="Próxima Página"
        >
            {{ cleanLabel(nextLink.label) }}
            <ChevronRight class="h-4 w-4 ml-1" />
        </Link>
    </nav>
</template>
