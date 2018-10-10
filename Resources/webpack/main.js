
import Vue from "vue";
import vueCustomElement from 'vue-custom-element';

import File from "./vue/field/file.vue";

// Use VueCustomElement
Vue.use(vueCustomElement);

if(!customElements.get('karls-media-file-field')) {
    Vue.customElement('karls-media-file-field', File);
}
