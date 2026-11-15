import { defineConfig } from 'vitepress'

// The docs are served from the domain root. Set DOCS_BASE to serve them from a subfolder.
const base = process.env.DOCS_BASE ?? '/'

export default defineConfig({
  title: 'Delivery Slots',
  description: 'Delivery and pickup time slots for Craft Commerce',
  base,
  cleanUrls: true,

  head: [
    ['link', { rel: 'icon', type: 'image/svg+xml', href: `${base}favicon.svg` }],
    ['link', { rel: 'icon', type: 'image/png', sizes: '64x64', href: `${base}favicon.png` }],
    ['link', { rel: 'apple-touch-icon', sizes: '180x180', href: `${base}apple-touch-icon.png` }],
    ['meta', { name: 'theme-color', content: '#1f5f3f' }],
    ['link', { rel: 'preconnect', href: 'https://api.fontshare.com' }],
    ['link', { rel: 'preconnect', href: 'https://cdn.fontshare.com', crossorigin: '' }],
    ['link', { rel: 'stylesheet', href: 'https://api.fontshare.com/v2/css?f[]=clash-grotesk@400,500&display=swap' }],
  ],

  themeConfig: {
    logo: { src: '/logo.svg', width: 24, height: 24 },
    siteTitle: 'Delivery Slots',
    nav: [
      { text: 'Plugin Store', link: 'https://plugins.craftcms.com/delivery-slots', target: '_blank' },
    ],

    sidebar: [
      {
        text: 'Getting Started',
        items: [
          { text: 'Introduction', link: '/getting-started/introduction' },
          { text: 'Installation', link: '/getting-started/installation' },
          { text: 'Setting Up a Schedule', link: '/getting-started/schedules' },
          { text: 'Configuration', link: '/getting-started/configuration' },
          { text: 'Troubleshooting', link: '/getting-started/troubleshooting' },
          { text: 'Privacy and Data', link: '/getting-started/privacy' },
        ],
      },
      {
        text: 'Guides',
        items: [
          { text: 'The Slot Picker', link: '/guides/slot-picker' },
          { text: 'Holds and Payment', link: '/guides/holds' },
          { text: 'Delivery Zones', link: '/guides/zones' },
          { text: 'Run Sheet and Exports', link: '/guides/run-sheet' },
          { text: 'Orders and Emails', link: '/guides/orders' },
          { text: 'JavaScript Checkouts', link: '/guides/javascript' },
          { text: 'Template Reference', link: '/guides/reference' },
        ],
      },
      {
        text: 'Examples',
        items: [
          { text: 'Grocery Delivery Checkout', link: '/examples/grocery-delivery' },
          { text: 'Click and Collect', link: '/examples/click-and-collect' },
          { text: 'Zone Days and Fees', link: '/examples/zone-fees' },
          { text: 'Order Confirmation Email', link: '/examples/order-email' },
          { text: 'Slot Picker in JavaScript', link: '/examples/javascript-picker' },
        ],
      },
    ],

    footer: {
      message: 'A Craft Commerce plugin by <a href="https://suprim.com.au" target="_blank">Suprim</a>',
    },

    search: {
      provider: 'local',
    },
  },
})
