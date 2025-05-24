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
  id: number | string; // Ajustado para aceitar string se ID for UUID
  name: string;
  email: string;
  avatar?: string;
  email_verified_at: string | null;
  created_at: string;
  updated_at: string;
}

export type ContactType = 'physical' | 'legal'
export type Gender = 'female' | 'male' | 'other' | 'prefer_not_to_say'; // Adicionado prefer_not_to_say
export type MaritalStatus = 'single' | 'married' | 'common_law' | 'divorced' | 'widowed' | 'separated'


export interface Contact {
  id: number | string;
  type: ContactType
  name: string
  cpf_cnpj?: string
  rg?: string
  gender?: Gender
  gender_label?: string
  nationality?: string
  marital_status?: MaritalStatus
  marital_status_label?: string; // Adicionado
  profession?: string
  business_activity?: string
  business_name?: string
  tax_state?: string
  tax_city?: string
  administrator_id?: number | string; // Ajustado para aceitar string
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
  administrator?: Contact // Alterado de admin_contact para administrator para corresponder ao model
  date_of_birth?: string
  annotations?: ContactAnnotation[];
  documents?: ContactDocument[];
  processes?: RelatedProcess[]; // Casos/Processos onde este contato é o principal
  administeredContacts?: Contact[]; // Contatos que este contato (PF) administra
  tasks?: Task[]; // Tarefas diretamente associadas a este contato
  display_name?: string; // Accessor
}

export interface ContactEmail {
  id: number | string; // Ajustado
  contact_id: number | string; // Ajustado
  email: string
}

export interface ContactPhone {
  id: number | string; // Ajustado
  contact_id: number | string; // Ajustado
  phone: string
}

export type BreadcrumbItemType = BreadcrumbItem;

export interface PaginationLink {
  url: string | null;
  label: string;
  active: boolean;
}

export interface PaginationMeta {
  current_page: number;
  from: number | null;
  last_page: number;
  links: PaginationLink[];
  path: string;
  per_page: number;
  to: number | null;
  total: number;
}

export interface PaginatedResponse<T> {
  data: T[];
  links: PaginationLink[];
  meta: PaginationMeta;
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

export interface UserReference {
  id: string | number;
  name: string;
}

export interface ContactReference {
  id: string | number;
  name: string;
  business_name?: string; // Adicionado
  type: 'physical' | 'legal';
}

export interface ProcessAnnotation {
  id: string | number;
  content: string;
  user_name: string;
  created_at: string;
  user?: UserReference; // Adicionado
}

export interface ProcessTask { // Renomeado para evitar conflito com Task principal
  id: string | number;
  title: string;
  due_date: string | null;
  is_overdue: boolean;
  responsible_user: UserReference | null;
  status: string;
  description?: string | null;
  priority?: string; // Adicionado
  status_label?: string; // Adicionado
  priority_label?: string; // Adicionado
  responsibleUser?: UserReference; // Para consistência
}

export interface ProcessDocument {
  id: string | number;
  name: string;
  url: string;
  uploaded_at?: string; // Tornar opcional se nem sempre presente
  created_at?: string; // Adicionado para consistência
  file_type_icon?: string;
  size?: string | number; // Ajustado
  description?: string | null; // Adicionado
  uploader?: UserReference; // Adicionado
}

export interface ProcessHistoryEntry {
  id: string | number;
  action: string;
  description: string;
  user_name: string;
  created_at: string;
  user?: UserReference; // Adicionado
}

// Interface para Pagamentos do Processo
export interface ProcessPaymentData { // Renomeado para evitar conflito com Model
  id: string;
  amount: number | string;
  payment_type: 'a_vista' | 'parcelado'; // NOVO
  payment_type_label?: string; // NOVO (label do accessor)
  payment_method: string | null;
  payment_date: string | null; // Formato YYYY-MM-DD
  status: 'pending' | 'paid' | 'failed' | 'refunded';
  notes: string | null;
  created_at?: string;
  updated_at?: string;
}


export interface Process {
  id: string;
  title: string;
  origin: string | null;
  // negotiated_value: number | string | null; // Removido, agora é parte de payments
  description: string | null;
  workflow: 'prospecting' | 'consultative' | 'administrative' | 'judicial';
  workflow_label?: string;
  stage: number | null;
  stage_label?: string;
  responsible: UserReference | null;
  contact: ContactReference | null;
  created_at: string;
  updated_at: string;
  archived_at?: string | null; // Adicionado
  is_archived?: boolean; // Adicionado (accessor)
  priority: 'low' | 'medium' | 'high'; // Adicionado
  priority_label?: string; // Adicionado
  status: string; // Ex: Aberto, Em Andamento
  status_label?: string; // Adicionado
  due_date?: string | null; // Adicionado

  annotations?: ProcessAnnotation[];
  tasks?: ProcessTask[]; // Usar ProcessTask
  documents?: ProcessDocument[];
  historyEntries?: ProcessHistoryEntry[]; // Corrigido nome
  payments?: ProcessPaymentData[]; // Usar ProcessPaymentData
  payments_sum_amount?: number | string; // Soma dos pagamentos
  pending_tasks_count?: number; // Contagem de tarefas pendentes
}


export interface ContactAnnotation {
  id: string | number;
  content: string;
  user_name?: string;
  created_at: string;
  user?: UserReference;
}

export interface ContactDocument {
  id: string | number;
  name: string;
  url: string;
  uploaded_at?: string; // Tornar opcional
  created_at?: string; // Adicionado
  file_type_icon?: string;
  size?: string | number; // Ajustado
  description?: string | null; // Adicionado
  uploader?: UserReference; // Adicionado
}

export interface RelatedProcess { // Usado em Contact.processes
  id: string;
  title: string;
  workflow_label?: string;
  status?: string; // Ou um tipo mais específico se tiver
  status_label?: string; // Adicionado
  updated_at: string;
  documents?: ProcessDocument[]; // Adicionado para consistência
  responsible?: UserReference; // Adicionado
}


export type TaskStatus = 'Pendente' | 'Em Andamento' | 'Concluída' | 'Cancelada';
export type TaskPriority = 'Baixa' | 'Média' | 'Alta';

export interface Task {
  id: number | string;
  title: string;
  description?: string | null;
  due_date?: string | null;
  status: TaskStatus;
  priority: TaskPriority;
  completed_at?: string | null;
  created_at: string;
  updated_at: string;

  process_id?: number | string | null;
  process?: RelatedProcess | null;

  contact_id?: number | string | null;
  contact?: ContactReference | null; // Usar ContactReference para resumo

  responsible_user_id?: number | string | null;
  responsibleUser?: UserReference | null; // Usar UserReference

  responsibles?: UserReference[];

  status_label?: string;
  priority_label?: string;
  is_overdue?: boolean;
}

// Tipos para os formulários
export interface ProcessFormData {
    title: string;
    description: string | null;
    contact_id: string | number | null;
    responsible_id: string | number | null;
    workflow: string; // Chave do workflow
    stage: number | null;
    due_date: string | null;
    priority: string; // Chave da prioridade
    origin: string | null;
    status: string | null; // Chave do status
    payment: { // Agrupar dados do pagamento
        amount: string | number | null;
        payment_type: string | null; // 'a_vista' ou 'parcelado'
        method: string | null;
        date: string | null;
        notes: string | null;
    };
}
