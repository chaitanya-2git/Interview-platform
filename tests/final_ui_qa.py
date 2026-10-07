import base64
import json
import os
import re
import time
from pathlib import Path

import requests
from websockets.sync.client import connect

BASE = "http://127.0.0.1:8767/interviews"
CDP = "http://127.0.0.1:9227"
MODE = os.environ.get("BLUJAY_QA_MODE", "final")
OUT = Path(__file__).resolve().parents[1] / "storage" / f"qa-{MODE}"
OUT.mkdir(parents=True, exist_ok=True)

VIEWPORTS = [(360, 800), (375, 812), (390, 844), (412, 915), (430, 932), (1366, 768), (1440, 900)]


def login(identity, password):
    session = requests.Session()
    page = session.get(f"{BASE}/login")
    page.raise_for_status()
    token = re.search(r'name="_token" value="([^"]+)"', page.text).group(1)
    response = session.post(f"{BASE}/login", data={"_token": token, "identity": identity, "password": password})
    response.raise_for_status()
    return session


class Browser:
    def __init__(self):
        target = requests.get(f"{CDP}/json/list").json()[0]
        self.ws = connect(target["webSocketDebuggerUrl"], origin=None, max_size=30_000_000)
        self.next_id = 1
        self.call("Page.enable")
        self.call("Network.enable")

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

    def eval(self, expression):
        result = self.call("Runtime.evaluate", {"expression": expression, "returnByValue": True, "awaitPromise": True})
        if "exceptionDetails" in result:
            raise RuntimeError(result["exceptionDetails"].get("text", expression))
        return result["result"].get("value")

    def viewport(self, width, height):
        self.call("Emulation.setDeviceMetricsOverride", {
            "width": width, "height": height, "deviceScaleFactor": 1,
            "mobile": width <= 430, "screenWidth": width, "screenHeight": height,
        })

    def cookies(self, session=None):
        self.call("Network.clearBrowserCookies")
        if session:
            for cookie in session.cookies:
                self.call("Network.setCookie", {"name": cookie.name, "value": cookie.value, "url": BASE})

    def goto(self, path):
        url = f"{BASE}{path}"
        self.call("Page.navigate", {"url": url})
        for _ in range(100):
            state = self.eval("({ready:document.readyState,url:location.href})")
            if state["ready"] == "complete":
                break
            time.sleep(0.04)
        time.sleep(0.12)

    def screenshot(self, name):
        size = self.call("Page.getLayoutMetrics")["cssContentSize"]
        data = self.call("Page.captureScreenshot", {
            "format": "png", "fromSurface": True, "captureBeyondViewport": True,
            "clip": {"x": 0, "y": 0, "width": size["width"], "height": min(size["height"], 7000), "scale": 1},
        })["data"]
        path = OUT / f"{name}.png"
        path.write_bytes(base64.b64decode(data))
        return str(path)


browser = Browser()
admin = login("lifecycle.admin", "LifecycleAdmin12")
candidate = login("+919711111111", "PhotoUpload12!")
resume_candidate = login("+919733333301", "ResumeTesting12!")

guest_pages = [
    ("login", "/login"),
    ("register", "/register"),
]
candidate_pages = [
    ("candidate-dashboard", "/candidate"),
    ("candidate-interviews", "/candidate/interviews"),
    ("candidate-interview-detail", "/candidate/interview?id=16"),
    ("candidate-book", "/candidate/book"),
    ("candidate-profile", "/profile"),
]
resume_pages = [("candidate-resume-profile", "/profile")]
admin_pages = [
    ("admin-dashboard", "/admin"),
    ("admin-calendar-day", "/admin/calendar?view=day"),
    ("admin-calendar-week", "/admin/calendar?view=week"),
    ("admin-interviews", "/admin/interviews"),
    ("admin-interview-detail", "/admin/interview?id=16"),
    ("admin-create-interview", "/admin/interview/new"),
    ("admin-reschedule", "/admin/interview/reschedule?id=16"),
    ("admin-candidates", "/admin/candidates"),
    ("admin-candidate-detail", "/admin/candidate?id=11"),
    ("admin-candidate-resume", "/admin/candidate?id=13"),
    ("admin-resources", "/admin/resources"),
    ("admin-support", "/admin/support"),
    ("admin-technologies", "/admin/technologies"),
    ("admin-reports", "/admin/reports?preset=custom&start=2032-05-01&end=2032-05-03"),
    ("admin-audit", "/admin/audit"),
    ("admin-audit-detail", "/admin/audit/detail?id=27"),
    ("admin-settings", "/admin/settings"),
]

groups = [(None, guest_pages), (candidate, candidate_pages), (resume_candidate, resume_pages), (admin, admin_pages)]
results = {"mode": MODE, "pages": 0, "layout_checks": 0, "screenshots": [], "small_targets": {}, "failures": []}


