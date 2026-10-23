// ——— CoreCraft Admin Console ———
let adminState={view:'dashboard',components:[],rules:[],regions:[],users:[],reports:[],content:[]};

function adminEsc(value){
  return String(value==null?'':value).replace(/[&<>"']/g,function(c){
    return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c];
  });
}
function adminVal(id){
  const el=document.getElementById(id);
  return el?String(el.value).trim():'';
}
function setAdminStatus(message,type){
  const el=document.getElementById('admin-status');
  if(!el)return;
  el.textContent=message||'';
  el.className='admin-status'+(type?' '+type:'');
}

async function adminApi(action,payload){
  if(!window._user||!window._user.authToken)throw new Error('Please sign in again');
  const body=Object.assign({action:action,email:window._user.email,auth_token:window._user.authToken},payload||{});
  let response;
  try{
    response=await fetch('admin_api.php',{method:'POST',cache:'no-store',headers:{'Content-Type':'application/json'},body:JSON.stringify(body)});
  }catch(error){
    throw new Error('Could not reach admin_api.php ('+(error&&error.message?error.message:'network error')+')');
  }
  let data;
  try{data=await response.json();}catch(error){throw new Error('The admin service returned an invalid response (HTTP '+response.status+')');}
  if(!data||typeof data!=='object')throw new Error('Unexpected admin response');
  if(!response.ok||!data.success)throw new Error(data.error||('Request failed (HTTP '+response.status+')'));
  return data;
}

function adminModal(html){
  let overlay=document.getElementById('admin-modal-overlay');
  if(!overlay){
    overlay=document.createElement('div');
    overlay.id='admin-modal-overlay';
    overlay.className='admin-modal-overlay';
    overlay.addEventListener('click',function(event){if(event.target===overlay)closeAdminModal();});
    document.body.appendChild(overlay);
  }
  overlay.innerHTML='<div class="admin-modal">'+html+'</div>';
  overlay.classList.add('open');
}
function closeAdminModal(){
  const overlay=document.getElementById('admin-modal-overlay');
  if(overlay){overlay.classList.remove('open');overlay.innerHTML='';}
}
function adminModalStatus(message,type){
  const el=document.getElementById('admin-modal-status');
  if(el){el.textContent=message||'';el.className='admin-modal-status'+(type?' '+type:'');}
}

// ——— Entry point (called from showPage) ———
function initAdminPage(){
  if(!window._user||!window._user.isAdmin){showPage('home');return;}
  const who=document.getElementById('admin-side-user');
  if(who)who.textContent=(window._user.name||'Admin')+' · '+((window._user.adminRole||'admin'));
  loadAdminView(adminState.view);
}

function switchAdminView(view,button){
  adminState.view=view;
  document.querySelectorAll('.admin-view').forEach(function(panel){panel.classList.remove('active');});
  const target=document.getElementById('admin-view-'+view);
  if(target)target.classList.add('active');
  document.querySelectorAll('.admin-nav-item').forEach(function(item){item.classList.remove('active');});
  if(button)button.classList.add('active');
  const titles={
    dashboard:['Dashboard','Platform health at a glance.'],
    components:['Component Catalog','Add, update and retire hardware parts and pricing.'],
    rules:['Compatibility Rules','Maintain the checks behind build compatibility.'],
    users:['Users & Roles','Manage accounts, roles and access.'],
    moderation:['Moderation','Review reports and remove abusive content.'],
    pricing:['Pricing Feeds','Sync regional prices used by AI estimation.'],
    analytics:['Analytics & Health','Usage trends, traffic and API health.'],
    logs:['System Logs','Errors, warnings and the admin audit trail.']
  };
  const meta=titles[view]||['Admin',''];
  const titleEl=document.getElementById('admin-view-title');
  const noteEl=document.getElementById('admin-view-note');
  if(titleEl)titleEl.textContent=meta[0];
  if(noteEl)noteEl.textContent=meta[1];
  setAdminStatus('');
  loadAdminView(view);
}

function loadAdminView(view){
  if(view==='dashboard')loadAdminDashboard();
  else if(view==='components')loadAdminComponents();
  else if(view==='rules')loadAdminRules();
  else if(view==='users')loadAdminUsers();
  else if(view==='moderation'){loadAdminReports();loadAdminContent();}
  else if(view==='pricing')loadAdminRegions();
  else if(view==='analytics')loadAdminAnalytics();
  else if(view==='logs')loadAdminLogs();
}
function refreshAdminView(){loadAdminView(adminState.view);}

