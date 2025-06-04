<script setup lang="ts">
import NavFooter from '@/components/NavFooter.vue';
import NavMain from '@/components/NavMain.vue';
import NavUser from '@/components/NavUser.vue';
import { Sidebar, SidebarContent, SidebarFooter, SidebarHeader, SidebarMenu, SidebarMenuButton, SidebarMenuItem } from '@/components/ui/sidebar';
import { type NavItem } from '@/types'; // Make sure NavItem includes 'permission'
import { Link } from '@inertiajs/vue3';
import { BookOpen, Folder, LayoutGrid, Contact, Signature, ClipboardCheck, DollarSign, Shield, Users } from 'lucide-vue-next';
import AppLogo from './AppLogo.vue';
import { usePermissions } from '@/composables/usePermissions';
import { computed } from 'vue'; // Import computed

const { can } = usePermissions();

// Define the main navigation items
// Each item can optionally have a 'permission' property.
// If 'permission' is present, the item will only be shown if the user 'can' perform that action.
const mainNavItems: NavItem[] = [
    {
        title: 'Contatos',
        href: '/contacts',
        icon: Contact,
        permission: 'contacts.index'
    },
    {
        title: 'Casos',
        href: '/processes',
        icon: Signature,
        permission: 'processes.index'
    },
    {
        title: 'Tarefas',
        href: '/tasks',
        icon: ClipboardCheck,
        permission: 'tasks.index'
    },
    {
        title: 'Finanças', // Corrected typo from "Financias"
        href: '/financial-transactions',
        icon: DollarSign,
        permission: 'financial-transactions.index'
    },
];

// Define footer navigation items
const footerNavItems: NavItem[] = [
    {
        title: 'Perfis',
        href: '/admin/roles',
        icon: Shield,
        permission: 'roles.index'
    },
    {
        title: 'Usuários',
        href: '/admin/users',
        icon: Users,
        permission: 'users.index'
    },
];

// Create a computed property to filter main navigation items based on user permissions.
// This ensures the list is reactive and updates if permissions change.
const filteredMainItems = computed(() => {
    return mainNavItems.filter(item => !item.permission || can(item.permission));
});

// Create a computed property to filter footer navigation items based on user permissions.
const filteredFooterItems = computed(() => {
    return footerNavItems.filter(item => !item.permission || can(item.permission));
});
</script>

<template>
    <Sidebar collapsible="icon" variant="inset">
        <SidebarHeader>
            <SidebarMenu>
                <SidebarMenuItem>
                    <SidebarMenuButton size="lg" as-child>
                        <Link :href="route('contacts.index')">
                        <AppLogo />
                        </Link>
                    </SidebarMenuButton>
                </SidebarMenuItem>
            </SidebarMenu>
        </SidebarHeader>

        <SidebarContent>
            <!-- Pass the filtered list of main navigation items to the NavMain component -->
            <NavMain :items="filteredMainItems" />
        </SidebarContent>

        <SidebarFooter>
            <!-- Pass the filtered list of footer navigation items to the NavFooter component -->
            <NavFooter :items="filteredFooterItems" />
            <NavUser />
        </SidebarFooter>
    </Sidebar>
    <!-- The main content of the page will be rendered here -->
    <slot />
</template>
