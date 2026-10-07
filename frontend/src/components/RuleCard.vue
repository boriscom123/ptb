<script setup>
import { computed, ref, watch } from 'vue'
import ToggleSwitch from './ToggleSwitch.vue'

const props = defineProps({
  rule: { type: Object, required: true },
  saving: Boolean,
})
const emit = defineEmits(['save'])

// Списки редактируются как текст «по одному в строке»
function toForm(rule) {
  const form = { enabled: rule.enabled }
  for (const field of rule.fields) {
    const value = rule.settings[field.name]
    form[field.name] = field.type === 'list' ? value.join('\n') : value
  }
  return form
}

const form = ref(toForm(props.rule))
watch(() => props.rule, (rule) => (form.value = toForm(rule)))

const changed = computed(() => JSON.stringify(form.value) !== JSON.stringify(toForm(props.rule)))

function save() {
  const settings = {}
  for (const field of props.rule.fields) {
    const value = form.value[field.name]
    settings[field.name] =
      field.type === 'list'
        ? value
            .split('\n')
            .map((line) => line.trim())
            .filter(Boolean)
        : field.type === 'number'
          ? Number(value)
          : value
  }
  emit('save', { enabled: form.value.enabled, settings })
}
</script>

<template>
  <section class="list rule">
    <div class="list-row">
      <div>
        <div class="rule__name">{{ $t(`rules.${rule.key}.name`) }}</div>
        <div class="rule__description hint">{{ $t(`rules.${rule.key}.description`) }}</div>
      </div>
      <ToggleSwitch v-model="form.enabled" />
    </div>

    <div v-for="field in rule.fields" :key="field.name" class="rule__field">
      <label class="rule__label hint">{{ $t(`fields.${field.name}`) }}</label>

      <textarea v-if="field.type === 'list'" v-model="form[field.name]" class="input rule__textarea" rows="4" />

      <div v-else-if="field.type === 'select'" class="chips">
        <button
          v-for="option in field.options"
          :key="option"
          type="button"
          class="chip"
          :class="{ 'chip--active': form[field.name] === option }"
          @click="form[field.name] = option"
        >
          {{ $t(`actions.${option}`) }}
        </button>
      </div>

      <input
        v-else-if="field.type === 'number'"
        v-model.number="form[field.name]"
        class="input"
        type="number"
        inputmode="numeric"
        :min="field.min"
        :max="field.max"
        :disabled="field.name === 'mute_minutes' && form.action !== 'mute'"
      />
    </div>

    <div class="rule__actions">
      <button class="button" :disabled="!changed || saving" @click="save">{{ $t('chat.save') }}</button>
    </div>
  </section>
</template>

<style scoped>
.rule {
  margin-bottom: 12px;
}

.rule__name {
  font-weight: 600;
}

.rule__description {
  margin-top: 2px;
  font-size: 13px;
}

.rule__field {
  padding: 0 16px 12px;
}

.rule__label {
  display: block;
  margin-bottom: 6px;
  font-size: 13px;
}

.rule .input,
.rule .chip {
  background: var(--secondary-bg);
}

.rule .chip--active {
  background: var(--button);
}

.rule__textarea {
  resize: vertical;
  font-family: inherit;
}

.rule .chips {
  margin: 0;
  flex-wrap: wrap;
}

.rule__actions {
  padding: 0 16px 16px;
}

.rule__actions .button {
  margin-top: 0;
}
</style>
