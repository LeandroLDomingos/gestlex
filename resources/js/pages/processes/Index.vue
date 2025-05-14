<script setup lang="ts">
import { defineProps, ref, h } from 'vue'
import { Head, Link } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'

// Tipos de usuário e breadcrumb (de acordo com sua estrutura)
import type { BreadcrumbItem, Process } from '@/types'

// Recebendo os dados vindos da controller via props
const props = defineProps<{
    processes: Process[]
}>()

// Definição dos breadcrumbs para o layout
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Casos', href: '/processes' },
]

</script>

<template>
    <Head title="Casos" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6 rounded-lg shadow-md">
        </div>
    </AppLayout>
</template>
