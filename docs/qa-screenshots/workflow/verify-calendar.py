from pathlib import Path
from playwright.sync_api import sync_playwright
import json,re
root=Path('C:/Users/Tenma/Jurnal-Kelas-App'); out=root/'docs/qa-screenshots/workflow'
html=(root/'storage/app/year-calendar-qa.html').read_text(encoding='utf-8')
html=re.sub(r'<script\b[^>]*>.*?</script>','',html,flags=re.S)
html=re.sub(r'<link[^>]+(?:stylesheet|modulepreload)[^>]*>','',html)
manifest=json.loads((root/'public/build/manifest.json').read_text())
html=html.replace('</head>',''.join(f'<link rel="stylesheet" href="http://127.0.0.1:8888/build/{v["file"]}">' for k,v in manifest.items() if k.endswith(('.scss','.css')))+'</head>')
results=[]
with sync_playwright() as p:
 b=p.chromium.launch(executable_path='C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',headless=True)
 page=b.new_page();page.route('http://127.0.0.1:8888/__qa_year',lambda r:r.fulfill(body=html,content_type='text/html'))
 for name,w,h in [('desktop',1920,1080),('laptop',1366,768),('tablet',768,1024),('mobile',390,844),('zoom200',960,540)]:
  page.set_viewport_size({'width':w,'height':h});page.goto('http://127.0.0.1:8888/__qa_year',wait_until='networkidle');page.screenshot(path=str(out/f'year-{name}.png'),full_page=True)
  scroll=page.evaluate('document.documentElement.scrollWidth');assert scroll<=w,(name,scroll)
  details=page.locator('details').first
  if details.count(): details.locator('summary').click();assert details.evaluate('(e)=>e.open')
  assert page.evaluate('document.documentElement.scrollWidth')<=w
  results.append({'viewport':name,'width':w,'scroll':scroll})
 b.close()
(out/'year-results.json').write_text(json.dumps(results,indent=2));print(results)
