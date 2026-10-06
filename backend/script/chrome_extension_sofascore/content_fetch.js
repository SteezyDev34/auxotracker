// Content script injecté dans les pages sofascore.com.
//
// 'page_read' : lit le JSON déjà affiché par le navigateur suite à une VRAIE
// NAVIGATION de l'onglet vers l'URL de l'API (chrome.tabs.update côté
// service_worker.js) — pas un fetch() XHR, même de qualité "page context".
// Une navigation top-level a une signature de requête (Sec-Fetch-Mode:
// navigate, Sec-Fetch-Dest: document) qu'aucun fetch() ne peut reproduire,
// et c'est exactement ce qu'un utilisateur fait en tapant l'URL dans la barre
// d'adresse — constaté fonctionner de façon fiable là où fetch() se fait
// bloquer par l'anti-bot (2026-09-29).
//
// 'page_fetch' : ancien mécanisme (fetch() depuis la page) conservé en repli,
// au cas où un endpoint nécessiterait un vrai fetch (rare).

// Le vrai code HTTP d'une navigation n'est pas exposé directement au JS de la
// page — seule la Performance API (Chrome 109+) le fournit via
// responseStatus. Repli : le corps JSON d'erreur de Sofascore a lui-même la
// forme {"error":{"code":404,...}} — on l'utilise si l'API Performance ne
// donne rien (0 ou absent).
function realHttpStatus(parsedJson) {
  try {
    const nav = performance.getEntriesByType('navigation')[0];
    if (nav && typeof nav.responseStatus === 'number' && nav.responseStatus > 0) {
      return nav.responseStatus;
    }
  } catch (e) {}
  const code = parsedJson && parsedJson.error && parsedJson.error.code;
  return typeof code === 'number' ? code : 200;
}

function readPageJson() {
  const text = document.body ? document.body.innerText : '';
  try {
    const parsed = JSON.parse(text);
    const status = realHttpStatus(parsed);
    return { ok: status >= 200 && status < 300, status, data_json: parsed, data_b64: null };
  } catch (e) {
    return { ok: false, status: null, data_json: null, data_b64: null, error: 'JSON invalide sur la page: ' + String(e && e.message || e) };
  }
}

chrome.runtime.onMessage.addListener((msg, sender, sendResponse) => {
  if (msg?.action === 'page_read') {
    sendResponse(readPageJson());
    return;
  }

  if (msg?.action !== 'page_fetch') return;

  (async () => {
    try {
      const r = await fetch(msg.url, {
        credentials: 'include',
        headers: {
          'Accept': 'application/json, text/plain, */*',
          'Accept-Language': 'fr-FR,fr;q=0.9,en-US;q=0.8',
        },
      });
      const status = r.status;
      const buf = await r.arrayBuffer();
      const bytes = new Uint8Array(buf);

      let binary = '';
      for (let i = 0; i < bytes.length; i++) binary += String.fromCharCode(bytes[i]);
      const data_b64 = btoa(binary);

      let data_json = null;
      try {
        const text = new TextDecoder('utf-8').decode(bytes);
        data_json = JSON.parse(text);
      } catch (e) {
        // Réponse non-JSON (image binaire par ex.)
      }

      sendResponse({ ok: r.ok, status, data_json, data_b64 });
    } catch (e) {
      sendResponse({ ok: false, status: null, error: String(e && e.message || e) });
    }
  })();

  return true; // réponse asynchrone
});
