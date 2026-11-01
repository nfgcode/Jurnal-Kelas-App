"""Disposable MySQL verification. No development DB connections or Docker socket in runners."""
import json, os, pathlib, secrets, subprocess, time, sys, re, hashlib, argparse
parser = argparse.ArgumentParser(description=__doc__)
parser.add_argument("--foundation-only", action="store_true", help="Run non-DB isolation tests only; never create MySQL.")
parser.add_argument("--scripts", nargs="+", choices=["migrations", "constraints", "concurrency", "restore_mysql"], default=["migrations", "constraints", "restore_mysql"])
options = parser.parse_args()
class FoundationComplete(Exception): pass
if not __debug__: raise RuntimeError('Optimized Python disables safety assertions; refusing execution.')
REPO = pathlib.Path(__file__).resolve().parents[2]
BASE = pathlib.Path('C:/Users/Tenma/AppData/Local/hermes/cache/scratch') if os.name == 'nt' else pathlib.Path(os.environ.get('TMPDIR', '/tmp'))
TOKEN = secrets.token_hex(16)
ROOT = BASE / ('phase2b-' + TOKEN)
ROOT.mkdir(parents=True, mode=0o700)
LABEL = 'org.jurnal.phase2b=' + TOKEN
NET, DB, PHP = ['phase2b-' + TOKEN + '-' + s for s in ('net','db','php')]
report = {'token':TOKEN,'root':str(ROOT),'baseline':str(BASE/'phase2b-baseline.json'),'steps':[],'resources':{'network':NET,'mysql':DB,'php':PHP},'commands_redacted':['docker run --network=none --read-only <pinned-php-image> tests/run.php <foundation-tests>','docker network create --internal --label org.jurnal.phase2b=<run-token> <owned-network>','docker run --network <owned-network> --tmpfs /var/lib/mysql --env-file <ephemeral-random-credentials> <pinned-mysql8-image>','docker run --read-only --network <owned-network> --tmpfs /scratch --mount <repo>:readonly <pinned-php-image>','docker exec <owned-runner> php /repo/tests/Integration/Phase2B_{migrations,constraints,concurrency}.php']}
hashed_files = sorted(p for folder in ('app','database/migrations','tests/Integration') for p in (REPO/folder).rglob('*') if p.is_file() and p.suffix in ('.php','.py'))
if options.foundation_only:
    hashed_files = [REPO / p for p in ('tests/run.php','tests/bootstrap.php','tests/TestCase.php','tests/Unit/SafetyFoundationTest.php','tests/Feature/SafetyApplicationTest.php','tests/Fixtures/SafetyEnvironmentProbeTest.php','tests/Integration/mysql_guard.php','tests/Integration/Phase2B_runner.py')]
def source_hashes(): return {str(p.relative_to(REPO)).replace('\\','/'):hashlib.sha256(p.read_bytes()).hexdigest() for p in hashed_files}
report['source_hashes_before']=source_hashes()
owned = []
def cmd(args, **kw):
    p = subprocess.run(['docker', *args], capture_output=True, text=True, **kw)
    if p.returncode: raise RuntimeError('Docker command failed: ' + ' '.join(args[:3]) + '\n' + p.stderr[:3000] + p.stdout[:3000])
    return p.stdout.strip()
def inspect(name, network=False):
    return json.loads(cmd((['network','inspect'] if network else ['inspect'])+[name]))[0]
def check(name, network=False):
    obj = inspect(name, network)
    labels = obj.get('Labels',{}) if network else obj['Config'].get('Labels',{})
    assert labels.get('org.jurnal.phase2b') == TOKEN, 'Ownership mismatch'
    return obj
def runphp(script, *args):
    check(PHP)
    return cmd(['exec',PHP,'php','-d','sys_temp_dir=/scratch/tmp','/repo/tests/Integration/'+script,*args])
