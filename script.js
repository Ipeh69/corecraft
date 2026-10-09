
// ——— MOBILE MENU ———
function toggleMenu(){document.getElementById('mobile-menu').classList.toggle('open')}
function closeMenu(){document.getElementById('mobile-menu').classList.remove('open')}

// ——— AUTH ———
let isLoggedIn=false,pendingPage=null,PHchatInit=false,activePHChat=null,phChatPoll=null,activeDMEmail=null,dmSearchTimer=null;
let pendingCommunityDelete=null;
const protected_=['compatibility','build','pricing','troubleshoot','profile','PHchat'];
let PHCHAT_COMMUNITIES=[
  {id:'Recommendation Builds',icon:'💡',description:'Share and discover recommended PC builds'},
  {id:'Part Pricing',icon:'💸',description:'Deals and store prices'},
  {id:'Troubleshooting',icon:'🛠️',description:'Fix PC problems together'},
  {id:'Build Showcase',icon:'🏆',description:'Share your finished build'}
];

function showPage(name){
  if(protected_.includes(name)&&!isLoggedIn){pendingPage=name;showPage('login');return}
  document.querySelectorAll('.page').forEach(p=>p.classList.remove('active'));
  const el=document.getElementById('page-'+name);
    if(el)el.classList.add('active');
  window.scrollTo(0,0);
  closeMenu();
  if(name==='pricing'&&!pricingInit)initChat('pricing');
  if(name==='pricing'){
    if(window.L&&!philippinesMap)initPhilippinesMap();
    else if(window.L&&philippinesMap)setTimeout(()=>philippinesMap.invalidateSize(),50);
    else if(!window.L)setMapStatus('Leaflet map library is unavailable. Check your internet connection.');
    if(window.L&&philippinesMap&&!hasLocatedUser&&!autoLocateTried){
      autoLocateTried=true;
      locateOnMap(true);
    }
  }
  if(name==='troubleshoot'&&!troubleInit)initChat('trouble');
  if(name==='build'&&!buildaiInit)initChat('buildai');
  if(name==='PHchat'&&!PHchatInit)initPHChat();
  if(name==='build'){updateSavedBuildsList();renderBuildIdeas();}
  if(name==='profile')renderRecentActivity();
}

const RECENT_ACTIVITY_KEY='corecraft_recent_activity';
function getActivityStorageKey(){
  return window._user&&window._user.email?RECENT_ACTIVITY_KEY+'_'+window._user.email.toLowerCase():'';
}
function recordActivity(icon,title,details,color){
  const key=getActivityStorageKey();
  if(!key)return;
  let activities=[];
  try{activities=JSON.parse(localStorage.getItem(key)||'[]');}catch(error){activities=[];}
  activities.unshift({icon:icon,title:title,details:details||'',color:color||'rgba(0,229,255,.1)',createdAt:Date.now()});
  localStorage.setItem(key,JSON.stringify(activities.slice(0,12)));
  renderRecentActivity();
}
function formatActivityAge(timestamp){
  const seconds=Math.max(0,Math.floor((Date.now()-Number(timestamp))/1000));
  if(seconds<60)return 'Just now';
  if(seconds<3600)return Math.floor(seconds/60)+' min ago';
  if(seconds<86400)return Math.floor(seconds/3600)+' hr ago';
  const days=Math.floor(seconds/86400);
  return days+' day'+(days===1?'':'s')+' ago';
}
function renderRecentActivity(){
  const container=document.getElementById('recent-activity-list');
  if(!container)return;
  const key=getActivityStorageKey();
  let activities=[];
  try{activities=key?JSON.parse(localStorage.getItem(key)||'[]'):[];}catch(error){activities=[];}
  if(!Array.isArray(activities)||activities.length===0){
    container.innerHTML='<div style="padding:18px 0;color:var(--muted);font-size:14px">Your activity will appear here as you use CoreCraft.</div>';
    return;
  }
  container.innerHTML=activities.map(function(activity,index){
    return `<div class="budget-row" style="${index===activities.length-1?'border-bottom:none':''}"><div style="display:flex;align-items:center;gap:14px;min-width:0"><div style="width:36px;height:36px;border-radius:10px;background:${escapeHtml(activity.color||'rgba(0,229,255,.1)')};display:flex;align-items:center;justify-content:center;font-size:16px;flex-shrink:0">${escapeHtml(activity.icon||'•')}</div><div style="min-width:0"><div style="font-size:14px;font-weight:500">${escapeHtml(activity.title||'Activity')}</div><div style="font-size:12px;color:var(--muted);overflow:hidden;text-overflow:ellipsis;white-space:nowrap">${escapeHtml(activity.details||'')}</div></div></div><div style="font-size:12px;color:var(--muted);white-space:nowrap">${formatActivityAge(activity.createdAt)}</div></div>`;
  }).join('');
}

// ——— PHILIPPINES SHOP MAP ———
let autoLocateTried=false, philippinesMap=null, philippinesMarkers=[], userLocationMarker=null, hasLocatedUser=false, lastLocatedPosition=null;
let shopSearchRequest=0,lastGeocodeSearchAt=0;
const geocodedShopLocations={};
const pcRetailerPattern=/PC ?Express|PCX |Octagon|Easy ?PC|DynaQuest|Villman|PC ?Worx|PC ?Worth|Gigahertz|JDM Techno|Data ?Blitz|Silicon Valley|PC ?Hub|Complink|Bermor|PC Parts|Computer Parts|PC Builders|Computer Builders/i;
const nonRetailPattern=/internet|net ?caf|cafe|café|gaming|e-?games|pisonet|rental|school|college|institute|university|academy|training/i;
const philippinesBounds={south:4.3,west:116.8,north:21.3,east:126.8};

function initPhilippinesMap(){
  const mapEl=document.getElementById('philippines-map');
  if(!mapEl||!window.L)return;
  philippinesMap=L.map(mapEl,{scrollWheelZoom:false}).setView([12.8797,121.774],5);
  L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png',{
    maxZoom:15,
    attribution:'&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
  }).addTo(philippinesMap);
  setTimeout(fitPhilippinesMap,50);
}

function applyUserLocation(latitude,longitude,useForSearch,approximate){
  lastLocatedPosition={lat:latitude,lng:longitude};
  hasLocatedUser=true;
  philippinesMap.setView([latitude,longitude],approximate?12:14);
  if(userLocationMarker)philippinesMap.removeLayer(userLocationMarker);
  userLocationMarker=L.circleMarker([latitude,longitude],{radius:8,color:'#fff',weight:3,fillColor:'#2563eb',fillOpacity:1}).addTo(philippinesMap).bindPopup(approximate?'Your approximate location':'Your current location');
  if(!approximate)try{localStorage.setItem('corecraft_last_location',JSON.stringify({lat:latitude,lng:longitude,time:Date.now()}));}catch(e){}
  if(!useForSearch){setMapStatus('Showing your current location.');return;}
  const searchInput=document.getElementById('stock-location');
  if(searchInput)searchInput.value='My current location';
  setMapStatus(approximate?'Using your approximate location. Loading nearby PC stores...':'Location found. Loading nearby PC stores...');
  searchPhilippinesShops('my current location',lastLocatedPosition).catch(error=>{
    setMapStatus('Could not load mapped stores: '+error.message+' Press “Use my location” to retry.');
  });
}

async function locateByIp(useForSearch,reason){
  try{
    const response=await fetch('https://ipwho.is/');
    const data=await response.json();
    if(!data||data.success===false||!Number.isFinite(data.latitude)||!Number.isFinite(data.longitude))throw new Error('no ip location');
    if(hasLocatedUser)return;
    applyUserLocation(data.latitude,data.longitude,useForSearch,true);
  }catch(error){
    setMapStatus(reason+' Type your city in the area box to search instead.');
  }
}

function locateOnMap(useForSearch){
  if(!philippinesMap||!window.L){
    setMapStatus('The map is not ready yet. Check your internet connection and try again.');
    return;
  }
  if(!navigator.geolocation||!window.isSecureContext){
    locateByIp(useForSearch,'Precise location is unavailable in this browser.');
    return;
  }
  let usedSaved=false;
  if(!hasLocatedUser){
    try{
      const saved=JSON.parse(localStorage.getItem('corecraft_last_location')||'null');
      if(saved&&Number.isFinite(saved.lat)&&Number.isFinite(saved.lng)&&Date.now()-saved.time<86400000){
        usedSaved=true;
        applyUserLocation(saved.lat,saved.lng,useForSearch,false);
      }
    }catch(e){}
  }
  if(!usedSaved)setMapStatus('Finding your current location...');
  navigator.geolocation.getCurrentPosition(pos=>{
    const {latitude,longitude}=pos.coords;
    const moved=!lastLocatedPosition||Math.abs(lastLocatedPosition.lat-latitude)>0.005||Math.abs(lastLocatedPosition.lng-longitude)>0.005;
    if(!usedSaved||moved)applyUserLocation(latitude,longitude,useForSearch,false);
  },error=>{
    if(usedSaved)return;
    const reason=error.code===error.PERMISSION_DENIED?'Location access was denied. Allow it in your browser for exact results.':'Finding your exact location failed.';
    locateByIp(useForSearch,reason);
  },{enableHighAccuracy:false,timeout:8000,maximumAge:600000});
}
function fitPhilippinesMap(){
  if(!philippinesMap||!window.L)return;
  philippinesMap.invalidateSize();
  if(hasLocatedUser)return;
  const countryBounds=L.latLngBounds(
    [philippinesBounds.south,philippinesBounds.west],
    [philippinesBounds.north,philippinesBounds.east]
  );
  philippinesMap.fitBounds(countryBounds,{padding:[8,8]});
}

function setMapStatus(message){
  const status=document.getElementById('map-status');
  if(status)status.textContent=message;
}

function isInsidePhilippines(location){
  const lat=typeof location.lat==='function'?location.lat():location.lat;
  const lng=typeof location.lng==='function'?location.lng():(typeof location.lng==='number'?location.lng:location.lon);
  return lat>=philippinesBounds.south&&lat<=philippinesBounds.north&&lng>=philippinesBounds.west&&lng<=philippinesBounds.east;
}

function clearPhilippinesMarkers(){
  if(philippinesMap)philippinesMarkers.forEach(marker=>philippinesMap.removeLayer(marker));
  philippinesMarkers=[];
}

function renderShopResults(results){
  const container=document.getElementById('shop-results');
  if(!container)return;
  container.innerHTML=results.map((place,index)=>{
    const address=place.address||'Address unavailable';
    return `<button type="button" class="shop-result" onclick="focusShopResult(${index})"><div class="shop-result-heading"><span class="shop-result-name">${escapeHtml(place.name||'Unnamed shop')}</span><span class="shop-type-tag">${escapeHtml(place.serviceType)}</span></div><div class="shop-result-meta">${escapeHtml(address)}</div></button>`;
  }).join('');
  window._philippinesShopResults=results;
}

function focusShopResult(index){
  const place=(window._philippinesShopResults||[])[index];
  if(!place||!place.lat||!place.lon||!philippinesMap)return;
  philippinesMap.setView([place.lat,place.lon],16);
  const location=[place.name,place.address].filter(Boolean).join(' — ');
  const input=document.getElementById('stock-location');
  if(input)input.value=location;
}

function getOSMCoordinates(element){
  if(typeof element.lat==='number'&&typeof element.lon==='number')return {lat:element.lat,lon:element.lon};
  if(element.center&&typeof element.center.lat==='number'&&typeof element.center.lon==='number')return {lat:element.center.lat,lon:element.center.lon};
  return null;
}

function getOSMAddress(tags){
  const street=[tags['addr:housenumber'],tags['addr:street']].filter(Boolean).join(' ');
  return [street,tags['addr:suburb'],tags['addr:city']||tags['addr:town']||tags['addr:municipality'],tags['addr:province']].filter(Boolean).join(', ')||tags['addr:full']||'';
}

async function geocodeShopSearchArea(location){
  const cacheKey=location.toLowerCase();
  if(geocodedShopLocations[cacheKey])return geocodedShopLocations[cacheKey];
  const wait=Math.max(0,1000-(Date.now()-lastGeocodeSearchAt));
  if(wait)await new Promise(resolve=>setTimeout(resolve,wait));
  lastGeocodeSearchAt=Date.now();
  const controller=new AbortController();
  const timeout=setTimeout(()=>controller.abort(),12000);
  try{
    const url='https://nominatim.openstreetmap.org/search?format=jsonv2&limit=1&q='+encodeURIComponent(location+', Philippines');
    const response=await fetch(url,{headers:{'Accept':'application/json'},signal:controller.signal});
    if(!response.ok)throw new Error('Place lookup returned HTTP '+response.status);
    const places=await response.json();
    if(!Array.isArray(places)||!places.length)throw new Error('No matching Philippine city or area was found.');
    const center={lat:Number(places[0].lat),lng:Number(places[0].lon)};
    if(!Number.isFinite(center.lat)||!Number.isFinite(center.lng)||!isInsidePhilippines(center)){
      throw new Error('No matching place inside the Philippines was found.');
    }
    geocodedShopLocations[cacheKey]=center;
    return center;
  }finally{clearTimeout(timeout);}
}

function getShopListingsQuery(center,radius){
  const scope=`(around:${radius||30000},${center.lat},${center.lng})`;
  const listings=`nwr["shop"="computer"]${scope};nwr["shop"="electronics"]["name"~"${pcRetailerPattern.source}",i]${scope};nwr["shop"="electronics"]["brand"~"${pcRetailerPattern.source}",i]${scope};`;
  return `[out:json][timeout:12];(${listings});out tags center;`;
}

async function fetchShopListings(center,radius){
  const cacheKey='shops:'+center.lat.toFixed(2)+','+center.lng.toFixed(2)+','+radius;
  try{
    const cached=JSON.parse(localStorage.getItem(cacheKey)||'null');
    if(cached&&Date.now()-cached.time<21600000)return cached.elements;
  }catch(e){}
  const query=getShopListingsQuery(center,radius);
  const mirrors=['https://overpass-api.de/api/interpreter','https://overpass.private.coffee/api/interpreter','https://overpass.openstreetmap.fr/api/interpreter','https://maps.mail.ru/osm/tools/overpass/api/interpreter'];
  for(let attempt=0;attempt<2;attempt++){
    const controllers=mirrors.map(()=>new AbortController());
    const timer=setTimeout(()=>controllers.forEach(c=>c.abort()),10000);
    try{
      const elements=await Promise.any(mirrors.map(async(endpoint,i)=>{
        const response=await fetch(endpoint,{
          method:'POST',
          headers:{'Content-Type':'application/x-www-form-urlencoded;charset=UTF-8'},
          body:'data='+encodeURIComponent(query),
          signal:controllers[i].signal
        });
        if(!response.ok)throw new Error('OpenStreetMap search returned HTTP '+response.status);
        const data=await response.json();
        if(!Array.isArray(data.elements)||data.remark||!data.elements.length)throw new Error('OpenStreetMap returned no usable response.');
        return data.elements;
      }));
      controllers.forEach(c=>c.abort());
      if(elements.length)try{localStorage.setItem(cacheKey,JSON.stringify({time:Date.now(),elements:elements}));}catch(e){}
      return elements;
    }catch(error){
      if(attempt)return await fetchShopListingsFromNominatim(center,radius);
      await new Promise(resolve=>setTimeout(resolve,1200));
    }finally{clearTimeout(timer);}
  }
}
async function fetchShopListingsFromNominatim(center,radius){
  const dLat=radius/111000, dLng=radius/(111000*Math.cos(center.lat*Math.PI/180));
  const viewbox=[center.lng-dLng,center.lat+dLat,center.lng+dLng,center.lat-dLat].join(',');
  const results=[];
  for(const term of ['computer store','computer shop','computer parts','PC Express','Octagon','EasyPC','DynaQuest','Villman']){
    const response=await fetch('https://nominatim.openstreetmap.org/search?format=jsonv2&limit=40&bounded=1&countrycodes=ph&q='+encodeURIComponent(term)+'&viewbox='+viewbox);
    if(!response.ok)throw new Error('OpenStreetMap servers are busy. Please try again in a moment.');
    (await response.json()).forEach(item=>{
      if(item.category!=='shop'||!['computer','electronics'].includes(item.type))return;
      results.push({type:item.osm_type||'node',id:item.osm_id,lat:parseFloat(item.lat),lon:parseFloat(item.lon),tags:{name:item.name||(item.display_name||'').split(',')[0],shop:item.type}});
    });
  }
  return results;
}
async function searchPhilippinesShops(location,nearbyCenter){
  let center=nearbyCenter||lastLocatedPosition;
  const requestId=++shopSearchRequest;
  let locationLabel=location?`near ${location}`:(center?'near your location':'near your selected area');
  recordActivity('🗺️','Searched PC stores',locationLabel,'rgba(0,229,255,.1)');
  if(!philippinesMap||!window.L){
    throw new Error('Leaflet did not load. Check your internet connection and reload the Pricing page.');
  }
  try{
    if(location&&location.toLowerCase()!=='my current location'){
      center=await geocodeShopSearchArea(location);
      locationLabel=`near ${location}`;
    }
    if(!center)throw new Error('Enter a city or area, or allow location access to search nearby.');
    if(requestId!==shopSearchRequest)return [];
    setMapStatus(`Searching OpenStreetMap for computer parts stores ${locationLabel}...`);
    clearPhilippinesMarkers();
    let elements=[];
    for(const radius of [25000,80000]){
      elements=await fetchShopListings(center,radius);
      if(elements.length||requestId!==shopSearchRequest)break;
      setMapStatus(`No stores within ${radius/1000} km. Widening the search...`);
    }
    if(requestId!==shopSearchRequest)return;
    const seen={};
    const filtered=[];
    elements.forEach(function(element){
      const tags=element.tags||{};
      const coordinates=getOSMCoordinates(element);
      const name=tags.name||tags.brand||tags.operator;
      const label=[tags.name,tags.brand,tags.operator].join(' ');
      const isKnownChain=pcRetailerPattern.test(label);
      const isComputerStore=(tags.shop==='computer'&&!nonRetailPattern.test(label))||(tags.shop==='electronics'&&isKnownChain);
      if(!coordinates||!name||!isComputerStore||!isInsidePhilippines(coordinates))return;
      const id=element.type+'/'+element.id;
      if(seen[id])return;
      seen[id]=true;
      const serviceType=isKnownChain?'PC parts store':'Computer store';
      filtered.push({chain:isKnownChain,id:id,name:name,lat:coordinates.lat,lon:coordinates.lon,address:getOSMAddress(tags)||tags['addr:place']||tags['addr:district']||tags['addr:province']||'Philippines',serviceType:serviceType,tags:tags});
    });
    filtered.sort((a,b)=>(b.chain?1:0)-(a.chain?1:0));
    if(!filtered.length){
      renderShopResults([]);
      setMapStatus(`No computer parts stores were found ${locationLabel}. OpenStreetMap coverage varies; try another city or area.`);
      return filtered;
    }
    filtered.forEach(place=>{
      const marker=L.marker([place.lat,place.lon]).addTo(philippinesMap);
      const popup=document.createElement('div');
      const title=document.createElement('strong');
      title.textContent=place.name;
      const type=document.createElement('div');
      type.className='shop-popup-type';
      type.textContent=place.serviceType;
      const address=document.createElement('div');
      address.textContent=place.address;
      popup.appendChild(title);
      popup.appendChild(type);
      popup.appendChild(address);
      const icon=L.divIcon({className:'shop-marker-wrap',html:'<span class="shop-marker" aria-hidden="true">PC</span>',iconSize:[34,34],iconAnchor:[17,17],popupAnchor:[0,-16]});
      marker.setIcon(icon);
      marker.bindPopup(popup);
      philippinesMarkers.push(marker);
    });
    if(filtered.length===1)philippinesMap.setView([filtered[0].lat,filtered[0].lon],14);
    else philippinesMap.fitBounds(L.latLngBounds(filtered.map(function(place){return [place.lat,place.lon];})),{padding:[20,20],maxZoom:12});
    renderShopResults(filtered);
    setMapStatus(`${filtered.length} computer store listing${filtered.length===1?'':'s'} ${locationLabel}. OpenStreetMap coverage may be incomplete; listings do not confirm current stock or availability.`);
    return filtered;
  }catch(error){
    if(requestId!==shopSearchRequest)return [];
    renderShopResults([]);
    const reason=error&&error.name==='AbortError'?'the request timed out':(error&&error.message?error.message:'the service is unavailable');
    setMapStatus(`Map search failed (${reason}). Try a different city or area, or search again later.`);
    throw error;
  }
}

