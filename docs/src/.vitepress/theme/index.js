import DefaultTheme from 'vitepress/theme'
import DsHome from './DsHome.vue'
import './custom.css'

export default {
  extends: DefaultTheme,
  enhanceApp({ app }) {
    app.component('DsHome', DsHome)
  },
}
