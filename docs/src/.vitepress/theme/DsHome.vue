<script setup>
import { computed, ref } from 'vue'
import { withBase } from 'vitepress'

// ---------------------------------------------------------------------------
// Hero: a working slot picker, with the same rules the plugin applies.
// ---------------------------------------------------------------------------

const WINDOWS = ['8 – 10am', '10am – 12pm', '12 – 2pm', '2 – 4pm', '4 – 6pm', '6 – 8pm']
const DAYS = [
  { dow: 'Mon', date: 16, left: [6, 2, 0, 9, 5, 1] },
  { dow: 'Tue', date: 17, left: [8, 7, 4, 9, 6, 3] },
  { dow: 'Wed', date: 18, left: [3, 0, 0, 2, 7, 5] },
  { dow: 'Thu', date: 19, left: [9, 9, 8, 7, 9, 6] },
  { dow: 'Fri', date: 20, left: [1, 0, 2, 4, 3, 0] },
  { dow: 'Sat', date: 21, left: [5, 4, null, null, null, null] },
  { dow: 'Sun', date: 22, closed: 'Closed' },
]

const day = ref(0)
const slot = ref(1)
const slots = computed(() => {
  const d = DAYS[day.value]
  return WINDOWS.map((label, i) => ({ label, left: d.left?.[i] })).filter((s) => s.left !== null && s.left !== undefined)
})
const chosen = computed(() => {
  const s = slots.value[slot.value]
  return s && s.left > 0 ? `${DAYS[day.value].dow} ${DAYS[day.value].date} Nov, ${s.label}` : null
})

function pickDay(i) {
  day.value = i
  slot.value = slots.value.findIndex((s) => s.left > 0)
}

const note = (s) => (s.left === 0 ? 'Full' : s.left <= 2 ? `${s.left} left` : 'Available')

// ---------------------------------------------------------------------------
// Content
// ---------------------------------------------------------------------------

const stores = ['Grocers', 'Florists', 'Bakeries', 'Butchers', 'Meal kits', 'Liquor stores', 'Furniture stores', 'Pharmacies', 'Click and collect']

const features = [
  { title: 'Windows per method', text: 'Two-hour delivery, one-hour pickup, shorter Saturdays. Each Commerce shipping method gets its own schedule.' },
  { title: 'Orders per slot', text: 'Slots close when they fill up. A chosen slot is held for 15 minutes while the customer pays.' },
  { title: 'Cut-offs and lead time', text: '“Order by 8pm for tomorrow”, same-day slots, a minimum notice and how many days ahead to show.' },
  { title: 'Closed days', text: 'Public holidays and stocktakes, with a label customers see in the picker.' },
  { title: 'Checked at payment', text: 'Customers can’t pay without a slot, or with one that filled up while they were checking out.' },
  { title: 'Your own markup', text: 'The picker is plain Twig and a form field. Style it to match your checkout, or use the JSON actions.' },
]

const examples = [
  { href: '/examples/grocery-delivery', img: '/examples/grocery-delivery.jpg', name: 'Grocery delivery checkout', line: 'Day tabs, two-hour slots, places left and the order summary.', alt: 'A checkout with day buttons, delivery slots and an order summary' },
  { href: '/examples/zone-fees', img: '/examples/zone-fees.jpg', name: 'Zone days and fees', line: 'The Hills on Thursdays and Saturdays, with a $4 slot fee.', alt: 'Slots for an Adelaide Hills address, with other days marked not in your area' },
  { href: '/examples/click-and-collect', img: '/examples/click-and-collect.jpg', name: 'Click and collect', line: 'Hourly pickup times, same day, ready an hour after ordering.', alt: 'Hourly pickup times from 9am to 6pm' },
]

const snippet = `{% for day in cart.deliverySlotDays %}
  <h3>{{ day.date|date('l j F') }}</h3>
  {% for slot in day.slots %}
    <label>
      <input type="radio" name="deliverySlot"
        value="{{ slot.value }}" {{ slot.isFull ? 'disabled' }}>
      {{ slot.label }}
      {% if slot.isLow %}{{ slot.left }} left{% endif %}
    </label>
  {% endfor %}
{% endfor %}`
</script>

