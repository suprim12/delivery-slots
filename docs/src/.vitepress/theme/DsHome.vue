<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue'
import { withBase } from 'vitepress'

// ---------------------------------------------------------------------------
// The docs' sample store: Green Grocer on The Parade, Norwood, delivering across Adelaide.
// The same windows, zones and fee as the examples.
// ---------------------------------------------------------------------------

const WINDOWS = [
  { start: 8, label: '8 – 10am' },
  { start: 10, label: '10am – 12pm' },
  { start: 12, label: '12 – 2pm' },
  { start: 14, label: '2 – 4pm' },
  { start: 16, label: '4 – 6pm' },
  { start: 18, label: '6 – 8pm' },
]

// Places left in each window, Monday to Sunday of the demo week. null: the window doesn't run that day.
const WEEK = [
  { dow: 'Mon', name: 'Monday', date: 16, left: [6, 2, 0, 9, 5, 1] },
  { dow: 'Tue', name: 'Tuesday', date: 17, left: [7, 7, 4, 9, 6, 3] },
  { dow: 'Wed', name: 'Wednesday', date: 18, left: [3, 0, 0, 2, 7, 5] },
  { dow: 'Thu', name: 'Thursday', date: 19, left: [9, 9, 8, 7, 9, 6] },
  { dow: 'Fri', name: 'Friday', date: 20, left: [1, 0, 2, 4, 3, 0] },
  { dow: 'Sat', name: 'Saturday', date: 21, left: [5, 4, null, null, null, null] },
  { dow: 'Sun', name: 'Sunday', date: 22, closed: 'Closed' },
]

const ADDRESSES = [
  { id: 'norwood', street: '12 Osmond Tce, Norwood', zone: 'Inner east', days: null, fee: 0 },
  { id: 'hahndorf', street: '15 Main St, Hahndorf', zone: 'Adelaide Hills', days: ['Thu', 'Sat'], fee: 4 },
]

const money = (n) => '$' + n.toFixed(2)

// Twig's braces, output as text so Vue doesn't read them.
const open = '{' + '{'
const close = '}' + '}'

// ---------------------------------------------------------------------------
// Hero: choose an address and a time, as a customer would.
// ---------------------------------------------------------------------------

const address = ref(ADDRESSES[0])
const day = ref(1)
const slot = ref(1)

const days = computed(() =>
  WEEK.map((d) => {
    const notHere = address.value.days && !address.value.days.includes(d.dow)
    const reason = d.closed ?? (notHere ? 'Not in your area' : null)
    return { ...d, reason, available: !reason && d.left.some((l) => l > 0) }
  }),
)
const slots = computed(() => {
  const d = days.value[day.value]
  if (!d || d.reason) return []
  return WINDOWS.map((w, i) => ({ ...w, left: d.left[i] })).filter((s) => s.left !== null)
})
const chosen = computed(() => {
  const s = slots.value[slot.value]
  return s && s.left > 0 ? s : null
})
const chosenLabel = computed(() => {
  const d = days.value[day.value]
  return chosen.value ? `${d.name} ${d.date} November, ${chosen.value.label}` : null
})
const delivery = computed(() => 5 + (chosen.value ? address.value.fee : 0))
const total = computed(() => 42 + 9.5 + delivery.value)

function pickDay(i) {
  day.value = i
  slot.value = slots.value.findIndex((s) => s.left > 0)
}
function pickAddress(a) {
  address.value = a
  const first = days.value.findIndex((d) => d.available)
  pickDay(first < 0 ? 0 : first)
}
const note = (s) => (s.left === 0 ? 'Full' : s.left <= 2 ? `${s.left} left` : address.value.fee ? '+' + money(address.value.fee) : 'Available')

// ---------------------------------------------------------------------------
// Split: the shop's rules on one side, what customers can choose on the other.
// ---------------------------------------------------------------------------

const now = ref(14 * 60 + 30) // 2:30pm on Monday
const cutoff = ref('20:00')
const lead = ref(2)
const sameDay = ref('true')

const clock = (m) => {
  const h = Math.floor(m / 60)
  const mm = String(m % 60).padStart(2, '0')
  return `${h % 12 || 12}:${mm}${h < 12 ? 'am' : 'pm'}`
}
const rules = computed(() => {
  const cut = cutoff.value === 'none' ? null : Number(cutoff.value.slice(0, 2)) * 60
  const earliest = now.value + Math.max(0, Number(lead.value) || 0) * 60
  const today = sameDay.value === 'true'
    ? WINDOWS.map((w) => ({ label: w.label, open: w.start * 60 >= earliest }))
    : null
  const tomorrowClosed = cut !== null && now.value >= cut
  const tomorrow = WINDOWS.map((w) => ({ label: w.label, open: !tomorrowClosed }))
  return {
    today,
    tomorrow,
    tomorrowClosed,
    openToday: today ? today.filter((s) => s.open).length : 0,
    openTomorrow: tomorrowClosed ? 0 : WINDOWS.length,
  }
})

// ---------------------------------------------------------------------------
// How it works and the footer
// ---------------------------------------------------------------------------

const step = ref(0)
const copied = ref('')
async function copy(text, key) {
  try {
    await navigator.clipboard.writeText(text)
    copied.value = key
    setTimeout(() => (copied.value = ''), 1600)
  } catch {
    /* clipboard unavailable: the text is still selectable */
  }
}

// Green Grocer's next-day cut-off is 8pm in Adelaide; the footer counts down to it.
const cutoffIn = ref('')
function tick() {
  const parts = Object.fromEntries(
    new Intl.DateTimeFormat('en-AU', { timeZone: 'Australia/Adelaide', hour: 'numeric', minute: 'numeric', hourCycle: 'h23' })
      .formatToParts(new Date())
      .map((p) => [p.type, p.value]),
  )
  const mins = Number(parts.hour) * 60 + Number(parts.minute)
  let left = 20 * 60 - mins
  if (left <= 0) left += 24 * 60
  cutoffIn.value = `${Math.floor(left / 60)}h ${String(left % 60).padStart(2, '0')}m`
}

