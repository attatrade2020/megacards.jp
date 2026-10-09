const AUD_JPY_RATE = 110.04;

let products = [
  {
    id: "m6a-30th-celebration",
    category: "pokemon",
    title: 'Pokemon Card Game MEGA Expansion Pack "30th CELEBRATION" Box',
    series: "MEGA · M6a",
    release: "16 Sep 2026",
    releaseOrder: 20260916,
    jpy: 26000,
    tag: "Latest",
    image: "assets/products/m6a-30th-celebration.webp",
  },
  {
    id: "m6a-30th-futuristic-box",
    category: "pokemon",
    title: 'Pokemon Card Game MEGA Special Box "30th CELEBRATION FUTURISTIC BOX"',
    series: "MEGA · 30th",
    release: "16 Sep 2026",
    releaseOrder: 20260916,
    jpy: 61500,
    tag: "Special box",
    image: "assets/products/m6a-30th-futuristic-box.webp",
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
    image: "assets/products/m6-storm-emeralda.webp",
  },
  {
    id: "m5-abyss-eye",
    category: "pokemon",
    title: 'Pokemon Card Game MEGA Expansion Pack "Abyss Eye" Box',
    series: "MEGA · M5",
    release: "22 May 2026",
    releaseOrder: 20260522,
    jpy: 8349,
    tag: "Value pick",
    image: "assets/products/m5-abyss-eye.webp",
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
    image: "assets/products/m4-ninja-spinner.webp",
  },
  {
    id: "m3-munikis-zero",
    category: "pokemon",
    title: 'Pokemon Card Game MEGA Expansion Pack "Munikis / Nihil Zero" Box',
    series: "MEGA · M3",
    release: "23 Jan 2026",
    releaseOrder: 20260123,
    jpy: 8090,
    tag: "Popular",
    image: "assets/products/m3-munikis-zero.webp",
  },
  {
    id: "m2a-mega-dream-ex",
    category: "pokemon",
    title: 'Pokemon Card Game MEGA High Class Pack "MEGA Dream ex" Box',
    series: "MEGA · M2a",
    release: "28 Nov 2025",
    releaseOrder: 20251128,
    jpy: 12997,
    tag: "High class",
    image: "assets/products/m2a-mega-dream-ex.webp",
  },
  {
    id: "m2-inferno-x",
    category: "pokemon",
    title: 'Pokemon Card Game MEGA Expansion Pack "Inferno X" Box',
    series: "MEGA · M2",
    release: "26 Sep 2025",
    releaseOrder: 20250926,
    jpy: 16900,
    tag: "Charizard set",
    image: "assets/products/m2-inferno-x.webp",
  },
  {
    id: "m1l-mega-brave",
    category: "pokemon",
    title: 'Pokemon Card Game MEGA Expansion Pack "Mega Brave" Box',
    series: "MEGA · M1L",
    release: "1 Aug 2025",
    releaseOrder: 20250801,
    jpy: 8300,
    tag: "MEGA series",
    image: "assets/products/m1l-mega-brave.webp",
  },
  {
    id: "m1s-mega-symphonia",
    category: "pokemon",
    title: 'Pokemon Card Game MEGA Expansion Pack "Mega Symphonia" Box',
    series: "MEGA · M1S",
    release: "1 Aug 2025",
    releaseOrder: 20250801,
    jpy: 7800,
    tag: "MEGA series",
    image: "assets/products/m1s-mega-symphonia.webp",
  },
  {
    id: "sv-pokemon-center-tohoku",
    category: "pokemon",
    title: 'Pokemon Card Game Scarlet & Violet Special Box "Pokemon Center Tohoku"',
    series: "Scarlet & Violet · Special Box",
    release: "13 Jun 2025",
    releaseOrder: 20250613,
    jpy: 14000,
    tag: "Special box",
    image: "assets/products/sv-pokemon-center-tohoku.webp",
  },
  {
    id: "sv10-glory-team-rocket",
    category: "pokemon",
    title: 'Pokemon Card Game Scarlet & Violet Expansion Pack "Glory of Team Rocket" Box',
    series: "Scarlet & Violet · SV10",
    release: "18 Apr 2025",
    releaseOrder: 20250418,
    jpy: 21749,
    tag: "Collector pick",
    image: "assets/products/sv10-glory-team-rocket.webp",
  },
  {
    id: "sv8a-terastal-festival-ex",
    category: "pokemon",
    title: 'Pokemon Card Game Scarlet & Violet High Class Pack "Terastal Festival ex" Box',
    series: "Scarlet & Violet · SV8a",
    release: "6 Dec 2024",
    releaseOrder: 20241206,
    jpy: 18300,
    tag: "High class",
    image: "assets/products/sv8a-terastal-festival-ex.webp",
  },
  {
    id: "sv4a-shiny-treasure-ex",
    category: "pokemon",
    title: 'Pokemon Card Game Scarlet & Violet High Class Pack "Shiny Treasure ex" Box',
    series: "Scarlet & Violet · SV4a",
    release: "1 Dec 2023",
    releaseOrder: 20231201,
    jpy: 16800,
    tag: "High class",
    image: "assets/products/sv4a-shiny-treasure-ex.webp",
  },
  {
    id: "sv2a-pokemon-card-151",
    category: "pokemon",
    title: 'Pokemon Card Game Scarlet & Violet Enhanced Expansion Pack "pokemon card 151" Box',
    series: "Scarlet & Violet · SV2a",
    release: "16 Jun 2023",
    releaseOrder: 20230616,
    jpy: 46280,
    tag: "Collector pick",
    image: "assets/products/sv2a-pokemon-card-151.webp",
  },
  {
    id: "25th-golden-box",
    category: "pokemon",
    title: "Pokémon Card Game 25th Anniversary Golden Box",
    series: "Sword & Shield · 25th",
    release: "Winter 2021",
    releaseOrder: 20211201,
    jpy: 209999,
    tag: "Premium box",
    image: "assets/products/25th-golden-box.webp",
  },
  {
    id: "s8a-25th-anniversary-collection",
    category: "pokemon",
    title: "Pokémon Card Game Sword & Shield Expansion Pack 25th Anniversary Collection Box",
    series: "Sword & Shield · S8a",
    release: "22 Oct 2021",
    releaseOrder: 20211022,
    jpy: 56000,
    tag: "Anniversary",
    image: "assets/products/s8a-25th-anniversary-collection.webp",
  },
  {
    id: "s7r-blue-sky-stream",
    category: "pokemon",
    title: "Pokémon Card Game Sword & Shield Expansion Pack Blue Sky Stream Box",
    series: "Sword & Shield · S7R",
    release: "9 Jul 2021",
    releaseOrder: 20210709,
    jpy: 249999,
    tag: "Collector archive",
    image: "assets/products/s7r-blue-sky-stream.webp",
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
    image: "assets/products/op13-carrying-on-his-will.webp",
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
    image: "assets/products/op14-azure-seas-seven.webp",
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
    image: "assets/products/eb04-egghead-crisis.webp",
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
    image: "assets/products/op11-fist-divine-speed.webp",
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
    image: "assets/products/yac1-original-artwork.webp",
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
    image: "assets/products/dbgv-glorious-victors.webp",
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
    image: "assets/products/26lp-world-championship.webp",
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
    image: "assets/products/lpg2-ra-yellow.webp",
  }
];

