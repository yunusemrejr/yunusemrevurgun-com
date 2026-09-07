/** Local passage retrieval: BM25 + field/phrase relevance + bounded neural reranking.
 * Every result is an unmodified excerpt with its actual source URL.
 */
((g) => {
    const stop = new Set(('a an the this that those these of in on at to for from by with and or but is are was were be been being do does did has have had i me my you your he him his she her they their it its we our who what when where why how which can could would should will tell show please about know more some any as also yunus emre vurgun yemre site website article post write writes writing written say says said think thinks explain question find want like give read learn learning using use used work works many much go got went come came get getting turkish english bana bir bu su ve veya ile mi mu nedir nasil hangi kim ne onun yunus\'un hakkinda bilgi misin anlat soyler soyle').split(' '));
    const synonyms = {
        projects: ['project'], built: ['project','build'], created: ['project','build'],
        education: ['education','university','degree'], studied: ['study','university','degree'], college: ['university'],
        countries: ['country','travel'], visited: ['visit','travel'], trips: ['travel'],
        languages: ['language','php','python','javascript'], coding: ['code','programming'],
        neural: ['neural'], ai: ['ai','intelligence'], ml: ['ml','machine'],
        egitim: ['education','university'], universite: ['university'], proje: ['project'], projeleri: ['project'],
        seyahat: ['travel'], ulke: ['country'], muzik: ['music'], iletisim: ['contact'],
        graphy: ['graphy'], finetuneyuno: ['finetuneyuno'], nodejs: ['node','nodejs'],
    };
    const normalize = text => g.YunoBotText.normalize(text);
    const stem = word => word.length > 4 ? word.replace(/ies$/, 'y').replace(/(?:ing|ed|s)$/, '') : word;
    const tokens = text => g.YunoBotText.tokenize(text).filter(t => !stop.has(t) && t.length > 1).map(stem);
    const liveSource = g.document?.currentScript?.dataset.liveSource || '';
    let refreshing = null;
    let rows = [], index = new Map(), average = 1, loaded = false;
    function build(passages) {
        index = new Map();
        rows = passages.map((passage, id) => {
            const body = tokens(passage.text), fields = new Map();
            for (const [text, boost] of [[passage.text, 1], [passage.heading, 1.8], [passage.title, 2.4]]) {
                for (const term of tokens(text)) fields.set(term, (fields.get(term) || 0) + boost);
            }
            for (const [term, tf] of fields) {
                if (!index.has(term)) index.set(term, []);
                index.get(term).push([id, tf]);
            }
            return { ...passage, length: body.length, fields, body: new Set(body) };
        });
        average = rows.reduce((sum, row) => sum + row.length, 0) / Math.max(1, rows.length);
        loaded = true;
        return true;
    }
    function load() {
        if (refreshing) return refreshing;
        const corpus = g.YUNOBOT_KB;
        if (!corpus || corpus.version !== 2 || !g.YunoBotText) return Promise.resolve(false);
        if (!loaded) build(corpus.passages);
        refreshing = (async () => {
            if (!liveSource || !g.fetch) return true;
            try {
                const source = new URL(liveSource, g.location.href);
                if(source.origin !== g.location.origin) return true;
                const response=await g.fetch(source.href,{credentials:'omit',signal:AbortSignal.timeout(2500)});
                if(!response.ok) return true;
                const latest=await response.json();
                if(latest.version!==2 || latest.scope!=='blog' || !Array.isArray(latest.passages) || !Array.isArray(latest.publishedUrls))return true;
                const published=new Set(latest.publishedUrls);
                const replacements=new Set(latest.passages.map(row=>row.url));
                const safe=latest.passages.filter(row=>typeof row.text==='string'&&typeof row.title==='string'&&typeof row.heading==='string'&&published.has(row.url)&&row.url.startsWith(source.origin+'/blog/'));
                const kept=corpus.passages.filter(row=>row.page!=='blog'||(!replacements.has(row.url)&&published.has(row.url)));
                build([...kept,...safe]);
            } catch { /* Bundled public sources remain available offline. */ }
            return true;
        })();
        return refreshing;
    }
    function search(query, options = {}) {
        if (!loaded || typeof query !== 'string' || query.length > 2000) return [];
        const raw = g.YunoBotText.tokenize(query).filter(t => !stop.has(t) && t.length > 1);
        const groups = [...new Set(raw)].map(term => [...new Set([term, ...(synonyms[term] || [])].map(stem))]);
        if (!groups.length) return [];
        const hits = new Map();
        groups.forEach((terms, group) => {
            const perGroup = new Map();
            for (const term of terms) {
                const postings = index.get(term) || [];
                const idf = Math.log(1 + (rows.length - postings.length + .5) / (postings.length + .5));
                for (const [id, tf] of postings) {
                    const row = rows[id];
                    if (options.url && row.url !== options.url) continue;
                    if (options.heading && row.heading !== options.heading) continue;
                    if (options.exclude && row.text === options.exclude) continue;
                    const score = idf * tf * 2.2 / (tf + 1.2 * (.25 + .75 * row.length / average));
                    const previous = perGroup.get(id);
                    if (!previous || previous.score < score) perGroup.set(id, {score, idf});
                }
            }
            for (const [id, value] of perGroup) {
                if (!hits.has(id)) hits.set(id, {id, score:0, groups:new Set(), specificity:0});
                const hit=hits.get(id); hit.score+=value.score; hit.groups.add(group); hit.specificity=Math.max(hit.specificity,value.idf);
            }
        });
        const phrase = normalize(query).replace(/^(?:what is|tell me about|find|explain)\s+/, '').replace(/[?.!]+$/, '');
        let candidates = [...hits.values()].filter(hit => {
            const coverage = hit.groups.size / groups.length;
            return coverage >= .7 && (hit.groups.size >= Math.min(2, groups.length)) && (hit.groups.size > 1 || hit.specificity >= 2);
        }).map(hit => {
            const row = rows[hit.id];
            const bodyMatches = groups.filter(terms => terms.some(term => row.body.has(term))).length;
            const headingMatch = groups.every(terms => terms.some(term => tokens(row.heading).includes(term)));
            const definition = /\b(?:application|software|system|project|tool|app|is a|was a|refers to)\b/i.test(row.text);
            if (!bodyMatches && !(headingMatch && definition)) return null;
            let boost = bodyMatches * 4;
            if (/\b(application|software|web server|fine.tuning app|database|algorithm)\b/i.test(row.text) && /^(?:what is|tell me about)/.test(normalize(query))) boost += 8;
            if (/^(?:what is|what are|tell me about)/.test(normalize(query)) && definition) boost += 3;
            if (phrase.length >= 5 && normalize(row.title).includes(phrase)) boost += 7;
            if (phrase.length >= 5 && normalize(row.heading).includes(phrase)) boost += 4;
            if (options.page && row.page === options.page) boost += 2;
            // Prefer the complete article over a listing-page excerpt.
            if (/\/(blog|updates)\/[^/]+$/.test(row.url)) boost += .8;
            return {...hit, score: (hit.score + boost) * (hit.groups.size / groups.length), row};
        }).filter(Boolean).sort((a,b) => b.score-a.score).slice(0,12);
        if (g.YunoBotNN?.ready) {
            const queryVector = g.YunoBotNN.embed(query);
            for (const hit of candidates) {
                if (!hit.row.vector) hit.row.vector = g.YunoBotNN.embed(hit.row.title + ' ' + hit.row.heading + ' ' + hit.row.text);
                let cosine=0; for(let d=0;d<queryVector.length;d++)cosine+=queryVector[d]*hit.row.vector[d];
                hit.score += Math.max(0, cosine) * .6;
            }
            candidates.sort((a,b)=>b.score-a.score);
        }
        return candidates.slice(0,options.limit || 3).map(hit=>({sentence:hit.row.text, text:hit.row.text, url:hit.row.url, page:hit.row.page, title:hit.row.title, heading:hit.row.heading, score:hit.score, coverage:hit.groups.size/groups.length,titleCoverage:groups.filter(terms=>terms.some(term=>tokens(hit.row.title+' '+hit.row.heading).includes(term))).length/groups.length,specificity:hit.specificity}));
    }
    g.YunoBotKB = {load,search,answer(query,options){return search(query,{...options,limit:1})[0]||null;},get ready(){return loaded;}};
})(typeof window === 'undefined' ? globalThis : window);
