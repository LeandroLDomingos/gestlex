<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import axios from 'axios'
import { Head, useForm, UseForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Label from '@/components/ui/label/Label.vue'
import Input from '@/components/ui/input/Input.vue'
import InputError from '@/components/InputError.vue'
import Button from '@/components/ui/button/Button.vue'
import {
    Select,
    SelectTrigger,
    SelectValue,
    SelectContent,
    SelectGroup,
    SelectLabel,
    SelectItem,
} from '@/components/ui/select'

// Importar o componente filho para campos de contato editáveis
import EditableContactFields from './EditableContactFields.vue' // Ajuste o caminho se necessário

import countries from 'i18n-iso-countries'
import pt from 'i18n-iso-countries/langs/pt.json'
// Ajustar a importação do tipo Contact para corresponder à estrutura de dados recebida
import type { BreadcrumbItem, Contact as GlobalContactType, ContactEmail, ContactPhone } from '@/types'

countries.registerLocale(pt)

// Helper para Ziggy (DEVE SER DEFINIDO ANTES DO USO)
const RGlobal = (window as any).route;
const route = (name?: string, params?: any, absolute?: boolean): string => {
    if (typeof RGlobal === 'function') {
        return RGlobal(name, params, absolute);
    }
    console.warn(`Helper de rota Ziggy não encontrado para a rota: ${name}. Usando fallback.`);
    let url = `/${name?.replace(/\./g, '/') || ''}`;
    if (params) {
        if (typeof params === 'object' && params !== null && !Array.isArray(params)) {
            Object.keys(params).forEach(key => {
                const paramPlaceholder = `:${key}`;
                const paramPlaceholderBraces = `{${key}}`;
                if (url.includes(paramPlaceholder)) {
                    url = url.replace(paramPlaceholder, String(params[key]));
                } else if (url.includes(paramPlaceholderBraces)) {
                    url = url.replace(paramPlaceholderBraces, String(params[key]));
                } else if (Object.keys(params).length === 1 && !url.includes(String(params[key]))) {
                    const paramValueString = String(params[key]);
                    if (url.split('/').pop() !== paramValueString) {
                        url += `/${paramValueString}`;
                    }
                }
            });
        } else if (typeof params !== 'object') {
             url += `/${params}`;
        }
    }
    return url;
};

interface ContactEditFormData {
  id: number | string;
  type: 'physical' | 'legal';
  name?: string;
  business_name?: string;
  cpf_cnpj: string;
  rg?: string;
  gender?: string;
  nationality?: string;
  marital_status?: string;
  profession?: string;
  date_of_birth?: string;
  zip_code?: string;
  address?: string;
  neighborhood?: string;
  city?: string;
  state?: string;
  complement?: string;
  number?: string;
  business_activity?: string;
  tax_state?: string;
  tax_city?: string;
  administrator_id?: string | number | null;
  emails: string[];
  phones: string[];
}

const props = defineProps<{
    contacts: GlobalContactType[]
    contact: {
        id: number | string;
        type: 'physical' | 'legal';
        name: string | null;
        business_name: string | null;
        cpf_cnpj: string;
        rg: string | null;
        gender: string | null;
        nationality: string | null;
        marital_status: string | null;
        profession: string | null;
        date_of_birth: string | null;
        zip_code: string | null;
        address: string | null;
        neighborhood: string | null;
        city: string | null;
        state: string | null;
        complement: string | null;
        number: string | null;
        business_activity: string | null;
        tax_state: string | null;
        tax_city: string | null;
        administrator_id: number | string | null;
        emails: ContactEmail[];
        phones: ContactPhone[];
    }
}>()

const activeTab = ref<'physical' | 'legal'>(props.contact.type)

function getContactDisplayName(contactData: typeof props.contact): string {
    if (!contactData) return 'Contato';
    if (contactData.type === 'physical') {
        return contactData.name || 'Editar Contato Físico';
    }
    return contactData.business_name || contactData.name || 'Editar Contato Jurídico';
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Contatos', href: route('contacts.index') },
    {
        title: getContactDisplayName(props.contact),
        href: route('contacts.show', props.contact.id),
    },
    { title: 'Editar', href: route('contacts.edit', { contact: props.contact.id }) },
]

const adminOptions = computed(() =>
    props.contacts.map(c => ({ value: c.id, label: c.name || 'N/A' }))
)

type PhysicalFormType = Omit<ContactEditFormData, 'id' | 'business_name' | 'business_activity' | 'tax_state' | 'tax_city' | 'administrator_id'> & { _method: 'PUT' };
type LegalFormType = Omit<ContactEditFormData, 'id' | 'name' | 'rg' | 'gender' | 'nationality' | 'marital_status' | 'profession' | 'date_of_birth'> & { _method: 'PUT' };

const formPF = useForm<PhysicalFormType>({
    _method: 'PUT',
    type: 'physical' as const,
    name: props.contact.name || '',
    cpf_cnpj: props.contact.cpf_cnpj || '',
    rg: props.contact.rg || '',
    gender: props.contact.gender ? props.contact.gender.toLowerCase() : '',
    nationality: props.contact.nationality || 'BR',
    marital_status: props.contact.marital_status ? props.contact.marital_status.toLowerCase() : '',
    profession: props.contact.profession || '',
    date_of_birth: props.contact.date_of_birth || '',
    zip_code: props.contact.zip_code || '',
    address: props.contact.address || '',
    neighborhood: props.contact.neighborhood || '',
    city: props.contact.city || '',
    state: props.contact.state || '',
    complement: props.contact.complement || '',
    number: props.contact.number || '',
    emails: props.contact.emails && props.contact.emails.length > 0
        ? props.contact.emails.map(e => e.email)
        : [''],
    phones: props.contact.phones && props.contact.phones.length > 0
        ? props.contact.phones.map(p => p.phone)
        : [''],
})

const formPJ = useForm<LegalFormType>({
    _method: 'PUT',
    type: 'legal' as const,
    name: props.contact.name || '',
    business_name: props.contact.business_name || '',
    cpf_cnpj: props.contact.cpf_cnpj || '',
    business_activity: props.contact.business_activity || '',
    tax_state: props.contact.tax_state || '',
    tax_city: props.contact.tax_city || '',
    zip_code: props.contact.zip_code || '',
    address: props.contact.address || '',
    neighborhood: props.contact.neighborhood || '',
    city: props.contact.city || '',
    state: props.contact.state || '',
    complement: props.contact.complement || '',
    number: props.contact.number || '',
    administrator_id: props.contact.administrator_id || '',
    emails: props.contact.emails && props.contact.emails.length > 0
        ? props.contact.emails.map(e => e.email)
        : [''],
    phones: props.contact.phones && props.contact.phones.length > 0
        ? props.contact.phones.map(p => p.phone)
        : [''],
})

const nationalityOptions = computed(() => {
    const list = countries.getNames('pt', { select: 'official' })
    return Object.entries(list)
        .map(([code, name]) => ({ code, name: name as string }))
        .sort((a, b) => a.name.localeCompare(b.name))
})
const genderOptions = [
    { value: 'male', label: 'Masculino' },
    { value: 'female', label: 'Feminino' },
    { value: 'other', label: 'Outro' },
    { value: 'prefer_not_to_say', label: 'Prefiro não dizer' },
]
const maritalOptions = [
    { value: 'single', label: 'Solteiro(a)' },
    { value: 'married', label: 'Casado(a)' },
    { value: 'divorced', label: 'Divorciado(a)' },
    { value: 'widowed', label: 'Viúvo(a)' },
    { value: 'separated', label: 'Separado Judicialmente' },
    { value: 'common_law', label: 'União Estável' },
]
const stateOptions = [
    { value: 'AC', label: 'Acre' }, { value: 'AL', label: 'Alagoas' },
    { value: 'AP', label: 'Amapá' }, { value: 'AM', label: 'Amazonas' },
    { value: 'BA', label: 'Bahia' }, { value: 'CE', label: 'Ceará' },
    { value: 'DF', label: 'Distrito Federal' }, { value: 'ES', label: 'Espírito Santo' },
    { value: 'GO', label: 'Goiás' }, { value: 'MA', label: 'Maranhão' },
    { value: 'MT', label: 'Mato Grosso' }, { value: 'MS', label: 'Mato Grosso do Sul' },
    { value: 'MG', label: 'Minas Gerais' }, { value: 'PA', label: 'Pará' },
    { value: 'PB', label: 'Paraíba' }, { value: 'PR', label: 'Paraná' },
    { value: 'PE', label: 'Pernambuco' }, { value: 'PI', label: 'Piauí' },
    { value: 'RJ', label: 'Rio de Janeiro' }, { value: 'RN', label: 'Rio Grande do Norte' },
    { value: 'RS', label: 'Rio Grande do Sul' }, { value: 'RO', label: 'Rondônia' },
    { value: 'RR', label: 'Roraima' }, { value: 'SC', label: 'Santa Catarina' },
    { value: 'SP', label: 'São Paulo' }, { value: 'SE', label: 'Sergipe' },
    { value: 'TO', label: 'Tocantins' },
]

type AnyFormInstance = UseForm<any>;

async function fetchViaCEP(cep: string, form: AnyFormInstance) {
    const cleanCep = cep.replace(/\D/g, '')
    if (cleanCep.length !== 8) {
        form.clearErrors('zip_code', 'address', 'neighborhood', 'city', 'state', 'complement');
        return
    }
    try {
        form.processing = true;
        const { data } = await axios.get(`https://viacep.com.br/ws/${cleanCep}/json/`)
        if (data.erro) {
            form.setError('zip_code', 'CEP não encontrado. Verifique e tente novamente.')
            form.address = ''
            form.neighborhood = ''
            form.city = ''
            form.state = ''
            form.complement = ''
        } else {
            form.address = data.logradouro || ''
            form.neighborhood = data.bairro || ''
            form.city = data.localidade || ''
            form.state = data.uf || ''
            form.complement = data.complemento || ''
            form.clearErrors('zip_code', 'address', 'neighborhood', 'city', 'state', 'complement')
        }
    } catch (err) {
        console.error('Erro ao buscar CEP via ViaCEP:', err)
        form.setError('zip_code', 'Erro ao buscar CEP. Tente novamente mais tarde.')
    } finally {
        form.processing = false;
    }
}

watch(() => formPF.zip_code, (val) => { if (val) fetchViaCEP(val, formPF) })
watch(() => formPJ.zip_code, (val) => { if (val) fetchViaCEP(val, formPJ) })

function addItem(form: AnyFormInstance, field: 'emails' | 'phones') {
    if (field === 'emails') {
        form.emails.push('')
    } else if (field === 'phones') {
        form.phones.push('')
    }
}
function removeItem(form: AnyFormInstance, field: 'emails' | 'phones', index: number) {
    const targetArray = form[field];
    if (targetArray.length > 1) {
        targetArray.splice(index, 1)
    } else {
        targetArray[0] = ''
    }
}

function sanitizeString(value?: string): string {
    return value ? String(value).replace(/\D/g, '') : '';
}

function sanitizePhoneNumbers(phones: string[]): string[] {
  return phones
    .map(phone => sanitizeString(phone))
    .filter(digits => digits.length >= 10 || digits.length === 0);
}

function filterEmptyEmails(emails: string[]): string[] {
    return emails.filter(email => email && email.trim() !== '');
}

function submitPF() {
    formPF.transform(data => ({
        ...data,
        cpf_cnpj: sanitizeString(data.cpf_cnpj),
        rg: sanitizeString(data.rg),
        zip_code: sanitizeString(data.zip_code),
        phones: sanitizePhoneNumbers(data.phones),
        emails: filterEmptyEmails(data.emails),
    })).put(route('contacts.update', { contact: props.contact.id }), {
        preserveScroll: true,
        onSuccess: () => { /* Lógica de sucesso, ex: toast */ },
        onError: (errors) => { console.error("Erros do backend (PF):", errors); }
    })
}
function submitPJ() {
    formPJ.transform(data => ({
        ...data,
        cpf_cnpj: sanitizeString(data.cpf_cnpj),
        zip_code: sanitizeString(data.zip_code),
        phones: sanitizePhoneNumbers(data.phones),
        emails: filterEmptyEmails(data.emails),
    })).put(route('contacts.update', { contact: props.contact.id }), {
        preserveScroll: true,
        onSuccess: () => { /* Lógica de sucesso, ex: toast */ },
        onError: (errors) => { console.error("Erros do backend (PJ):", errors); }
    })
}

</script>

<template>
    <Head :title="`Editar: ${getContactDisplayName(props.contact)}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-4 sm:p-6 lg:p-8 max-w-4xl mx-auto bg-white dark:bg-gray-800 rounded-lg shadow-xl space-y-6">
            <div class="flex border-b border-gray-200 dark:border-gray-700">
                <button
                    @click="activeTab = 'physical'"
                    :disabled="props.contact.type === 'legal'"
                    :class="[
                        'py-3 px-4 font-medium text-sm focus:outline-none',
                        activeTab === 'physical'
                            ? 'border-b-2 border-indigo-500 text-indigo-600 dark:text-indigo-400'
                            : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200',
                        props.contact.type === 'legal' ? 'opacity-50 cursor-not-allowed' : ''
                    ]"
                >
                    Pessoa Física
                </button>
                <button
                    @click="activeTab = 'legal'"
                    :disabled="props.contact.type === 'physical'"
                    :class="[
                        'py-3 px-4 font-medium text-sm focus:outline-none',
                        activeTab === 'legal'
                            ? 'border-b-2 border-indigo-500 text-indigo-600 dark:text-indigo-400'
                            : 'text-gray-500 hover:text-gray-700 dark:text-gray-400 dark:hover:text-gray-200',
                        props.contact.type === 'physical' ? 'opacity-50 cursor-not-allowed' : ''
                    ]"
                >
                    Pessoa Jurídica
                </button>
            </div>

            <form v-if="activeTab === 'physical'" @submit.prevent="submitPF" class="space-y-6">
                <h2 class="text-xl font-semibold text-gray-700 dark:text-gray-200">Editar Pessoa Física</h2>
                
                <div class="grid gap-2">
                    <Label for="name_pf">Nome Completo <span class="text-red-500">*</span></Label>
                    <Input id="name_pf" v-model="formPF.name" required placeholder="Nome Completo do Indivíduo" />
                    <InputError :message="formPF.errors.name" />
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <Label for="cpf_pf">CPF <span class="text-red-500">*</span></Label>
                        <Input id="cpf_pf" v-model="formPF.cpf_cnpj" v-imask="{ mask: '000.000.000-00', unmask: true, lazy: false }" required placeholder="000.000.000-00" />
                        <InputError :message="formPF.errors.cpf_cnpj" />
                    </div>
                    <div>
                        <Label for="rg_pf">RG</Label>
                        <Input id="rg_pf" v-model="formPF.rg" placeholder="Número do RG" />
                        <InputError :message="formPF.errors.rg" />
                    </div>
                </div>
                 <div>
                    <Label for="date_of_birth_pf">Data de Nascimento</Label>
                    <Input id="date_of_birth_pf" v-model="formPF.date_of_birth" type="date" placeholder="DD/MM/AAAA" />
                    <InputError :message="formPF.errors.date_of_birth" />
                </div>

                <EditableContactFields
                    v-model:emails="formPF.emails"
                    v-model:phones="formPF.phones"
                    :form-errors="formPF.errors"
                    email-field-name="emails"
                    phone-field-name="phones"
                    @add-email="addItem(formPF, 'emails')"
                    @remove-email="index => removeItem(formPF, 'emails', index)"
                    @add-phone="addItem(formPF, 'phones')"
                    @remove-phone="index => removeItem(formPF, 'phones', index)"
                    form-identifier="pf"
                />

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <Label for="gender_pf">Gênero</Label>
                        <Select v-model="formPF.gender" id="gender_pf">
                            <SelectTrigger> <SelectValue placeholder="Selecione o Gênero" /> </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectLabel>Gêneros</SelectLabel>
                                    <SelectItem v-for="o in genderOptions" :key="o.value" :value="o.value">{{ o.label }}</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <InputError :message="formPF.errors.gender" />
                    </div>
                    <div>
                        <Label for="nationality_pf">Nacionalidade</Label>
                        <Select v-model="formPF.nationality" id="nationality_pf">
                            <SelectTrigger>
                                <SelectValue placeholder="Selecione a Nacionalidade" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectLabel>País</SelectLabel>
                                    <SelectItem v-for="o in nationalityOptions" :key="o.code" :value="o.code">{{ o.name }}</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <InputError :message="formPF.errors.nationality" />
                    </div>
                    <div>
                        <Label for="marital_status_pf">Estado Civil</Label>
                        <Select v-model="formPF.marital_status" id="marital_status_pf">
                            <SelectTrigger>
                                <SelectValue placeholder="Selecione o Estado Civil" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectLabel>Estado Civil</SelectLabel>
                                    <SelectItem v-for="o in maritalOptions" :key="o.value" :value="o.value">{{ o.label }}</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <InputError :message="formPF.errors.marital_status" />
                    </div>
                </div>
                
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 pt-2">Endereço</h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                    <div class="md:col-span-1">
                        <Label for="profession_pf">Profissão</Label>
                        <Input id="profession_pf" v-model="formPF.profession" placeholder="Ex: Advogado(a)" />
                        <InputError :message="formPF.errors.profession" />
                    </div>
                    <div class="md:col-span-1">
                        <Label for="zip_pf">CEP</Label>
                        <Input id="zip_pf" v-model="formPF.zip_code" v-imask="{ mask: '00000-000', unmask: true, lazy: false }" placeholder="00000-000" />
                        <InputError :message="formPF.errors.zip_code" />
                    </div>
                    <div class="md:col-span-2">
                        <Label for="address_pf">Endereço (Rua/Avenida)</Label>
                        <Input id="address_pf" v-model="formPF.address" placeholder="Preenchido automaticamente pelo CEP" :readonly="!!formPF.address && !formPF.errors.zip_code" />
                        <InputError :message="formPF.errors.address" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <Label for="neighborhood_pf">Bairro</Label>
                        <Input id="neighborhood_pf" v-model="formPF.neighborhood" placeholder="Preenchido pelo CEP" :readonly="!!formPF.neighborhood && !formPF.errors.zip_code" />
                        <InputError :message="formPF.errors.neighborhood" />
                    </div>
                    <div>
                        <Label for="city_pf">Cidade</Label>
                        <Input id="city_pf" v-model="formPF.city" placeholder="Preenchida pelo CEP" :readonly="!!formPF.city && !formPF.errors.zip_code" />
                        <InputError :message="formPF.errors.city" />
                    </div>
                    <div>
                        <Label for="state_pf">Estado (UF)</Label>
                        <Input id="state_pf" v-model="formPF.state" placeholder="Preenchido pelo CEP" :readonly="!!formPF.state && !formPF.errors.zip_code" />
                        <InputError :message="formPF.errors.state" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <Label for="number_pf">Número</Label>
                        <Input id="number_pf" v-model="formPF.number" placeholder="Ex: 123 ou S/N" />
                        <InputError :message="formPF.errors.number" />
                    </div>
                    <div>
                        <Label for="complement_pf">Complemento</Label>
                        <Input id="complement_pf" v-model="formPF.complement" placeholder="Ex: Apto 101, Bloco B" />
                        <InputError :message="formPF.errors.complement" />
                    </div>
                </div>

                <div class="pt-4">
                    <Button type="submit" :disabled="formPF.processing" class="w-full sm:w-auto">
                        Atualizar Pessoa Física
                    </Button>
                </div>
            </form>

            <form v-else @submit.prevent="submitPJ" class="space-y-6">
                <h2 class="text-xl font-semibold text-gray-700 dark:text-gray-200">Editar Pessoa Jurídica</h2>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <Label for="name_pj">Nome Fantasia <span class="text-red-500">*</span></Label>
                        <Input id="name_pj" v-model="formPJ.name" required placeholder="Nome Fantasia da Empresa" />
                        <InputError :message="formPJ.errors.name" />
                    </div>
                    <div>
                        <Label for="business_name_pj">Razão Social <span class="text-red-500">*</span></Label>
                        <Input id="business_name_pj" v-model="formPJ.business_name" required placeholder="Razão Social Completa" />
                        <InputError :message="formPJ.errors.business_name" />
                    </div>
                </div>

                <EditableContactFields
                    v-model:emails="formPJ.emails"
                    v-model:phones="formPJ.phones"
                    :form-errors="formPJ.errors"
                    email-field-name="emails"
                    phone-field-name="phones"
                    @add-email="addItem(formPJ, 'emails')"
                    @remove-email="index => removeItem(formPJ, 'emails', index)"
                    @add-phone="addItem(formPJ, 'phones')"
                    @remove-phone="index => removeItem(formPJ, 'phones', index)"
                    form-identifier="pj"
                />

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <Label for="cnpj_pj">CNPJ <span class="text-red-500">*</span></Label>
                        <Input id="cnpj_pj" v-model="formPJ.cpf_cnpj" v-imask="{ mask: '00.000.000/0000-00', unmask: true, lazy: false }" required placeholder="00.000.000/0000-00" />
                        <InputError :message="formPJ.errors.cpf_cnpj" />
                    </div>
                    <div>
                        <Label for="business_activity_pj">Atividade Principal</Label>
                        <Input id="business_activity_pj" v-model="formPJ.business_activity" placeholder="Ramo de Atividade da Empresa"/>
                        <InputError :message="formPJ.errors.business_activity" />
                    </div>
                    <div>
                        <Label for="administrator_id_pj">Administrador Responsável</Label>
                        <Select v-model="formPJ.administrator_id" id="administrator_id_pj">
                            <SelectTrigger>
                                <SelectValue placeholder="Selecione um Contato" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectLabel>Contatos Físicos Existentes</SelectLabel>
                                     <SelectItem v-if="adminOptions.length === 0" value="" disabled>Nenhum contato físico disponível</SelectItem>
                                    <SelectItem v-for="o in adminOptions" :key="o.value" :value="o.value">{{ o.label }}</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <InputError :message="formPJ.errors.administrator_id" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <Label for="tax_state_pj">Estado de Tributação</Label>
                        <Select v-model="formPJ.tax_state" id="tax_state_pj">
                            <SelectTrigger>
                                <SelectValue placeholder="Selecione o Estado" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectLabel>Estados Brasileiros</SelectLabel>
                                    <SelectItem v-for="o in stateOptions" :key="o.value" :value="o.value">{{ o.label }}</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <InputError :message="formPJ.errors.tax_state" />
                    </div>
                    <div>
                        <Label for="tax_city_pj">Município de Tributação</Label>
                        <Input id="tax_city_pj" v-model="formPJ.tax_city" placeholder="Nome do Município" />
                        <InputError :message="formPJ.errors.tax_city" />
                    </div>
                </div>
                
                <h3 class="text-lg font-medium text-gray-900 dark:text-gray-100 pt-2">Endereço</h3>
                <div class="grid grid-cols-1 md:grid-cols-4 gap-4 items-end">
                     <div class="md:col-span-1">
                        <Label for="zip_pj">CEP</Label>
                        <Input id="zip_pj" v-model="formPJ.zip_code" v-imask="{ mask: '00000-000', unmask: true, lazy: false }" placeholder="00000-000" />
                        <InputError :message="formPJ.errors.zip_code" />
                    </div>
                    <div class="md:col-span-3">
                        <Label for="address_pj">Endereço (Rua/Avenida)</Label>
                        <Input id="address_pj" v-model="formPJ.address" placeholder="Preenchido automaticamente pelo CEP" :readonly="!!formPJ.address && !formPJ.errors.zip_code" />
                        <InputError :message="formPJ.errors.address" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-3 gap-4">
                    <div>
                        <Label for="neighborhood_pj">Bairro</Label>
                        <Input id="neighborhood_pj" v-model="formPJ.neighborhood" placeholder="Preenchido pelo CEP" :readonly="!!formPJ.neighborhood && !formPJ.errors.zip_code" />
                        <InputError :message="formPJ.errors.neighborhood" />
                    </div>
                    <div>
                        <Label for="city_pj">Cidade</Label>
                        <Input id="city_pj" v-model="formPJ.city" placeholder="Preenchida pelo CEP" :readonly="!!formPJ.city && !formPJ.errors.zip_code" />
                        <InputError :message="formPJ.errors.city" />
                    </div>
                    <div>
                        <Label for="state_pj">Estado (UF)</Label>
                        <Input id="state_pj" v-model="formPJ.state" placeholder="Preenchido pelo CEP" :readonly="!!formPJ.state && !formPJ.errors.zip_code" />
                        <InputError :message="formPJ.errors.state" />
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div>
                        <Label for="number_pj">Número</Label>
                        <Input id="number_pj" v-model="formPJ.number" placeholder="Ex: 123 ou S/N" />
                        <InputError :message="formPJ.errors.number" />
                    </div>
                    <div>
                        <Label for="complement_pj">Complemento</Label>
                        <Input id="complement_pj" v-model="formPJ.complement" placeholder="Ex: Sala 10, Prédio Anexo" />
                        <InputError :message="formPJ.errors.complement" />
                    </div>
                </div>

                <div class="pt-4">
                    <Button type="submit" :disabled="formPJ.processing" class="w-full sm:w-auto">
                        Atualizar Pessoa Jurídica
                    </Button>
                </div>
            </form>
        </div>
    </AppLayout>
</template>