const categoryNames = { all: "All Japanese TCG boxes", pokemon: "Pokémon boxes", onepiece: "ONE PIECE boxes", yugioh: "Yu-Gi-Oh! boxes" };
const rankedProductIds = [
  "m6-storm-emeralda",
  "m6a-30th-celebration",
  "m2a-mega-dream-ex",
  "m2-inferno-x"
];
const fallbackShippingProfiles = {
  packaging_weight_g: 250,
  padding_cm: 4,
  products: {
    "m6a-30th-celebration": { weight_g: 450, width_cm: 14, depth_cm: 14, height_cm: 5 },
    "m6a-30th-futuristic-box": { weight_g: 1100, width_cm: 36, depth_cm: 27, height_cm: 8 },
    "m6-storm-emeralda": { weight_g: 350, width_cm: 14, depth_cm: 14, height_cm: 4 },
    "m5-abyss-eye": { weight_g: 350, width_cm: 14, depth_cm: 14, height_cm: 4 },
    "m4-ninja-spinner": { weight_g: 350, width_cm: 14, depth_cm: 14, height_cm: 4 },
    "m3-munikis-zero": { weight_g: 350, width_cm: 14, depth_cm: 14, height_cm: 4 },
    "m2a-mega-dream-ex": { weight_g: 300, width_cm: 14, depth_cm: 8, height_cm: 7 },
    "m2-inferno-x": { weight_g: 350, width_cm: 14, depth_cm: 14, height_cm: 4 },
    "m1l-mega-brave": { weight_g: 350, width_cm: 14, depth_cm: 14, height_cm: 4 },
    "m1s-mega-symphonia": { weight_g: 350, width_cm: 14, depth_cm: 14, height_cm: 4 },
    "sv-pokemon-center-tohoku": { weight_g: 850, width_cm: 30, depth_cm: 24, height_cm: 8 },
    "sv10-glory-team-rocket": { weight_g: 350, width_cm: 14, depth_cm: 14, height_cm: 4 },
    "sv8a-terastal-festival-ex": { weight_g: 300, width_cm: 14, depth_cm: 8, height_cm: 7 },
    "sv4a-shiny-treasure-ex": { weight_g: 300, width_cm: 14, depth_cm: 8, height_cm: 7 },
    "sv2a-pokemon-card-151": { weight_g: 350, width_cm: 14, depth_cm: 14, height_cm: 4 },
    "25th-golden-box": { weight_g: 1500, width_cm: 45, depth_cm: 32, height_cm: 12 },
    "s8a-25th-anniversary-collection": { weight_g: 350, width_cm: 14, depth_cm: 14, height_cm: 4 },
    "s7r-blue-sky-stream": { weight_g: 350, width_cm: 14, depth_cm: 14, height_cm: 4 },
    "op13-carrying-on-his-will": { weight_g: 420, width_cm: 14, depth_cm: 14, height_cm: 5 },
    "op14-azure-seas-seven": { weight_g: 420, width_cm: 14, depth_cm: 14, height_cm: 5 },
    "eb04-egghead-crisis": { weight_g: 420, width_cm: 14, depth_cm: 14, height_cm: 5 },
    "op11-fist-divine-speed": { weight_g: 420, width_cm: 14, depth_cm: 14, height_cm: 5 },
    "yac1-original-artwork": { weight_g: 300, width_cm: 14, depth_cm: 8, height_cm: 6 },
    "dbgv-glorious-victors": { weight_g: 220, width_cm: 14, depth_cm: 8, height_cm: 4 },
    "26lp-world-championship": { weight_g: 220, width_cm: 14, depth_cm: 8, height_cm: 4 },
    "lpg2-ra-yellow": { weight_g: 220, width_cm: 14, depth_cm: 8, height_cm: 4 }
  }
};
const state = { query: "", sort: "featured", category: "all", cart: {}, shippingProfiles: fallbackShippingProfiles, shippingQuote: null };

const aud = (jpy) => Math.round(jpy / AUD_JPY_RATE);
const audLabel = (value) => `A$${new Intl.NumberFormat("en-AU", { maximumFractionDigits: 0 }).format(value)}`;
const jpyLabel = (value) => new Intl.NumberFormat("ja-JP", { style: "currency", currency: "JPY", maximumFractionDigits: 0 }).format(value);

