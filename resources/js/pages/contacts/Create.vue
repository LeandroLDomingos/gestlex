<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue'
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
import { BreadcrumbItem, Contact } from '@/types'
countries.registerLocale(pt)

const props = defineProps<{ contacts:Contact[] }>()
const activeTab = ref<'physical' | 'legal'>('physical')

// Mapeia contatos recebidos do backend
const adminOptions = computed(() =>
    props.contacts.map(c => ({ value: c.id, label: c.name }))
)

// formulário PF com múltiplos emails e telefones
const formPF = useForm({
    type: 'physical',
    name: '',
    cpf_cnpj: '',
    rg: '',
    gender: '',
    nationality: 'BR',
    marital_status: '',
    profession: '',
    zip_code: '',
    address: '',
    neighborhood: '',
    city: '',
    state: '',
    complement: '',
    number: '',
    administrator_id: '',
    emails: [''],
    phones: [''],
})

// formulário PJ com múltiplos emails e telefones
const formPJ = useForm({
    type: 'legal',
    trade_name: '',
    business_name: '',
    cpf_cnpj: '',
    business_activity: '',
    tax_state: '',
    tax_city: '',
    zip_code: '',
    address: '',
    neighborhood: '',
    city: '',
    state: '',
    complement: '',
    number: '',
    administrator_id: '',
    emails: [''],
    phones: [''],
})

// Opções de select
const nationalityOptions = computed(() => {
    const list = countries.getNames('pt', { select: 'official' })
    return Object.entries(list)
        .map(([code, name]) => ({ code, name }))
        .sort((a, b) => a.name.localeCompare(b.name))
})
const genderOptions = computed(() => [
    { value: 'male', label: 'Masculino' },
    { value: 'female', label: 'Feminino' },
    { value: 'other', label: 'Outro' },
])

const maritalOptions = computed(() => [
    { value: 'single', label: 'Solteiro(a)' },
    { value: 'married', label: 'Casado(a)' },
    { value: 'divorced', label: 'Divorciado(a)' },
    { value: 'widowed', label: 'Viúvo(a)' },
    { value: 'separated', label: 'Separado(a)' },
])


// Estados brasileiros
const stateOptions = computed(() => [
    { value: 'AC', label: 'Acre' },
    { value: 'AL', label: 'Alagoas' },
    { value: 'AP', label: 'Amapá' },
    { value: 'AM', label: 'Amazonas' },
    { value: 'BA', label: 'Bahia' },
    { value: 'CE', label: 'Ceará' },
    { value: 'DF', label: 'Distrito Federal' },
    { value: 'ES', label: 'Espírito Santo' },
    { value: 'GO', label: 'Goiás' },
    { value: 'MA', label: 'Maranhão' },
    { value: 'MT', label: 'Mato Grosso' },
    { value: 'MS', label: 'Mato Grosso do Sul' },
    { value: 'MG', label: 'Minas Gerais' },
    { value: 'PA', label: 'Pará' },
    { value: 'PB', label: 'Paraíba' },
    { value: 'PR', label: 'Paraná' },
    { value: 'PE', label: 'Pernambuco' },
    { value: 'PI', label: 'Piauí' },
    { value: 'RJ', label: 'Rio de Janeiro' },
    { value: 'RN', label: 'Rio Grande do Norte' },
    { value: 'RS', label: 'Rio Grande do Sul' },
    { value: 'RO', label: 'Rondônia' },
    { value: 'RR', label: 'Roraima' },
    { value: 'SC', label: 'Santa Catarina' },
    { value: 'SP', label: 'São Paulo' },
    { value: 'SE', label: 'Sergipe' },
    { value: 'TO', label: 'Tocantins' },
])
// Busca no ViaCEP e popula o form
async function fetchViaCEP(cep: string, form: typeof formPF) {
    const clean = cep.replace(/\D/g, '')
    if (clean.length !== 8) return
    try {
        const { data } = await axios.get(`https://viacep.com.br/ws/${clean}/json/`)
        if (data.erro) return
        form.address = data.logradouro || ''
        form.neighborhood = data.bairro || ''
        form.city = data.localidade || ''
        form.state = data.uf || ''
        form.complement = data.complemento || ''
    } catch (err) {
        console.error('ViaCEP error', err)
    }
}

