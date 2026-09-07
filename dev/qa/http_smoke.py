"""Read-only route/SEO checks against the local PHP server."""
import json, sys, urllib.request, urllib.error, http.cookiejar, xml.etree.ElementTree as ET
from html.parser import HTMLParser
from urllib.parse import urlparse
base=sys.argv[1] if len(sys.argv)>1 else 'http://127.0.0.1:8817'
if urlparse(base).hostname not in ('127.0.0.1','localhost'): raise SystemExit('Local preview only')
class Page(HTMLParser):
 def __init__(self): super().__init__(); self.tags={}; self.canonical=[];self.robots=[];self.assets=[]
 def handle_starttag(self, tag, attrs):
  a=dict(attrs);self.tags[tag]=self.tags.get(tag,0)+1
  if tag=='link' and a.get('rel')=='canonical':self.canonical.append(a.get('href'))
  if tag=='meta' and a.get('name')=='robots':self.robots.append(a.get('content'))
  if tag=='script' and a.get('src'):self.assets.append(a['src'])
opener=urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
opener.addheaders=[('User-Agent','Mozilla/5.0 Chrome/130.0.0.0 Safari/537.36')]
def get(path, data=None, headers=None):
 try:r=opener.open(urllib.request.Request(base+path,data=data,headers=headers or {}),timeout=20)
 except urllib.error.HTTPError as error:r=error
 return r.status,r.headers,r.read().decode('utf-8')
checks=0
routes=['/','/about','/portfolio','/blog','/gallery','/travel','/updates','/rmrp','/contact','/more','/music','/videos','/downloads','/yunobot','/post-code','/science-corner','/comedy','/sitemap','/search','/privacy','/terms','/cookies']
for path in routes:
 status,headers,html=get(path);p=Page();p.feed(html)
 assert status==200,(path,status)
 assert p.tags.get('main')==1,(path,'main',p.tags.get('main'))
 assert p.tags.get('h1')==1,(path,'h1',p.tags.get('h1'))
 assert len(p.canonical)==1,(path,'canonical')
 assert 'Fatal error' not in html and 'Uncaught Exception' not in html,path
 checks+=1
for path in ['/404','/not-a-real-page','/blog/not-a-real-post','/blog?page=999999','/updates?page=999999']:
 status,_,html=get(path);assert status==404,(path,status);assert 'noindex,follow' in html,path;checks+=1
for path in ['/sitemap.xml','/blog.xml','/updates.xml','/rmrp.xml']:
 status,_,xml=get(path);assert status==200,(path,status);ET.fromstring(xml);checks+=1
_,_,html=get('/updates?page=2&view=list&utm_source=test');p=Page();p.feed(html)
assert p.canonical==[base+'/updates?page=2'],p.canonical;checks+=1
_,_,html=get('/updates?per=20&page=2');p=Page();p.feed(html)
assert p.canonical==[base+'/updates?per=20&page=2'],p.canonical;checks+=1
status,_,_=get('/api/admin/regenerate-sitemap.php',b'csrf_token=invalid');assert status in (401,403),status;checks+=1
get('/dev/tests/login_as_admin.php?to=/admin/dashboard')
admin=['dashboard','blog','blog/create','portfolio','portfolio/create','gallery','updates','updates/create','updates/edit?id=23','rmrp','rmrp/create','music','videos','downloads','socials','travel','settings','tracker-codes','tracker-codes/create','search']
for path in admin:
 status,headers,html=get('/admin/'+path);p=Page();p.feed(html)
 assert status==200,(path,status)
 assert p.tags.get('main')==1,(path,'main',p.tags.get('main'))
 assert p.tags.get('h1')==1,(path,'h1',p.tags.get('h1'))
 assert any('noindex' in x for x in p.robots),(path,'indexable admin')
 assert 'Fatal error' not in html and 'Uncaught Exception' not in html,path
 if path in ['blog/create','updates/create','rmrp/create']:assert any('admin-editor.js' in x for x in p.assets),path
 checks+=1
status,_,_=get('/api/admin/regenerate-sitemap.php',b'csrf_token=invalid');assert status==403,status;checks+=1
status,headers,payload=get('/api/yunobot-knowledge.php');snapshot=json.loads(payload)
assert status==200 and snapshot['version']==2 and snapshot['scope']=='blog'
assert headers.get('X-Robots-Tag')=='noindex' and 'public' in headers.get('Cache-Control','');checks+=1
status,_,_=get('/api/yunobot-knowledge.php',headers={'If-None-Match':headers['ETag']});assert status==304;checks+=1
status,_,_=get('/api/yunobot-knowledge.php',b'');assert status==405;checks+=1
print(json.dumps({'passed':checks,'public_routes':len(routes),'admin_routes':len(admin),'checks':'landmarks, status codes, canonical, pagination, XML, admin indexing, editor loading, CSRF'},indent=2))