const productGrid = document.getElementById("productGrid");
const rankingGrid = document.getElementById("rankingGrid");
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
const customsIndicator = document.getElementById("customsIndicator");
const customsTitle = document.getElementById("customsTitle");
const customsMessage = document.getElementById("customsMessage");
const shippingPostcode = document.getElementById("shippingPostcode");
const shippingState = document.getElementById("shippingState");
const calculateShippingButton = document.getElementById("calculateShipping");
const shippingStatus = document.getElementById("shippingStatus");
const shippingRates = document.getElementById("shippingRates");
const shippingTotalRow = document.getElementById("shippingTotalRow");
const shippingTotal = document.getElementById("shippingTotal");
const grandTotalRow = document.getElementById("grandTotalRow");
const grandTotal = document.getElementById("grandTotal");
const checkoutButton = document.getElementById("checkoutButton");
const checkoutModal = document.getElementById("checkoutModal");
const closeCheckoutButton = document.getElementById("closeCheckout");
const checkoutForm = document.getElementById("checkoutForm");
const checkoutSuccess = document.getElementById("checkoutSuccess");
const checkoutItems = document.getElementById("checkoutItems");
const checkoutSubtotal = document.getElementById("checkoutSubtotal");
const checkoutShipping = document.getElementById("checkoutShipping");
const checkoutShippingLabel = document.getElementById("checkoutShippingLabel");
const checkoutFeeRow = document.getElementById("checkoutFeeRow");
const checkoutFee = document.getElementById("checkoutFee");
const checkoutGrandTotal = document.getElementById("checkoutGrandTotal");
const checkoutAudEstimate = document.getElementById("checkoutAudEstimate");
const checkoutStatus = document.getElementById("checkoutStatus");
const submitOrderButton = document.getElementById("submitOrder");
const checkoutSuccessMessage = document.getElementById("checkoutSuccessMessage");
const paymentLink = document.getElementById("paymentLink");
const finishCheckoutButton = document.getElementById("finishCheckout");
const checkoutState = document.getElementById("checkoutState");
const checkoutPostcode = document.getElementById("checkoutPostcode");
const hero = document.querySelector(".hero");
const heroEyebrow = document.getElementById("heroEyebrow");
const heroTitle = document.getElementById("heroTitle");
const heroLead = document.getElementById("heroLead");
const heroPrimaryAction = document.getElementById("heroPrimaryAction");
const heroFrontImage = document.getElementById("heroFrontImage");
const heroBackImage = document.getElementById("heroBackImage");
const heroPeekImage = document.getElementById("heroPeekImage");
const heroBadge = document.getElementById("heroBadge");
const heroProductName = document.getElementById("heroProductName");
const heroProductPrice = document.getElementById("heroProductPrice");
const heroSliderToggle = document.getElementById("heroSliderToggle");
const heroSliderDots = document.getElementById("heroSliderDots");
const heroSliderStatus = document.getElementById("heroSliderStatus");
const heroSliderProgress = document.getElementById("heroSliderProgress");
const openRegisterButton = document.getElementById("openRegister");
const heroRegisterAction = document.getElementById("heroRegisterAction");
const registerModal = document.getElementById("registerModal");
const closeRegisterButton = document.getElementById("closeRegister");
const registerForm = document.getElementById("registerForm");
const registerFormView = document.getElementById("registerFormView");
const registerSuccess = document.getElementById("registerSuccess");
const registerStatus = document.getElementById("registerStatus");
const registerSubmit = document.getElementById("registerSubmit");
const registerSuccessMessage = document.getElementById("registerSuccessMessage");
const finishRegisterButton = document.getElementById("finishRegister");

const heroSlides = [
  {
    category: "pokemon",
    eyebrow: "Japanese Pokémon · shipped from Japan",
    title: "Japan’s latest Pokémon boxes, without the guesswork.",
    lead: "Discover current Japanese Pokémon releases with clear price estimates and tracked international delivery direct from Japan.",
    action: "Shop Pokémon boxes",
    badge: "Latest Pokémon",
    front: "m6a-30th-celebration",
    back: "m6-storm-emeralda",
    peek: "m5-abyss-eye",
    name: "30th Celebration"
  },
  {
    category: "onepiece",
    eyebrow: "ONE PIECE Card Game · Japanese edition",
    title: "Build your ONE PIECE collection from Japan.",
    lead: "Shop Japanese ONE PIECE booster boxes, packed in Japan with live tracked shipping options at checkout.",
    action: "Shop ONE PIECE boxes",
    badge: "ONE PIECE pick",
    front: "op13-carrying-on-his-will",
    back: "op14-azure-seas-seven",
    peek: "eb04-egghead-crisis",
    name: "Carrying On His Will"
  },
  {
    category: "yugioh",
    eyebrow: "Yu-Gi-Oh! OCG · direct from Japan",
    title: "Japanese Yu-Gi-Oh! OCG boxes for collectors.",
    lead: "Explore Japanese OCG releases with clear price estimates, secure checkout, and tracked shipping from Japan.",
    action: "Shop Yu-Gi-Oh! boxes",
    badge: "Yu-Gi-Oh! OCG",
    front: "yac1-original-artwork",
    back: "26lp-world-championship",
    peek: "dbgv-glorious-victors",
    name: "Original Artwork Collection"
  },
  {
    category: "pokemon",
    eyebrow: "Japanese Pokémon · collector rotation",
    title: "More Japanese Pokémon sets, always in motion.",
    lead: "Move through high-class and expansion boxes, compare clear price estimates, and build your shipment from Japan.",
    action: "Explore Pokémon boxes",
    badge: "Collector rotation",
    front: "m2-inferno-x",
    back: "m2a-mega-dream-ex",
    peek: "m4-ninja-spinner",
    name: "Inferno X"
  },
  {
    category: "onepiece",
    eyebrow: "ONE PIECE Card Game · rotating picks",
    title: "Fresh ONE PIECE picks from the Japanese lineup.",
    lead: "Browse booster and extra-booster boxes with a live view of the collection and tracked delivery choices for your postcode.",
    action: "Explore ONE PIECE boxes",
    badge: "Extra booster pick",
    front: "eb04-egghead-crisis",
    back: "op11-fist-divine-speed",
    peek: "op14-azure-seas-seven",
    name: "Egghead Crisis"
  },
  {
    category: "yugioh",
    eyebrow: "Yu-Gi-Oh! OCG · rotating picks",
    title: "Limited and value OCG boxes, direct from Japan.",
    lead: "Discover current Japanese Yu-Gi-Oh! releases as the lineup rotates, with clear price estimates and tracked express shipping.",
    action: "Explore Yu-Gi-Oh! boxes",
    badge: "Value OCG pick",
    front: "dbgv-glorious-victors",
    back: "lpg2-ra-yellow",
    peek: "26lp-world-championship",
    name: "Glorious Victors"
  }
];
let heroSlideIndex = 0;
let heroSlideTimer = null;
let heroPaused = window.matchMedia("(prefers-reduced-motion: reduce)").matches;