watch(() => formPF.zip_code, val => fetchViaCEP(val, formPF))
watch(() => formPJ.zip_code, val => fetchViaCEP(val, formPJ))

// métodos para adicionar/remover emails e telefones com proteção do último campo
function addEmail(form: typeof formPF) {
    form.emails.push('')
}
function removeEmail(form: typeof formPF, index: number) {
    if (form.emails.length > 1) {
        form.emails.splice(index, 1)
    } else {
        form.emails[0] = ''
    }
}
function addPhone(form: typeof formPF) {
    form.phones.push('')
}
function removePhone(form: typeof formPF, index: number) {
    if (form.phones.length > 1) {
        form.phones.splice(index, 1)
    } else {
        form.phones[0] = ''
    }
}

function submitPF() {
    formPF.post(route('contacts.store'))
}
function submitPJ() {
    formPJ.post(route('contacts.store'))
}

const breadcrumbs: BreadcrumbItem[] = [
    { title: 'Contatos', href: '/contacts' },
    { title: 'Criar Contatos', href: '/contacts/create' },
]
</script>


<template>

    <Head title="Criar Contato" />
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

            <!-- FORMULÁRIO PF -->
            <form v-if="activeTab === 'physical'" @submit.prevent="submitPF" class="space-y-4">
                <!-- Campos pessoais -->
                <div class="grid gap-2">
                    <Label for="name_pf">Nome Completo</Label>
                    <Input id="name_pf" v-model="formPF.name" required placeholder="Nome Completo" />
                    <InputError :message="formPF.errors.name" />
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <Label for="cpf_pf">CPF</Label>
                        <Input id="cpf_pf" v-model="formPF.cpf_cnpj" required placeholder="000.000.000-00"
                            v-imask="{ mask: '000.000.000-00', unmask: true }" />
                        <InputError :message="formPF.errors.cpf_cnpj" />
                    </div>
                    <div>
                        <Label for="rg">RG</Label>
                        <Input id="rg" v-model="formPF.rg" required placeholder="RG" />
                        <InputError :message="formPF.errors.rg" />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <!-- Emails -->
                    <div class="space-y-2">
                        <Label>Emails</Label>
                        <div v-for="(email, idx) in formPF.emails" :key="idx" class="flex items-center space-x-2">
                            <Input v-model="formPF.emails[idx]" placeholder="email@exemplo.com" />
                            <Button type="button" @click="removeEmail(formPF, idx)" variant="outline">Remover</Button>
                        </div>
                        <Button type="button" @click="addEmail(formPF)" variant="default">Adicionar Email</Button>
                        <InputError :message="formPF.errors.emails" />
                    </div>

                    <!-- Telefones -->
                    <div class="space-y-2">
                        <Label>Telefones</Label>
                        <div v-for="(phone, idx) in formPF.phones" :key="idx" class="flex items-center space-x-2">
                            <Input v-model="formPF.phones[idx]" placeholder="(00) 00000-0000"
                                v-imask="{ mask: '(00) 00000-0000', unmask: true }" />
                            <Button type="button" @click="removePhone(formPF, idx)" variant="outline">Remover</Button>
                        </div>
                        <Button type="button" @click="addPhone(formPF)" variant="default">Adicionar Telefone</Button>
                        <InputError :message="formPF.errors.phones" />
                    </div>
                </div>

                <!-- Seletor de gênero, nacionalidade e estado civil -->
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <Label for="gender">Gênero</Label>
                        <Select v-model="formPF.gender" id="gender" class="w-full">
                            <SelectTrigger>
                                <SelectValue placeholder="Selecione um gênero" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectLabel>Gêneros</SelectLabel>
                                    <SelectItem v-for="opt in genderOptions" :key="opt.value" :value="opt.value">
                                        {{ opt.label }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <InputError :message="formPF.errors.gender" />
                    </div>
                    <div>
                        <Label for="nationality">Nacionalidade</Label>
                        <Select v-model="formPF.nationality" id="nationality" class="w-full">
                            <SelectTrigger>
                                <SelectValue placeholder="Selecione uma nacionalidade" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectLabel>País</SelectLabel>
                                    <SelectItem v-for="opt in nationalityOptions" :key="opt.code" :value="opt.code">
                                        {{ opt.name }}
                                    </SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <InputError :message="formPF.errors.nationality" />
                    </div>
                    <div>
                        <Label for="marital_status">Estado Civil</Label>
                        <Select v-model="formPF.marital_status" id="marital_status">
                            <SelectTrigger>
                                <SelectValue placeholder="Selecione estado civil" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectLabel>Estado Civil</SelectLabel>
                                    <SelectItem v-for="opt in maritalOptions" :key="opt.value" :value="opt.value">{{
                                        opt.label }}</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <InputError :message="formPF.errors.marital_status" />
                    </div>
                </div>
                <!-- Endereço: CEP, rua, bairro, cidade, estado -->
                <div class="grid grid-cols-4 gap-4">
                    <div>
                        <Label for="profession">Profissão</Label>
                        <Input id="profession" v-model="formPF.profession" placeholder="Escreva a Profissão" />
                        <InputError :message="formPF.errors.profession" />
                    </div>
                    <div>
                        <Label for="zip_pf">CEP</Label>
                        <Input id="zip_pf" v-model="formPF.zip_code" placeholder="00000-000"
                            v-imask="{ mask: '00000-000', unmask: true }" />
                        <InputError :message="formPF.errors.zip_code" />
                    </div>
                    <div class="col-span-2">
                        <Label for="address_pf">Rua/Avenida</Label>
                        <Input id="address_pf" v-model="formPF.address" readonly placeholder="Rua/Avenida" />
                        <InputError :message="formPF.errors.address" />
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <Label for="neighborhood_pf">Bairro</Label>
                        <Input id="neighborhood_pf" v-model="formPF.neighborhood" readonly placeholder="Bairro" />
                        <InputError :message="formPF.errors.neighborhood" />
                    </div>
                    <div>
                        <Label for="city_pf">Cidade</Label>
                        <Input id="city_pf" v-model="formPF.city" readonly placeholder="Cidade" />
                        <InputError :message="formPF.errors.city" />
                    </div>
                    <div>
                        <Label for="state_pf">Estado</Label>
                        <Input id="state_pf" v-model="formPF.state" readonly placeholder="Estado" />
                        <InputError :message="formPF.errors.state" />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <Label for="number_pf">Número</Label>
                        <Input id="number_pf" v-model="formPF.number" placeholder="Número" />
                        <InputError :message="formPF.errors.number" />
                    </div>
                    <div>
                        <Label for="complement_pf">Complemento</Label>
                        <Input id="complement_pf" v-model="formPF.complement" placeholder="Complemento" />
                        <InputError :message="formPF.errors.complement" />
                    </div>
                </div>
                <Button type="submit" :disabled="formPF.processing" class="w-full">
                    Criar Pessoa Física
                </Button>
            </form>

            <!-- FORMULÁRIO PJ -->
            <form v-else @submit.prevent="submitPJ" class="space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="grid gap-2">
                        <Label for="trade_name">Nome Fantasia</Label>
                        <Input id="trade_name" v-model="formPJ.trade_name" required placeholder="Nome Fantaria" />
                        <InputError :message="formPJ.errors.trade_name" />
                    </div>
                    <div class="grid gap-2">
                        <Label for="business_name">Razão Social</Label>
                        <Input id="business_name" v-model="formPJ.business_name" required placeholder="Razão Social" />
                        <InputError :message="formPJ.errors.business_name" />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <!-- Emails -->
                    <div class="space-y-2">
                        <Label>Emails</Label>
                        <div v-for="(email, idx) in formPJ.emails" :key="idx" class="flex items-center space-x-2">
                            <Input v-model="formPJ.emails[idx]" placeholder="email@exemplo.com" />
                            <Button type="button" @click="removeEmail(formPJ, idx)" variant="outline">Remover</Button>
                        </div>
                        <Button type="button" @click="addEmail(formPJ)" variant="default">Adicionar Email</Button>
                        <InputError :message="formPJ.errors.emails" />
                    </div>

                    <!-- Telefones -->
                    <div class="space-y-2">
                        <Label>Telefones</Label>
                        <div v-for="(phone, idx) in formPJ.phones" :key="idx" class="flex items-center space-x-2">
                            <Input v-model="formPJ.phones[idx]" placeholder="(00) 00000-0000"
                                v-imask="{ mask: '(00) 00000-0000', unmask: true }" />
                            <Button type="button" @click="removePhone(formPJ, idx)" variant="outline">Remover</Button>
                        </div>
                        <Button type="button" @click="addPhone(formPJ)" variant="default">Adicionar Telefone</Button>
                        <InputError :message="formPJ.errors.phones" />
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <Label for="cnpj">CNPJ</Label>
                        <Input id="cnpj" v-model="formPJ.cpf_cnpj" required placeholder="00.000.000/0000-00"
                            v-imask="{ mask: '00.000.000/0000-00', unmask: true }" />
                        <InputError :message="formPJ.errors.cpf_cnpj" />
                    </div>
                    <div>
                        <Label for="business_activity">Atividade</Label>
                        <Input id="business_activity" v-model="formPJ.business_activity" placeholder="Atividade" />
                        <InputError :message="formPJ.errors.business_activity" />
                    </div>
                    <!-- Administrador -->
                    <div>
                        <Label for="administrator_id_pj">Administrador</Label>
                        <Select v-model="formPJ.administrator_id" id="administrator_id_pj">
                            <SelectTrigger>
                                <SelectValue placeholder="Selecione um contato" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectLabel>Contatos</SelectLabel>
                                    <SelectItem v-for="opt in adminOptions" :key="opt.value" :value="opt.value">{{
                                        opt.label }}</SelectItem>
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
                                <SelectValue placeholder="Selecione um estado" />
                            </SelectTrigger>
                            <SelectContent>
                                <SelectGroup>
                                    <SelectLabel>Estados</SelectLabel>
                                    <SelectItem v-for="opt in stateOptions" :key="opt.value" :value="opt.value">{{
                                        opt.label }}</SelectItem>
                                </SelectGroup>
                            </SelectContent>
                        </Select>
                        <InputError :message="formPJ.errors.tax_state" />
                    </div>
                    <div>
                        <Label for="tax_city">Município Tributário</Label>
                        <Input id="tax_city" v-model="formPJ.tax_city" placeholder="Escreva um município" />
                        <InputError :message="formPJ.errors.tax_city" />
                    </div>
                </div>

                <!-- Endereço: CEP, rua, bairro, cidade, estado -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <Label for="zip_pj">CEP</Label>
                        <Input id="zip_pj" v-model="formPJ.zip_code" placeholder="00000-000"
                            v-imask="{ mask: '00000-000', unmask: true }" />
                        <InputError :message="formPJ.errors.zip_code" />
                    </div>
                    <div>
                        <Label for="address_pj">Rua/Avenida</Label>
                        <Input id="address_pj" v-model="formPJ.address" readonly placeholder="Rua/Avenida" />
                        <InputError :message="formPJ.errors.address" />
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <Label for="neighborhood_pj">Bairro</Label>
                        <Input id="neighborhood_pj" v-model="formPJ.neighborhood" readonly placeholder="Bairro" />
                        <InputError :message="formPJ.errors.neighborhood" />
                    </div>
                    <div>
                        <Label for="city_pj">Cidade</Label>
                        <Input id="city_pj" v-model="formPJ.city" readonly placeholder="Cidade" />
                        <InputError :message="formPJ.errors.city" />
                    </div>
                    <div>
                        <Label for="state_pj">Estado</Label>
                        <Input id="state_pj" v-model="formPJ.state" readonly placeholder="Estado" />
                        <InputError :message="formPJ.errors.state" />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <Label for="number_pj">Número</Label>
                        <Input id="number_pj" v-model="formPJ.number" placeholder="Número" />
                        <InputError :message="formPJ.errors.number" />
                    </div>
                    <div>
                        <Label for="complement_pj">Complemento</Label>
                        <Input id="complement_pj" v-model="formPJ.complement" placeholder="Complemento" />
                        <InputError :message="formPJ.errors.complement" />
                    </div>
                </div>

                <Button type="submit" :disabled="formPJ.processing" class="w-full">
                    Criar Pessoa Jurídica
                </Button>
            </form>

        </div>
    </AppLayout>
</template>
