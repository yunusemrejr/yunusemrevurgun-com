/** Source relevance and refresh lifecycle, with no network access. */
import assert from 'node:assert/strict';
import {loadBot} from '../yunobot-nn/harness.mjs';
let count=0;
const {sandbox:s}=loadBot();await s.YunoBotKB.load();
for(const [query,expected] of [['what is mr graphy',/graph data|Django/i],['what is finetuneyuno',/fine.tuning/i],['edge AI',/edge|frontier/i],['explain entropy',/entropy/i]]){
 const result=s.YunoBotKB.answer(query);
 assert(result&&expected.test(result.text),query);
 assert(s.YUNOBOT_KB.passages.some(row=>row.text===result.text&&row.url===result.url));count+=2;
}
for(const query of ['who won the world cup in 2018','asdkjh qwerty zzz','how do I fix my car engine']){assert.equal(s.YunoBotKB.answer(query),null);count++;}
const origin='https://yunusemrevurgun.com';
const fresh={version:2,scope:'blog',publishedUrls:[origin+'/blog/aurorafixture'],passages:[{page:'blog',url:origin+'/blog/aurorafixture',title:'AuroraFixture',heading:'AuroraFixture',text:'AuroraFixture is a test application for checking newly published source discovery.'}]};
let requests=[];
const options={document:{currentScript:{dataset:{liveSource:origin+'/api/yunobot-knowledge.php'}}},location:{href:origin+'/yunobot',origin},AbortSignal,fetch:async(url,opts)=>{requests.push([url,opts]);return{ok:true,json:async()=>fresh};}};
const {sandbox:live,engine}=loadBot(options);await live.YunoBotKB.load();
assert.equal(live.YunoBotKB.answer('AuroraFixture').url,fresh.publishedUrls[0]);count++;
assert.equal(live.YunoBotKB.search('finetuneyuno').some(row=>row.page==='blog'),false);count++;
const answer=await engine.process('What is AuroraFixture?');assert.match(answer.response,/test application/);count++;
assert.equal(requests.length,1);assert.equal(requests[0][0],origin+'/api/yunobot-knowledge.php');assert.equal(requests[0][1].credentials,'omit');assert(!requests[0][1].body);count++;
const {sandbox:offline}=loadBot({...options,fetch:async()=>{throw Error('offline');}});await offline.YunoBotKB.load();assert(offline.YunoBotKB.answer('finetuneyuno'));count++;
console.log(`${count} knowledge-source checks passed`);