function heroProduct(id) {
  return products.find((product) => product.id === id);
}

function renderHeroSlide(index, animate = true) {
  heroSlideIndex = (index + heroSlides.length) % heroSlides.length;
  const slide = heroSlides[heroSlideIndex];
  const front = heroProduct(slide.front);
  const back = heroProduct(slide.back);
  const peek = heroProduct(slide.peek);
  const apply = () => {
    hero.dataset.heroTheme = slide.category;
    heroEyebrow.textContent = slide.eyebrow;
    heroTitle.textContent = slide.title;
    heroLead.textContent = slide.lead;
    heroPrimaryAction.textContent = slide.action;
    heroPrimaryAction.dataset.category = slide.category;
    heroBadge.textContent = slide.badge;
    heroProductName.textContent = slide.name;
    if (front) {
      heroFrontImage.src = front.image;
      heroFrontImage.alt = `${front.title} booster box`;
      heroProductPrice.textContent = audLabel(aud(front.jpy));
    }
    if (back) {
      heroBackImage.src = back.image;
      heroBackImage.alt = `${back.title} booster box`;
    }
    if (peek) heroPeekImage.src = peek.image;
    heroSliderDots.querySelectorAll("button").forEach((dot, dotIndex) => {
      const active = dotIndex === heroSlideIndex;
      dot.classList.toggle("active", active);
      dot.setAttribute("aria-current", active ? "true" : "false");
    });
    heroSliderStatus.textContent = `${heroSlideIndex + 1} / ${heroSlides.length}`;
    heroSliderProgress.style.animation = "none";
    void heroSliderProgress.offsetWidth;
    heroSliderProgress.style.animation = "";
    hero.classList.remove("is-changing");
  };
  if (animate) {
    hero.classList.add("is-changing");
    window.setTimeout(apply, 220);
  } else {
    apply();
  }
}

function restartHeroTimer() {
  if (heroSlideTimer) window.clearInterval(heroSlideTimer);
  heroSlideTimer = null;
  if (!heroPaused) heroSlideTimer = window.setInterval(() => renderHeroSlide(heroSlideIndex + 1), 5200);
  hero.classList.toggle("is-paused", heroPaused);
  heroSliderToggle.textContent = heroPaused ? "Play" : "Pause";
  heroSliderToggle.setAttribute("aria-label", heroPaused ? "Play hero slides" : "Pause hero slides");
}

function initialiseHeroSlider() {
  heroSliderDots.innerHTML = heroSlides.map((slide, index) => `<button class="hero-slider__dot${index === 0 ? " active" : ""}" type="button" data-hero-slide="${index}" aria-label="Show ${slide.category} feature" ${index === 0 ? 'aria-current="true"' : 'aria-current="false"'}></button>`).join("");
  renderHeroSlide(0, false);
  restartHeroTimer();
}

function productCard(product) {
  const soldOut = Number(product.stock ?? 1) <= 0;
  return `
    <article class="product-card">
      <div class="product-card__image">
        <span class="product-card__tag">${soldOut ? "Sold out" : escapeHtml(product.tag)}</span>
        <img src="${product.image}" alt="${escapeHtml(product.title)}" width="480" height="420" loading="lazy" decoding="async">
      </div>
      <div class="product-card__content">
        <p class="product-card__series">${escapeHtml(product.series)}</p>
        <h3>${escapeHtml(product.title)}</h3>
        <div class="product-card__meta"><span>Japanese box</span><span>${escapeHtml(product.release)}</span></div>
        <div class="product-card__price-row">
          <div class="product-card__price">
            <strong>${audLabel(aud(product.jpy))}</strong>
          </div>
          <div class="product-card__actions">
            <label class="product-card__quantity"><span>Qty</span><input type="number" min="1" max="99" step="1" value="1" inputmode="numeric" data-quantity="${product.id}" aria-label="Quantity for ${escapeHtml(product.title)}" ${soldOut ? "disabled" : ""}></label>
            <button class="add-button" type="button" data-add="${product.id}" aria-label="Add selected quantity of ${escapeHtml(product.title)} to cart" ${soldOut ? "disabled" : ""}>${soldOut ? "×" : "+"}</button>
          </div>
        </div>
      </div>
    </article>`;
}

function rankingCard(product, index) {
  const soldOut = Number(product.stock ?? 1) <= 0;
  return `
    <article class="ranking-card ranking-card--${index + 1}">
      <div class="ranking-card__image">
        <span class="ranking-card__position" aria-label="Rank ${index + 1}">${String(index + 1).padStart(2, "0")}</span>
        <img src="${product.image}" alt="${escapeHtml(product.title)}" width="440" height="380" loading="eager" decoding="async">
      </div>
      <div class="ranking-card__content">
        <p>${escapeHtml(product.series)}</p>
        <h3>${escapeHtml(product.title)}</h3>
        <div class="ranking-card__footer">
          <div><small>Price per box</small><strong>${audLabel(aud(product.jpy))}</strong></div>
          <button type="button" data-ranking-add="${product.id}" aria-label="Add one ${escapeHtml(product.title)} to cart" ${soldOut ? "disabled" : ""}>${soldOut ? "Sold out" : "Add to cart"}</button>
        </div>
      </div>
    </article>`;
}

