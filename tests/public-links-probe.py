#!/usr/bin/env python3
"""Read-only production smoke check for CMS editor loading and visible public links."""
from html.parser import HTMLParser
from urllib.request import Request, urlopen
from urllib.parse import urlparse
import os
import sys

BASE = os.environ.get("MEDIAPITCH_STORE_URL", "https://store.mediapitch.in").rstrip("/")
GUIDE = "/guide/best-robot-vacuum-cleaners-india"

class EditorialLinks(HTMLParser):
    def __init__(self):
        super().__init__()
        self.stack = []
        self.links = []
        self.navigation = []

    def handle_starttag(self, tag, attrs):
        attrs = dict(attrs)
        inside = bool(self.stack and self.stack[-1][1])
        inside |= "prose" in attrs.get("class", "").split()
        if tag not in {"area", "base", "br", "col", "embed", "hr", "img", "input", "link", "meta", "param", "source", "track", "wbr"}:
            self.stack.append((tag, inside))
        if tag == "a":
            href = (attrs.get("href") or "").strip()
            if href:
                self.navigation.append(href)
                if inside and href.startswith(("https://", "http://", "/")):
                    self.links.append(href)

    def handle_startendtag(self, tag, attrs):
        self.handle_starttag(tag, attrs)
        self.handle_endtag(tag)

    def handle_endtag(self, tag):
        for pos in range(len(self.stack) - 1, -1, -1):
            if self.stack[pos][0] == tag:
                del self.stack[pos:]
                break

def fetch(path):
    target = BASE + path
    req = Request(target, headers={"User-Agent": "MediaPitchLinkHealth/1.0", "Accept": "text/html"})
    with urlopen(req, timeout=20) as response:
        if response.status != 200:
            raise AssertionError(f"{path}: unexpected HTTP {response.status}")
        return response.read().decode("utf-8", errors="replace"), response.headers

def main():
    home, _ = fetch("/")
    nav = EditorialLinks()
    nav.feed(home)
    if not any(href.startswith("/guide/") for href in nav.navigation):
        raise AssertionError("Homepage does not contain a buying-guide link")

    guide, _ = fetch(GUIDE)
    links = EditorialLinks()
    links.feed(guide)
    if len(set(links.links)) < 4:
        raise AssertionError(f"Published guide lost editorial links: only {len(set(links.links))} unique links found")

    # Authentication is not bypassed. We only inspect the publicly accessible
    # login page's policy; credentialed CMS content is validated in CI fixtures.
    _, headers = fetch("/admin/login")
    policy = headers.get("Content-Security-Policy", "")
    if "https://cdn.jsdelivr.net" not in policy:
        raise AssertionError("CMS Jodit/Chart.js assets are blocked by production CSP")

    print(f"PASS: homepage guide links visible; guide body has {len(set(links.links))} unique links; CMS assets allowed by CSP")

if __name__ == "__main__":
    try:
        main()
    except Exception as error:
        print(f"FAIL: {error}", file=sys.stderr)
        sys.exit(1)
