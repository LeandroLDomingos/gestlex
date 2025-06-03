import { computed } from 'vue';
import { usePage } from '@inertiajs/vue3';
import { User, Role, Permission } from '@/types'; // Assuming Role and Permission types are also in @/types

export function usePermissions() {
  const page = usePage<{ auth: { user: User | null } }>();
  const user = computed(() => page.props.auth.user);

  /**
   * Verifica se o usuário possui a role informada.
   * Se o usuário tiver uma role com level > 5, considera como se tivesse qualquer role (incluindo 'Admin' para fins de bypass).
   */
  const hasRole = (roleName: string): boolean => {
    if (!user.value || !user.value.roles) return false; // Check if roles array exists

    // Check for a role with level > 5 (acts as a super admin)
    if (user.value.roles.some(r => r.level > 5)) return true;
    
    // Specifically check for 'Admin' role if no super admin role is found
    if (roleName === 'Admin' && user.value.roles.some(r => r.name === 'Admin')) return true;

    return user.value.roles.some(r => r.name === roleName);
  };

  /**
   * Agrega permissões diretas + permissões via roles, removendo duplicados.
   */
  const allPermissions = computed(() => {
    if (!user.value) return [];

    // Safely access permissions, default to empty array if undefined
    const direct = (user.value.permissions || []).map((p: Permission) => p.name);

    // Safely access roles and their permissions, default to empty array if undefined
    const viaRoles = (user.value.roles || [])
      .flatMap((r: Role) => (r.permissions || []).map((p: Permission) => p.name));
      
    return Array.from(new Set([...direct, ...viaRoles]));
  });

  /**
   * Retorna true se o usuário tiver a permissão (direta ou via role)
   * ou se tiver uma role com level > 5 (que concede todas as permissões).
   */
  const can = (permission: string): boolean => {
    if (!user.value) return false;

    // If user has any role with level > 5, grant permission
    if ((user.value.roles || []).some(r => r.level > 5)) return true;
    
    return allPermissions.value.includes(permission);
  };

  return { user, hasRole, can, allPermissions };
}