function renderRanking() {
  const rankedProducts = rankedProductIds
    .map((id) => products.find((product) => product.id === id))
    .filter(Boolean);
  rankingGrid.innerHTML = rankedProducts.map(rankingCard).join("");
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

function cartSubtotalJpy() {
  return Object.entries(state.cart).reduce((sum, [id, quantity]) => {
    const product = products.find((item) => item.id === id);
    return sum + (product ? product.jpy * quantity : 0);
  }, 0);
}

function cartSubtotalAud() {
  return aud(cartSubtotalJpy());
}

function renderCustomsEstimate() {
  const itemCount = Object.values(state.cart).reduce((sum, quantity) => sum + quantity, 0);
  if (!itemCount) {
    customsIndicator.dataset.status = "empty";
    customsTitle.textContent = "Australian customs estimate";
    customsMessage.textContent = "Add a box to see whether border charges are likely.";
    return;
  }

  const productValueAud = cartSubtotalAud();
  if (productValueAud <= 1000) {
    customsIndicator.dataset.status = "low";
    customsTitle.textContent = "Customs duty generally not charged";
    customsMessage.textContent = `${audLabel(productValueAud)} product value is within the ABF low-value threshold of A$1,000. GST may still apply at sale; ABF makes the final assessment.`;
  } else {
    customsIndicator.dataset.status = "high";
    customsTitle.textContent = "Import charges may apply";
    customsMessage.textContent = `${audLabel(productValueAud)} product value is above A$1,000. An Import Declaration is generally required; JAEPA may reduce duty for qualifying goods. ABF makes the final assessment.`;
  }
}

function resetShippingQuote(message = "") {
  state.shippingQuote = null;
  shippingRates.innerHTML = "";
  shippingTotalRow.hidden = true;
  grandTotalRow.hidden = true;
  shippingStatus.classList.remove("error");
  shippingStatus.textContent = message;
  checkoutButton.disabled = true;
}

function buildShipment() {
  const entries = Object.entries(state.cart).filter(([, quantity]) => quantity > 0);
  const padding = Number(state.shippingProfiles.padding_cm) || 4;
  let weight = Number(state.shippingProfiles.packaging_weight_g) || 250;
  let width = 1;
  let depth = 1;
  let stackedHeight = 0;
  const items = [];

  entries.forEach(([id, quantity]) => {
    const product = products.find((item) => item.id === id);
    const profile = state.shippingProfiles.products[id] || fallbackShippingProfiles.products[id] || {
      weight_g: Number(product?.weight_g) || 450,
      width_cm: Number(product?.width_cm) || 14,
      depth_cm: Number(product?.depth_cm) || 14,
      height_cm: Number(product?.height_cm) || 5,
    };
    if (!product || !profile) return;
    weight += profile.weight_g * quantity;
    width = Math.max(width, profile.width_cm);
    depth = Math.max(depth, profile.depth_cm);
    stackedHeight += profile.height_cm * quantity;
    items.push({ name: product.title, price: product.jpy, quantity, weight: profile.weight_g });
  });

  return {
    weight: Math.ceil(weight),
    width: Math.ceil(width + padding),
    depth: Math.ceil(depth + padding),
    height: Math.ceil(stackedHeight + padding),
    items,
    totalValue: entries.reduce((sum, [id, quantity]) => {
      const product = products.find((item) => item.id === id);
      return sum + (product ? product.jpy * quantity : 0);
    }, 0)
  };
}

const carrierLabels = { fedex: "FedEx", japanpost: "Japan Post", ups: "UPS", dhl: "DHL" };
const serviceLabels = {
  fedex_international_priority_express: "International Priority Express",
  fedex_international_priority_eod: "International Priority",
  fedex_international_connect_plus: "International Connect Plus",
  fedex_international_economy: "International Economy",
  japanpost_ems: "EMS",
  japanpost_parcel_air: "International Parcel Air",
  japanpost_parcel_sea: "International Parcel Sea",
  ups_worldwide_express_plus: "Worldwide Express Plus",
  ups_worldwide_express: "Worldwide Express",
  ups_saver: "Worldwide Saver",
  ups_worldwide_expedited: "Worldwide Expedited",
  dhl_express_worldwide: "Express Worldwide"
};

function selectShippingRate(index) {
  if (!state.shippingQuote?.rates[index]) return;
  state.shippingQuote.selected = index;
  const rate = state.shippingQuote.rates[index];
  const shippingAud = aud(rate.price);
  shippingTotal.textContent = audLabel(shippingAud);
  grandTotal.textContent = audLabel(cartSubtotalAud() + shippingAud);
  shippingTotalRow.hidden = false;
  grandTotalRow.hidden = false;
  checkoutButton.disabled = false;
}

function renderShippingRates(rates, shipment) {
  const allowed = rates
    .filter((rate) => Number(rate.price) > 0 && ["dhl", "fedex"].includes(String(rate.carrier).toLowerCase()) && serviceLabels[rate.service])
    .sort((a, b) => Number(a.price) - Number(b.price));
  if (!allowed.length) throw new Error("No DHL or FedEx services are available for this postcode.");
  state.shippingQuote = { rates: allowed, selected: 0 };
  shippingStatus.textContent = `${shipment.weight.toLocaleString()} g · ${shipment.width} × ${shipment.depth} × ${shipment.height} cm packed`;
  shippingRates.innerHTML = allowed.map((rate, index) => `
    <label class="shipping-rate">
      <input type="radio" name="shippingRate" value="${index}" ${index === 0 ? "checked" : ""}>
      <span class="shipping-rate__name"><strong>${carrierLabels[rate.carrier] || rate.carrier}</strong><small>${serviceLabels[rate.service] || rate.service}</small></span>
      <span class="shipping-rate__price">${audLabel(aud(Number(rate.price)))}</span>
    </label>`).join("");
  selectShippingRate(0);
}

async function calculateShipping() {
  const postcode = shippingPostcode.value.trim();
  if (!/^\d{4}$/.test(postcode)) {
    resetShippingQuote();
    shippingStatus.classList.add("error");
    shippingStatus.textContent = "Enter a valid 4-digit postcode.";
    return;
  }
  if (!Object.values(state.cart).some((quantity) => quantity > 0)) {
    resetShippingQuote();
    shippingStatus.classList.add("error");
    shippingStatus.textContent = "Add at least one box before calculating shipping.";
    return;
  }

  const shipment = buildShipment();
  calculateShippingButton.disabled = true;
  calculateShippingButton.textContent = "Checking…";
  resetShippingQuote("Requesting live carrier rates…");
  try {
    const response = await fetch("shipping_rates.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify({
        country: "AU", zip: postcode, state: shippingState.value, city: "Australia", address1: "Shipping quote",
        name: "MegaCards customer", phone: "+61000000000", email: "quote@megacards.jp",
        weight: shipment.weight, width: shipment.width, depth: shipment.depth, height: shipment.height,
        items: shipment.items, total_value: shipment.totalValue
      })
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || data.error) throw new Error(data.error || "Unable to retrieve shipping rates.");
    renderShippingRates(data.rates || [], shipment);
  } catch (error) {
    resetShippingQuote();
    shippingStatus.classList.add("error");
    shippingStatus.textContent = error.message || "Unable to retrieve shipping rates.";
  } finally {
    calculateShippingButton.disabled = false;
    calculateShippingButton.textContent = "Calculate";
  }
}