// ——— Charts (inline SVG) ———
const ADMIN_PALETTE=['#00bfff','#a78bfa','#ff6b35','#22c55e','#f472b6','#facc15','#38bdf8'];
const ADMIN_EVENT_LABELS={search:'Searches',build_check:'Build checks',build_save:'Build saves',compat_fail:'Compat fails'};
function adminShortDate(d){const p=d.split('-');return p[1]+'/'+p[2];}
function adminSmoothPath(pts){
  if(!pts.length)return '';
  let d='M'+pts[0][0]+','+pts[0][1];
  for(let i=1;i<pts.length;i++){
    const cx=(pts[i-1][0]+pts[i][0])/2;
    d+=' C'+cx+','+pts[i-1][1]+' '+cx+','+pts[i][1]+' '+pts[i][0]+','+pts[i][1];
  }
  return d;
}
// series: [{name,color,values[]}], labels: string[]
function adminAreaChart(el,labels,series){
  if(!el)return;
  const W=640,H=240,L=34,R=10,T=12,B=26,uid='g'+Math.random().toString(36).slice(2,7);
  const max=Math.max(4,Math.ceil(Math.max.apply(null,series.reduce(function(a,s){return a.concat(s.values);},[0]))*1.15));
  const n=labels.length,step=(W-L-R)/Math.max(1,n-1);
  let svg='<svg viewBox="0 0 '+W+' '+H+'" preserveAspectRatio="none" class="admin-svg"><defs>';
  series.forEach(function(s,i){svg+='<linearGradient id="'+uid+i+'" x1="0" y1="0" x2="0" y2="1"><stop offset="0%" stop-color="'+s.color+'" stop-opacity=".35"/><stop offset="100%" stop-color="'+s.color+'" stop-opacity="0"/></linearGradient>';});
  svg+='</defs>';
  for(let g=0;g<=4;g++){
    const y=T+(H-T-B)*g/4;
    svg+='<line x1="'+L+'" x2="'+(W-R)+'" y1="'+y+'" y2="'+y+'" stroke="#17283a" stroke-dasharray="3 4"/><text x="'+(L-6)+'" y="'+(y+4)+'" text-anchor="end" class="admin-axis">'+Math.round(max*(1-g/4))+'</text>';
  }
  const every=Math.ceil(n/7);
  labels.forEach(function(lb,i){if(i%every===0||i===n-1)svg+='<text x="'+(L+i*step)+'" y="'+(H-6)+'" text-anchor="middle" class="admin-axis">'+adminEsc(adminShortDate(lb))+'</text>';});
  series.forEach(function(s,i){
    const pts=s.values.map(function(v,j){return [L+j*step,T+(H-T-B)*(1-v/max)];});
    const line=adminSmoothPath(pts);
    svg+='<path d="'+line+' L'+pts[pts.length-1][0]+','+(H-B)+' L'+pts[0][0]+','+(H-B)+' Z" fill="url(#'+uid+i+')"/>';
    svg+='<path d="'+line+'" fill="none" stroke="'+s.color+'" stroke-width="2.4" stroke-linecap="round"/>';
    pts.forEach(function(p,j){svg+='<circle cx="'+p[0]+'" cy="'+p[1]+'" r="3.4" fill="'+s.color+'" class="admin-dot"><title>'+adminEsc(s.name+' · '+labels[j]+': '+s.values[j])+'</title></circle>';});
  });
  el.innerHTML=svg+'</svg>';
}
function adminDonut(el,rows,centerLabel){
  if(!el)return;
  const total=rows.reduce(function(a,r){return a+r.total;},0);
  if(!total){el.innerHTML='<div class="admin-empty">No data yet.</div>';return;}
  const R=70,C=2*Math.PI*R;let offset=0;
  let svg='<svg viewBox="0 0 200 200" class="admin-donut"><circle cx="100" cy="100" r="'+R+'" fill="none" stroke="#0c1420" stroke-width="26"/>';
  rows.forEach(function(r,i){
    const len=C*r.total/total;
    svg+='<circle cx="100" cy="100" r="'+R+'" fill="none" stroke="'+ADMIN_PALETTE[i%ADMIN_PALETTE.length]+'" stroke-width="26" stroke-dasharray="'+len+' '+(C-len)+'" stroke-dashoffset="'+(-offset)+'" transform="rotate(-90 100 100)"><title>'+adminEsc(r.label+': '+r.total)+'</title></circle>';
    offset+=len;
  });
  svg+='<text x="100" y="100" text-anchor="middle" class="admin-donut-num">'+total+'</text><text x="100" y="120" text-anchor="middle" class="admin-axis">'+adminEsc(centerLabel)+'</text></svg>';
  el.innerHTML=svg+'<div class="admin-donut-legend">'+rows.map(function(r,i){
    return '<div><span class="admin-dotkey" style="background:'+ADMIN_PALETTE[i%ADMIN_PALETTE.length]+'"></span><b>'+Math.round(r.total/total*100)+'%</b> '+adminEsc(r.label)+'</div>';
  }).join('')+'</div>';
}
function adminColumns(el,labels,values,color){
  if(!el)return;
  const W=480,H=140,B=20,max=Math.max(1,Math.max.apply(null,values)),bw=W/values.length;
  let svg='<svg viewBox="0 0 '+W+' '+H+'" preserveAspectRatio="none" class="admin-svg">';
  values.forEach(function(v,i){
    const h=Math.max(v?3:1,(H-B-8)*v/max);
    svg+='<rect x="'+(i*bw+bw*.18)+'" y="'+(H-B-h)+'" width="'+(bw*.64)+'" height="'+h+'" rx="3" fill="'+color+'" opacity="'+(v?1:.25)+'"><title>'+adminEsc(labels[i]+': '+v)+'</title></rect>';
    if(i%2===0)svg+='<text x="'+(i*bw+bw/2)+'" y="'+(H-5)+'" text-anchor="middle" class="admin-axis">'+adminEsc(adminShortDate(labels[i]))+'</text>';
  });
  el.innerHTML=svg+'</svg>';
}
function adminSpark(values,color){
  const W=200,H=50,max=Math.max(1,Math.max.apply(null,values)),step=W/Math.max(1,values.length-1);
  const pts=values.map(function(v,i){return [i*step,H-4-(H-10)*v/max];});
  const line=adminSmoothPath(pts);
  return '<svg viewBox="0 0 '+W+' '+H+'" preserveAspectRatio="none" class="admin-spark"><path d="'+line+' L'+W+','+H+' L0,'+H+' Z" fill="rgba(255,255,255,.2)"/><path d="'+line+'" fill="none" stroke="#fff" stroke-width="2" stroke-linecap="round"/></svg>';
}
function adminDayTotals(days,keys){
  return days.map(function(day){return keys.reduce(function(a,k){return a+(day.counts[k]||0);},0);});
}
function adminGradCards(el,cards){
  if(!el)return;
  el.innerHTML=cards.map(function(c){
    return '<div class="admin-grad g'+c.tone+'"><div class="admin-grad-label">'+adminEsc(c.label)+'</div><div class="admin-grad-value">'+adminEsc(c.value)+'</div><div class="admin-grad-sub">'+adminEsc(c.sub)+'</div>'+adminSpark(c.spark)+'</div>';
  }).join('');
}
function adminLegend(el,series){
  if(el)el.innerHTML=series.map(function(s){return '<span><span class="admin-dotkey" style="background:'+s.color+'"></span>'+adminEsc(s.name)+'</span>';}).join('');
}
function adminSum(a){return a.reduce(function(x,y){return x+y;},0);}
function renderAdminTrend(chartEl,legendEl,charts){
  const days=charts.events_daily||[];
  const labels=days.map(function(d){return d.date;});
  const series=[
    {name:'All events',color:ADMIN_PALETTE[0],values:adminDayTotals(days,Object.keys(ADMIN_EVENT_LABELS))},
    {name:'Build checks',color:ADMIN_PALETTE[1],values:adminDayTotals(days,['build_check'])},
    {name:'Sign-ups',color:ADMIN_PALETTE[2],values:adminDayTotals(charts.signups_daily||[],['signups'])}
  ];
  adminLegend(legendEl,series);
  adminAreaChart(chartEl,labels,series);
}
function renderAdminGradCards(el,charts){
  const ev=charts.events_daily||[],su=charts.signups_daily||[];
  const searches=adminDayTotals(ev,['search']),checks=adminDayTotals(ev,['build_check']),saves=adminDayTotals(ev,['build_save']),fails=adminDayTotals(ev,['compat_fail']),signups=adminDayTotals(su,['signups']);
  adminGradCards(el,[
    {tone:1,label:'Searches (14d)',value:adminSum(searches),sub:'Part lookups',spark:searches},
    {tone:2,label:'Build checks (14d)',value:adminSum(checks),sub:'Compatibility runs',spark:checks},
    {tone:3,label:'Build saves (14d)',value:adminSum(saves),sub:'Saved configurations',spark:saves},
    {tone:4,label:'Sign-ups (14d)',value:adminSum(signups),sub:'New accounts · '+adminSum(fails)+' compat fails',spark:signups}
  ]);
}

