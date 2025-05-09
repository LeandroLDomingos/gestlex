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
countries.registerLocale(pt)

const activeTab = ref<'physical' | 'legal'>('physical')

// formulário PF (agora com campos de endereço completos)
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
    admin_contact_id: '',
})

// formulário PJ (idem)
const formPJ = useForm({
    type: 'legal',
    fantasy_name: '',
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
    admin_contact_id: '',
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

// Dispara a busca quando CEP muda e tiver 8 dígitos
watch(() => formPF.zip_code, val => fetchViaCEP(val, formPF))
watch(() => formPJ.zip_code, val => fetchViaCEP(val, formPJ))

function submitPF() {
    formPF.post(route('contacts.storePF'))
}
function submitPJ() {
    formPJ.post(route('contacts.storePJ'))
}
</script>


<template>

    <Head title="Criar Contato" />
    <AppLayout>
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
                    <Input id="name_pf" v-model="formPF.name" required />
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
                        <Input id="rg" v-model="formPF.rg" required />
                        <InputError :message="formPF.errors.rg" />
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
                        <Input id="marital_status" v-model="formPF.marital_status" />
                        <InputError :message="formPF.errors.marital_status" />
                    </div>
                </div>

                <!-- Profissão e contato admin -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <Label for="profession">Profissão</Label>
                        <Input id="profession" v-model="formPF.profession" />
                        <InputError :message="formPF.errors.profession" />
                    </div>
                    <div>
                        <Label for="admin_contact_id">Contato Admin</Label>
                        <Input id="admin_contact_id" v-model="formPF.admin_contact_id" />
                        <InputError :message="formPF.errors.admin_contact_id" />
                    </div>
                </div>

                <!-- Endereço: CEP, rua, bairro, cidade, estado -->
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <Label for="zip_pf">CEP</Label>
                        <Input id="zip_pf" v-model="formPF.zip_code" placeholder="00000-000"
                            v-imask="{ mask: '00000-000', unmask: true }" />
                        <InputError :message="formPF.errors.zip_code" />
                    </div>
                    <div class="col-span-2">
                        <Label for="address_pf">Rua</Label>
                        <Input id="address_pf" v-model="formPF.address" readonly />
                        <InputError :message="formPF.errors.address" />
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <Label for="neighborhood_pf">Bairro</Label>
                        <Input id="neighborhood_pf" v-model="formPF.neighborhood" readonly />
                        <InputError :message="formPF.errors.neighborhood" />
                    </div>
                    <div>
                        <Label for="city_pf">Cidade</Label>
                        <Input id="city_pf" v-model="formPF.city" readonly />
                        <InputError :message="formPF.errors.city" />
                    </div>
                    <div>
                        <Label for="state_pf">Estado</Label>
                        <Input id="state_pf" v-model="formPF.state" readonly />
                        <InputError :message="formPF.errors.state" />
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <Label for="number_pf">Número</Label>
                        <Input id="number_pf" v-model="formPF.number" />
                        <InputError :message="formPF.errors.number" />
                    </div>
                    <div>
                        <Label for="complement_pf">Complemento</Label>
                        <Input id="complement_pf" v-model="formPF.complement" />
                        <InputError :message="formPF.errors.complement" />
                    </div>
                </div>
                <Button type="submit" :disabled="formPF.processing" class="w-full">
                    Criar Pessoa Física
                </Button>
            </form>

            <!-- FORMULÁRIO PJ -->
            <form v-else @submit.prevent="submitPJ" class="space-y-4">
                <div class="grid gap-2">
                    <Label for="fantasy_name">Nome Fantasia</Label>
                    <Input id="fantasy_name" v-model="formPJ.fantasy_name" required />
                    <InputError :message="formPJ.errors.fantasy_name" />
                </div>

                <div class="grid gap-2">
                    <Label for="business_name">Razão Social</Label>
                    <Input id="business_name" v-model="formPJ.business_name" required />
                    <InputError :message="formPJ.errors.business_name" />
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <Label for="cnpj">CNPJ</Label>
                        <Input id="cnpj" v-model="formPJ.cpf_cnpj" required />
                        <InputError :message="formPJ.errors.cpf_cnpj" />
                    </div>
                    <div>
                        <Label for="business_activity">Atividade</Label>
                        <Input id="business_activity" v-model="formPJ.business_activity" />
                        <InputError :message="formPJ.errors.business_activity" />
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <Label for="tax_state">Estado (ICMS)</Label>
                        <Input id="tax_state" v-model="formPJ.tax_state" />
                        <InputError :message="formPJ.errors.tax_state" />
                    </div>
                    <div>
                        <Label for="tax_city">Município</Label>
                        <Input id="tax_city" v-model="formPJ.tax_city" />
                        <InputError :message="formPJ.errors.tax_city" />
                    </div>
                </div>

                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <Label for="zip_pj">CEP</Label>
                        <Input id="zip_pj" v-model="formPJ.zip_code" />
                        <InputError :message="formPJ.errors.zip_code" />
                    </div>
                    <div>
                        <Label for="number_pj">Número</Label>
                        <Input id="number_pj" v-model="formPJ.number" />
                        <InputError :message="formPJ.errors.number" />
                    </div>
                    <div>
                        <Label for="complement_pj">Complemento</Label>
                        <Input id="complement_pj" v-model="formPJ.complement" />
                        <InputError :message="formPJ.errors.complement" />
                    </div>
                </div>

                <div class="grid gap-2">
                    <Label for="admin_contact_id_pj">Contato Admin</Label>
                    <Input id="admin_contact_id_pj" v-model="formPJ.admin_contact_id" />
                    <InputError :message="formPJ.errors.admin_contact_id" />
                </div>

                <Button type="submit" :disabled="formPJ.processing" class="w-full">
                    Criar Pessoa Jurídica
                </Button>
            </form>

        </div>
    </AppLayout>
</template>
