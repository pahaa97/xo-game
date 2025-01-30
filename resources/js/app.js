import './bootstrap.js';
import { createApp } from 'vue';
import App from './App.vue';
import Game from './components/Game.vue';
import VueCookies from 'vue-cookies';

const app = createApp(App);

app.component('Game', Game);
app.use(VueCookies, {
    expires: '30d',
    path: '/',
    domain: window.location.hostname
})

app.mount('#app');
