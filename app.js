const AUD_JPY_RATE = 110.04;

const products = [
  {
    id: "m6a-30th-celebration",
    category: "pokemon",
    title: 'Pokemon Card Game MEGA Expansion Pack "30th CELEBRATION" Box',
    series: "MEGA · M6a",
    release: "16 Sep 2026",
    releaseOrder: 20260916,
    jpy: 26000,
    tag: "Latest",
    image: "https://cdn.snkrdunk.com/upload_bg_removed/9e6e18b1-ace5-4782-b975-81a17803faee.webp?size=l",
    source: "https://snkrdunk.com/apparels/881421"
  },
  {
    id: "m6-storm-emeralda",
    category: "pokemon",
    title: 'Pokemon Card Game MEGA Expansion Pack "Storm Emeralda" Box',
    series: "MEGA · M6",
    release: "31 Jul 2026",
    releaseOrder: 20260731,
    jpy: 10500,
    tag: "Popular",
    image: "https://cdn.snkrdunk.com/upload_bg_removed/c08dfabc-735b-4487-8231-06f565c5014c.webp?size=l",
    source: "https://snkrdunk.com/apparels/846048"
  },
  {
    id: "m5-abyss-eye",
    category: "pokemon",
    title: 'Pokemon Card Game MEGA Expansion Pack "Abyss Eye" Box',
    series: "MEGA · M5",
    release: "22 May 2026",
    releaseOrder: 20260522,
    jpy: 8340,
    tag: "Value pick",
    image: "https://cdn.snkrdunk.com/upload_bg_removed/08bd4531-8f88-451c-a952-78f999adc4d3.webp?size=l",
    source: "https://snkrdunk.com/apparels/806644"
  },
  {
    id: "m4-ninja-spinner",
    category: "pokemon",
    title: 'Pokemon Card Game MEGA Expansion Pack "Ninja Spinner" Box',
    series: "MEGA · M4",
    release: "13 Mar 2026",
    releaseOrder: 20260313,
    jpy: 8500,
    tag: "Collector pick",
    image: "https://cdn.snkrdunk.com/upload_bg_removed/c3829d36-641e-4dc2-84ba-4badcc376267.webp?size=l",
    source: "https://snkrdunk.com/apparels/762693"
  },
  {
    id: "m2a-mega-dream-ex",
    category: "pokemon",
    title: 'Pokemon Card Game MEGA High Class Pack "MEGA Dream ex" Box',
    series: "MEGA · M2a",
    release: "28 Nov 2025",
    releaseOrder: 20251128,
    jpy: 12980,
    tag: "High class",
    image: "https://cdn.snkrdunk.com/upload_bg_removed/fdd5a167-a53a-4b9f-9a3d-0864d311e378.webp?size=l",
    source: "https://snkrdunk.com/apparels/721913"
  },
  {
    id: "m2-inferno-x",
    category: "pokemon",
    title: 'Pokemon Card Game MEGA Expansion Pack "Inferno X" Box',
    series: "MEGA · M2",
    release: "26 Sep 2025",
    releaseOrder: 20250926,
    jpy: 17000,
    tag: "Charizard set",
    image: "https://cdn.snkrdunk.com/upload_bg_removed/a8d20d1e-64b7-448e-8e4c-4cc51583499a.webp?size=l",
    source: "https://snkrdunk.com/apparels/687430"
  },
  {
    id: "op13-carrying-on-his-will",
    category: "onepiece",
    title: 'ONE PIECE Card Game Booster Pack "CARRYING ON HIS WILL" Box',
    series: "ONE PIECE · OP-13",
    release: "23 Aug 2025",
    releaseOrder: 20250823,
    jpy: 17999,
    tag: "Popular",
    image: "https://cdn.snkrdunk.com/upload_bg_removed/9b92497a-a7d1-4027-80f6-93efad7eebdc.webp?size=l",
    source: "https://snkrdunk.com/apparels/548909"
  },
  {
    id: "op14-azure-seas-seven",
    category: "onepiece",
    title: 'ONE PIECE Card Game Booster Pack "THE AZURE SEA\'S SEVEN" Box',
    series: "ONE PIECE · OP-14",
    release: "22 Nov 2025",
    releaseOrder: 20251122,
    jpy: 8500,
    tag: "New generation",
    image: "https://cdn.snkrdunk.com/upload_bg_removed/ad98e7cd-0028-4261-a22f-9c054c55fdfa.webp?size=l",
    source: "https://snkrdunk.com/apparels/725356"
  },
  {
    id: "eb04-egghead-crisis",
    category: "onepiece",
    title: 'ONE PIECE Card Game Extra Booster "EGGHEAD CRISIS" Box',
    series: "ONE PIECE · EB-04",
    release: "31 Jan 2026",
    releaseOrder: 20260131,
    jpy: 12250,
    tag: "Extra booster",
    image: "https://cdn.snkrdunk.com/upload_bg_removed/7b8afbf6-b2f4-4a6b-a7f8-da83d318a039.webp?size=l",
    source: "https://snkrdunk.com/apparels/751287"
  },
  {
    id: "op11-fist-divine-speed",
    category: "onepiece",
    title: 'ONE PIECE Card Game Booster Pack "A Fist of Divine Speed" Box',
    series: "ONE PIECE · OP-11",
    release: "1 Mar 2025",
    releaseOrder: 20250301,
    jpy: 30690,
    tag: "Collector pick",
    image: "https://cdn.snkrdunk.com/upload_bg_removed/e8e3aea2-824f-4a46-9137-267f5ef2f0eb.webp?size=l",
    source: "https://snkrdunk.com/apparels/498158"
  },
  {
    id: "yac1-original-artwork",
    category: "yugioh",
    title: 'Yu-Gi-Oh OCG Duel Monsters Special Pack "ORIGINAL ARTWORK COLLECTION" JP Edition Box',
    series: "Yu-Gi-Oh! OCG · YAC1",
    release: "26 Sep 2026",
    releaseOrder: 20260926,
    jpy: 8499,
    tag: "Latest",
    image: "https://cdn.snkrdunk.com/upload_bg_removed/1018172e-bfe3-4bd6-b05a-a849e00c07eb.webp?size=l",
    source: "https://snkrdunk.com/apparels/886014"
  },
  {
    id: "dbgv-glorious-victors",
    category: "yugioh",
    title: 'Yu-Gi-Oh OCG Duel Monsters Concept Pack "Deck-Build Pack: Glorious Victors" JP Edition Box',
    series: "Yu-Gi-Oh! OCG · DBGV",
    release: "5 Sep 2026",
    releaseOrder: 20260905,
    jpy: 2999,
    tag: "Value pick",
    image: "https://cdn.snkrdunk.com/upload_bg_removed/6abfdf99-039b-43d4-9575-b36d7782710f.webp?size=l",
    source: "https://snkrdunk.com/apparels/881242"
  },
  {
    id: "26lp-world-championship",
    category: "yugioh",
    title: 'Yu-Gi-Oh OCG Duel Monsters LIMITED PACK "WORLD CHAMPIONSHIP 2026" JP Edition Box',
    series: "Yu-Gi-Oh! OCG · 26LP",
    release: "29 Aug 2026",
    releaseOrder: 20260829,
    jpy: 4000,
    tag: "Limited pack",
    image: "https://cdn.snkrdunk.com/upload_bg_removed/870c6b55-3f5c-4f39-b08e-5b557c4cdd7b.webp?size=l",
    source: "https://snkrdunk.com/apparels/880998"
  },
  {
    id: "lpg2-ra-yellow",
    category: "yugioh",
    title: 'Yu-Gi-Oh OCG Duel Monsters LIMITED PACK GX "Ra Yellow" JP Edition Box',
    series: "Yu-Gi-Oh! OCG · LPG2",
    release: "25 Jul 2026",
    releaseOrder: 20260725,
    jpy: 3200,
    tag: "GX series",
    image: "https://cdn.snkrdunk.com/upload_bg_removed/9dad378f-8228-4766-8313-c7de283dbc5b.webp?size=l",
    source: "https://snkrdunk.com/apparels/847245"
  }
];

