import { createRouter, createWebHistory } from 'vue-router'
import ChatEventsView from './views/ChatEventsView.vue'
import ChatsView from './views/ChatsView.vue'
import ChatView from './views/ChatView.vue'
import UsersView from './views/UsersView.vue'
import UserView from './views/UserView.vue'

const idProp = (route) => ({ id: Number(route.params.id) })

export const router = createRouter({
  history: createWebHistory(),
  routes: [
    { path: '/', redirect: '/users' },
    { path: '/users', name: 'users', component: UsersView, meta: { tab: true } },
    { path: '/users/:id', name: 'user', component: UserView, props: idProp },
    { path: '/chats', name: 'chats', component: ChatsView, meta: { tab: true } },
    { path: '/chats/:id', name: 'chat', component: ChatView, props: idProp },
    { path: '/chats/:id/events', name: 'chat-events', component: ChatEventsView, props: idProp },
    { path: '/:pathMatch(.*)*', redirect: '/users' },
  ],
})