// ——— Dashboard ———
async function loadAdminDashboard(){
  const grid=document.getElementById('admin-stat-grid');
  setAdminStatus('Loading dashboard…');
  try{
    const data=await adminApi('overview');
    const o=data.overview;
    if(grid){
      const stats=[
        ['Registered users',o.total_users,'accent'],
        ['New users (7d)',o.new_users_7d,''],
        ['Active parts',o.active_components,'accent'],
        ['Retired parts',o.inactive_components,''],
        ['Active rules',o.rules_enabled+' / '+o.rules_total,''],
        ['Saved builds',o.saved_builds,''],
        ['Build checks today',o.build_checks_today,'accent'],
        ['Open reports',o.open_reports,o.open_reports>0?'warn':''],
        ['Banned users',o.banned_users,o.banned_users>0?'warn':''],
        ['Errors today',o.errors_today,o.errors_today>0?'warn':''],
        ['Communities',o.communities,''],
        ['Community posts',o.posts,'']
      ];
      grid.innerHTML=stats.map(function(item){
        return '<div class="admin-stat '+item[2]+'"><div class="admin-stat-label">'+adminEsc(item[0])+'</div><div class="admin-stat-value">'+adminEsc(item[1])+'</div></div>';
      }).join('');
    }
    const usersEl=document.getElementById('admin-recent-users');
    if(usersEl){
      usersEl.innerHTML=(o.recent_users||[]).length?(o.recent_users||[]).map(function(u){
        return '<div class="admin-list-row"><div class="grow"><div class="strong">'+adminEsc(u.full_name)+'</div><div class="muted truncate">'+adminEsc(u.email)+'</div></div><span class="admin-tag '+adminRoleClass(u.role)+'">'+adminEsc(u.role)+'</span></div>';
      }).join(''):'<div class="admin-empty">No accounts yet.</div>';
    }
    const logsEl=document.getElementById('admin-recent-logs');
    if(logsEl){
      logsEl.innerHTML=(o.recent_logs||[]).length?(o.recent_logs||[]).map(function(log){
        return '<div class="admin-list-row"><span class="admin-tag '+adminLevelClass(log.level)+'">'+adminEsc(log.level)+'</span><div class="grow"><div class="truncate">'+adminEsc(log.message)+'</div><div class="muted">'+adminEsc(log.source)+' · '+adminEsc(log.created_at)+'</div></div></div>';
      }).join(''):'<div class="admin-empty">No activity recorded yet.</div>';
    }
    const charts=o.charts||{};
    renderAdminTrend(document.getElementById('admin-trend-chart'),document.getElementById('admin-trend-legend'),charts);
    adminDonut(document.getElementById('admin-role-donut'),charts.roles||[],'users');
    renderAdminGradCards(document.getElementById('admin-grad-grid'),charts);
    setAdminStatus('Dashboard updated','ok');
  }catch(error){
    setAdminStatus(error.message,'err');
    if(grid)grid.innerHTML='<div class="admin-empty">'+adminEsc(error.message)+'</div>';
  }
}

function adminRoleClass(role){
  if(role==='admin')return 'admin';
  if(role==='moderator')return 'warning';
  if(role==='verified')return 'info';
  return 'neutral';
}
function adminLevelClass(level){
  if(level==='error')return 'error';
  if(level==='warning')return 'warning';
  return 'info';
}

