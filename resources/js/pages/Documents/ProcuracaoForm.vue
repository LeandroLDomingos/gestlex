<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { reactive } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Button } from '@/components/ui/button';
import InputError from '@/components/InputError.vue';

const props = defineProps({
    process: {
        type: Object,
        required: true,
    },
    qualificacaoOutorgante: {
        type: String,
        required: true,
    }
});

const form = reactive({
    outorgante_qualificacao: props.qualificacaoOutorgante,
    poderes: 'Pelo presente Instrumento de Mandato, nomeio e constituo minha bastante procuradora Fernanda Lóren Ferreira Santos, brasileira, casada, Advogada regularmente inscrita na Ordem dos Advogados do Brasil sob o nº 187.526 (151ª Subseção – Seção Minas Gerais), estabelecida profissionalmente na Rua Coronel Durães, nº 170, Sala 09, Bela Vista, Lagoa Santa/MG, CEP 33.239-206, com poderes para o Foro em Geral, para defender meus interesses perante repartições públicas Federais, Estaduais e Municipais, órgãos da administração pública direta e indireta, qualquer Juízo ou Tribunal do país, em qualquer Instância, em que eu for autor, réu, assistente, oponente, reclamante, reclamado, litisconsorte ou chamado à autoria podendo o dito procurador, para o bom e fiel desempenho deste Mandato receber crédito, desistir, transigir, receber citação inicial, reconhecer a procedência do pedido, confessar, firmar termos, acordos, estabelecer ritos de arrolamentos, impugnar créditos, oferecer lances e arrematar, habilitar, recorrer, prestar compromisso de inventariante, levantar ou receber RPV e ALVARÁS, pedir a justiça gratuita e assinar declaração de hipossuficiência econômica, em conformidade com a norma do art. 105 da Lei 13.105/2015, podendo ainda substabelecer com ou sem reservas de iguais poderes, do qual dou tudo por bom, firme e valioso, especificamente para a presente.	',
});

const csrfToken = usePage().props.csrf_token;
</script>

<template>
    <Head title="Gerar Procuração" />

    <AppLayout>
        <div class="container px-4 sm:px-6 lg:px-8 py-8">
            <h1 class="text-2xl font-bold mb-6">
                Gerar Procuração - Processo: {{ process.title }}
            </h1>

            <form :action="route('processes.documents.generate.procuracao', process.id)" method="POST" target="_blank">
                <input type="hidden" name="_token" :value="csrfToken">

                <Card>
                    <CardHeader>
                        <CardTitle>Preencha os Dados da Procuração</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        
                        <div class="space-y-2">
                            <Label for="outorgante_qualificacao">Outorgante</Label>
                            <Textarea id="outorgante_qualificacao" v-model="form.outorgante_qualificacao" name="outorgante_qualificacao" rows="4" />
                        </div>
                        
                        <div class="space-y-2">
                            <Label for="poderes">Poderes</Label>
                            <Textarea id="poderes" v-model="form.poderes" name="poderes" rows="8" />
                        </div>

                        <div class="flex justify-end">
                            <Button type="submit">
                                Gerar PDF da Procuração
                            </Button>
                        </div>
                    </CardContent>
                </Card>
            </form>
        </div>
    </AppLayout>
</template>
