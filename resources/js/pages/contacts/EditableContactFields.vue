<script setup lang="ts">
import { PropType } from 'vue'
import Input from '@/components/ui/input/Input.vue'
import Button from '@/components/ui/button/Button.vue'
import Label from '@/components/ui/label/Label.vue'
import InputError from '@/components/InputError.vue'
// Ensure v-imask is globally available or imported if needed locally by Input component
// For example, if Input itself doesn't handle v-imask:
// import { vIMask } from '@imask/vue';

const props = defineProps({
  emails: {
    type: Array as PropType<string[]>,
    required: true,
  },
  phones: {
    type: Array as PropType<string[]>,
    required: true,
  },
  formErrors: {
    type: Object as PropType<Record<string, string>>,
    required: true,
  },
  // Field names are used to construct keys for error messages, e.g., 'emails.0', 'phones.1'
  emailFieldName: {
    type: String,
    required: true,
    default: 'emails'
  },
  phoneFieldName: {
    type: String,
    required: true,
    default: 'phones'
  },
  // Unique identifier for keys if multiple instances are used in complex forms,
  // though with v-if/v-else for PF/PJ, direct conflicts are less likely for simple keys.
  formIdentifier: {
    type: String,
    required: true, // e.g., 'pf' or 'pj'
  }
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
  <div class="space-y-6">
    <div class="space-y-2">
      <Label :for="`${formIdentifier}-${emailFieldName}-0`">Emails</Label>
      <div
        v-for="(email, idx) in emails"
        :key="`${formIdentifier}-${emailFieldName}-${idx}`"
        class="flex items-start space-x-2"
      >
        <div class="flex-grow">
          <Input
            :id="`${formIdentifier}-${emailFieldName}-${idx}`"
            :modelValue="email"
            @update:modelValue="updateEmail(idx, $event)"
            type="email"
            placeholder="email@exemplo.com"
            class="w-full"
          />
          <InputError :message="formErrors[`${emailFieldName}.${idx}`] || formErrors[emailFieldName]" />
        </div>
        <Button
          type="button"
          @click="$emit('removeEmail', idx)"
          variant="outline"
          size="sm"
          class="mt-1 shrink-0"
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
      <InputError v-if="!emails.length" :message="formErrors[emailFieldName]" />
    </div>

    <div class="space-y-2">
      <Label :for="`${formIdentifier}-${phoneFieldName}-0`">Telefones</Label>
      <div
        v-for="(phone, idx) in phones"
        :key="`${formIdentifier}-${phoneFieldName}-${idx}`"
        class="flex items-start space-x-2"
      >
        <div class="flex-grow">
          <Input
            :id="`${formIdentifier}-${phoneFieldName}-${idx}`"
            :modelValue="phone"
            @update:modelValue="updatePhone(idx, $event)"
            placeholder="(00) 00000-0000"
            v-imask="{ mask: '(00) [0]0000-0000', unmask: true, lazy: false }"
            class="w-full"
          />
          <InputError :message="formErrors[`${phoneFieldName}.${idx}`] || formErrors[phoneFieldName]" />
        </div>
        <Button
          type="button"
          @click="$emit('removePhone', idx)"
          variant="outline"
          size="sm"
          class="mt-1 shrink-0"
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
      <InputError v-if="!phones.length" :message="formErrors[phoneFieldName]" />
    </div>
  </div>
</template>