// ——— Components ———
async function loadAdminComponents(){
  const tbody=document.getElementById('admin-component-rows');
  setAdminStatus('Loading catalog…');
  try{
    const data=await adminApi('component_list',{search:adminVal('component-search'),category:adminVal('component-category-filter')});
    adminState.components=data.components||[];
    if(tbody){
      tbody.innerHTML=adminState.components.length?adminState.components.map(function(c,index){
        return '<tr>'+
          '<td>'+adminEsc(c.category)+'</td>'+
          '<td><div class="strong">'+adminEsc(c.brand)+'</div><div class="muted">'+adminEsc(c.model)+'</div></td>'+
          '<td>'+adminEsc(c.socket||'—')+'</td>'+
          '<td>'+adminEsc(c.tdp_watts!=null?c.tdp_watts:'—')+'</td>'+
          '<td>'+adminEsc(c.form_factor||'—')+'</td>'+
          '<td>₱'+Number(c.price_php||0).toLocaleString()+'</td>'+
          '<td>$'+Number(c.price_usd||0).toLocaleString()+'</td>'+
          '<td>'+adminEsc(c.stock_status||'—')+'</td>'+
          '<td>'+(c.is_active?'<span class="admin-tag ok">Active</span>':'<span class="admin-tag neutral">Retired</span>')+'</td>'+
          '<td><div class="admin-row-actions">'+
            '<button class="admin-icon-btn" onclick="openComponentForm('+index+')">Edit</button>'+
            '<button class="admin-icon-btn danger" onclick="deleteComponent('+index+')">Delete</button>'+
          '</div></td>'+
        '</tr>';
      }).join(''):'<tr><td colspan="10"><div class="admin-empty">No components match this filter.</div></td></tr>';
    }
    setAdminStatus(adminState.components.length+' parts loaded','ok');
  }catch(error){
    setAdminStatus(error.message,'err');
    if(tbody)tbody.innerHTML='<tr><td colspan="10"><div class="admin-empty">'+adminEsc(error.message)+'</div></td></tr>';
  }
}

function openComponentForm(index){
  const editing=(typeof index==='number'&&adminState.components[index])?adminState.components[index]:null;
  const categories=['cpu','motherboard','gpu','ram','storage','psu','case','cooler','other'];
  const cats=categories.map(function(c){return '<option value="'+c+'"'+(editing&&editing.category===c?' selected':'')+'>'+c+'</option>';}).join('');
  const stocks=['in_stock','low_stock','out_of_stock','unknown'];
  const stockOptions=stocks.map(function(s){return '<option value="'+s+'"'+(editing&&editing.stock_status===s?' selected':'')+'>'+s.replace(/_/g,' ')+'</option>';}).join('');
  const specs=editing&&editing.specs?JSON.stringify(editing.specs,null,1):'';
  adminModal(
    '<div class="admin-modal-title">'+(editing?'Edit component':'New component')+'</div>'+
    '<p class="admin-modal-hint">Resolve duplicates and incorrect specs here. Compatibility fields (socket, TDP, form factor) power the rule engine.</p>'+
    '<div class="admin-form-grid">'+
      '<label class="admin-label">Category<select id="cf-category">'+cats+'</select></label>'+
      '<label class="admin-label">Brand<input id="cf-brand" value="'+adminEsc(editing?editing.brand:'')+'" placeholder="e.g. AMD"/></label>'+
      '<label class="admin-label full">Model<input id="cf-model" value="'+adminEsc(editing?editing.model:'')+'" placeholder="e.g. Ryzen 5 5600"/></label>'+
      '<label class="admin-label">Socket<input id="cf-socket" value="'+adminEsc(editing&&editing.socket?editing.socket:'')+'" placeholder="AM5 / LGA1700"/></label>'+
      '<label class="admin-label">TDP (watts)<input id="cf-tdp" type="number" min="0" value="'+adminEsc(editing&&editing.tdp_watts!=null?editing.tdp_watts:'')+'"/></label>'+
      '<label class="admin-label">Form factor<input id="cf-form" value="'+adminEsc(editing&&editing.form_factor?editing.form_factor:'')+'" placeholder="ATX / mATX / DIMM"/></label>'+
      '<label class="admin-label">Stock<select id="cf-stock">'+stockOptions+'</select></label>'+
      '<label class="admin-label">Price ₱<input id="cf-php" type="number" step="0.01" min="0" value="'+adminEsc(editing?editing.price_php:0)+'"/></label>'+
      '<label class="admin-label">Price $<input id="cf-usd" type="number" step="0.01" min="0" value="'+adminEsc(editing?editing.price_usd:0)+'"/></label>'+
      '<label class="admin-label">Status<select id="cf-active"><option value="1"'+(!editing||editing.is_active?' selected':'')+'>Active</option><option value="0"'+(editing&&!editing.is_active?' selected':'')+'>Retired</option></select></label>'+
      '<label class="admin-label full">Specs (JSON)<textarea id="cf-specs" rows="4" placeholder=\'{"chipset":"B650"}\'>'+adminEsc(specs)+'</textarea></label>'+
    '</div>'+
    '<div class="admin-modal-status" id="admin-modal-status"></div>'+
    '<div class="admin-modal-actions">'+
      '<button class="admin-btn" onclick="closeAdminModal()">Cancel</button>'+
      '<button class="admin-btn primary" onclick="saveComponentModal('+(editing?editing.id:0)+')">'+(editing?'Save changes':'Create component')+'</button>'+
    '</div>'
  );
}

async function saveComponentModal(id){
  let specs={};
  const rawSpecs=adminVal('cf-specs');
  if(rawSpecs){
    try{specs=JSON.parse(rawSpecs);}catch(error){adminModalStatus('Specs must be valid JSON.','err');return;}
    if(typeof specs!=='object'||Array.isArray(specs)){adminModalStatus('Specs must be a JSON object.','err');return;}
  }
  const payload={
    id:id||0,
    category:adminVal('cf-category'),
    brand:adminVal('cf-brand'),
    model:adminVal('cf-model'),
    socket:adminVal('cf-socket'),
    tdp_watts:adminVal('cf-tdp'),
    form_factor:adminVal('cf-form'),
    stock_status:adminVal('cf-stock'),
    price_php:adminVal('cf-php'),
    price_usd:adminVal('cf-usd'),
    is_active:adminVal('cf-active'),
    specs:specs
  };
  adminModalStatus('Saving…');
  try{
    const data=await adminApi('component_save',payload);
    adminModalStatus(data.message||'Saved','ok');
    setTimeout(closeAdminModal,400);
    loadAdminComponents();
  }catch(error){
    adminModalStatus(error.message,'err');
  }
}

