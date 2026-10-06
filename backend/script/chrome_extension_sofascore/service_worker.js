// Service Worker — pont WebSocket avec le script Python fetch_sofascore_cache.py
// Python écoute sur ws://localhost:9998 (voir sofascore_extension_bridge.py)
//
// Principe : Python envoie {action:'fetch_url', req_id, url}. Le fetch() est
// relayé vers un content script (content_fetch.js) injecté dans un vrai onglet
// sofascore.com — PAS fait directement ici. Un fetch() lancé depuis CE service
// worker a une signature de requête différente d'une navigation normale
// (Sec-Fetch-Site/Referer absents ou "extension"), ce que l'anti-bot de
// Sofascore détecte et bloque (403 "challenge") même avec les bons cookies et
// une IP saine — constaté empiriquement le 2026-09-29. Un fetch() exécuté
// DEPUIS la page (même origine, vrais en-têtes) passe en revanche.

const WS_URL = 'ws://localhost:9998';
const RECONNECT_DELAY = 2000;

let ws = null;
let wsConnected = false;

chrome.alarms.get('keepalive', (a) => { if (!a) chrome.alarms.create('keepalive', { periodInMinutes: 0.4 }); });
chrome.alarms.onAlarm.addListener((alarm) => {
  if (alarm.name === 'keepalive' && !wsConnected) connectWS();
});

function connectWS() {
  if (ws && (ws.readyState === WebSocket.OPEN || ws.readyState === WebSocket.CONNECTING)) return;

  try {
    ws = new WebSocket(WS_URL);
  } catch (e) {
    console.warn('[WS] Impossible de créer WebSocket:', e);
    setTimeout(connectWS, RECONNECT_DELAY);
    return;
  }

  ws.onopen = () => {
    wsConnected = true;
    console.log('[WS] Connecté à Python');
    ws.send(JSON.stringify({ action: 'extension_ready', version: '1.0.0' }));
  };

  ws.onmessage = (event) => {
    let msg;
    try { msg = JSON.parse(event.data); } catch { return; }
    handleMessage(msg).catch((e) => console.error('[WS] handleMessage erreur:', e));
  };

  ws.onclose = () => {
    wsConnected = false;
    console.warn('[WS] Déconnecté, reconnexion dans', RECONNECT_DELAY, 'ms');
    setTimeout(connectWS, RECONNECT_DELAY);
  };

  ws.onerror = () => {
    console.warn('[WS] Erreur WebSocket');
    try { ws.close(); } catch {}
  };
}

function send(obj) {
  if (ws && ws.readyState === WebSocket.OPEN) {
    ws.send(JSON.stringify(obj));
  } else {
    console.warn('[WS] Message perdu (WS fermé):', obj);
  }
}

let sofascoreTabId = null;

async function getSofascoreTab() {
  if (sofascoreTabId !== null) {
    try {
      const tab = await chrome.tabs.get(sofascoreTabId);
      if (tab && tab.url && tab.url.startsWith('https://www.sofascore.com/')) {
        return sofascoreTabId;
      }
    } catch (e) {
      // Onglet fermé/introuvable — on en recrée un.
    }
    sofascoreTabId = null;
  }

  const existing = await chrome.tabs.query({ url: 'https://www.sofascore.com/*' });
  if (existing.length > 0) {
    sofascoreTabId = existing[0].id;
    // Focus l'onglet même s'il existait déjà — s'il tournait en arrière-plan,
    // son challenge anti-bot a pu ne jamais s'exécuter (timers throttled).
    try { await chrome.tabs.update(sofascoreTabId, { active: true }); } catch (e) {}
    return sofascoreTabId;
  }

  // active:true — un onglet en arrière-plan est throttled par Chrome (timers
  // JS ralentis/suspendus), ce qui empêche le challenge anti-bot de la page
  // de s'exécuter complètement et fait échouer les fetch() lancés depuis ce
  // contexte même si l'origine/les cookies sont corrects (constaté le 2026-09-29).
  const tab = await chrome.tabs.create({ url: 'https://www.sofascore.com/', active: true });
  await new Promise((resolve) => {
    function listener(tabId, info) {
      if (tabId === tab.id && info.status === 'complete') {
        chrome.tabs.onUpdated.removeListener(listener);
        resolve();
      }
    }
    chrome.tabs.onUpdated.addListener(listener);
  });
  // Laisse le temps au content script de s'injecter et à un éventuel
  // challenge anti-bot initial de se résoudre (comme warm_session() côté Selenium).
  await new Promise((r) => setTimeout(r, 2000));

  sofascoreTabId = tab.id;
  return sofascoreTabId;
}

