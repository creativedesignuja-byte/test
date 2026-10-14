"""Publish only a temporary test URL and expiry, never environment or database data."""
import json,os,subprocess,sys,tempfile
from datetime import datetime,timezone
from pathlib import Path
state=sys.argv[1]
assert state in ['ready','ended']
env=os.environ.copy()
with tempfile.TemporaryDirectory() as tmp:
    env['GIT_INDEX_FILE']=str(Path(tmp)/'index')
    def git(*args,input=None):
        return subprocess.check_output(['git',*args],input=input,text=True,env=env).strip()
    status={'state':state,'url':os.environ['LIVE_TEST_URL'],'expires_at':os.environ['LIVE_TEST_EXPIRES_AT'],'updated_at':datetime.now(timezone.utc).isoformat(),'run_url':f"https://github.com/{os.environ['GITHUB_REPOSITORY']}/actions/runs/{os.environ['GITHUB_RUN_ID']}",'temporary':True}
    parent=None
    if git('ls-remote','--heads','origin','live-status'):
        git('fetch','--depth=1','origin','live-status');parent=git('rev-parse','FETCH_HEAD')
    git('read-tree','--empty')
    for name,content in {'status.json':json.dumps(status,indent=2)+'\n','README.md':f"# Rasta live acceptance test\n\nState: **{state}**\n\n{status['url']}\n\nExpires: {status['expires_at']}\n\nThis is a temporary real Laravel/MySQL test session. Data is discarded when the session ends.\n\n[Workflow]({status['run_url']})\n"}.items():
        blob=git('hash-object','-w','--stdin',input=content)
        git('update-index','--add','--cacheinfo','100644',blob,name)
    tree=git('write-tree')
    args=['-c','user.name=github-actions[bot]','-c','user.email=41898282+github-actions[bot]@users.noreply.github.com','commit-tree',tree,'-m',f'Live application test: {state}']
    if parent:args+=['-p',parent]
    commit=git(*args)
    git('push','origin',commit+':refs/heads/live-status')
    print('Published test session status:',state)
