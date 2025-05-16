<script setup lang="ts">
import { computed, ref, watch } from 'vue'
import axios from 'axios'
import { Head, useForm } from '@inertiajs/vue3'
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
import countries from 'i18n-iso-countries'
import pt from 'i18n-iso-countries/langs/pt.json'
import type { BreadcrumbItem, Contact } from '@/types'

countries.registerLocale(pt)

// Props vindos do controller
const props = defineProps<{
    contacts: Contact[]
    contact: Contact
}>()

// Aba ativa inicial (PF ou PJ)
const activeTab = ref<'physical' | 'legal'>(props.contact.type as any)

// Breadcrumbs para o layout
const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Contatos', href: '/contacts' },
    { title: 'Editar Contato', href: `/contacts/${props.contact.id}/edit` },
]

// Opções de administrador (dropdown)
const adminOptions = computed(() =>
    props.contacts.map(c => ({ value: c.id, label: c.name }))
)

// Formulário Pessoa Física (pré-populado)
const formPF = useForm({
    type: 'physical',
    name: props.contact.name,
    cpf_cnpj: props.contact.cpf_cnpj,
    rg: props.contact.rg || '',
    gender: props.contact.gender || '',
    nationality: props.contact.nationality || 'BR',
    marital_status: props.contact.marital_status || '',
    profession: props.contact.profession || '',
    zip_code: props.contact.zip_code || '',
    address: props.contact.address || '',
    neighborhood: props.contact.neighborhood || '',
    city: props.contact.city || '',
    state: props.contact.state || '',
    complement: props.contact.complement || '',
    number: props.contact.number || '',
    administrator_id: props.contact.administrator_id || '',
    emails: props.contact.emails.length
        ? props.contact.emails.map(e => e.email)
        : [''],
    phones: props.contact.phones.length
        ? props.contact.phones.map(p => p.phone)
        : [''],

})

// Formulário Pessoa Jurídica (pré-populado)
const formPJ = useForm({
    type: 'legal',
    name: props.contact.name || '',
    name: props.contact.name || '',
    business_name: props.contact.business_name || '',
    cpf_cnpj: props.contact.cpf_cnpj,
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
    emails: props.contact.emails.length
        ? props.contact.emails.map(e => e.email)
        : [''],
    phones: props.contact.phones.length
        ? props.contact.phones.map(p => p.phone)
        : [''],
})

// Opções de selects estáticas
const nationalityOptions = computed(() => {
    const list = countries.getNames('pt', { select: 'official' })
    return Object.entries(list)
        .map(([code, name]) => ({ code, name }))
        .sort((a, b) => a.name.localeCompare(b.name))
})
const genderOptions = [
    { value: 'male', label: 'Masculino' },
    { value: 'female', label: 'Feminino' },
    { value: 'other', label: 'Outro' },
]
const maritalOptions = [
    { value: 'single', label: 'Solteiro(a)' },
    { value: 'married', label: 'Casado(a)' },
    { value: 'divorced', label: 'Divorciado(a)' },
    { value: 'widowed', label: 'Viúvo(a)' },
    { value: 'separated', label: 'Separado(a)' },
]
const stateOptions = [
    { value: 'AC', label: 'Acre' }, { value: 'AL', label: 'Alagoas' },
    { value: 'AP', label: 'Amapá' }, { value: 'AM', label: 'Amazonas' },
    { value: 'BA', label: 'Bahia' }, { value: 'CE', label: 'Ceará' },
    { value: 'DF', label: 'Distrito Federal' },
    { value: 'ES', label: 'Espírito Santo' }, { value: 'GO', label: 'Goiás' },
    { value: 'MA', label: 'Maranhão' }, { value: 'MT', label: 'Mato Grosso' },
    { value: 'MS', label: 'Mato Grosso do Sul' },
    { value: 'MG', label: 'Minas Gerais' }, { value: 'PA', label: 'Pará' },
    { value: 'PB', label: 'Paraíba' }, { value: 'PR', label: 'Paraná' },
    { value: 'PE', label: 'Pernambuco' }, { value: 'PI', label: 'Piauí' },
    { value: 'RJ', label: 'Rio de Janeiro' }, { value: 'RN', label: 'Rio Grande do Norte' },
    { value: 'RS', label: 'Rio Grande do Sul' },
    { value: 'RO', label: 'Rondônia' }, { value: 'RR', label: 'Roraima' },
    { value: 'SC', label: 'Santa Catarina' }, { value: 'SP', label: 'São Paulo' },
    { value: 'SE', label: 'Sergipe' }, { value: 'TO', label: 'Tocantins' },
]

