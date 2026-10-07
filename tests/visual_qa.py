import base64
import json
import re
import time
from pathlib import Path

import requests
from websockets.sync.client import connect

BASE = "http://127.0.0.1:8767/interviews"
OUT = Path(__file__).resolve().parents[1] / "storage" / "qa"
OUT.mkdir(parents=True, exist_ok=True)


def login(identity, password):
    session = requests.Session()
    html = session.get(f"{BASE}/login").text
    token = re.search(r'name="_token" value="([^"]+)"', html).group(1)
    response = session.post(f"{BASE}/login", data={"_token": token, "identity": identity, "password": password})
    response.raise_for_status()
    return session


class CDP:
    def __init__(self):
        target = requests.get("http://127.0.0.1:9225/json/list").json()[0]
        self.ws = connect(target["webSocketDebuggerUrl"], origin=None, max_size=20_000_000)
        self.next_id = 1

    def call(self, method, params=None):
        call_id = self.next_id
        self.next_id += 1
        self.ws.send(json.dumps({"id": call_id, "method": method, "params": params or {}}))
        while True:
            message = json.loads(self.ws.recv())
            if message.get("id") == call_id:
                if "error" in message:
                    raise RuntimeError(f"{method}: {message['error']}")
                return message.get("result", {})

    def cookies(self, session):
        self.call("Network.clearBrowserCookies")
        for cookie in session.cookies:
            self.call("Network.setCookie", {"name": cookie.name, "value": cookie.value, "url": BASE})

    def viewport(self, width, height=900):
        self.call("Emulation.setDeviceMetricsOverride", {
            "width": width, "height": height, "deviceScaleFactor": 1,
            "mobile": width <= 390, "screenWidth": width, "screenHeight": height,
        })

    def goto(self, path):
        url=f"{BASE}{path}"
        self.call("Page.navigate", {"url": url})
        for _ in range(60):
            state=self.eval("({ready:document.readyState,url:location.href})")
            if state["ready"] == "complete" and state["url"] == url:
                break
            time.sleep(0.05)
        time.sleep(0.15)

    def eval(self, expression):
        result = self.call("Runtime.evaluate", {"expression": expression, "returnByValue": True})
        return result["result"].get("value")

    def screenshot(self, name, full=False):
        params = {"format": "png", "fromSurface": True, "captureBeyondViewport": full}
        if full:
            metrics = self.call("Page.getLayoutMetrics")
            size = metrics["cssContentSize"]
            params["clip"] = {"x": 0, "y": 0, "width": size["width"], "height": min(size["height"], 6000), "scale": 1}
        data = self.call("Page.captureScreenshot", params)["data"]
        path = OUT / name
        path.write_bytes(base64.b64decode(data))
        return str(path)


admin = login("lifecycle.admin", "LifecycleAdmin12")
candidate = login("+919711111111", "PhotoUpload12!")
browser = CDP()
browser.call("Page.enable")
browser.call("Network.enable")

checks = []
captures = []


def check(condition, label):
    if not condition:
        raise RuntimeError(f"Visual QA failed: {label}")
    checks.append(label)


def inspect(path, width, name, full=True, expected=None):
    browser.viewport(width)
    browser.goto(path)
    check(browser.eval("document.documentElement.scrollWidth <= window.innerWidth"), f"{name} has no horizontal overflow at {width}px")
    check(browser.eval("[...document.images].every(i => i.complete && i.naturalWidth > 0)"), f"{name} has no broken images at {width}px")
    if expected:
        check(browser.eval(f"document.body.innerText.toLowerCase().includes({json.dumps(expected.lower())})"), f"{name} renders {expected!r} at {width}px")
    captures.append(browser.screenshot(f"{name}-{width}.png", full=full))


browser.cookies(admin)
for width in (1366, 390):
    inspect("/admin", width, "dashboard", expected="Today’s schedule")
    check(not browser.eval("document.body.innerText.includes('Lifecycle Company')"), f"dashboard excludes non-today interviews at {width}px")
    check(browser.eval("document.body.innerText.includes('Visual QA 1') && document.body.innerText.includes('Visual QA 2') && document.body.innerText.includes('Visual QA 3')"), f"dashboard includes all today interviews at {width}px")
    check(browser.eval("[...document.querySelectorAll('.agenda-avatar img,.focus-avatar img')].every(i => i.complete && i.naturalWidth > 0)"), f"dashboard candidate photos load at {width}px")
    inspect("/admin/candidates?search=Photo%20Upload%20Candidate", width, "candidates", expected="Photo Upload Candidate")
    inspect("/admin/candidate?id=11", width, "candidate-detail", expected="Photo Upload Candidate")
    inspect("/admin/candidate?id=6", width, "reactivate-state", expected="Reactivate candidate")
    inspect("/admin/interviews?search=Today%20Candidate", width, "deleted-interviews", expected="Deleted candidate")
    inspect("/admin/calendar?date=2038-01-05&view=day", width, "deleted-calendar", expected="Deleted candidate")
    inspect("/admin/interview?id=3", width, "deleted-interview-detail", expected="Deleted candidate")

browser.viewport(1366)
browser.goto("/admin/candidate?id=11")
browser.eval("document.querySelector('[data-confirm-form] button').click()")
time.sleep(0.15)
check(browser.eval("document.querySelector('#confirmation-dialog').open"), "deactivate warning modal opens")
captures.append(browser.screenshot("deactivate-modal-1366.png"))
browser.eval("document.querySelector('#confirmation-cancel').click()")
browser.eval("document.querySelector('[data-delete-candidate]').click()")
time.sleep(0.15)
check(browser.eval("document.querySelector('#candidate-delete-dialog').open"), "permanent-delete modal opens")
captures.append(browser.screenshot("delete-modal-1366.png"))

browser.viewport(390)
browser.goto("/admin/candidate?id=11")
browser.eval("document.querySelector('[data-delete-candidate]').click()")
time.sleep(0.15)
check(browser.eval("document.querySelector('#candidate-delete-dialog').open"), "permanent-delete modal opens on mobile")
check(browser.eval("document.documentElement.scrollWidth <= window.innerWidth"), "delete modal has no mobile overflow")
captures.append(browser.screenshot("delete-modal-390.png"))

browser.cookies(candidate)
for width in (1366, 390):
    inspect("/profile", width, "candidate-profile", expected="My profile")
    check(browser.eval("document.querySelector('.profile-header .avatar img')?.naturalWidth > 0"), f"candidate profile photo loads at {width}px")

print(json.dumps({"checks": len(checks), "captures": captures}, indent=2))
