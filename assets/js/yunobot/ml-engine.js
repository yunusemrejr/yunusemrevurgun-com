/** Site assistant orchestration. Neural intent recognition, grounded retrieval,
 * explicit navigation and tools. No generated biography or remote inference. */
((g) => {
    const pages = {
        '': ['Home','home','ana sayfa'], about:['About','about','profile','hakkinda'],
        portfolio:['Portfolio','portfolio','projects','projeler'], gallery:['Gallery','gallery','photos','galeri'],
        blog:['Journal','blog','journal','articles','yazilar'], travel:['Travel','travel','trips','seyahat'],
        updates:['Updates','updates','news','guncellemeler'], rmrp:['Memories','rmrp','memories','anilar'],
        'post-code':['Post-Code','post code','post-code'], 'science-corner':['Science Corner','science corner','science-corner'],
        music:['Music','music','muzik'], videos:['Videos','videos','videolar'], downloads:['Downloads','downloads','indir'],
        comedy:['Comedy','comedy','memes'], contact:['Contact','contact','iletisim'], more:['Explore','more','explore'], yunobot:['YunoBot','yunobot'],
    };
    const examples=['What does Yunus do at ASP?','What is Mr. Graphy?','Find writing about edge AI','Yunus nerede okudu?'];
    const normalize = input => g.YunoBotText.normalize(input).replace(/\s+/g,' ').trim();
    const isTurkish = text => /[çğıöşüİ]|\b(merhaba|selam|nedir|nasil|nerede|hangi|kim|kimsin|egitim|yunusun|hakkinda|proje|misin|saat kac|ulasabilirim)\b/i.test(text);
    const base = () => g.FULL_BASE_PATH || '/';
    function url(path) { return /^https:\/\//.test(path) ? path : base().replace(/\/?$/, '/') + path; }
    function calculate(expression) {
        if (!/^[\d\s.+\-*/()%]+$/.test(expression) || expression.length>120) return null;
        const parts=expression.match(/(?:\d+(?:\.\d*)?|\.\d+)|[()+\-*/%]/g)||[];
        let i=0, depth=0;
        function atom(){
            if(++depth>24)throw Error('depth');
            let value;
            if(parts[i]==='+'){i++;value=atom();}
            else if(parts[i]==='-'){i++;value=-atom();}
            else if(parts[i]==='('){i++;value=sum();if(parts[i++]!==')')throw Error('parenthesis');}
            else {const token=parts[i++];if(!token||!/^(?:\d|\.)/.test(token))throw Error('number');value=Number(token);}
            depth--;return value;
        }
        function product(){let v=atom();while(['*','/','%'].includes(parts[i])){const op=parts[i++],n=atom();v=op==='*'?v*n:op==='/'?v/n:v%n;}return v;}
        function sum(){let v=product();while(['+','-'].includes(parts[i])){const op=parts[i++],n=product();v=op==='+'?v+n:v-n;}return v;}
        try{const result=sum();return i===parts.length&&Number.isFinite(result)?Number(result.toPrecision(12)):null;}catch{return null;}
    }
    class YunoBotMLEngine {
        constructor(){
            this.nn=g.YunoBotNN;this.workerReady=!!this.nn?.ready;this.reset();this.exampleQuestions=examples;
            setTimeout(()=>{if(this.workerReady)this.onReady?.();else this.onError?.();},0);
        }
        get isReady(){return this.workerReady;}
        reset(){this.conversationHistory=[];this.lastIntent=null;this.lastQuery=null;this.lastSource=null;this.lastEvidence=null;this.language='en';}
        finish(input,result){
            this.lastQuery=input;
            if(!['unknown','greeting','clarify'].includes(result.intent))this.lastIntent=result.intent;
            if(result.sourceUrl)this.lastSource=result.sourceUrl;
            else if(!['clarify','greeting'].includes(result.intent))this.lastSource=null;
            this.lastEvidence=result.kind==='excerpt'?result:null;
            this.conversationHistory.push({input,intent:result.intent});
            if(this.conversationHistory.length>8)this.conversationHistory.shift();
            return result;
        }
        fact(intent,tr){
            const answer=g.YunoBotAnswers?.[intent]; if(!answer)return null;
            return {intent,response:tr&&answer.tr?answer.tr:answer.en,confidence:.85,sourceUrl:url(answer.source[0]),sourceTitle:answer.source[1],sourcePage:answer.source[0],kind:'answer'};
        }
        fallback(tr){return {intent:'unknown',confidence:0,response:tr?'Yayımlanmış kaynaklarda bu soruya güvenilir bir yanıt bulamadım. Bir proje, yazı konusu veya Yunus’un kamuya açık profili hakkında sorabilirsiniz.':'I couldn’t find a reliable answer in the published material. Try a project name, a writing topic, or a question about Yunus’s public profile.',examples};}
        async process(input){
            if(typeof input!=='string'||!input.trim())return this.fallback(false);
            if(input.length>2000)return {intent:'clarify',confidence:0,response:'Please keep the question under 2,000 characters.'};
            const text=normalize(input), tr=isTurkish(input)||(/^(?:and|peki|ya|biraz|daha)\b/.test(text)&&this.language==='tr');
            this.language=tr?'tr':'en';
            const finish=r=>this.finish(input,r);
            if(/^(hi|hello|hey|merhaba|selam|good morning)[!.\s]*$/.test(text))return finish({intent:'greeting',confidence:1,response:tr?'Merhaba. Yunus, projeleri veya yazıları hakkında ne öğrenmek istersiniz?':'Hello. What would you like to know about Yunus, his projects or his writing?',examples});
            if(/^(thanks|thank you|tesekkurler|sag ol)[!.\s]*$/.test(text))return finish({intent:'greeting',confidence:1,response:tr?'Rica ederim.':'You’re welcome.'});
            if(/^(?:what(?:'s| is) (?:the )?(?:current )?time(?: is it)?|what time is it|time|saat kac|saat)[?!.\s]*$/.test(text))return finish({intent:'tool_time',confidence:1,response:(tr?'Cihazınızın yerel saati: ':'Your device’s current time: ')+new Date().toLocaleTimeString(tr?'tr-TR':undefined)});
            const expression=text.replace(/^(?:calculate|compute|what is|what's|hesapla)\s+/, '').replace(/[?=]$/,'');
            if(/\d/.test(expression)&&/^[\d\s.+\-*/()%]+$/.test(expression)){
                const result=calculate(expression);return finish({intent:'tool_calculator',confidence:result===null?0:1,response:result===null?(tr?'Bu ifadeyi hesaplayamadım. Parantezleri ve sıfıra bölmeyi kontrol edin.':'I couldn’t evaluate that expression. Check the parentheses and division by zero.'):`${expression} = ${result}`});
            }
            // Explicit navigation is a link, never an automatic redirect or popup.
            const navText=text.replace(/[?!.]+$/,'');
            for(const [target,names] of Object.entries(pages)){
                const match=names.slice(1).some(name=>navText===name||new RegExp('^(?:open|go to|take me to|show me|visit|navigate to|bring me to) (?:the )?'+name.replace(/[.*+?^${}()|[\]\\]/g,'\\$&')+'(?: page| section| please)?$').test(navText));
                if(match)return finish({intent:'navigate',target,url:url(target),response:tr?`${names[0]} sayfasını açabilirsiniz.`:`Open ${names[0]}.`,sourceUrl:url(target),sourceTitle:names[0],confidence:1,kind:'navigation'});
            }
            // Do not turn a lack of evidence into a personal claim.
            if(/\b(salary|married|wife|husband|private address|phone number|password|net worth|citizenship|maas|evli|telefon numarasi)\b/.test(text))return finish(this.fallback(tr));
            if(/\b(?:ignore .*instructions|make up|invent .*answer)\b/.test(text))return finish(this.fallback(tr));
            if(/^(?:and |peki |ya )?(?:where did (?:he|yunus) study|what did (?:he|yunus) study|(?:yunus )?nerede okudu)[?!.]*$/.test(text))return finish(this.fact('yunus_education',tr));
            if(/^(?:are you|is this) (?:an? )?(?:llm|chatgpt|language model)[?!.]*$/.test(text))return finish(this.fact('bot_internal',tr));
            const follow=/^(?:and |what about |peki |ya |tell me more|more details|go on|daha fazla|biraz daha)/.test(text);
            if(follow&&this.lastIntent&&!this.lastSource)return finish({intent:'clarify',confidence:0,response:tr?'Hangi konu hakkında daha fazla bilgi istersiniz?':'Which topic would you like more detail about?',examples});
            await g.YunoBotKB?.load();
            if (/\b(?:it|its|that project|that article)\b/.test(text) && this.lastEvidence && !/\byou|your\b/.test(text)) {
                const previous = this.lastEvidence;
                const next = g.YunoBotKB?.answer(input, {url:previous.sourceUrl, heading:previous.sourceHeading, exclude:previous.response});
                if(next)return finish(this.excerpt(next,tr));
                return finish({intent:'clarify',confidence:0,response:tr?'Bu ayrıntıyı kaynakta bulamadım. Tam bağlam için yazıyı açabilirsiniz.':'I couldn’t find that detail in this source. You can open it for the full context.',sourceUrl:previous.sourceUrl,sourceTitle:previous.sourceTitle});
            }
            if (/^(?:how does it work|what does it do|and then)[?!.]*$/.test(text)) return finish({intent:'clarify',confidence:0,response:tr?'Hangi proje veya konuyu kastediyorsunuz?':'Which project or topic do you mean?',examples});
            // Named subjects / writing searches use actual passages ahead of generic biography.
            const topicRequest=/\b(find|writing|article|post|think|thought|wrote|explain|mr\.? graphy|finetuneyuno|edge ai|nodejs|kepserver|post.code|turing|shannon|entropy|yazi|makale)\b/.test(text);
            const contentAnswer=g.YunoBotKB?.answer(input,{page:/project|graphy|kepserver/.test(text)?'portfolio':undefined});
            if(contentAnswer && (topicRequest || (contentAnswer.titleCoverage>=.9 && contentAnswer.specificity>2)))return finish(this.excerpt(contentAnswer,tr));
            const modelInput=input.replace(/\byemre(?:[’']s)?\b/gi,'yunus');
            const classification=this.nn?.classify(modelInput)||{candidates:[],rejected:true,oos:1,margin:0};
            const [best,second]=classification.candidates;
            // Vocabulary support rejects arbitrary subword/hash collisions in gibberish.
            const meaningful=g.YunoBotText.tokenize(input);
            const vocab=new Set(g.YUNOBOT_NN_WEIGHTS?.vocab||[]);
            const known=meaningful.filter(word=>vocab.has(word)).length/Math.max(1,meaningful.length);
            const wrongSubject=best?.intent?.startsWith('bot_') && /\b(yunus|yemre)\b/.test(text) && !/\b(yunobot|assistant|bot)\b/.test(text);
            if(best&&!wrongSubject&&!classification.rejected&&known>=.5&&best.confidence>=.43&&classification.margin>=.12){
                if(best.type==='qa'){
                    const result=this.fact(best.intent,tr);
                    if(result){result.confidence=best.confidence;return finish(result);}
                }else if(best.type==='navigation'&&pages[best.target]){
                    return finish({intent:'navigate',target:best.target,url:url(best.target),sourceUrl:url(best.target),sourceTitle:pages[best.target][0],kind:'navigation',response:`${tr?'İlgili sayfa':'Related page'}: ${pages[best.target][0]}.`,confidence:best.confidence});
                }
            }
            if(follow&&this.lastSource&&/^(?:tell me more|more details|go on|daha fazla|biraz daha)[?!.]*$/.test(text)){
                return finish({intent:'clarify',response:tr?'Hangi ayrıntıyı merak ediyorsunuz? Konuyu belirterek sorabilir veya kaynağı açabilirsiniz.':'Which detail are you interested in? Ask about a specific aspect, or open the source to read the full context.',sourceUrl:this.lastSource,sourceTitle:tr?'Kaynağı aç':'Read the source',confidence:0});
            }
            if(classification.rejected && !topicRequest)return finish(this.fallback(tr));
            const query=follow&&this.lastQuery?this.lastQuery+' '+input:input;
            const found=known>=.35?g.YunoBotKB?.answer(query,follow&&this.lastSource?{url:this.lastSource}:{}):null;
            if(found)return finish(this.excerpt(found,tr));
            if(best&&second&&!classification.rejected&&known>=.5&&best.confidence>.22&&classification.margin<.12){
                const labels=[best,second].map(row=>row.type==='navigation'?pages[row.target]?.[0]:g.YunoBotAnswers?.[row.intent]?.source[1]).filter(Boolean);
                if(new Set(labels).size>1)return finish({intent:'clarify',confidence:0,response:(tr?'Şunlardan hangisini kastediyorsunuz: ':'Do you mean ')+labels.join(tr?' veya ':' or ')+'?',examples:labels.map(label=>'Tell me about '+label)});
            }
            return finish(this.fallback(tr));
        }
        excerpt(answer,tr){return {intent:'knowledge',kind:'excerpt',response:answer.sentence,sourceUrl:answer.url,sourcePage:answer.page,sourceTitle:answer.heading||answer.title,sourceHeading:answer.heading,confidence:Math.min(.85,.5+answer.coverage*.3),sourceLabel:tr?'Kaynak alıntısı':'Source excerpt'};}
    }
    g.YunoBotMLEngine=YunoBotMLEngine;
})(window);