<template>
  <div class="dh">
    <!-- Hero -->
    <section class="dh-hero">
      <div class="dh-wrap dh-hero__grid">
        <div>
          <p class="dh-eyebrow">For Craft Commerce</p>
          <h1 class="dh-h1">Let customers choose when their order arrives.</h1>
          <p class="dh-lead">Delivery and pickup time slots at checkout, with a limit on orders per slot, cut-off times, closed days, delivery zones and a run sheet for your drivers.</p>
          <div class="dh-actions">
            <a class="dh-btn dh-btn--brand" :href="withBase('/getting-started/installation')">Get started</a>
            <a class="dh-btn" :href="withBase('/examples/grocery-delivery')">See an example</a>
          </div>
        </div>

        <div class="dh-picker" aria-label="Try the slot picker">
          <div class="dh-picker__head">
            <strong>Choose a delivery time</strong>
            <span>Order by 8pm for tomorrow</span>
          </div>
          <div class="dh-days" role="group" aria-label="Day">
            <button v-for="(d, i) in DAYS" :key="d.date" type="button" class="dh-day" :aria-pressed="i === day" :disabled="!!d.closed" @click="pickDay(i)">
              <small>{{ d.dow }}</small>
              <b>{{ d.date }}</b>
              <small>{{ d.closed ?? '' }}</small>
            </button>
          </div>
          <div class="dh-slots" role="radiogroup" aria-label="Time">
            <button v-for="(s, i) in slots" :key="s.label" type="button" role="radio" class="dh-slot" :class="{ 'is-full': s.left === 0 }" :aria-checked="i === slot && s.left > 0" :disabled="s.left === 0" @click="slot = i">
              <b>{{ s.label }}</b>
              <small :class="{ low: s.left > 0 && s.left <= 2 }">{{ note(s) }}</small>
            </button>
          </div>
          <p class="dh-picker__foot" aria-live="polite">
            <template v-if="chosen">Held for you: <b>{{ chosen }}</b></template>
            <template v-else>Choose a time</template>
          </p>
        </div>
      </div>
    </section>

    <!-- Who it's for -->
    <section class="dh-band">
      <div class="dh-wrap">
        <p class="dh-band__label">For stores that deliver their own orders</p>
        <ul class="dh-chips">
          <li v-for="s in stores" :key="s">{{ s }}</li>
        </ul>
      </div>
    </section>

    <!-- Screenshot -->
    <section class="dh-wrap dh-shot">
      <img :src="withBase('/examples/grocery-delivery.jpg')" alt="A grocery checkout: delivery or pickup, day buttons, two-hour slots with one nearly full, and the order summary" width="1600" height="902" loading="lazy">
    </section>

    <!-- Features -->
    <section class="dh-wrap dh-section">
      <h2 class="dh-h2">Everything a delivery checkout needs</h2>
      <div class="dh-features">
        <div v-for="f in features" :key="f.title" class="dh-feature">
          <h3>{{ f.title }}</h3>
          <p>{{ f.text }}</p>
        </div>
      </div>
    </section>

    <!-- Run sheet -->
    <section class="dh-wrap dh-section dh-split">
      <div>
        <p class="dh-eyebrow">The run sheet</p>
        <h2 class="dh-h2">Tomorrow’s orders, by slot and suburb.</h2>
        <p class="dh-text">See how full each slot is, and every order grouped by time, zone and suburb, with phone numbers. Print a page per slot for each driver, or export a CSV for your routing app.</p>
        <a class="dh-link" :href="withBase('/guides/run-sheet')">The run sheet →</a>
      </div>
      <img :src="withBase('/images/run-sheet.jpg')" alt="The run sheet: six slots with how full they are, and eight orders grouped by time" width="1600" height="1060" loading="lazy">
    </section>

    <!-- Zones -->
    <section class="dh-wrap dh-section dh-split dh-split--flip">
      <div>
        <p class="dh-eyebrow">Delivery zones</p>
        <h2 class="dh-h2">Different days for different suburbs.</h2>
        <p class="dh-text">Draw your areas with <a href="https://plugins.craftcms.com/geomaps" target="_blank" rel="noopener">Geo Maps</a> and give each its own delivery days, order limit and fee. The Hills on Thursdays for $4 more; the city every weekday. Addresses outside every zone aren’t offered delivery.</p>
        <a class="dh-link" :href="withBase('/guides/zones')">Delivery zones →</a>
      </div>
      <img :src="withBase('/examples/zone-fees.jpg')" alt="Slots for an Adelaide Hills address: only Thursday and Saturday, each with a $4 fee" width="1600" height="902" loading="lazy">
    </section>

    <!-- Setup -->
    <section class="dh-wrap dh-section dh-split">
      <div>
        <p class="dh-eyebrow">Ten-minute setup</p>
        <h2 class="dh-h2">Your markup, one form field.</h2>
        <ol class="dh-steps">
          <li>Install from the Plugin Store.</li>
          <li>Under <b>Delivery Slots → Schedules</b>, choose a shipping method and add its time windows.</li>
          <li>Add the slots to your checkout’s shipping step. They post with the cart form as <code>deliverySlot</code>.</li>
        </ol>
        <a class="dh-link" :href="withBase('/guides/slot-picker')">The slot picker →</a>
      </div>
      <pre class="dh-code"><code>{{ snippet }}</code></pre>
    </section>

    <!-- Examples -->
    <section class="dh-wrap dh-section">
      <h2 class="dh-h2">Examples</h2>
      <div class="dh-examples">
        <a v-for="e in examples" :key="e.href" class="dh-example" :href="withBase(e.href)">
          <img :src="withBase(e.img)" :alt="e.alt" width="1600" height="902" loading="lazy">
          <b>{{ e.name }}</b>
          <span>{{ e.line }}</span>
        </a>
      </div>
    </section>

    <!-- CTA -->
    <section class="dh-cta">
      <div class="dh-wrap">
        <h2 class="dh-h2">Give customers a time, not a guess.</h2>
        <p class="dh-text">Delivery Slots works with Craft CMS 5 and Craft Commerce 5. Try it free on local and staging sites.</p>
        <div class="dh-actions dh-actions--center">
          <a class="dh-btn dh-btn--brand" :href="withBase('/getting-started/installation')">Install Delivery Slots</a>
          <a class="dh-btn" href="https://plugins.craftcms.com/delivery-slots" target="_blank" rel="noopener">Plugin Store</a>
        </div>
      </div>
    </section>
  </div>