async function deleteComponent(index){
  const component=adminState.components[index];
  if(!component)return;
  if(!window.confirm('Delete '+component.brand+' '+component.model+' from the catalog?'))return;
  setAdminStatus('Deleting…');
  try{
    await adminApi('component_delete',{id:component.id});
    setAdminStatus('Component deleted','ok');
    loadAdminComponents();
  }catch(error){
    setAdminStatus(error.message,'err');
  }
}

function openBulkPrice(){
  switchAdminView('pricing',document.querySelector('.admin-nav-item[data-admin-view="pricing"]'));
}

// ——— Compatibility rules ———
async function loadAdminRules(){
  const list=document.getElementById('admin-rule-list');
  setAdminStatus('Loading rules…');
  try{
    const data=await adminApi('rules_list');
    adminState.rules=data.rules||[];
    if(list){
      list.innerHTML=adminState.rules.length?adminState.rules.map(function(rule,index){
        return '<div class="admin-rule'+(rule.is_enabled?'':' disabled')+'">'+
          '<div class="admin-rule-head">'+
            '<div><div class="admin-rule-name">'+adminEsc(rule.name)+'</div><div class="admin-rule-key">'+adminEsc(rule.rule_key)+'</div></div>'+
            '<label class="admin-switch" title="Enable or disable this check"><input type="checkbox" '+(rule.is_enabled?'checked':'')+' onchange="toggleRule('+index+')"><span class="admin-switch-slider"></span></label>'+
          '</div>'+
          '<div><span class="admin-tag '+adminLevelClass(rule.severity)+'">'+adminEsc(rule.severity)+'</span> <span class="admin-tag neutral">'+adminEsc(rule.category)+'</span></div>'+
          '<div class="admin-rule-summary">'+adminEsc(rule.logic_summary||'No description')+'</div>'+
          '<div class="admin-rule-actions">'+
            '<button class="admin-icon-btn" onclick="openRuleForm('+index+')">Edit</button>'+
            '<button class="admin-icon-btn danger" onclick="deleteRule('+index+')">Delete</button>'+
          '</div>'+
        '</div>';
      }).join(''):'<div class="admin-empty">No rules configured.</div>';
    }
    setAdminStatus(adminState.rules.length+' rules','ok');
  }catch(error){
    setAdminStatus(error.message,'err');
    if(list)list.innerHTML='<div class="admin-empty">'+adminEsc(error.message)+'</div>';
  }
}

function openRuleForm(index){
  const editing=(typeof index==='number'&&adminState.rules[index])?adminState.rules[index]:null;
  const severities=['info','warning','error'];
  const severityOptions=severities.map(function(s){return '<option value="'+s+'"'+(editing&&editing.severity===s?' selected':'')+'>'+s+'</option>';}).join('');
  adminModal(
    '<div class="admin-modal-title">'+(editing?'Edit compatibility rule':'New compatibility rule')+'</div>'+
    '<p class="admin-modal-hint">Update the logic when new standards emerge (PCIe generations, DDR5 profiles, 12VHPWR). Disable a rule to override edge cases automated checks get wrong.</p>'+
    '<div class="admin-form-grid">'+
      '<label class="admin-label">Rule key<input id="rf-key" value="'+adminEsc(editing?editing.rule_key:'')+'" placeholder="e.g. pcie_generation"/></label>'+
      '<label class="admin-label">Severity<select id="rf-severity">'+severityOptions+'</select></label>'+
      '<label class="admin-label full">Name<input id="rf-name" value="'+adminEsc(editing?editing.name:'')+'" placeholder="Short rule title"/></label>'+
      '<label class="admin-label full">Category<input id="rf-category" value="'+adminEsc(editing?editing.category:'general')+'" placeholder="e.g. power"/></label>'+
      '<label class="admin-label full">Logic summary<textarea id="rf-summary" rows="4" placeholder="Describe what the rule checks…">'+adminEsc(editing?editing.logic_summary:'')+'</textarea></label>'+
      '<label class="admin-label">Enabled<select id="rf-enabled"><option value="1"'+(!editing||editing.is_enabled?' selected':'')+'>Enabled</option><option value="0"'+(editing&&!editing.is_enabled?' selected':'')+'>Disabled</option></select></label>'+
    '</div>'+
    '<div class="admin-modal-status" id="admin-modal-status"></div>'+
    '<div class="admin-modal-actions">'+
      '<button class="admin-btn" onclick="closeAdminModal()">Cancel</button>'+
      '<button class="admin-btn primary" onclick="saveRuleModal('+(editing?editing.id:0)+')">'+(editing?'Save rule':'Create rule')+'</button>'+
    '</div>'
  );
}

async function saveRuleModal(id){
  adminModalStatus('Saving…');
  try{
    const data=await adminApi('rule_save',{
      id:id||0,
      rule_key:adminVal('rf-key'),
      name:adminVal('rf-name'),
      category:adminVal('rf-category'),
      severity:adminVal('rf-severity'),
      logic_summary:adminVal('rf-summary'),
      is_enabled:adminVal('rf-enabled')
    });
    adminModalStatus(data.message||'Saved','ok');
    setTimeout(closeAdminModal,400);
    loadAdminRules();
  }catch(error){
    adminModalStatus(error.message,'err');
  }
}

async function toggleRule(index){
  const rule=adminState.rules[index];
  if(!rule)return;
  try{
    await adminApi('rule_toggle',{id:rule.id});
    loadAdminRules();
  }catch(error){
    setAdminStatus(error.message,'err');
    loadAdminRules();
  }
}