let observer
let timer
onMounted(() => {
  observer = new IntersectionObserver((entries) => {
    for (const entry of entries) {
      if (entry.isIntersecting) step.value = Number(entry.target.dataset.step)
    }
  }, { rootMargin: '-45% 0px -45% 0px' })
  document.querySelectorAll('.dh-steps [data-step]').forEach((s) => observer.observe(s))
  tick()
  timer = setInterval(tick, 30000)
})
onBeforeUnmount(() => {
  observer?.disconnect()
  clearInterval(timer)
})

const installCmd = 'composer require suprim/delivery-slots && php craft plugin/install delivery-slots'
const pickerCmd = `{% for day in cart.deliverySlotDays %}
  {% for slot in day.slots %}
    <input type="radio" name="deliverySlot" value="{{ slot.value }}">
  {% endfor %}
{% endfor %}`

const examples = [
  { href: '/examples/zone-fees', img: '/examples/zone-fees.jpg', name: 'Zone days and fees', line: 'The Hills on Thursdays and Saturdays, for $4 more.', alt: 'Slots for an Adelaide Hills address: Thursday chosen, other weekdays not in your area, every slot +$4' },
  { href: '/examples/click-and-collect', img: '/examples/click-and-collect.jpg', name: 'Click and collect', line: 'Hourly pickup times, the same day, ready in an hour.', alt: 'Pick up in store chosen, with hourly pickup times from 9am to 6pm' },
  { href: '/examples/javascript-picker', img: '/examples/javascript-picker.jpg', name: 'Slot picker in JavaScript', line: 'Loads and holds slots without a page load.', alt: 'A row of days and six time buttons, 10am – 12pm chosen with 2 left' },
]
</script>

<template>
  <div class="dh">
    <!-- Hero ------------------------------------------------------------- -->
    <section class="dh-hero">
      <div class="dh-wrap dh-hero__head" data-reveal>
        <h1>Delivery times for Craft Commerce</h1>
        <p class="dh-lead">Customers choose when their order arrives, or when to collect it. You set the windows, how many orders each takes, and when ordering closes. Try it below as a customer.</p>
        <p class="dh-actions">
          <a class="dh-btn" :href="withBase('/getting-started/installation')">Get started</a>
          <a class="dh-link dh-link--light" :href="withBase('/examples/grocery-delivery')">See the examples →</a>
        </p>
      </div>

      <div class="dh-wrap">
        <div class="dh-live" data-ui data-reveal>
          <div class="dh-live__pick">
            <div class="dh-live__addr" role="group" aria-label="Delivering to">
              <button v-for="a in ADDRESSES" :key="a.id" type="button" :aria-pressed="a.id === address.id" @click="pickAddress(a)">{{ a.street }}</button>
            </div>
            <div class="dh-live__headrow">
              <strong>Choose a delivery time</strong>
              <span>Order by 8pm for next-day delivery</span>
            </div>
            <div class="dh-days" role="group" aria-label="Day">
              <button v-for="(d, i) in days" :key="d.date" type="button" class="dh-day" :aria-pressed="i === day" :disabled="!d.available" @click="pickDay(i)">
                <small>{{ d.dow }}</small>
                <b>{{ d.date }}</b>
                <small>{{ d.reason ?? '' }}</small>
              </button>
            </div>
            <div class="dh-slots" role="radiogroup" aria-label="Time">
              <button v-for="(s, i) in slots" :key="s.label" type="button" role="radio" class="dh-slot" :aria-checked="i === slot && s.left > 0" :disabled="s.left === 0" @click="slot = i">
                <b>{{ s.label }}</b>
                <small :class="{ low: s.left > 0 && s.left <= 2 }">{{ note(s) }}</small>
              </button>
            </div>
          </div>

          <div class="dh-live__panel" aria-live="polite">
            <p class="dh-live__label">Order summary</p>
            <ol class="dh-live__list">
              <li><span>Seasonal veg box</span><b>$42.00</b></li>
              <li><span>Sourdough loaf</span><b>$9.50</b></li>
              <li><span>Delivery{{ chosen && address.fee ? ', incl. Hills slot fee' : '' }}</span><b>{{ money(delivery) }}</b></li>
              <li class="dh-live__total"><span>Total</span><b>{{ money(total) }}</b></li>
            </ol>
            <div class="dh-live__held">
              <template v-if="chosen">
                <strong>Held for you for 15 minutes</strong>
                <span>{{ chosenLabel }} · {{ address.zone }}</span>
              </template>
              <template v-else>
                <strong>Choose a time</strong>
                <span>Full slots can’t be chosen.</span>
              </template>
            </div>
            <pre class="dh-live__code"><code><span class="t-k" v-text="open"></span> cart.deliverySlot.label <span class="t-k" v-text="close"></span>