const categoryNames = { all: "All booster boxes", pokemon: "Pokémon boxes", onepiece: "ONE PIECE boxes", yugioh: "Yu-Gi-Oh! boxes" };
const state = { query: "", sort: "featured", category: "all", cart: {} };

const aud = (jpy) => Math.round(jpy / AUD_JPY_RATE);
const audLabel = (value) => new Intl.NumberFormat("en-AU", { style: "currency", currency: "AUD", maximumFractionDigits: 0 }).format(value);
const jpyLabel = (value) => new Intl.NumberFormat("ja-JP", { style: "currency", currency: "JPY", maximumFractionDigits: 0 }).format(value);

const productGrid = document.getElementById("productGrid");
const searchInput = document.getElementById("searchInput");
const sortSelect = document.getElementById("sortSelect");
const categoryTabs = document.getElementById("categoryTabs");
const catalogTitle = document.getElementById("catalogTitle");
const cartButton = document.getElementById("cartButton");
const cartPanel = document.getElementById("cartPanel");
const cartScrim = document.getElementById("cartScrim");
const closeCartButton = document.getElementById("closeCart");
const cartItems = document.getElementById("cartItems");
const cartCount = document.getElementById("cartCount");
const cartTotal = document.getElementById("cartTotal");

function productCard(product) {
  return `
    <article class="product-card">
      <div class="product-card__image">
        <span class="product-card__tag">${product.tag}</span>
        <img src="${product.image}" alt="${product.title}" width="480" height="420" loading="lazy" decoding="async">
      </div>
      <div class="product-card__content">
        <p class="product-card__series">${product.series}</p>
        <h3>${product.title}</h3>
        <div class="product-card__meta"><span>Japanese box</span><span>${product.release}</span></div>
        <div class="product-card__price-row">
          <div class="product-card__price">
            <strong>${audLabel(aud(product.jpy))}</strong>
            <small>${jpyLabel(product.jpy)} per box</small>
          </div>
          <button class="add-button" type="button" data-add="${product.id}" aria-label="Add ${product.title} to cart">+</button>
        </div>
        <a class="product-card__source" href="${product.source}" target="_blank" rel="noopener noreferrer">Price and image source: SNKRDUNK</a>
      </div>
    </article>`;
}

