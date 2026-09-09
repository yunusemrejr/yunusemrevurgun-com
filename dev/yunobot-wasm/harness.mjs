import fs from 'node:fs';import vm from 'node:vm';
export async function load(){const {instance}=await WebAssembly.instantiate(fs.readFileSync(new URL('../../assets/js/yunobot/core.wasm',import.meta.url)),{});const c=instance.exports;const enc=new TextEncoder(),dec=new TextDecoder();
const write=s=>{const bytes=enc.encode(s);new Uint8Array(c.memory.buffer,c.input_ptr(),bytes.length+1).set([...bytes,0]);return bytes.length;};
const read=ptr=>{let a=new Uint8Array(c.memory.buffer),end=ptr;while(a[end])end++;return dec.decode(a.subarray(ptr,end));};
const g={};g.window=g;vm.runInNewContext(fs.readFileSync(new URL('../../assets/js/yunobot/knowledge-pack.js',import.meta.url),'utf8'),g);
c.clear_documents();for(const p of g.YUNOBOT_KB.passages){const text=[p.page,p.url,p.heading||p.title,p.text].join('\0')+'\0';if(enc.encode(text).length<c.input_capacity())c.add_document(write(text));}
return{c,rows:g.YUNOBOT_KB.passages,ask(q){c.process(write(q));return{response:read(c.result_ptr()),sourceUrl:read(c.source_ptr()),kind:c.result_kind(),language:c.result_language(),confidence:c.result_confidence()};}};}
