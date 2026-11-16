/* ============================================================
 * MegaCards.jp — GA4 Traffic Source Attribution Enhancement
 * ------------------------------------------------------------
 * - Captures UTM parameters (utm_source/medium/campaign/term/content)
 * - Captures click IDs (gclid, msclkid, fbclid, ttclid)
 * - Classifies referrer into: paid_search / organic_search / social
 *   / email / referral / direct
 * - Stores first-touch + last-touch attribution in localStorage
 * - Sets GA4 user_properties so you can segment reports by
 *   first-touch source forever
 * - Sends one custom event "traffic_source_detail" per session
 *   containing a human-readable channel label
 * - Adds channel as a custom parameter to every GA4 page_view
 * - Internal navigation (same-site referrer, no new campaign
 *   signal) reuses the session's attribution instead of being
 *   re-classified as "direct"
 * - Leaves GA4's own session source/medium/campaign untouched
 *   (no campaign_* overrides), so GA4 and Google Ads attribution
 *   keep working natively
 * ============================================================ */
(function () {
  var GA_ID = 'G-SPWWT45MZF';
  var LS_FIRST = 'mc_first_touch';
  var LS_LAST  = 'mc_last_touch';
  var SS_KEY   = 'mc_session_tagged';
  var SS_ATTR  = 'mc_session_attribution';

  // Ensure dataLayer + gtag exist even if this loads before the bootstrap block
  window.dataLayer = window.dataLayer || [];
  function gtag() { window.dataLayer.push(arguments); }
  if (typeof window.gtag !== 'function') window.gtag = gtag;

  // ---------- helpers ----------
  function getParam(name) {
    try {
      var u = new URL(window.location.href);
      return u.searchParams.get(name) || '';
    } catch (e) { return ''; }
  }

  function isInternalReferrer() {
    try {
      if (!document.referrer) return false;
      return new URL(document.referrer).hostname === window.location.hostname;
    } catch (e) { return false; }
  }

  function parseReferrerDomain() {
    try {
      if (!document.referrer) return '';
      var u = new URL(document.referrer);
      if (u.hostname === window.location.hostname) return ''; // same site
      return u.hostname.replace(/^www\./, '');
    } catch (e) { return ''; }
  }

  // Classify a referrer domain into a channel group
  function classifyReferrer(host) {
    if (!host) return { source: '(direct)', medium: '(none)', channel: 'Direct' };
    var rules = [
      { re: /google\./,    s: 'google',    m: 'organic', c: 'Organic Search' },
      { re: /bing\./,      s: 'bing',      m: 'organic', c: 'Organic Search' },
      { re: /yahoo\./,     s: 'yahoo',     m: 'organic', c: 'Organic Search' },
      { re: /duckduckgo\./,s: 'duckduckgo',m: 'organic', c: 'Organic Search' },
      { re: /baidu\./,     s: 'baidu',     m: 'organic', c: 'Organic Search' },
      { re: /yandex\./,    s: 'yandex',    m: 'organic', c: 'Organic Search' },
      { re: /facebook\.|fb\.me|fb\.com/,        s: 'facebook',  m: 'social', c: 'Social' },
      { re: /instagram\./, s: 'instagram', m: 'social',  c: 'Social' },
      { re: /(^|\.)(t\.co|twitter\.com|x\.com)$/,        s: 'twitter',   m: 'social', c: 'Social' },
      { re: /tiktok\./,    s: 'tiktok',    m: 'social',  c: 'Social' },
      { re: /reddit\./,    s: 'reddit',    m: 'social',  c: 'Social' },
      { re: /youtube\./,   s: 'youtube',   m: 'social',  c: 'Social' },
      { re: /pinterest\./, s: 'pinterest', m: 'social',  c: 'Social' },
      { re: /linkedin\./,  s: 'linkedin',  m: 'social',  c: 'Social' },
      { re: /discord\./,   s: 'discord',   m: 'social',  c: 'Social' },
      { re: /mail\.google|mail\.yahoo|outlook\.|hotmail\./, s: 'email', m: 'email', c: 'Email' },
    ];
    for (var i = 0; i < rules.length; i++) {
      if (rules[i].re.test(host)) return { source: rules[i].s, medium: rules[i].m, channel: rules[i].c };
    }
    return { source: host, medium: 'referral', channel: 'Referral' };
  }

  // ---------- collect attribution ----------
  var utm = {
    source:   getParam('utm_source'),
    medium:   getParam('utm_medium'),
    campaign: getParam('utm_campaign'),
    term:     getParam('utm_term'),
    content:  getParam('utm_content'),
  };
  var gclid  = getParam('gclid');
  var msclkid = getParam('msclkid');
  var fbclid  = getParam('fbclid');
  var ttclid  = getParam('ttclid');

  var attribution;
  var hasCampaignSignal = !!(utm.source || utm.medium || utm.campaign || gclid || msclkid || fbclid || ttclid);
  var externalReferrer = parseReferrerDomain();
  // Internal navigation (or a reload) with no new campaign signal is not a
  // new touch: reuse this session's attribution, or the stored last touch
  // when a new tab was opened from a MegaCards page.
  var reused = null;
  if (!hasCampaignSignal && !externalReferrer) {
    reused = readSS(SS_ATTR) || (isInternalReferrer() ? readLS(LS_LAST) : null);
  }
  var isNewTouch = !reused;

  if (reused) {
    attribution = reused;
  } else if (utm.source || utm.medium || utm.campaign) {
    // Explicit UTM tagging wins
    var ch = 'Other';
    var m = (utm.medium || '').toLowerCase();
    if (m === 'cpc' || m === 'paid' || m === 'ppc') ch = 'Paid Search';
    else if (m === 'social' || m === 'social-paid') ch = 'Social';
    else if (m === 'email')    ch = 'Email';
    else if (m === 'affiliate')ch = 'Affiliate';
    else if (m === 'referral') ch = 'Referral';
    else if (m === 'organic')  ch = 'Organic Search';
    else if (m === 'display')  ch = 'Display';
    attribution = {
      source:   utm.source   || '(not set)',
      medium:   utm.medium   || '(not set)',
      campaign: utm.campaign || '(not set)',
      term:     utm.term     || '',
      content:  utm.content  || '',
      channel:  ch,
      method:   'utm',
    };
  } else if (gclid) {
    attribution = { source: 'google', medium: 'cpc', campaign: '(google ads)', channel: 'Paid Search', method: 'gclid' };
  } else if (msclkid) {
    attribution = { source: 'bing',   medium: 'cpc', campaign: '(bing ads)',   channel: 'Paid Search', method: 'msclkid' };
  } else if (fbclid) {
    attribution = { source: 'facebook', medium: 'social', campaign: '(facebook)', channel: 'Social', method: 'fbclid' };
  } else if (ttclid) {
    attribution = { source: 'tiktok', medium: 'social', campaign: '(tiktok)', channel: 'Social', method: 'ttclid' };
  } else {
    var host = parseReferrerDomain();
    var r = classifyReferrer(host);
    attribution = {
      source: r.source, medium: r.medium, campaign: '(not set)',
      term: '', content: '', channel: r.channel, method: host ? 'referrer' : 'direct',
    };
  }

  if (isNewTouch) {
    attribution.landing_page = window.location.pathname + window.location.search;
    attribution.first_seen_at = new Date().toISOString();
  }

  // ---------- persist first + last touch ----------
  function readLS(k) { try { return JSON.parse(localStorage.getItem(k) || 'null'); } catch (e) { return null; } }
  function writeLS(k, v) { try { localStorage.setItem(k, JSON.stringify(v)); } catch (e) {} }
  function readSS(k) { try { return JSON.parse(sessionStorage.getItem(k) || 'null'); } catch (e) { return null; } }
  function writeSS(k, v) { try { sessionStorage.setItem(k, JSON.stringify(v)); } catch (e) {} }

  var firstTouch = readLS(LS_FIRST);
  if (!firstTouch) { firstTouch = attribution; writeLS(LS_FIRST, firstTouch); }
  if (isNewTouch) writeLS(LS_LAST, attribution);
  writeSS(SS_ATTR, attribution);

  // ---------- push to GA4 ----------
  gtag('js', new Date());

  // Configure GA4 property WITH enriched params attached to every event
  gtag('config', GA_ID, {
    // Pass-through: these become event parameters on every event
    traffic_channel:        attribution.channel,
    traffic_source_detail:  attribution.source,
    traffic_medium_detail:  attribution.medium,
    traffic_campaign_detail: attribution.campaign,
    detection_method:       attribution.method,
    // No campaign_source/medium/name overrides: GA4 reads UTM tags,
    // gclid and the referrer itself. Forcing them here made every
    // referrer-less page view report "(direct) / (none)".
  });

  // Persistent user properties (first-touch — never overwritten once set)
  gtag('set', 'user_properties', {
    first_touch_channel: firstTouch.channel,
    first_touch_source:  firstTouch.source,
    first_touch_medium:  firstTouch.medium,
    first_touch_campaign: firstTouch.campaign,
    last_touch_channel:  attribution.channel,
    last_touch_source:   attribution.source,
  });

  // Fire a single high-signal event per session so you can build an
  // "Acquisition by channel" exploration easily
  try {
    if (!sessionStorage.getItem(SS_KEY)) {
      gtag('event', 'traffic_source_detail', {
        traffic_channel:  attribution.channel,
        traffic_source:   attribution.source,
        traffic_medium:   attribution.medium,
        traffic_campaign: attribution.campaign,
        detection_method: attribution.method,
        landing_page:     attribution.landing_page,
        referrer:         document.referrer || '(none)',
        first_touch_channel: firstTouch.channel,
        first_touch_source:  firstTouch.source,
      });
      sessionStorage.setItem(SS_KEY, '1');
    }
  } catch (e) {}

  // Expose for debugging: window.mcAttribution
  window.mcAttribution = { current: attribution, firstTouch: firstTouch };
})();
