<script setup lang="ts">
import { ref, watch, onMounted } from 'vue'
import { usePage } from '@inertiajs/vue3'
import { Button } from '@/components/ui/button' // Seu componente de botão
import { useToast } from '@/components/ui/toast/use-toast'
import type { ToastProps } from '@/components/ui/toast' // Ajuste este tipo conforme a sua implementação de toast

interface FlashMessages {
  success?: string;
  error?: string;
  info?: string;
  warning?: string;
  [key: string]: string | undefined; // Permite outras chaves de string
}

const { toast } = useToast()
const page = usePage()

// Função auxiliar para processar e exibir toasts de mensagens flash
function displayFlashMessage(type: keyof FlashMessages, message: string | undefined) {
  if (message && typeof message === 'string') {
    let title = 'Notificação';
    let variant: ToastProps['variant'] = 'default'; // Variante padrão

    switch (type) {
      case 'success':
        title = 'Sucesso!';
        variant = 'default'; // Ou 'success' se você tiver essa variante em seu ToastProps
        break;
      case 'error':
        title = 'Erro!';
        variant = 'destructive'; // Ou 'error' se você tiver essa variante
        break;
      case 'info':
        title = 'Informação';
        variant = 'default'; // Ou 'info' se você tiver essa variante
        break;
      case 'warning':
        title = 'Atenção!';
        // Se você não tiver uma variante 'warning', pode usar 'default'
        // ou criar uma estilização customizada para o toast de aviso.
        variant = 'default'; // Ou 'warning' se você tiver essa variante
        break;
    }

    toast({
      title: title,
      description: message,
      variant: variant,
    });

    // Opcional: Limpar a mensagem flash específica no frontend para evitar reexibição.
    // Isso é mais robusto se feito com cuidado para não interferir com o Inertia.
    // if (page.props.flash && (page.props.flash as FlashMessages)[type]) {
    //   (page.props.flash as FlashMessages)[type] = undefined;
    // }
  }
}

// Observadores para diferentes tipos de mensagens flash
const flashMessageTypes: Array<keyof FlashMessages> = ['success', 'error', 'info', 'warning'];

flashMessageTypes.forEach(type => {
  watch(
    // Adicionada verificação para page.props.flash
    () => (page.props.flash ? (page.props.flash as FlashMessages)[type] : undefined),
    (newMessage) => {
      // A verificação de 'newMessage' já existe em displayFlashMessage
      displayFlashMessage(type, newMessage as string | undefined);
    },
    {
      // deep: true, // Geralmente não é necessário para strings, mas seguro se o flash for complexo.
      // Se page.props.flash for substituído por um novo objeto, o watch raso deve ser suficiente.
    }
  );
});

// Exemplo de como você poderia usar isso em um botão para simular
function showSampleToast(variant: ToastProps['variant'] = 'default', title: string, description: string) {
  toast({
    title: title,
    description: description,
    variant: variant
  });
}

// Verificar mensagens flash uma vez quando o componente é montado
onMounted(() => {
  // Adicionada verificação para page.props.flash
  if (page.props.flash) {
    const flash = page.props.flash as FlashMessages;
    flashMessageTypes.forEach(type => {
      // A verificação de 'flash[type]' já existe em displayFlashMessage
      displayFlashMessage(type, flash[type]);

      // Limpar a mensagem flash após a exibição inicial para evitar que o watcher
      // a dispare novamente se a mensagem não for limpa pelo Inertia/backend
      // na próxima navegação. Isso é uma medida de segurança.
      // if (flash[type]) {
      //    (page.props.flash as FlashMessages)[type] = undefined;
      // }
    });
  }
})

</script>

<template>
  <div v-if="false" class="p-4 space-y-2 hidden">
    <h2 class="text-lg font-semibold">Demonstração de Toasts (Lógica Ativa)</h2>
    <div class="flex flex-wrap gap-2">
      <Button
        variant="outline"
        @click="showSampleToast('default', 'Toast de Sucesso (Exemplo)', 'Sua mensagem de sucesso de exemplo.')"
      >
        Toast Sucesso
      </Button>
      <Button
        variant="destructive"
        @click="showSampleToast('destructive', 'Toast de Erro (Exemplo)', 'Sua mensagem de erro de exemplo.')"
      >
        Toast Erro
      </Button>
    </div>
    <p class="text-sm text-gray-600 mt-4">
      Toasts de feedback do backend serão exibidos automaticamente.
    </p>
  </div>
</template>
