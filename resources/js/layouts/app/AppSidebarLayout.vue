<script setup lang="ts">
import Alert from '@/components/Alert.vue'; // Seu componente que usa useToast (ToastWithInertiaFlash)
import AppContent from '@/components/AppContent.vue';
import AppShell from '@/components/AppShell.vue'; // Se este for um componente filho, ajuste
import AppSidebar from '@/components/AppSidebar.vue';
import AppSidebarHeader from '@/components/AppSidebarHeader.vue';
import Toaster from '@/components/ui/toast/Toaster.vue'; // O componente que RENDERIZA os toasts
import type { BreadcrumbItemType } from '@/types';

interface Props {
    breadcrumbs?: BreadcrumbItemType[];
}

withDefaults(defineProps<Props>(), {
    breadcrumbs: () => [],
});
</script>

<template>
    <AppShell variant="sidebar">
        <AppSidebar />
        <AppContent variant="sidebar">
            <AppSidebarHeader :breadcrumbs="breadcrumbs" />
            <slot />
        </AppContent>
        <Alert />
        <Toaster />
    </AppShell>
</template>