async function loadShippingProfiles() {
  try {
    const response = await fetch("shipping_products.php", { cache: "no-store" });
    if (!response.ok) return;
    const data = await response.json();
    if (data?.products) state.shippingProfiles = data;
  } catch (_) {
    state.shippingProfiles = fallbackShippingProfiles;
  }
}

function selectedPaymentMethod() {
  return checkoutForm.querySelector('input[name="paymentMethod"]:checked')?.value || "wise";
}

function checkoutAmounts() {
  const subtotal = buildShipment().totalValue;
  const selectedRate = state.shippingQuote?.rates[state.shippingQuote.selected];
  const shipping = Number(selectedRate?.price) || 0;
  const fee = selectedPaymentMethod() === "paypal" ? Math.round((subtotal + shipping) * 0.05) : 0;
  return { subtotal, shipping, fee, total: subtotal + shipping + fee };
}

function updateCheckoutSummary() {
  const shipment = buildShipment();
  const rate = state.shippingQuote?.rates[state.shippingQuote.selected];
  const amounts = checkoutAmounts();
  checkoutItems.innerHTML = shipment.items.map((item) => `
    <p class="checkout-summary__item"><span>${item.name} × ${item.quantity}</span><strong>${jpyLabel(item.price * item.quantity)}</strong></p>`).join("");
  checkoutSubtotal.textContent = jpyLabel(amounts.subtotal);
  checkoutShipping.textContent = jpyLabel(amounts.shipping);
  checkoutShippingLabel.textContent = rate ? `${carrierLabels[rate.carrier] || rate.carrier} · ${serviceLabels[rate.service] || rate.service}` : "Shipping";
  checkoutFeeRow.hidden = amounts.fee === 0;
  checkoutFee.textContent = jpyLabel(amounts.fee);
  checkoutGrandTotal.textContent = jpyLabel(amounts.total);
  checkoutAudEstimate.textContent = `Approx. ${audLabel(aud(amounts.total))}`;
  const method = selectedPaymentMethod();
  submitOrderButton.textContent = `Submit order & pay with ${method === "paypal" ? "PayPal" : "Wise"}`;
}

function openCheckout() {
  if (!state.shippingQuote?.rates[state.shippingQuote.selected]) return;
  checkoutState.value = shippingState.value;
  checkoutPostcode.value = shippingPostcode.value;
  checkoutStatus.textContent = "";
  checkoutForm.hidden = false;
  checkoutSuccess.hidden = true;
  updateCheckoutSummary();
  closeCart();
  checkoutModal.showModal();
  document.body.classList.add("checkout-open");
}

function closeCheckout() {
  if (checkoutModal.open) checkoutModal.close();
  document.body.classList.remove("checkout-open");
}

function makeOrderNumber() {
  return String(Math.floor(10000000 + Math.random() * 90000000));
}

async function parseJsonResponse(response, fallbackMessage) {
  const data = await response.json().catch(() => ({}));
  if (!response.ok || data.error) throw new Error(data.error || fallbackMessage);
  return data;
}