// Injecte content_fetch.js dans un onglet qui ne l'a pas (cas d'un onglet
// sofascore.com déjà ouvert AVANT que ce content script existe/soit rechargé
// — les content scripts déclarés dans le manifest ne s'injectent que sur les
// nouvelles navigations, jamais rétroactivement sur un onglet déjà chargé).
async function ensureContentScript(tabId) {
  await chrome.scripting.executeScript({ target: { tabId }, files: ['content_fetch.js'] });
}

async function sendToTab(tabId, message) {
  try {
    return await chrome.tabs.sendMessage(tabId, message);
  } catch (e) {
    if (!String(e && e.message).includes('Receiving end does not exist')) {
      throw e;
    }
    console.warn('[Bridge] Content script absent de l\'onglet, injection à la volée...');
    await ensureContentScript(tabId);
    return await chrome.tabs.sendMessage(tabId, message);
  }
}

// Fetch direct depuis le service worker — utilisé UNIQUEMENT pour les images
// (api.sofascore.com). Un fetch cross-origin depuis le content script (page
// www.sofascore.com) échouerait en CORS ; le service worker, lui, bypass le
// CORS grâce aux host_permissions du manifest. Les images ne semblent pas
// soumises au même anti-bot que les endpoints JSON (jamais vu de 403
// "challenge" dessus), donc le fetch direct reste fiable pour ce cas précis.
async function fetchDirect(url) {
  const resp = await fetch(url, {
    credentials: 'include',
    headers: {
      'Accept': 'application/json, text/plain, */*',
      'Accept-Language': 'fr-FR,fr;q=0.9,en-US;q=0.8',
    },
  });
  const status = resp.status;
  const buf = await resp.arrayBuffer();
  const bytes = new Uint8Array(buf);
  let binary = '';
  for (let i = 0; i < bytes.length; i++) binary += String.fromCharCode(bytes[i]);
  const data_b64 = btoa(binary);
  let data_json = null;
  try {
    data_json = JSON.parse(new TextDecoder('utf-8').decode(bytes));
  } catch (e) {}
  return { ok: resp.ok, status, data_json, data_b64 };
}

// Navigation RÉELLE de l'onglet vers l'URL cible (comme taper l'URL dans la
// barre d'adresse), puis lecture du JSON déjà affiché par le navigateur —
// voir content_fetch.js pour le pourquoi (signature de requête indiscernable
// d'une vraie navigation humaine, contrairement à un fetch() même en page).
async function navigateAndRead(tabId, url) {
  await chrome.tabs.update(tabId, { url });
  await new Promise((resolve, reject) => {
    const timeout = setTimeout(() => { cleanup(); reject(new Error('Timeout navigation (15s)')); }, 15000);
    function listener(updatedTabId, info) {
      if (updatedTabId === tabId && info.status === 'complete') { cleanup(); resolve(); }
    }
    function cleanup() {
      clearTimeout(timeout);
      chrome.tabs.onUpdated.removeListener(listener);
    }
    chrome.tabs.onUpdated.addListener(listener);
  });
  return await sendToTab(tabId, { action: 'page_read' });
}

// Espacement minimum garanti entre deux requêtes API, quel que soit le délai
// naturel côté Python (traitement/calcul) — évite un burst si le script tourne
// vite (ex: beaucoup de cache hits d'affilée puis plusieurs vrais fetch coup
// sur coup).
const MIN_REQUEST_GAP_MS = 2000;
let lastRequestAt = 0;

async function handleMessage(msg) {
  if (msg.action !== 'fetch_url') return;
  const { req_id, url } = msg;

  const elapsed = Date.now() - lastRequestAt;
  if (elapsed < MIN_REQUEST_GAP_MS) {
    await new Promise((r) => setTimeout(r, MIN_REQUEST_GAP_MS - elapsed));
  }
  lastRequestAt = Date.now();

  try {
    let result;
    if (new URL(url).hostname === 'api.sofascore.com') {
      result = await fetchDirect(url);
    } else {
      const tabId = await getSofascoreTab();
      result = await navigateAndRead(tabId, url);
    }
    send({ req_id, ...result });
  } catch (e) {
    console.warn('[Bridge] Erreur fetch:', e);
    send({ req_id, ok: false, status: null, error: String(e && e.message || e) });
  }
}

chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {
  if (msg?.action === "get_status") {
    sendResponse({ connected: wsConnected });
  }
});

connectWS();
