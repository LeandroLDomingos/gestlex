<template>
    <Head title="Início" />
    <div class="min-h-screen flex flex-col items-center justify-center bg-[#D16002] p-6">
        <h1 class="text-5xl font-bold text-white mb-4 animate-fadeInDown">GestLex</h1>
        <p class="text-xl text-white mb-8 animate-fadeInDown animation-delay-200">Gestão Jurídica Simplificada</p>

        <a href="/contacts">
            <button
                class="mb-12 bg-white text-[#D16002] font-bold py-2 px-6 rounded-full shadow-lg hover:bg-gray-100 transition duration-300 animate-fadeInDown animation-delay-400">
                Entrar no sistema
            </button>
        </a>

        <transition-group name="feature" tag="div" class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-6" appear>
            <div v-for="(feature, index) in features" :key="feature.title" class="bg-white rounded-2xl p-6 shadow-lg"
                :class="['feature-enter-active']" :style="{ transitionDelay: `${index * 150}ms` }">
                <div class="text-3xl mb-4">{{ feature.icon }}</div>
                <h2 class="text-xl font-semibold mb-2 text-black">{{ feature.title }}</h2>
                <p class="text-gray-600">{{ feature.description }}</p>
            </div>
        </transition-group>
    </div>
</template>

<script setup lang="ts">
import { Head } from '@inertiajs/vue3';
import { ref } from 'vue';

interface Feature {
    icon: string;
    title: string;
    description: string;
}

const features = ref<Feature[]>([
    { icon: '👤', title: 'Clientes', description: 'Gerencie seus clientes de forma eficiente.' },
    { icon: '⚖️', title: 'Processos', description: 'Acompanhe processos e prazos.' },
    { icon: '🗓️', title: 'Agenda', description: 'Organize compromissos e audiências.' },
    { icon: '💼', title: 'Financeiro', description: 'Controle honorários e pagamentos.' },
]);
</script>

<style scoped>
.feature-enter-from {
    opacity: 0;
    transform: translateY(20px);
}

.feature-enter-active {
    transition: opacity 0.5s ease-out, transform 0.5s ease-out;
}

.feature-enter-to {
    opacity: 1;
    transform: translateY(0);
}

@keyframes fadeInDown {
    from {
        opacity: 0;
        transform: translateY(-20px);
    }

    to {
        opacity: 1;
        transform: translateY(0);
    }
}

.animate-fadeInDown {
    animation: fadeInDown 0.8s ease-out forwards;
}

.animation-delay-200 {
    animation-delay: 0.2s;
}

.animation-delay-400 {
    animation-delay: 0.4s;
}
</style>