async function checkPartStock(){
  const part=(document.getElementById('stock-part')?.value||'').trim();
  const area=(document.getElementById('stock-location')?.value||'').trim();
  const source=(document.getElementById('stock-source')?.value||'').trim();
  const result=document.getElementById('stock-result');
  const button=document.getElementById('stock-check-btn');
  if(button.disabled)return;
  if(!part){
    if(result)result.innerHTML='<span style="color:var(--orange)">Enter the exact PC part or model you want to check.</span>';
    return;
  }
  if(!area){
    if(result)result.innerHTML='<span style="color:var(--orange)">Enter your city or area, or click “Use my location” before searching.</span>';
    return;
  }
  const useCurrentLocation=area.toLowerCase()==='my current location';
  const locationLabel=useCurrentLocation?'your current location':area;
  if(useCurrentLocation&&!lastLocatedPosition){
    if(result)result.innerHTML='<span style="color:var(--orange)">Your location is not available yet. Click “Use my location” and allow location access first.</span>';
    return;
  }
  recordActivity('📦','Searched PC stores and stock',part+' near '+locationLabel,'rgba(255,107,53,.1)');
  button.disabled=true;
  result.innerHTML='<span style="color:var(--muted)">Finding nearby stores, then asking Gemini to search current listings for this part and area...</span>';
  try{
    let nearbyStores=[];
    try{
      nearbyStores=await searchPhilippinesShops(useCurrentLocation?'My current location':area,useCurrentLocation?lastLocatedPosition:null);
    }catch(mapError){
      setMapStatus('Could not load mapped stores: '+(mapError.message||'OpenStreetMap is unavailable')+'. Gemini will still search retailers for this part and area.');
    }
    const mappedStoreContext=nearbyStores.length
      ?`\nThe map found ${nearbyStores.length} computer stores. Search these local business names for the requested part; the map is not inventory evidence. Names passed to search:\n${nearbyStores.slice(0,60).map(store=>`- ${store.name} — ${store.address} (${store.serviceType})`).join('\n').slice(0,4500)}`
      :'\nThe map could not provide store listings for this area. Search relevant Philippine retailers directly and do not invent local branches.';
    const response=await fetch('gemini.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({
      feature:'pricing',
      grounded_search:true,
      message:`Search the web now for this exact computer part and current Philippine retailer listings in ${locationLabel}. Part/model: ${part}. Requested area: ${area}. ${source?'Also inspect this retailer product URL as a source: '+source+'.':''}

Search each mapped business name where web results allow, and search official Philippine computer-store product pages, including PC Express, EasyPC, DynaQuest, Octagon, VillMan, PCWORX, and other relevant retailers. Never claim every store was searched if you cannot verify that. Include only actual stores and product pages supported by search sources. For each result give store/branch, exact matching product/model, current listed price in PHP, stock wording from its source, source date if shown, and the direct product/source URL. Clearly distinguish “listed in stock online” from confirmed branch stock; use “STOCK NOT VERIFIED” unless a source explicitly shows current availability for that product at that branch. A store's presence on a map is not proof it sells this part or has stock. If no reliable current matching listing is found, say so instead of guessing. State when you searched and recommend confirming with the store before travelling or paying.${mappedStoreContext}`
    })});
    const data=await response.json();
    if(!response.ok||!data.reply)throw new Error(data.error||'Gemini request failed');
    const sources=Array.isArray(data.sources)?data.sources:[];
    const sourceList=sources.length?`<div class="stock-sources"><strong>Web sources</strong><ul>${sources.map(source=>`<li><a href="${escapeHtml(source.url)}" target="_blank" rel="noopener noreferrer">${escapeHtml(source.title||source.url)}</a></li>`).join('')}</ul></div>`:'';
    const searchedAt=data.searchedAt?new Date(data.searchedAt).toLocaleString():'';
    const checkedTime=searchedAt?` Search performed ${escapeHtml(searchedAt)}.`:'';
    const groundingNotice=data.grounded
      ?`<p class="stock-verification-note">Gemini used web search. A source can show an online listing but does not guarantee live branch inventory; confirm directly with the seller.${checkedTime}</p>`
      :`<p class="stock-verification-note">No citable web results were returned, so current stock and prices could not be independently verified. Treat any estimates above as unverified and check with the seller.${checkedTime}</p>`;
    result.innerHTML=`<div class="ai-header"><div class="ai-avatar">🤖</div><span class="ai-label">Live-search stock report</span></div>${formatAIResponse(data.reply)}${sourceList}${groundingNotice}`;
  }catch(error){
    result.innerHTML=`<span style="color:var(--orange)">Stock check unavailable: ${escapeHtml(error.message||'Unknown error')}</span>`;
  }finally{button.disabled=false;}
}

// ——— COMPONENTS DATA ———
const componentData = {
  motherboard: [
    {id: 'mb1', name: 'ASUS B550-F', socket: 'AM4', formFactor: 'ATX', ramType: 'DDR4', price: 8500},
    {id: 'mb2', name: 'MSI B450 Tomahawk', socket: 'AM4', formFactor: 'ATX', ramType: 'DDR4', price: 7200},
    {id: 'mb3', name: 'Gigabyte B650 Aorus Elite', socket: 'AM5', formFactor: 'ATX', ramType: 'DDR5', price: 12000},
    {id: 'mb4', name: 'ASRock B550M Pro4', socket: 'AM4', formFactor: 'mATX', ramType: 'DDR4', price: 6500},
    {id: 'mb5', name: 'ASUS Prime Z690-A', socket: 'LGA1700', formFactor: 'ATX', ramType: 'DDR5', price: 15000}
  ],
  cpu: [
    {id: 'cpu1', name: 'Ryzen 5 5600X', socket: 'AM4', cores: 6, price: 9200},
    {id: 'cpu2', name: 'Ryzen 7 5700X', socket: 'AM4', cores: 8, price: 12500},
    {id: 'cpu3', name: 'Intel Core i5-12400F', socket: 'LGA1700', cores: 6, price: 8800},
    {id: 'cpu4', name: 'Ryzen 5 7600X', socket: 'AM5', cores: 6, price: 15500},
    {id: 'cpu5', name: 'Intel Core i7-12700K', socket: 'LGA1700', cores: 12, price: 18500}
  ],
  ram: [
    {id: 'ram1', name: 'Corsair Vengeance 16GB DDR4 3200', type: 'DDR4', speed: 3200, capacity: 16, price: 3400},
    {id: 'ram2', name: 'G.Skill Ripjaws 32GB DDR4 3600', type: 'DDR4', speed: 3600, capacity: 32, price: 5800},
    {id: 'ram3', name: 'Corsair Dominator 16GB DDR5 5200', type: 'DDR5', speed: 5200, capacity: 16, price: 4500},
    {id: 'ram4', name: 'Kingston HyperX 8GB DDR4 2666', type: 'DDR4', speed: 2666, capacity: 8, price: 1500},
    {id: 'ram5', name: 'TeamGroup T-Force 64GB DDR4 3200', type: 'DDR4', speed: 3200, capacity: 64, price: 12000}
  ],
  gpu: [
    {id: 'gpu1', name: 'RTX 3060', vram: 12, power: 550, price: 25000},
    {id: 'gpu2', name: 'RTX 3070', vram: 8, power: 650, price: 28000},
    {id: 'gpu3', name: 'RTX 4060', vram: 8, power: 550, price: 22000},
    {id: 'gpu4', name: 'RX 7600', vram: 8, power: 550, price: 23000},
    {id: 'gpu5', name: 'RTX 4070', vram: 12, power: 700, price: 42000}
  ],
  storage: [
    {id: 'ssd1', name: 'Samsung 970 EVO 1TB NVMe', type: 'NVMe', capacity: 1000, price: 5800},
    {id: 'ssd2', name: 'WD Blue 500GB SATA', type: 'SATA', capacity: 500, price: 2800},
    {id: 'ssd3', name: 'Crucial P5 Plus 2TB NVMe', type: 'NVMe', capacity: 2000, price: 9500},
    {id: 'ssd4', name: 'Seagate Barracuda 2TB HDD', type: 'HDD', capacity: 2000, price: 3200},
    {id: 'ssd5', name: 'Kingston A2000 250GB NVMe', type: 'NVMe', capacity: 250, price: 1800}
  ],
  psu: [
    {id: 'psu1', name: 'Corsair RM550x 550W 80+ Gold', wattage: 550, efficiency: 'Gold', price: 4200},
    {id: 'psu2', name: 'EVGA 600 W1 600W 80+ White', wattage: 600, efficiency: 'White', price: 2800},
    {id: 'psu3', name: 'Seasonic Focus GX-650 650W 80+ Gold', wattage: 650, efficiency: 'Gold', price: 5500},
    {id: 'psu4', name: 'Cooler Master MWE 500W 80+ Bronze', wattage: 500, efficiency: 'Bronze', price: 2200},
    {id: 'psu5', name: 'be quiet! Straight Power 11 750W 80+ Platinum', wattage: 750, efficiency: 'Platinum', price: 7200}
  ]
};

const components=[
  {id:'1',name:'Motherboard',type:'motherboard',value:'Not Selected',price:0,icon:'🔲',tag:'○ Select Motherboard',status:'empty',detail:''},
  {id:'2',name:'CPU',type:'cpu',value:'Not Selected',price:0,icon:'🧠',tag:'○ Select CPU',status:'empty',detail:''},
  {id:'3',name:'RAM',type:'ram',value:'Not Selected',price:0,icon:'💾',tag:'○ Select RAM',status:'empty',detail:''},
  {id:'4',name:'GPU',type:'gpu',value:'Not Selected',price:0,icon:'🎮',tag:'○ Select GPU',status:'empty',detail:''},
  {id:'5',name:'Storage',type:'storage',value:'Not Selected',price:0,icon:'💿',tag:'○ Select Storage',status:'empty',detail:''},
  {id:'6',name:'PSU',type:'psu',value:'Not Selected',price:0,icon:'🔌',tag:'○ Select PSU',status:'empty',detail:''}
];

function getAllMotherboards() {
  return componentData.motherboard;
}

function getAllCPUs() {
  return componentData.cpu;
}

function getAllRAM() {
  return componentData.ram;
}

function getAllGPUs() {
  return componentData.gpu;
}

function getAllStorage() {
  return componentData.storage;
}

function getAllPSUs() {
  return componentData.psu;
}

function getComponentsByType(type) {
  switch(type) {
    case 'motherboard': return getAllMotherboards();
    case 'cpu': return getAllCPUs();
    case 'ram': return getAllRAM();
    case 'gpu': return getAllGPUs();
    case 'storage': return getAllStorage();
    case 'psu': return getAllPSUs();
    default: return [];
  }
}

function getTypicalPriceEstimate(type,value){
  const text=String(value||'').toLowerCase();
  const known=componentData[type]&&componentData[type].find(function(item){return item.name.toLowerCase()===text;});
  if(known&&known.price)return '₱'+Number(known.price).toLocaleString();
  const estimates={
    motherboard:[['b550',6500,9000],['b650',9000,14000],['h610',4500,7000],['b760',7000,11000]],
    cpu:[['ryzen 5 5600',8500,10500],['ryzen 5 7600',13000,17000],['ryzen 5 7600x',14500,18000],['i3-12100',5000,7500],['i5-12400',8000,11500]],
    ram:[['16gb',2500,4000],['32gb',5000,7500],['64gb',10000,16000]],
    gpu:[['rx 6600',18000,24000],['rtx 3060',20000,28000],['rtx 4060',20000,26000],['rtx 4070',35000,48000]],
    storage:[['1tb',3500,6500],['2tb',6500,11000],['500gb',2000,3500]],
    psu:[['550w',2500,4500],['650w',3500,6000],['750w',4500,7500],['500w',2200,4000]]
  };
  const match=(estimates[type]||[]).find(function(item){return text.indexOf(item[0])>-1;});
  if(!match)return '';
  return '₱'+match[1].toLocaleString()+' - ₱'+match[2].toLocaleString();
}

function renderComponents(){
  checkCompatibility();
  const compat=components.filter(c=>c.status==='compatible').length;
  const compatStatusEl=document.getElementById('compat-status');
  if(compatStatusEl){
    compatStatusEl.textContent=`✓ ${compat} of 6 Parts Compatible`;
  }
  const gridEl=document.getElementById('components-grid');
  if(gridEl){
    gridEl.innerHTML=components.map(c=>`
      <div class="comp-card ${c.status!=='empty'?c.status:''}" onclick="highlightCompatCard(this)">
        <div class="status-light ${c.status==='compatible'?'green':c.status==='warning'?'red':'gray'}"></div>
        <div class="comp-icon">${c.icon}</div>
        <div class="comp-name">${c.name}</div>
        <div class="comp-value" style="color:${c.status==='empty'?'var(--muted)':'var(--text)'}">${c.value}</div>
        <div class="comp-tag ${c.status==='warning'?'warn':c.status==='empty'?'empty':'ok'}">${c.tag}</div>
        ${(c.aiPrice||getTypicalPriceEstimate(c.type,c.value)) ? `<div class="comp-ai-price">${c.aiPrice?'AI estimate'+(c.aiPriceSource?' for '+escapeHtml(c.aiPriceSource):''):'Typical PH estimate'}: ${escapeHtml(c.aiPrice||getTypicalPriceEstimate(c.type,c.value))}</div>` : ''}
        ${c.detail ? `<div class="comp-detail">${c.detail}</div>` : ''}
      </div>`).join('');
  }
  const budgetEl=document.getElementById('budget-list');
  if(budgetEl){
    budgetEl.innerHTML='';
    budgetEl.style.display='none';
  }
}

function highlightCompatCard(cardEl){
  if(!cardEl) return;
  const grid=document.getElementById('components-grid');
  if(!grid) return;
  grid.querySelectorAll('.comp-card.active-card').forEach(function(el){
    el.classList.remove('active-card');
  });
  cardEl.classList.add('active-card');
}

function applyAiPriceEstimates(text,priceSource){
  const categories={cpu:'cpu',motherboard:'motherboard',mb:'motherboard',ram:'ram',memory:'ram',gpu:'gpu',graphics:'gpu',storage:'storage',ssd:'storage',hdd:'storage',psu:'psu',power:'psu'};
  String(text||'').split(/\r?\n/).forEach(function(line){
    const normalized=line.toLowerCase();
    let type=null;
    Object.keys(categories).some(function(label){
      if(normalized.indexOf(label)>-1){type=categories[label];return true;}
      return false;
    });
    if(!type||line.indexOf('|')===-1)return;
    const values=[];
    const matches=line.match(/(?:₱|php\s*)?\s*\d[\d,]*(?:\.\d+)?/gi)||[];
    matches.forEach(function(value){
      const number=parseFloat(value.replace(/[^0-9.]/g,''));
      if(number>0)values.push(number);
    });
    if(values.length<1)return;
    const low='₱'+Math.round(values[0]).toLocaleString();
    const high='₱'+Math.round(values[Math.min(1,values.length-1)]).toLocaleString();
    const display=low===high?low:low+' - '+high;
    const component=components.find(function(item){return item.type===type;});
    if(component){
      component.aiPrice=display;
      component.aiPriceSource=priceSource||'';
    }
  });
  renderComponents();
}

renderComponents();