async function deleteRule(index){
  const rule=adminState.rules[index];
  if(!rule)return;
  if(!window.confirm('Delete rule "'+rule.name+'"?'))return;
  try{
    await adminApi('rule_delete',{id:rule.id});
    setAdminStatus('Rule deleted','ok');
    loadAdminRules();
  }catch(error){
    setAdminStatus(error.message,'err');
  }
}

// ——— Users & roles ———
async function loadAdminUsers(){
  const tbody=document.getElementById('admin-user-rows');
  setAdminStatus('Loading users…');
  try{
    const data=await adminApi('user_list',{search:adminVal('user-search'),role:adminVal('user-role-filter')});
    adminState.users=data.users||[];
    if(tbody){
      tbody.innerHTML=adminState.users.length?adminState.users.map(function(user,index){
        const isSelf=window._user&&user.email===window._user.email;
        const roles=['user','verified','moderator','admin'];
        const roleOptions=roles.map(function(r){return '<option value="'+r+'"'+(user.role===r?' selected':'')+'>'+r+'</option>';}).join('');
        return '<tr>'+
          '<td><div class="strong">'+adminEsc(user.full_name)+(isSelf?' <span class="admin-tag info">you</span>':'')+'</div></td>'+
          '<td class="muted">'+adminEsc(user.email)+'</td>'+
          '<td><select class="admin-input admin-select small" onchange="setUserRole('+index+',this.value)">'+roleOptions+'</select></td>'+
          '<td>'+(user.status==='banned'?'<span class="admin-tag error">banned</span>':'<span class="admin-tag ok">active</span>')+'</td>'+
          '<td>'+Number(user.saved_builds||0)+'</td>'+
          '<td class="muted">'+adminEsc(user.created_at||'—')+'</td>'+
          '<td class="muted">'+adminEsc(user.last_login_at||'—')+'</td>'+
          '<td><div class="admin-row-actions">'+
            (user.status==='banned'
              ?'<button class="admin-icon-btn" onclick="setUserStatus('+index+',\'active\')">Reactivate</button>'
              :'<button class="admin-icon-btn danger" onclick="setUserStatus('+index+',\'banned\')">Ban</button>')+
            '<button class="admin-icon-btn danger" onclick="deleteUser('+index+')">Delete</button>'+
          '</div></td>'+
        '</tr>';
      }).join(''):'<tr><td colspan="8"><div class="admin-empty">No users match this filter.</div></td></tr>';
    }
    setAdminStatus(adminState.users.length+' accounts','ok');
  }catch(error){
    setAdminStatus(error.message,'err');
    if(tbody)tbody.innerHTML='<tr><td colspan="8"><div class="admin-empty">'+adminEsc(error.message)+'</div></td></tr>';
  }
}

async function setUserRole(index,role){
  const user=adminState.users[index];
  if(!user)return;
  try{
    const data=await adminApi('user_set_role',{user_email:user.email,role:role});
    setAdminStatus(data.message||'Role updated','ok');
    loadAdminUsers();
  }catch(error){
    setAdminStatus(error.message,'err');
    loadAdminUsers();
  }
}

async function setUserStatus(index,status){
  const user=adminState.users[index];
  if(!user)return;
  if(status==='banned'&&!window.confirm('Ban '+user.full_name+'? Their active sessions will be revoked.'))return;
  try{
    const data=await adminApi('user_set_status',{user_email:user.email,status:status});
    setAdminStatus(data.message||'Updated','ok');
    loadAdminUsers();
  }catch(error){
    setAdminStatus(error.message,'err');
  }
}

async function deleteUser(index){
  const user=adminState.users[index];
  if(!user)return;
  if(!window.confirm('Permanently delete '+user.full_name+' and all their content?'))return;
  try{
    const data=await adminApi('user_delete',{user_email:user.email});
    setAdminStatus(data.message||'Account deleted','ok');
    loadAdminUsers();
  }catch(error){
    setAdminStatus(error.message,'err');
  }
}

// ——— Moderation ———
async function loadAdminReports(){
  const list=document.getElementById('admin-report-list');
  setAdminStatus('Loading reports…');
  try{
    const data=await adminApi('report_list',{status:adminVal('report-status-filter')});
    adminState.reports=data.reports||[];
    if(list){
      list.innerHTML=adminState.reports.length?adminState.reports.map(function(report,index){
        const open=report.status==='open';
        return '<div class="admin-list-row">'+
          '<div class="grow"><div class="strong">'+adminEsc(report.content_type)+' · '+adminEsc(report.content_ref)+'</div>'+
          '<div class="muted">'+adminEsc(report.reason)+'</div>'+
          '<div class="muted">by '+adminEsc(report.reported_by)+(report.community?' · '+adminEsc(report.community):'')+' · '+adminEsc(report.created_at)+'</div></div>'+
          '<span class="admin-tag '+(report.status==='open'?'warning':(report.status==='resolved'?'ok':'neutral'))+'">'+adminEsc(report.status)+'</span>'+
          (open?'<div class="admin-row-actions"><button class="admin-icon-btn" onclick="resolveReport('+index+',\'resolved\')">Resolve</button><button class="admin-icon-btn" onclick="resolveReport('+index+',\'dismissed\')">Dismiss</button></div>':'')+
        '</div>';
      }).join(''):'<div class="admin-empty">No reports in this view.</div>';
    }
  }catch(error){
    if(list)list.innerHTML='<div class="admin-empty">'+adminEsc(error.message)+'</div>';
  }
}

async function resolveReport(index,status){
  const report=adminState.reports[index];
  if(!report)return;
  try{
    await adminApi('report_resolve',{id:report.id,status:status});
    setAdminStatus('Report '+status,'ok');
    loadAdminReports();
  }catch(error){
    setAdminStatus(error.message,'err');
  }
}