function renderProducts() {
  const query = state.query.trim().toLowerCase();
  const filtered = products.filter((product) =>
    (state.category === "all" || product.category === state.category) &&
    `${product.title} ${product.series}`.toLowerCase().includes(query)
  );
  if (state.sort === "newest") filtered.sort((a, b) => b.releaseOrder - a.releaseOrder);
  if (state.sort === "price-low") filtered.sort((a, b) => a.jpy - b.jpy);
  if (state.sort === "price-high") filtered.sort((a, b) => b.jpy - a.jpy);
  productGrid.innerHTML = filtered.length ? filtered.map(productCard).join("") : '<div class="empty-state">No boxes match your search.</div>';
}

function renderCart() {
  const entries = Object.entries(state.cart).filter(([, quantity]) => quantity > 0);
  const count = entries.reduce((sum, [, quantity]) => sum + quantity, 0);
  const total = entries.reduce((sum, [id, quantity]) => {
    const product = products.find((item) => item.id === id);
    return sum + (product ? aud(product.jpy) * quantity : 0);
  }, 0);

  cartCount.textContent = count;
  cartTotal.textContent = audLabel(total);
  cartItems.innerHTML = entries.length ? entries.map(([id, quantity]) => {
    const product = products.find((item) => item.id === id);
    return `
      <div class="cart-item">
        <img src="${product.image}" alt="" width="74" height="74">
        <div><strong>${product.title}</strong><span>${audLabel(aud(product.jpy))} × ${quantity}</span></div>
        <button type="button" data-remove="${id}">Remove</button>
      </div>`;
  }).join("") : '<div class="cart-empty">Your cart is empty.<br>Add a box to get started.</div>';
}

function openCart() {
  cartPanel.classList.add("open");
  cartPanel.setAttribute("aria-hidden", "false");
  cartButton.setAttribute("aria-expanded", "true");
  cartScrim.hidden = false;
  document.body.classList.add("cart-open");
}

function closeCart() {
  cartPanel.classList.remove("open");
  cartPanel.setAttribute("aria-hidden", "true");
  cartButton.setAttribute("aria-expanded", "false");
  cartScrim.hidden = true;
  document.body.classList.remove("cart-open");
}

productGrid.addEventListener("click", (event) => {
  const button = event.target.closest("[data-add]");
  if (!button) return;
  const id = button.dataset.add;
  state.cart[id] = (state.cart[id] || 0) + 1;
  renderCart();
  openCart();
});

cartItems.addEventListener("click", (event) => {
  const button = event.target.closest("[data-remove]");
  if (!button) return;
  delete state.cart[button.dataset.remove];
  renderCart();
});

searchInput.addEventListener("input", () => { state.query = searchInput.value; renderProducts(); });
sortSelect.addEventListener("change", () => { state.sort = sortSelect.value; renderProducts(); });
categoryTabs.addEventListener("click", (event) => {
  const button = event.target.closest("[data-category]");
  if (!button) return;
  state.category = button.dataset.category;
  categoryTabs.querySelectorAll("[data-category]").forEach((tab) => {
    const active = tab === button;
    tab.classList.toggle("active", active);
    tab.setAttribute("aria-selected", String(active));
  });
  catalogTitle.textContent = categoryNames[state.category];
  renderProducts();
});
cartButton.addEventListener("click", openCart);
closeCartButton.addEventListener("click", closeCart);
cartScrim.addEventListener("click", closeCart);
document.addEventListener("keydown", (event) => { if (event.key === "Escape") closeCart(); });

renderProducts();
renderCart();
