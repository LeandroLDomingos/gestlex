<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { reactive } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Input } from '@/components/ui/input';
import { Textarea } from '@/components/ui/textarea';
import { Button } from '@/components/ui/button';

const props = defineProps({
    process: {
        type: Object,
        required: true,
    },
    textosPadrao: {
        type: Object,
        required: true,
    }
});

const form = reactive({
    tipo_beneficio: 'BENEFÍCIO POR INCAPACIDADE TEMPORÁRIA',
    introducao: props.textosPadrao.introducao,
    pedido_principal: props.textosPadrao.pedido_principal,
    pedido_relacao_trabalho: props.textosPadrao.pedido_relacao_trabalho,
    pedido_atestado: props.textosPadrao.pedido_atestado,
});

const csrfToken = usePage().props.csrf_token;
</script>

<template>
    <Head title="Gerar Pedido Médico" />

    <AppLayout>
        <div class="container px-4 sm:px-6 lg:px-8 py-8">
            <h1 class="text-2xl font-bold mb-6">
                Gerar Pedido Médico - Processo: {{ process.title }}
            </h1>

            <form :action="route('processes.documents.generate.pedido-medico', process.id)" method="POST" target="_blank">
                <input type="hidden" name="_token" :value="csrfToken">

                <Card>
                    <CardHeader>
                        <CardTitle>Preencha os Dados do Pedido Médico</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        
                        <div class="space-y-2">
                            <Label for="tipo_beneficio">Tipo de Benefício Solicitado</Label>
                            <Input id="tipo_beneficio" v-model="form.tipo_beneficio" name="tipo_beneficio" />
                        </div>
                        
                        <div class="space-y-2">
                            <Label for="introducao">Texto de Introdução</Label>
                            <Textarea id="introducao" v-model="form.introducao" name="introducao" rows="4" />
                            <p class="text-sm text-muted-foreground">Use {NOME_PACIENTE} e {TIPO_BENEFICIO} como placeholders.</p>
                        </div>

                        <div class="space-y-2">
                            <Label for="pedido_principal">Pedido Principal (Laudo Detalhado)</Label>
                            <Textarea id="pedido_principal" v-model="form.pedido_principal" name="pedido_principal" rows="6" />
                        </div>

                        <div class="space-y-2">
                            <Label for="pedido_relacao_trabalho">Pedido (Relação com Atividade Profissional)</Label>
                            <Textarea id="pedido_relacao_trabalho" v-model="form.pedido_relacao_trabalho" name="pedido_relacao_trabalho" rows="4" />
                        </div>

                        <div class="space-y-2">
                            <Label for="pedido_atestado">Pedido Final (Atestado Médico)</Label>
                            <Textarea id="pedido_atestado" v-model="form.pedido_atestado" name="pedido_atestado" rows="4" />
                        </div>

                        <div class="flex justify-end">
                            <Button type="submit">
                                Gerar PDF
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </form>
        </div>
    </AppLayout>
</template>