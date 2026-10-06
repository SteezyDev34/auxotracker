const el = document.getElementById("status");

chrome.runtime.sendMessage({ action: "get_status" }, (resp) => {
  if (chrome.runtime.lastError || !resp) {
    el.textContent = "Erreur : service worker injoignable";
    el.className = "ko";
    return;
  }
  if (resp.connected) {
    el.textContent = "✅ Connecté au script Python (ws://localhost:9998)";
    el.className = "ok";
  } else {
    el.textContent = "⏳ Pas encore connecté — lancez fetch_sofascore_cache.py --transport extension";
    el.className = "ko";
  }
});