async function runCompatCheck(){
  syncComponentsFromFields();
  const cpu=document.getElementById('compat-cpu').value.trim();
  const mb=document.getElementById('compat-mb').value.trim();
  const ram=document.getElementById('compat-ram').value.trim();
  const gpu=document.getElementById('compat-gpu').value.trim();
  const storage=document.getElementById('compat-storage').value.trim();
  const psu=document.getElementById('compat-psu').value.trim();
  const pccase=document.getElementById('compat-case').value.trim();
  const priceStore=(document.getElementById('compat-price-store')?.value||'').trim();
  if(!cpu||!mb||!ram||!psu){
    alert('Please fill in CPU, Motherboard, RAM, and PSU to run the AI compatibility check.');
    return;
  }
  recordActivity('🔍','Ran compatibility check',`${cpu} + ${mb}${gpu?' + '+gpu:''}`,'rgba(0,229,255,.1)');
  // Gemini is the source of truth for this run. The local database is used only
  // by the part picker and remains the fallback if Gemini is unavailable.
  components.forEach(function(component){
    if(component.status !== 'empty'){
      component.status='compatible';
      component.tag='✓ Selected';
      component.detail='';
    }
  });
  renderComponents();
  const result=document.getElementById('ai-compat-result');
  if(result){
    result.style.display='block';
    result.innerHTML='<strong>🤖 Gemini is analyzing your complete build...</strong>';
  }
  const buildContext=`CPU: ${cpu}\nMotherboard: ${mb}\nRAM: ${ram}\nGPU: ${gpu||'Not selected'}\nStorage: ${storage||'Not selected'}\nPSU: ${psu}\nCase: ${pccase||'Not selected'}\nPrice store or city: ${priceStore||'Not specified'}`;
  try{
    const response=await fetch('gemini.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({
      feature:'compat',
      message:'Give a complete engineering review of this build. Put IMMEDIATE CHANGES directly after the verdict, explain why each change is needed, provide replacement options, and finish with FINAL RECOMMENDATION. Do not stop after the first issue. Distinguish physical incompatibility from a performance bottleneck. After FINAL RECOMMENDATION, add a section titled ESTIMATED PHILIPPINES PRICES. List every entered part using this exact format: PART | LOW ESTIMATE | HIGH ESTIMATE. Use Philippine pesos and realistic current-market ranges based on the exact model. If a price store or city is provided in context, tailor the range to that store or city and name it; do not invent an exact live seller price or claim stock. Add TOTAL ESTIMATED BUILD COST with a low and high total. Label these as estimates, state that prices vary by seller and date, and recommend checking the current listing before buying.',
      context:buildContext
    })});
    const data=await response.json();
    if(!response.ok||!data.reply)throw new Error(data.error||'Gemini request failed');
    const aiText=data.reply;
    applyAiPriceEstimates(aiText,priceStore);
    const status=document.getElementById('compat-status');
    const needsChanges=/needs changes|not compatible|incompatible|mismatch|cannot work|does not support|too weak/i.test(aiText);
    if(status)status.textContent=needsChanges?'⚠ AI verdict: Needs Changes':'✓ AI verdict: Compatible';
    if(result){
      result.innerHTML='<div class="ai-header"><div class="ai-avatar">🤖</div><span class="ai-label">Gemini Compatibility and Price Estimate</span></div><div style="line-height:1.7;margin-top:12px">'+formatAIResponse(aiText)+'</div><p style="color:var(--muted);margin:16px 0 0;font-size:12px">Prices are AI estimates for the Philippines, not guaranteed live quotes. Confirm the current price, stock, warranty, and seller before buying.</p>';
    }
  }catch(error){
    checkCompatibility();
    renderComponents();
    const errorText=error.message||'Unknown Gemini error';
    const quotaMessage=/quota|rate limit|limit reached|429/i.test(errorText)?'Gemini quota reached. The local compatibility checker is being used until quota resets.':'Gemini analysis unavailable. The local compatibility checker is being used.';
    if(result)result.innerHTML='<strong>⚠️ '+quotaMessage+'</strong><p style="color:var(--muted);margin:8px 0 0">Reason: '+escapeHtml(errorText)+'</p>';
  }
}

