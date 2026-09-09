"""Local-only, non-mutating request boundary regressions."""
import urllib.request, urllib.error, http.cookiejar, re
base = 'http://127.0.0.1:8817'
client = urllib.request.build_opener(urllib.request.HTTPCookieProcessor(http.cookiejar.CookieJar()))
client.addheaders = [('User-Agent', 'Mozilla/5.0 Chrome/130.0.0.0 Safari/537.36'), ('X-Requested-With','XMLHttpRequest')]
def request(path, data=None, method=None, headers=None):
 try: response = client.open(urllib.request.Request(base+path,data=data,method=method,headers=headers or {}))
 except urllib.error.HTTPError as e: response=e
 return response.status,response.read().decode()
count=0
def expect(path,status,**kwargs):
 global count
 got,body=request(path,**kwargs)
 assert got==status,(path,got,body[:100])
 assert 'Fatal error' not in body
 count+=1
expect('/api/admin/music/links.php',401)
expect('/admin/login',422,data=b'csrf_token[]=bad')
request('/dev/tests/login_as_admin.php?to=/admin/dashboard')
_,body=request('/admin/music')
token=re.search(r'name="csrf-token" content="([^"]+)"',body).group(1)
expect('/api/admin/music/links.php',405,method='PATCH')
expect('/api/admin/music/links.php',403,data=b'csrf_token[]=bad')
expect('/api/admin/music/links.php',403,data=('csrf_token='+token).encode(),headers={'Sec-Fetch-Site':'cross-site'})
expect('/api/admin/music/links.php',422,data=('csrf_token='+token+'&action[]=create').encode())
expect('/api/admin/music/links.php',200)
expect('/admin/blog/create',403,data=b'csrf_token[]=bad')
expect('/admin/blog/create',200)
print(f'PASS: {count} request boundary checks')
