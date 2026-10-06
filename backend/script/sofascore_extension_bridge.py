"""
Pont WebSocket entre fetch_sofascore_cache.py et l'extension Chrome
"Sofascore Fetch Bridge" (backend/script/chrome_extension_sofascore/).

Pourquoi : Sofascore bloque les clients HTTP côté serveur (403/challenge IP).
L'extension fait le fetch() depuis une vraie session Chrome de l'utilisateur
(cookies + empreinte TLS réels) — même principe que Selenium (contourner le
blocage), sans avoir à piloter un Chrome headless.

Ce module fait tourner un serveur WebSocket (Python = serveur, l'extension =
client qui s'y connecte) sur ws://localhost:9998, dans un thread dédié avec sa
propre boucle asyncio, et expose une API synchrone (ExtensionBridge.fetch_json /
fetch_binary) que fetch_sofascore_cache.py peut appeler comme il appelait
fetch_json(driver, url) avec Selenium.

Usage :
    bridge = ExtensionBridge()
    bridge.wait_for_extension(timeout=60)  # ouvrir Chrome avec l'extension chargée
    data = bridge.fetch_json("https://www.sofascore.com/api/v1/...")
"""

import asyncio
import base64
import json
import threading
import time
import uuid
from queue import Queue, Empty
from typing import Optional

import websockets

WS_HOST = "localhost"
WS_PORT = 9998


class ExtensionBridge:
    def __init__(self, host: str = WS_HOST, port: int = WS_PORT):
        self.host = host
        self.port = port
        self._loop = None
        self._server = None
        self._ws = None  # connexion extension courante (une seule à la fois)
        self._pending: dict[str, Queue] = {}
        self._extension_ready = threading.Event()
        self._started = threading.Event()
        self._thread = threading.Thread(target=self._run_loop, daemon=True)
        self._thread.start()
        self._started.wait(timeout=10)

    # --- infrastructure asyncio (thread dédié) ---

    def _run_loop(self):
        self._loop = asyncio.new_event_loop()
        asyncio.set_event_loop(self._loop)
        self._loop.run_until_complete(self._serve())

    async def _serve(self):
        async def handler(websocket):
            self._ws = websocket
            print(f"[Bridge] Extension connectée ({websocket.remote_address})")
            try:
                async for raw in websocket:
                    try:
                        msg = json.loads(raw)
                    except Exception:
                        continue
                    self._handle_message(msg)
            except websockets.ConnectionClosed:
                pass
            finally:
                print("[Bridge] Extension déconnectée")
                if self._ws is websocket:
                    self._ws = None
                    self._extension_ready.clear()

        self._server = await websockets.serve(handler, self.host, self.port)
        print(f"[Bridge] En écoute sur ws://{self.host}:{self.port}")
        self._started.set()
        await self._server.wait_closed()

    def _handle_message(self, msg: dict):
        action = msg.get("action")
        if action == "extension_ready":
            print(f"[Bridge] Extension prête (version {msg.get('version')})")
            self._extension_ready.set()
            return
        req_id = msg.get("req_id")
        q = self._pending.get(req_id)
        if q:
            q.put(msg)

    # --- API publique (synchrone, appelée depuis le thread principal) ---

    def wait_for_extension(self, timeout: float = 60):
        """Bloque jusqu'à ce que l'extension Chrome se connecte (Chrome ouvert,
        extension chargée, et le service worker a pu établir le WebSocket).
        Lève TimeoutError sinon."""
        if not self._extension_ready.wait(timeout=timeout):
            raise TimeoutError(
                f"Extension Chrome non connectée après {timeout}s — "
                "vérifiez que Chrome est lancé avec l'extension "
                "chrome_extension_sofascore chargée (chrome://extensions)."
            )

    def is_connected(self) -> bool:
        return self._ws is not None and self._extension_ready.is_set()

    def _request(self, url: str, timeout: float) -> Optional[dict]:
        if not self._ws:
            return None
        req_id = uuid.uuid4().hex
        q: Queue = Queue()
        self._pending[req_id] = q
        try:
            fut = asyncio.run_coroutine_threadsafe(
                self._ws.send(json.dumps({"action": "fetch_url", "req_id": req_id, "url": url})),
                self._loop,
            )
            fut.result(timeout=5)
            try:
                return q.get(timeout=timeout)
            except Empty:
                return None
        except Exception as e:
            print(f"[Bridge] Erreur envoi requête: {e}")
            return None
        finally:
            self._pending.pop(req_id, None)

    def fetch_json(self, url: str, retries: int = 3, with_status: bool = False, timeout: float = 20):
        """Équivalent de fetch_json(driver, url) côté Selenium, mais via
        l'extension. Retourne le JSON parsé (ou (data, status) si
        with_status=True), ou None/(None, status) en cas d'échec définitif."""
        last_status = None
        for attempt in range(1, retries + 1):
            result = self._request(url, timeout)
            if result is None:
                print(f"  ⚠️  [extension] pas de réponse (tentative {attempt}/{retries}): {url}")
                time.sleep(2)
                continue

            last_status = result.get("status")
            if result.get("ok") and result.get("data_json") is not None:
                data = result["data_json"]
                return (data, last_status) if with_status else data

            print(f"  ⚠️  [extension] fetch KO (tentative {attempt}/{retries}) status={last_status}: {url}")
            if last_status == 404:
                break  # définitif, inutile de retenter
            if last_status == 403:
                time.sleep(15)  # challenge/rate-limit anti-bot
            else:
                time.sleep(2)
        return (None, last_status) if with_status else None

    def fetch_binary(self, url: str, retries: int = 3, timeout: float = 20) -> Optional[bytes]:
        """Équivalent des helpers _browser_fetch_image() — pour logos/images."""
        for attempt in range(1, retries + 1):
            result = self._request(url, timeout)
            if result and result.get("ok") and result.get("data_b64"):
                try:
                    return base64.b64decode(result["data_b64"])
                except Exception:
                    pass
            time.sleep(1.5)
        return None
