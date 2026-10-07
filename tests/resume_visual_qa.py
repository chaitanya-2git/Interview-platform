import base64
import json
import re
import time
from pathlib import Path

import requests
from websockets.sync.client import connect

BASE = "http://127.0.0.1:8770/interviews"
CDP_BASE = "http://127.0.0.1:9226"
OUT = Path(__file__).resolve().parents[1] / "storage" / "qa-resume"
OUT.mkdir(parents=True, exist_ok=True)


def login(identity, password):
    session = requests.Session()
    html = session.get(f"{BASE}/login").text
    token = re.search(r'name="_token" value="([^"]+)"', html).group(1)
    response = session.post(f"{BASE}/login", data={"_token": token, "identity": identity, "password": password})
    response.raise_for_status()
    return session


class Browser:
    def __init__(self):
        target = requests.get(f"{CDP_BASE}/json/list").json()[0]
        self.ws = connect(target["webSocketDebuggerUrl"], origin=None, max_size=25_000_000)
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

    def eval(self, expression, object_result=False):
        result = self.call("Runtime.evaluate", {
            "expression": expression,
            "returnByValue": not object_result,
            "awaitPromise": True,
        })["result"]
        return result if object_result else result.get("value")

    def cookies(self, session=None):
        self.call("Network.clearBrowserCookies")
        if session:
            for cookie in session.cookies:
                self.call("Network.setCookie", {"name": cookie.name, "value": cookie.value, "url": BASE})

    def viewport(self, width, height=900):
        self.call("Emulation.setDeviceMetricsOverride", {
            "width": width, "height": height, "deviceScaleFactor": 1,
            "mobile": width <= 390, "screenWidth": width, "screenHeight": height,
        })

    def goto(self, path):
        url = f"{BASE}{path}"
        self.call("Page.navigate", {"url": url})
        for _ in range(80):
            state = self.eval("({ready:document.readyState,url:location.href})")
            if state["ready"] == "complete" and state["url"] == url:
                break
            time.sleep(0.05)
        time.sleep(0.15)

    def screenshot(self, name):
        metrics = self.call("Page.getLayoutMetrics")["cssContentSize"]
        data = self.call("Page.captureScreenshot", {
            "format": "png", "fromSurface": True, "captureBeyondViewport": True,
            "clip": {"x": 0, "y": 0, "width": metrics["width"], "height": min(metrics["height"], 6000), "scale": 1},
        })["data"]
        path = OUT / name
        path.write_bytes(base64.b64decode(data))
        return str(path)


checks = []
captures = []


def check(condition, label):
    if not condition:
        raise RuntimeError(f"Visual QA failed: {label}")
    checks.append(label)


browser = Browser()
admin = login("resume.admin", "ResumeAdmin12!")
candidate = login("+919733333301", "ResumeTesting12!")


def inspect(path, width, name, expected):
    browser.viewport(width)
    browser.goto(path)
    check(browser.eval("document.documentElement.scrollWidth <= window.innerWidth"), f"{name} has no horizontal overflow at {width}px")
    check(browser.eval("[...document.images].every(i => i.complete && i.naturalWidth > 0)"), f"{name} has no broken images at {width}px")
    check(browser.eval(f"document.body.innerText.includes({json.dumps(expected)})"), f"{name} renders {expected!r} at {width}px")
    captures.append(browser.screenshot(f"{name}-{width}.png"))


browser.cookies()
for width in (1366, 390):
    inspect("/register", width, "registration", "Upload Resume")
    check(browser.eval("document.querySelector('#resume')?.accept.includes('.docx')"), f"registration resume accepts DOCX at {width}px")
    check(browser.eval("document.querySelector('[data-tech-options]')?.offsetHeight < 100"), f"picker does not expose the full catalog initially at {width}px")
    browser.eval("const q=document.querySelector('[data-tech-search]');q.value='k8s';q.dispatchEvent(new Event('input',{bubbles:true}));")
    check(browser.eval("[...document.querySelectorAll('[data-tech-option]')].some(o => o.offsetParent !== null && o.innerText.includes('Kubernetes'))"), f"k8s reveals Kubernetes at {width}px")
    captures.append(browser.screenshot(f"picker-k8s-{width}.png"))

browser.cookies(candidate)
for width in (1366, 390):
    inspect("/profile", width, "candidate-profile", "Updated_Resume.pdf")
    check(browser.eval("document.body.innerText.includes('Replace Resume')"), f"profile renders resume replacement at {width}px")
    check(browser.eval("document.querySelector('a[href*=" + json.dumps("profile/resume") + "]') !== null"), f"profile renders authorized resume action at {width}px")

visual_pdf = OUT / "visual-selection.pdf"
visual_pdf.write_bytes(b"%PDF-1.4\n1 0 obj\n<<>>\nendobj\ntrailer\n<<>>\n%%EOF\n")
browser.viewport(390)
browser.goto("/profile")
file_object = browser.eval("document.querySelector('#profile-resume')", object_result=True)["objectId"]
browser.call("DOM.setFileInputFiles", {"files": [str(visual_pdf)], "objectId": file_object})
browser.eval("document.querySelector('#profile-resume').dispatchEvent(new Event('change',{bubbles:true}))")
check(browser.eval("document.querySelector('[data-file-status]').innerText.includes('Ready to upload')"), "resume replacement shows selected-file ready state")
captures.append(browser.screenshot("resume-ready-390.png"))

browser.cookies(admin)
for width in (1366, 390):
    inspect("/admin/candidates?search=PDF%20Resume%20Candidate", width, "admin-candidates", "Resume")
    inspect("/admin/candidate?id=3", width, "admin-candidate-detail", "Updated_Resume.pdf")
    check(browser.eval("document.body.innerText.includes('View Resume') && document.body.innerText.includes('Download Resume')"), f"admin resume actions render at {width}px")
    inspect("/admin/technologies", width, "admin-technologies", "Technology directory")
    check(browser.eval("document.querySelectorAll('[data-technology-form]').length <= 25"), f"technology page remains paginated at {width}px")

print(json.dumps({"checks": len(checks), "captures": captures}, indent=2))