try:
    mysql_image = json.loads(cmd(['image','inspect','mysql:8.0']))[0]['Id']
    php_image = json.loads(cmd(['image','inspect','jurnal-kelas-app-app']))[0]['Id']
    report['images']={'mysql':mysql_image,'php':php_image}
    # Safety foundation executes before any database container is created.
    foundation = cmd(['run','--rm','--pull=never','--network=none','--read-only','--tmpfs','/scratch:rw,nosuid,nodev,mode=1777','--mount','type=bind,src='+str(REPO)+',dst=/repo,readonly','--workdir','/repo','--entrypoint','php','-e','TMPDIR=/scratch',php_image,'tests/run.php','tests/Unit/SafetyFoundationTest.php','tests/Feature/SafetyApplicationTest.php'])
    report['foundation']=foundation
    match=re.search(r'OK \((\d+) tests, (\d+) assertions\)',foundation)
    assert match, 'Foundation did not pass'
    report['foundation_counts']={'tests':int(match[1]),'assertions':int(match[2])}
    if options.foundation_only:
        report['mode']='foundation-only'
        raise FoundationComplete()
    for script in options.scripts:
        if not (REPO / ('tests/Integration/Phase2B_'+script+'.php')).is_file():
            raise RuntimeError('Phase2B API harness pending: '+script)
    cmd(['network','create','--internal','--label',LABEL,NET]); owned.append(('network',NET))
    assert check(NET,True)['Internal'] is True
    password=secrets.token_hex(32)
    env=ROOT/'mysql.env'
    env.write_text('MYSQL_ROOT_PASSWORD='+password+'\nMYSQL_DATABASE=phase2b_'+TOKEN+'\n')
    cmd(['run','-d','--pull=never','--name',DB,'--label',LABEL,'--network',NET,'--network-alias','disposable-db','--tmpfs','/var/lib/mysql:rw,nosuid,nodev,size=1073741824','--env-file',str(env),mysql_image,'--performance-schema=ON','--log-bin-trust-function-creators=1','--innodb-flush-log-at-trx-commit=2']); owned.append(('container',DB))
    env.unlink()
    obj=check(DB)
    assert obj['Image']==mysql_image and not obj['HostConfig']['PortBindings']
    assert obj['HostConfig']['Tmpfs'].get('/var/lib/mysql')
    assert not [m for m in obj['Mounts'] if m['Type']=='volume']
    assert list(obj['NetworkSettings']['Networks'])==[NET]
    report['isolation']={'internal_network':True,'mysql_tmpfs':True,'published_ports':False,'persistent_volumes':False}
    php_env=ROOT/'php.env'
    php_env.write_text('PHASE2B_TOKEN='+TOKEN+'\nPHASE2B_PASSWORD='+password+'\nPHASE2B_DATABASE=phase2b_'+TOKEN+'\nTMPDIR=/scratch\n')
    cmd(['run','-d','--pull=never','--name',PHP,'--label',LABEL,'--network',NET,'--read-only','--tmpfs','/scratch:rw,nosuid,nodev,mode=1777','--mount','type=bind,src='+str(REPO)+',dst=/repo,readonly','--workdir','/repo','--env-file',str(php_env),'--entrypoint','sleep',php_image,'1800']); owned.append(('container',PHP))
    php_env.unlink()
    obj=check(PHP)
    assert obj['Image']==php_image and obj['HostConfig']['ReadonlyRootfs']
    assert list(obj['NetworkSettings']['Networks'])==[NET]
    for attempt in range(90):
        p=subprocess.run(['docker','exec',PHP,'php','-d','sys_temp_dir=/scratch','/repo/tests/Integration/Phase2B_bootstrap.php','probe'],capture_output=True,text=True)
        if p.returncode==0: break
        time.sleep(1)
    else: raise RuntimeError('Disposable MySQL readiness timeout: '+p.stderr[:1000])
    identity=json.loads(p.stdout)
    report['identity']=identity
    assert identity['server_uuid'] and identity['database']=='phase2b_'+TOKEN
    cmd(['exec',PHP,'php','-r','file_put_contents("/scratch/server-uuid", $argv[1]);',identity['server_uuid']])
    # Recheck exact resources immediately before schema mutations.
    mysql_state=check(DB)
    runner_state=check(PHP)
    network_state=check(NET,True)
    assert mysql_state['Image']==mysql_image and mysql_state['HostConfig']['Tmpfs'].get('/var/lib/mysql')
    assert not mysql_state['HostConfig']['PortBindings'] and not any(m['Type']=='volume' for m in mysql_state['Mounts'])
    assert list(mysql_state['NetworkSettings']['Networks'])==[NET]
    assert list(runner_state['NetworkSettings']['Networks'])==[NET]
    assert set(network_state['Containers'])=={mysql_state['Id'],runner_state['Id']} and network_state['Internal']
    for script in options.scripts:
        execution=subprocess.run(['docker','exec',PHP,'php','-d','sys_temp_dir=/scratch/tmp','/repo/tests/Integration/Phase2B_'+script+'.php'],capture_output=True,text=True)
        (ROOT/(script+'-output.txt')).write_text(execution.stdout+'\n'+execution.stderr)
        report[script]=json.loads(execution.stdout)
        if execution.returncode: raise RuntimeError(script+' verification failed; inspect results.json')
except FoundationComplete:
    pass
except Exception as e:
    report['error']=str(e)
finally:
    report['cleanup']=[]
    for typ,name in reversed(owned):
        try:
            check(name,typ=='network')
            cmd(['network','rm',name] if typ=='network' else ['rm','-f',name])
            try: inspect(name,typ=='network')
            except RuntimeError: report['cleanup'].append({'name':name,'removed':True})
            else: raise RuntimeError('Resource remains')
        except Exception as e: report['cleanup'].append({'name':name,'error':str(e)})
    for p in ROOT.glob('*.env'): p.unlink()
    report['assertions_total']=report.get('foundation_counts',{}).get('assertions',0)+report.get('migrations',{}).get('assertions',0)+report.get('constraints',{}).get('assertions',0)+report.get('concurrency',{}).get('assertions',0)+report.get('restore_mysql',{}).get('assertions',0)
    report['source_hashes_after']=source_hashes()
    report['source_hashes_unchanged']=report['source_hashes_before']==report['source_hashes_after']
    if not report['source_hashes_unchanged']:
        report['changed_sources']=[k for k,v in report['source_hashes_before'].items() if report['source_hashes_after'].get(k)!=v]
        report['error']=(report.get('error','')+'; Source changed during verification').lstrip('; ')
    (ROOT/'results.json').write_text(json.dumps(report,indent=2))
    print(json.dumps({'artifact':str(ROOT/'results.json'),'error':report.get('error'),'migration_count':report.get('migrations',{}).get('migration_count'),'assertions_total':report['assertions_total'],'cleanup':report['cleanup']}))
if 'error' in report or any(not item.get('removed') for item in report.get('cleanup', [])): sys.exit(1)
