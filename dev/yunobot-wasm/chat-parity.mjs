// The conversation classifier in core.cpp must reproduce the JS reference forward pass written by build.mjs.
import fs from 'node:fs';import assert from 'node:assert/strict';
import {load} from './harness.mjs';
const bot=await load(),ref=JSON.parse(fs.readFileSync(new URL('chat-reference.json',import.meta.url)));
const enc=new TextEncoder();let maxErr=0,classMismatch=0;
for(const [text,cls,p] of ref){const b=enc.encode(text);new Uint8Array(bot.c.memory.buffer,bot.c.input_ptr(),b.length+1).set([...b,0]);
 const got=bot.c.chat_classify(b.length),prob=bot.c.chat_probability(cls);
 if(got!==cls&&Math.abs(bot.c.chat_probability(got)-prob)>.02)classMismatch++;
 maxErr=Math.max(maxErr,Math.abs(prob-p));}
assert(classMismatch===0,`${classMismatch} class mismatches`);assert(maxErr<.02,`max probability error ${maxErr}`);
console.log(`${ref.length} conversation-classifier parity cases passed; max probability error ${maxErr.toFixed(5)}`);