</template>

<style scoped>
.dh {
  --ink: var(--vp-c-text-1);
  --muted: var(--vp-c-text-2);
  --line: var(--vp-c-divider);
  --card: var(--vp-c-bg);
  --soft: var(--vp-c-bg-soft);
  --green: #1f5f3f;
  --green-soft: #eaf3ed;
  --amber: #a4470b;
  --display: 'Clash Grotesk', var(--vp-font-family-base);
}
:global(.dark) .dh { --green-soft: #18261e; --amber: #f0a36b; }

.dh-wrap { max-width: 1180px; margin: 0 auto; padding: 0 24px; }

/* Hero */
.dh-hero { padding: 72px 0 56px; background: linear-gradient(180deg, var(--soft), transparent); }
.dh-hero__grid { display: grid; grid-template-columns: minmax(0, 1fr) minmax(0, 520px); gap: 56px; align-items: center; }
.dh-eyebrow { margin: 0 0 12px; font-size: 13px; font-weight: 600; letter-spacing: 0.06em; text-transform: uppercase; color: var(--vp-c-brand-1); }
.dh-h1 { margin: 0; font-family: var(--display); font-weight: 500; font-size: clamp(38px, 5vw, 60px); line-height: 1.04; letter-spacing: -0.02em; color: var(--ink); }
.dh-lead { margin: 20px 0 0; font-size: 18px; line-height: 1.6; color: var(--muted); max-width: 540px; }
.dh-actions { display: flex; flex-wrap: wrap; gap: 12px; margin-top: 28px; }
.dh-actions--center { justify-content: center; }
.dh-btn { display: inline-flex; align-items: center; min-height: 44px; padding: 0 20px; border-radius: 22px; border: 1px solid var(--line); font-weight: 600; font-size: 15px; color: var(--ink); text-decoration: none; background: var(--card); transition: border-color 0.2s, background 0.2s; }
.dh-btn:hover { border-color: var(--vp-c-brand-1); }
.dh-btn--brand { background: var(--vp-button-brand-bg); border-color: var(--vp-button-brand-bg); color: #fff; }
.dh-btn--brand:hover { background: var(--vp-button-brand-hover-bg); }

/* The picker */
.dh-picker { background: var(--card); border: 1px solid var(--line); border-radius: 16px; padding: 20px; box-shadow: 0 24px 48px -24px rgba(16, 40, 28, 0.25); }
.dh-picker__head { display: flex; justify-content: space-between; align-items: baseline; gap: 12px; margin-bottom: 14px; }
.dh-picker__head strong { font-size: 16px; color: var(--ink); }
.dh-picker__head span { font-size: 12px; color: var(--muted); }
.dh-days { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 6px; margin-bottom: 12px; }
.dh-day { height: 64px; border-radius: 10px; border: 1px solid var(--line); background: var(--card); color: var(--ink); font: inherit; cursor: pointer; padding: 0; }
.dh-day b { display: block; font-size: 17px; line-height: 1.2; }
.dh-day small { display: block; font-size: 11px; color: var(--muted); min-height: 13px; }
.dh-day[aria-pressed="true"] { background: var(--green); border-color: var(--green); color: #fff; }
.dh-day[aria-pressed="true"] small { color: #cfe4d7; }
.dh-day:disabled { background: var(--soft); color: var(--muted); cursor: not-allowed; }
.dh-slots { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; }
.dh-slot { text-align: left; padding: 11px 12px; border-radius: 10px; border: 1px solid var(--line); background: var(--card); color: var(--ink); font: inherit; cursor: pointer; }
.dh-slot b { display: block; font-size: 14px; }
.dh-slot small { font-size: 12px; color: var(--muted); }
.dh-slot small.low { color: var(--amber); font-weight: 600; }
.dh-slot[aria-checked="true"] { border: 2px solid var(--green); background: var(--green-soft); padding: 10px 11px; }
.dh-slot.is-full { background: var(--soft); color: var(--muted); cursor: not-allowed; }
.dh-day:focus-visible, .dh-slot:focus-visible { outline: 2px solid var(--vp-c-brand-1); outline-offset: 2px; }
.dh-picker__foot { margin: 14px 0 0; padding: 10px 12px; border-radius: 8px; background: var(--green-soft); font-size: 13px; color: var(--ink); }

/* Who it's for */
.dh-band { padding: 28px 0; border-top: 1px solid var(--line); border-bottom: 1px solid var(--line); }
.dh-band__label { margin: 0 0 12px; font-size: 14px; color: var(--muted); text-align: center; }
.dh-chips { list-style: none; margin: 0; padding: 0; display: flex; flex-wrap: wrap; justify-content: center; gap: 8px; }
.dh-chips li { padding: 6px 14px; border-radius: 999px; background: var(--soft); font-size: 14px; color: var(--ink); }

/* Sections */
.dh-shot { margin-top: 56px; }
.dh-shot img, .dh-split img { width: 100%; height: auto; border-radius: 14px; border: 1px solid var(--line); background: var(--soft); }
.dh-section { margin-top: 96px; }
.dh-h2 { margin: 0 0 16px; font-family: var(--display); font-weight: 500; font-size: clamp(28px, 3.4vw, 40px); line-height: 1.1; letter-spacing: -0.01em; color: var(--ink); border: 0; padding: 0; }
.dh-text { margin: 0 0 16px; font-size: 16px; line-height: 1.65; color: var(--muted); }
.dh-text a, .dh-link { color: var(--vp-c-brand-1); font-weight: 600; text-decoration: none; }
.dh-link:hover { text-decoration: underline; }
.dh-features { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 16px; margin-top: 24px; }
.dh-feature { border: 1px solid var(--line); border-radius: 14px; padding: 22px; background: var(--card); }
.dh-feature h3 { margin: 0 0 8px; font-size: 17px; color: var(--ink); }
.dh-feature p { margin: 0; font-size: 15px; line-height: 1.6; color: var(--muted); }
.dh-split { display: grid; grid-template-columns: minmax(0, 5fr) minmax(0, 7fr); gap: 48px; align-items: center; }
.dh-split--flip > div { order: 2; }
.dh-steps { margin: 0 0 16px; padding-left: 20px; list-style: decimal; color: var(--muted); line-height: 1.65; }
.dh-steps li { margin-bottom: 6px; }
.dh-steps code { font-size: 13px; }
.dh-code { margin: 0; padding: 20px 22px; border-radius: 14px; background: #14201a; color: #d6eadc; font-size: 13px; line-height: 1.65; overflow-x: auto; }
.dh-examples { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 20px; margin-top: 24px; }
.dh-example { display: flex; flex-direction: column; gap: 6px; text-decoration: none; color: var(--ink); }
.dh-example img { width: 100%; height: auto; border-radius: 12px; border: 1px solid var(--line); margin-bottom: 8px; transition: transform 0.25s; }
.dh-example:hover img { transform: translateY(-3px); }
.dh-example b { font-size: 16px; }
.dh-example span { font-size: 14px; color: var(--muted); }
.dh-cta { margin-top: 112px; padding: 72px 0 88px; text-align: center; background: var(--soft); }
.dh-cta .dh-text { max-width: 560px; margin: 0 auto; }

@media (max-width: 900px) {
  .dh-hero { padding-top: 40px; }
  .dh-hero__grid, .dh-split { grid-template-columns: 1fr; gap: 32px; }
  .dh-split--flip > div { order: 0; }
  .dh-features, .dh-examples { grid-template-columns: 1fr; }
  .dh-section { margin-top: 72px; }
}
@media (max-width: 520px) {
  .dh-days { grid-template-columns: repeat(4, minmax(0, 1fr)); }
  .dh-slots { grid-template-columns: repeat(2, minmax(0, 1fr)); }
}
@media (prefers-reduced-motion: reduce) {
  .dh-example img { transition: none; }
}
</style>
