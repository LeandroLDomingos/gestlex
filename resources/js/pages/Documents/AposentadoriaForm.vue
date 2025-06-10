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
    clausulaPagamento: {
        type: String,
        required: true,
    }
});

// Usamos um objeto reativo simples para os dados do formulário,
// em vez do useForm do Inertia para esta submissão.
const form = reactive({
    clausula_1: 'A Advogada contratada compromete-se, em cumprimento ao mandato recebido a requerer administrativamente Aposentadoria e ajuste de pendências no CNIS, junto ao INSS e solicitar Certidão de Contagem de Tempo e PPP junto a Prefeitura Municipal de Lagoa.',
    clausula_2: 'O CONTRATANTE reconhece já haver recebido a orientação preventiva comportamental e jurídica para a consecução dos serviços, se compromete a fornecer à ADVOGADA CONTRATADA os documentos e meios necessários à comprovação processual do seu pretendido direito, bem como, pagará as despesas extrajudiciais que decorrerem da causa, caso haja, nada havendo adiantado para esse fim.',
    clausula_3: props.clausulaPagamento,
    paragrafo_primeiro_clausula_3: 'A respectiva quitação será dada quando da emissão do recibo.',
    clausula_4: 'Outras medidas extrajudiciais ou judiciais necessárias, incidentais ou não, diretas ou indiretas, decorrentes da causa ora contratada, devem ter novos honorários estimados com a anuência da CONTRATANTE.',
    clausula_5: 'Considerar-se-ão vencidos e imediatamente exigíveis os honorários ora contratados – como se o cliente fosse vencedor – no caso de O CONTRATANTE vir a revogar ou cassar o mandato outorgado, optar por não prosseguir com o procedimento por motivos pessoais ou a exigir o substabelecimento sem reservas, sem que a ADVOGADA CONTRATADA tenha, para isso, dado causa.',
    clausula_6: 'A atuação profissional da ADVOGADA CONTRATADA ficará restrita até grau recursal. A indicação de advogados para acompanhamento de recursos nos Tribunais Superiores, bem como para acompanhamento de eventuais cartas precatórias será da CONTRATANTE, caso este prefira os serviços de outros profissionais da sua confiança pessoal.',
    clausula_7: 'Elegem as partes o foro da Comarca de Lagoa Santa, para dirimir controvérsias que possam surgir do presente contrato.',
    texto_final: 'E por estarem assim justos e contratados, assinam o presente em duas vias de igual forma e teor, na presença de duas testemunhas, para que possa produzir todos os seus efeitos de direito.',
});

// Obtém o token CSRF das propriedades da página
const csrfToken = usePage().props.csrf_token;

</script>

<template>
    <Head title="Gerar Contrato de Aposentadoria" />

    <AppLayout>
        <div class="container px-4 sm:px-6 lg:px-8 py-8">
            <h1 class="text-2xl font-bold mb-6">
                Gerar Contrato de Aposentadoria - Processo: {{ process.title }}
            </h1>

            <!-- CORREÇÃO: O formulário agora é um formulário HTML padrão -->
            <!-- O 'action' aponta para a rota de geração do PDF -->
            <!-- O 'target="_blank"' faz com que o resultado (o PDF) abra numa nova aba -->
            <form :action="route('processes.documents.generate.aposentadoria', process.id)" method="POST" target="_blank">
                <!-- Token CSRF do Laravel é necessário para submissões POST -->
                <input type="hidden" name="_token" :value="csrfToken">

                <Card>
                    <CardHeader>
                        <CardTitle>Preencha as Cláusulas do Contrato</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <!-- Cláusula 1 -->
                        <div class="space-y-2">
                            <Label for="clausula_1">Cláusula Primeira (Objeto do Contrato)</Label>
                            <Textarea id="clausula_1" v-model="form.clausula_1" name="clausula_1" rows="4" />
                        </div>

                        <!-- Cláusula 2 -->
                        <div class="space-y-2">
                            <Label for="clausula_2">Cláusula Segunda (Obrigações)</Label>
                            <Textarea id="clausula_2" v-model="form.clausula_2" name="clausula_2" rows="4" />
                        </div>
                        
                        <!-- Cláusula 3 (Honorários) - Pré-preenchida -->
                        <div class="space-y-2">
                            <Label for="clausula_3">Cláusula Terceira (Honorários)</Label>
                            <Textarea id="clausula_3" v-model="form.clausula_3" name="clausula_3" rows="8" />
                        </div>

                        <!-- Outras cláusulas -->
                        <div class="space-y-2">
                            <Label for="paragrafo_primeiro_clausula_3">Parágrafo Primeiro (Quitação)</Label>
                            <Textarea id="paragrafo_primeiro_clausula_3" v-model="form.paragrafo_primeiro_clausula_3" name="paragrafo_primeiro_clausula_3" rows="2" />
                        </div>
                        <div class="space-y-2">
                            <Label for="clausula_4">Cláusula Quarta (Medidas Adicionais)</Label>
                            <Textarea id="clausula_4" v-model="form.clausula_4" name="clausula_4" rows="3" />
                        </div>
                        <div class="space-y-2">
                            <Label for="clausula_5">Cláusula Quinta (Revogação)</Label>
                            <Textarea id="clausula_5" v-model="form.clausula_5" name="clausula_5" rows="4" />
                        </div>
                        <div class="space-y-2">
                            <Label for="clausula_6">Cláusula Sexta (Limites)</Label>
                            <Textarea id="clausula_6" v-model="form.clausula_6" name="clausula_6" rows="4" />
                        </div>
                        <div class="space-y-2">
                            <Label for="clausula_7">Cláusula Sétima (Foro)</Label>
                            <Textarea id="clausula_7" v-model="form.clausula_7" name="clausula_7" rows="2" />
                        </div>
                        <div class="space-y-2">
                            <Label for="texto_final">Texto de Fechamento</Label>
                            <Textarea id="texto_final" v-model="form.texto_final" name="texto_final" rows="3" />
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
