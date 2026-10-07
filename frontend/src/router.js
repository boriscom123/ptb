import { createRouter, createWebHistory } from 'vue-router'
import UsersView from './views/UsersView.vue'
import UserView from './views/UserView.vue'

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', redirect: '/users' },
    { path: '/users', name: 'users', component: UsersView },
    { path: '/users/:id', name: 'user', component: UserView, props: (route) => ({ id: Number(route.params.id) }) },
    { path: '/:pathMatch(.*)*', redirect: '/users' },
  ],
})
