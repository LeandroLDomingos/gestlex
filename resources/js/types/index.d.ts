import type { PageProps } from '@inertiajs/core';
import type { LucideIcon } from 'lucide-vue-next';
import type { Config } from 'ziggy-js';

export interface Auth {
  user: User;
}

export interface BreadcrumbItem {
  title: string;
  href: string;
}

export interface NavItem {
  title: string;
  href: string;
  icon?: LucideIcon;
  isActive?: boolean;
}

export interface SharedData extends PageProps {
  name: string;
  quote: { message: string; author: string };
  auth: Auth;
  ziggy: Config & { location: string };
  sidebarOpen: boolean;
}

export interface User {
  id: number;
  name: string;
  email: string;
  avatar?: string;
  email_verified_at: string | null;
  created_at: string;
  updated_at: string;
}

export type ContactType = 'physical' | 'legal'
export type Gender = 'female' | 'male'
export type MaritalStatus = 'single' | 'married' | 'common_law' | 'divorced' | 'widowed' | 'separated'

export interface Contact {
  id: number
  type: ContactType
  name: string
  cpf_cnpj?: string
  rg?: string
  gender?: Gender
  gender_label?: string
  nationality?: string
  marital_status?: MaritalStatus
  profession?: string
  business_activity?: string
  business_name?: string
  tax_state?: string
  tax_city?: string
  administrator_id?: number
  zip_code?: string
  address?: string;
  neighborhood?: string;
  city?: string;
  state?: string;
  complement?: string;
  number?: string;
  created_at?: string
  updated_at?: string
  emails?: ContactEmail[]
  phones?: ContactPhone[]
  admin_contact?: Contact
  date_of_birth?: string
}
export interface Process {
  id: string
  title: string
  origin: string
  negotiated_value: number
  description: string
  workflow: number
  stage: number
  responsible: { name: string }
}

export interface ContactEmail {
  id: number
  contact_id: number
  email: string
}

export interface ContactPhone {
  id: number
  contact_id: number
  phone: string
}

export type BreadcrumbItemType = BreadcrumbItem;

/**
 * Define a estrutura para um link de paginação individual,
 * como retornado pelo paginador do Laravel.
 */
export interface PaginationLink {
  url: string | null; // URL para a página, ou null se não aplicável (ex: "...")
  label: string;      // Rótulo do link (ex: "1", "2", "&laquo; Previous", "Next &raquo;", "...")
  active: boolean;    // True se este link representa a página atual
}

/**
 * Define a estrutura para metadados da paginação,
 * como retornado pelo paginator do Laravel.
 */
export interface PaginationMeta {
  current_page: number;    // A página atual
  from: number | null;         // O número do primeiro item na página atual
  last_page: number;       // A última página disponível
  links: PaginationLink[]; // Array de links de metadados (diferente dos links principais)
  path: string;            // URL base para a paginação
  per_page: number;        // Número de itens por página
  to: number | null;           // O número do último item na página atual
  total: number;           // Número total de itens em todas as páginas
}

/**
 * Define a estrutura genérica para uma resposta paginada do Laravel via Inertia.
 * @template T O tipo dos itens de dados dentro da coleção paginada.
 */
export interface PaginatedResponse<T> {
  data: T[];                     // Array dos itens de dados para a página atual
  links: PaginationLink[];       // Array de links de navegação da paginação (Previous, Next, números de página)
  meta: PaginationMeta;          // Objeto contendo metadados da paginação

  // Campos adicionais que o paginador do Laravel geralmente inclui no nível raiz:
  current_page: number;
  first_page_url: string | null;
  from: number | null;
  last_page: number;
  last_page_url: string | null;
  next_page_url: string | null;
  path: string;
  per_page: number;
  prev_page_url: string | null;
  to: number | null;
  total: number;
}

// Em resources/js/types/index.ts ou um novo arquivo resources/js/types/process.d.ts

export interface UserReference {
    id: string | number;
    name: string;
}

export interface ContactReference {
    id: string | number;
    name: string; // Nome da pessoa física ou nome fantasia/razão social da jurídica
    type: 'physical' | 'legal';
}

export interface ProcessAnnotation {
    id: string | number;
    content: string;
    user_name: string; // Nome do usuário que criou a anotação
    created_at: string; // Data da criação
    // Adicione outros campos se necessário, como 'user_avatar_url'
}

export interface ProcessTask {
    id: string | number;
    title: string;
    due_date: string; // Data de entrega
    is_overdue: boolean; // Indica se está atrasada
    responsible_user: UserReference | null; // Usuário responsável pela tarefa
    status: string; // Ex: "Pendente", "Em Andamento", "Concluída"
    description?: string | null;
    // Adicione outros campos como 'priority', 'completed_at', etc.
}

export interface ProcessDocument {
    id: string | number;
    name: string;
    url: string; // URL para download ou visualização
    uploaded_at: string;
    file_type_icon?: string; // Ex: 'pdf', 'doc', 'img' para mostrar um ícone
    size?: string;
}

export interface ProcessHistoryEntry {
    id: string | number;
    action: string; // Ex: "Criou tarefa", "Atualizou status", "Adicionou documento"
    description: string; // Detalhes da ação
    user_name: string;
    created_at: string;
}

export interface Process {
    id: string; // UUID
    title: string;
    origin: string | null;
    negotiated_value: number | string | null;
    description: string | null;
    workflow: 'prospecting' | 'consultative' | 'administrative' | 'judicial';
    workflow_label?: string; // Accessor do backend
    stage: number | null;
    stage_label?: string; // Accessor do backend
    responsible: UserReference | null;
    contact: ContactReference | null; // Contato principal associado ao processo
    created_at: string;
    updated_at: string;

    // Relacionamentos que seriam carregados (eager-loaded)
    annotations?: ProcessAnnotation[];
    tasks?: ProcessTask[];
    documents?: ProcessDocument[];
    history_entries?: ProcessHistoryEntry[];
    // Outros campos relevantes
    video_placeholder_url?: string; // Para o cartão azul
}

export interface BreadcrumbItem {
  title: string;
  href: string;
}