// Busca endereço via CEP e popula o form
async function fetchViaCEP(cep: string, form: typeof formPF) {
    const clean = cep.replace(/\D/g, '')
    if (clean.length !== 8) return
    try {
        const { data } = await axios.get(`https://viacep.com.br/ws/${clean}/json/`)
        if (!data.erro) {
            form.address = data.logradouro || ''
            form.neighborhood = data.bairro || ''
            form.city = data.localidade || ''
            form.state = data.uf || ''
            form.complement = data.complemento || ''
        }
    } catch { }
}
watch(() => formPF.zip_code, val => fetchViaCEP(val, formPF))
watch(() => formPJ.zip_code, val => fetchViaCEP(val, formPJ))

// Adicionar / remover campos de array (emails, phones)
function addItem(form: typeof formPF, field: 'emails' | 'phones') {
    form[field].push('')
}
function removeItem(form: typeof formPF, field: 'emails' | 'phones', i: number) {
    if (form[field].length > 1) form[field].splice(i, 1)
    else form[field][0] = ''
}

// Submissão (PUT para update)
function submitPF() {
    formPF.put(route('contacts.update', props.contact.id))
}
function submitPJ() {
    formPJ.put(route('contacts.update', props.contact.id))
}
</script>


<template>

    <Head :title="`Editar: ${props.contact.name}`" />
    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="p-6 rounded-lg shadow-md space-y-6">
            <!-- TABS -->
            <div class="flex space-x-2">
                <Button @click="activeTab = 'physical'" :variant="activeTab === 'physical' ? 'default' : 'outline'">
                    Pessoa Física
                </Button>
                <Button @click="activeTab = 'legal'" :variant="activeTab === 'legal' ? 'default' : 'outline'">
                    Pessoa Jurídica
                </Button>
            </div>

            <!-- Formulário Pessoa Física -->
            <form v-if="activeTab === 'physical'" @submit.prevent="submitPF" class="space-y-4">
                <div class="grid gap-2">
                    <Label for="name_pf">Nome Completo</Label>
                    <Input id="name_pf" v-model="formPF.name" required />
                    <InputError :message="formPF.errors.name" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <Label for="cpf_pf">CPF</Label>
                        <Input id="cpf_pf" v-model="formPF.cpf_cnpj" v-imask="{ mask: '000.000.000-00', unmask: true }"
                            required />
                        <InputError :message="formPF.errors.cpf_cnpj" />
                    </div>
                    <div>
                        <Label for="rg">RG</Label>
                        <Input id="rg" v-model="formPF.rg" />
                        <InputError :message="formPF.errors.rg" />
                    </div>
                </div>

                <!-- Emails PF -->
                <div class="space-y-2">
                    <Label>Emails</Label>
                    <div v-for="(email, i) in formPF.emails" :key="i" class="flex items-start space-x-2">
                        <div class="flex-1">
                            <Input v-model="formPF.emails[i]" placeholder="email@exemplo.com" />
                            <InputError :message="formPF.errors[`emails.${i}`]" />
                        </div>
                        <Button type="button" @click="removeItem(formPF, 'emails', i)" variant="outline" class="mt-1">
                            Remover
                        </Button>
                    </div>
                    <Button type="button" @click="addItem(formPF, 'emails')" variant="default">
                        Adicionar Email
                    </Button>
                    <InputError :message="formPF.errors.emails" />
                </div>

                <!-- Telefones PF -->
                <div class="space-y-2">
                    <Label>Telefones</Label>
                    <div v-for="(phone, i) in formPF.phones" :key="i" class="flex items-start space-x-2">
                        <div class="flex-1">
                            <Input v-model="formPF.phones[i]" placeholder="(00) 00000-0000"
                                v-imask="{ mask: '(00) 00000-0000', unmask: true }" />
                            <InputError :message="formPF.errors[`phones.${i}`]" />
                        </div>
                        <Button type="button" @click="removeItem(formPF, 'phones', i)" variant="outline" class="mt-1">
                            Remover
                        </Button>
                    </div>
                    <Button type="button" @click="addItem(formPF, 'phones')" variant="default">
                        Adicionar Telefone
                    </Button>
                    <InputError :message="formPF.errors.phones" />
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <!-- Gênero -->
                    <div>
                        <Label for="gender">Gênero</Label>
                        <Select v-model="formPF.gender" id="gender">
                            <SelectTrigger>
                                <SelectValue placeholder="Selecione" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectLabel>Gêneros</SelectLabel>
                                    <SelectItem v-for="o in genderOptions" :key="o.value" :value="o.value">{{ o.label }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <InputError :message="formPF.errors.gender" />
                    </div>
                    <!-- Nacionalidade -->
                    <div>
                        <Label for="nationality">Nacionalidade</Label>
                        <Select v-model="formPF.nationality" id="nationality">
                            <SelectTrigger>
                                <SelectValue placeholder="Selecione" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectLabel>País</SelectLabel>
                                    <SelectItem v-for="o in nationalityOptions" :key="o.code" :value="o.code">{{ o.name
                                        }}</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <InputError :message="formPF.errors.nationality" />
                    </div>
                    <!-- Estado Civil -->
                    <div>
                        <Label for="marital_status">Estado Civil</Label>
                        <Select v-model="formPF.marital_status" id="marital_status">
                            <SelectTrigger>
                                <SelectValue placeholder="Selecione" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectLabel>Estado Civil</SelectLabel>
                                    <SelectItem v-for="o in maritalOptions" :key="o.value" :value="o.value">{{ o.label
                                        }}</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <InputError :message="formPF.errors.marital_status" />
                    </div>
                </div>

                <div class="grid grid-cols-4 gap-4">
                    <div>
                        <Label for="profession">Profissão</Label>
                        <Input id="profession" v-model="formPF.profession" />
                        <InputError :message="formPF.errors.profession" />
                    </div>
                    <div>
                        <Label for="zip_pf">CEP</Label>
                        <Input id="zip_pf" v-model="formPF.zip_code" v-imask="{ mask: '00000-000', unmask: true }" />
                        <InputError :message="formPF.errors.zip_code" />
                    </div>
                    <div class="col-span-2">
                        <Label for="address_pf">Endereço</Label>
                        <Input id="address_pf" v-model="formPF.address" readonly />
                        <InputError :message="formPF.errors.address" />
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <Label for="neighborhood_pf">Bairro</Label>
                        <Input id="neighborhood_pf" v-model="formPF.neighborhood" readonly />
                    </div>
                    <div>
                        <Label for="city_pf">Cidade</Label>
                        <Input id="city_pf" v-model="formPF.city" readonly />
                    </div>
                    <div>
                        <Label for="state_pf">Estado</Label>
                        <Input id="state_pf" v-model="formPF.state" readonly />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <Label for="number_pf">Número</Label>
                        <Input id="number_pf" v-model="formPF.number" />
                    </div>
                    <div>
                        <Label for="complement_pf">Complemento</Label>
                        <Input id="complement_pf" v-model="formPF.complement" />
                    </div>
                </div>

                <Button type="submit" :disabled="formPF.processing" class="w-full">
                    Atualizar Pessoa Física
                </Button>
            </form>

            <!-- Formulário Pessoa Jurídica -->
            <form v-else @submit.prevent="submitPJ" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <Label for="name">Nome Fantasia</Label>
                        <Input id="name" v-model="formPJ.name" required />
                        <InputError :message="formPJ.errors.name" />
                    </div>
                    <div>
                        <Label for="business_name">Razão Social</Label>
                        <Input id="business_name" v-model="formPJ.business_name" required />
                        <InputError :message="formPJ.errors.business_name" />
                    </div>
                </div>

                <!-- Emails PJ -->
                <div class="space-y-2">
                    <Label>Emails</Label>
                    <div v-for="(email, i) in formPJ.emails" :key="i" class="flex items-start space-x-2">
                        <div class="flex-1">
                            <Input v-model="formPJ.emails[i]" placeholder="email@exemplo.com" />
                            <InputError :message="formPJ.errors[`emails.${i}`]" />
                        </div>
                        <Button type="button" @click="removeItem(formPJ, 'emails', i)" variant="outline" class="mt-1">
                            Remover
                        </Button>
                    </div>
                    <Button type="button" @click="addItem(formPJ, 'emails')" variant="default">
                        Adicionar Email
                    </Button>
                    <InputError :message="formPJ.errors.emails" />
                </div>

                <!-- Telefones PJ -->
                <div class="space-y-2">
                    <Label>Telefones</Label>
                    <div v-for="(phone, i) in formPJ.phones" :key="i" class="flex items-start space-x-2">
                        <div class="flex-1">
                            <Input v-model="formPJ.phones[i]" placeholder="(00) 00000-0000"
                                v-imask="{ mask: '(00) 00000-0000', unmask: true }" />
                            <InputError :message="formPJ.errors[`phones.${i}`]" />
                        </div>
                        <Button type="button" @click="removeItem(formPJ, 'phones', i)" variant="outline" class="mt-1">
                            Remover
                        </Button>
                    </div>
                    <Button type="button" @click="addItem(formPJ, 'phones')" variant="default">
                        Adicionar Telefone
                    </Button>
                    <InputError :message="formPJ.errors.phones" />
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <Label for="cnpj">CNPJ</Label>
                        <Input id="cnpj" v-model="formPJ.cpf_cnpj"
                            v-imask="{ mask: '00.000.000/0000-00', unmask: true }" required />
                        <InputError :message="formPJ.errors.cpf_cnpj" />
                    </div>
                    <div>
                        <Label for="business_activity">Atividade</Label>
                        <Input id="business_activity" v-model="formPJ.business_activity" />
                        <InputError :message="formPJ.errors.business_activity" />
                    </div>
                    <div>
                        <Label for="administrator_id_pj">Administrador</Label>
                        <Select v-model="formPJ.administrator_id" id="administrator_id_pj">
                            <SelectTrigger>
                                <SelectValue placeholder="Selecione" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectLabel>Contatos</SelectLabel>
                                    <SelectItem v-for="o in adminOptions" :key="o.value" :value="o.value">{{ o.label }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <InputError :message="formPJ.errors.administrator_id" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <Label for="tax_state">Estado Tributário</Label>
                        <Select v-model="formPJ.tax_state" id="tax_state">
                            <SelectTrigger>
                                <SelectValue placeholder="Selecione" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectLabel>Estados</SelectLabel>
                                    <SelectItem v-for="o in stateOptions" :key="o.value" :value="o.value">{{ o.label }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <InputError :message="formPJ.errors.tax_state" />
                    </div>
                    <div>
                        <Label for="tax_city">Município Tributário</Label>
                        <Input id="tax_city" v-model="formPJ.tax_city" />
                        <InputError :message="formPJ.errors.tax_city" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <Label for="zip_pj">CEP</Label>
                        <Input id="zip_pj" v-model="formPJ.zip_code" v-imask="{ mask: '00000-000', unmask: true }" />
                        <InputError :message="formPJ.errors.zip_code" />
                    </div>
                    <div>
                        <Label for="address_pj">Endereço</Label>
                        <Input id="address_pj" v-model="formPJ.address" readonly />
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <Label for="neighborhood_pj">Bairro</Label>
                        <Input id="neighborhood_pj" v-model="formPJ.neighborhood" readonly />
                    </div>
                    <div>
                        <Label for="city_pj">Cidade</Label>
                        <Input id="city_pj" v-model="formPJ.city" readonly />
                    </div>
                    <div>
                        <Label for="state_pj">Estado</Label>
                        <Input id="state_pj" v-model="formPJ.state" readonly />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <Label for="number_pj">Número</Label>
                        <Input id="number_pj" v-model="formPJ.number" />
                    </div>
                    <div>
                        <Label for="complement_pj">Complemento</Label>
                        <Input id="complement_pj" v-model="formPJ.complement" />
                    </div>
                </div>

                <Button type="submit" :disabled="formPJ.processing" class="w-full">
                    Atualizar Pessoa Jurídica
                </Button>
            </form>
        </div>
    </AppLayout>
</template>
