const { chromium } = require('@playwright/test');
const { randomBytes } = require('node:crypto');
(async () => {
  const base = process.env.LIVE_TEST_URL;
  if (!/^https:\/\/[a-z0-9-]+\.trycloudflare\.com$/.test(base || '')) throw Error('Missing public test address');
  const browser = await chromium.launch({headless:true});
  const page = await browser.newPage();
  const errors=[];
  page.on('pageerror', e=>errors.push(e.message));
  try {
    const response=await page.goto(base+'/register',{waitUntil:'networkidle',timeout:60000});
    if(response.status()!==200)throw Error('Registration page is unavailable');
    const password=randomBytes(24).toString('hex');
    await page.locator('[name=name]').fill('کاربر آزمون آنلاین');
    await page.locator('[name=email]').fill(`live-check-${Date.now()}@browser.test`);
    await page.locator('[name=password]').fill(password);
    await page.locator('[name=password_confirmation]').fill(password);
    await page.getByRole('button',{name:'ساخت حساب'}).click();
    await page.waitForURL('**/dashboard',{timeout:30000});
    await page.locator('.page-heading:visible').waitFor();
    const send=async(path,method,data)=>page.evaluate(async({path,method,data})=>{
      const response=await fetch('/api'+path,{method,headers:{'Content-Type':'application/json','Accept':'application/json','X-CSRF-TOKEN':document.querySelector('meta[name="csrf-token"]').content},body:data?JSON.stringify(data):undefined});
      const result=await response.json();
      if(!response.ok)throw Error('Application request failed: '+response.status);
      return result;
    },{path,method,data});
    const project=await send('/projects','POST',{name:'پروژه آزمون آنلاین',description:'بررسی عملکرد واقعی سایت',goal:{title:'ذخیره اطلاعات از اینترنت',success_criteria:'ساخت و تکمیل تسک پس از بارگذاری مجدد',start_date:'2026-10-14',end_date:'2026-11-14'},stages:[{title:'طراحی'},{title:'توسعه'}]});
    const data=await send('/workspace','GET');
    const saved=data.projects.find(p=>p.id===project.id);
    if(!saved||saved.stages.length!==2)throw Error('Project was not persisted');
    const task=await send('/tasks','POST',{project_id:project.id,stage_id:saved.stages[0].id,title:'تسک واقعی آنلاین',description:'آزمون پایگاه داده MySQL',assigned_to:data.user.id,start_date:'2026-10-14',due_date:'2026-11-14',members:[],labels:[],subtasks:[{title:'بررسی ذخیره',completed:false}],completed:false});
    await page.goto(base+'/projects/'+project.id,{waitUntil:'networkidle'});
    await page.getByRole('button',{name:'تسک واقعی آنلاین',exact:true}).waitFor();
    await page.getByRole('button',{name:'تکمیل تسک واقعی آنلاین',exact:true}).click();
    await page.waitForTimeout(500);
    const updated=await send('/tasks/'+task.id,'GET');
    if(!updated.completed_at||updated.subtasks.length!==1)throw Error('Task update did not persist');
    await page.reload({waitUntil:'networkidle'});
    await page.getByRole('combobox',{name:'فیلتر تسک‌های پروژه'}).selectOption('done');
    await page.getByRole('button',{name:'تسک واقعی آنلاین',exact:true}).waitFor();
    const badCsrf=await page.request.post(base+'/api/projects',{data:{}});
    if(badCsrf.status()!==419)throw Error('CSRF protection was not active');
    if(errors.length)throw Error('Browser runtime errors detected');
    console.log('Public HTTPS acceptance check passed: registration, authenticated project and task creation, MySQL persistence after reload, task completion, subtasks, and CSRF.');
  } finally {await browser.close();}
})().catch(e=>{console.error(e.message);process.exit(1)});
