<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { useForm } from '@inertiajs/vue3';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Checkbox } from '@/components/ui/checkbox';
import { Role, Permission, SharedData } from '@/types'; // Certifique-se que Role e Permission estão em types
import { Card, CardContent, CardHeader, CardTitle, CardDescription } from '@/components/ui/card';
import { ScrollArea } from '@/components/ui/scroll-area';

interface Props {
    role?: Role & { permissions?: Permission[] }; // Papel existente para edição
    allPermissions: Permission[]; // Todas as permissões disponíveis no sistema
    submitButtonText?: string;
    formAction: 'create' | 'edit';
}

const props = withDefaults(defineProps<Props>(), {
    submitButtonText: 'Salvar',
});

const emit = defineEmits(['submit']);

// Define a estrutura do formulário
const form = useForm({
    name: props.role?.name || '',
    description: props.role?.description || '',
    level: props.role?.level || 0,
    permissions: props.role?.permissions?.map(p => p.id) || [] as string[], // Array de IDs de permissões
});

// Observa mudanças no prop 'role' para atualizar o formulário (útil na página de edição)
watch(() => props.role, (newRole) => {
    if (newRole) {
        form.name = newRole.name;
        form.description = newRole.description || '';
        form.level = newRole.level;
        form.permissions = newRole.permissions?.map(p => p.id) || [];
    } else {
        form.reset();
    }
}, { deep: true });


const submit = () => {
    emit('submit', form);
};

// Agrupar permissões por um prefixo comum (ex: 'contacts.', 'processes.')
const groupedPermissions = computed(() => {
    const groups: Record<string, Permission[]> = {};
    props.allPermissions.forEach(permission => {
        const groupName = permission.name.split('.')[0] || 'outras'; // Ex: 'contacts' de 'contacts.create'
        if (!groups[groupName]) {
            groups[groupName] = [];
        }
        groups[groupName].push(permission);
    });
    return groups;
});

const getPermissionLabel = (permissionName: string): string => {
    const parts = permissionName.split('.');
    if (parts.length > 1) {
        const action = parts.pop(); // Ex: 'create', 'view', 'delete'
        const resource = parts.join('.'); // Ex: 'contacts', 'processes.tasks'
        
        // Mapeamento de ações para traduções mais amigáveis
        const actionTranslations: Record<string, string> = {
            'index': 'Listar',
            'create': 'Criar',
            'store': 'Salvar (Criar)',
            'show': 'Visualizar',
            'edit': 'Editar',
            'update': 'Atualizar',
            'destroy': 'Excluir',
            'view': 'Ver',
            'viewAny': 'Ver Qualquer',
            // Adicione mais traduções conforme necessário
        };
        const translatedAction = actionTranslations[action || ''] || ucfirst(action || '');
        return `${translatedAction} ${ucfirst(resource.replace(/\./g, ' '))}`;
    }
    return ucfirst(permissionName.replace(/\./g, ' '));
};

const ucfirst = (str: string) => {
    return str.charAt(0).toUpperCase() + str.slice(1);
};

</script>

<template>
    <form @submit.prevent="submit">
        <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
            <!-- Coluna Principal (Nome, Descrição, Nível) -->
            <div class="md:col-span-2 space-y-6">
                <Card>
                    <CardHeader>
                        <CardTitle>Detalhes do Papel</CardTitle>
                        <CardDescription>
                            Defina o nome, descrição e o nível hierárquico do papel.
                            O nível determina a precedência (maior nível = mais poder).
                        </CardDescription>
                    </CardHeader>
                    <CardContent class="space-y-4">
                        <div>
                            <Label for="name">Nome do Papel</Label>
                            <Input id="name" v-model="form.name" type="text" required />
                            <div v-if="form.errors.name" class="text-sm text-red-600 mt-1">{{ form.errors.name }}</div>
                        </div>
                        <div>
                            <Label for="description">Descrição</Label>
                            <Textarea id="description" v-model="form.description" />
                            <div v-if="form.errors.description" class="text-sm text-red-600 mt-1">{{ form.errors.description }}</div>
                        </div>
                        <div>
                            <Label for="level">Nível</Label>
                            <Input id="level" v-model.number="form.level" type="number" min="0" required />
                            <div v-if="form.errors.level" class="text-sm text-red-600 mt-1">{{ form.errors.level }}</div>
                        </div>
                    </CardContent>
                </Card>
            </div>

            <!-- Coluna de Permissões -->
            <div class="md:col-span-1">
                <Card>
                    <CardHeader>
                        <CardTitle>Permissões</CardTitle>
                        <CardDescription>Atribua permissões a este papel.</CardDescription>
                    </CardHeader>
                    <CardContent>
                        <ScrollArea class="h-96"> <!-- Ajuste a altura conforme necessário -->
                            <div class="space-y-4">
                                <div v-for="(permissionsInGroup, groupName) in groupedPermissions" :key="groupName">
                                    <h4 class="font-semibold text-md mb-2 capitalize text-slate-700 dark:text-slate-300">{{ groupName.replace('_', ' ') }}</h4>
                                    <div class="space-y-2 ml-2">
                                        <div v-for="permission in permissionsInGroup" :key="permission.id" class="flex items-center space-x-2">
                                            <Checkbox
                                                :id="`permission-${permission.id}`"
                                                :value="permission.id"
                                                v-model:checked="form.permissions"
                                            />
                                            <Label :for="`permission-${permission.id}`" class="font-normal cursor-pointer">
                                                {{ getPermissionLabel(permission.name) }}
                                                <span class="text-xs text-slate-500 dark:text-slate-400 block">({{ permission.name }})</span>
                                                <span v-if="permission.description" class="text-xs text-slate-400 dark:text-slate-500 block italic">{{ permission.description }}</span>
                                            </Label>
                                        </div>
                                    </div>
                                </div>
                                <div v-if="Object.keys(groupedPermissions).length === 0" class="text-sm text-slate-500">
                                    Nenhuma permissão disponível para atribuição.
                                </div>
                            </div>
                        </ScrollArea>
                        <div v-if="form.errors.permissions" class="text-sm text-red-600 mt-2">{{ form.errors.permissions }}</div>
                    </CardContent>
                </Card>
            </div>
        </div>

        <div class="mt-8 flex justify-end">
            <Button type="submit" :disabled="form.processing">
                {{ props.submitButtonText }}
            </Button>
        </div>
    </form>
</template>