<span class="dh-dim">→ {{ chosenLabel ?? 'null' }}</span></code></pre>
          </div>
        </div>
      </div>
    </section>

    <!-- Split: the shop's rules, the customer's choice --------------------- -->
    <section class="dh-split" aria-labelledby="dh-split-title">
      <div class="dh-split__in">
        <h2 id="dh-split-title" class="dh-split__title dh-split__title--a">Set the rules,</h2>
        <div class="dh-split__rules" data-ui>
          <label for="dh-now" class="dh-split__now">A customer orders at <span class="dh-split__clock">{{ clock(now) }}</span> on Monday</label>
          <input id="dh-now" v-model.number="now" class="dh-range" type="range" min="360" max="1380" step="30">
          <pre class="dh-split__code"><code><span class="dh-dim">{# Local delivery's schedule #}</span>
cutoff: <label class="dh-edit"><span class="sr-only">Next-day cut-off</span><select v-model="cutoff"><option value="18:00">'18:00'</option><option value="20:00">'20:00'</option><option value="22:00">'22:00'</option><option value="none">null</option></select></label>
leadHours: <label class="dh-edit"><span class="sr-only">Book at least, in hours</span><input v-model.number="lead" type="number" min="0" max="8" inputmode="numeric"></label>
sameDay: <label class="dh-edit"><span class="sr-only">Same-day slots</span><select v-model="sameDay"><option value="true">true</option><option value="false">false</option></select></label></code></pre>
        </div>
      </div>
      <div class="dh-split__out">
        <div class="dh-results" data-ui aria-live="polite">
          <div class="dh-results__day">
            <p class="dh-results__head">Today, Monday <span>{{ rules.today ? rules.openToday + ' of 6 open' : 'No same-day slots' }}</span></p>
            <ul v-if="rules.today">
              <li v-for="s in rules.today" :key="'t' + s.label" :class="{ off: !s.open }"><span>{{ s.label }}</span><b>{{ s.open ? 'Open' : 'Too late' }}</b></li>
            </ul>
          </div>
          <div class="dh-results__day">
            <p class="dh-results__head">Tomorrow, Tuesday <span>{{ rules.tomorrowClosed ? 'Ordering has closed' : rules.openTomorrow + ' of 6 open' }}</span></p>
            <ul>
              <li v-for="s in rules.tomorrow" :key="'m' + s.label" :class="{ off: !s.open }"><span>{{ s.label }}</span><b>{{ s.open ? 'Open' : 'Closed' }}</b></li>
            </ul>
          </div>
        </div>
        <p class="dh-split__title dh-split__title--b" aria-hidden="true">see the slots.</p>
      </div>
    </section>

    <!-- Features ------------------------------------------------------------ -->
    <section class="dh-wrap dh-features" aria-labelledby="dh-features-title">
      <h2 id="dh-features-title" data-reveal>Everything after the checkout, too</h2>
      <div class="dh-cells">
        <article class="dh-cell" data-reveal>
          <div class="dh-cell__text"><h3>A run sheet for drivers</h3><p>Each day’s orders by slot, zone and suburb, with phone numbers. Print a page per slot.</p></div>
          <img :src="withBase('/images/run-sheet.jpg')" width="1600" height="1060" alt="The run sheet: six slots with how full each is, and eight orders grouped by time" loading="lazy">
        </article>
        <article class="dh-cell dh-cell--flip" data-reveal>
          <div class="dh-cell__text"><h3>What to pack</h3><p>How many of each item for the day, by slot. Six veg boxes, five loaves, two bunches of flowers.</p></div>
          <img :src="withBase('/images/packing-list.jpg')" width="1600" height="597" alt="The packing list: four items with their quantity in each slot and a total" loading="lazy">
        </article>
        <article class="dh-cell" data-reveal>
          <div class="dh-cell__text"><h3>Move an order</h3><p>A customer rings to change their time. Pick the new one from the order page, even if it’s full.</p></div>
          <img :src="withBase('/images/change-slot.jpg')" width="1600" height="748" alt="Changing an order’s delivery time: each window with how many orders it has and a Move here button" loading="lazy">
        </article>
        <article class="dh-cell dh-cell--flip" data-reveal>
          <div class="dh-cell__text"><h3>Today at a glance</h3><p>A dashboard widget for today and tomorrow, and a delivery date filter on Commerce’s orders.</p></div>
          <img :src="withBase('/images/widget.jpg')" width="734" height="534" alt="The Delivery Slots widget showing today’s windows and how many are booked" loading="lazy">
        </article>
        <article class="dh-cell" data-reveal>
          <div class="dh-cell__text"><h3>Exports for any week</h3><p>Orders or the packing list as CSV, from the control panel or <code>php craft delivery-slots/export</code>.</p></div>
          <img :src="withBase('/images/export.jpg')" width="1600" height="570" alt="The Export page: dates, a shipping method, and Orders or Packing list" loading="lazy">
        </article>
        <article class="dh-cell dh-cell--flip" data-reveal>
          <div class="dh-cell__text"><h3>Headless checkouts</h3><p>The slot comes back in Commerce’s cart JSON, and two actions list and hold slots.</p></div>
          <pre class="dh-cell__code" data-ui><code>"deliverySlot": {
  "value": <span class="t-s">"2026-11-17@10:00"</span>,
  "label": <span class="t-s">"Tuesday 17 November,
            10am – 12pm"</span>,
  "fee": <span class="t-n">0</span>,
  "isBooked": <span class="t-k">false</span>
}</code></pre>
        </article>
      </div>
    </section>

    <!-- Zones ----------------------------------------------------------------- -->
    <section class="dh-shape" aria-labelledby="dh-shape-title">
      <div class="dh-wrap dh-shape__grid">
        <figure class="dh-shape__shot" data-reveal>
          <img :src="withBase('/examples/zone-fees.jpg')" width="1600" height="902" alt="Slots for 15 Main St, Hahndorf: Thursday chosen, other weekdays say Not in your area, every slot +$4.00" loading="lazy">
        </figure>
        <div class="dh-shape__text">
          <h2 id="dh-shape-title" data-reveal>Different days for different suburbs</h2>
          <p data-reveal>Draw your delivery areas with Geo Maps and give each its own days, order limit and fee. The van goes up to the Hills on Thursdays and Saturdays; the city gets every weekday.</p>
          <ul class="dh-shape__list" data-reveal>
            <li><strong>Days per zone.</strong> Other days say “Not in your area”.</li>
            <li><strong>A limit per zone.</strong> At most four Hills orders share a slot.</li>
            <li><strong>A fee per zone.</strong> Added to the shipping, before tax.</li>
            <li><strong>Outside every zone?</strong> Delivery isn’t offered, so nobody orders a trip you don’t make.</li>
          </ul>
          <p data-reveal><a class="dh-link" :href="withBase('/guides/zones')">Delivery zones →</a></p>
        </div>
      </div>
    </section>

    <!-- Examples -------------------------------------------------------------- -->
    <section class="dh-ex" aria-labelledby="dh-ex-title">
      <div class="dh-wrap">
        <h2 id="dh-ex-title" data-reveal>Built with Delivery Slots</h2>
        <p class="dh-ex__intro" data-reveal>Complete checkout templates you can copy, from a grocer on The Parade, Norwood.</p>
        <a class="dh-ex__lead" :href="withBase('/examples/grocery-delivery')" data-reveal>
          <img :src="withBase('/examples/grocery-delivery.jpg')" width="1600" height="902" alt="A grocery checkout: Local delivery chosen, Tuesday 17 selected, 8 – 10am with 2 left" loading="lazy">
          <span class="dh-ex__text">
            <span class="dh-ex__name">Grocery delivery checkout</span>
            <span class="dh-ex__line">Delivery or pickup, a week of days, two-hour slots with places left, and the order summary.</span>
          </span>
        </a>
        <ul class="dh-ex__grid">
          <li v-for="ex in examples" :key="ex.href" data-reveal>
            <a :href="withBase(ex.href)">
              <img :src="withBase(ex.img)" width="1600" height="902" :alt="ex.alt" loading="lazy">
              <span class="dh-ex__name">{{ ex.name }}</span>
              <span class="dh-ex__line">{{ ex.line }}</span>
            </a>
          </li>
        </ul>
      </div>
    </section>

    <!-- The baker's round ------------------------------------------------------- -->
    <section class="dh-city" aria-labelledby="dh-city-title">
      <div class="dh-wrap dh-city__grid">
        <figure class="dh-city__plan" data-reveal>
          <img :src="withBase('/world/bakers-cart-michelin-1656.jpg')" width="1400" height="1093" alt="A seventeenth-century painting of bakers with their bread cart in a city street, loaves stacked on the cart" loading="lazy">
          <figcaption>The Baker’s Cart, Jean Michelin, 1656. The Metropolitan Museum of Art.</figcaption>
        </figure>
        <div class="dh-city__text">
          <h2 id="dh-city-title" data-reveal>The baker always came at seven</h2>
          <p data-reveal>For three hundred years the bread arrived at the same time each morning, and everyone on the street knew when to open the door. Online orders lost that. Delivery Slots gives it back: a time chosen at checkout, and a shop that knows exactly how many orders it can carry.</p>
          <figure data-reveal>
            <img :src="withBase('/world/bakers-cart-victor-harbor-1920.jpg')" width="994" height="600" alt="Fred Bailey in J. E. Sedunary’s horse-drawn baker’s cart at Victor Harbor, South Australia, 1920" loading="lazy">
            <figcaption>Fred Bailey on the baker’s round at Victor Harbor, South Australia, 1920.</figcaption>
          </figure>
          <figure data-reveal>
            <img :src="withBase('/world/victoria-square-adelaide-1916.jpg')" width="1400" height="1050" alt="Adelaide from above in 1916, looking south-west across Victoria Square" loading="lazy">
            <figcaption>Adelaide in 1916, where the docs’ sample store delivers today.</figcaption>
          </figure>
        </div>
      </div>
    </section>

    <!-- How it works: pinned frame ---------------------------------------------- -->
    <section class="dh-wrap dh-how" aria-labelledby="dh-how-title">
      <h2 id="dh-how-title" data-reveal>Three steps to delivery times</h2>
      <div class="dh-how__grid">
        <div class="dh-steps">
          <div class="dh-step" data-step="0">
            <p class="dh-step__say">First, install it.</p>
            <div class="dh-call">
              <pre><code>composer require suprim/delivery-slots
php craft plugin/install delivery-slots</code></pre>
              <button type="button" class="dh-copy" @click="copy(installCmd, 'install')">{{ copied === 'install' ? 'Copied' : 'Copy' }}</button>
            </div>
          </div>
          <div class="dh-step" data-step="1">
            <p class="dh-step__say">Then set the times.</p>
            <p class="dh-step__note">Delivery Slots → Schedules, choose a shipping method, and add its windows, limits and cut-off.</p>
          </div>
          <div class="dh-step" data-step="2">
            <p class="dh-step__say">Then add the picker.</p>
            <div class="dh-call">
              <pre v-pre><code><span class="t-k">{%</span> for day in cart.deliverySlotDays <span class="t-k">%}</span>
  <span class="t-k">{%</span> for slot in day.slots <span class="t-k">%}</span>
    &lt;input type="radio" name="deliverySlot"
      value="<span class="t-k">{{</span> slot.value <span class="t-k">}}</span>"&gt;
  <span class="t-k">{%</span> endfor <span class="t-k">%}</span>
<span class="t-k">{%</span> endfor <span class="t-k">%}</span></code></pre>
              <button type="button" class="dh-copy" @click="copy(pickerCmd, 'picker')">{{ copied === 'picker' ? 'Copied' : 'Copy' }}</button>
            </div>
          </div>
        </div>
        <div class="dh-frame" aria-hidden="true">
          <div class="dh-frame__inner">
            <pre class="dh-frame__slide dh-frame__slide--term" :class="{ 'is-on': step === 0 }"><code><span class="t-k">$</span> composer require suprim/delivery-slots
<span class="dh-dim">  - Installing suprim/delivery-slots (1.0.0)</span>
<span class="t-k">$</span> php craft plugin/install delivery-slots
<span class="dh-ok">*** installed delivery-slots successfully</span></code></pre>
            <img class="dh-frame__slide" :class="{ 'is-on': step === 1 }" :src="withBase('/images/schedule.jpg')" width="1600" height="2217" alt="" loading="lazy">
            <img class="dh-frame__slide" :class="{ 'is-on': step === 2 }" :src="withBase('/examples/grocery-delivery.jpg')" width="1600" height="902" alt="" loading="lazy">
          </div>
        </div>
      </div>
    </section>

    <!-- Close ----------------------------------------------------------------- -->
    <section class="dh-close">
      <div class="dh-wrap">
        <h2>Give every order a time.</h2>
        <div class="dh-close__row">
          <pre class="dh-close__cmd"><code>composer require suprim/delivery-slots</code></pre>
          <button type="button" class="dh-btn" @click="copy('composer require suprim/delivery-slots', 'close')">{{ copied === 'close' ? 'Copied' : 'Copy command' }}</button>
          <a class="dh-link dh-link--light" :href="withBase('/getting-started/installation')">Installation guide →</a>
        </div>
      </div>
    </section>

    <!-- Footer tiles ----------------------------------------------------------- -->
    <footer class="dh-foot">
      <div class="dh-tile dh-tile--about">
        <p class="dh-tile__big">A Craft Commerce plugin by suprim.</p>
        <p class="dh-tile__small">Pictures: “The Baker’s Cart” by Jean Michelin, 1656, CC0, The Metropolitan Museum of Art via Wikimedia Commons. “Horse drawn baker’s cart Victor Harbor 1920”, State Library of South Australia, CC BY 2.0 via Wikimedia Commons. “Adelaide City View, looking south-west across Victoria Square, circa 1916” by the South Australian government photographer, CC0 via Wikimedia Commons.</p>
      </div>
      <nav class="dh-tile dh-tile--nav" aria-label="Docs">
        <a :href="withBase('/getting-started/installation')">Installation</a>
        <a :href="withBase('/getting-started/schedules')">Setting up a schedule</a>
        <a :href="withBase('/guides/slot-picker')">The slot picker</a>
        <a :href="withBase('/examples/grocery-delivery')">Examples</a>
        <a :href="withBase('/getting-started/privacy')">Privacy and data</a>
        <a href="https://plugins.craftcms.com/geomaps">Geo Maps</a>
      </nav>
      <div class="dh-tile dh-tile--time">
        <p class="dh-tile__small">Green Grocer’s orders for tomorrow close in</p>
        <p class="dh-tile__clock">{{ cutoffIn || '—' }}</p>
      </div>
      <div class="dh-tile dh-tile--mail">
        <p class="dh-tile__small">Questions and support</p>
        <a href="mailto:support@suprim.com.au">support@suprim.com.au</a>
      </div>
      <div class="dh-tile dh-tile--mark"><span>Delivery Slots</span></div>
      <a class="dh-tile dh-tile--go" :href="withBase('/getting-started/introduction')">
        <span>Read the docs</span>
        <svg width="56" height="56" viewBox="0 0 56 56" aria-hidden="true"><path d="M12 44 44 12M20 12h24v24" fill="none" stroke="currentColor" stroke-width="4"/></svg>
      </a>
    </footer>
  </div>
</template>

<style scoped>
.dh {
  --soot: #0f1b15;       /* the green of the van at dusk */
  --soot-2: #18291f;
  --paper: #f1efea;      /* paper bag, unbleached */
  --paper-2: #e6e2d9;
  --ink: #14191a;
  --ink-soft: #4a5048;
  --light: #f3f2ec;
  --light-soft: #b5c2b8;
  --accent: #8fdcab;     /* Delivery Slots brand, a fresh leaf */
  --accent-hover: #a6e5bd;
  --accent-ink: #0b1a12;
  --brand: #1f5f3f;
  --amber: #a4470b;
  --gap: 8px;
  --s-ui: 15px;
  --s-body: 17px;
  --s-lead: clamp(19px, 1.5vw, 23px);
  --s-head: clamp(28px, 2.6vw, 36px);
  --s-sect: clamp(36px, 3.6vw, 56px);
  --m: 24px;
  font-family: 'Clash Grotesk', var(--vp-font-family-base);
  color: var(--ink);
  background: var(--paper);
}

@media (min-width: 960px) { .dh { --m: 48px; } }
@media (max-width: 959px) {
  .dh-features, .dh-how { padding-top: 112px !important; padding-bottom: 112px !important; }
  .dh-city { padding: 112px 0 !important; }
  .dh-close { padding: 112px 0 96px !important; }
}

.dh-wrap { max-width: 1344px; margin: 0 auto; padding: 0 var(--m); }
.dh h1, .dh h2, .dh h3 { font-weight: 500; letter-spacing: -0.03em; line-height: 0.98; margin: 0; border: 0; padding: 0; }
.dh p { margin: 0; }
.dh a { text-decoration: none; }
.sr-only { position: absolute; width: 1px; height: 1px; overflow: hidden; clip: rect(0 0 0 0); white-space: nowrap; }

.t-k { color: #8fd3a6; }
.t-s { color: #f0d08a; }
.t-n { color: #f0a36b; }
.dh-dim { color: #8fa197; }
.dh-ok { color: #8fdcab; }

.dh-btn {
  display: inline-flex; align-items: center; height: 48px; padding: 0 24px;
  border: 0; border-radius: 999px; background: var(--accent); color: var(--accent-ink);
  font: 500 var(--s-body)/1 'Clash Grotesk', var(--vp-font-family-base); cursor: pointer;
  transition: transform 0.2s cubic-bezier(0.2, 0.7, 0.2, 1), background-color 0.2s;
}
.dh-btn:hover { background: var(--accent-hover); }
.dh-btn:active { transform: scale(0.97); }
.dh-btn:focus-visible, .dh-link:focus-visible, .dh-copy:focus-visible { outline: 2px solid var(--accent); outline-offset: 3px; }
.dh-link { font-size: var(--s-body); font-weight: 500; color: var(--brand); transition: opacity 0.2s; }
.dh-link--light { color: var(--light); }
.dh-link:hover { opacity: 0.7; }

/* Hero */
.dh-hero { background: var(--soot); color: var(--light); padding: 72px 0 96px; }
.dh-hero__head { display: grid; gap: 24px; margin-bottom: 48px; }
.dh-hero h1 { font-size: clamp(44px, 6vw, 92px); max-width: 13ch; }
.dh-lead { font-size: var(--s-lead); line-height: 1.45; max-width: 36em; color: var(--light); }
.dh-actions { display: flex; flex-wrap: wrap; gap: 24px; align-items: center; }

.dh-live {
  display: grid; overflow: hidden; border-radius: 10px; background: #fff; color: var(--ink);
  font-family: var(--vp-font-family-base);
}
@media (min-width: 960px) { .dh-live { grid-template-columns: 7fr 5fr; } }
.dh-live__pick { padding: 28px; background: #f6f5f1; display: grid; align-content: start; gap: 16px; }
.dh-live__addr { display: flex; flex-wrap: wrap; gap: 8px; }
.dh-live__addr button {
  height: 40px; padding: 0 16px; border-radius: 999px; border: 0; background: #e9e7df; color: var(--ink);
  font: 500 var(--s-ui)/1 var(--vp-font-family-base); cursor: pointer; transition: background-color 0.2s;
}
.dh-live__addr button:hover { background: #dedbd0; }
.dh-live__addr button[aria-pressed="true"] { background: var(--brand); color: #fff; }
.dh-live__headrow { display: flex; flex-wrap: wrap; justify-content: space-between; align-items: baseline; gap: 8px; }
.dh-live__headrow strong { font-size: var(--s-lead); font-weight: 600; }
.dh-live__headrow span { font-size: var(--s-ui); color: #5d5f63; }
.dh-days { display: grid; grid-template-columns: repeat(7, minmax(0, 1fr)); gap: 8px; }
.dh-day {
  height: 76px; padding: 0 2px; border-radius: 10px; border: 0; background: #fff; color: var(--ink);
  font: inherit; cursor: pointer; transition: background-color 0.2s, transform 0.15s;
}
.dh-day b { display: block; font-size: 20px; line-height: 1.2; }
.dh-day small { display: block; font-size: 11px; line-height: 1.2; color: #5d5f63; min-height: 13px; }
.dh-day:hover:not(:disabled) { background: #eef4ef; }
.dh-day:active:not(:disabled) { transform: scale(0.97); }
.dh-day[aria-pressed="true"] { background: var(--brand); color: #fff; }
.dh-day[aria-pressed="true"] small { color: #d1e6d8; }
.dh-day:disabled { background: #ecebe5; color: #6b6d72; cursor: not-allowed; }
.dh-slots { display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 8px; min-height: 152px; align-content: start; }
.dh-slot {
  text-align: left; padding: 13px 14px; border-radius: 10px; border: 0; background: #fff; color: var(--ink);
  font: inherit; cursor: pointer; outline: 2px solid transparent; outline-offset: -2px; transition: outline-color 0.15s, background-color 0.2s, transform 0.15s;
}
.dh-slot b { display: block; font-size: var(--s-ui); }
.dh-slot small { font-size: 13px; color: #5d5f63; }
.dh-slot small.low { color: var(--amber); font-weight: 600; }
.dh-slot:hover:not(:disabled) { background: #eef4ef; }
.dh-slot:active:not(:disabled) { transform: scale(0.98); }
.dh-slot[aria-checked="true"] { background: #eef4ef; outline-color: var(--brand); }
.dh-slot:disabled { background: #ecebe5; color: #6b6d72; cursor: not-allowed; }
.dh-day:focus-visible, .dh-slot:focus-visible, .dh-live__addr button:focus-visible { outline: 2px solid var(--brand); outline-offset: 2px; }

.dh-live__panel { display: grid; align-content: start; gap: 12px; padding: 28px; font-size: var(--s-ui); }
.dh-live__label { font-size: var(--s-ui); font-weight: 600; color: #5d6170; }
.dh-live__list { margin: 0 0 4px; padding: 0; list-style: none; }
.dh-live__list li { display: flex; justify-content: space-between; gap: 12px; padding: 9px 0; border-bottom: 1px solid #ecebe6; }
.dh-live__list b { font-variant-numeric: tabular-nums; font-weight: 500; }
.dh-live__list .dh-live__total { border-bottom: 0; font-size: var(--s-body); }
.dh-live__list .dh-live__total b { font-weight: 700; }
.dh-live__held { display: grid; gap: 4px; padding: 16px 18px; border-radius: 8px; background: #eef4ef; border-left: 4px solid var(--brand); }
.dh-live__held strong { font-size: var(--s-body); }
.dh-live__held span { color: #474b57; }
.dh-live__code {
  margin: 0; padding: 14px 16px; border-radius: 8px; background: var(--soot); color: #e8ebe7;
  font: var(--s-ui)/1.6 var(--vp-font-family-mono); white-space: pre-wrap; overflow-wrap: anywhere;
}

/* Split */
.dh-split { display: grid; }
@media (min-width: 960px) { .dh-split { grid-template-columns: 1fr 1fr; min-height: 92svh; } }
.dh-split__in, .dh-split__out { position: relative; display: grid; align-content: start; gap: 16px; padding: 96px var(--m) 140px; }
.dh-split__in { background: var(--soot-2); color: var(--light); }
.dh-split__out { background: var(--paper); }
@media (min-width: 960px) {
  .dh-split__in, .dh-split__out { padding-top: 16vh; }
  .dh-split__in { justify-items: end; }
}
.dh-split__rules { display: grid; gap: 16px; width: min(100%, 460px); color: var(--light); }
.dh-split__now { font-size: var(--s-lead); line-height: 1.3; color: var(--light); }
.dh-split__clock { display: block; font: 500 clamp(52px, 6vw, 96px)/1 'Clash Grotesk', var(--vp-font-family-base); letter-spacing: -0.03em; font-variant-numeric: tabular-nums; margin: 8px 0 4px; }
.dh-split__code { margin: 16px 0 0; font: var(--s-lead)/1.7 var(--vp-font-family-mono); color: #e8ebe7; white-space: pre-wrap; background: none; }
.dh-range { width: 100%; accent-color: var(--accent); height: 32px; margin-bottom: 12px; cursor: pointer; }
.dh-edit input, .dh-edit select {
  font: inherit; color: #f0a36b; background: #26392e; border: 0; border-radius: 4px; padding: 0 6px;
  width: 3em; height: 1.4em; vertical-align: baseline;
}
.dh-edit select { width: auto; color: #f0d08a; }
.dh-edit input:focus, .dh-edit select:focus, .dh-range:focus-visible { outline: 2px solid var(--accent); }
.dh-split__title {
  position: absolute; bottom: 32px; font: 500 var(--s-sect)/1 'Clash Grotesk', var(--vp-font-family-base);
  letter-spacing: -0.03em;
}
.dh-split__title--a { right: var(--m); color: var(--light); }
.dh-split__title--b { left: var(--m); color: var(--ink); }
@media (max-width: 959px) {
  .dh-split__title--a { left: var(--m); right: auto; }
  .dh-split__title--a::after { content: ' see the slots.'; }
  .dh-split__title--b { display: none; }
}
.dh-results {
  display: grid; gap: 20px; width: min(100%, 520px); padding: 22px 24px; border-radius: 10px; background: #fff;
  font-family: var(--vp-font-family-base); font-size: var(--s-ui);
}
.dh-results__head { display: flex; justify-content: space-between; gap: 12px; font-weight: 600; margin-bottom: 8px !important; }
.dh-results__head span { font-weight: 400; color: #5d6170; }
.dh-results ul { margin: 0; padding: 0; list-style: none; display: grid; grid-template-columns: repeat(3, minmax(0, 1fr)); gap: 6px; }
.dh-results li { display: grid; gap: 2px; padding: 10px 12px; border-radius: 8px; background: #eef4ef; transition: background-color 0.25s, color 0.25s; }
.dh-results li b { font-weight: 500; font-size: 13px; color: var(--brand); }
.dh-results li.off { background: #f1f0ec; color: #5f6166; }
.dh-results li.off b { color: #5f6166; }

/* Features */
@media (max-width: 759px) {
  .dh-features .dh-cells {
    display: flex; overflow-x: auto; scroll-snap-type: x mandatory; gap: 12px;
    margin: 0 calc(var(--m) * -1); padding: 0 var(--m) 8px; scroll-padding: 0 var(--m); scrollbar-width: none;
  }
  .dh-features .dh-cells::-webkit-scrollbar { display: none; }
  .dh-features .dh-cells > .dh-cell { flex: 0 0 84%; scroll-snap-align: start; }
}
.dh-features { padding-top: 160px; padding-bottom: 160px; }
.dh-features h2 { font-size: var(--s-sect); margin-bottom: 56px; }
.dh-cells { display: grid; gap: var(--gap); }
@media (min-width: 760px) { .dh-cells { grid-template-columns: 1fr 1fr; } }
@media (min-width: 1100px) { .dh-cells { grid-template-columns: repeat(3, 1fr); } }
.dh-cell { display: grid; grid-template-rows: auto 1fr; gap: 24px; min-height: 440px; overflow: hidden; border-radius: 10px; background: var(--paper-2); }
.dh-cell__text { padding: 28px 28px 0; }
.dh-cell h3 { font-size: var(--s-lead); margin-bottom: 10px; }
.dh-cell p { font-size: var(--s-body); line-height: 1.45; color: var(--ink); }
.dh-cell p code { font: 14px var(--vp-font-family-mono); padding: 1px 5px; border-radius: 4px; background: var(--paper); }
.dh-cell img, .dh-cell__code { display: block; align-self: end; width: calc(100% - 56px); margin: 0 28px 28px; border-radius: 8px; }
.dh-cell img { height: auto; box-sizing: border-box; padding: 10px; background: #fff; }
.dh-cell__code { height: 250px; }
.dh-cell--flip { grid-template-rows: 1fr auto; }
.dh-cell--flip > :first-child { order: 2; padding: 0 28px 28px; }
.dh-cell--flip img, .dh-cell--flip .dh-cell__code { align-self: start; margin: 28px 28px 0; }
@media (max-width: 759px) {
  .dh-cell { min-height: 0; }
  .dh-cell__text { padding: 20px 20px 0; }
  .dh-cell--flip > :first-child { padding: 0 20px 20px; }
  .dh-cell img, .dh-cell__code { width: calc(100% - 40px); margin: 0 20px 20px; }
  .dh-cell--flip img, .dh-cell--flip .dh-cell__code { margin: 20px 20px 0; }
}
.dh-cell__code { padding: 22px 24px; background: var(--soot); color: #e8ebe7; font: var(--s-ui)/1.6 var(--vp-font-family-mono); white-space: pre-wrap; overflow: hidden; }

/* Zones */
.dh-shape { background: var(--paper-2); padding: 160px 0; }
.dh-shape__grid { display: grid; gap: 48px; align-items: center; }
@media (min-width: 960px) { .dh-shape__grid { grid-template-columns: 7fr 5fr; gap: 64px; } }
.dh-shape__shot { margin: 0; }
.dh-shape__shot img { display: block; width: 100%; height: auto; border-radius: 10px; }
.dh-shape__text { display: grid; gap: 24px; }
.dh-shape__text h2 { font-size: var(--s-sect); }
.dh-shape__text > p { font-size: var(--s-lead); line-height: 1.5; }
.dh-shape__list { display: grid; gap: 14px; margin: 0; padding: 0; list-style: none; font-size: var(--s-body); line-height: 1.5; }
.dh-shape__list strong { font-weight: 600; }
@media (max-width: 959px) { .dh-shape { padding: 112px 0; } }

/* Examples */
.dh-ex { background: var(--soot); color: var(--light); padding: 160px 0; }
.dh-ex h2 { font-size: var(--s-sect); }
.dh-ex .dh-ex__intro { margin: 16px 0 56px; font-size: var(--s-lead); line-height: 1.35; color: var(--light); max-width: 36ch; }
.dh-ex a { display: block; color: var(--light); }
.dh-ex img { display: block; width: 100%; height: auto; border-radius: 10px; background: var(--soot-2); transition: transform 0.4s cubic-bezier(0.2, 0.7, 0.2, 1); }
.dh-ex a:hover img { transform: translateY(-4px); }
.dh-ex a:focus-visible { outline: 2px solid var(--accent); outline-offset: 6px; border-radius: 12px; }
.dh-ex__name { display: block; margin-top: 16px; font: 500 var(--s-lead)/1.2 'Clash Grotesk', var(--vp-font-family-base); }
.dh-ex__line { display: block; margin-top: 6px; font-size: var(--s-body); line-height: 1.45; color: var(--light-soft); }
.dh-ex__lead { margin-bottom: 64px; }
.dh-ex__grid { display: grid; gap: 48px 24px; margin: 0; padding: 0; list-style: none; }
@media (min-width: 760px) { .dh-ex__grid { grid-template-columns: 1fr 1fr; } }
@media (min-width: 1100px) {
  .dh-ex a.dh-ex__lead { display: grid; grid-template-columns: 8fr 4fr; gap: 40px; align-items: end; }
  .dh-ex__lead .dh-ex__name { margin-top: 0; font-size: var(--s-head); }
  .dh-ex__grid { grid-template-columns: repeat(3, 1fr); }
}
@media (max-width: 959px) { .dh-ex { padding: 96px 0; } .dh-ex .dh-ex__intro { margin-bottom: 40px; } .dh-ex__lead { margin-bottom: 48px; } }

/* The baker's round */
.dh-city { background: var(--paper-2); padding: 160px 0; }
.dh-city__grid { display: grid; gap: 48px; align-items: start; }
@media (min-width: 960px) { .dh-city__grid { grid-template-columns: 6fr 6fr; gap: 64px; } }
.dh-city__plan { margin: 0; }
@media (min-width: 960px) { .dh-city__plan { position: sticky; top: calc(var(--vp-nav-height) + 24px); } }
.dh-city__plan img, .dh-city__text img { width: 100%; height: auto; display: block; }
.dh-city figure { margin: 0; }
.dh-city figcaption { margin-top: 12px; font-size: var(--s-ui); color: var(--ink-soft); }
.dh-city__text { display: grid; gap: 32px; }
.dh-city__text h2 { font-size: var(--s-sect); }
.dh-city__text > p { font-size: var(--s-lead); line-height: 1.5; max-width: 32em; }

/* How it works */
.dh-how { padding-top: 160px; padding-bottom: 160px; }
.dh-how h2 { font-size: var(--s-sect); margin-bottom: 56px; }
.dh-how__grid { display: grid; gap: 48px; }
@media (min-width: 960px) { .dh-how__grid { grid-template-columns: 5fr 7fr; gap: 64px; } }
.dh-steps { display: grid; gap: 0; }
.dh-step { min-height: 60vh; display: grid; align-content: center; gap: 16px; }
@media (max-width: 959px) { .dh-step { min-height: 0; padding: 24px 0; } }
.dh-step__say { font: 500 var(--s-head)/1.1 'Clash Grotesk', var(--vp-font-family-base); letter-spacing: -0.02em; }
.dh-step__note { font-size: var(--s-body); line-height: 1.5; max-width: 30em; }
.dh-call { position: relative; border-radius: 8px; background: var(--soot); color: #e8ebe7; }
.dh-call pre { margin: 0; padding: 20px 88px 20px 20px; font: var(--s-ui)/1.6 var(--vp-font-family-mono); white-space: pre-wrap; overflow-wrap: anywhere; }
.dh-copy {
  position: absolute; top: 12px; right: 12px; height: 32px; padding: 0 14px; border: 0; border-radius: 999px;
  background: #26392e; color: #e8ebe7; font: 500 var(--s-ui)/1 var(--vp-font-family-base); cursor: pointer;
}
.dh-copy:hover { background: #33493c; }
.dh-frame { display: none; }
@media (min-width: 960px) {
  .dh-frame { display: block; position: relative; }
  .dh-frame__inner { position: sticky; top: calc(var(--vp-nav-height) + 10vh); aspect-ratio: 4 / 3.3; border-radius: 10px; overflow: hidden; background: var(--paper-2); }
}
.dh-frame__slide {
  position: absolute; inset: 0; width: 100%; height: 100%; object-fit: cover; object-position: top left;
  opacity: 0; transform: scale(1.02); transition: opacity 0.5s, transform 0.6s cubic-bezier(0.2, 0.7, 0.2, 1);
}
.dh-frame__slide.is-on { opacity: 1; transform: none; }
.dh-frame__slide--term {
  margin: 0; display: grid; align-content: center; padding: 48px; background: var(--soot); color: #e8ebe7;
  font: var(--s-body)/1.9 var(--vp-font-family-mono); white-space: pre-wrap;
}

/* Close */
.dh-close { background: var(--soot); color: var(--light); padding: 160px 0 120px; }
.dh-close h2 { font-size: clamp(44px, 7vw, 112px); max-width: 12ch; margin-bottom: 48px; }
.dh-close__row { display: flex; flex-wrap: wrap; gap: 16px 24px; align-items: center; }
.dh-close__cmd {
  display: inline-flex; align-items: center; height: 48px; margin: 0; padding: 0 20px; border-radius: 999px;
  background: var(--soot-2); color: #e8ebe7; font: var(--s-ui)/1 var(--vp-font-family-mono);
}

/* Footer tiles */
.dh-foot { display: grid; gap: var(--gap); padding: var(--gap); background: var(--soot); grid-template-columns: repeat(2, 1fr); }
@media (min-width: 960px) { .dh-foot { grid-template-columns: repeat(12, 1fr); } }
.dh-tile { display: grid; align-content: space-between; gap: 16px; padding: 24px; border-radius: 10px; background: var(--soot-2); color: var(--light); min-height: 200px; }
.dh-tile__big { font-size: var(--s-lead); line-height: 1.25; }
.dh-tile__small { font-size: var(--s-ui); line-height: 1.5; color: var(--light-soft); max-width: 60ch; }
.dh-tile--about { grid-column: 1 / -1; }
.dh-tile--nav { align-content: start; gap: 8px; }
.dh-tile--nav a, .dh-tile--mail a { color: var(--light); font-size: var(--s-body); }
.dh-tile--nav a:hover, .dh-tile--mail a:hover { color: var(--accent); }
.dh-tile--mail a { font-size: var(--s-lead); overflow-wrap: anywhere; }
.dh-tile__clock { font: 500 var(--s-sect)/1 'Clash Grotesk', var(--vp-font-family-base); font-variant-numeric: tabular-nums; }
.dh-tile--mark { grid-column: 1 / -1; min-height: 220px; place-items: center; background: var(--paper); color: var(--ink); }
.dh-tile--mark span { font: 500 clamp(52px, 9.5vw, 156px)/0.9 'Clash Grotesk', var(--vp-font-family-base); letter-spacing: -0.04em; white-space: nowrap; }
.dh-tile--go { grid-column: 1 / -1; background: var(--accent); color: var(--accent-ink); font: 500 var(--s-head)/1.1 'Clash Grotesk', var(--vp-font-family-base); transition: background-color 0.2s; }
.dh-tile--go:hover { background: var(--accent-hover); }
.dh-tile--go svg { justify-self: end; transition: transform 0.3s cubic-bezier(0.2, 0.7, 0.2, 1); }
.dh-tile--go:hover svg { transform: translate(4px, -4px); }
@media (min-width: 960px) {
  .dh-tile { min-height: 220px; }
  .dh-tile--about { grid-column: 1 / 5; }
  .dh-tile--nav { grid-column: 5 / 8; }
  .dh-tile--time { grid-column: 8 / 10; }
  .dh-tile--mail { grid-column: 10 / 13; }
  .dh-tile--mark { grid-column: 1 / 9; min-height: 320px; }
  .dh-tile--go { grid-column: 9 / 13; min-height: 320px; }
}
@media (max-width: 520px) {
  .dh-days { grid-template-columns: repeat(4, minmax(0, 1fr)); }
  .dh-slots { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .dh-results ul { grid-template-columns: repeat(2, minmax(0, 1fr)); }
  .dh-tile--mark span { font-size: 44px; white-space: normal; text-align: center; }
}
</style>