async function loadAdminContent(){
  const list=document.getElementById('admin-content-list');
  try{
    const data=await adminApi('content_list',{type:adminVal('content-type-filter')});
    adminState.content=data.items||[];
    const type=adminVal('content-type-filter')||'posts';
    if(list){
      list.innerHTML=adminState.content.length?adminState.content.map(function(item,index){
        return '<div class="admin-list-row">'+
          '<div class="grow"><div class="strong">'+adminEsc(item.community||'—')+'</div>'+
          '<div class="truncate">'+adminEsc(item.body||'')+'</div>'+
          '<div class="muted">'+adminEsc(item.author)+' · '+adminEsc(item.created_at)+'</div></div>'+
          '<button class="admin-icon-btn danger" onclick="deleteContent('+index+',\''+type+'\')">Remove</button>'+
        '</div>';
      }).join(''):'<div class="admin-empty">Nothing to moderate here.</div>';
    }
  }catch(error){
    if(list)list.innerHTML='<div class="admin-empty">'+adminEsc(error.message)+'</div>';
  }
}

async function deleteContent(index,type){
  const item=adminState.content[index];
  if(!item)return;
  if(!window.confirm('Remove this content permanently?'))return;
  try{
    await adminApi('content_delete',{type:type,id:item.id});
    setAdminStatus('Content removed','ok');
    loadAdminContent();
  }catch(error){
    setAdminStatus(error.message,'err');
  }
}

// ——— Pricing feeds ———
async function loadAdminRegions(){
  const list=document.getElementById('admin-region-list');
  setAdminStatus('Loading pricing feeds…');
  try{
    const data=await adminApi('pricing_list');
    adminState.regions=data.regions||[];
    if(list){
      list.innerHTML=adminState.regions.length?adminState.regions.map(function(region,index){
        return '<div class="admin-list-row">'+
          '<div class="grow"><div class="strong">'+adminEsc(region.region)+'</div>'+
          '<div class="muted">'+adminEsc(region.currency)+' · ×'+Number(region.multiplier).toFixed(3)+(region.notes?' · '+adminEsc(region.notes):'')+'</div></div>'+
          '<div class="admin-row-actions">'+
            '<button class="admin-icon-btn" onclick="applyRegionPricing('+index+')">Apply to catalog</button>'+
            '<button class="admin-icon-btn" onclick="openRegionForm('+index+')">Edit</button>'+
            '<button class="admin-icon-btn danger" onclick="deleteRegion('+index+')">Delete</button>'+
          '</div>'+
        '</div>';
      }).join(''):'<div class="admin-empty">No regions configured.</div>';
    }
    setAdminStatus(adminState.regions.length+' regions','ok');
  }catch(error){
    setAdminStatus(error.message,'err');
    if(list)list.innerHTML='<div class="admin-empty">'+adminEsc(error.message)+'</div>';
  }
}

function openRegionForm(index){
  const editing=(typeof index==='number'&&adminState.regions[index])?adminState.regions[index]:null;
  adminModal(
    '<div class="admin-modal-title">'+(editing?'Edit pricing region':'New pricing region')+'</div>'+
    '<p class="admin-modal-hint">The multiplier adjusts baseline prices for a regional market. Use "Apply to catalog" afterwards to sync component prices.</p>'+
    '<div class="admin-form-grid">'+
      '<label class="admin-label full">Region<input id="rg-region" value="'+adminEsc(editing?editing.region:'')+'" placeholder="e.g. Visayas"/></label>'+
      '<label class="admin-label">Currency<input id="rg-currency" value="'+adminEsc(editing?editing.currency:'PHP')+'" placeholder="PHP"/></label>'+
      '<label class="admin-label">Multiplier<input id="rg-multiplier" type="number" step="0.001" min="0.1" value="'+adminEsc(editing?editing.multiplier:1)+'"/></label>'+
      '<label class="admin-label full">Notes<input id="rg-notes" value="'+adminEsc(editing&&editing.notes?editing.notes:'')+'" placeholder="Shipping markup, market notes…"/></label>'+
    '</div>'+
    '<div class="admin-modal-status" id="admin-modal-status"></div>'+
    '<div class="admin-modal-actions">'+
      '<button class="admin-btn" onclick="closeAdminModal()">Cancel</button>'+
      '<button class="admin-btn primary" onclick="saveRegionModal('+(editing?editing.id:0)+')">'+(editing?'Save region':'Create region')+'</button>'+
    '</div>'
  );
}

async function saveRegionModal(id){
  adminModalStatus('Saving…');
  try{
    const data=await adminApi('pricing_save',{
      id:id||0,
      region:adminVal('rg-region'),
      currency:adminVal('rg-currency'),
      multiplier:adminVal('rg-multiplier'),
      notes:adminVal('rg-notes')
    });
    adminModalStatus(data.message||'Saved','ok');
    setTimeout(closeAdminModal,400);
    loadAdminRegions();
  }catch(error){
    adminModalStatus(error.message,'err');
  }
}

async function applyRegionPricing(index){
  const region=adminState.regions[index];
  if(!region)return;
  if(!window.confirm('Apply '+region.region+' pricing (×'+Number(region.multiplier).toFixed(3)+') to all components?'))return;
  setAdminStatus('Applying prices…');
  try{
    const data=await adminApi('pricing_apply_region',{id:region.id});
    setAdminStatus(data.message||'Prices applied','ok');
  }catch(error){
    setAdminStatus(error.message,'err');
  }
}

async function deleteRegion(index){
  const region=adminState.regions[index];
  if(!region)return;
  if(!window.confirm('Delete region "'+region.region+'"?'))return;
  try{
    await adminApi('pricing_delete',{id:region.id});
    setAdminStatus('Region deleted','ok');
    loadAdminRegions();
  }catch(error){
    setAdminStatus(error.message,'err');
  }
}

