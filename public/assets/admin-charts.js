(function(){
'use strict';
if(typeof window.Chart==='undefined')return;

const main=document.querySelector('.admin-main');
if(!main)return;

const path=(location.pathname||'/').replace(/\/+$/,'')||'/';
const params=new URLSearchParams(location.search);
const charts=[];

const text=e=>e?(e.textContent||'').replace(/\s+/g,' ').trim():'';
const clean=v=>String(v||'').replace(/\s+/g,' ').trim();
const number=v=>{const m=String(v||'').replace(/,/g,'').match(/-?\d+(?:\.\d+)?/);return m?Number(m[0]):null};
const usable=v=>{v=clean(v);return v&&v!=='—'&&!['none','n/a','not checked this session'].includes(v.toLowerCase())};

function tableHeaders(table){return [...table.querySelectorAll('thead th')].map(th=>text(th).toLowerCase())}
function tableRows(table){return table?[...table.querySelectorAll('tbody tr')].filter(row=>!row.querySelector('.empty,.empty-state')):[]}
function columnIndex(table,label){return tableHeaders(table).indexOf(label.toLowerCase())}
function cellValue(cell){
  if(!cell)return'';
  const badge=cell.querySelector('.badge');
  if(badge)return text(badge);
  const strong=cell.querySelector('strong');
  if(strong)return text(strong);
  const clone=cell.cloneNode(true);
  clone.querySelectorAll('small,.muted,form,button').forEach(node=>node.remove());
  return text(clone);
}
function columnValues(table,label){
  const index=columnIndex(table,label);
  if(index<0)return[];
  return tableRows(table).map(row=>cellValue(row.children[index])).filter(usable);
}
function countValues(values,limit=10){
  const map=new Map();
  values.forEach(value=>{
    value=clean(value);
    if(usable(value))map.set(value,(map.get(value)||0)+1);
  });
  return [...map].sort((a,b)=>b[1]-a[1]||a[0].localeCompare(b[0])).slice(0,limit).map(([label,value])=>({label,value}));
}
function numericPairs(table,labelColumn,valueColumn,limit=10){
  const labelIndex=columnIndex(table,labelColumn);
  const valueIndex=columnIndex(table,valueColumn);
  if(labelIndex<0||valueIndex<0)return[];
  return tableRows(table).map(row=>({
    label:cellValue(row.children[labelIndex]),
    value:number(cellValue(row.children[valueIndex]))
  })).filter(item=>usable(item.label)&&item.value!==null&&item.value>0)
    .sort((a,b)=>b.value-a.value)
    .slice(0,limit);
}
function sectionByTitle(title){
  return [...main.querySelectorAll('section,.panel,.admin-card')].find(section=>text(section.querySelector('h2'))===title)||null;
}
function tableBySection(title){
  const section=sectionByTitle(title);
  return section?section.querySelector('table'):null;
}
function statValue(label){
  const card=[...main.querySelectorAll('.stat-card')].find(card=>text(card.querySelector('span'))===label);
  return card?number(text(card.querySelector('strong'))):null;
}
function makeSpec(title,type,items,description,options={}){
  items=(items||[]).filter(item=>item&&usable(item.label)&&Number.isFinite(Number(item.value))&&Number(item.value)>=0);
  const positive=items.filter(item=>Number(item.value)>0);
  if(positive.length<1)return null;
  return {title,type,items,description,suffix:options.suffix||'',max:options.max||null};
}
function makeDistribution(title,items,description){
  items=(items||[]).filter(item=>Number(item.value)>0);
  if(items.length<2)return null;
  return makeSpec(title,'doughnut',items,description);
}
function mediaCounts(attribute,map){
  const cards=[...main.querySelectorAll('[data-media-card]')];
  if(!cards.length)return[];
  return countValues(cards.map(card=>{
    const key=clean(card.getAttribute(attribute));
    return map&&map[key]?map[key]:key;
  }),10);
}
function dailyTrend(){
  const section=sectionByTitle('Daily trend');
  if(!section)return[];
  return [...section.querySelectorAll('div[style*="grid-template-columns"]')].map(row=>({
    label:text(row.children[0]),
    value:number(text(row.children[row.children.length-1]))
  })).filter(item=>usable(item.label)&&item.value!==null);
}
function senderQueueState(){
  const queued=statValue('Queued')||0;
  const blocked=statValue('Blocked by cleaner')||0;
  const failed=statValue('Failed')||0;
  return [
    {label:'Queued',value:queued},
    {label:'Blocked',value:blocked},
    {label:'Failed',value:failed}
  ];
}
function senderCapacity(){
  const sent=statValue('Sent today');
  const remaining=statValue('Remaining today');
  if(sent===null||remaining===null)return[];
  return [{label:'Sent today',value:sent},{label:'Remaining capacity',value:remaining}];
}
function tableDistribution(table,column){
  return table?countValues(columnValues(table,column),10):[];
}
function contentPipeline(table){
  return tableDistribution(table,'Status');
}
function categoryCoverage(table){
  return tableDistribution(table,'Category');
}

function pageSpecs(){
  const specs=[];
  if(path==='/admin'&&params.has('help'))return specs;

  if(path==='/admin'){
    const table=tableBySection('Most-clicked products');
    const items=numericPairs(table,'Product','Clicks',10);
    if(items.length>=2)specs.push(makeSpec('Most-clicked products','bar',items,'Shows which products are actually attracting outbound Amazon clicks.'));
    return specs.filter(Boolean);
  }

  if(path==='/admin/products'){
    const table=main.querySelector('table');
    const categories=categoryCoverage(table);
    const sources=tableDistribution(table,'Source');
    if(categories.length>=2)specs.push(makeSpec('Products by category','bar',categories,'Where the catalog is concentrated, useful for spotting thin or overrepresented categories.'));
    if(sources.length>=2)specs.push(makeDistribution('Product source mix',sources,'How much of the catalog is manual versus Amazon/API-backed.'));
    return specs.filter(Boolean);
  }

  if(path==='/admin/guides'){
    const table=main.querySelector('table');
    const pipeline=contentPipeline(table);
    const depth=numericPairs(table,'Guide','Products',10);
    if(pipeline.length>=2)specs.push(makeDistribution('Buying-guide publishing pipeline',pipeline,'Published versus draft/in-progress guides.'));
    if(depth.length>=2)specs.push(makeSpec('Products per buying guide','bar',depth,'Shows which guides are deep enough and which may need more product coverage.'));
    return specs.filter(Boolean);
  }

  if(path==='/admin/comparisons'){
    const table=main.querySelector('table');
    const pipeline=contentPipeline(table);
    const categories=categoryCoverage(table);
    if(pipeline.length>=2)specs.push(makeDistribution('Comparison publishing pipeline',pipeline,'Published versus draft/in-progress comparisons.'));
    if(categories.length>=2)specs.push(makeSpec('Comparisons by category','bar',categories,'Shows which categories have comparison coverage and where gaps may exist.'));
    return specs.filter(Boolean);
  }

  if(path==='/admin/reviews'){
    const table=main.querySelector('table');
    const pipeline=contentPipeline(table);
    const scores=numericPairs(table,'Title','Score',10);
    if(pipeline.length>=2)specs.push(makeDistribution('Review publishing pipeline',pipeline,'Published versus draft/in-progress reviews.'));
    if(scores.length>=3)specs.push(makeSpec('Editorial review scores','bar',scores,'Quickly compares the scores assigned across recent reviews.',{max:10,suffix:'/10'}));
    return specs.filter(Boolean);
  }

  if(path==='/admin/blog'){
    const table=main.querySelector('table');
    const pipeline=contentPipeline(table);
    const categories=categoryCoverage(table);
    if(pipeline.length>=2)specs.push(makeDistribution('Blog publishing pipeline',pipeline,'Published versus draft/in-progress articles.'));
    if(categories.length>=2)specs.push(makeSpec('Articles by category','bar',categories,'Shows where editorial coverage is concentrated and where the blog may be thin.'));
    return specs.filter(Boolean);
  }

  if(path==='/admin/media'){
    const usage=mediaCounts('data-usage',{used:'In use',unused:'Unused'});
    const alt=mediaCounts('data-alt',{present:'Alt text present',missing:'Missing alt text'});
    const unused=usage.find(item=>item.label==='Unused')?.value||0;
    const missing=alt.find(item=>item.label==='Missing alt text')?.value||0;
    if(unused>0&&usage.length>=2)specs.push(makeDistribution('Media cleanup opportunity',usage,'Unused images can be reviewed for cleanup; in-use assets remain protected.'));
    if(missing>0&&alt.length>=2)specs.push(makeDistribution('Images needing alt text',alt,'Highlights the remaining accessibility work in the media library.'));
    return specs.filter(Boolean);
  }

  if(path==='/admin/sender'){
    const tab=params.get('tab')||'dashboard';
    if(tab==='dashboard'){
      const capacity=senderCapacity();
      const queue=senderQueueState();
      if(capacity.length===2)specs.push(makeSpec('Today’s Sender capacity','doughnut',capacity,'Successful sends used versus the remaining daily send allowance.'));
      if(queue.some(item=>item.value>0)&&queue.filter(item=>item.value>0).length>=2)specs.push(makeSpec('Queue requiring attention','bar',queue,'Queued, cleaner-blocked and failed messages that may need action.'));
      return specs.filter(Boolean);
    }
    if(tab==='queue'||tab==='history'){
      const table=main.querySelector('.admin-card table');
      if(table){
        const status=tableDistribution(table,'Status');
        const cleaner=tableDistribution(table,'Cleaner');
        if(status.length>=2)specs.push(makeDistribution(tab==='queue'?'Current queue status':'Email outcome mix',status,'Breakdown of email records by delivery state.'));
        if(cleaner.length>=2)specs.push(makeDistribution('List-cleaner outcomes',cleaner,'Shows how many addresses are passing, blocked or unresolved by validation.'));
      }
      return specs.filter(Boolean);
    }
    return specs;
  }

  if(path==='/admin/newsletter'){
    const total=statValue('Total subscribers')||0;
    const active=statValue('Active')||0;
    const unsubscribed=statValue('Unsubscribed')||0;
    const risky=statValue('Needs review')||0;
    const unknown=statValue('Unknown / not checked')||0;
    if(total>0&&active>0&&unsubscribed>0)specs.push(makeDistribution('Subscriber status',[{label:'Active',value:active},{label:'Unsubscribed',value:unsubscribed}],'Active audience versus people who have unsubscribed.'));
    const clean=Math.max(0,active-risky-unknown);
    const validation=[{label:'Clean',value:clean},{label:'Needs review',value:risky},{label:'Unknown / not checked',value:unknown}].filter(item=>item.value>0);
    if(validation.length>=2)specs.push(makeDistribution('Subscriber validation health',validation,'Shows how much of the active list is clean versus requiring validation attention.'));
    return specs.filter(Boolean);
  }

  if(path==='/admin/analytics'){
    const trend=dailyTrend();
    if(trend.filter(item=>item.value>0).length>=2)specs.push(makeSpec('Affiliate clicks over time','line',trend,'Outbound Amazon clicks by UTC day.'));
    const products=numericPairs(tableBySection('Top products'),'Product','Clicks',10);
    if(products.length>=2)specs.push(makeSpec('Top products by affiliate clicks','bar',products,'Products generating the most outbound Amazon traffic.'));
    const searches=numericPairs(tableBySection('Top site searches'),'Query','Searches',10);
    if(searches.length>=2)specs.push(makeSpec('Most common site searches','bar',searches,'What visitors are actively trying to find on the store.'));
    return specs.filter(Boolean).slice(0,3);
  }

  return specs;
}

function render(specs){
  specs=specs.filter(Boolean);
  if(!specs.length)return;

  const panel=document.createElement('section');
  panel.className='admin-insights';
  panel.innerHTML='<div class="admin-insights-head"><div><span class="admin-insights-eyebrow">Useful signals</span><h2>What deserves attention</h2><p>Only charts tied to a real CMS decision are shown here.</p></div><span class="admin-insights-hint">Hover for exact values</span></div><div class="admin-chart-grid"></div>';

  const grid=panel.querySelector('.admin-chart-grid');
  specs.forEach((spec,index)=>grid.appendChild(chartCard(spec,index)));

  const tabs=main.querySelector(':scope > .email-tabs');
  if(tabs)tabs.insertAdjacentElement('afterend',panel);
  else{
    const first=[...main.children].find(node=>!node.classList.contains('admin-top')&&!node.classList.contains('flash'));
    first?main.insertBefore(panel,first):main.appendChild(panel);
  }
}

function chartCard(spec,index){
  const card=document.createElement('article');
  card.className='admin-chart-card';

  const head=document.createElement('div');
  head.className='admin-chart-card-head';

  const copy=document.createElement('div');
  const title=document.createElement('h3');
  title.textContent=spec.title;
  const description=document.createElement('p');
  description.textContent=spec.description;
  copy.append(title,description);
  head.append(copy);

  const wrap=document.createElement('div');
  wrap.className='admin-chart-canvas-wrap';
  const canvas=document.createElement('canvas');
  canvas.id='admin-chart-'+index;
  canvas.setAttribute('role','img');
  canvas.setAttribute('aria-label',spec.title);
  wrap.append(canvas);

  card.append(head,wrap);
  draw(canvas,spec);
  return card;
}

function draw(canvas,spec){
  const labels=spec.items.map(item=>item.label);
  const values=spec.items.map(item=>Number(item.value));
  const type=spec.type;
  const horizontal=type==='bar'&&labels.length>5;
  const isLine=type==='line';
  const isDonut=type==='doughnut';

  const chart=new Chart(canvas.getContext('2d'),{
    type,
    data:{
      labels,
      datasets:[{
        label:spec.title,
        data:values,
        borderWidth:isLine?2:1,
        pointRadius:isLine?3:undefined,
        pointHoverRadius:isLine?5:undefined,
        tension:isLine?.28:undefined,
        fill:isLine?false:undefined
      }]
    },
    options:{
      responsive:true,
      maintainAspectRatio:false,
      indexAxis:horizontal?'y':'x',
      interaction:{mode:'nearest',intersect:true},
      plugins:{
        legend:{display:isDonut,position:'bottom'},
        tooltip:{callbacks:{label:context=>{
          const raw=context.parsed&&typeof context.parsed==='object'
            ?(context.parsed.y??context.parsed.x??context.raw)
            :context.parsed;
          return (context.label||spec.title)+': '+raw+spec.suffix;
        }}}
      },
      scales:isDonut?undefined:{
        x:horizontal
          ?{beginAtZero:true,suggestedMax:spec.max||undefined,grid:{display:false},ticks:{precision:0}}
          :{grid:{display:false}},
        y:horizontal
          ?{grid:{display:false}}
          :{beginAtZero:true,suggestedMax:spec.max||undefined,ticks:{precision:0}}
      }
    }
  });
  charts.push(chart);
}

window.addEventListener('DOMContentLoaded',()=>render(pageSpecs()),{once:true});
})();