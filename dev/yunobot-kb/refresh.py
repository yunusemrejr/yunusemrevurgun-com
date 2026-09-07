"""Build a reproducible, public-only passage corpus. No private/admin data.

python3 dev/yunobot-kb/refresh.py [https://yunusemrevurgun.com]
The cached corpus supports offline rebuilds and is the source of every excerpt.
"""
import concurrent.futures
import hashlib
import json
import re
import sys
import time
import urllib.parse
import urllib.request
import xml.etree.ElementTree as ET
from html.parser import HTMLParser
from pathlib import Path

ROOT = Path(__file__).resolve().parents[2]
BASE = (sys.argv[1] if len(sys.argv) > 1 else 'https://yunusemrevurgun.com').rstrip('/')
ALLOWED = {'', 'about', 'portfolio', 'travel', 'contact', 'post-code', 'science-corner', 'music', 'comedy', 'downloads', 'videos', 'blog', 'updates', 'rmrp'}

class Passages(HTMLParser):
    VOID = {'br', 'img', 'hr', 'input', 'meta', 'link', 'source', 'wbr'}
    SKIP = {'script', 'style', 'nav', 'form', 'svg', 'button', 'footer', 'noscript'}
    def __init__(self):
        super().__init__(convert_charrefs=True)
        self.root = {'tag': 'root', 'attrs': {}, 'children': []}
        self.stack = [self.root]
        self.parts, self.title = [], ''

    def handle_starttag(self, tag, attrs):
        node = {'tag': tag, 'attrs': dict(attrs), 'children': []}
        self.stack[-1]['children'].append(node)
        if tag not in self.VOID: self.stack.append(node)

    def handle_endtag(self, tag):
        for i in range(len(self.stack)-1, 0, -1):
            if self.stack[i]['tag'] == tag:
                self.stack = self.stack[:i]
                break

    def handle_data(self, data): self.stack[-1]['children'].append(data)

    def close(self):
        super().close()
        heading = ''
        def text(node):
            if isinstance(node, str): return node
            if node['tag'] in self.SKIP: return ''
            if node['tag'] == 'br': return ' · '
            return ''.join(text(child) for child in node['children'])
        def walk(node, inside=False):
            nonlocal heading
            if isinstance(node, str): return
            tag, attrs = node['tag'], node['attrs']
            if tag in self.SKIP or any(x in attrs.get('class', '') for x in ['ui-author', 'ui-tags', 'ui-feed-meta', 'ui-card-meta']): return
            inside = inside or tag == 'main'
            blocks = [child for child in node['children'] if isinstance(child, dict) and child['tag'] in {'div','p','li','h1','h2','h3','ul','ol','blockquote','section','article'}]
            value = re.sub(r'\s+', ' ', text(node)).strip() if inside and (tag in {'h1','h2','h3','p','li'} or (tag == 'div' and not blocks)) else ''
            if value and tag.startswith('h'):
                heading = value
                if tag == 'h1' and not self.title: self.title = value
            elif value and len(value) >= 35 and len(value.split()) >= 5 and not re.match(r'^(Published |Read more|Share |Previous|Next|©|No .+ (yet|available))', value):
                sentences = re.split(r'(?<=[.!?])\s+(?=[A-ZİÖÜ])', value)
                chunk = ''
                for sentence in sentences:
                    if chunk and len(chunk) + len(sentence) > 800:
                        self.parts.append((heading, chunk)); chunk = ''
                    chunk = (chunk + ' ' + sentence).strip()
                if chunk: self.parts.append((heading, chunk))
                return
            for child in node['children']: walk(child, inside)
        def find(node, predicate):
            if isinstance(node, str): return []
            found = [node] if predicate(node) else []
            return found + [hit for child in node['children'] for hit in find(child, predicate)]
        rich = find(self.root, lambda node: 'ui-rich-content' in node['attrs'].get('class', ''))
        if rich:
            titles = find(self.root, lambda node: node['tag'] == 'h1')
            self.title = re.sub(r'\s+', ' ', text(titles[0])).strip() if titles else ''
            heading = self.title
            for node in rich: walk(node, True)
        else:
            walk(self.root)


def fetch(url):
    cache = ROOT / 'dev/_work/yunobot-fetch'
    cache.mkdir(parents=True, exist_ok=True)
    cached = cache / (hashlib.sha256(url.encode()).hexdigest() + '.html')
    if cached.exists() and time.time() - cached.stat().st_mtime < 3600:
        return cached.read_text()
    request = urllib.request.Request(url, headers={'User-Agent': 'YunoBot-public-corpus/2.0'})
    for attempt in range(3):
        try:
            with urllib.request.urlopen(request, timeout=45) as response:
                if urllib.parse.urlparse(response.url).netloc != urllib.parse.urlparse(BASE).netloc:
                    raise ValueError('Cross-origin redirect rejected')
                html = response.read().decode('utf-8')
                cached.write_text(html)
                return html
        except Exception:
            if attempt == 2:
                print('Could not fetch ' + url, file=sys.stderr)
                raise


def collect(url):
    parser = Passages()
    parser.feed(fetch(url))
    parser.close()
    page = urllib.parse.urlparse(url).path.strip('/').split('/')[0] or 'home'
    seen, rows = set(), []
    for heading, text in parser.parts:
        if text in seen: continue
        seen.add(text)
        rows.append({'page': page, 'url': url, 'title': parser.title or page, 'heading': heading, 'text': text})
    return rows

if __name__ == '__main__':
    sitemap = ET.fromstring(fetch(BASE + '/sitemap.xml'))
    paths = {'/' + page for page in ALLOWED}
    for node in sitemap.iter():
        if node.tag.endswith('}loc'):
            parsed = urllib.parse.urlparse(node.text)
            path = parsed.path.rstrip('/') or '/'
            parts = path.strip('/').split('/')
            if parts[0] in ALLOWED and not parsed.query and not parsed.fragment:
                paths.add(path)
    urls = sorted(BASE + path for path in paths)
    # Fail the build on fetch failure; never replace a complete corpus with a partial one.
    with concurrent.futures.ThreadPoolExecutor(max_workers=3) as pool:
        groups = list(pool.map(collect, urls))
    passages = [row for group in groups for row in group]
    if len(passages) < 20: raise ValueError('Unexpectedly small corpus; previous corpus preserved')
    data = {'version': 2, 'origin': BASE, 'documents': len(urls), 'passages': passages}
    canonical = json.dumps(data, ensure_ascii=False, separators=(',', ':'))
    data['digest'] = hashlib.sha256(canonical.encode()).hexdigest()[:16]
    target = ROOT / 'dev/yunobot-kb/corpus.json'
    target.write_text(json.dumps(data, ensure_ascii=False, indent=2) + '\n')
    print(f'Collected {len(passages)} passages from {len(urls)} public pages; digest {data["digest"]}')
