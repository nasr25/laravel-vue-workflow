<template>
  <div class="language-switcher">
    <button
      @click="toggleLanguage"
      class="lang-btn"
      :title="currentLocale === 'en' ? 'Switch to Arabic' : 'التبديل إلى الإنجليزية'"
    >
      <span class="lang-icon">🌐</span>
      <span class="lang-text">{{ currentLocale === 'en' ? 'عربي' : 'English' }}</span>
    </button>
  </div>
</template>

<script setup>
import { computed, watch } from 'vue'
import { useI18n } from 'vue-i18n'

const { locale } = useI18n()

const currentLocale = computed(() => locale.value)

const toggleLanguage = () => {
  const newLocale = locale.value === 'en' ? 'ar' : 'en'
  locale.value = newLocale
  localStorage.setItem('locale', newLocale)

  // Update HTML dir and lang attributes
  document.documentElement.setAttribute('dir', newLocale === 'ar' ? 'rtl' : 'ltr')
  document.documentElement.setAttribute('lang', newLocale)
}

// Set initial direction on mount
watch(locale, (newLocale) => {
  document.documentElement.setAttribute('dir', newLocale === 'ar' ? 'rtl' : 'ltr')
  document.documentElement.setAttribute('lang', newLocale)
}, { immediate: true })
</script>

<style scoped>
.language-switcher {
  display: inline-block;
}

.lang-btn {
  display: flex;
  align-items: center;
  gap: 8px;
  padding: 8px 16px;
  background: rgba(255, 255, 255, 0.1);
  border: 2px solid rgba(255, 255, 255, 0.3);
  border-radius: 25px;
  color: white;
  cursor: pointer;
  font-size: 14px;
  font-weight: 600;
  transition: all 0.3s ease;
  backdrop-filter: blur(10px);
}

.lang-btn:hover {
  background: rgba(255, 255, 255, 0.2);
  border-color: rgba(255, 255, 255, 0.5);
  transform: translateY(-2px);
  box-shadow: 0 4px 12px rgba(0, 0, 0, 0.15);
}

.lang-icon {
  font-size: 18px;
  line-height: 1;
}

.lang-text {
  line-height: 1;
}
</style>
