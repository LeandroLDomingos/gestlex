<script setup lang="ts">
import { Head, useForm } from '@inertiajs/vue3'
import AppLayout from '@/layouts/AppLayout.vue'
import Label from '@/components/ui/label/Label.vue'
import Input from '@/components/ui/input/Input.vue'
import Button from '@/components/ui/button/Button.vue'
import { Contact } from '@/types'
import { Link } from 'lucide-vue-next'


const props = defineProps<{ contact: Contact }>()
</script>


<template>

    <Head title="Criar Contato" />
    <AppLayout>
        <div class="p-6 bg-white rounded-lg shadow space-y-6">
            <h2 class="text-2xl font-semibold">
                {{ contact.name }}
            </h2>

            <!-- Identification -->
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <Label>CPF/CNPJ</Label>
                    <Input :model-value="contact.cpf_cnpj" readonly />
                </div>
                <div v-if="contact.type === 'physical'">
                    <Label>RG</Label>
                    <Input :model-value="contact.rg" readonly/>
                </div>
            </div>

            <!-- Additional fields -->
            <div v-if="contact.type === 'physical'" class="grid grid-cols-3 gap-4">
                <div>
                    <Label>Gênero</Label>
                    <Input :model-value="contact.gender"  readonly/>
                </div>
                <div>
                    <Label>Nacionalidade</Label>
                    <Input :model-value="contact.nationality"  readonly/>
                </div>
                <div>
                    <Label>Estado Civil</Label>
                    <Input :model-value="contact.marital_status"  readonly/>
                </div>
            </div>

            <div v-else class="grid grid-cols-2 gap-4">
                <div>
                    <Label>Nome Fantasia</Label>
                    <Input :model-value="contact.trade_name"  readonly/>
                </div>
                <div>
                    <Label>Razão Social</Label>
                    <Input :model-value="contact.trade_name"  readonly/>
                </div>
            </div>

            <!-- Contact Info -->
            <div class="space-y-4">
                <div>
                    <Label>Emails</Label>
                    <ul class="list-disc list-inside">
                        <li v-for="email in contact.emails" :key="email.id">
                            {{ email.email }}
                        </li>
                    </ul>
                </div>
                <div>
                    <Label>Telefones</Label>
                    <ul class="list-disc list-inside">
                        <li v-for="phone in contact.phones" :key="phone.id">
                            {{ phone.phone }}
                        </li>
                    </ul>
                </div>
            </div>

            <div v-if="contact.admin_contact">
                <Label>Contato Administrador</Label>
                <Input :model-value="contact.admin_contact.name" readonly/>
            </div>

            <!-- Endereço: CEP, rua, bairro, cidade, estado -->
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <Label for="zip_pj">CEP</Label>
                        <Input id="zip_pj" v-model="contact.zip_code" placeholder="00000-000"
                            v-imask="{ mask: '00000-000', unmask: true }" readonly/>
                    </div>
                    <div>
                        <Label for="address_pj">Rua/Avenida</Label>
                        <Input id="address_pj" v-model="contact.address"  placeholder="Rua/Avenida" readonly/>
                    </div>
                </div>
                <div class="grid grid-cols-3 gap-4">
                    <div>
                        <Label for="neighborhood_pj">Bairro</Label>
                        <Input id="neighborhood_pj" v-model="contact.neighborhood"  placeholder="Bairro" readonly/>
                    </div>
                    <div>
                        <Label for="city_pj">Cidade</Label>
                        <Input id="city_pj" v-model="contact.city"  placeholder="Cidade" readonly/>
                    </div>
                    <div>
                        <Label for="state_pj">Estado</Label>
                        <Input id="state_pj" v-model="contact.state"  placeholder="Estado" readonly/>
                    </div>
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div>
                        <Label for="number_pj">Número</Label>
                        <Input id="number_pj" v-model="contact.number" placeholder="Número" readonly/>
                    </div>
                    <div>
                        <Label for="complement_pj">Complemento</Label>
                        <Input id="complement_pj" v-model="contact.complement" placeholder="Complemento" readonly/>
                    </div>
                </div>

            <div class="flex space-x-2">
                <Button @click="$inertia.visit(route('contacts.index'))">Voltar</Button>
                <Button @click="$inertia.visit(route('contacts.edit', contact.id))" variant="outline">Editar</Button>
            </div>
        </div>
    </AppLayout>
</template>
