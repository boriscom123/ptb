<template>
  <nav class="tabs">
    <RouterLink :to="{ name: 'users' }" class="tab">{{ $t('nav.users') }}</RouterLink>
    <RouterLink :to="{ name: 'chats' }" class="tab">{{ $t('nav.chats') }}</RouterLink>
  </nav>
</template>

<style scoped>
.tabs {
  display: flex;
  gap: 4px;
  max-width: 640px;
  margin: 12px auto 0;
  padding: 3px;
  border-radius: 10px;
  background: var(--section-bg);
  width: calc(100% - 32px);
}

.tab {
  flex: 1;
  padding: 7px;
  border-radius: 8px;
  color: var(--text);
  font-size: 14px;
  font-weight: 500;
  text-align: center;
  text-decoration: none;
}

.tab.router-link-active {
  background: var(--button);
  color: var(--button-text);
}
</style>
