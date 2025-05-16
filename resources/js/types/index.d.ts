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
