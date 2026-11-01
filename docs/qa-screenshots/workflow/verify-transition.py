from pathlib import Path
from playwright.sync_api import sync_playwright
import json,re
root=Path('C:/Users/Tenma/Jurnal-Kelas-App')
out=root/'docs/qa-screenshots/workflow'
out.mkdir(parents=True,exist_ok=True)
manifest=json.loads((root/'public/build/manifest.json').read_text())
css=[v['file'] for k,v in manifest.items() if k.endswith('.scss') or k.endswith('.css')]
results=[]
with sync_playwright() as p:
    browser=p.chromium.launch(executable_path='C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',headless=True)
    page=browser.new_page()
    for state in ['empty','ready']:
        html=(root/f'storage/app/transition-{state}-qa.html').read_text(encoding='utf-8')
        html=re.sub(r'<script\b[^>]*>.*?</script>','',html,flags=re.S)
        html=re.sub(r'<link[^>]+(?:stylesheet|modulepreload)[^>]*>','',html)
        html=html.replace('</head>',''.join(f'<link rel="stylesheet" href="http://127.0.0.1:8888/build/{f}">' for f in css)+'</head>')
        page.route('http://127.0.0.1:8888/__qa_fixture',lambda route:route.fulfill(status=200,body=html,content_type='text/html'))
        for label,w,h in [('desktop',1920,1080),('laptop',1366,768),('tablet',768,1024),('mobile',390,844),('zoom200',960,540)]:
            page.set_viewport_size({'width':w,'height':h})
            page.goto('http://127.0.0.1:8888/__qa_fixture',wait_until='networkidle')
            page.screenshot(path=str(out/f'{state}-{label}.png'),full_page=True)
            metrics=page.evaluate('''() => ({width:innerWidth,scroll:document.documentElement.scrollWidth,selectors:document.querySelectorAll('select').length,simulation:document.querySelector('form[action*=pratinjau] button').disabled,buttonTop:document.querySelector('form[action*=pratinjau]').getBoundingClientRect().top})''')
            assert metrics['scroll']<=w,metrics
            assert metrics['selectors']==2,metrics
            assert metrics['simulation']==(state=='empty'),metrics
            page.locator('.transition-page details > summary').first.click()
            assert page.locator('.transition-page details').first.evaluate('(e)=>e.open')
            assert page.evaluate('document.documentElement.scrollWidth')<=w
            results.append({'state':state,'viewport':label,**metrics,'details_toggle':True})
        page.unroute('http://127.0.0.1:8888/__qa_fixture')
    browser.close()
(out/'results.json').write_text(json.dumps(results,indent=2))
print(json.dumps(results,indent=2))