async function submitOrder(event) {
  event.preventDefault();
  checkoutStatus.textContent = "";
  if (!checkoutForm.reportValidity()) return;
  if (!state.shippingQuote?.rates[state.shippingQuote.selected]) {
    checkoutStatus.textContent = "Your shipping quote has expired. Return to the cart and calculate it again.";
    return;
  }
  if (checkoutPostcode.value.trim() !== shippingPostcode.value.trim() || checkoutState.value !== shippingState.value) {
    checkoutStatus.textContent = "Postcode or state changed. Close checkout and recalculate shipping for the new destination.";
    return;
  }

  const formData = new FormData(checkoutForm);
  const name = String(formData.get("name") || "").trim();
  const email = String(formData.get("email") || "").trim();
  const phone = String(formData.get("phone") || "").trim();
  const address = {
    line1: String(formData.get("address1") || "").trim(),
    line2: String(formData.get("address2") || "").trim(),
    city: String(formData.get("city") || "").trim(),
    state: checkoutState.value,
    zip: checkoutPostcode.value.trim(),
    country: "AU",
    phone
  };
  const paymentMethod = selectedPaymentMethod();
  const shipment = buildShipment();
  const rate = state.shippingQuote.rates[state.shippingQuote.selected];
  const shippingInfo = { carrier: rate.carrier, service: rate.service, price: Number(rate.price) };
  const amounts = checkoutAmounts();
  const orderNumber = makeOrderNumber();
  const now = new Date();
  const items = shipment.items.map((item) => {
    const product = products.find((entry) => entry.title === item.name);
    return { id: product?.id || "", title: item.name, qty: item.quantity, price_jpy: item.price };
  });
  const payload = {
    action: "order",
    order_number: orderNumber,
    name,
    email,
    country: "AU",
    contact: phone,
    address,
    items,
    subtotal_jpy: amounts.subtotal,
    shipping: shippingInfo,
    payment_method: paymentMethod,
    paypal_fee: amounts.fee,
    total_jpy: amounts.total,
    invoice_currency: "JPY",
    notes: "MegaCards storefront order",
    timestamp: now.toISOString(),
    status: "Pending_Payment"
  };

  submitOrderButton.disabled = true;
  submitOrderButton.textContent = "Creating your order…";
  try {
    const orderResponse = await fetch("api_proxy.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload)
    });
    await parseJsonResponse(orderResponse, "The order service did not respond.");

    let payUrl;
    if (paymentMethod === "paypal") {
      const paypalResponse = await fetch("paypal_invoice.php", {
        method: "POST",
        headers: { "Content-Type": "application/json" },
        body: JSON.stringify({
          action: "create_invoice",
          order_id: orderNumber,
          customer_email: email,
          customer_name: name,
          items: [
            ...items.map((item) => ({ name: item.title, quantity: item.qty, price: item.price_jpy })),
            ...(amounts.fee ? [{ name: "PayPal transaction fee (5%)", quantity: 1, price: amounts.fee }] : [])
          ],
          shipping: amounts.shipping,
          note: `MegaCards.jp Order: ${orderNumber}`
        })
      });
      const paypalData = await parseJsonResponse(paypalResponse, "Could not create the PayPal invoice.");
      if (!paypalData.ok || !paypalData.payment_link) throw new Error(paypalData.error || "Could not create the PayPal payment link.");
      payUrl = paypalData.payment_link;
    } else {
      const description = `${orderNumber} - Valid for 10 minutes`;
      payUrl = `https://wise.com/pay/business/q32cyu?amount=${amounts.total}&currency=JPY&description=${encodeURIComponent(description)}`;
    }

    const expiresAt = new Date(Date.now() + 10 * 60 * 1000);
    const expiryString = expiresAt.toLocaleString("en-AU", { day: "numeric", month: "short", hour: "numeric", minute: "2-digit", timeZoneName: "short" });
    const emailPayload = {
      email,
      name,
      order_number: orderNumber,
      payment_method: paymentMethod,
      country: "AU",
      contact: phone,
      address,
      total_jpy: amounts.total,
      subtotal_jpy: amounts.subtotal,
      shipping_price: amounts.shipping,
      shipping_carrier: rate.carrier,
      shipping_service: rate.service,
      items,
      currency_code: "AUD",
      currency_symbol: "A$",
      currency_rate: 1 / AUD_JPY_RATE,
      paypal_fee: amounts.fee,
      expiry_str: expiryString,
      ...(paymentMethod === "paypal" ? { paypal_pay_url: payUrl } : { wise_pay_url: payUrl })
    };
    fetch("send_order_email.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(emailPayload)
    }).catch(() => {});

    checkoutForm.hidden = true;
    checkoutSuccess.hidden = false;
    checkoutSuccessMessage.textContent = `Order ${orderNumber} is ready. Pay ${jpyLabel(amounts.total)} with ${paymentMethod === "paypal" ? "PayPal" : "Wise"} by ${expiryString}.`;
    paymentLink.href = payUrl;
    paymentLink.textContent = `Pay ${jpyLabel(amounts.total)} with ${paymentMethod === "paypal" ? "PayPal" : "Wise"}`;
  } catch (error) {
    checkoutStatus.textContent = error.message || "We could not create your order. Please try again.";
  } finally {
    submitOrderButton.disabled = false;
    updateCheckoutSummary();
  }
}

function openRegister() {
  registerStatus.textContent = "";
  if (!registerModal.open) registerModal.showModal();
  document.body.classList.add("register-open");
}

function closeRegister() {
  if (registerModal.open) registerModal.close();
  document.body.classList.remove("register-open");
}

async function submitRegistration(event) {
  event.preventDefault();
  registerStatus.textContent = "";
  if (!registerForm.reportValidity()) return;
  const form = new FormData(registerForm);
  const password = String(form.get("password") || "");
  const confirmPassword = String(form.get("confirmPassword") || "");
  if (password !== confirmPassword) {
    registerStatus.textContent = "Passwords do not match.";
    return;
  }
  if (password.length < 8 || !/[A-Z]/.test(password) || !/\d/.test(password)) {
    registerStatus.textContent = "Use at least 8 characters with an uppercase letter and a number.";
    return;
  }

  const payload = {
    name: String(form.get("name") || "").trim(),
    email: String(form.get("email") || "").trim(),
    state: String(form.get("state") || ""),
    password,
    marketing: form.get("marketing") === "on",
    accepted_terms: form.get("acceptedTerms") === "on",
    website: String(form.get("website") || "")
  };
  registerSubmit.disabled = true;
  registerSubmit.textContent = "Creating account…";
  try {
    const response = await fetch("member_register.php", {
      method: "POST",
      headers: { "Content-Type": "application/json" },
      body: JSON.stringify(payload)
    });
    const data = await response.json().catch(() => ({}));
    if (!response.ok || !data.ok) throw new Error(data.error || "Registration could not be completed.");
    registerFormView.hidden = true;
    registerSuccess.hidden = false;
    registerSuccessMessage.textContent = `Thanks, ${payload.name}. Your MegaCards member profile has been created for ${payload.email}.`;
    registerForm.reset();
  } catch (error) {
    registerStatus.textContent = error.message || "Registration could not be completed.";
  } finally {
    registerSubmit.disabled = false;
    registerSubmit.textContent = "Create my account";
  }
}

