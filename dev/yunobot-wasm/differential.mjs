import fs from 'node:fs';import vm from 'node:vm';import assert from 'node:assert/strict';
import {load} from './harness.mjs';
const bot=await load(),g={console,Float32Array,Int8Array,Uint8Array,atob:s=>Buffer.from(s,'base64').toString('binary')};g.window=g;
for(const name of ['text','nn-weights','nn-engine'])vm.runInNewContext(fs.readFileSync(new URL(`../../assets/js/yunobot/${name}.js`,import.meta.url),'utf8'),g);
const training=JSON.parse(fs.readFileSync(new URL('../yunobot-nn/training.json',import.meta.url)));
const samples=[...training.qa,...training.navigation].flatMap(x=>x.sentences).filter((_,i)=>i%23===0);
let maxError=0;
for(const text of samples){const bytes=new TextEncoder().encode(text);new Uint8Array(bot.c.memory.buffer,bot.c.input_ptr(),bytes.length+1).set([...bytes,0]);bot.c.classify_input(bytes.length);
 const result=g.YunoBotNN.classify(text);
 for(const row of result.candidates){const id=g.YUNOBOT_NN_WEIGHTS.intents.findIndex(i=>i.label===row.label);const error=Math.abs(row.confidence-bot.c.class_probability(id));maxError=Math.max(error,maxError);assert(error<.006,`${text}: ${error}`);}
}
console.log(`${samples.length} classifier parity cases passed; max probability error ${maxError.toFixed(6)}`);
