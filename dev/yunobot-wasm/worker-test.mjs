/** Worker transport tests: real Wasm with deterministic public-source fetch fixtures. */
import fs from 'node:fs';import vm from 'node:vm';import assert from 'node:assert/strict';
const root=new URL('../../',import.meta.url),base='https://yunusemrevurgun.com';
const messages=[],requests=[];
const snapshot={version:2,scopes:['blog','updates','gallery','travel','portfolio'],publishedUrls:[base+'/blog/northstar',base+'/updates/900'],passages:[
 {page:'blog',url:base+'/blog/northstar',title:'NorthstarCompiler',heading:'NorthstarCompiler',text:'NorthstarCompiler is a small compiler project that translates a simple input language into executable instructions.'},
 {page:'updates',url:base+'/updates/900',title:'New release',heading:'New release',text:'New release — 2026-09-09. The compiler now handles extra expressions and clearer errors.'}
]};
const scope={WebAssembly,TextEncoder,TextDecoder,Uint8Array,URL,AbortSignal,console,location:{href:base+'/assets/js/yunobot/wasm-worker.js',origin:base},postMessage:m=>messages.push(m),fetch:async(url,options)=>{
 requests.push({url:String(url),options});
 if(String(url).endsWith('.wasm'))return{ok:true,arrayBuffer:async()=>Uint8Array.from(fs.readFileSync(new URL('assets/js/yunobot/core.wasm',root))).buffer};
 return{ok:true,json:async()=>snapshot};
}};scope.self=scope;vm.createContext(scope);scope.importScripts=()=>vm.runInContext(fs.readFileSync(new URL('assets/js/yunobot/knowledge-pack.js',root),'utf8'),scope);
vm.runInContext(fs.readFileSync(new URL('assets/js/yunobot/wasm-worker.js',root),'utf8'),scope);
await scope.onmessage({data:{type:'init',wasm:base+'/core.wasm',corpus:base+'/knowledge.js',live:base+'/api/yunobot-knowledge.php'}});
assert.equal(messages.at(-1).type,'ready');const count=requests.length;
await scope.onmessage({data:{type:'process',id:1,text:'What is NorthstarCompiler?'}});
assert.equal(messages.at(-1).result.sourceUrl,base+'/blog/northstar');
await scope.onmessage({data:{type:'process',id:2,text:'What are the latest updates?'}});
assert.equal(messages.at(-1).result.sourceUrl,base+'/updates/900');
await scope.onmessage({data:{type:'process',id:3,text:'What is Mr. Graphy?'}});
assert(!messages.at(-1).result.sourceUrl.includes('meet-mr-graphy'));
assert.equal(requests.length,count);assert(requests.every(r=>r.options.credentials==='omit'&&!r.options.body));
console.log('5 worker checks passed: fresh retrieval, latest update, removed sources, no prompt requests, no credentials');