function renderCart() {
  const entries = Object.entries(state.cart).filter(([, quantity]) => quantity > 0);
  const count = entries.reduce((sum, [, quantity]) => sum + quantity, 0);
  const total = cartSubtotalAud();

  cartCount.textContent = count;
  cartTotal.textContent = audLabel(total);
  renderCustomsEstimate();
  cartItems.innerHTML = entries.length ? entries.map(([id, quantity]) => {
    const product = products.find((item) => item.id === id);
    return `
      <div class="cart-item">
        <img src="${product.image}" alt="" width="74" height="74">
        <div><strong>${product.title}</strong><span>${audLabel(aud(product.jpy))} × ${quantity}</span></div>
        <button type="button" data-remove="${id}">Remove</button>
      </div>`;
  }).join("") : '<div class="cart-empty">Your cart is empty.<br>Add a box to get started.</div>';
  calculateShippingButton.disabled = entries.length === 0;
  checkoutButton.disabled = !state.shippingQuote?.rates[state.shippingQuote.selected] || entries.length === 0;
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
  const quantityInput = button.closest(".product-card")?.querySelector(`[data-quantity="${id}"]`);
  const quantity = Math.min(99, Math.max(1, Math.trunc(Number(quantityInput?.value) || 1)));
  if (quantityInput) quantityInput.value = String(quantity);
  state.cart[id] = (state.cart[id] || 0) + quantity;
  resetShippingQuote("Cart updated. Recalculate shipping for the new parcel.");
  renderCart();
  openCart();
});

rankingGrid.addEventListener("click", (event) => {
  const button = event.target.closest("[data-ranking-add]");
  if (!button) return;
  const id = button.dataset.rankingAdd;
  state.cart[id] = (state.cart[id] || 0) + 1;
  resetShippingQuote("Cart updated. Recalculate shipping for the new parcel.");
  renderCart();
  openCart();
});

cartItems.addEventListener("click", (event) => {
  const button = event.target.closest("[data-remove]");
  if (!button) return;
  delete state.cart[button.dataset.remove];
  resetShippingQuote(Object.keys(state.cart).length ? "Cart updated. Recalculate shipping for the new parcel." : "");
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
openRegisterButton.addEventListener("click", openRegister);
heroRegisterAction.addEventListener("click", openRegister);
closeRegisterButton.addEventListener("click", closeRegister);
registerForm.addEventListener("submit", submitRegistration);
registerModal.addEventListener("close", () => document.body.classList.remove("register-open"));
registerModal.addEventListener("click", (event) => {
  if (event.target === registerModal) closeRegister();
});
finishRegisterButton.addEventListener("click", () => {
  closeRegister();
  window.setTimeout(() => {
    registerFormView.hidden = false;
    registerSuccess.hidden = true;
  }, 250);
  document.getElementById("shop").scrollIntoView({ behavior: "smooth" });
});
heroSliderToggle.addEventListener("click", () => {
  heroPaused = !heroPaused;
  restartHeroTimer();
});
heroSliderDots.addEventListener("click", (event) => {
  const dot = event.target.closest("[data-hero-slide]");
  if (!dot) return;
  renderHeroSlide(Number(dot.dataset.heroSlide));
  restartHeroTimer();
});
heroPrimaryAction.addEventListener("click", () => {
  const tab = categoryTabs.querySelector(`[data-category="${heroPrimaryAction.dataset.category}"]`);
  if (tab) tab.click();
});
document.addEventListener("visibilitychange", () => {
  if (document.hidden && heroSlideTimer) {
    window.clearInterval(heroSlideTimer);
    heroSlideTimer = null;
  } else if (!document.hidden) {
    restartHeroTimer();
  }
});
calculateShippingButton.addEventListener("click", calculateShipping);
checkoutButton.addEventListener("click", openCheckout);
closeCheckoutButton.addEventListener("click", closeCheckout);
checkoutForm.addEventListener("submit", submitOrder);
checkoutForm.addEventListener("change", (event) => {
  if (event.target.matches('input[name="paymentMethod"]')) updateCheckoutSummary();
});
checkoutPostcode.addEventListener("input", () => {
  checkoutPostcode.value = checkoutPostcode.value.replace(/\D/g, "").slice(0, 4);
});
checkoutModal.addEventListener("close", () => document.body.classList.remove("checkout-open"));
checkoutModal.addEventListener("click", (event) => {
  if (event.target === checkoutModal) closeCheckout();
});
finishCheckoutButton.addEventListener("click", () => {
  state.cart = {};
  resetShippingQuote();
  renderCart();
  checkoutForm.reset();
  closeCheckout();
});
shippingPostcode.addEventListener("input", () => {
  shippingPostcode.value = shippingPostcode.value.replace(/\D/g, "").slice(0, 4);
  if (state.shippingQuote) resetShippingQuote("Postcode changed. Calculate again for updated rates.");
});
shippingState.addEventListener("change", () => {
  if (state.shippingQuote) resetShippingQuote("State changed. Calculate again for updated rates.");
});
shippingRates.addEventListener("change", (event) => {
  const input = event.target.closest('input[name="shippingRate"]');
  if (input) selectShippingRate(Number(input.value));
});
document.addEventListener("keydown", (event) => { if (event.key === "Escape") closeCart(); });

function escapeHtml(value) {
  return String(value ?? "").replace(/[&<>'"]/g, (character) => ({
    "&": "&amp;", "<": "&lt;", ">": "&gt;", "'": "&#39;", '"': "&quot;"
  })[character]);
}

async function loadCatalogProducts() {
  try {
    const response = await fetch(`catalog_products.php?v=${Date.now()}`, { cache: "no-store" });
    const data = await response.json();
    if (!response.ok || !data.ok || !Array.isArray(data.products)) throw new Error(data.error || "Catalog unavailable");
    products = data.products.map((product) => ({
      ...product,
      releaseOrder: Number(product.releaseOrder) || 0,
      jpy: Number(product.jpy) || 0,
      stock: Number(product.stock ?? 1),
    }));
  } catch (error) {
    console.warn("Using bundled catalog fallback:", error);
  }
}

async function initialiseStorefront() {
  await loadCatalogProducts();
  renderRanking();
  renderProducts();
  renderCart();
}

initialiseStorefront();
loadShippingProfiles();
initialiseHeroSlider();
