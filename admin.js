// â€”â€”â€” CoreCraft Admin Console â€”â€”â€”
let adminState={view:'dashboard',users:[],reports:[],content:[]};

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

// â€”â€”â€” Entry point (called from showPage) â€”â€”â€”
function initAdminPage(){
  if(!window._user||!window._user.isAdmin){showPage('home');return;}
  const who=document.getElementById('admin-side-user');
  if(who)who.textContent=(window._user.name||'Admin')+' Â· '+((window._user.adminRole||'admin'));
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
    users:['Users & Roles','Manage accounts, roles and access.'],
    moderation:['Moderation','Review reports and remove abusive content.'],
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
  else if(view==='users')loadAdminUsers();
  else if(view==='moderation')loadAdminReports();
  else if(view==='logs')loadAdminLogs();
}
function refreshAdminView(){loadAdminView(adminState.view);}

// â€”â€”â€” Charts (inline SVG) â€”â€”â€”
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
    pts.forEach(function(p,j){svg+='<circle cx="'+p[0]+'" cy="'+p[1]+'" r="3.4" fill="'+s.color+'" class="admin-dot"><title>'+adminEsc(s.name+' Â· '+labels[j]+': '+s.values[j])+'</title></circle>';});
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
    {tone:4,label:'Sign-ups (14d)',value:adminSum(signups),sub:'New accounts Â· '+adminSum(fails)+' compat fails',spark:signups}
  ]);
}

// â€”â€”â€” Dashboard â€”â€”â€”
async function loadAdminDashboard(){
  const grid=document.getElementById('admin-stat-grid');
  setAdminStatus('Loading dashboardâ€¦');
  try{
    const data=await adminApi('overview');
    const o=data.overview;
    if(grid){
      const stats=[
        ['Registered users',o.total_users,'accent'],
        ['New users (7d)',o.new_users_7d,''],
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
        return '<div class="admin-list-row"><span class="admin-tag '+adminLevelClass(log.level)+'">'+adminEsc(log.level)+'</span><div class="grow"><div class="truncate">'+adminEsc(log.message)+'</div><div class="muted">'+adminEsc(log.source)+' Â· '+adminEsc(log.created_at)+'</div></div></div>';
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

// â€”â€”â€” Users & roles â€”â€”â€”
async function loadAdminUsers(){
  const tbody=document.getElementById('admin-user-rows');
  setAdminStatus('Loading usersâ€¦');
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
          '<td class="muted">'+adminEsc(user.created_at||'â€”')+'</td>'+
          '<td class="muted">'+adminEsc(user.last_login_at||'â€”')+'</td>'+
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

// â€”â€”â€” Moderation â€”â€”â€”
async function loadAdminReports(){
  const list=document.getElementById('admin-report-list');
  setAdminStatus('Loading reportsâ€¦');
  try{
    const data=await adminApi('report_list',{status:adminVal('report-status-filter')});
    adminState.reports=data.reports||[];
    if(list){
      list.innerHTML=adminState.reports.length?adminState.reports.map(function(report,index){
        const open=report.status==='open';
        return '<div class="admin-list-row">'+
          '<div class="grow"><div class="strong">'+adminEsc(report.content_type.replace(/_/g,' '))+' Â· '+adminEsc(report.content_ref)+'</div>'+
          '<div class="muted">'+adminEsc(report.reason)+'</div>'+
          '<div class="muted">by '+adminEsc(report.reported_by)+(report.community?' Â· '+adminEsc(report.community):'')+' Â· '+adminEsc(report.created_at)+'</div></div>'+
          '<span class="admin-tag '+(report.status==='open'?'warning':(report.status==='resolved'?'ok':'neutral'))+'">'+adminEsc(report.status)+'</span>'+
          (open?'<div class="admin-row-actions"><button class="admin-icon-btn" onclick="goToReportedPost('+index+')">Go to post</button><button class="admin-icon-btn danger" onclick="deleteReportedPost('+index+')">Delete</button></div>':'')+
        '</div>';
      }).join(''):'<div class="admin-empty">No reports in this view.</div>';
    }
  }catch(error){
    if(list)list.innerHTML='<div class="admin-empty">'+adminEsc(error.message)+'</div>';
  }
}

async function reportCommunityPost(postId){
  const reason=window.prompt('Why are you reporting this post? (Up to 255 characters)');
  if(reason===null)return;
  const trimmedReason=reason.trim();
  if(!trimmedReason){
    window.alert('Please enter a reason for the report.');
    return;
  }
  if(trimmedReason.length>255){
    window.alert('Report reasons are limited to 255 characters.');
    return;
  }
  try{
    const data=await adminApi('report_create',{
      content_type:'community_post',
      content_ref:String(postId),
      community:feedCommunityName(),
      reason:trimmedReason
    });
    window.alert(data.message||'Report submitted for review.');
  }catch(error){
    window.alert(error.message||'The report could not be submitted.');
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

async function deleteReportedPost(index){
  const report=adminState.reports[index];
  if(!report)return;
  const type=report.content_type==='community_post'?'posts':report.content_type;
  if(!window.confirm('Delete this '+type.replace(/_/g,' ')+' permanently? This cannot be undone.'))return;
  try{
    await adminApi('content_delete',{type:type,id:report.content_ref});
    await adminApi('report_resolve',{id:report.id,status:'resolved'});
    setAdminStatus('Post deleted and report resolved','ok');
    loadAdminReports();
  }catch(error){
    setAdminStatus(error.message,'err');
  }
}

function goToReportedPost(index){
  const report=adminState.reports[index];
  if(!report)return;
  closeAdminModal();
  if(typeof showPage!=='function')return;
  showPage('PHchat');
  setTimeout(function(){
    if(typeof switchPHChatView==='function')switchPHChatView('posts',document.querySelector('.phchat-tab[data-view="posts"]')||document.querySelector('.phchat-tab'));
    if(typeof selectFeedCommunity==='function')selectFeedCommunity(report.community);
  },250);
}



// â€”â€”â€” Logs â€”â€”â€”
async function loadAdminLogs(){
  const tbody=document.getElementById('admin-log-rows');
  setAdminStatus('Loading logsâ€¦');
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
