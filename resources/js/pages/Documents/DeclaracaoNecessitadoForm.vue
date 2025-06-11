<script setup>
import { Head, usePage } from '@inertiajs/vue3';
import { reactive } from 'vue';
import AppLayout from '@/layouts/AppLayout.vue';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { Textarea } from '@/components/ui/textarea';
import { Button } from '@/components/ui/button';

const props = defineProps({
    process: {
        type: Object,
        required: true,
    },
    // Propriedade para receber o texto pré-preenchido do controller
    textoDeclaracao: {
        type: String,
        required: true,
    },
});

const form = reactive({
    texto_declaracao: props.textoDeclaracao,
});

const csrfToken = usePage().props.csrf_token;
</script>

<template>
    <Head title="Gerar Declaração de Hipossuficiência" />

    <AppLayout :breadcrumbs="breadcrumbs">
        <div class="container px-4 sm:px-6 lg:px-8 py-8">
            <h1 class="text-2xl font-bold mb-6">
                Gerar Declaração de Hipossuficiência - Processo: {{ process.title }}
            </h1>

            <form :action="route('processes.documents.generate.declaracao', process.id)" method="POST" target="_blank">
                <input type="hidden" name="_token" :value="csrfToken">

                <Card>
                    <CardHeader>
                        <CardTitle>Preencha o Texto da Declaração de Hipossuficiência</CardTitle>
                    </CardHeader>
                    <CardContent class="space-y-6">
                        <div class="space-y-2">
                            <Label for="texto_declaracao">Corpo da Declaração</Label>
                            <Textarea id="texto_declaracao" v-model="form.texto_declaracao" name="texto_declaracao" rows="15" />
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