async function applyBulkPrice(){
  const value=adminVal('bulk-value');
  if(!value){setAdminStatus('Enter a non-zero value first.','err');return;}
  setAdminStatus('Applying update…');
  try{
    const data=await adminApi('component_bulk_price',{
      mode:adminVal('bulk-mode'),
      category:adminVal('bulk-category'),
      value:value
    });
    setAdminStatus(data.message||'Prices updated','ok');
  }catch(error){
    setAdminStatus(error.message,'err');
  }
}

// ——— Analytics ———
async function loadAdminAnalytics(){
  setAdminStatus('Loading analytics…');
  try{
    const data=await adminApi('analytics');
    const s=data.summary;
    const health=document.getElementById('admin-health-grid');
    if(health){
      const items=[
        ['Events (24h)',s.api_health.events_24h,'accent'],
        ['Active sessions',s.api_health.active_sessions,''],
        ['Errors (24h)',s.api_health.errors_24h,s.api_health.errors_24h>0?'warn':''],
        ['Warnings (24h)',s.api_health.warnings_24h,'']
      ];
      health.innerHTML=items.map(function(item){
        return '<div class="admin-stat '+item[2]+'"><div class="admin-stat-label">'+adminEsc(item[0])+'</div><div class="admin-stat-value">'+adminEsc(item[1])+'</div></div>';
      }).join('');
    }
    const charts=s.charts||{};
    renderAdminTrend(document.getElementById('admin-an-trend'),document.getElementById('admin-an-legend'),charts);
    adminDonut(document.getElementById('admin-event-donut'),(s.events_by_type||[]).map(function(r){return {label:ADMIN_EVENT_LABELS[r.event_type]||r.event_type,total:Number(r.total)};}),'events');
    renderAdminGradCards(document.getElementById('admin-an-grad'),charts);
    const su=charts.signups_daily||[];
    adminColumns(document.getElementById('admin-signup-bars'),su.map(function(d){return d.date;}),adminDayTotals(su,['signups']),'#22c55e');
    const cats=charts.categories||[],catMax=cats.reduce(function(m,c){return Math.max(m,c.total);},0)||1;
    const catEl=document.getElementById('admin-category-bars');
    if(catEl)catEl.innerHTML=cats.length?cats.map(function(c){
      return '<div class="admin-bar-row wide"><span>'+adminEsc(c.label)+'</span><div class="admin-bar-track"><div class="admin-bar-fill" style="width:'+Math.round(c.total/catMax*100)+'%"></div></div><span>'+c.total+'</span></div>';
    }).join(''):'<div class="admin-empty">No active parts yet.</div>';
    renderAdminTotals('admin-top-searches',s.top_searches,'No searches recorded yet.');
    renderAdminTotals('admin-trending-builds',s.trending_builds,'No saved builds recorded yet.');
    renderAdminTotals('admin-failed-queries',s.failed_queries,'No failed compatibility checks recorded.');
    const peak=document.getElementById('admin-peak-hours');
    if(peak){
      const rows=s.peak_hours||[];
      const max=rows.reduce(function(m,row){return Math.max(m,row.total);},0)||1;
      peak.innerHTML=rows.length?rows.map(function(row){
        const hour=(row.hour<10?'0':'')+row.hour+':00';
        const pct=Math.round((row.total/max)*100);
        return '<div class="admin-bar-row"><span>'+hour+'</span><div class="admin-bar-track"><div class="admin-bar-fill" style="width:'+pct+'%"></div></div><span>'+row.total+'</span></div>';
      }).join(''):'<div class="admin-empty">No activity in the last 7 days.</div>';
    }
    setAdminStatus('Analytics updated','ok');
  }catch(error){
    setAdminStatus(error.message,'err');
  }
}
function renderAdminTotals(elementId,rows,emptyMessage){
  const el=document.getElementById(elementId);
  if(!el)return;
  el.innerHTML=(rows&&rows.length)?rows.map(function(row){
    return '<div class="admin-list-row"><div class="grow truncate">'+adminEsc(row.detail)+'</div><span class="admin-tag info">'+Number(row.total)+'</span></div>';
  }).join(''):'<div class="admin-empty">'+adminEsc(emptyMessage)+'</div>';
}

// ——— Logs ———
async function loadAdminLogs(){
  const tbody=document.getElementById('admin-log-rows');
  setAdminStatus('Loading logs…');
  try{
    const data=await adminApi('logs_list',{level:adminVal('log-level-filter')});
    const logs=data.logs||[];
    if(tbody){
      tbody.innerHTML=logs.length?logs.map(function(log){
        return '<tr><td><span class="admin-tag '+adminLevelClass(log.level)+'">'+adminEsc(log.level)+'</span></td>'+
          '<td>'+adminEsc(log.source)+'</td>'+
          '<td>'+adminEsc(log.message)+'</td>'+
          '<td class="muted">'+adminEsc(log.created_at)+'</td></tr>';
      }).join(''):'<tr><td colspan="4"><div class="admin-empty">No log entries.</div></td></tr>';
    }
    setAdminStatus(logs.length+' entries','ok');
  }catch(error){
    setAdminStatus(error.message,'err');
    if(tbody)tbody.innerHTML='<tr><td colspan="4"><div class="admin-empty">'+adminEsc(error.message)+'</div></td></tr>';
  }
}

async function clearAdminLogs(days){
  if(!window.confirm(days>0?('Delete log entries older than '+days+' days?'):'Delete all log entries?'))return;
  try{
    const data=await adminApi('logs_clear',{older_than_days:days||0});
    setAdminStatus('Removed '+data.removed+' log entries','ok');
    loadAdminLogs();
  }catch(error){
    setAdminStatus(error.message,'err');
  }
}