def audit(name, path, width, height, screenshot=False):
    browser.viewport(width, height)
    browser.goto(path)
    metrics = browser.eval("""
      (() => {
        const visible = e => { const s=getComputedStyle(e),r=e.getBoundingClientRect(); return s.display!=='none'&&s.visibility!=='hidden'&&r.width>0&&r.height>0; };
        const outside=[...document.querySelectorAll('button,a,input,select,dialog')].filter(visible).filter(e=>{const r=e.getBoundingClientRect();return r.left < -1 || r.right > innerWidth + 1;}).map(e=>({tag:e.tagName,text:(e.innerText||e.value||e.getAttribute('aria-label')||'').trim().slice(0,60),left:Math.round(e.getBoundingClientRect().left),right:Math.round(e.getBoundingClientRect().right)}));
        const small=[...document.querySelectorAll('button,input:not([type=hidden]),select,.button,.icon-button,.nav-item')].filter(visible).filter(e=>{const r=e.getBoundingClientRect();return r.width<40||r.height<40;}).map(e=>({tag:e.tagName,text:(e.innerText||e.value||e.getAttribute('aria-label')||'').trim().slice(0,50),w:Math.round(e.getBoundingClientRect().width),h:Math.round(e.getBoundingClientRect().height)}));
        return {scrollWidth:document.documentElement.scrollWidth,clientWidth:document.documentElement.clientWidth,outside,small,broken:[...document.images].filter(i=>i.complete&&!i.naturalWidth).length};
      })()
    """)
    results["pages"] += 1
    results["layout_checks"] += 3
    if metrics["scrollWidth"] > metrics["clientWidth"]:
        results["failures"].append(f"{name}@{width}: overflow {metrics['scrollWidth']}>{metrics['clientWidth']}")
    if metrics["outside"]:
        results["failures"].append(f"{name}@{width}: offscreen controls {metrics['outside']}")
    if metrics["broken"]:
        results["failures"].append(f"{name}@{width}: {metrics['broken']} broken images")
    if width <= 430 and metrics["small"]:
        results["small_targets"][f"{name}@{width}"] = metrics["small"][:12]
    if screenshot:
        results["screenshots"].append(browser.screenshot(f"{name}-{width}"))


for session, pages in groups:
    browser.cookies(session)
    for width, height in VIEWPORTS:
        for name, path in pages:
            audit(name, path, width, height, screenshot=width in (390, 1440))

# Exercise mobile menu and dialogs as rendered overlays.
browser.cookies(admin)
browser.viewport(390, 844)
browser.goto("/admin")
browser.eval("document.querySelector('.admin-mobile-nav [data-menu]').click()")
results["screenshots"].append(browser.screenshot("admin-navigation-390"))
browser.goto("/admin/candidate?id=11")
browser.eval("document.querySelector('[data-delete-candidate]').click()")
dialog = browser.eval("(() => {const r=document.querySelector('#candidate-delete-dialog').getBoundingClientRect();return {left:r.left,right:r.right,top:r.top,bottom:r.bottom,vw:innerWidth,vh:innerHeight}})()")
if dialog["left"] < 0 or dialog["right"] > dialog["vw"] or dialog["top"] < 0 or dialog["bottom"] > dialog["vh"]:
    results["failures"].append(f"delete dialog out of viewport: {dialog}")
results["screenshots"].append(browser.screenshot("candidate-delete-dialog-390"))
browser.goto("/admin/calendar?view=day")
if browser.eval("document.querySelector('[data-calendar-card]') !== null"):
    browser.eval("document.querySelector('[data-calendar-card]').click()")
    results["screenshots"].append(browser.screenshot("calendar-drawer-390"))

# Render each candidate booking step without submitting or changing data.
browser.cookies(candidate)
browser.viewport(390, 844)
browser.goto("/candidate/book")
for step in (1, 2, 3, 4):
    browser.eval(f"""
      (()=>{{
        document.querySelectorAll('[data-step]').forEach(e=>{{const on=Number(e.dataset.step)==={step};e.hidden=!on;e.classList.toggle('active',on)}});
        document.querySelectorAll('[data-progress]').forEach(e=>e.classList.toggle('active',Number(e.dataset.progress)==={step}));
        if({step}===3){{const g=document.querySelector('[data-slot-grid]');if(g&&!g.children.length)g.innerHTML='<button type="button" class="time-slot">10:00 AM</button><button type="button" class="time-slot selected">10:30 AM</button><button type="button" class="time-slot">11:00 AM</button><button type="button" class="time-slot">11:30 AM</button>';}}
      }})()
    """)
    results["screenshots"].append(browser.screenshot(f"candidate-book-step-{step}-390"))

(OUT / "results.json").write_text(json.dumps(results, indent=2), encoding="utf-8")
print(json.dumps({
    "mode": MODE,
    "pages": results["pages"],
    "layout_checks": results["layout_checks"],
    "screenshots": len(results["screenshots"]),
    "failure_count": len(results["failures"]),
    "failures": results["failures"],
    "small_target_pages": len(results["small_targets"]),
}, indent=2))

if MODE == "final" and results["failures"]:
    raise SystemExit(1)