function formatAIResponse(text){
  return escapeHtml(text)
    .replace(/^###\s*(.*?)$/gm,'<h3 style="margin:20px 0 8px;font-size:18px">$1</h3>')
    .replace(/^##\s*(.*?)$/gm,'<h3 style="margin:20px 0 8px;font-size:18px">$1</h3>')
    .replace(/\*\*(.*?)\*\*/g,'<strong>$1</strong>')
    .replace(/\n/g,'<br>');
}

function getAICompatAnalysis(cpu,mb,ram,gpu,storage,psu,pccase){
  const cpuL=cpu.toLowerCase();
  const mbL=mb.toLowerCase();
  const ramL=ram.toLowerCase();
  const gpuL=gpu.toLowerCase();
  const psuL=psu.toLowerCase();
  let issues=[];
  let notes=[];
  let cpuSocket='Unknown';
  if(cpuL.includes('ryzen')){
    if(cpuL.includes('7600')||cpuL.includes('7700')||cpuL.includes('7800')||cpuL.includes('7900')||cpuL.includes('7900x')||cpuL.includes('7950')) cpuSocket='AM5';
    else cpuSocket='AM4';
  } else if(cpuL.includes('intel')||cpuL.includes('core i')){
    cpuSocket='LGA1700';
  }
  let mbSocket='Unknown';
  if(mbL.includes('b650')||mbL.includes('x670')||mbL.includes('am5')) mbSocket='AM5';
  else if(mbL.includes('b550')||mbL.includes('b450')||mbL.includes('am4')) mbSocket='AM4';
  else if(mbL.includes('z690')||mbL.includes('b760')||mbL.includes('lga1700')||mbL.includes('z790')) mbSocket='LGA1700';
  let ramType='Unknown';
  if(ramL.includes('ddr5')) ramType='DDR5';
  else if(ramL.includes('ddr4')) ramType='DDR4';
  let mbRamType='Unknown';
  if(mbSocket==='AM5'||mbSocket==='LGA1700') mbRamType='DDR5';
  else if(mbSocket==='AM4') mbRamType='DDR4';
  if(cpuSocket!=='Unknown'&&mbSocket!=='Unknown'&&cpuSocket!==mbSocket){
    issues.push(`⚠️ **Socket mismatch** — CPU is ${cpuSocket} but motherboard is ${mbSocket}. Use a matching CPU or motherboard.`);
  } else if(cpuSocket!=='Unknown'&&mbSocket!=='Unknown'){
    notes.push(`✅ CPU and motherboard are likely compatible with ${cpuSocket}.`);
  }
  if(ramType!=='Unknown'&&mbRamType!=='Unknown'&&ramType!==mbRamType){
    issues.push(`⚠️ **RAM type mismatch** — build uses ${ramType} but motherboard likely supports ${mbRamType}. Choose matching RAM.`);
  } else if(ramType!=='Unknown'&&mbRamType!=='Unknown'){
    notes.push(`✅ RAM type ${ramType} matches the motherboard's expected memory.`);
  }
  let psuWatt=0;
  const match=psuL.match(/(\d+)\s?w/);
  if(match) psuWatt=parseInt(match[1],10);
  let gpuRequirement=0;
  if(gpuL.includes('4090')) gpuRequirement=850;
  else if(gpuL.includes('4080')) gpuRequirement=750;
  else if(gpuL.includes('4070')) gpuRequirement=700;
  else if(gpuL.includes('4060')) gpuRequirement=550;
  else if(gpuL.includes('rx 7600')||gpuL.includes('6600')||gpuL.includes('rtx 3060')) gpuRequirement=550;
  if(gpuRequirement>0 && psuWatt>0 && psuWatt < gpuRequirement){
    issues.push(`⚠️ **PSU too weak** — your GPU needs about ${gpuRequirement}W but your PSU is ${psuWatt}W. Upgrade to ${gpuRequirement}W+ for stability.`);
  } else if(gpuRequirement>0 && psuWatt>0){
    notes.push(`✅ PSU wattage looks sufficient for the listed GPU.`);
  }
  if(!gpu){
    notes.push(`💻 No GPU detected. If your CPU has integrated graphics, the system can still display output.`);
  }
  if(issues.length===0){
    issues.push('✅ No major compatibility issues detected from the entered parts.');
  }
  let response='🤖 **AI Compatibility Analysis**\n\n';
  issues.forEach(i=>response+=`${i}\n`);
  if(notes.length){
    response+=`\n📋 Notes:\n`;
    notes.forEach(n=>response+=`${n}\n`);
  }
  response+=`\nAsk me for details if you want to verify sockets, RAM type, PSU sizing, or GPU compatibility.`;
  return response;
}

function getCompatibilityNotes() {
  const warnings = components.filter(c => c.status === 'warning');
  const missing = components.filter(c => c.status === 'empty');
  
  let notes = [];
  if (missing.length > 0) {
    notes.push(`⚠️ ${missing.length} component(s) not selected`);
  }
  if (warnings.length > 0) {
    notes.push(`⚠️ ${warnings.length} compatibility issue(s) found`);
  }
  if (notes.length === 0) {
    notes.push('✅ All selected components are compatible!');
  }
  return notes.join('<br>');
}
function closeModal(){document.getElementById('compat-modal').classList.remove('open')}

let selectedComponentType = null;
let selectedComponentId = null;

function openComponentSelector(type) {
  selectedComponentType = type;
  selectedComponentId = null;
  const componentList = getComponentsByType(type);
  const typeName = type.charAt(0).toUpperCase() + type.slice(1);
  
  document.getElementById('component-modal-title').textContent = `Select ${typeName}`;
  document.getElementById('component-list').innerHTML = `
    <div style="margin-bottom:15px;padding:10px;background:var(--bg3);border-radius:8px;cursor:pointer;border:2px solid transparent" onclick="selectComponentItem(null)">
      <div style="font-weight:600;color:var(--muted)">Not Selected</div>
      <div style="font-size:12px;color:var(--muted)">Clear selection</div>
    </div>
    ${componentList.map(comp => `
      <div style="margin-bottom:10px;padding:15px;background:var(--bg3);border-radius:8px;cursor:pointer;border:2px solid transparent;transition:border-color .2s" onclick="selectComponentItem('${comp.id}')">
        <div style="font-weight:600;margin-bottom:5px">${comp.name}</div>
        <div style="font-size:12px;color:var(--muted)">${getComponentDetails(comp, type)}</div>
      </div>
    `).join('')}
  `;
  document.getElementById('component-modal').classList.add('open');
}

function getComponentDetails(comp, type) {
  switch(type) {
    case 'motherboard': return `${comp.socket} Socket / ${comp.formFactor} / ${comp.ramType}`;
    case 'cpu': return `${comp.socket} / ${comp.cores} Cores`;
    case 'ram': return `${comp.capacity}GB ${comp.type} ${comp.speed}MHz`;
    case 'gpu': return `${comp.vram}GB VRAM / ${comp.power}W Power`;
    case 'storage': return `${comp.capacity}GB ${comp.type}`;
    case 'psu': return `${comp.wattage}W / ${comp.efficiency} Efficiency`;
    default: return '';
  }
}

function setCompatInputForType(type, value) {
  const fieldMap = {motherboard:'compat-mb', cpu:'compat-cpu', ram:'compat-ram', gpu:'compat-gpu', storage:'compat-storage', psu:'compat-psu'};
  const input = document.getElementById(fieldMap[type]);
  if (input) input.value = value;
}

function syncComponentsFromFields() {
  const fieldMap = {motherboard:'compat-mb', cpu:'compat-cpu', ram:'compat-ram', gpu:'compat-gpu', storage:'compat-storage', psu:'compat-psu'};
  components.forEach(c => {
    const input = document.getElementById(fieldMap[c.type]);
    if (!input) return;
    const value = input.value.trim();
    if (!value) {
      c.value = `Not Selected`;
      c.price = 0;
      c.tag = `○ Select ${c.name}`;
      c.status = 'empty';
      c.detail = '';
    } else {
      c.value = value;
      if (c.status === 'empty') c.status = 'compatible';
      c.tag = '✓ Selected';
      c.detail = '';
    }
  });
}

function selectComponentItem(id) {
  selectedComponentId = id;
  const items = document.querySelectorAll('#component-list > div');
  items.forEach((item, index) => {
    if (index === 0 && id === null) {
      item.style.borderColor = 'var(--cyan)';
    } else if (index > 0 && componentData[selectedComponentType][index-1].id === id) {
      item.style.borderColor = 'var(--cyan)';
    } else {
      item.style.borderColor = 'transparent';
    }
  });
}

function selectComponent() {
  if (selectedComponentType && selectedComponentId !== undefined) {
    const componentIndex = components.findIndex(c => c.type === selectedComponentType);
    if (componentIndex !== -1) {
      if (selectedComponentId === null) {
        // Clear selection
        components[componentIndex] = {
          ...components[componentIndex],
          value: 'Not Selected',
          price: 0,
          tag: `○ Select ${components[componentIndex].name}`,
          status: 'empty',
          detail: ''
        };
        setCompatInputForType(selectedComponentType, '');
      } else {
        const selectedComp = componentData[selectedComponentType].find(c => c.id === selectedComponentId);
        if (selectedComp) {
          components[componentIndex] = {
            ...components[componentIndex],
            value: selectedComp.name,
            price: selectedComp.price,
            tag: '✓ Selected',
            status: 'compatible',
            detail: getComponentDetails(selectedComp, selectedComponentType)
          };
          setCompatInputForType(selectedComponentType, selectedComp.name);
        }
      }
      checkCompatibility();
      renderComponents();
    }
  }
  closeComponentModal();
}

function closeComponentModal() {
  document.getElementById('component-modal').classList.remove('open');
}

function inferCpuSocket(value) {
  const text = String(value || '').toLowerCase();
  if (text.includes('ryzen 7') || text.includes('ryzen 9')) {
    if (/(7600|7700|7800|7900|7950|8000|9000)/.test(text)) return 'AM5';
    return 'AM4';
  }
  if (text.includes('ryzen 3') || text.includes('ryzen 5')) {
    if (/(7600|8600|9600)/.test(text)) return 'AM5';
    return 'AM4';
  }
  if (text.includes('intel') || text.includes('core i')) {
    if (/(12|13|14)[0-9]{3}/.test(text)) return 'LGA1700';
  }
  return '';
}

function inferMotherboardSpecs(value) {
  const text = String(value || '').toLowerCase();
  if (/(b650|x670|a620|b850|x870|am5)/.test(text)) return {socket:'AM5', ramType:'DDR5'};
  if (/(b550|b450|a520|x570|am4)/.test(text)) return {socket:'AM4', ramType:'DDR4'};
  if (/(z690|z790|b660|b760|h610|h670|lga1700)/.test(text)) return {socket:'LGA1700', ramType:text.includes('ddr4') ? 'DDR4' : 'DDR5'};
  return {socket:'', ramType:''};
}

function inferRamType(value) {
  const text = String(value || '').toLowerCase();
  if (text.includes('ddr5')) return 'DDR5';
  if (text.includes('ddr4')) return 'DDR4';
  return '';
}

function inferGpuPower(value) {
  const text = String(value || '').toLowerCase();
  if (text.includes('4090')) return 850;
  if (text.includes('4080')) return 750;
  if (text.includes('4070')) return 700;
  if (text.includes('3090')) return 850;
  if (text.includes('3080')) return 750;
  if (text.includes('3070')) return 650;
  if (text.includes('4060') || text.includes('3060') || text.includes('rx 7600') || text.includes('rx 6600')) return 550;
  if (text.includes('3050')) return 450;
  return 0;
}

function inferPsuWattage(value) {
  const match = String(value || '').match(/(\d{3,4})\s*w/i);
  return match ? parseInt(match[1], 10) : 0;
}

function checkCompatibility() {
  // Reset selected component statuses before running checks
  components.forEach(c => {
    if (c.status !== 'empty') {
      c.status = 'compatible';
      c.tag = '✓ Selected';
      c.detail = '';
    }
  });

  // Basic compatibility checks
  const mb = components.find(c => c.type === 'motherboard');
  const cpu = components.find(c => c.type === 'cpu');
  const ram = components.find(c => c.type === 'ram');
  const gpu = components.find(c => c.type === 'gpu');
  const psu = components.find(c => c.type === 'psu');

  // Free-text parts need an exact model before the local database can verify them.
  const cpuData = componentData.cpu.find(c => c.name.toLowerCase() === cpu.value.toLowerCase());
  const mbData = componentData.motherboard.find(c => c.name.toLowerCase() === mb.value.toLowerCase());
  const gpuData = componentData.gpu.find(c => c.name.toLowerCase() === gpu.value.toLowerCase());
  const detectedCpuSocket = cpuData ? cpuData.socket : inferCpuSocket(cpu.value);
  const detectedMb = mbData || inferMotherboardSpecs(mb.value);
  const detectedRamType = inferRamType(ram.value);
  const detectedGpuPower = gpuData ? gpuData.power : inferGpuPower(gpu.value);
  const detectedPsuWattage = inferPsuWattage(psu.value);
  if (cpu.status === 'compatible' && !detectedCpuSocket) {
    cpu.status = 'warning';
    cpu.tag = '⚠ Exact Model Needed';
    cpu.detail = 'Ryzen 9 is a product family. Enter the exact model, such as Ryzen 9 5900X or Ryzen 9 7900X, to verify the motherboard socket.';
  }
  if (gpu.status === 'compatible' && !detectedGpuPower) {
    gpu.status = 'warning';
    gpu.tag = '⚠ Exact Model Needed';
    gpu.detail = 'RTX 3050 is not in the local parts database. It is generally compatible with modern PCIe motherboards, but the exact model is needed to verify power and case clearance.';
  }

  // CPU-Motherboard socket compatibility
  if (cpu.status === 'compatible' && mb.status === 'compatible') {
    if (detectedCpuSocket && detectedMb.socket && detectedCpuSocket !== detectedMb.socket) {
      cpu.status = 'warning';
      cpu.tag = '⚠ Socket Mismatch';
      cpu.detail = `CPU uses ${detectedCpuSocket}, but the motherboard uses ${detectedMb.socket}. These components cannot work together. Choose a ${detectedCpuSocket} motherboard.`;
      mb.status = 'warning';
      mb.tag = '⚠ Socket Mismatch';
      mb.detail = `Motherboard uses ${detectedMb.socket}, but the CPU requires ${detectedCpuSocket}. Choose a compatible motherboard.`;
    }
  }

  // RAM-Motherboard compatibility
  if (ram.status === 'compatible' && mb.status === 'compatible') {
    const ramData = componentData.ram.find(c => c.name === ram.value);
    const detectedRam = ramData ? ramData.type : detectedRamType;
    if (detectedRam && detectedMb.ramType && detectedRam !== detectedMb.ramType) {
      ram.status = 'warning';
      ram.tag = '⚠ RAM Type Mismatch';
      ram.detail = `This RAM is ${detectedRam}, but the motherboard supports ${detectedMb.ramType}. Select matching memory.`;
      mb.status = 'warning';
      mb.tag = '⚠ RAM Type Mismatch';
      mb.detail = `This motherboard supports ${detectedMb.ramType}, but the selected RAM is ${detectedRam}.`;
    }
  }

  // PSU-GPU power requirement
  if (gpu.status === 'compatible' && psu.status === 'compatible') {
    const psuData = componentData.psu.find(c => c.name === psu.value);
    const detectedPsu = psuData ? psuData.wattage : detectedPsuWattage;
    if (detectedGpuPower && detectedPsu && detectedPsu < detectedGpuPower) {
      psu.status = 'warning';
      psu.tag = `⚠ Need ${detectedGpuPower}W+`;
      psu.detail = `This PSU provides ${detectedPsu}W, but the GPU needs about ${detectedGpuPower}W. Upgrade to at least ${detectedGpuPower}W, preferably 80+ Gold.`;
      gpu.status = 'warning';
      gpu.tag = `⚠ Need ${detectedGpuPower}W+ PSU`;
      gpu.detail = `The GPU needs about ${detectedGpuPower}W, which is more than the selected PSU can provide.`;
    }
  }
}

function getCompatResponse(msg) {
  const lower = msg.toLowerCase();
  if (lower.includes('socket') || lower.includes('cpu') || lower.includes('motherboard')) {
    return `🔌 **CPU & Motherboard Compatibility Guide**\n\n• AMD Ryzen 7000/9000 series = AM5\n• AMD Ryzen 3000/5000 series = AM4\n• Intel 12th/13th gen = LGA1700\n\nYour CPU socket must match your motherboard socket exactly. If they differ, the CPU will not fit and the build will fail.`;
  }
  if (lower.includes('ram') || lower.includes('memory') || lower.includes('ddr')) {
    return `💾 **RAM Compatibility Guide**\n\n• DDR5 is for AM5 and newer Intel LGA1700 boards\n• DDR4 is for AM4 and older Intel platforms\n\nYour motherboard only supports one RAM type. Match the RAM type to the motherboard or it will not boot.`;
  }
  if (lower.includes('gpu') || lower.includes('power') || lower.includes('wattage')) {
    return `🎮 **GPU & PSU Guidance**\n\nCommon GPU power requirements:\n• RTX 4060 / RX 7600 — 550W minimum\n• RTX 4070 — 700W recommended\n• RTX 4080 — 750W+ required\n• RTX 4090 — 850W+ required\n\nAlways choose a PSU with at least 20% headroom above the GPU's requirement.`;
  }
  if (lower.includes('psu') || lower.includes('power supply')) {
    return `⚡ **PSU Size Guide**\n\n• Budget gaming: 550-650W 80+ Bronze/Gold\n• Mid-range: 750-850W 80+ Gold\n• High-end: 850-1000W 80+ Platinum\n\nIf you have a high-end GPU or multiple drives, choose a higher wattage PSU for stable power delivery.`;
  }
  if (lower.includes('recommend') || lower.includes('suggest') || lower.includes('build')) {
    return `💡 **Build Recommendation Help**\n\nTell me your budget and use case, for example:\n• "₱50,000 gaming PC for 1080p"\n• "₱120,000 video editing workstation"\n• "Office PC for ₱25,000"\n\nI can suggest the best parts for your goals.`;
  }
  return `❓ **Ask me about compatibility:**\n• "What CPU is compatible with my motherboard?"\n• "Do I need DDR4 or DDR5 RAM?"\n• "Is my PSU strong enough for my GPU?"\n• "Recommend a compatible build for ₱60K"`;
}

// ——— BUILD RECOMMENDATIONS ———
const builds = {
  gaming: [],
  office: [],
  students: [],
  streaming: [],
  editing: [],
  workstation: [],
  home: []
};

const recommendationTypes = Object.keys(builds);

function getActiveBuildType() {
  const active = document.querySelector('.build-tab.active');
  if (!active) return 'gaming';
  return active.getAttribute('data-type') || 'gaming';
}

function getLocalRecommendationFallback(type) {
  const fallback = {
    gaming: {name:'Gaming Value 1080p',description:'Balanced entry gaming build for esports and modern games.',budget:28000,parts:['CPU: Ryzen 5 5600','Motherboard: B550M','RAM: 16GB DDR4','GPU: RX 6600 8GB','Storage: 1TB NVMe SSD','PSU: 550W 80+ Bronze']},
    office: {name:'Office Productivity',description:'Reliable system for documents, browser work, meetings, and productivity.',budget:26000,parts:['CPU: Intel Core i3-12100','Motherboard: H610M','RAM: 16GB DDR4','GPU: Integrated UHD Graphics','Storage: 500GB SATA SSD','PSU: 500W 80+ Bronze']},
    students: {name:'Student Smart Ryzen 5',description:'Practical PC for schoolwork, coding, research, and online classes.',budget:22000,parts:['CPU: Ryzen 5 5600G','Motherboard: B550M','RAM: 16GB DDR4','GPU: Integrated Radeon Graphics','Storage: 512GB NVMe SSD','PSU: 500W 80+ Bronze']},
    streaming: {name:'Starter Streaming',description:'Affordable setup for livestreaming and esports content.',budget:30000,parts:['CPU: Ryzen 5 5600','Motherboard: B550M','RAM: 16GB DDR4','GPU: RTX 3060 12GB','Storage: 1TB NVMe SSD','PSU: 650W 80+ Bronze']},
    editing: {name:'Creator Editing',description:'Editing-focused build for 1080p projects and content creation.',budget:42000,parts:['CPU: Ryzen 7 7700','Motherboard: B650M','RAM: 32GB DDR5','GPU: RTX 4060 8GB','Storage: 2TB NVMe SSD','PSU: 650W 80+ Gold']},
    workstation: {name:'Developer Workstation',description:'Powerful build for development, rendering, virtualization, and multitasking.',budget:55000,parts:['CPU: Ryzen 9 7900','Motherboard: B650 ATX','RAM: 64GB DDR5','GPU: RTX 4070 12GB','Storage: 2TB NVMe SSD','PSU: 750W 80+ Gold']},
    home: {name:'Home Office Essential',description:'Quiet everyday system for browsing, documents, calls, and media.',budget:20000,parts:['CPU: Intel Core i3-12100','Motherboard: H610M','RAM: 16GB DDR4','GPU: Integrated UHD Graphics','Storage: 500GB SATA SSD','PSU: 500W 80+ Bronze']}
  };
  const item = fallback[type];
  if (!item) return [];
  return [{id:'local-'+type,build_name:item.name,description:item.description,estimated_budget:item.budget,components:item.parts.map(function(value){const split=value.indexOf(':');return {cat:value.substring(0,split),name:value.substring(split+1).trim()};})}];
}

async function loadRecommendedBuilds() {
  try {
    const res = await fetch('get_recommendations.php', { cache: 'no-store' });
    if (!res.ok) throw new Error('HTTP ' + res.status);

    const data = await res.json();
    recommendationTypes.forEach(function (type) {
      const rows = Array.isArray(data[type]) && data[type].length ? data[type] : getLocalRecommendationFallback(type);
      builds[type] = rows.map(function (row) {
        const componentList = Array.isArray(row.components) ? row.components : [];
        const parsedParts = componentList.map(function (item) {
          if (item && typeof item === 'object' && item.cat && item.name) {
            return {
              icon: '⚙️',
              cat: String(item.cat).trim(),
              name: String(item.name).trim(),
              raw: String(item.name).trim()
            };
          }

          const text = String(item || '');
          const splitIdx = text.indexOf(':');
          if (splitIdx > -1) {
            return {
              icon: '⚙️',
              cat: text.substring(0, splitIdx).trim(),
              name: text.substring(splitIdx + 1).trim(),
              raw: text.substring(splitIdx + 1).trim()
            };
          }

          return {
            icon: '⚙️',
            cat: 'Component',
            name: text,
            raw: text
          };
        });

        return {
          id: row.id,
          label: row.build_name || 'Unnamed Build',
          description: row.description || '',
          parts: parsedParts,
          budget: Number(row.price || row.estimated_budget || 0),
          pros: getBuildPros(type,row.build_name || '',parsedParts),
          cons: getBuildCons(type,row.build_name || '',parsedParts)
        };
      });
    });
  } catch (err) {
    recommendationTypes.forEach(function (type) {
      builds[type] = getLocalRecommendationFallback(type).map(function (row) {
        const parts = (row.components || []).map(function (item) {
          return {
            icon: '⚙️',
            cat: String(item.cat || 'Component'),
            name: String(item.name || ''),
            raw: String(item.name || '')
          };
        });
        return {
          id: row.id,
          label: row.build_name,
          description: row.description,
          parts: parts,
          budget: Number(row.estimated_budget || 0),
          pros: getBuildPros(type,row.build_name,parts),
          cons: getBuildCons(type,row.build_name,parts)
        };
      });
    });
  }

  renderBuilds(getActiveBuildType());
}

function getRecommendationPros(type){
  const pros={gaming:'Good 1080p gaming performance with a dedicated GPU and balanced CPU.',office:'Responsive for documents, browser multitasking, video calls, and daily productivity.',students:'Affordable and practical for schoolwork, coding, research, and light creative tasks.',streaming:'Handles livestreaming and esports while keeping the total build cost controlled.',editing:'More memory and fast storage improve timeline editing, exports, and content creation.',workstation:'High core count, large memory capacity, and dedicated graphics support demanding workloads.',home:'Reliable everyday performance with low power use and enough speed for common home and office tasks.'};
  return pros[type]||pros.home;
}

function getRecommendationCons(type){
  const cons={gaming:'The graphics card may need an upgrade for high-refresh 1440p or 4K gaming.',office:'Integrated graphics are not suitable for modern AAA games or GPU-heavy applications.',students:'Limited graphics performance and storage may require upgrades for heavier projects.',streaming:'Entry-level parts can struggle with demanding games and high-quality 4K streams.',editing:'Export times and complex effects are slower than on a higher-end workstation build.',workstation:'Higher power consumption and cost make it excessive for basic office or school use.',home:'Not designed for intensive gaming, 3D rendering, or professional video production.'};
  return cons[type]||cons.home;
}

function getBuildPros(type,label,parts){
  const name=label.toLowerCase();
  if(name.includes('beast')||name.includes('high'))return 'Strong dedicated graphics and CPU performance for high settings and demanding games.';
  if(name.includes('value')||name.includes('basic'))return 'Good value for everyday use with enough performance for the target workload.';
  if(name.includes('smart')||name.includes('pro'))return 'Balanced parts provide smooth multitasking with room for practical upgrades.';
  if(type==='gaming')return 'Dedicated graphics deliver a better gaming experience than an integrated-GPU build.';
  if(type==='office')return 'Fast startup, responsive applications, and efficient multitasking for office work.';
  if(type==='students')return 'Practical performance for school software, research, coding, and online classes.';
  return getRecommendationPros(type);
}

function getBuildCons(type,label,parts){
  const name=label.toLowerCase();
  if(name.includes('beast')||name.includes('high'))return 'Higher total cost and power draw; may be excessive for casual use.';
  if(name.includes('value')||name.includes('basic'))return 'Lower-tier parts may need an upgrade sooner for heavier workloads.';
  if(name.includes('smart')||name.includes('pro'))return 'Integrated or mid-range graphics limit advanced gaming and GPU-heavy applications.';
  if(type==='gaming')return 'Performance depends on game settings and may be limited at 1440p or 4K.';
  if(type==='office')return 'Not intended for modern AAA gaming, 3D rendering, or professional editing.';
  if(type==='students')return 'Limited graphics and storage capacity can restrict demanding creative projects.';
  return getRecommendationCons(type);
}

function switchBuild(type,btn){
  document.querySelectorAll('.build-tab').forEach(b=>b.classList.remove('active'));
  btn.classList.add('active');
  renderBuilds(type);
  recordActivity('💡','Viewed '+(btn.textContent||type).replace(/^\S+\s*/, '').trim()+' builds','Exploring recommended PC parts','rgba(124,58,237,.1)');
}
function useBuildRecommendation(encodedBuild){
  let build = null;
  try {
    build = JSON.parse(decodeURIComponent(encodedBuild));
  } catch (e) {
    build = null;
  }
  if (!build || !Array.isArray(build.parts)) return;
  recordActivity('🧩','Selected '+(build.label||'recommended build'),build.budget?'₱'+Number(build.budget).toLocaleString()+' build':'Recommended PC parts','rgba(124,58,237,.1)');

  const fieldMap = {
    cpu: 'compat-cpu',
    motherboard: 'compat-mb',
    ram: 'compat-ram',
    gpu: 'compat-gpu',
    storage: 'compat-storage',
    psu: 'compat-psu'
  };

  Object.values(fieldMap).forEach(id => {
    const input = document.getElementById(id);
    if (input) input.value = '';
  });

  build.parts.forEach(part => {
    const rawLine = (part.raw || '').toLowerCase();
    const cat = (part.cat || '').toLowerCase();
    const name = part.name || '';
    let type = null;
    if (cat.includes('cpu') || rawLine.indexOf('cpu:') === 0) type = 'cpu';
    else if (cat.includes('motherboard') || cat.includes('mb') || rawLine.indexOf('motherboard:') === 0) type = 'motherboard';
    else if (cat.includes('ram') || rawLine.indexOf('ram:') === 0) type = 'ram';
    else if (cat.includes('gpu') || rawLine.indexOf('gpu:') === 0) type = 'gpu';
    else if (cat.includes('storage') || cat.includes('ssd') || cat.includes('hdd') || rawLine.indexOf('storage:') === 0) type = 'storage';
    else if (cat.includes('psu') || rawLine.indexOf('psu:') === 0) type = 'psu';

    if (type && fieldMap[type]) {
      const input = document.getElementById(fieldMap[type]);
      if (input) input.value = name;
    }
  });

  syncComponentsFromFields();
  checkCompatibility();
  renderComponents();
  showPage('compatibility');

  const cpu = document.getElementById('compat-cpu')?.value.trim() || '';
  const mb = document.getElementById('compat-mb')?.value.trim() || '';
  const ram = document.getElementById('compat-ram')?.value.trim() || '';
  const psu = document.getElementById('compat-psu')?.value.trim() || '';
  if (cpu && mb && ram && psu) {
    setTimeout(() => runCompatCheck(), 220);
  }
}
function renderBuilds(type){
  const minimumBudget=20000;
  const maxBudget=Number(document.getElementById('build-budget')?.value||0);
  const allItems = Array.isArray(builds[type]) ? builds[type] : [];
  const items = allItems.filter(b=>b.budget>=minimumBudget&&(!maxBudget||b.budget<=maxBudget));
  if (items.length === 0) {
    document.getElementById('build-content').innerHTML = `
      <div class="build-card">
        <div class="build-card-title">No recommendations yet</div>
        <p style="margin-top:10px;color:var(--muted)">${maxBudget ? 'No build from ₱20,000 up matches your maximum budget.' : 'No recommended builds found for this category.'}</p>
      </div>`;
    return;
  }

  document.getElementById('build-content').innerHTML=items.map(b=>`
    <div class="build-card">
      <div class="build-card-header">
        <div class="build-card-title">${b.label}</div>
      </div>
      <p style="color:var(--muted);line-height:1.7;margin-bottom:14px">${b.description || 'No description available.'}</p>
      ${b.budget ? `<div style="font-size:18px;font-weight:800;color:var(--cyan);margin-bottom:14px">Estimated budget: ₱${b.budget.toLocaleString()}</div>` : ''}
      <div class="build-pros-cons" style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:16px">
        <div style="padding:12px;background:rgba(34,197,94,.08);border-left:3px solid var(--green)"><strong style="color:var(--green)">Pros</strong><div style="font-size:13px;color:var(--muted);margin-top:5px">${escapeHtml(b.pros)}</div></div>
        <div style="padding:12px;background:rgba(255,107,53,.08);border-left:3px solid var(--orange)"><strong style="color:var(--orange)">Cons</strong><div style="font-size:13px;color:var(--muted);margin-top:5px">${escapeHtml(b.cons)}</div></div>
      </div>
      ${Array.isArray(b.parts) && b.parts.length ? `
      <div class="build-parts">
        ${b.parts.map(p=>`<div class="build-part"><div class="build-part-icon">${p.icon}</div><div class="build-part-info"><div class="build-part-cat">${p.cat}</div><div class="build-part-name">${p.name}</div></div></div>`).join('')}
      </div>` : '<p style="color:var(--muted);margin-bottom:14px">No components listed for this build yet.</p>'}
      <div style="margin-top:20px;display:flex;gap:12px;flex-wrap:wrap">
        ${Array.isArray(b.parts) && b.parts.length ? `<button class="build-use-btn" onclick="useBuildRecommendation('${encodeURIComponent(JSON.stringify(b))}')">Use This Build →</button>` : ''}
        <button class="build-use-btn" style="background:transparent;border:1px solid var(--border);color:var(--text)" onclick="showPage('pricing')">Check Local Prices</button>
      </div>
    </div>`).join('');
}
loadRecommendedBuilds();
renderBuildIdeas();

// ——— AI CHAT (shared engine) ———
let pricingInit=false,troubleInit=false,compatInit=false,buildaiInit=false;
function initChat(id){
  if(id==='pricing'){pricingInit=true;addMsg('pricing','ai',`Hi! I'm CoreCraft's Pricing AI. I can find component prices from stores across the Philippines.\n\nTry asking:\n• "RTX 4060 price in Manila"\n• "Ryzen 5 5600 cheapest in Cebu"\n• "Budget PC parts price list"`)}
  if(id==='trouble'){troubleInit=true;addMsg('trouble','ai',`Hi! I'm CoreCraft's Troubleshooting AI Bot. Tell me your PC problem and I will guide you step-by-step to fix it.\n\nDescribe what's happening — e.g.:\n• "PC won't boot, no display"\n• "Keeps restarting randomly"\n• "Very slow after Windows update"`)}
  if(id==='compat'){compatInit=true;addMsg('compat','ai',`Hi! I'm your Compatibility AI. Enter your PC parts in the fields above and I will analyze socket compatibility, RAM type, PSU wattage, and GPU fitment for your system.\n\nTry:\n• "Check my build compatibility"\n• "Why is my RAM incompatible?"\n• "What PSU do I need for my GPU?"`)}
  if(id==='buildai'){buildaiInit=true;addMsg('buildai','ai',`Hi! I'm CoreCraft's AI Build Recommendation Engine.\n\nTell me your requirements:\n• Budget (e.g. ₱50,000)
• Purpose (gaming, work, streaming, editing)
• Performance target (1080p, 1440p, 4K, etc.)
\nExample: "Gaming PC for ₱50K, want 1440p 60fps"`)}
}
function addMsg(id,type,text){
  const wrap=document.getElementById(id+'-messages');
  const div=document.createElement('div');
  div.className='msg '+type;
  if(type==='ai'&&id!=='PHchat'){
    div.innerHTML=`<div class="msg-bubble"><div class="ai-header"><div class="ai-avatar">🤖</div><span class="ai-label">CoreCraft AI</span></div><div>${text.replace(/\*\*(.*?)\*\*/g,'<strong>$1</strong>')}</div></div>`;
  }else{div.innerHTML=`<div class="msg-bubble">${text}</div>`}
  wrap.appendChild(div);wrap.scrollTop=wrap.scrollHeight;
}
function formatChatTime(value){
  const date=new Date(String(value).replace(' ','T')+'Z');
  if(Number.isNaN(date.getTime()))return '';
  return date.toLocaleTimeString([], {hour:'numeric',minute:'2-digit'});
}
function addTyping(id){
  const wrap=document.getElementById(id+'-messages');
  const div=document.createElement('div');
  div.className='msg ai';div.id=id+'-typing';
  div.innerHTML=`<div class="msg-bubble"><div class="typing-dots"><div class="dot"></div><div class="dot"></div><div class="dot"></div></div></div>`;
  wrap.appendChild(div);wrap.scrollTop=wrap.scrollHeight;
}
function removeTyping(id){const el=document.getElementById(id+'-typing');if(el)el.remove()}
function setInput(id,text){document.getElementById(id+'-input').value=text}
function handleKey(e,id){if(e.key==='Enter'&&!e.shiftKey){e.preventDefault();sendChat(id)}}
function getGeminiContext(id){
  if(id!=='compat')return '';
  const fields=['cpu','mb','ram','gpu','storage','psu','case'];
  return fields.map(part=>{
    const input=document.getElementById('compat-'+part);
    return input&&input.value.trim()?`${part.toUpperCase()}: ${input.value.trim()}`:'';
  }).filter(Boolean).join('\n');
}
async function askGemini(id,text){
  if(id==='PHchat') return getPHChatResponse(text);
  const feature=id==='PHchat'?'phchat':id;
  const message=id==='pricing'?buildPricingQuery(text):text;
  const response=await fetch('gemini.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({feature,message,context:getGeminiContext(id)})});
  const data=await response.json();
  if(!response.ok||!data.reply)throw new Error(data.error||'Gemini request failed');
  return data.reply;
}
async function sendChat(id){
  const inp=document.getElementById(id+'-input');
  const text=inp.value.trim();if(!text)return;
  const topic=id==='PHchat'?(activePHChat||'Recommendation Builds'):'';
  const displayText=id==='PHchat'?`[${topic}] ${text}`:text;

  if(id==='PHchat'){
    if(!window._user||!window._user.authToken){alert('Please log in again to send messages.');return;}
    recordActivity('💬','Posted in PH Community',text,'rgba(99,102,241,.12)');
    await sendPHCommunityMessage(text);
    inp.value='';
    document.getElementById(id+'-send').disabled=false;
    return;
  }

  if(id==='pricing')recordActivity('📍','Asked pricing AI',text,'rgba(255,107,53,.1)');
  if(id==='trouble')recordActivity('🛠️','Asked troubleshooting AI',text,'rgba(34,197,94,.1)');
  if(id==='buildai')recordActivity('💡','Asked build recommendation AI',text,'rgba(124,58,237,.1)');
  addMsg(id,'user',displayText);inp.value='';
  document.getElementById(id+'-send').disabled=true;
  addTyping(id);
  try{
    const reply=await askGemini(id,text);
    removeTyping(id);
    addMsg(id,'ai',reply);
  }catch(error){
    removeTyping(id);
    let reply = '';
    if(id==='pricing') reply = getPricingResponse(buildPricingQuery(text));
    else if(id==='trouble') reply = getTroubleResponse(text);
    else if(id==='compat') reply = getCompatResponse(text);
    else if(id==='buildai') reply = getBuildAIResponse(text);
    addMsg(id,'ai',reply);
  }finally{
    document.getElementById(id+'-send').disabled=false;
  }
}

function initPHChat(){
  PHchatInit=true;
  activePHChat=localStorage.getItem('corecraft_active_phchat')||PHCHAT_COMMUNITIES[0].id;
  if(!PHCHAT_COMMUNITIES.some(c=>c.id===activePHChat))activePHChat=PHCHAT_COMMUNITIES[0].id;
  loadPHChatCommunities();
  if(!phChatPoll)phChatPoll=setInterval(function(){
    const page=document.getElementById('page-PHchat');
    if(page&&page.classList.contains('active')&&window._user){
      refreshPHChatCommunities();
      if(document.getElementById('phchat-posts-view')&&document.getElementById('phchat-posts-view').classList.contains('active'))loadCommunityPosts();
      if(document.getElementById('phchat-messages-view')&&document.getElementById('phchat-messages-view').classList.contains('active')){
        loadDMConversations();
        if(activeDMEmail)loadPrivateMessages();
      }
    }
  },5000);
}

async function phCommunityRequest(action,payload){
  const body=Object.assign({action:action,email:window._user.email,auth_token:window._user.authToken},payload||{});
  const response=await fetch('index.php?chat_api=1',{method:'POST',cache:'no-store',headers:{'Content-Type':'application/json','Cache-Control':'no-cache'},body:JSON.stringify(body)});
  const data=await response.json();
  if(!response.ok||!data.success)throw new Error(data.error||'Community request failed');
  return data;
}

async function loadPHChatCommunities(){
  try{
    const data=await phCommunityRequest('community_list');
    if(Array.isArray(data.communities)&&data.communities.length){
      PHCHAT_COMMUNITIES=data.communities.map(function(item){return {id:item.name,icon:'💬',description:item.description||'Community discussion',createdBy:item.created_by||''};});
      if(!PHCHAT_COMMUNITIES.some(function(item){return item.id===activePHChat;}))activePHChat=PHCHAT_COMMUNITIES[0].id;
    }
  }catch(error){
    // Keep the built-in rooms available if the community table is unavailable.
  }
  syncFeedCommunitySelect();
  renderPHChatFeedCommunities();
  loadCommunityPosts();
}

async function refreshPHChatCommunities(){
  if(!window._user)return;
  try{
    const data=await phCommunityRequest('community_list');
    if(!Array.isArray(data.communities)||!data.communities.length)return;
    const nextCommunities=data.communities.map(function(item){
      return {id:item.name,icon:'💬',description:item.description||'Community discussion',createdBy:item.created_by||''};
    });
    const currentSignature=PHCHAT_COMMUNITIES.map(function(item){return item.id+'|'+item.createdBy;}).join('||');
    const nextSignature=nextCommunities.map(function(item){return item.id+'|'+item.createdBy;}).join('||');
    if(currentSignature!==nextSignature){
      PHCHAT_COMMUNITIES=nextCommunities;
      if(!PHCHAT_COMMUNITIES.some(function(item){return item.id===activePHChat;}))activePHChat=PHCHAT_COMMUNITIES[0].id;
      syncFeedCommunitySelect();
      renderPHChatFeedCommunities();
    }
  }catch(error){
    // Keep the current room list if a background refresh fails.
  }
}

function syncFeedCommunitySelect(){
  const select=document.getElementById('feed-community');
  if(!select)return;
  const selected=PHCHAT_COMMUNITIES.some(function(item){return item.id===select.value;})?select.value:activePHChat;
  select.innerHTML=PHCHAT_COMMUNITIES.map(function(item){return `<option value="${escapeHtml(item.id)}">${escapeHtml(item.id)}</option>`;}).join('');
  select.value=selected;
  renderPHChatFeedCommunities();
}

function renderPHChatFeedCommunities(){
  const container=document.getElementById('PHchat-feed-communities');
  if(!container)return;
  container.innerHTML=PHCHAT_COMMUNITIES.map(function(item){
    const active=feedCommunityName()===item.id;
    const canDelete=window._user&&item.createdBy&&item.createdBy.toLowerCase()===window._user.email.toLowerCase();
    return `<div class="phchat-feed-community-row"><button type="button" class="phchat-feed-community ${active?'active':''}" onclick="selectFeedCommunity(decodeURIComponent('${encodeURIComponent(item.id)}'))"><span class="phchat-community-dot">${escapeHtml(item.icon||'•')}</span><span>${escapeHtml(item.id)}</span></button>${canDelete?`<button type="button" class="phchat-community-delete" title="Delete your community" aria-label="Delete ${escapeHtml(item.id)}" onclick="deletePHCommunity(decodeURIComponent('${encodeURIComponent(item.id)}'))">×</button>`:''}</div>`;
  }).join('');
}

function feedCommunityName(){
  const select=document.getElementById('feed-community');
  return select&&select.value?select.value:activePHChat;
}

function selectFeedCommunity(name){
  const select=document.getElementById('feed-community');
  if(!select)return;
  select.value=name;
  renderPHChatFeedCommunities();
  loadCommunityPosts();
}

function switchPHChatView(view,button){
  document.querySelectorAll('.phchat-view').forEach(function(panel){panel.classList.remove('active');});
  document.querySelectorAll('.phchat-tab').forEach(function(tab){tab.classList.remove('active');tab.setAttribute('aria-selected','false');});
  const panel=document.getElementById('phchat-'+view+'-view');
  if(panel)panel.classList.add('active');
  if(button){button.classList.add('active');button.setAttribute('aria-selected','true');}
  if(view==='posts')loadCommunityPosts();
  if(view==='messages')loadDMConversations();
}

function previewCommunityImage(input){
  const preview=document.getElementById('community-post-preview');
  const name=document.getElementById('community-image-name');
  if(!preview||!name)return;
  preview.innerHTML='';
  const file=input.files&&input.files[0];
  if(!file){name.textContent='Images up to 5 MB';return;}
  name.textContent=file.name;
  if(file.size>5*1024*1024){name.textContent='Image is larger than 5 MB';input.value='';return;}
  if(!/^image\/(jpeg|png|gif|webp)$/.test(file.type)){name.textContent='Choose JPEG, PNG, GIF, or WebP';input.value='';return;}
  const image=document.createElement('img');
  image.alt='Image preview';
  image.src=URL.createObjectURL(file);
  preview.appendChild(image);
}

async function publishCommunityPost(){
  const text=document.getElementById('community-post-text');
  const image=document.getElementById('community-post-image');
  const status=document.getElementById('community-post-status');
  const button=document.getElementById('community-post-submit');
  const message=text?text.value.trim():'';
  const file=image&&image.files?image.files[0]:null;
  if(!message&&!file){if(status)status.textContent='Write something or attach a picture first.';return;}
  if(file&&file.size>5*1024*1024){if(status)status.textContent='Image must be 5 MB or smaller.';return;}
  const form=new FormData();
  form.append('action','post_create');
  form.append('email',window._user.email);
  form.append('auth_token',window._user.authToken);
  form.append('community',document.getElementById('feed-community').value||activePHChat);
  form.append('message',message);
  if(file)form.append('image',file);
  button.disabled=true;
  if(status)status.textContent='Publishing post...';
  try{
    const response=await fetch('index.php?chat_api=1',{method:'POST',cache:'no-store',body:form});
    const data=await response.json();
    if(!response.ok||!data.success)throw new Error(data.error||'Post could not be published');
    text.value='';image.value='';
    document.getElementById('community-post-preview').innerHTML='';
    document.getElementById('community-image-name').textContent='Images up to 5 MB';
    if(status)status.textContent='Posted.';
    recordActivity('📝','Published a PH Community post',message.substring(0,100)||'Shared a picture','rgba(0,229,255,.1)');
    await loadCommunityPosts();
  }catch(error){if(status)status.textContent=error.message||'Post could not be published.';}
  finally{button.disabled=false;}
}

async function loadCommunityPosts(){
  const container=document.getElementById('community-post-list');
  const select=document.getElementById('feed-community');
  if(!container||!window._user)return;
  const community=select&&select.value?select.value:activePHChat;
  container.innerHTML='<div class="card" style="padding:18px;color:var(--muted)">Loading community posts...</div>';
  try{
    const data=await phCommunityRequest('post_list',{community:community});
    renderCommunityPosts(data.posts||[],container);
  }catch(error){container.innerHTML=`<div class="card" style="padding:18px;color:#fca5a5">${escapeHtml(error.message||'Could not load posts.')}</div>`;}
}

function renderCommunityPosts(posts,container){
  if(!container)return;
  if(!posts.length){container.innerHTML='<div class="card" style="padding:22px;color:var(--muted)">No posts here yet. Start the discussion with a build, picture, or PC question.</div>';return;}
  container.innerHTML=posts.map(function(post){
    const comments=Array.isArray(post.comments)?post.comments:[];
    const image=post.image_path?`<img class="phchat-post-image" src="${escapeHtml(post.image_path)}" alt="Image shared by ${escapeHtml(post.sender_name)}" loading="lazy">`:'';
    const commentHtml=comments.map(function(comment){return `<div class="phchat-comment"><strong>${escapeHtml(comment.sender_name||'Member')}:</strong> ${escapeHtml(comment.comment)}</div>`;}).join('');
    return `<article class="card phchat-post"><div class="phchat-post-header"><span class="phchat-post-author">${escapeHtml(post.sender_name||'CoreCraft user')}</span><time>${formatChatTime(post.created_at)}</time></div>${post.body?`<div class="phchat-post-body">${escapeHtml(post.body)}</div>`:''}${image}<div class="phchat-post-actions"><button type="button" class="phchat-action-button ${Number(post.liked)?'liked':''}" onclick="togglePostLike(${Number(post.id)})">${Number(post.liked)?'♥ Liked':'♡ Like'} · ${Number(post.like_count)||0}</button><span class="phchat-muted">${comments.length} comment${comments.length===1?'':'s'}</span></div><div class="phchat-comments">${commentHtml}</div><form class="phchat-comment-form" onsubmit="submitPostComment(event,${Number(post.id)})"><input class="chat-textarea" type="text" maxlength="1000" placeholder="Write a comment..." required><button class="phchat-action-button" type="submit">Comment</button></form></article>`;
  }).join('');
}

async function togglePostLike(postId){
  const community=document.getElementById('feed-community').value||activePHChat;
  try{await phCommunityRequest('post_like',{community:community,post_id:postId});await loadCommunityPosts();}
  catch(error){alert(error.message||'Like could not be saved.');}
}

async function submitPostComment(event,postId){
  event.preventDefault();
  const input=event.currentTarget.querySelector('input');
  const comment=input.value.trim();
  if(!comment)return;
  const community=document.getElementById('feed-community').value||activePHChat;
  try{await phCommunityRequest('post_comment',{community:community,post_id:postId,comment:comment});await loadCommunityPosts();}
  catch(error){alert(error.message||'Comment could not be posted.');}
}

async function searchDMUsers(){
  if(dmSearchTimer)clearTimeout(dmSearchTimer);
  dmSearchTimer=setTimeout(async function(){
    const query=(document.getElementById('dm-user-search').value||'').trim();
    const container=document.getElementById('dm-search-results');
    if(query.length<2){container.innerHTML='';return;}
    try{
      const data=await phCommunityRequest('dm_user_search',{query:query});
      container.innerHTML=(data.users||[]).map(function(user){return `<button type="button" class="dm-user-button" onclick="openPrivateConversation(decodeURIComponent('${encodeURIComponent(user.email)}'),decodeURIComponent('${encodeURIComponent(user.full_name)}'))"><strong>${escapeHtml(user.full_name)}</strong><span class="dm-user-email">${escapeHtml(user.email)}</span></button>`;}).join('')||'<span class="phchat-muted">No matching members</span>';
    }catch(error){container.innerHTML=`<span class="phchat-muted">${escapeHtml(error.message||'Member search failed')}</span>`;}
  },250);
}

async function loadDMConversations(){
  const container=document.getElementById('dm-conversation-list');
  if(!container||!window._user)return;
  try{
    const data=await phCommunityRequest('dm_conversations');
    container.innerHTML=(data.conversations||[]).map(function(item){return `<button type="button" class="dm-user-button ${activeDMEmail===item.email?'active':''}" onclick="openPrivateConversation(decodeURIComponent('${encodeURIComponent(item.email)}'),decodeURIComponent('${encodeURIComponent(item.name)}'))"><strong>${escapeHtml(item.name)}</strong><span class="dm-user-email">${escapeHtml(item.email)}</span></button>`;}).join('')||'<span class="phchat-muted">No conversations yet</span>';
  }catch(error){container.innerHTML=`<span class="phchat-muted">${escapeHtml(error.message||'Could not load messages')}</span>`;}
}

async function openPrivateConversation(email,name){
  activeDMEmail=email;
  document.getElementById('dm-active-name').textContent=name+' · '+email;
  document.getElementById('dm-send-button').disabled=false;
  await loadPrivateMessages();
  loadDMConversations();
}

async function loadPrivateMessages(){
  const container=document.getElementById('dm-messages');
  if(!container||!activeDMEmail)return;
  try{
    const data=await phCommunityRequest('dm_messages',{peer_email:activeDMEmail});
    container.innerHTML=(data.messages||[]).map(function(message){const mine=message.sender_email.toLowerCase()===window._user.email.toLowerCase();return `<div class="dm-message ${mine?'mine':''}">${escapeHtml(message.message)}<span class="dm-message-meta">${mine?'You ':''}${formatChatTime(message.created_at)}</span></div>`;}).join('');
    container.scrollTop=container.scrollHeight;
  }catch(error){container.innerHTML=`<div style="color:#fca5a5;padding:14px">${escapeHtml(error.message||'Could not load this conversation')}</div>`;}
}

async function sendPrivateMessage(){
  const input=document.getElementById('dm-message-input');
  const message=input.value.trim();
  if(!activeDMEmail||!message)return;
  try{
    await phCommunityRequest('dm_send',{peer_email:activeDMEmail,message:message});
    input.value='';
    recordActivity('✉️','Sent a private message','To '+activeDMEmail,'rgba(124,58,237,.1)');
    await loadPrivateMessages();
    loadDMConversations();
  }catch(error){alert(error.message||'Message could not be sent.');}
}

function updatePHChatHeader(){
  const community=PHCHAT_COMMUNITIES.find(function(item){return item.id===activePHChat;});
  const name=document.getElementById('PHchat-user-name');
  const description=document.getElementById('PHchat-community-description');
  if(name)name.textContent=community?community.id:'PH Lounge';
  if(description)description.textContent=community?community.description:'Chat with the PH community about builds, prices, and PC advice.';
}

async function openCreateCommunity(){
  if(!window._user||!window._user.authToken){alert('Please sign in before creating a community.');return;}
  const modal=document.getElementById('create-community-modal');
  const nameInput=document.getElementById('community-name-input');
  const descriptionInput=document.getElementById('community-description-input');
  const status=document.getElementById('create-community-status');
  if(!modal||!nameInput||!descriptionInput)return;
  nameInput.value='';
  descriptionInput.value='';
  if(status)status.textContent='';
  modal.classList.add('open');
  setTimeout(function(){nameInput.focus();},0);
}

function closeCreateCommunity(){
  const modal=document.getElementById('create-community-modal');
  if(modal)modal.classList.remove('open');
}

async function submitCreateCommunity(){
  const nameInput=document.getElementById('community-name-input');
  const descriptionInput=document.getElementById('community-description-input');
  const status=document.getElementById('create-community-status');
  const name=(nameInput&&nameInput.value||'').trim();
  const description=(descriptionInput&&descriptionInput.value||'').trim();
  if(!name||!description){if(status)status.textContent='Enter a community name and description.';return;}
  try{
    if(status)status.style.color='var(--muted)';
    if(status)status.textContent='Creating community...';
    const data=await phCommunityRequest('community_create',{name:name,description:description});
    const created=data.community;
    PHCHAT_COMMUNITIES.push({id:created.name,icon:'💬',description:created.description,createdBy:created.created_by||window._user.email});
    activePHChat=created.name;
    localStorage.setItem('corecraft_active_phchat',activePHChat);
    syncFeedCommunitySelect();
    renderPHChatFeedCommunities();
    await loadCommunityPosts();
    recordActivity('👥','Created community',created.name,'rgba(99,102,241,.12)');
    closeCreateCommunity();
  }catch(error){if(status){status.style.color='#fca5a5';status.textContent=error.message||'Community could not be created.';}}
}

async function deletePHCommunity(communityId){
  const community=PHCHAT_COMMUNITIES.find(function(item){return item.id===communityId;});
  if(!community||!community.createdBy||!window._user||community.createdBy.toLowerCase()!==window._user.email.toLowerCase())return;
  pendingCommunityDelete=communityId;
  const name=document.getElementById('delete-community-name');
  const status=document.getElementById('delete-community-status');
  const modal=document.getElementById('delete-community-modal');
  if(name)name.textContent=communityId;
  if(status)status.textContent='';
  if(modal)modal.classList.add('open');
}

function closeDeleteCommunityModal(){
  const modal=document.getElementById('delete-community-modal');
  if(modal)modal.classList.remove('open');
  pendingCommunityDelete=null;
}

async function confirmDeletePHCommunity(){
  const communityId=pendingCommunityDelete;
  if(!communityId)return;
  const community=PHCHAT_COMMUNITIES.find(function(item){return item.id===communityId;});
  if(!community||!community.createdBy||!window._user||community.createdBy.toLowerCase()!==window._user.email.toLowerCase()){
    closeDeleteCommunityModal();
    return;
  }
  const status=document.getElementById('delete-community-status');
  const button=document.getElementById('confirm-delete-community-button');
  if(button)button.disabled=true;
  if(status)status.textContent='Deleting community...';
  try{
    await phCommunityRequest('community_delete',{community:communityId});
    PHCHAT_COMMUNITIES=PHCHAT_COMMUNITIES.filter(function(item){return item.id!==communityId;});
    if(activePHChat===communityId){
      activePHChat=PHCHAT_COMMUNITIES[0].id;
      localStorage.setItem('corecraft_active_phchat',activePHChat);
    }
    syncFeedCommunitySelect();
    renderPHChatFeedCommunities();
    closeDeleteCommunityModal();
    await loadCommunityPosts();
  }catch(error){if(status)status.textContent=error.message||'Community could not be deleted.';}
  finally{if(button)button.disabled=false;}
}

function renderPHChatCommunities(){
  renderPHChatFeedCommunities();
}

function switchPHCommunity(communityId){
  selectFeedCommunity(communityId);
}

async function loadPHCommunityMessages(silent=false){
  const wrap=document.getElementById('PHchat-messages');
  if(!wrap||!window._user)return;
  if(!silent)wrap.innerHTML='<div style="padding:20px;color:var(--muted)">Loading community messages...</div>';
  try{
    const response=await fetch('index.php?chat_api=1',{method:'POST',cache:'no-store',headers:{'Content-Type':'application/json','Cache-Control':'no-cache'},body:JSON.stringify({action:'community_messages',email:window._user.email,auth_token:window._user.authToken,community:activePHChat})});
    const data=await response.json();
    if(!response.ok||!data.success)throw new Error(data.error||'Unable to load messages');
    wrap.innerHTML='';
    data.messages.forEach(message=>addMsg('PHchat',message.sender_email===window._user.email?'user':'ai',`${escapeHtml(`[${message.sender_name}] ${message.message}`)}<span class="chat-message-time">${formatChatTime(message.created_at)}</span>`));
  }catch(error){
    wrap.innerHTML='<div style="padding:20px;color:#fca5a5">Unable to load community messages. '+escapeHtml(error.message||'Please try another community.')+'</div>';
    if(/does not exist/i.test(error.message||''))loadPHChatCommunities();
  }
}

async function sendPHCommunityMessage(text){
  try{
    const response=await fetch('index.php?chat_api=1',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify({action:'community_send',email:window._user.email,auth_token:window._user.authToken,community:activePHChat,message:text})});
    const data=await response.json();
    if(!response.ok||!data.success)throw new Error(data.error||'Unable to send message');
    await loadPHCommunityMessages();
  }catch(error){alert(error.message||'Unable to send message.');}
}

function getPHChatMessages(){
  try{return JSON.parse(localStorage.getItem('corecraft_phchat_messages')||'{}');}catch(error){return {};}
}

function savePHChatMessage(type,text){
  const messages=getPHChatMessages();
  if(!Array.isArray(messages[activePHChat]))messages[activePHChat]=[];
  messages[activePHChat].push({type,text});
  localStorage.setItem('corecraft_phchat_messages',JSON.stringify(messages));
}

function renderPHChatMessages(){
  const wrap=document.getElementById('PHchat-messages');
  const header=document.getElementById('PHchat-user-name');
  if(!wrap||!header)return;
  header.textContent=activePHChat;
  wrap.innerHTML='';
  const messages=getPHChatMessages()[activePHChat]||[];
  if(messages.length===0){
    return;
  }
  messages.forEach(message=>addMsg('PHchat',message.type,message.text));
}

function getPHChatResponse(text){
  const lower=text.toLowerCase();
  if(lower.includes('hello')||lower.includes('hi')||lower.includes('hey')){
    return `Hey! Good to meet you. What are you planning to build or upgrade?`;
  }
  if(lower.includes('price')||lower.includes('buy')||lower.includes('store')||lower.includes('deal')){
    return `I usually compare prices in a few stores before buying. Checking your city and budget first helps a lot.`;
  }
  if(lower.includes('build')||lower.includes('gaming')||lower.includes('pc')||lower.includes('setup')){
    return `I recently built a mid-range gaming setup with Ryzen 5 and an RTX 4060. It runs 1080p smoothly and is easy to upgrade later.`;
  }
  if(lower.includes('thanks')||lower.includes('thank')){
    return `No problem! If you want, I can suggest a good budget list or a part combo that fits your setup.`;
  }
  if(lower.includes('ram')||lower.includes('gpu')||lower.includes('cpu')||lower.includes('motherboard')){
    return `For parts, I’d first check compatibility and budget. A balanced combo is better than buying the most expensive stuff.`;
  }
  return `Nice! Share the part or build you’re working on and someone here can give you a better suggestion.`;
}

function buildPricingQuery(msg){
  const locationInput = document.getElementById('pricing-location');
  if(!locationInput) return msg;
  const location = locationInput.value.trim();
  if(!location) return msg;
  const lower = msg.toLowerCase();
  if(lower.includes(location.toLowerCase())) return msg;
  return `${msg} in ${location}`;
}

function getPricingResponse(msg){
  const m=msg.toLowerCase();
  if(m.includes('manila')||m.includes('metro')){
    return `📍 **Component Prices in Metro Manila:**\n\n**GPU — RTX 4060:**\n• PC Corner (MOA): ₱22,000\n• Dynaquest (Gilmore): ₱21,500\n• PC Hub (Gilmore): ₱21,800\n\n**CPU — Ryzen 5 5600:**\n• Easy PC: ₱9,200\n• Villman (Greenhills): ₱9,400\n• PC Express: ₱9,100\n\n💡 **Tip:** Gilmore, Quezon City has the most competitive prices in Metro Manila. Visit on weekdays to avoid crowds.`;
  }
  if(m.includes('cebu')){
    return `📍 **Component Prices in Cebu:**\n\n**GPU — RTX 4060:**\n• PC Express (SM Cebu): ₱22,500\n• Mindpro Computer (IT Park): ₱22,200\n• Ayala Computer Center: ₱23,000\n\n**CPU — Ryzen 5 5600:**\n• Mindpro Computer: ₱9,500\n• PC Express Cebu: ₱9,300\n\n💡 **Tip:** IT Park Cebu has the best selection. Prices in Cebu are typically 2-5% higher than Manila.`;
  }
  if(m.includes('davao')){
    return `📍 **Component Prices in Davao:**\n\n**GPU — RTX 4060:**\n• Octagon (Victoria Plaza): ₱22,800\n• Silicon Valley (Gaisano): ₱23,100\n\n**CPU — Ryzen 5 5600:**\n• Octagon Superstore: ₱9,600\n• Silicon Valley: ₱9,700\n\n💡 **Tip:** Octagon in Victoria Plaza has the widest selection in Davao.`;
  }
  if(m.includes('rtx')||m.includes('gpu')||m.includes('graphics')){
    return `🎮 **GPU Price List (Philippines):**\n\n**Budget:**\n• GTX 1660 Super — ₱17,500–₱19,000\n• RX 6600 — ₱20,000–₱22,000\n\n**Mid-Range:**\n• RTX 4060 — ₱21,500–₱23,500\n• RTX 4060 Ti — ₱30,000–₱33,000\n• RX 7600 — ₱22,000–₱24,000\n\n**High-End:**\n• RTX 4070 — ₱42,000–₱46,000\n• RTX 4070 Super — ₱48,000–₱52,000\n• RTX 4080 — ₱78,000–₱85,000\n\nTell me your city and I'll find the closest store prices!`;
  }
  if(m.includes('ryzen')||m.includes('cpu')||m.includes('processor')){
    return `🧠 **CPU Price List (Philippines):**\n\n**Budget (AM4):**\n• Ryzen 3 4100 — ₱5,000–₱5,500\n• Ryzen 5 4500 — ₱7,500–₱8,000\n• Ryzen 5 5600 — ₱9,000–₱9,800\n\n**Mid-Range (AM5):**\n• Ryzen 5 7600 — ₱14,500–₱16,000\n• Ryzen 7 7700X — ₱21,000–₱24,000\n\n**High-End:**\n• Ryzen 9 7900X — ₱30,000–₱34,000\n• Ryzen 9 7950X — ₱52,000–₱58,000\n\nWhich city are you shopping in?`;
  }
  if(m.includes('ram')||m.includes('memory')){
    return `💾 **RAM Price List (Philippines):**\n\n**DDR4:**\n• 8GB 3200MHz — ₱1,200–₱1,600\n• 16GB (2×8) 3200 — ₱2,800–₱3,600\n• 32GB (2×16) 3600 — ₱5,000–₱6,500\n\n**DDR5:**\n• 16GB 4800MHz — ₱3,500–₱4,500\n• 32GB 5200MHz — ₱7,500–₱9,500\n• 64GB 6000MHz — ₱18,000–₱24,000\n\n💡 DDR4 still offers the best price-to-performance for most builds!`;
  }
  if(m.includes('budget')||m.includes('cheap')||m.includes('list')){
    return `💰 **Budget Gaming PC Price List (₱35,000):**\n\n• Ryzen 5 5600 (CPU) — ₱9,200\n• B550M Motherboard — ₱7,500\n• 16GB DDR4 3200 RAM — ₱3,400\n• GTX 1660 Super (GPU) — ₱18,500\n• 500GB NVMe SSD — ₱2,800\n• 550W PSU — ₱3,200\n• mATX Case — ₱1,500\n──────────────────\n**Total: ≈₱46,100**\n\n📍 Best places to buy:\n• Manila (Gilmore, QC) — cheapest\n• Cebu (IT Park) — good selection\n• Davao (Octagon) — reliable`;
  }
  return `I can find prices for any PC component in your city! Try:\n\n• "RTX 4060 price in [your city]"\n• "CPU prices in Cebu"\n• "Cheapest RAM in Manila"\n• "Budget PC parts list"\n\nWhich component or city are you looking for?`;
}

// ——— SAVE & LOAD BUILDS ———
function escapeJsString(s){
  return String(s||'').replace(/\\/g,'\\\\').replace(/'/g,"\\'");
}

function getSavedBuildsUserEmail(){
  return window._user && window._user.email ? String(window._user.email).toLowerCase() : '';
}

async function savedBuildsRequest(action,payload){
  const body=Object.assign({action},payload||{});
  const res=await fetch('saved_builds_api.php',{
    method:'POST',
    headers:{'Content-Type':'application/json'},
    body:JSON.stringify(body)
  });
  const data=await res.json().catch(function(){return {success:false,error:'Invalid server response'};});
  if(!res.ok||!data.success){
    throw new Error(data.error||('Request failed: '+res.status));
  }
  if(action==='save'){
    recordActivity('💾','Saved build',payload&&payload.build_name?payload.build_name:'My PC Build','rgba(34,197,94,.1)');
  }
  return data;
}

async function getSavedBuildsMap(){
  const email=getSavedBuildsUserEmail();
  if(!email){
    return {source:'local',builds:JSON.parse(localStorage.getItem('saved_builds')||'{}')};
  }
  const data=await savedBuildsRequest('list',{user_email:email});
  const map={};
  (data.items||[]).forEach(function(item){
    map[item.build_name]={
      timestamp:item.updated_at,
      components:Array.isArray(item.components)?item.components:[]
    };
  });
  return {source:'db',builds:map};
}

async function saveCurrentBuild(){
  syncComponentsFromFields();
  const buildName=prompt('Name this build:','My PC Build');
  if(!buildName)return;
  const selected=components.filter(c=>c.status!=='empty').map(c=>({type:c.type,value:c.value}));
  if(selected.length===0){alert('Please select at least one component');return}

  const email=getSavedBuildsUserEmail();
  if(email){
    try{
      await savedBuildsRequest('save',{user_email:email,build_name:buildName,components:selected});
      alert(`✅ Build "${buildName}" saved to your account!`);
    }catch(err){
      alert('Failed to save to database: '+err.message);
      return;
    }
  }else{
    const builds_local=JSON.parse(localStorage.getItem('saved_builds')||'{}');
    builds_local[buildName]={timestamp:new Date().toLocaleString(),components:selected};
    localStorage.setItem('saved_builds',JSON.stringify(builds_local));
    alert(`✅ Build "${buildName}" saved locally (guest mode).`);
    recordActivity('💾','Saved build',buildName,'rgba(34,197,94,.1)');
  }
  updateSavedBuildsList();
}

async function openLoadBuildsModal(){
  let payload;
  try{
    payload=await getSavedBuildsMap();
  }catch(err){
    alert('Failed to load saved builds: '+err.message);
    return;
  }
  const buildsMap=payload.builds;
  const list=Object.entries(buildsMap);
  if(list.length===0){alert('No saved builds yet. Save your first build!');return}
  const html=list.map(([name,data])=>`
    <div style="padding:15px;background:var(--bg3);border-radius:8px;border:1px solid var(--border);margin-bottom:12px;display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap">
      <div style="flex:1;min-width:200px">
        <div style="font-weight:600;margin-bottom:4px">${escapeHtml(name)}</div>
        <div style="font-size:12px;color:var(--muted)">${escapeHtml(data.timestamp||'')} • ${(data.components||[]).length} parts</div>
      </div>
      <div style="display:flex;gap:8px">
        <button class="quick-btn" style="background:var(--cyan);color:#000;padding:8px 14px;font-size:12px;border:none;cursor:pointer" onclick="loadBuild('${escapeJsString(name)}');closeSavedBuildsModal()">Load</button>
        <button class="quick-btn" style="background:#ef4444;color:white;padding:8px 14px;font-size:12px;border:none;cursor:pointer" onclick="deleteBuild('${escapeJsString(name)}')">Delete</button>
      </div>
    </div>`).join('');
  document.getElementById('saved-builds-modal-list').innerHTML=html;
  document.getElementById('saved-builds-modal').classList.add('open');
}
function closeSavedBuildsModal(){
  document.getElementById('saved-builds-modal').classList.remove('open');
}
async function updateSavedBuildsList(){
  const listContainer=document.getElementById('saved-builds-list');
  const contentContainer=document.getElementById('saved-builds-content');
  if(!listContainer||!contentContainer) return;
  let payload;
  try{
    payload=await getSavedBuildsMap();
  }catch(err){
    listContainer.style.display='none';
    return;
  }
  const list=Object.entries(payload.builds);
  if(list.length===0){listContainer.style.display='none';return}
  listContainer.style.display='block';
  contentContainer.innerHTML=list.map(([name,data])=>`
    <div class="build-card" style="padding:16px">
      <div style="font-weight:600;margin-bottom:8px">${escapeHtml(name)}</div>
      <div style="font-size:13px;color:var(--muted);margin-bottom:12px">${escapeHtml(data.timestamp||'')}<br>${(data.components||[]).length} parts selected</div>
      <button class="btn-gradient" style="width:100%;background:var(--cyan);color:#000;border:none;cursor:pointer" onclick="loadBuild('${escapeJsString(name)}')">Load Build</button>
    </div>`).join('');
}
function openAddBuildIdea(){
  document.getElementById('idea-title').value='';
  document.getElementById('idea-description').value='';
  const ideaCase=document.getElementById('idea-case'); if(ideaCase) ideaCase.value='';
  ['idea-cpu','idea-motherboard','idea-ram','idea-gpu','idea-storage','idea-psu'].forEach(id=>{
    const input=document.getElementById(id);
    if(input) input.value='';
  });
  // default category
  const cat=document.getElementById('idea-category'); if(cat) cat.value='gaming';
  document.getElementById('build-idea-modal').classList.add('open');
}
function closeBuildIdeaModal(){
  document.getElementById('build-idea-modal').classList.remove('open');
}
function saveBuildIdea(){
  const title=document.getElementById('idea-title').value.trim();
  const description=document.getElementById('idea-description').value.trim();
  if(!title||!description){
    alert('Please enter a build name and description.');
    return;
  }
  // prepare idea object from inputs
  const category=document.getElementById('idea-category')?document.getElementById('idea-category').value:'other';
  const idea={
    id: Date.now(),
    title,description,category,topic:activePHChat||'Recommendation Builds',timestamp:new Date().toLocaleString(),components:{}
  };
  ['cpu','motherboard','ram','gpu','storage','psu','case'].forEach(k=>{
    const v=document.getElementById('idea-'+k);
    if(v && v.value) idea.components[k]=v.value;
  });
  // send the idea to PHChat instead of saving locally
  sendIdeaToChat(idea);
  closeBuildIdeaModal();
}

function sendIdeaToChat(idea){
  const comps=idea.components||{};
  const compItems=['cpu','motherboard','ram','gpu','storage','psu','case'].map(k=>{
    const label = k.charAt(0).toUpperCase() + k.slice(1);
    if(!comps[k]) return '';
    return `<li><strong>${label}:</strong> ${comps[k]}</li>`;
  }).filter(Boolean).join('');
  const categoryLabel = idea.category==='gaming' ? '🎮 Gaming' : idea.category==='office' ? '💼 Office' : idea.category==='workstation' ? '🖥️ Workstation' : '🔖 Other';
  const compHtml = compItems ? `<ul class="idea-bullet-list">${compItems}</ul>` : '';
  const innerHtml = `
    <div id="idea-msg-${idea.id}" class="idea-card-chat" style="max-width:520px">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px">
        <div style="flex:1">
          <div style="display:flex;align-items:flex-start;justify-content:space-between;gap:8px;flex-wrap:wrap">
            <div style="font-weight:900;font-size:18px;line-height:1.2">${idea.title}</div>
            <div style="background:rgba(255,255,255,0.14);padding:4px 10px;border-radius:999px;font-size:12px;color:#fff;white-space:nowrap">${categoryLabel}</div>
          </div>
          <div style="margin-top:8px;font-size:14px;line-height:1.5;color:rgba(255,255,255,0.95)">${idea.description}</div>
          ${compHtml}
        </div>
      </div>
      <div style="display:flex;gap:8px;justify-content:flex-end;margin-top:12px">
        <button class="quick-btn" onclick="voteIdea(${idea.id},1)" style="background:rgba(255,255,255,0.08);color:white">👍 <span id="idea-likes-${idea.id}">0</span></button>
        <button class="quick-btn" onclick="voteIdea(${idea.id},-1)" style="background:rgba(255,255,255,0.04);color:white">👎 <span id="idea-unlikes-${idea.id}">0</span></button>
      </div>
    </div>
  `;
  savePHChatMessage('user',`[${idea.topic||activePHChat||'Recommendation Builds'}] New thread: ${idea.title}\n${idea.description}`);
    savePHChatMessage('user',`[${idea.topic||activePHChat||'Recommendation Builds'}] New thread: ${idea.title}\n${idea.description}`);
  // insert as a right-aligned user message with blue bubble
  const wrap = document.getElementById('PHchat-messages');
  if(wrap){
    const msgEl = document.createElement('div');
    msgEl.className = 'msg user';
    const bubble = document.createElement('div');
    bubble.className = 'msg-bubble';
    bubble.style.background = 'linear-gradient(90deg,#60a5fa,#0369a1)';
    bubble.style.color = 'white';
    bubble.style.borderRadius = '14px';
    bubble.style.padding = '12px 16px';
    bubble.innerHTML = innerHtml;
    msgEl.appendChild(bubble);
    wrap.appendChild(msgEl);
    wrap.scrollTop = wrap.scrollHeight;
  } else {
    addMsg('PHchat','user',innerHtml);
  }
  // ensure votes entry exists
  const votes=JSON.parse(localStorage.getItem('idea_votes')||'{}');
  if(!votes[idea.id]) votes[idea.id]={likes:0,unlikes:0};
  localStorage.setItem('idea_votes',JSON.stringify(votes));
  // update displays after DOM insertion
  setTimeout(()=>updateIdeaVotesDisplay(idea.id),50);
}

function voteIdea(id,delta){
  const votes=JSON.parse(localStorage.getItem('idea_votes')||'{}');
  if(!votes[id]) votes[id]={likes:0,unlikes:0};
  if(delta===1) votes[id].likes = (votes[id].likes||0) + 1;
  else votes[id].unlikes = (votes[id].unlikes||0) + 1;
  localStorage.setItem('idea_votes',JSON.stringify(votes));
  updateIdeaVotesDisplay(id);
}

function updateIdeaVotesDisplay(id){
  const votes=JSON.parse(localStorage.getItem('idea_votes')||'{}');
  const v = votes[id] || {likes:0,unlikes:0};
  const elLike=document.getElementById('idea-likes-'+id);
  const elUn=document.getElementById('idea-unlikes-'+id);
  if(elLike) elLike.textContent = v.likes;
  if(elUn) elUn.textContent = v.unlikes;
}
function renderBuildIdeas(){
  const ideas=JSON.parse(localStorage.getItem('build_ideas')||'[]');
  const container=document.getElementById('build-ideas-list');
  if(!container) return;
  if(ideas.length===0){
    container.innerHTML=`<div class="idea-card"><div class="idea-card-title">No ideas yet</div><div class="idea-card-desc">Share your build idea — budget, use case, and goals — and it will appear here for easy reference.</div></div>`;
    return;
  }
  container.innerHTML=ideas.map(idea=>{
    const comps=idea.components||{};
    const compHtml=['cpu','motherboard','ram','gpu','storage','psu'].map(k=>{
      if(!comps[k]) return '';
      return `<div style="font-size:13px;color:var(--muted);margin-top:6px"><strong>${k.toUpperCase()}:</strong> ${escapeHtml(comps[k])}</div>`;
    }).join('');
    const catLabel = idea.category==='gaming'? '🎮 Gaming' : idea.category==='office'? '💼 Office' : idea.category==='workstation'? '🖥️ Workstation' : '🔖 Other';
    return `
    <div class="idea-card">
      <div style="display:flex;justify-content:space-between;align-items:flex-start;gap:12px">
        <div>
          <div style="display:flex;align-items:center;gap:10px">
            <div class="idea-card-title">${escapeHtml(idea.title)}</div>
            <div class="idea-badge">${escapeHtml(catLabel)}</div>
          </div>
          <div class="idea-card-meta">${escapeHtml(idea.timestamp)}</div>
        </div>
        <div style="display:flex;gap:8px;align-items:center">
          <button class="quick-btn" style="background:#ef4444;color:white;padding:8px 10px;font-size:12px;border-radius:8px" onclick="deleteBuildIdea(${idea.id})">Delete</button>
        </div>
      </div>
      <div class="idea-card-desc">${escapeHtml(idea.description).replace(/\n/g,'<br>')}</div>
      ${compHtml}
    </div>`;
  }).join('');
}


function deleteBuildIdea(id){
  if(!confirm('Delete this build idea?')) return;
  const ideas=JSON.parse(localStorage.getItem('build_ideas')||'[]');
  const filtered=ideas.filter(i=>i.id!==id);
  localStorage.setItem('build_ideas',JSON.stringify(filtered));
  renderBuildIdeas();
}

function escapeHtml(s){
  return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
}
async function loadBuild(name){
  let payload;
  try{
    payload=await getSavedBuildsMap();
  }catch(err){
    alert('Failed to load builds: '+err.message);
    return;
  }
  const build=payload.builds[name];
  if(!build){alert('Build not found');return}
  components.forEach(c=>c.status='empty');
  build.components.forEach(({type,value})=>{
    const comp=components.find(c=>c.type===type);
    if(comp){comp.value=value;comp.status='compatible';comp.detail=value}
  });
  renderComponents();
  alert(`✅ "${name}" loaded!`);
}
async function deleteBuild(name){
  if(!confirm('Delete this build?'))return;
  const email=getSavedBuildsUserEmail();
  if(email){
    try{
      await savedBuildsRequest('delete',{user_email:email,build_name:name});
    }catch(err){
      alert('Failed to delete build: '+err.message);
      return;
    }
  }else{
    const builds_local=JSON.parse(localStorage.getItem('saved_builds')||'{}');
    delete builds_local[name];
    localStorage.setItem('saved_builds',JSON.stringify(builds_local));
  }
  updateSavedBuildsList();
  openLoadBuildsModal();
}

// ——— AI BUILD RECOMMENDATIONS ———
function getBuildAIResponse(msg){
  const m=msg.toLowerCase();
  let budget=0,purpose='gaming',performance='balanced';
  
  // Extract budget
  if(m.match(/(\d+)k|(\d+),?000/i)){const match=m.match(/(\d+)k|(\d+),?000/i);budget=parseInt(match[1]||match[2])*1000}
  
  // Detect purpose
  if(m.includes('gaming')||m.includes('game')||m.includes('fps')){purpose='gaming'}
  else if(m.includes('work')||m.includes('office')||m.includes('productivity')){purpose='office'}
  else if(m.includes('stream')||m.includes('content')||m.includes('edit')||m.includes('video')||m.includes('3d')){purpose='workstation'}
  
  if(m.includes('high')||m.includes('4k')||m.includes('ultra')){performance='high'}
  else if(m.includes('budget')||m.includes('low')||m.includes('compact')){performance='budget'}
  
  // Budget-based recommendation
  let rec={};
  if(budget===0||!m.match(/\d+k/i)){
    return `🤖 **AI Build Recommendation**\n\nPlease mention your budget! Examples:\n• "₱35,000 gaming PC"\n• "₱80,000 streaming build"\n• "₱120,000 workstation"\n\nAlso helpful: What's the main purpose?\n• Gaming (1080p, 1440p, 4K?)\n• Work/Office\n• Content creation/Video editing\n• Streaming\n• Mini-ITX (compact)\n• Ultra-premium\n\nAsk me something like: "Build a gaming PC for ₱50K, want 1440p high settings"`;
  }
  
  // Gaming PC recommendations
  if(purpose==='gaming'){
    if(budget<=40000){
      rec={name:'Entry Gaming',price:'₱35K',desc:'1080p 60fps gaming',parts:[
        {cat:'CPU',name:'Ryzen 5 5600',price:'₱9,200'},{cat:'MB',name:'B550M DS3H',price:'₱7,500'},
        {cat:'RAM',name:'16GB DDR4 3200',price:'₱3,400'},{cat:'GPU',name:'GTX 1660 Super',price:'₱18,500'},
        {cat:'PSU',name:'550W Bronze',price:'₱3,200'},{cat:'Storage',name:'500GB NVMe',price:'₱2,800'}
      ]};
    }else if(budget<=70000){
      rec={name:'Mid Gaming',price:'₱65K',desc:'1440p 60fps gaming',parts:[
        {cat:'CPU',name:'Ryzen 5 7600X',price:'₱15,500'},{cat:'MB',name:'B650 Carbon',price:'₱14,000'},
        {cat:'RAM',name:'32GB DDR5 5200',price:'₱8,500'},{cat:'GPU',name:'RTX 4060 Ti',price:'₱32,000'},
        {cat:'PSU',name:'750W Gold',price:'₱5,500'},{cat:'Storage',name:'1TB NVMe',price:'₱5,800'}
      ]};
    }else{
      rec={name:'High-End Gaming',price:'₱100K+',desc:'4K 60fps or 1440p 144fps gaming',parts:[
        {cat:'CPU',name:'Ryzen 7 7700X',price:'₱21,000'},{cat:'MB',name:'B650E Master',price:'₱16,500'},
        {cat:'RAM',name:'64GB DDR5 6000',price:'₱22,000'},{cat:'GPU',name:'RTX 4070',price:'₱42,000'},
        {cat:'PSU',name:'850W Gold',price:'₱8,500'},{cat:'Storage',name:'2TB NVMe',price:'₱9,500'}
      ]};
    }
  }else if(purpose==='office'){
    if(budget<=30000){
      rec={name:'Office Essentials',price:'₱20K',desc:'Office work, web browsing',parts:[
        {cat:'CPU',name:'Ryzen 3 4100',price:'₱5,200'},{cat:'MB',name:'A520M',price:'₱3,800'},
        {cat:'RAM',name:'8GB DDR4',price:'₱1,500'},{cat:'GPU',name:'iGPU',price:'—'},
        {cat:'PSU',name:'450W',price:'₱1,800'},{cat:'Storage',name:'500GB SSD',price:'₱2,200'}
      ]};
    }else{
      rec={name:'Power Office',price:'₱50K',desc:'Office + light content creation',parts:[
        {cat:'CPU',name:'Ryzen 5 5600',price:'₱9,200'},{cat:'MB',name:'B550',price:'₱8,500'},
        {cat:'RAM',name:'32GB DDR4 3600',price:'₱5,800'},{cat:'GPU',name:'iGPU',price:'—'},
        {cat:'PSU',name:'550W',price:'₱3,200'},{cat:'Storage',name:'1TB NVMe',price:'₱5,800'}
      ]};
    }
  }else if(purpose==='workstation'){
    rec={name:'Creative Workstation',price:'₱120K',desc:'Video/3D/Photo editing',parts:[
      {cat:'CPU',name:'Ryzen 9 7900X',price:'₱32,000'},{cat:'MB',name:'X670E Taichi',price:'₱28,000'},
      {cat:'RAM',name:'64GB DDR5 6000',price:'₱22,000'},{cat:'GPU',name:'RTX 4070 Ti',price:'₱65,000'},
      {cat:'PSU',name:'1000W Platinum',price:'₱9,000'},{cat:'Storage',name:'2TB NVMe',price:'₱9,500'}
    ]};
  }
  
  if(!rec.name){rec={name:'Custom Gaming Build',price:budget?`≈₱${budget.toLocaleString()}`:' ?',desc:'Based on your budget',parts:[]}}
  
  let response=`🤖 **AI Recommendation: ${rec.name}**\n\n`;
  response+=`💰 Budget: ${rec.price}\n📊 Use Case: ${rec.desc}\n\n`;
  response+=`**Recommended Components:**\n`;
  rec.parts.forEach(p=>{response+=`• **${p.cat}:** ${p.name} ${p.price?'—'+p.price:''}\n`});
  response+=`\n✅ This build is optimized for your budget and needs!\n\n💡 **Next Step:** Load this build in the Compatibility Checker to verify compatibility and ask follow-up questions.`;
  return response;
}

function getTroubleResponse(msg){
  const m=msg.toLowerCase();
  if(m.includes('won\'t turn')||m.includes('wont turn')||m.includes('no power')||m.includes('not turning')){
    return `🔴 **PC Won't Turn On — Fix Guide:**\n\n**Step 1: Check Power**\n• Ensure PSU switch is ON (rear of PC)\n• Check power cable is firmly plugged in\n• Test the wall outlet with another device\n\n**Step 2: Check Connections**\n• Reseat the 24-pin ATX motherboard connector\n• Reseat the 8-pin CPU power connector\n• Check front panel power button cable on motherboard\n\n**Step 3: Minimal Boot Test**\n• Remove all components except 1 RAM stick\n• Remove GPU, use onboard video if available\n• Try to boot — if it works, add parts one by one\n\n**Step 4: PSU Test**\n• Use a PSU tester, or short the green wire to ground on the 24-pin connector\n\n💡 Most common cause: loose 24-pin or 8-pin connector`;
  }
  if(m.includes('no display')||m.includes('black screen')||m.includes('no signal')||m.includes('blank')){
    return `🖥️ **No Display / Black Screen — Fix Guide:**\n\n**Step 1: Check Cables**\n• Ensure HDMI/DisplayPort cable is firmly connected\n• Connect cable to GPU (not motherboard) if GPU is installed\n• Try a different cable or port\n\n**Step 2: Check RAM**\n• Reseat RAM sticks — most common cause!\n• Try with only 1 stick in slot A2 (or as per manual)\n• Try each stick individually\n\n**Step 3: Check GPU**\n• Reseat the GPU in PCIe slot\n• Ensure GPU power connectors are plugged in\n• Test with another monitor or TV\n\n**Step 4: BIOS**\n• Clear CMOS (remove battery 30 sec or use CLRTC jumper)\n• Check if your CPU has integrated graphics — unplug GPU and test\n\n💡 80% of black screen issues are caused by unseated RAM!`;
  }
  if(m.includes('restart')||m.includes('restarting')||m.includes('reboots')||m.includes('random shutdown')){
    return `🔄 **Random Restarts — Fix Guide:**\n\n**Most Likely Causes:**\n\n**1. Overheating**\n• Check CPU temps using HWMonitor (download free)\n• CPU over 90°C at load = thermal issue\n• Reapply thermal paste if CPU cooler is old\n\n**2. PSU Failing**\n• Insufficient wattage for your components\n• Failing PSU = instability under load\n• Test with a known-good PSU\n\n**3. RAM Issues**\n• Run Windows Memory Diagnostic (search in Start)\n• Or boot MemTest86 from USB — let it run overnight\n\n**4. Windows / Driver Issue**\n• Update GPU drivers (DDU clean uninstall first)\n• Check Event Viewer > Windows Logs > System for errors\n\n5. **Dust buildup** — clean fans and heatsinks with compressed air`;
  }
  if(m.includes('overheat')||m.includes('hot')||m.includes('temperature')||m.includes('shutting down')){
    return `🌡️ **Overheating — Diagnosis & Fix:**\n\n**Check Temps First:**\n• Download HWMonitor or HWiNFO64 (free)\n• Normal CPU idle: 30–50°C\n• Normal CPU load: 65–80°C\n• Danger zone: above 90°C\n\n**Common Fixes:**\n\n**1. Thermal Paste**\n• Remove CPU cooler, clean old paste with isopropyl alcohol\n• Apply small pea-sized amount of new paste\n• Remount cooler firmly\n\n**2. Airflow**\n• Ensure case has front intake + rear exhaust fans\n• Check for cable blockage inside case\n• Minimum: 2 fans for budget builds\n\n**3. CPU Cooler**\n• Stock AMD cooler is okay for non-X CPUs\n• Upgrade to Hyper 212 (₱1,800) for better temps\n\n**4. Case**\n• Avoid placing PC in enclosed spaces\n• Keep 15cm+ space behind PC for exhaust`;
  }
  if(m.includes('bsod')||m.includes('blue screen')||m.includes('stop code')){
    return `💙 **Blue Screen of Death (BSOD) — Fix Guide:**\n\n**Step 1: Note the Stop Code**\n• Read the error code on the blue screen\n• Common codes: MEMORY_MANAGEMENT, IRQL_NOT_LESS_OR_EQUAL, CRITICAL_PROCESS_DIED\n\n**Step 2: Update Drivers**\n• GPU driver issues cause most BSODs\n• Use DDU (Display Driver Uninstaller) to clean uninstall GPU drivers\n• Reinstall latest drivers from NVIDIA/AMD website\n\n**Step 3: Check RAM**\n• Run Windows Memory Diagnostic\n• Run MemTest86 for thorough test\n\n**Step 4: Check Storage**\n• Run CrystalDiskInfo — check SSD/HDD health\n• Run CHKDSK in Command Prompt as admin\n\n**Step 5: Windows Repair**\n• Run: sfc /scannow in admin Command Prompt\n• Run: DISM /Online /Cleanup-Image /RestoreHealth\n\n💡 Check the Windows Event Viewer for the exact error that triggered the crash.`;
  }
  if(m.includes('slow')||m.includes('lag')||m.includes('sluggish')||m.includes('freeze')){
    return `🐌 **PC Running Slow — Fix Plan:**\n\n**Step 1: Check Resource Usage**\n• Open Task Manager (Ctrl+Shift+Esc)\n• Check CPU, RAM, Disk usage\n• If disk is at 100% — this is the #1 cause\n\n**Step 2: Disk Issues**\n• If using HDD — replace with SSD (huge improvement)\n• Check disk health with CrystalDiskInfo\n• Run disk cleanup and defrag (HDD only, not SSD)\n\n**Step 3: RAM**\n• 8GB RAM is minimum for modern use\n• Close unused browser tabs and background apps\n\n**Step 4: Startup Programs**\n• Open Task Manager > Startup tab\n• Disable unnecessary startup programs\n\n**Step 5: Malware Scan**\n• Run Windows Defender full scan\n• Or use Malwarebytes (free)\n\n**Step 6: Thermal Throttling**\n• Check CPU temps — hot CPU slows itself down\n• Clean dust, reapply thermal paste`;
  }
  return `🛠️ **Troubleshooting Bot Ready**\n\nTell me your PC problem and I will help fix it step-by-step.\n• When does the problem occur?\n• Any error messages or codes?\n• Did it happen after a change (new part, update)?\n\nOr try one of the common issues:\n• PC won't turn on\n• No display / black screen\n• Random restarts\n• Overheating\n• Blue screen (BSOD)\n• PC running slow`;
}

// ——— LOGIN ———
let isSignup=false;
let authAttempts=0;
let authLockoutUntil=0;
const AUTH_USERS_KEY='corecraft_users';
const AUTH_SESSION_KEY='corecraft_session';
const MAX_ATTEMPTS=5;
const LOCKOUT_MS=10*60*1000;
const GOOGLE_CLIENT_ID=window.CORECRAFT_GOOGLE_CLIENT_ID||'';
let googleSignInInitialized=false;

function isGoogleClientIdConfigured(){
  return /^[A-Za-z0-9._-]+\.apps\.googleusercontent\.com$/.test(GOOGLE_CLIENT_ID)&&
    GOOGLE_CLIENT_ID.indexOf('PASTE_')!==0&&GOOGLE_CLIENT_ID.indexOf('YOUR_')!==0;
}

function mountGoogleSignInButton(){
  const slot=document.querySelector('.google-signin-slot');
  if(!slot||googleSignInInitialized)return;
  if(!isGoogleClientIdConfigured()){
    slot.textContent='Set CORECRAFT_GOOGLE_CLIENT_ID in config.php';
    slot.classList.add('google-signin-unconfigured');
    return;
  }
  if(!window.google||!google.accounts||!google.accounts.id)return;
  google.accounts.id.initialize({client_id:GOOGLE_CLIENT_ID,callback:handleGoogleCredential,auto_select:false});
  google.accounts.id.renderButton(slot,{type:'standard',theme:'outline',size:'large',text:'continue_with',shape:'rectangular',width:280,logo_alignment:'left'});
  googleSignInInitialized=true;
}

function prepareGoogleSignIn(){
  const original=document.querySelector('.social-grid .social-btn');
  if(original&&!document.getElementById('google-signin-button')){
    const mount=document.createElement('div');
    mount.id='google-signin-button';
    mount.className='google-signin-slot';
    original.parentNode.replaceChild(mount,original);
  }
  mountGoogleSignInButton();
}

window.addEventListener('load',prepareGoogleSignIn);
document.addEventListener('DOMContentLoaded',prepareGoogleSignIn);
let googleButtonRetryCount=0;
const googleButtonRetry=setInterval(function(){
  googleButtonRetryCount++;
  if(document.getElementById('google-signin-button')&&window.google&&google.accounts&&google.accounts.id){
    mountGoogleSignInButton();
    clearInterval(googleButtonRetry);
  }else if(googleButtonRetryCount>=40){
    clearInterval(googleButtonRetry);
  }
},250);

function switchTab(tab){
  isSignup=(tab==='signup');
  document.getElementById('tab-login').className='login-tab '+(!isSignup?'active-tab':'');
  document.getElementById('tab-signup').className='login-tab '+(isSignup?'active-signup':'');
  document.getElementById('field-name').className=isSignup?'form-group':'hidden-field form-group';
  document.getElementById('field-confirm').className=isSignup?'form-group':'hidden-field form-group';
  document.getElementById('remember-row').style.display=isSignup?'none':'flex';
  const btn=document.getElementById('submit-btn');
  btn.textContent=isSignup?'Create Account':'Login';
  btn.className='submit-btn '+(isSignup?'submit-signup':'submit-login');
  document.getElementById('login-tagline').textContent=isSignup?'Create your account to start building':'Welcome back, PC Builder!';
  showAuthStatus('');
}
function togglePw(){
  const inp=document.getElementById('pw-input');
  inp.type=inp.type==='password'?'text':'password';
  document.getElementById('pw-eye').textContent=inp.type==='password'?'👁':'🙈';
}
function showAuthStatus(message,type='error'){
  const status=document.getElementById('login-status');
  if(!status)return;
  status.textContent=message||'';
  status.className='login-status '+(message?type:'');
}
function clearAuthErrors(){
  document.querySelectorAll('#page-login .form-input').forEach(el=>{
    el.classList.remove('invalid');
  });
  showAuthStatus('');
}
function validateEmail(email){
  return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}
function validatePassword(password){
  return /^(?=.*[a-z])(?=.*[A-Z])(?=.*\d)(?=.*[^A-Za-z0-9]).{8,}$/.test(password);
}
function getStoredUsers(){
  try{const raw=localStorage.getItem(AUTH_USERS_KEY);return raw?JSON.parse(raw):[];}catch{return [];}
}
function saveStoredUsers(users){
  localStorage.setItem(AUTH_USERS_KEY,JSON.stringify(users));
}
function generateSalt(length=16){
  const arr=new Uint8Array(length);
  crypto.getRandomValues(arr);
  return Array.from(arr,b=>b.toString(16).padStart(2,'0')).join('');
}
async function hashPassword(password,salt){
  const encoder=new TextEncoder();
  const baseKey=await crypto.subtle.importKey('raw',encoder.encode(password+':'+salt),'PBKDF2',false,['deriveBits']);
  const derived=await crypto.subtle.deriveBits({name:'PBKDF2',hash:'SHA-256',salt:encoder.encode(salt),iterations:220000},baseKey,256);
  return Array.from(new Uint8Array(derived),b=>b.toString(16).padStart(2,'0')).join('');
}
function setActiveUser(user, rememberMe=false){
  const firstName=user.name.split(' ')[0];
  const initials=user.name.split(' ').map(w=>w[0]).join('').toUpperCase().slice(0,2);
  window._user={name:user.name,email:user.email,authToken:user.auth_token||'',initials,firstName};
  document.getElementById('dropdown-name').textContent=user.name;
  document.getElementById('dropdown-email').textContent=user.email;
  document.getElementById('user-displayname').textContent=firstName;
  document.getElementById('user-avatar').textContent=initials;
  document.getElementById('nav-login-btn').style.display='none';
  document.getElementById('user-dropdown').classList.add('visible');
  syncProfilePage();
  updateMobileMenu(true,firstName);
  const storage=rememberMe?localStorage:sessionStorage;
  storage.setItem(AUTH_SESSION_KEY,JSON.stringify({name:user.name,email:user.email,auth_token:user.auth_token||'',remember:rememberMe}));
  isLoggedIn=true;
  const communityPage=document.getElementById('page-PHchat');
  if(PHchatInit&&communityPage&&communityPage.classList.contains('active')){
    loadPHChatCommunities();
  }
}
function clearActiveUser(){
  localStorage.removeItem(AUTH_SESSION_KEY);
  sessionStorage.removeItem(AUTH_SESSION_KEY);
  window._user=null;
}
async function handleLogin(){
  clearAuthErrors();
  const now=Date.now();
  if(now<authLockoutUntil){
    showAuthStatus(`Too many attempts. Please wait ${Math.ceil((authLockoutUntil-now)/60000)} minute(s).`,'error');
    return;
  }
  const emailInput=document.getElementById('email-input');
  const passwordInput=document.getElementById('pw-input');
  const nameInput=document.getElementById('name-input');
  const confirmInput=document.getElementById('confirm-input');
  const rememberInput=document.getElementById('remember-input');
  const rawEmail=(emailInput&&emailInput.value.trim())?emailInput.value.trim():'';
  const rawPassword=(passwordInput&&passwordInput.value)?passwordInput.value:'';
  const rawName=(isSignup&&nameInput&&nameInput.value.trim())?nameInput.value.trim():'';
  const rawConfirm=(isSignup&&confirmInput&&confirmInput.value)?confirmInput.value:'';
  const normalizedEmail=rawEmail.toLowerCase();
  if(!normalizedEmail||!validateEmail(normalizedEmail)){
    emailInput?.classList.add('invalid');showAuthStatus('Please enter a valid email address.','error');return;
  }
  if(!rawPassword){
    passwordInput?.classList.add('invalid');showAuthStatus('Please enter your password.','error');return;
  }
  if(isSignup){
    if(!rawName){nameInput?.classList.add('invalid');showAuthStatus('Please enter your full name.','error');return;}
    if(!validatePassword(rawPassword)){passwordInput?.classList.add('invalid');showAuthStatus('Use at least 8 characters, including uppercase, lowercase, a number, and a symbol.','error');return;}
    if(rawPassword!==rawConfirm){confirmInput?.classList.add('invalid');showAuthStatus('Passwords do not match.','error');return;}
    const result=await authRequest('register',{name:rawName,email:normalizedEmail,password:rawPassword});
    if(!result.success){showAuthStatus(result.error||'Registration failed.','error');return;}
    setActiveUser(result.user,rememberInput?.checked);
    showAuthStatus('Account created securely. Welcome aboard!','success');
    const dest=pendingPage||'home';pendingPage=null;showPage(dest);
    return;
  }
  const result=await authRequest('login',{email:normalizedEmail,password:rawPassword});
  if(!result.success){
    authAttempts+=1;if(authAttempts>=MAX_ATTEMPTS){authLockoutUntil=Date.now()+LOCKOUT_MS;}
    showAuthStatus(result.error||'Invalid email or password.','error');return;
  }
  authAttempts=0;authLockoutUntil=0;setActiveUser(result.user,rememberInput?.checked);
  showAuthStatus('Signed in securely.','success');
  const dest=pendingPage||'home';pendingPage=null;showPage(dest);
}
function handleGoogleLogin(){
  if(!isGoogleClientIdConfigured()){
    showAuthStatus('Google sign-in is not configured. Add your Web Client ID to CORECRAFT_GOOGLE_CLIENT_ID in config.php.','error');
    return;
  }
  if(!window.google||!google.accounts||!google.accounts.id){
    showAuthStatus('Google sign-in is still loading. Please try again.','error');
    return;
  }
  mountGoogleSignInButton();
  showAuthStatus('Use the Google button above to continue.','error');
}
async function handleGoogleCredential(response){
  clearAuthErrors();
  const result=await authRequest('google',{credential:response.credential});
  if(!result.success){showAuthStatus(result.error||'Google verification failed.','error');return;}
  setActiveUser(result.user,true);
  showAuthStatus('Google account verified. Signed in securely.','success');
  const dest=pendingPage||'home';pendingPage=null;showPage(dest);
}
async function authRequest(action,payload){
  try{
    const response=await fetch('users_api.php',{method:'POST',headers:{'Content-Type':'application/json'},body:JSON.stringify(Object.assign({action},payload))});
    const data=await response.json();
    return data;
  }catch(error){
    return {success:false,error:'Cannot connect to the account service. Start Apache and MySQL, then try again.'};
  }
}
function syncProfilePage(){
  const u=window._user||{};
  if(!u.name)return;
  const set=(id,v)=>{const el=document.getElementById(id);if(el)el.textContent=v;};
  set('profile-avatar-big',u.initials);
  set('profile-name-big',u.name);
  set('profile-email-big',u.email);
  set('view-name',u.name);
  set('view-email',u.email);
}
function toggleEditMode(){
  const viewPanel=document.getElementById('info-view-panel');
  const editPanel=document.getElementById('info-edit-panel');
  const isEditing=editPanel.style.display!=='none';
  if(isEditing){
    viewPanel.style.display='';editPanel.style.display='none';
    document.getElementById('edit-profile-btn').textContent='✏️ Edit Profile';
  }else{
    const u=window._user||{};
    document.getElementById('edit-name').value=u.name||'';
    document.getElementById('edit-email').value=u.email||'';
    viewPanel.style.display='none';editPanel.style.display='';
    document.getElementById('edit-profile-btn').textContent='✕ Cancel Edit';
  }
}
function saveProfile(){
  const newName=document.getElementById('edit-name').value.trim()||window._user.name;
  const newEmail=document.getElementById('edit-email').value.trim()||window._user.email;
  const initials=newName.split(' ').map(w=>w[0]).join('').toUpperCase().slice(0,2);
  const firstName=newName.split(' ')[0];
  window._user={name:newName,email:newEmail,authToken:window._user.authToken||'',initials,firstName};
  // Update nav
  document.getElementById('dropdown-name').textContent=newName;
  document.getElementById('dropdown-email').textContent=newEmail;
  document.getElementById('user-displayname').textContent=firstName;
  document.getElementById('user-avatar').textContent=initials;
  syncProfilePage();
  toggleEditMode();
}
function handleLogout(){
  isLoggedIn=false;
  clearActiveUser();
  closeDropdown();
  document.getElementById('nav-login-btn').style.display='';
  document.getElementById('user-dropdown').classList.remove('visible');
  updateMobileMenu(false,'');
  showPage('home');
}
function toggleDropdown(){
  document.getElementById('dropdown-menu').classList.toggle('hidden');
}
function closeDropdown(){
  document.getElementById('dropdown-menu').classList.add('hidden');
}
function updateMobileMenu(loggedIn,firstName){
  const actions=document.querySelector('.mobile-menu-actions');
  if(!actions)return;
  if(loggedIn){
    const initials=(firstName||'').split(' ').map(w=>w[0]||'').join('').toUpperCase().slice(0,2);
    actions.innerHTML=`
      <div style="padding:12px 0;border-top:1px solid var(--border);margin-top:8px">
        <button class="mobile-signed-btn" onclick="showPage('profile');closeMenu()">
          <div class="mobile-signed-avatar" style="background:linear-gradient(135deg,var(--purple),var(--cyan))">${initials}</div>
          <div style="text-align:left"><div style="font-weight:700;color:var(--text)">${firstName}</div><div style="font-size:12px;color:var(--muted)">Signed in</div></div>
          <div style="margin-left:auto;color:var(--muted);font-weight:700">▾</div>
        </button>
        <button class="btn-login" style="justify-content:center;border:1px solid var(--border);background:#ef4444;color:white;width:100%;margin-top:10px" onclick="handleLogout();closeMenu()">🚪 Log Out</button>
      </div>`;
  }else{
    actions.innerHTML=`
      <button class="btn-login" onclick="showPage('login');closeMenu()">👤 Login</button>
      <button class="btn-start" onclick="showPage('compatibility');closeMenu()">⚡ Start Building</button>`;
  }
}
// Close dropdown when clicking outside
document.addEventListener('click',function(e){
  const dd=document.getElementById('user-dropdown');
  if(dd&&!dd.contains(e.target)){closeDropdown();}
});

function restoreSession(){
  const stored=sessionStorage.getItem(AUTH_SESSION_KEY)||localStorage.getItem(AUTH_SESSION_KEY);
  if(!stored)return false;
  try{
    const parsed=JSON.parse(stored);
    if(parsed?.email){
      setActiveUser({name:parsed.name||'Guest User',email:parsed.email,auth_token:parsed.auth_token||''});
      return true;
    }
  }catch{}
  return false;
}
restoreSession();
