@verbatim
<script>document.documentElement.classList.add('splash-activa')</script>
<style>
html.splash-activa,html.splash-activa body{overflow:hidden}
#splash{position:fixed;inset:0;z-index:9999;display:grid;place-items:center;overflow:hidden;background:linear-gradient(to bottom,#D8E4F2,#EAF0F8)}
.sp-stage{position:relative;width:min(80vw,760px);aspect-ratio:1001/284}
.sp-part{position:absolute;inset:0;width:100%;height:100%;display:block;overflow:visible}
.sp-pin,.sp-ya{opacity:0}
.sp-l{opacity:0;transform-box:fill-box;transform-origin:50% 100%}
.sp-shine{position:absolute;inset:0;opacity:0;pointer-events:none;
 -webkit-mask:url(/images/splash/logo.svg) center/100% 100% no-repeat;mask:url(/images/splash/logo.svg) center/100% 100% no-repeat;
 background:linear-gradient(100deg,transparent 36%,rgba(255,238,180,.85) 46%,#fff 50%,rgba(255,238,180,.85) 54%,transparent 64%) 0 0/300% 100% no-repeat;background-position:130% 0}
</style>
<div id="splash" aria-hidden="true"><div class="sp-stage">
<img class="sp-part sp-pin" src="/images/splash/pin.svg" alt="">
<img class="sp-part sp-ya" src="/images/splash/ya.svg" alt="">
<div class="sp-shine"></div></div></div>
<script>
(function(){
const $=s=>document.querySelector(s),$$=s=>[...document.querySelectorAll(s)],sp=$('#splash'),st=$('.sp-stage');
if(!sp)return;
/* TIEMPOS (ms): ajusta aquí. Total ≈ 8 s */
const T={intro:900,hold:900,expand:1400,letter:170,sweep:2000,zoom:2300,zoomMax:22};
const E='cubic-bezier(.65,0,.25,1)',sh=25;
let raf,fin=false;
const A=(el,k,o)=>el.animate(k,{fill:'both',easing:E,...o}).finished;
const soltarScroll=()=>document.documentElement.classList.remove('splash-activa');
function finish(){
 if(fin)return;fin=true;
 cancelAnimationFrame(raf);soltarScroll();sp.remove();
 document.dispatchEvent(new Event('splash:done'));
}
/* Las letras de FICIOS se cargan desde /images/splash/ficios.svg y se meten en la pantalla */
function cargarLetras(){
 return fetch('/images/splash/ficios.svg').then(r=>r.text()).then(t=>{
  const svg=document.importNode(new DOMParser().parseFromString(t,'image/svg+xml').documentElement,true);
  svg.setAttribute('class','sp-part sp-fic');
  svg.querySelectorAll('g').forEach(g=>g.setAttribute('class','sp-l'));
  st.insertBefore(svg,$('.sp-ya'));
 }).catch(()=>{});
}
function clearTime(){
 const bez=(x1,y1,x2,y2)=>{const B=(s,a,b)=>3*(1-s)*(1-s)*s*a+3*(1-s)*s*s*b+s**3;return t=>{let a=0,b=1;for(let i=0;i<30;i++){const m=(a+b)/2;B(m,x1,x2)<t?a=m:b=m}return B((a+b)/2,y1,y2)}};
 const f=bez(.65,0,.25,1),F=$('.sp-l').getBBox().x,pinR=860,shift=4004*sh/100,m=40;
 const need=Math.min(.98,Math.max(0,1-(F-m-pinR)/shift));
 let a=0,b=1;for(let i=0;i<30;i++){const t=(a+b)/2;f(t)<need?a=t:b=t}return b}
function play(){
 const t2=T.intro+T.hold,ls=t2+clearTime()*T.expand,sw=ls+T.letter*5+450;
 A($('.sp-pin'),[{transform:`translateX(${sh}%) scale(.92)`,opacity:0},{transform:`translateX(${sh}%) scale(1)`,opacity:1}],{duration:T.intro});
 A($('.sp-ya'),[{transform:`translateX(-${sh}%) scale(.92)`,opacity:0},{transform:`translateX(-${sh}%) scale(1)`,opacity:1}],{duration:T.intro,delay:150});
 A($('.sp-pin'),[{transform:`translateX(${sh}%)`},{transform:'translateX(0)'}],{duration:T.expand,delay:t2,composite:'replace'});
 A($('.sp-ya'),[{transform:`translateX(-${sh}%)`},{transform:'translateX(0)'}],{duration:T.expand,delay:t2});
 $$('.sp-l').forEach((g,i)=>A(g,[{opacity:0,transform:'translateY(25%) scale(.8)'},{opacity:1,transform:'none'}],{duration:700,delay:ls+i*T.letter,easing:'cubic-bezier(.2,.75,.25,1)'}));
 A($('.sp-shine'),[{opacity:1,backgroundPosition:'130% 0'},{opacity:1,backgroundPosition:'-30% 0'}],{duration:T.sweep,delay:sw,easing:'ease-in-out'});
 setTimeout(zoom,sw+T.sweep+150);
}
function zoom(){
 if(fin)return;
 soltarScroll();
 $('.sp-shine').getAnimations().forEach(a=>a.cancel());
 const r=st.getBoundingClientRect(),W=r.width,H=r.height,ox=.107,px=r.left+ox*W,py=r.top+H/2,t0=performance.now();
 Object.assign(st.style,{position:'fixed',margin:0,left:r.left+'px',top:r.top+'px',width:W+'px',height:H+'px'});
 const step=now=>{const t=Math.min(1,(now-t0)/T.zoom),s=Math.pow(T.zoomMax,Math.pow(t,1.7));
  Object.assign(st.style,{width:W*s+'px',height:H*s+'px',left:px-ox*W*s+'px',top:py-H*s/2+'px',opacity:t<.6?1:1-(t-.6)/.4});
  sp.style.opacity=t<.5?1:Math.max(0,1-(t-.5)/.5);
  t<1?raf=requestAnimationFrame(step):finish()};
 raf=requestAnimationFrame(step)}

/* Arranque */
if(matchMedia('(prefers-reduced-motion:reduce)').matches){finish();return}
setTimeout(finish,20000); // seguro: la splash nunca se queda tapando el sitio
const listo=i=>i.complete?Promise.resolve():new Promise(r=>{i.addEventListener('load',r);i.addEventListener('error',r)});
Promise.race([Promise.all([...$$('#splash img').map(listo),cargarLetras()]),new Promise(r=>setTimeout(r,2500))])
 .then(()=>setTimeout(()=>{try{play()}catch(e){finish()}},100));
})();
</script>
@endverbatim