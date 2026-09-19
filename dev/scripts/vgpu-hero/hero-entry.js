/* Procedural study: one merged, vertex-lit mesh per prop, no downloaded models.
 * Build with npm run build. Kept separate from the optional WebGPU page effects.
 */
import {
  WebGLRenderer, Scene, PerspectiveCamera, Color, Fog, Group, Mesh,
  MeshLambertMaterial, MeshBasicMaterial, ShaderMaterial, BufferAttribute,
  BoxGeometry, CylinderGeometry, SphereGeometry, TubeGeometry, PlaneGeometry,
  CatmullRomCurve3, Vector3, CanvasTexture, SRGBColorSpace,
  HemisphereLight, DirectionalLight,
} from 'three';
import { mergeGeometries } from 'three/addons/utils/BufferGeometryUtils.js';

const palette = { case: '#b5a58a', edge: '#655846', ink: '#242724', pcb: '#375e51', gold: '#c4924c', blue: '#718cab', paper: '#c3b591', red: '#875246', screen: '#122e29' };

export function mountVgpuHero(canvas) {
  const hero = canvas.closest('.ui-hero');
  const motion = matchMedia('(prefers-reduced-motion: reduce)');
  const compact = matchMedia('(max-width: 700px)');
  const toggle = hero.querySelector('[data-scene-toggle]');
  let renderer;
  try {
    renderer = new WebGLRenderer({ canvas, alpha: true, antialias: !compact.matches, powerPreference: 'low-power' });
  } catch { return false; }
  renderer.outputColorSpace = SRGBColorSpace;
  renderer.setClearColor(0x15120f, 0);
  const scene = new Scene();
  scene.fog = new Fog('#15120f', 23, 43);
  const camera = new PerspectiveCamera(36, 1, 0.1, 70);
  camera.position.z = 24;
  const world = new Group();
  scene.add(world, new HemisphereLight('#dfd8bb', '#283b47', 2.3));
  const key = new DirectionalLight('#ffd5a0', 2.5);
  key.position.set(-5, 8, 12);
  scene.add(key);
  const rim = new DirectionalLight('#7d9fbb', 1.8);
  rim.position.set(6, -1, 4);
  scene.add(rim);
  const solid = new MeshLambertMaterial({ vertexColors: true });
  const resources = new Set([solid]);
  const props = [];
  const movingParts = [];

  // Bake local transforms and face colors once; small details do not add draw calls.
  function builder() {
    const parts = [];
    function part(geometry, color, x, y, z, rx = 0, ry = 0, rz = 0) {
      geometry.rotateX(rx); geometry.rotateY(ry); geometry.rotateZ(rz);
      geometry.translate(x, y, z);
      const c = new Color(palette[color] || color);
      const colors = new Float32Array(geometry.attributes.position.count * 3);
      for (let i = 0; i < colors.length; i += 3) c.toArray(colors, i);
      geometry.setAttribute('color', new BufferAttribute(colors, 3));
      // UVs are unused on vertex-lit models; all geometry must share attributes.
      geometry.deleteAttribute('uv');
      parts.push(geometry);
    }
    return {
      box: (w,h,d,c,x=0,y=0,z=0,rx=0,ry=0,rz=0) => part(new BoxGeometry(w,h,d),c,x,y,z,rx,ry,rz),
      cylinder: (r,h,c,x=0,y=0,z=0,rx=Math.PI/2,ry=0,rz=0) => part(new CylinderGeometry(r,r,h,10),c,x,y,z,rx,ry,rz),
      ball: (r,c,x,y,z) => part(new SphereGeometry(r,8,6),c,x,y,z),
      wire: (points,c,r=.024) => part(new TubeGeometry(new CatmullRomCurve3(points.map(p=>new Vector3(...p))),24,r,5,false),c,0,0,0),
      finish(parent) {
        const geometry = mergeGeometries(parts);
        parts.forEach(p=>p.dispose());
        resources.add(geometry);
        const mesh = new Mesh(geometry,solid); parent.add(mesh); return mesh;
      },
    };
  }
  function label(parent, lines, w, h, x, y, z, bg = '#233b33', fg = '#c6d3a4') {
    const surface = document.createElement('canvas');
    surface.width = 256; surface.height = 128;
    const ctx = surface.getContext('2d');
    ctx.fillStyle = bg; ctx.fillRect(0,0,256,128);
    ctx.fillStyle = fg; ctx.font = '18px monospace';
    lines.forEach((line,i)=>ctx.fillText(line,15,28+i*25));
    const texture = new CanvasTexture(surface); texture.colorSpace = SRGBColorSpace;
    const material = new MeshBasicMaterial({map:texture});
    const geometry = new PlaneGeometry(w,h);
    const mesh = new Mesh(geometry,material); mesh.position.set(x,y,z); parent.add(mesh);
    [texture,material,geometry].forEach(r=>resources.add(r));
    return mesh;
  }
  function computer(group) {
    const b=builder();
    b.box(1.9,1.55,1.3,'case',0,.25,0);
    b.box(1.6,1.23,.12,'edge',0,.3,.68);
    b.box(1.4,1.04,.08,'screen',0,.32,.77);
    b.box(.48,.4,.7,'edge',0,-.7,0);
    b.box(1.5,.2,1.15,'case',0,-.98,.1);
    for(let i=0;i<8;i++)b.box(.025,.45,.018,'ink',.58+i*.045,.3,-.657);
    b.ball(.038,'gold',.76,-.33,.72);
    b.box(.35,.035,.03,'ink',.38,-.34,.73);
    b.finish(group);
    label(group,['C:\\> THINK','  sense → plan','  act → learn','_'],1.32,.93,0,.32,.816);
  }
  function board(group) {
    const b=builder();
    b.box(1.35,1.95,.11,'pcb');
    b.box(.65,.65,.12,'ink',0,.15,.12);
    for(let i=0;i<7;i++) {
      const p=-.27+i*.09;
      b.box(.035,.14,.035,'gold',p,.53,.13);b.box(.035,.14,.035,'gold',p,-.24,.13);
      b.box(.14,.035,.035,'gold',-.4,p+.15,.13);b.box(.14,.035,.035,'gold',.4,p+.15,.13);
    }
    for(let i=0;i<11;i++)for(const side of [-1,1]){
      b.box(.16,.1,.14,'ink',side*.52,-.6+i*.135,.13);
      b.box(.035,.035,.16,'gold',side*.52,-.6+i*.135,.27);
    }
    b.box(.43,.3,.23,'case',-.15,.92,.15);
    b.cylinder(.11,.24,'edge',.26,-.67,.18);
    b.ball(.042,'gold',.29,.69,.16);
    for(let i=0;i<4;i++)b.box(.45,.012,.012,'gold',-.12,-.38-i*.075,.061);
    b.finish(group);
    label(group,['MCU / 32','I/O  01'],.52,.26,0,.16,.187,'#242724','#c3b591');
  }
  function book(group, title, color) {
    const b=builder();
    b.box(1.25,1.7,.3,'paper');
    b.box(1.36,1.8,.07,color,0,0,.2);b.box(1.36,1.8,.07,color,0,0,-.2);
    b.box(.12,1.8,.46,color,-.63,0,0);
    for(let i=0;i<8;i++)b.box(1.24,.008,.01,'edge',.02,-.8+i*.024,.16);
    b.box(.025,1.7,.018,'gold',-.5,0,.245);
    b.finish(group);
    label(group,title,1.03,.62,.04,.22,.242,color,'#e3d4ab');
  }
  function floppy(group) {
    const b=builder();
    b.box(1.45,1.5,.14,'blue');
    b.box(.88,.57,.025,'case',0,.42,.087);
    b.box(.16,.4,.028,'ink',.2,.44,.105);
    b.box(1.07,.64,.025,'paper',0,-.37,.084);
    b.box(.12,.09,.03,'ink',-.55,-.58,.089);
    b.finish(group);
    label(group,['FIELD NOTES','1.44 MB / 1994'],1,.4,0,-.35,.103,'#c3b591','#403b31');
  }
  function keyboard(group) {
    const b=builder();
    b.box(2.7,.97,.22,'edge');
    b.box(2.61,.88,.08,'case',0,0,.15);
    for(let row=0;row<4;row++)for(let col=0;col<12;col++) {
      if(row===3&&col>2&&col<8)continue;
      b.box(.16,.14,.1,(col===0&&row===0)?'red':'paper',-1.17+col*.211,.3-row*.2,.24);
    }
    b.box(1.01,.14,.1,'paper',-.12,-.3,.24);
    b.wire([[.8,.49,0],[1.05,.84,-.1],[.7,1.05,-.2],[.2,1.1,-.3]],'ink');
    b.finish(group);
  }
  function cables(group) {
    const b=builder();
    ['red','gold','blue'].forEach((color,i)=>{
      const dx=i*.2;
      b.wire([[-.7+dx,.85,0],[-1+dx,.05,.2],[-.3+dx,-.65,.1],[.6+dx,-.55,-.1],[.55+dx,.32,.25]],color,.035);
      for(const [x,y,z] of [[-.7+dx,.85,0],[.55+dx,.32,.25]]){
        b.box(.12,.25,.12,'ink',x,y,z);b.box(.025,.18,.025,'gold',x,y+.2,z);
      }
    });b.finish(group);
  }
  function robot(group) {
    const base=builder();
    base.box(1.15,.22,.8,'edge',0,-.85,0);base.cylinder(.32,.22,'case',0,-.61,0,0);
    base.finish(group);
    const arm=new Group();arm.position.set(0,-.45,0);group.add(arm);
    const a=builder();a.cylinder(.2,.35,'gold');a.box(.24,.95,.23,'case',0,.48,0);a.cylinder(.19,.36,'gold',0,.97,0);a.finish(arm);
    const elbow=new Group();elbow.position.y=.97;arm.add(elbow);
    const e=builder();e.box(.2,.73,.2,'blue',0,.35,0);e.cylinder(.13,.28,'gold',0,.76,0);
    e.box(.08,.33,.1,'case',-.16,.93,0,0,0,-.35);e.box(.08,.33,.1,'case',.16,.93,0,0,0,.35);e.finish(elbow);
    movingParts.push(t=>{arm.rotation.z=-.35+Math.sin(t*.35)*.2;elbow.rotation.z=-.8+Math.cos(t*.45)*.35;});
  }
  function algorithm(group) {
    const b=builder();
    b.box(1.8,1.7,.065,'ink');
    const nodes=[[-.54,.48],[.45,.48],[.45,-.5],[-.54,-.5]];
    nodes.forEach(([x,y],i)=>{b.box(.53,.35,.035,i===2?'pcb':'edge',x,y,.058);});
    b.wire([[-.28,.48,.09],[.15,.48,.09]],'gold',.014);
    b.wire([[.45,.28,.09],[.45,-.29,.09]],'gold',.014);
    b.wire([[.16,-.5,.09],[-.27,-.5,.09]],'gold',.014);
    b.wire([[-.54,-.3,.09],[-.54,.28,.09]],'blue',.014);
    b.finish(group);
    label(group,['SENSE      PLAN','','LEARN      ACT'],1.56,1.02,0,.05,.09,'#242724','#c3b591');
    const dotBuilder=builder();dotBuilder.ball(.045,'gold',0,0,0);
    const dot=new Group();dotBuilder.finish(dot);dot.position.z=.14;group.add(dot);
    movingParts.push(t=>{const p=(t*.22)%4;const i=Math.floor(p);const f=p-i;const a=nodes[i],b=nodes[(i+1)%4];dot.position.x=a[0]+(b[0]-a[0])*f;dot.position.y=a[1]+(b[1]-a[1])*f;});
  }
  const placements=[
    [computer,-.35,.27,-1,1.18], [board,.36,.32,-2,1.1],
    [g=>book(g,['THE C','PROGRAMMING','LANGUAGE'],'#795446'),-.38,-.1,-3,1.1],
    [keyboard,.35,-.12,-1,1.05], [floppy,-.3,-.39,-1,.9],
    [robot,.36,-.39,-2,1.0], [cables,-.43,.06,-5,.8],
    [algorithm,.26,.05,-7,.95],
    [g=>book(g,['COMPUTER','SYSTEMS','& STRUCTURES'],'#4a6475'),.19,.43,-5,.8],
    [floppy,-.17,.43,-6,.65],
  ];
  placements.forEach(([make,x,y,z,scale],i)=>{
    const group=new Group();make(group);group.scale.setScalar(scale);
    group.rotation.set(.12+(i%3)*.12,(i%2?-.3:.3),i%2?.16:-.15);
    world.add(group);props.push({group,x,y,z,scale,phase:i*1.7,rx:group.rotation.x,ry:group.rotation.y,rz:group.rotation.z});
  });
  // A single inexpensive water sheet, deliberately quieter than the objects.
  const waterMaterial=new ShaderMaterial({transparent:true,depthWrite:false,uniforms:{time:{value:0},aspect:{value:1},pointer:{value:[0,0]}},
    vertexShader:'varying vec2 vUv; void main(){vUv=uv;gl_Position=vec4(position.xy,0.999,1.0);}',
    fragmentShader:`precision mediump float;
      varying vec2 vUv; uniform float time; uniform float aspect; uniform vec2 pointer;
      void main(){
        vec2 p=(vUv-.5)*vec2(aspect,1.0);
        float d=length(p-vec2(-.5,.18));
        float w=d*39.0+sin(p.x*7.0+time*.12)*1.8+sin(p.y*9.0-time*.1)*1.2-time*.12;
        w+=exp(-length(p-pointer)*4.0)*.8;
        float line=pow(.5+.5*sin(w),28.0);
        float edge=smoothstep(.17,.6,abs(vUv.x-.5));
        gl_FragColor=vec4(vec3(.64,.44,.22),line*.11*(.3+edge));
      }`});
  const waterGeo=new PlaneGeometry(2,2);const water=new Mesh(waterGeo,waterMaterial);water.frustumCulled=false;water.renderOrder=-1;scene.add(water);
  resources.add(waterMaterial);resources.add(waterGeo);

  let width=1,height=1,spanX=1,spanY=1,time=0,last=0,timer=0,raf=0,inView=true,paused=false,lost=false,disposed=false,frameCount=0;
  let targetX=0,targetY=0,pointerX=0,pointerY=0;
  let frameInterval=compact.matches?1000/18:1000/24;
  function resize() {
    const rect=hero.getBoundingClientRect();width=rect.width;height=rect.height;
    renderer.setPixelRatio(Math.min(devicePixelRatio,compact.matches?1:1.25));renderer.setSize(width,height,false);
    camera.aspect=width/height;camera.updateProjectionMatrix();
    spanY=2*Math.tan(Math.PI/10)*24;spanX=spanY*camera.aspect;
    waterMaterial.uniforms.aspect.value=camera.aspect;
    frameInterval=compact.matches?1000/18:1000/24;
    render();
  }
  function render() {
    pointerX+=(targetX-pointerX)*.045;pointerY+=(targetY-pointerY)*.045;
    camera.position.x=pointerX*.28;camera.position.y=pointerY*.18;camera.lookAt(0,0,0);
    for(const p of props){
      const mobileScale=compact.matches?.48:1;
      const perspective=(24-p.z)/24;
      const x=compact.matches?Math.sign(p.x)*(.43+Math.abs(p.x)*.04):p.x;
      p.group.position.set(x*spanX*perspective+Math.sin(time*.16+p.phase)*.18*mobileScale,p.y*spanY*perspective+Math.sin(time*.22+p.phase)*.22,p.z+Math.cos(time*.13+p.phase)*.7);
      p.group.scale.setScalar(p.scale*mobileScale);
      p.group.rotation.set(p.rx+Math.sin(time*.17+p.phase)*.16,p.ry+Math.sin(time*.12+p.phase)*.34,p.rz+Math.cos(time*.15+p.phase)*.12);
    }
    movingParts.forEach(update=>update(time));
    waterMaterial.uniforms.time.value=time;
    waterMaterial.uniforms.pointer.value[0]=pointerX*camera.aspect;
    waterMaterial.uniforms.pointer.value[1]=pointerY;
    const start=performance.now();renderer.render(scene,camera);
    // Drop resolution and cadence if this device cannot sustain the small budget.
    if(frameCount===30 && performance.now()-start>24){renderer.setPixelRatio(.75);renderer.setSize(width,height,false);frameInterval=1000/15;}
    frameCount++;
    canvas.dataset.frames=String(frameCount);
    canvas.dataset.drawCalls=String(renderer.info.render.calls);
    canvas.dataset.triangles=String(renderer.info.render.triangles);
  }
  const canRun=()=>!disposed&&!lost&&!paused&&!motion.matches&&!document.hidden&&inView&&!document.querySelector('.ui-image-dialog[open]');
  function stop(){clearTimeout(timer);cancelAnimationFrame(raf);timer=0;raf=0;last=0;canvas.dataset.sceneState='paused';}
  function tick(now){
    if(!canRun()){stop();return;}
    if(last)time+=Math.min((now-last)/1000,.1);last=now;render();
    canvas.dataset.sceneState='running';
    timer=setTimeout(()=>{raf=requestAnimationFrame(tick);},frameInterval);
  }
  function sync(){stop();if(canRun())raf=requestAnimationFrame(tick);}
  function preference(){if(motion.matches){time=0;render();}sync();}
  function pointer(event){if(event.pointerType==='touch')return;const r=hero.getBoundingClientRect();targetX=(event.clientX-r.left)/width-.5;targetY=.5-(event.clientY-r.top)/height;}
  function leave(){targetX=0;targetY=0;}
  function toggleMotion(){paused=!paused;toggle.textContent=paused?'Play background':'Pause background';toggle.setAttribute('aria-pressed',String(paused));sync();}
  function contextLost(event){event.preventDefault();lost=true;stop();hero.classList.add('ui-hero--fallback');toggle.hidden=true;}
  function contextRestored(){lost=false;hero.classList.remove('ui-hero--fallback');toggle.hidden=false;resize();sync();}
  const observer=new IntersectionObserver(entries=>{inView=entries[0].isIntersecting;sync();},{threshold:0});observer.observe(hero);
  const resizer=new ResizeObserver(resize);resizer.observe(hero);
  document.addEventListener('visibilitychange',sync);
  document.addEventListener('hero-dialog-change',sync);
  motion.addEventListener('change',preference);
  hero.addEventListener('pointermove',pointer,{passive:true});hero.addEventListener('pointerleave',leave,{passive:true});
  canvas.addEventListener('webglcontextlost',contextLost);canvas.addEventListener('webglcontextrestored',contextRestored);
  toggle.hidden=false;toggle.addEventListener('click',toggleMotion);
  hero.classList.remove('ui-hero--fallback');resize();sync();
  function dispose(event){
    if(event.persisted){stop();return;}
    disposed=true;stop();observer.disconnect();resizer.disconnect();
    document.removeEventListener('visibilitychange',sync);document.removeEventListener('hero-dialog-change',sync);
    motion.removeEventListener('change',preference);hero.removeEventListener('pointermove',pointer);hero.removeEventListener('pointerleave',leave);
    toggle.removeEventListener('click',toggleMotion);resources.forEach(r=>r.dispose());renderer.dispose();
  }
  window.addEventListener('pagehide',dispose);
  window.addEventListener('pageshow',sync);
  return {stop:()=>{paused=true;sync();}};
}
