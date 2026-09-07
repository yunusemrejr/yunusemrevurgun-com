/** Neural-only evaluation. These paraphrases are not training examples. */
import assert from 'node:assert/strict';
import {readFileSync} from 'node:fs';
import {loadBot} from './harness.mjs';
const {sandbox:s}=loadBot();
const nn=s.YunoBotNN;
assert(nn.ready);
const data=JSON.parse(readFileSync(new URL('./training.json',import.meta.url),'utf8'));
const training=new Set([...data.qa,...data.navigation].flatMap(row=>row.sentences.map(text=>s.YunoBotText.tokenize(text).join(' '))));
const probes=[
 ['Summarize his employment history','what_does_yunus_do'],
 ['Where is Yemre currently based','where_is_yunus'],
 ['What universities appear in his education','yunus_education'],
 ['What are his coding languages','yunus_technologies'],
 ['Which places has Yunus traveled to','yunus_travel'],
 ['Is the assistant a large language model','bot_internal'],
 ['Where can I browse his repositories','yunus_github'],
 ['Does this chatbot keep the messages I write','bot_privacy'],
 ['How can someone get in touch with Yunus','yunus_contact_method'],
 ['What kinds of questions can I ask this bot','bot_capabilities'],
 ['Could you explain your neural architecture','bot_internal'],
 ['Can I use Turkish to ask you questions','bot_language'],
 ['Which courses and certificates does he list','yunus_certifications'],
 ['Is his software released as open source','yunus_open_source'],
 ['Which programming projects has he created','yunus_projects'],
 ['What are the main themes of his blog','yunus_blog_topics'],
 ['What is his work with industrial automation','yunus_industrial_automation'],
 ['Yunus hangi sehirde yasiyor','where_is_yunus'],
 ['Yunusun universite egitimi hakkinda bilgi','yunus_education'],
 ['Sorularimi baska sunuculara yolluyor musun','bot_privacy'],
];
let correct=0;
for(const [query,want] of probes){
 assert(!training.has(s.YunoBotText.tokenize(query).join(' ')),`Training overlap: ${query}`);
 const result=nn.classify(query.replace(/yemre/gi,'yunus'));
 const got=result.candidates[0]?.intent;
 if(!result.rejected && got===want)correct++;else console.log('MISS',query,'=>',result.rejected?'OOS':got,'expected',want);
}
const unknown=['qzvxq brrzz kkkk','how do I replace a car battery','what is the weather tomorrow in Rome','who is the current king of Spain','can you make me a dinner recipe'];
let rejected=0;
for(const query of unknown){if(!nn.classify(query).accepted)rejected++;}
const start=performance.now();for(let i=0;i<500;i++)nn.classify(probes[i%probes.length][0]);
const ms=(performance.now()-start)/500;
assert.deepEqual([...s.YunoBotText.tokenize('İSTANBUL ışık C++ C#')],['istanbul','isik','c++','c#']);
assert.equal(nn.embed('software').length,s.YUNOBOT_NN_WEIGHTS.dim);
console.log(`Neural held-out top-1: ${correct}/${probes.length}; Out-of-scope abstention: ${rejected}/${unknown.length}; ${ms.toFixed(3)} ms/query`);
assert(correct>=17,'Neural paraphrase regression');assert(rejected===unknown.length,'OOS regression');assert(ms<10,'Inference regression');
