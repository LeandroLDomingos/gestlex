<script setup lang="ts">
import { PropType } from 'vue'
import Input from '@/components/ui/input/Input.vue'
import Button from '@/components/ui/button/Button.vue'
import Label from '@/components/ui/label/Label.vue' // Assuming Label is used or needed
import InputError from '@/components/InputError.vue'
// Assuming v-imask is globally registered or imported if needed here
// import { vIMask } from '@imask/vue'; // Example if not global

const props = defineProps({
  // v-model for emails array
  emails: {
    type: Array as PropType<string[]>,
    required: true,
  },
  // v-model for phones array
  phones: {
    type: Array as PropType<string[]>,
    required: true,
  },
  emailErrors: {
    type: String,
    default: '',
  },
  phoneErrors: {
    type: String,
    default: '',
  },
  // Unique prefix for keys if multiple instances are used in complex scenarios,
  // though with v-if/v-else for PF/PJ, direct conflicts are less likely.
  formIdentifier: {
    type: String,
    required: true,
  },
})

const emit = defineEmits([
  'update:emails',
  'update:phones',
  'addEmail',
  'removeEmail',
  'addPhone',
  'removePhone',
])

// Helper function to update an email in the emails array
function updateEmail(index: number, value: string) {
  const newEmails = [...props.emails]
  newEmails[index] = value
  emit('update:emails', newEmails)
}

// Helper function to update a phone in the phones array
function updatePhone(index: number, value: string) {
  const newPhones = [...props.phones]
  newPhones[index] = value
  emit('update:phones', newPhones)
}
</script>

<template>
  <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
    <div class="space-y-3">
      <Label :for="`${formIdentifier}-email-0`">Emails</Label>
      <div
        v-for="(email, idx) in emails"
        :key="`${formIdentifier}-email-${idx}`"
        class="flex items-center space-x-2"
      >
        <Input
          :id="`${formIdentifier}-email-${idx}`"
          :modelValue="email"
          @update:modelValue="updateEmail(idx, $event)"
          type="email"
          placeholder="email@exemplo.com"
          class="flex-grow"
        />
        <Button
          type="button"
          @click="$emit('removeEmail', idx)"
          variant="outline"
          size="sm"
        >
          Remover
        </Button>
      </div>
      <Button
        type="button"
        @click="$emit('addEmail')"
        variant="default"
        size="sm"
      >
        Adicionar Email
      </Button>
      <InputError :message="emailErrors" />
    </div>

    <div class="space-y-3">
      <Label :for="`${formIdentifier}-phone-0`">Telefones</Label>
      <div
        v-for="(phone, idx) in phones"
        :key="`${formIdentifier}-phone-${idx}`"
        class="flex items-center space-x-2"
      >
        <Input
          :id="`${formIdentifier}-phone-${idx}`"
          :modelValue="phone"
          @update:modelValue="updatePhone(idx, $event)"
          placeholder="(00) 00000-0000"
          v-imask="{ mask: '(00) [0]0000-0000', unmask: true, lazy: false }"
          class="flex-grow"
        />
        <Button
          type="button"
          @click="$emit('removePhone', idx)"
          variant="outline"
          size="sm"
        >
          Remover
        </Button>
      </div>
      <Button
        type="button"
        @click="$emit('addPhone')"
        variant="default"
        size="sm"
      >
        Adicionar Telefone
      </Button>
      <InputError :message="phoneErrors" />
    </div>
  </div>
</template>
