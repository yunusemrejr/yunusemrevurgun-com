/**
 * YunoBot ML Engine - Hybrid Pattern Matching & Semantic Search
 * Combines regex patterns (fast, accurate) with Transformers.js embeddings (semantic understanding)
 */

(function() {
    'use strict';
    
    class YunoBotMLEngine {
        constructor() {
            this.patterns = this.initializePatterns();
            this.navigationMap = this.initializeNavigationMap();
            this.exampleQuestions = this.initializeExampleQuestions();
            this.semanticTargets = this.initializeSemanticTargets();
            
            // ============================================================
            // PERFORMANCE: Lazy loading state
            // ============================================================
            this.embeddingWorker = null;
            this.workerReady = false;
            this.workerInitiated = false; // Track if we've started init
            this.targetEmbeddings = null;
            this.pendingRequests = new Map();
            this.requestCounter = 0;
            
            // ============================================================
            // PERFORMANCE: Query debouncing for faster inference
            // ============================================================
            this.debounceMs = 150; // Debounce rapid-fire queries
            this.debounceTimer = null;
            this.lastQueryTime = 0;
            
            // ============================================================
            // PERFORMANCE: Response deduplication cache
            // ============================================================
            this.responseCache = new Map();
            this.responseCacheMaxAge = 60000; // 1 minute cache
            
            // ============================================================
            // Context for follow-up questions
            // ============================================================
            this.lastIntent = null;
            this.lastQuery = null;
            
            // ============================================================
            // Conversation memory (sliding window of last N exchanges)
            // ============================================================
            this.conversationHistory = [];
            this.maxHistoryLength = 8;
            
            // ============================================================
            // Typo correction cache for common misspellings
            // ============================================================
            this.typoCorrections = this.initializeTypoCorrections();
            
            // ============================================================
            // Confidence calibration state
            // ============================================================
            this.confidenceHistory = [];
            this.maxConfidenceHistory = 20;
            
            // ============================================================
            // Session-level statistics for adaptive thresholding
            // ============================================================
            this.sessionStats = {
                totalQueries: 0,
                successfulMatches: 0,
                avgConfidence: 0.5,
                lastRecalibration: Date.now(),
            };
            
            // ============================================================
            // PERFORMANCE: Inactivity timeout for worker
            // ============================================================
            this.workerInactivityTimeout = null;
            this.WORKER_INACTIVITY_MS = 4 * 60 * 1000; // 4 minutes (worker self-terminates at 5)
            
            // Lazy initialization - worker starts on first user interaction
            // this.initializeWorker(); // Called lazily
        }
        
        // ============================================================
        // PERFORMANCE: Lazy worker initialization
        // ============================================================
        ensureWorkerInitialized() {
            if (this.workerInitiated) return;
            this.workerInitiated = true;
            this.initializeWorker();
        }
        
        initializePatterns() {
            return {
                // Direct navigation patterns (regex - instant, 100% accurate)
                navigation: [
                    { pattern: /^(?:home|main|landing|index)$/i, intent: 'navigate', target: '', confidence: 1.0 },
                    { pattern: /^(?:about)$/i, intent: 'navigate', target: 'about', confidence: 1.0 },
                    { pattern: /^(?:portfolio|work|projects)$/i, intent: 'navigate', target: 'portfolio', confidence: 1.0 },
                    { pattern: /^(?:gallery|photos|pictures|pics|images)$/i, intent: 'navigate', target: 'gallery', confidence: 1.0 },
                    { pattern: /^(?:blog|posts|articles)$/i, intent: 'navigate', target: 'blog', confidence: 1.0 },
                    { pattern: /^(?:travel|trips|journeys)$/i, intent: 'navigate', target: 'travel', confidence: 1.0 },
                    { pattern: /^(?:updates|news|changelog)$/i, intent: 'navigate', target: 'updates', confidence: 1.0 },
                    { pattern: /^(?:post[\s-]?code|code|snippets)$/i, intent: 'navigate', target: 'post-code', confidence: 1.0 },
                    { pattern: /^(?:contact|reach|email)$/i, intent: 'navigate', target: 'contact', confidence: 1.0 },
                    
                    // Navigation with action verbs (regex)
                    { pattern: /(?:take|go|navigate|open|show|visit|see|view).*(?:me|us)?.*(?:to|the)?\s*(?:about|about\s*page|about\s*section)/i, intent: 'navigate', target: 'about', confidence: 0.95 },
                    { pattern: /(?:take|go|navigate|open|show|visit|see|view).*(?:me|us)?.*(?:to|the)?\s*(?:portfolio|portfolio\s*page|portfolio\s*section)/i, intent: 'navigate', target: 'portfolio', confidence: 0.95 },
                    { pattern: /(?:take|go|navigate|open|show|visit|see|view).*(?:me|us)?.*(?:to|the)?\s*(?:gallery|gallery\s*page|gallery\s*section)/i, intent: 'navigate', target: 'gallery', confidence: 0.95 },
                    { pattern: /(?:take|go|navigate|open|show|visit|see|view).*(?:me|us)?.*(?:to|the)?\s*(?:blog|blog\s*page|blog\s*section)/i, intent: 'navigate', target: 'blog', confidence: 0.95 },
                    { pattern: /(?:take|go|navigate|open|show|visit|see|view).*(?:me|us)?.*(?:to|the)?\s*(?:travel|travel\s*page|travel\s*section)/i, intent: 'navigate', target: 'travel', confidence: 0.95 },
                    { pattern: /(?:take|go|navigate|open|show|visit|see|view).*(?:me|us)?.*(?:to|the)?\s*(?:updates|updates\s*page|updates\s*section)/i, intent: 'navigate', target: 'updates', confidence: 0.95 },
                    { pattern: /(?:take|go|navigate|open|show|visit|see|view).*(?:me|us)?.*(?:to|the)?\s*(?:post[\s-]?code|post[\s-]?code\s*page|post[\s-]?code\s*section)/i, intent: 'navigate', target: 'post-code', confidence: 0.95 },
                    { pattern: /(?:take|go|navigate|open|show|visit|see|view).*(?:me|us)?.*(?:to|the)?\s*(?:contact|contact\s*page|contact\s*section)/i, intent: 'navigate', target: 'contact', confidence: 0.95 },
                    { pattern: /(?:take|go|navigate|open|show|visit|see|view).*(?:me|us)?.*(?:to|the)?\s*(?:home|home\s*page|landing|main)/i, intent: 'navigate', target: '', confidence: 0.95 },
                ],
                
                // Greeting patterns
                greeting: [
                    { pattern: /^(?:hi|hello|hey|greetings|howdy|sup|what's\s*up|heyo|yo|wassup|selam|merhaba)/i, response: "Hello! How can I help you today?", confidence: 0.9 },
                    { pattern: /^(?:good\s*(?:morning|afternoon|evening|day))/i, response: "Good day! What would you like to know?", confidence: 0.9 },
                ],

                // Turkish greeting patterns
                greeting_tr: [
                    { pattern: /^(?:selam|merhaba|günaydın|iyi\s*akşamlar|iyi\s*günler|hey|alo|hey\s*bot)/i, response: "Selam! Size nasıl yardımcı olabilirim?", confidence: 0.9 },
                    { pattern: /^(?:nasılsın|naber|ne\s*haber|iyi\s*misinn?)/i, response: "Teşekkür ederim, iyiyim! Size nasıl yardımcı olabilirim?", confidence: 0.9 },
                    { pattern: /^(?:günaydın)/i, response: "Günaydın! Size nasıl yardımcı olabilirim?", confidence: 0.9 },
                    { pattern: /^(?:iyi\s*akşamlar)/i, response: "İyi akşamlar! Size nasıl yardımcı olabilirim?", confidence: 0.9 },
                    { pattern: /^(?:teşekkür\s*ederim|sağ\s*ol|eyvallah|teşekkürler)/i, response: "Rica ederim! Başka bir şey sorabilirsiniz.", confidence: 0.9 },
                    { pattern: /^(?:hoşça\s*kal|güle\s*güle|bye|görüşürüz)/i, response: "Görüşürüz! Tekrar beklerim.", confidence: 0.9 },
                ],
                
                // Question patterns (specific ones with high confidence, generic ones with low confidence)
                questions: [
                    { pattern: /^(?:who\s*is\s*(?:yunus|this|that))|(?:who\s*is\s*yunus)|(?:yunus\s*is\s*who)/i, response: "I'm YunoBot, Yunus's AI assistant. Yunus Emre Vurgun (also known as Yµn ^…^ ƒ(x)) is a software developer and IT specialist based in Istanbul, Türkiye. He writes code that helps industries work better and brings new ideas to life.", confidence: 0.95 },
                    { pattern: /^(?:what\s*does\s*yunus\s*do)|(?:yunus\s*does\s*what)|(?:what\s*is\s*yunus\s*job)|(?:what\s*does\s*he\s*do)/i, response: "Yunus is a software developer and IT specialist. He creates projects like scheduling systems for industrial clients, develops web applications with Django, builds neural networks, and works on computational intelligence and operational technology. Most of his work is open-source and available on GitHub.", confidence: 0.95 },
                    { pattern: /^(?:where\s*is\s*(?:he|yunus))|(?:where\s*does\s*(?:he|yunus)\s*(?:live|reside))|(?:where\s*is\s*(?:he|yunus)\s*from)|(?:where\s*(?:does\s*)?(?:he|yunus)\s*(?:located|based))/i, response: "Yunus is based in Istanbul, Türkiye.", confidence: 0.95 },
                    { pattern: /^(?:where\s*did\s*yunus\s*go)|(?:which\s*countries)|(?:how\s*many\s*countries)|(?:countries\s*(?:has|did|visited))/i, response: "Yunus has visited 21 countries, from Tanzania to Boston. He enjoys traveling and has explored many places around the world. Check out his travel page to see more about his journeys!", confidence: 0.95 },
                    { pattern: /^(?:is\s*yunus\s*(?:cool|awesome|nice|great|good|interesting))|(?:yunus\s*is\s*(?:cool|awesome|nice|great|good|interesting))/i, response: "I think Yunus is pretty cool! He's a software developer who loves coding, learning new technologies, playing guitar, and traveling. He's visited 21 countries and creates interesting projects. Check out his portfolio and blog to learn more about him!", confidence: 0.95 },
                    { pattern: /^(?:what\s*projects|what\s*has\s*yunus\s*(?:made|built|created|done))|(?:tell\s*me\s*(?:about\s*)?(?:yunus\s*)?projects)|(?:show\s*me\s*(?:yunus\s*)?projects)/i, response: "Yunus has created various projects including a scheduling system using PHP and JavaScript for industrial clients, Mr. Graphy (a Django app visualizing graph data with Neo4j and Plotly), a neural network for classification in pure C++, a KepServerEX REST API client, and many more. Check out his portfolio page to see more!", confidence: 0.95 },
                    { pattern: /^(?:what\s*are\s*yunus\s*skills)|(?:what\s*technologies|what\s*does\s*yunus\s*know)/i, response: "Yunus works with PHP, JavaScript, Python, Django, Neo4j, C++, and focuses on computational intelligence, operational technology, industrial automation, and machine learning. He has over 100 repositories on GitHub and continues learning through courses from Google Cloud, Cisco, and more.", confidence: 0.95 },
                    { pattern: /^(?:where\s*did\s*yunus\s*study)|(?:what\s*is\s*yunus\s*education)|(?:yunus\s*education)/i, response: "Yunus has an A.S. Degree in programming and has studied at Beykoz University, Illinois Institute of Technology, and Anadolu University. He's always learning and has taken courses from Google Cloud, Cisco, and other institutions. He values hands-on learning and experimentation.", confidence: 0.95 },
                    { pattern: /^(?:does\s*(?:he|yunus)\s*(?:like|know|use|work\s*with)\s*(?:c\+\+|php|javascript|python|django|java|react|node))/i, response: "Yes! Yunus works with PHP, JavaScript, Python, Django, Neo4j, C++, and many other technologies. He's created projects using PHP and JavaScript for industrial clients, built neural networks in pure C++, developed Django applications, and more. Check out his portfolio to see specific projects!", confidence: 0.95 },
                    { pattern: /^(?:does\s*(?:he|yunus)\s*(?:like|know|use)\s*(?:c\s*or\s*c\+|c\+\+|\+))/i, response: "Yes! Yunus works with C++. He's built neural networks for classification in pure C++. He works with PHP, JavaScript, Python, Django, Neo4j, C++, and many other technologies. Check out his portfolio to see specific projects!", confidence: 0.95 },
                    { pattern: /^(?:what\s*math|what\s*mathematics|does\s*yunus\s*(?:like|know|study)\s*math)/i, response: "Yunus values strong algorithmic foundations and the math behind computing. He focuses on computational intelligence and the mathematical principles underlying technology. His work involves mathematical concepts in neural networks, algorithms, and computational systems.", confidence: 0.95 },
                    { pattern: /^(?:when\s*(?:was\s*)?yunus\s*(?:born|birth))|(?:yunus\s*(?:was\s*)?born)|(?:birth\s*(?:date|day)\s*(?:of\s*)?yunus)/i, response: "Yunus was born in February 2000.", confidence: 0.95 },
                    { pattern: /^(?:how\s*old\s*(?:is|are)\s*(?:yunus|he))|(?:yunus\s*(?:age|old))|(?:age\s*of\s*yunus)|(?:is\s*(?:he|yunus)\s*(?:a\s*)?2000s?\s*kid)/i, response: "Yunus was born in February 2000, so he's in his mid-20s. Yes, he's a 2000s kid!", confidence: 0.95 },
                    { pattern: /^(?:is\s*yunus\s*(?:turkish|turkey|türkiye))|(?:yunus\s*(?:is\s*)?(?:turkish|from\s*turkey|from\s*türkiye))|(?:is\s*he\s*(?:turkish|from\s*turkey))/i, response: "Yes, Yunus is Turkish! He's based in Istanbul, Türkiye.", confidence: 0.95 },
                    { pattern: /^(?:does\s*(?:he|yunus)\s*(?:have|has)\s*(?:certifications|certificates|certs))|(?:certifications\s*(?:of|for)\s*yunus)|(?:yunus\s*(?:certifications|certificates))/i, response: "Yes! Yunus has taken courses and certifications from Google Cloud, Cisco, and other institutions. He's always learning and values continuous education. Check out his about page for more details on his education and certifications.", confidence: 0.95 },
                    { pattern: /^(?:are\s*you\s*(?:an\s*)?ai|are\s*you\s*(?:a\s*)?bot|are\s*you\s*(?:a\s*)?robot)|(?:you\s*(?:are|is)\s*(?:ai|bot|robot))/i, response: "Yes, I'm YunoBot, an AI assistant created by Yunus to help visitors navigate his website and answer questions about his work and background. I use machine learning to understand natural language!", confidence: 0.95 },
                    { pattern: /^(?:are\s*you\s*(?:chatgpt|gpt|grok|gemini|claude|llm|large\s*language\s*model))|(?:you\s*(?:are|is)\s*(?:chatgpt|gpt|grok|gemini|claude|llm))|(?:is\s*you\s*(?:an\s*)?llm)|(?:are\s*u\s*gpt)/i, response: "No, I'm not ChatGPT, Grok, Gemini, or any other LLM. I'm YunoBot, a specialized AI assistant built specifically for Yunus's website. I use Transformers.js with semantic embeddings (all-MiniLM-L6-v2 model) for understanding, not a large language model. I'm designed to answer questions about Yunus and help navigate his site!", confidence: 0.95 },
                    { pattern: /^(?:what\s*(?:is|are)\s*the\s*difference|what\s*differences|how\s*(?:are|is)\s*you\s*different|compare\s*you|you\s*vs|you\s*versus).*(?:chatgpt|gpt|grok|gemini|claude|llm|other)/i, response: "The main difference: ChatGPT and other LLMs are large language models that generate text. I use semantic embeddings - I convert text into mathematical vectors and compare similarity. Key differences: 1) I don't generate text, I match to predefined answers, 2) I'm much smaller (~25MB vs billions of parameters), 3) I run entirely in your browser (no API calls), 4) I'm specialized for Yunus's site only, 5) I use cosine similarity between vectors, not next-token prediction. Think of me as a smart search engine, not a text generator!", confidence: 0.95 },
                    { pattern: /^(?:so\s*you\s*(?:are|is)\s*(?:an\s*)?lm|you\s*(?:are|is)\s*(?:an\s*)?lm|all[\s-]?minilm|yunus\s*copied|yunus\s*stole|so\s*r\s*u\s*(?:a\s*)?clone|r\s*u\s*(?:a\s*)?clone)/i, response: "No, I'm not an LLM or a clone! all-MiniLM-L6-v2 is an embedding model, not a language model. It converts text to vectors (embeddings) for similarity matching. Yunus didn't copy or steal anything - it's an open-source model from Hugging Face that he integrated using Transformers.js. The difference: LLMs generate text, embedding models convert text to numbers for comparison. I use embeddings to find similar questions, then return predefined answers - I don't generate responses like ChatGPT does.", confidence: 0.95 },
                    { pattern: /^(?:what\s*(?:is|are)\s*minilm|minilm\?|what\s*is\s*minilm|i\s*dont?\s*get\s*it|i\s*dont?\s*understand)/i, response: "MiniLM (all-MiniLM-L6-v2) is a small embedding model from Microsoft. It converts text into 384-dimensional vectors (numbers). When you ask a question, I convert it to a vector, then compare it to vectors of my target questions using cosine similarity. The closest match wins! It's like finding the most similar question in meaning, not exact words. That's why I understand typos and variations - the semantic meaning is preserved in the vector space.", confidence: 0.95 },
                    { pattern: /^(?:how\s*do\s*embeddings\s*work|if\s*you\s*are\s*not\s*an\s*llm|how\s*does\s*embeddings\s*work|explain\s*embeddings|what\s*are\s*embeddings)/i, response: "Embeddings work like this: 1) Your question gets converted to a vector (list of numbers) by MiniLM, 2) I compare this vector to vectors of my target questions using cosine similarity (measures angle between vectors), 3) The closest match (highest similarity) determines my response. It's semantic matching - 'where is your work' and 'show me portfolio' have similar vectors even though words differ. I don't generate text like LLMs - I just find the best matching predefined answer based on meaning similarity!", confidence: 0.95 },
                    { pattern: /^(?:what\s*kind\s*of\s*ai|what\s*type\s*of\s*ai|what\s*are\s*you|how\s*do\s*you\s*work|what\s*technology|what\s*model)/i, response: "I'm YunoBot, powered by Transformers.js using the all-MiniLM-L6-v2 model for semantic understanding. I use a hybrid approach: regex patterns for instant responses and semantic embeddings (vector similarity) for natural language understanding. This lets me handle typos, variations, and conversational queries!", confidence: 0.95 },
                    { pattern: /^(?:what\s*(?:are|is)\s*u|what\s*(?:are|is)\s*r\s*u|what\s*ru|what\s*the\s*fuck\s*(?:are|is)\s*(?:u|r\s*u|you))/i, response: "I'm YunoBot, Yunus's AI assistant! I help visitors navigate his website and answer questions about his work, background, and projects. I use semantic embeddings to understand natural language, so I can handle questions even with typos or casual phrasing. How can I help you?", confidence: 0.9 },
                    { pattern: /^(?:what\s*are\s*you\s*internally|what\s*are\s*u\s*internally|internally\s*what\s*are\s*you|how\s*are\s*you\s*built)/i, response: "Internally, I'm a hybrid system: 1) Regex patterns for instant keyword matching, 2) Transformers.js running all-MiniLM-L6-v2 embedding model in a Web Worker, 3) Cosine similarity comparison between your question's vector and my target question vectors, 4) Predefined answer responses (I don't generate text). The model loads once (~25MB) and runs entirely in your browser. I'm essentially a semantic search engine with predefined answers!", confidence: 0.95 },
                    { pattern: /^(?:do\s*you\s*load|you\s*load|load\s*that|load\s*the\s*(?:l6|lm|model)|how\s*do\s*you\s*load|where\s*do\s*you\s*load)/i, response: "Yes! The all-MiniLM-L6-v2 model loads once when you first visit the page. Transformers.js is loaded locally from the server (with CDN fallback), and the model itself is downloaded from Hugging Face on first use. It runs in a Web Worker so it doesn't block the UI. The model is about 25MB and gets cached in your browser's IndexedDB. Once loaded, it converts text to embeddings instantly. Yunus integrated it using Transformers.js - it's all client-side, no server processing needed!", confidence: 0.95 },
                    { pattern: /^(?:can\s*yunus\s*sing)|(?:does\s*yunus\s*sing)|(?:yunus\s*(?:can|does)\s*sing)/i, response: "Yunus plays guitar, but I'm not sure about his singing abilities! He enjoys playing guitar to unwind. He's more known for his coding and technical work than singing.", confidence: 0.95 },
                    { pattern: /^(?:can\s*you\s*speak\s*(?:turkish|türkçe|english|spanish|french|german|any\s*language))|(?:do\s*you\s*(?:speak|know|understand)\s*(?:turkish|türkçe|english|spanish|french|german|any\s*language))|(?:you\s*(?:can|do)\s*speak)|(?:do\s*you\s*only\s*know\s*(?:english|wneglish|englsih|engilsh))/i, response: "I primarily understand English, but I can process questions in other languages too thanks to semantic embeddings! However, my responses are in English. The semantic model I use (all-MiniLM-L6-v2) is multilingual, so I can understand the meaning even if you ask in Turkish or other languages, but I'll respond in English.", confidence: 0.95 },
                    { pattern: /^(?:is\s*(?:he|yunus)\s*(?:a\s*)?(?:programmer|programer|progrmmer|progamer|coder|developer))|(?:yunus\s*(?:is\s*)?(?:a\s*)?(?:programmer|programer|progrmmer|progamer|coder|developer))|(?:does\s*(?:he|yunus)\s*(?:program|code))/i, response: "Yes! Yunus is a software developer and programmer. He creates projects using PHP, JavaScript, Python, Django, C++, and more. He's also an IT specialist focusing on computational intelligence and operational technology.", confidence: 0.95 },
                    { pattern: /^(?:knock\s*knock|kock\s*kock|knockknock|kockkokc|knockknockk)/i, response: "Who's there? 😊 I'm YunoBot, Yunus's AI assistant. How can I help you today?", confidence: 0.9 },
                    { pattern: /(?:what|tell\s*me).*(?:can|do).*(?:you|yuno|bot)/i, response: "I can help you navigate the site, answer questions about Yunus's work, and guide you to different sections. Try asking me to take you somewhere!", confidence: 0.85 },
                    { pattern: /(?:help|what\s*can|how\s*can)/i, response: "I can help you navigate the site! Try saying 'take me to the about section' or 'show me the portfolio'. What would you like to explore?", confidence: 0.8 },
                    // Generic fallback patterns - low confidence so semantic can override
                    { pattern: /(?:who|what|where|when|why|how).*(?:yunus|you|yuno)/i, response: "I'm YunoBot, Yunus's AI assistant. I can help you navigate the site and answer questions about Yunus's work and projects.", confidence: 0.6 },
                    { pattern: /(?:what|tell\s*me).*(?:about|is).*(?:yunus|this\s*site|website)/i, response: "This is Yunus Emre Vurgun's personal website. He's a software developer and IT specialist. You can explore his portfolio, blog, gallery, and more!", confidence: 0.6 },
                ],
                
                // Turkish Q&A patterns (Turkish responses for Turkish queries)
                turkish_qa: [
                    { pattern: /^(?:kim\s*sin\s*sen|kim\s*bu|kim\s*yunus|kim\s*bu\s*yunus|kimsiniz)/i, response: "Ben YunoBot, Yunus'un AI asistanıyım. Yunus Emre Vurgun, İstanbul'da yaşayan bir yazılım geliştirici ve BT uzmanıdır.", confidence: 0.95 },
                    { pattern: /^(?:yunus\s*nerede|nerede\s*yunus|nerede\s*yaşıyor|yaşıyor\s*nerede|hangi\s*şehir)/i, response: "Yunus İstanbul, Türkiye'de yaşıyor.", confidence: 0.95 },
                    { pattern: /^(?:yunus\s*ne\s*yapar|ne\s*yapıyor|mesleği\s*ne|uzmanlık|ne\s*iş)/i, response: "Yunus yazılım geliştirici ve BT uzmanıdır. Endüstriyel müşteriler için PHP ve JavaScript ile sistemler geliştiriyor, Django ile web uygulamaları yapıyor, saf C++ ile sinir ağları kuruyor ve hesaplamalı zekayla ilgileniyor.", confidence: 0.95 },
                    { pattern: /^(?:proje|projeler|yaptığı|nedir\s*projeler)/i, response: "Yunus'in endüstriyel müşteriler için PHP ve JavaScript ile hazırlanan bir çizelgeleme sistemi, Neo4j ve Plotly ile grafik verilerini görselleştiren bir Django uygulaması (Mr. Graphy), saf C++ ile sınıflandırma için bir sinir ağı ve daha fazlası gibi projeleri var. Detaylar için portfolyo sayfasına göz atabilirsiniz.", confidence: 0.95 },
                    { pattern: /^(?:teknoloji|dili|kullanılan|hangi\s*dil|php|javascript|python|c\+\+|django)/i, response: "Yunus PHP, JavaScript, Python, Django, Neo4j, C++ ve daha birçok teknoloji ile çalışıyor. Endüstriyel müşteriler için PHP ve JavaScript kullanarak projeler geliştirdi, saf C++ ile sinir ağları kurdurdu ve Django uygulamaları geliştirdi.", confidence: 0.95 },
                    { pattern: /^(?:matematik|matematiğe|sayısal|sayı|algoritma|hesaplamalı)/i, response: "Yunus güçlü algoritmik temelleri ve bilgisayarın arkasındaki matematiği değerlendiriyor. Hesaplamalı zeka, sinir ağları ve algoritmalarla ilgileniyor.", confidence: 0.95 },
                    { pattern: /^(?:kayıt|github|depo|repo|açık\s*kaynak)/i, response: "Yunus GitHub'da 100'den fazla deposu var. Kullanıcı adı: github.com/yunusemrejr. Çoğu projesi açık kaynaklıdır.", confidence: 0.95 },
                    { pattern: /^(?:eğitim|okul|üniversite|bölüm|mezun)/i, response: "Yunus programlama alanında A.S. derecesine sahiptir ve Beykoz Üniversitesi, Illinois Institute of Technology ve Anadolu Üniversitesi'nde çalışmalar yapmıştır.", confidence: 0.95 },
                    { pattern: /^(?:sertifika|certificate|google|cisco)/i, response: "Yunus Google Cloud, Cisco ve diğer kurumlardan çeşitli sertifikalar almıştır. Sürekli öğrenmeye önem verir. Detaylar için Hakkında sayfasına göz atabilirsiniz.", confidence: 0.95 },
                    { pattern: /^(?:yaş|kaç\s*yaşında|doğum|doğdu)/i, response: "Yunus Şubat 2000'de doğmuştur, bu yüzden 20'li yaşlarındadır. Evet, bir 2000'li çocuğudur!", confidence: 0.95 },
                    { pattern: /^(?:türk\s*mü|türk\s*müyüm|nerede\s*doğdu|memleket)/i, response: "Evet, Yunus Türk'tür! İstanbul, Türkiye'de yaşıyor.", confidence: 0.95 },
                    { pattern: /^(?:hangi\s*dil\s*konuş|konuş|anla|türkçe\s*mi|ingilizce\s*mi|çoklu\s*dil)/i, response: "Bot şu anda yalnızca İngilizce yanıt verir, ancak anlamsal benzerlik sayesinde Türkçe de içeren soruları anlayabilir. Tamamen tarayıcınızda çalışır.", confidence: 0.9 },
                    { pattern: /^(?:nasılsın|naber|ne\s*haber|nasıl\s*gidiyor|iyi\s*misin)/i, response: "Teşekkür ederim, iyiyim! Size nasıl yardımcı olabilirim? Site hakkında bir şey öğrenmek ister misiniz?", confidence: 0.85 },
                ],

                // Turkish tool-use patterns
                turkish_tools: [
                    { pattern: /^(?:saat|saat\s*kaç|şu\s*an\s*saat|bugün\s*saat|zaman|tarih)/i, intent: 'tool_time', confidence: 0.95 },
                    { pattern: /^(?:hesapla|matematik|topla|çıkar|çarp|böl|kaç\s*edir|hesap)/i, intent: 'tool_calculator', confidence: 0.9 },
                ],

                // Tool patterns
                tools: [
                    { pattern: /^(?:what\s*time|current\s*time|what\s*is\s*the\s*time|time\s*now|what\s*date|today|what\s*day)/i, intent: 'tool_time', confidence: 0.95 },
                    { pattern: /^(?:calculate|calc|compute|math\s*problem|solve|what\s*is\s*\d+|sum|add|subtract|multiply|divide)/i, intent: 'tool_calculator', confidence: 0.9 },
                    { pattern: /^(?:search\s*(?:for|about)?|google|look\s*up|find\s*(?:info|information)?|web\s*search)/i, intent: 'tool_search', confidence: 0.85 },
                ],

                // General conversation
                general: [
                    { pattern: /(?:thanks|thank\s*you|thx|appreciate)/i, response: "You're welcome! Is there anything else I can help with?", confidence: 0.9 },
                    { pattern: /(?:bye|goodbye|see\s*ya|later|farewell)/i, response: "Goodbye! Feel free to come back anytime.", confidence: 0.9 },
                    { pattern: /(?:yes|yeah|yep|sure|ok|okay)/i, response: "Great! What would you like to do?", confidence: 0.7 },
                    { pattern: /(?:no|nope|nah)/i, response: "No problem! Let me know if you need anything else.", confidence: 0.7 },
                ],
            };
        }
        
        initializeNavigationMap() {
            const basePath = window.FULL_BASE_PATH || '';
            return {
                'about': basePath + 'about',
                'portfolio': basePath + 'portfolio',
                'gallery': basePath + 'gallery',
                'blog': basePath + 'blog',
                'travel': basePath + 'travel',
                'updates': basePath + 'updates',
                'post-code': basePath + 'post-code',
                'contact': basePath + 'contact',
                '': basePath || '/',
            };
        }
        
        initializeExampleQuestions() {
            return [
                "Take me to the about section",
                "Show me the portfolio",
                "Who is Yunus?",
                "What does Yunus do?",
                "Where is Yunus from?",
                "What projects has Yunus made?",
                "What technologies does Yunus know?",
                "How old is Yunus?",
                "Can Yunus speak Turkish?",
                "What is YunoBot?",
                "Şu an saat kaç? (What time is it?)",
                "Yunus kimdir? (Who is Yunus?)",
                "Projeleri neler? (What are his projects?)",
            ];
        }

        /**
         * Detect if input is primarily Turkish
         */
        isTurkishInput(input) {
            const lower = input.toLowerCase();
            // Turkish-specific character patterns + common Turkish words
            const turkishIndicators = [
                /[çğıöşü]/, // Turkish characters
                /\b(kim|nerede|ne|nasıl|hangi|kaç|nedir|kimdir|olan|yapar|yaşıyor|proje|teknoloji|matematik|eğitim|sertifika|github|çalışıyor|çalışma|yazılım|geliştirici|endüstriyel|otomasyon|sinir|ağ|yapay|zeka|saat|tarih|dil|konuş|anla|günaydın|merhaba|selam|teşekkür|rica|görüşürüz|hoşça)\b/i,
                /\b(yunus\s*kim|kimsin|nasılsın|naber|ne\s*haber|yaş|kaç|yaşında|türk|istanbul|ankara|izmir)\b/i,
            ];
            
            let score = 0;
            for (const indicator of turkishIndicators) {
                if (indicator.test(lower)) score++;
            }
            return score >= 2 || (/[çğıöşü]/.test(lower) && score >= 1);
        }
        
        /**
         * Initialize typo corrections dictionary for common misspellings
         */
        initializeTypoCorrections() {
            return {
                // Navigation typos
                'protfo': 'portfolio', 'porfo': 'portfolio', 'portoflio': 'portfolio',
                'portofolio': 'portfolio', 'portoflio': 'portfolio', 'portoflio': 'portfolio',
                'foli': 'folio', 'folıo': 'folio', 'follwo': 'follow',
                'gallary': 'gallery', 'gallerie': 'gallery', 'gally': 'gallery',
                'photose': 'photos', 'phtotos': 'photos', 'picturs': 'pictures',
                'pictues': 'pictures', 'pictuers': 'pictures', 'imags': 'images',
                'imgaes': 'images', 'imges': 'images',
                'aboutme': 'about', 'abotu': 'about', 'aboot': 'about',
                'bloge': 'blog', 'blohg': 'blog', 'blg': 'blog', 'blod': 'blog',
                'artciles': 'articles', 'articls': 'articles', 'articals': 'articles',
                'travle': 'travel', 'travell': 'travel', 'travl': 'travel',
                'trips': 'travel', 'trip': 'travel',
                'upadtes': 'updates', 'udpates': 'updates', 'upates': 'updates',
                'newss': 'news', 'newz': 'news',
                'contcat': 'contact', 'contant': 'contact', 'contat': 'contact',
                'contatc': 'contact', 'contanct': 'contact', 'reacch': 'reach',
                'reachh': 'reach', 'emial': 'email', 'eamil': 'email',
                // Name typos
                'yunuse': 'yunus', 'yuns': 'yunus', 'yunes': 'yunus',
                'yunous': 'yunus', 'younus': 'yunus', 'yonus': 'yunus',
                'yunusm': 'yunus', 'yunusemre': 'yunus',
                // Technology typos
                'javscript': 'javascript', 'javasript': 'javascript',
                'jvscript': 'javascript', 'js': 'javascript',
                'pyhton': 'python', 'pyton': 'python', 'phyton': 'python',
                'pythn': 'python', 'pythin': 'python',
                'djanog': 'django', 'djang': 'django', 'djano': 'django',
                'c++': 'c++', 'cplus': 'c++', 'c plus plus': 'c++',
                'cpp': 'c++', 'c+': 'c++',
                'phh': 'php', 'pjp': 'php', 'phtp': 'php',
                'neoo4j': 'neo4j', 'neo4': 'neo4j', 'neo4jj': 'neo4j',
                // Common word typos
                'proejct': 'project', 'probject': 'project', 'prohect': 'project',
                'projecct': 'project', 'projec': 'project', 'proje': 'project',
                'projct': 'project', 'prohect': 'project',
                'teh': 'the', 'hte': 'the', 'th': 'the',
                'waht': 'what', 'wath': 'what', 'whta': 'what',
                'wher': 'where', 'wheer': 'where', 'whe': 'where',
                'wich': 'which', 'whihc': 'which', 'whch': 'which',
                'howw': 'how', 'ho': 'how', 'hw': 'how',
                'doe': 'does', 'dos': 'does', 'deso': 'does',
                'knw': 'know', 'kno': 'know', 'konw': 'know',
                'liks': 'like', 'lik': 'like', 'lkke': 'like',
                'us': 'use', 'ues': 'use', 'uuse': 'use',
                'wokr': 'work', 'wrok': 'work', 'owrk': 'work',
                'devloper': 'developer', 'develper': 'developer',
                'develpoer': 'developer', 'devlopr': 'developer',
                'programer': 'programmer', 'progrmmer': 'programmer',
                'progamer': 'programmer', 'porgrammer': 'programmer',
                // AI/Bot typos
                'chaatgpt': 'chatgpt', 'chatgptt': 'chatgpt', 'chagpt': 'chatgpt',
                'gptt': 'gpt', 'gpt4': 'gpt', 'gtp': 'gpt',
                'claudee': 'claude', 'claude2': 'claude', 'cluaude': 'claude',
                'gemini': 'gemini', 'gemin': 'gemini', 'gemni': 'gemini',
                'grok': 'grok', 'grok2': 'grok', 'grokai': 'grok',
                'llm': 'llm', 'llms': 'llm', 'llmmodel': 'llm',
                'embeding': 'embedding', 'embeddings': 'embedding',
                'embedd': 'embedding', 'embdding': 'embedding',
                'minilm': 'minilm', 'minilm-l6': 'minilm', 'minilm l6': 'minilm',
                'transformers': 'transformers', 'transformer': 'transformers',
                'transformes': 'transformers', 'tranformers': 'transformers',
                // Question typos
                'wwhat': 'what', 'whaat': 'what', 'whatt': 'what',
                'wwhere': 'where', 'wheree': 'where', 'wheere': 'where',
                'hhow': 'how', 'howw': 'how', 'hwo': 'how',
                'wwho': 'who', 'whoo': 'who', 'wh': 'who',
                'wwhen': 'when', 'when': 'when', 'whn': 'when',
                // Misc
                'certifcate': 'certification', 'certifcate': 'certification',
                'certificte': 'certification', 'certifcate': 'certification',
                'certifcate': 'certification', 'cert': 'certification',
                'certs': 'certification', 'certifcates': 'certification',
                'educaton': 'education', 'educatio': 'education',
                'edcuation': 'education', 'eduction': 'education',
                'natonality': 'nationality', 'nationalty': 'nationality',
                'natonality': 'nationality', 'naitonality': 'nationality',
                // Common phrases
                'take me to': 'take me to', 'takeme to': 'take me to',
                'takeme': 'take me', 'takemeto': 'take me to',
                'show me': 'show me', 'showme': 'show me',
                'showem': 'show me', 'shw me': 'show me',
                'go to': 'go to', 'goto': 'go to', 'gto': 'go to',
                'navigate to': 'navigate to', 'navigateto': 'navigate to',
                'navigateto': 'navigate to', 'navgate': 'navigate',
            };
        }
        
        /**
         * Initialize semantic targets - goal sentences for navigation and Q&A
         * These are used for semantic matching when regex doesn't match
         */
        initializeSemanticTargets() {
            return {
                // Navigation targets (expanded with more variations)
                navigation: [
                    {
                        target: '',
                        sentences: [
                            'go to home page',
                            'take me to the main page',
                            'show me the landing page',
                            'navigate to homepage',
                            'back to home',
                            'return to home',
                            'front page',
                            'start page',
                            'index page',
                            'home screen',
                            'main screen',
                            'first page',
                            'home please',
                            'show home',
                            'open home',
                            'go home',
                            'take me home',
                            'bring me home',
                            'home now',
                            'main page now',
                        ],
                    },
                    {
                        target: 'about',
                        sentences: [
                            'show me about page',
                            'take me to about section',
                            'where can I learn about Yunus',
                            'about Yunus information',
                            'tell me about yunus',
                            'yunus about page',
                            'yunus profile',
                            'yunus bio',
                            'who is yunus page',
                            'yunus background',
                            'yunus information',
                            'yunus details',
                            'about him',
                            'about yunus emre',
                            'yunus emre vurgun about',
                            'learn about yunus',
                            'read about yunus',
                            'yunus story',
                            'yunus personal info',
                            'yunus personal page',
                            'open about',
                            'go to about',
                            'navigate to about',
                            'about section please',
                            'show about',
                            'view about',
                        ],
                    },
                    {
                        target: 'portfolio',
                        sentences: [
                            'show me portfolio',
                            'take me to work page',
                            'where is your work',
                            'show me your projects',
                            'display portfolio',
                            'portfolio page',
                            'go to portfolio',
                            'open portfolio',
                            'see portfolio',
                            'view portfolio',
                            'show portfolio',
                            'yunus projects',
                            'yunus work',
                            'yunus portfolio',
                            'project showcase',
                            'work showcase',
                            'case studies',
                            'project list',
                            'work list',
                            'portfolio items',
                            'project archive',
                            'work archive',
                            'what has yunus built',
                            'yunus creations',
                            'yunus builds',
                            'yunus code projects',
                            'software projects',
                            'programming projects',
                            'development work',
                            'professional work',
                            'client work',
                            'industrial projects',
                            'portfolio now',
                            'show me what yunus made',
                            'show yunus work',
                            'navigate to portfolio',
                            'take me to portfolio',
                        ],
                    },
                    {
                        target: 'gallery',
                        sentences: [
                            'show me gallery',
                            'take me to photos',
                            'where are the pictures',
                            'show me images',
                            'pics plz',
                            'display gallery',
                            'photo gallery',
                            'image gallery',
                            'picture gallery',
                            'yunus photos',
                            'yunus pictures',
                            'yunus images',
                            'photo collection',
                            'image collection',
                            'picture collection',
                            'view photos',
                            'see photos',
                            'browse photos',
                            'browse images',
                            'browse pictures',
                            'look at photos',
                            'look at images',
                            'photo album',
                            'image album',
                            'gallery now',
                            'open gallery',
                            'go to gallery',
                            'navigate to gallery',
                            'show me the pictures',
                            'show me some photos',
                            'pics',
                            'photos',
                            'pictures',
                            'images',
                        ],
                    },
                    {
                        target: 'blog',
                        sentences: [
                            'show me blog',
                            'take me to blog posts',
                            'where are the articles',
                            'show me blog page',
                            'display blog',
                            'yunus blog',
                            'yunus articles',
                            'yunus posts',
                            'yunus writing',
                            'yunus journal',
                            'read blog',
                            'read articles',
                            'read posts',
                            'blog articles',
                            'blog posts',
                            'blog entries',
                            'article list',
                            'post list',
                            'writing section',
                            'long form content',
                            'technical writing',
                            'tech blog',
                            'engineering blog',
                            'code blog',
                            'programming blog',
                            'blog now',
                            'open blog',
                            'go to blog',
                            'navigate to blog',
                            'show me the blog',
                            'articles',
                            'posts',
                            'writings',
                        ],
                    },
                    {
                        target: 'travel',
                        sentences: [
                            'show me travel page',
                            'take me to travel section',
                            'where are travel photos',
                            'show travel journeys',
                            'yunus travel',
                            'yunus travels',
                            'yunus trips',
                            'yunus journeys',
                            'travel photos',
                            'travel pictures',
                            'travel images',
                            'countries visited',
                            'places visited',
                            'travel destinations',
                            'travel map',
                            'world map',
                            'travel log',
                            'travel diary',
                            'travel stories',
                            'travel experiences',
                            'travel adventures',
                            'travel section',
                            'travel page',
                            'travel now',
                            'open travel',
                            'go to travel',
                            'navigate to travel',
                            'show me travel',
                            'where has yunus been',
                            'which countries has yunus visited',
                            'yunus country visits',
                            'travel destinations yunus',
                        ],
                    },
                    {
                        target: 'updates',
                        sentences: [
                            'show me updates',
                            'take me to news',
                            'where are the updates',
                            'show changelog',
                            'yunus updates',
                            'yunus news',
                            'yunus changelog',
                            'recent updates',
                            'latest updates',
                            'new updates',
                            'update log',
                            'change log',
                            'activity log',
                            'activity feed',
                            'news feed',
                            'update feed',
                            'micro blog',
                            'micro updates',
                            'short updates',
                            'quick updates',
                            'status updates',
                            'what is new',
                            'whats new',
                            'new stuff',
                            'recent activity',
                            'latest activity',
                            'updates now',
                            'open updates',
                            'go to updates',
                            'navigate to updates',
                            'show me the updates',
                            'news',
                            'changelog',
                        ],
                    },
                    {
                        target: 'post-code',
                        sentences: [
                            'show me code snippets',
                            'take me to code page',
                            'where is the code',
                            'show post code',
                            'yunus code',
                            'yunus snippets',
                            'yunus post code',
                            'code snippets',
                            'code samples',
                            'code examples',
                            'programming snippets',
                            'programming examples',
                            'algorithm examples',
                            'math code',
                            'mathematical code',
                            'computational code',
                            'theory code',
                            'architecture code',
                            'fundamentals',
                            'fundamental concepts',
                            'core concepts',
                            'basic concepts',
                            'first principles',
                            'post code section',
                            'post code page',
                            'post-code now',
                            'open post code',
                            'go to post code',
                            'navigate to post-code',
                            'show me post code',
                            'code page',
                            'snippets page',
                        ],
                    },
                    {
                        target: 'contact',
                        sentences: [
                            'show me contact page',
                            'take me to contact',
                            'how to reach you',
                            'show contact information',
                            'yunus contact',
                            'yunus email',
                            'yunus phone',
                            'contact yunus',
                            'reach yunus',
                            'contact information',
                            'contact details',
                            'contact form',
                            'email address',
                            'email yunus',
                            'message yunus',
                            'send message',
                            'get in touch',
                            'reach out',
                            'contact form',
                            'contact page',
                            'contact section',
                            'contact now',
                            'open contact',
                            'go to contact',
                            'navigate to contact',
                            'show me contact',
                            'how can i contact yunus',
                            'how to reach yunus',
                            'how to email yunus',
                            'yunus social media',
                            'linkedin',
                            'github contact',
                        ],
                    },
                ],
                // Q&A targets with responses (expanded with more variations)
                qa: [
                    {
                        intent: 'who_is_yunus',
                        sentences: [
                            'who is he',
                            'who is yunus',
                            'who are you',
                            'tell me about yunus',
                            'what is yunus',
                            'yunus is who',
                            'who is this person',
                            'tell me who yunus is',
                            'who is yunus emre vurgun',
                            'who is the owner of this site',
                            'who runs this website',
                            'who made this site',
                            'who created this website',
                            'yunus emre vurgun who is',
                            'yunus who',
                            'he is who',
                            'this person who',
                            'identity of yunus',
                            'yunus identity',
                            'about yunus emre vurgun',
                            'yunus emre who',
                        ],
                        response: "I'm YunoBot, Yunus's AI assistant. Yunus Emre Vurgun (also known as Yµn ^…^ ƒ(x)) is a software developer and IT specialist based in Istanbul, Türkiye. He writes code that helps industries work better and brings new ideas to life, focusing on computational intelligence and operational technology.",
                    },
                    {
                        intent: 'what_does_yunus_do',
                        sentences: [
                            'what does yunus do',
                            'yunus does what',
                            'what is yunus job',
                            'what does he do',
                            'what is his profession',
                            'what does yunus work on',
                            'what is yunus occupation',
                            'what does yunus do for work',
                            'what is yunus job title',
                            'what does yunus do for a living',
                            'yunus profession',
                            'yunus career',
                            'yunus work',
                            'what is his job',
                            'what kind of work does yunus do',
                            'what type of work',
                            'describe yunus work',
                            'yunus does what for work',
                            'what does yunus build',
                            'what does yunus create',
                            'yunus specialty',
                            'yunus specialization',
                            'yunus focus area',
                        ],
                        response: "Yunus is a software developer and IT specialist. He creates projects like scheduling systems for industrial clients, develops web applications with Django, builds neural networks, and works on computational intelligence and operational technology. Most of his work is open-source and available on GitHub.",
                    },
                    {
                        intent: 'where_is_yunus',
                        sentences: [
                            'where is yunus',
                            'where does yunus live',
                            'where is he from',
                            'where is he located',
                            'where is he',
                            'what city is yunus in',
                            'what country is yunus in',
                            'yunus location',
                            'yunus city',
                            'yunus country',
                            'which city does yunus live in',
                            'which country does yunus live in',
                            'where does he reside',
                            'where is yunus based',
                            'base of yunus',
                            'yunus residence',
                            'yunus is located in',
                            'he lives in',
                            'yunus lives where',
                        ],
                        response: "Yunus is based in Istanbul, Türkiye.",
                    },
                    {
                        intent: 'yunus_travel',
                        sentences: [
                            'where did yunus go',
                            'which countries has yunus been to',
                            'which countries has yunus visited',
                            'how many countries has yunus visited',
                            'where has yunus traveled',
                            'yunus travel',
                            'countries yunus visited',
                            'yunus visited countries',
                            'yunus travel history',
                            'yunus travel experience',
                            'yunus places visited',
                            'yunus destinations',
                            'yunus trips',
                            'yunus journeys',
                            'yunus travels',
                            'countries yunus has been to',
                            'list of countries yunus visited',
                            'how many places has yunus been',
                            'yunus world travel',
                            'yunus international travel',
                            'yunus travel log',
                            'yunus travel diary',
                            'where has he traveled',
                            'where has he been',
                            'has yunus traveled much',
                            'does yunus travel a lot',
                            'yunus is a traveler',
                        ],
                        response: "Yunus has visited 21 countries, from Tanzania to Boston. He enjoys traveling and has explored many places around the world. Check out his travel page to see more about his journeys!",
                    },
                    {
                        intent: 'yunus_projects',
                        sentences: [
                            'what projects has yunus made',
                            'what has yunus built',
                            'what are yunus projects',
                            'what work has yunus done',
                            'show me yunus work',
                            'what has yunus created',
                            'yunus creations',
                            'yunus builds',
                            'yunus made what',
                            'yunus project list',
                            'list yunus projects',
                            'describe yunus projects',
                            'tell me about yunus projects',
                            'yunus notable projects',
                            'yunus best projects',
                            'yunus major projects',
                            'what has he built',
                            'what has he created',
                            'what has he made',
                            'his projects',
                            'his work',
                            'his creations',
                            'yunus software projects',
                            'yunus programming projects',
                            'yunus development projects',
                            'yunus industrial projects',
                            'yunus client projects',
                        ],
                        response: "Yunus has created various projects including a scheduling system using PHP and JavaScript for industrial clients, Mr. Graphy (a Django app visualizing graph data with Neo4j and Plotly), a neural network for classification in pure C++, a KepServerEX REST API client, and many more. Check out his portfolio page to see more!",
                    },
                    {
                        intent: 'yunus_skills',
                        sentences: [
                            'what are yunus skills',
                            'what can yunus do',
                            'what technologies does yunus know',
                            'what does yunus know',
                            'what is yunus good at',
                            'yunus abilities',
                            'yunus capabilities',
                            'yunus expertise',
                            'yunus strengths',
                            'yunus technical skills',
                            'yunus skillset',
                            'yunus competencies',
                            'what is yunus expert in',
                            'what is yunus specialized in',
                            'yunus areas of expertise',
                            'yunus strong points',
                            'yunus key skills',
                            'yunus core skills',
                            'yunus main skills',
                            'what yunus is good at',
                            'what yunus excels at',
                            'yunus talents',
                        ],
                        response: "Yunus works with PHP, JavaScript, Python, Django, Neo4j, C++, and focuses on computational intelligence, operational technology, industrial automation, and machine learning. He has over 100 repositories on GitHub and continues learning through courses from Google Cloud, Cisco, and more.",
                    },
                    {
                        intent: 'yunus_education',
                        sentences: [
                            'where did yunus study',
                            'what is yunus education',
                            'where did yunus go to school',
                            'what is yunus educational background',
                            'yunus education',
                            'yunus degree',
                            'yunus university',
                            'yunus college',
                            'yunus school',
                            'yunus academic background',
                            'yunus academic history',
                            'yunus studies',
                            'yunus learning',
                            'yunus formal education',
                            'yunus qualifications',
                            'yunus educational qualifications',
                            'what degree does yunus have',
                            'what did yunus study',
                            'what did he study',
                            'where did he study',
                            'his education',
                            'his degree',
                            'his university',
                            'yunus as degree',
                            'yunus beykoz university',
                            'yunus illinois institute',
                            'yunus anadolu university',
                        ],
                        response: "Yunus has an A.S. Degree in programming and has studied at Beykoz University, Illinois Institute of Technology, and Anadolu University. He's always learning and has taken courses from Google Cloud, Cisco, and other institutions. He values hands-on learning and experimentation.",
                    },
                    {
                        intent: 'yunus_interests',
                        sentences: [
                            'what does yunus like',
                            'what are yunus hobbies',
                            'what does yunus enjoy',
                            'what interests yunus',
                            'yunus interests',
                            'yunus hobbies',
                            'yunus pastimes',
                            'yunus leisure activities',
                            'yunus free time',
                            'yunus spare time',
                            'yunus passions',
                            'yunus passion',
                            'yunus likes',
                            'yunus favorite things',
                            'what does he like',
                            'what does he enjoy',
                            'what are his hobbies',
                            'what are his interests',
                            'his interests',
                            'his hobbies',
                            'his passions',
                            'yunus outside of work',
                            'yunus personal interests',
                            'yunus personal life',
                            'what yunus does for fun',
                            'yunus fun activities',
                        ],
                        response: "Yunus enjoys coding, learning new technologies, playing guitar, and traveling. He's visited 21 countries, from Tanzania to Boston. He also creates content on Odysee about neural networks and writes blog posts. He values strong algorithmic foundations and the math behind computing.",
                    },
                    {
                        intent: 'yunus_github',
                        sentences: [
                            'where is yunus github',
                            'yunus github',
                            'what is yunus github',
                            'github yunus',
                            'yunus github profile',
                            'yunus github account',
                            'yunus github page',
                            'yunus github link',
                            'yunus github url',
                            'yunus github repository',
                            'yunus repositories',
                            'yunus repo',
                            'yunus repos',
                            'yunus open source',
                            'yunus open source work',
                            'yunus github username',
                            'yunus github handle',
                            'github.com yunus',
                            'github yunusemrejr',
                            'yunusemrejr github',
                            'yunus code github',
                            'yunus projects github',
                            'yunus github contributions',
                            'how many repos does yunus have',
                            'yunus github stats',
                        ],
                        response: "Yunus has over 100 repositories on GitHub. You can find him at github.com/yunusemrejr. Most of his work is open-source and includes tools for automation, neural networks, and various programming projects.",
                    },
                    {
                        intent: 'yunus_technologies',
                        sentences: [
                            'does yunus know php',
                            'does yunus use php',
                            'does yunus know javascript',
                            'does yunus know python',
                            'does yunus know c++',
                            'does yunus know django',
                            'does yunus like php',
                            'does yunus like javascript',
                            'does yunus like python',
                            'does yunus like c++',
                            'does yunus use c++',
                            'does he like c++',
                            'does he know c++',
                            'does he use c++',
                            'does he like php',
                            'does he know php',
                            'does he like python',
                            'does he know python',
                            'what languages does yunus know',
                            'what programming languages',
                            'yunus c++',
                            'yunus php',
                            'yunus python',
                            'yunus javascript',
                            'yunus django',
                            'yunus neo4j',
                            'yunus java',
                            'yunus react',
                            'yunus node',
                            'yunus tech stack',
                            'yunus technology stack',
                            'yunus programming languages',
                            'yunus frameworks',
                            'yunus tools',
                            'yunus technologies used',
                            'what tech does yunus use',
                            'what tools does yunus use',
                            'what frameworks does yunus use',
                            'what languages does he know',
                            'what can yunus program in',
                            'what does yunus code in',
                            'yunus coding languages',
                            'yunus development tools',
                        ],
                        response: "Yes! Yunus works with PHP, JavaScript, Python, Django, Neo4j, C++, and many other technologies. He's created projects using PHP and JavaScript for industrial clients, built neural networks in pure C++, developed Django applications, and more. Check out his portfolio to see specific projects!",
                    },
                    {
                        intent: 'yunus_math',
                        sentences: [
                            'what math',
                            'what mathematics',
                            'does yunus like math',
                            'does yunus know math',
                            'what math does yunus know',
                            'yunus math',
                            'yunus mathematics',
                            'yunus mathematical skills',
                            'yunus math background',
                            'yunus math knowledge',
                            'yunus and math',
                            'yunus math ability',
                            'is yunus good at math',
                            'does yunus use math',
                            'math in yunus work',
                            'mathematics in yunus work',
                            'mathematical yunus',
                            'yunus algorithmic thinking',
                            'yunus algorithmic skills',
                            'yunus algorithmic foundation',
                            'yunus computational thinking',
                            'yunus math skills',
                        ],
                        response: "Yunus values strong algorithmic foundations and the math behind computing. He focuses on computational intelligence and the mathematical principles underlying technology. His work involves mathematical concepts in neural networks, algorithms, and computational systems.",
                    },
                    {
                        intent: 'yunus_birth_age',
                        sentences: [
                            'when was yunus born',
                            'when was he born',
                            'yunus birth date',
                            'yunus birthday',
                            'how old is yunus',
                            'how old is he',
                            'yunus age',
                            'is yunus a 2000s kid',
                            'is he a 2000s kid',
                            'yunus birth year',
                            'yunus year of birth',
                            'yunus date of birth',
                            'yunus born when',
                            'yunus was born',
                            'he was born',
                            'yunus birth',
                            'yunus born',
                            'yunus age now',
                            'yunus current age',
                            'yunus is how old',
                            'yunus is years old',
                            'yunus generation',
                            'yunus gen z',
                            'yunus millennial',
                            'yunus born in 2000',
                            'yunus february 2000',
                            'yunus birthday month',
                        ],
                        response: "Yunus was born in February 2000, so he's in his mid-20s. Yes, he's a 2000s kid!",
                    },
                    {
                        intent: 'yunus_nationality',
                        sentences: [
                            'is yunus turkish',
                            'is he turkish',
                            'is yunus from turkey',
                            'is yunus from turkiye',
                            'yunus nationality',
                            'where is yunus from',
                            'yunus is turkish',
                            'yunus turkey',
                            'yunus turkiye',
                            'yunus istanbul',
                            'yunus is from istanbul',
                            'yunus is from turkey',
                            'yunus is from turkiye',
                            'yunus citizenship',
                            'yunus ethnic background',
                            'yunus origin',
                            'yunus origins',
                            'yunus heritage',
                            'yunus national origin',
                            'yunus is a turk',
                            'yunus turkish citizen',
                            'yunus nationality turkish',
                            'yunus from which country',
                            'yunus from which nation',
                            'yunus istanbul turkey',
                            'yunus istanbul turkiye',
                        ],
                        response: "Yes, Yunus is Turkish! He's based in Istanbul, Türkiye.",
                    },
                    {
                        intent: 'yunus_certifications',
                        sentences: [
                            'does yunus have certifications',
                            'does he have certifications',
                            'yunus certifications',
                            'what certifications does yunus have',
                            'yunus certificates',
                            'yunus certs',
                            'yunus certification list',
                            'yunus professional certifications',
                            'yunus professional certificates',
                            'yunus google cloud certification',
                            'yunus cisco certification',
                            'yunus google cert',
                            'yunus cisco cert',
                            'yunus courses',
                            'yunus online courses',
                            'yunus training',
                            'yunus professional training',
                            'yunus certifications and courses',
                            'yunus credentials',
                            'yunus professional credentials',
                            'yunus qualifications',
                            'yunus professional qualifications',
                            'yunus licensed',
                            'yunus certified',
                            'is yunus certified',
                            'is yunus certified in anything',
                            'yunus google cloud',
                            'yunus cisco',
                        ],
                        response: "Yes! Yunus has taken courses and certifications from Google Cloud, Cisco, and other institutions. He's always learning and values continuous education. Check out his about page for more details on his education and certifications.",
                    },
                    {
                        intent: 'bot_identity',
                        sentences: [
                            'are you an ai',
                            'are you a bot',
                            'are you a robot',
                            'what are you',
                            'who are you',
                            'you are ai',
                            'you are a bot',
                            'you are an ai',
                            'you are ai assistant',
                            'you are yunobot',
                            'you are yunus bot',
                            'you are the bot',
                            'you are the assistant',
                            'you are the ai',
                            'what is your name',
                            'your name',
                            'what should i call you',
                            'who made you',
                            'who created you',
                            'who built you',
                            'who developed you',
                            'you were made by',
                            'you were created by',
                            'you were built by',
                            'are you real',
                            'are you human',
                            'are you a person',
                            'are you alive',
                            'are you conscious',
                        ],
                        response: "Yes, I'm YunoBot, an AI assistant created by Yunus to help visitors navigate his website and answer questions about his work and background. I use machine learning to understand natural language!",
                    },
                    {
                        intent: 'bot_technology',
                        sentences: [
                            'what kind of ai are you',
                            'what type of ai',
                            'what kind of ai',
                            'how do you work',
                            'what technology do you use',
                            'what model are you',
                            'what ai model',
                            'what ai are you',
                            'what powers you',
                            'what runs you',
                            'what drives you',
                            'what engine',
                            'what algorithm',
                            'what algorithms',
                            'what is your technology',
                            'what is your model',
                            'what is your ai',
                            'what is your system',
                            'what is your architecture',
                            'what is your tech stack',
                            'what framework',
                            'what library',
                            'what is behind you',
                            'how are you powered',
                            'how are you running',
                            'what software',
                            'what platform',
                        ],
                        response: "I'm YunoBot, powered by Transformers.js using the all-MiniLM-L6-v2 model for semantic understanding. I use a hybrid approach: regex patterns for instant responses and semantic embeddings (vector similarity) for natural language understanding. This lets me handle typos, variations, and conversational queries!",
                    },
                    {
                        intent: 'bot_llm',
                        sentences: [
                            'are you chatgpt',
                            'are you gpt',
                            'are you grok',
                            'are you gemini',
                            'are you claude',
                            'are you an llm',
                            'are you a large language model',
                            'is you an llm',
                            'you are llm',
                            'are u gpt',
                            'are you gpt4',
                            'are you gpt-4',
                            'are you chat gpt',
                            'are you openai',
                            'are you from openai',
                            'are you from google',
                            'are you from anthropic',
                            'are you from xai',
                            'are you from meta',
                            'are you deepseek',
                            'are you mistral',
                            'are you qwen',
                            'are you perplexity',
                            'are you a transformer',
                            'are you based on gpt',
                            'are you using gpt',
                            'are you using chatgpt',
                            'do you use gpt',
                            'do you use chatgpt',
                            'do you use openai',
                            'is this chatgpt',
                            'is this gpt',
                            'this is chatgpt',
                            'this is gpt',
                        ],
                        response: "No, I'm not ChatGPT, Grok, Gemini, or any other LLM. I'm YunoBot, a specialized AI assistant built specifically for Yunus's website. I use Transformers.js with semantic embeddings (all-MiniLM-L6-v2 model) for understanding, not a large language model. I'm designed to answer questions about Yunus and help navigate his site!",
                    },
                    {
                        intent: 'bot_difference',
                        sentences: [
                            'what is the difference between you and chatgpt',
                            'what is the difference between you and other',
                            'how are you different from chatgpt',
                            'compare you and chatgpt',
                            'you vs chatgpt',
                            'difference between you and llm',
                            'difference between you and gpt',
                            'difference between you and ai',
                            'you versus chatgpt',
                            'you versus gpt',
                            'you compared to chatgpt',
                            'you compared to gpt',
                            'you compared to llm',
                            'how are you different from gpt',
                            'how are you different from ai',
                            'how are you different from other ai',
                            'how are you different from other bots',
                            'how are you different from other assistants',
                            'what makes you different',
                            'what makes you special',
                            'what makes you unique',
                            'why are you different',
                            'are you better than chatgpt',
                            'are you better than gpt',
                            'are you like chatgpt',
                            'are you similar to chatgpt',
                            'are you the same as chatgpt',
                        ],
                        response: "The main difference: ChatGPT and other LLMs are large language models that generate text. I use semantic embeddings - I convert text into mathematical vectors and compare similarity. Key differences: 1) I don't generate text, I match to predefined answers, 2) I'm much smaller (~25MB vs billions of parameters), 3) I run entirely in your browser (no API calls), 4) I'm specialized for Yunus's site only, 5) I use cosine similarity between vectors, not next-token prediction. Think of me as a smart search engine, not a text generator!",
                    },
                    {
                        intent: 'bot_model_clarification',
                        sentences: [
                            'so you are an lm',
                            'you are an lm called',
                            'yunus copied',
                            'yunus stole',
                            'all minilm',
                            'so r u a clone',
                            'r u a clone',
                            'you are a clone',
                            'you are cloned',
                            'you copied chatgpt',
                            'you stole code',
                            'yunus copied code',
                            'yunus stole code',
                            'this is a copy',
                            'this is stolen',
                            'this is plagiarized',
                            'you are not original',
                            'you are derivative',
                            'you are based on',
                            'you are a fork',
                            'you are a copy',
                            'lm or not',
                            'language model or not',
                            'are you a language model',
                            'so you are not an llm',
                            'so what are you then',
                            'then what are you',
                            'what are you if not llm',
                            'if not llm then what',
                        ],
                        response: "No, I'm not an LLM or a clone! all-MiniLM-L6-v2 is an embedding model, not a language model. It converts text to vectors (embeddings) for similarity matching. Yunus didn't copy or steal anything - it's an open-source model from Hugging Face that he integrated using Transformers.js. The difference: LLMs generate text, embedding models convert text to numbers for comparison. I use embeddings to find similar questions, then return predefined answers - I don't generate responses like ChatGPT does.",
                    },
                    {
                        intent: 'bot_minilm_explanation',
                        sentences: [
                            'what is minilm',
                            'minilm',
                            'what is minilm i dont get it',
                            'i dont understand minilm',
                            'explain minilm',
                            'tell me about minilm',
                            'what does minilm mean',
                            'what is all minilm l6 v2',
                            'all minilm l6 v2',
                            'minilm l6 v2',
                            'minilm model',
                            'minilm embedding',
                            'minilm vector',
                            'minilm from microsoft',
                            'minilm explained',
                            'minilm details',
                            'minilm information',
                            'what is the minilm model',
                            'what is the minilm',
                            'describe minilm',
                            'minilm what is it',
                            'minilm meaning',
                            'minilm definition',
                            'minilm overview',
                            'minilm basics',
                            'how does minilm work',
                        ],
                        response: "MiniLM (all-MiniLM-L6-v2) is a small embedding model from Microsoft. It converts text into 384-dimensional vectors (numbers). When you ask a question, I convert it to a vector, then compare it to vectors of my target questions using cosine similarity. The closest match wins! It's like finding the most similar question in meaning, not exact words. That's why I understand typos and variations - the semantic meaning is preserved in the vector space.",
                    },
                    {
                        intent: 'bot_embeddings_explanation',
                        sentences: [
                            'how does embeddings work',
                            'if you are not an llm how does embeddings work',
                            'how do embeddings work',
                            'explain embeddings',
                            'what are embeddings',
                            'tell me about embeddings',
                            'what is an embedding',
                            'what is embedding',
                            'embedding meaning',
                            'embedding definition',
                            'embedding explained',
                            'embedding basics',
                            'embedding details',
                            'how do vectors work',
                            'what are vectors',
                            'what is a vector',
                            'vector meaning',
                            'vector in ml',
                            'semantic vector',
                            'semantic embedding',
                            'semantic similarity',
                            'vector similarity',
                            'cosine similarity',
                            'what is cosine similarity',
                            'how does similarity work',
                            'how do you compare',
                            'how do you match',
                            'how do you understand',
                            'how do you process text',
                            'how do you process questions',
                            'text to vector',
                            'text to numbers',
                            'text to embeddings',
                        ],
                        response: "Embeddings work like this: 1) Your question gets converted to a vector (list of numbers) by MiniLM, 2) I compare this vector to vectors of my target questions using cosine similarity (measures angle between vectors), 3) The closest match (highest similarity) determines my response. It's semantic matching - 'where is your work' and 'show me portfolio' have similar vectors even though words differ. I don't generate text like LLMs - I just find the best matching predefined answer based on meaning similarity!",
                    },
                    {
                        intent: 'bot_identity_casual',
                        sentences: [
                            'what are u',
                            'what is u',
                            'what r u',
                            'what ru',
                            'what the fuck are u',
                            'what the hell are you',
                            'wtf are you',
                            'wth are you',
                            'what the heck are you',
                            'what r u man',
                            'what are u dude',
                            'what is this bot',
                            'what is this thing',
                            'what is this ai',
                            'what is this assistant',
                            'what is yunobot',
                            'yunobot what is',
                            'yunobot explain',
                            'yunobot tell me',
                            'yunobot who are you',
                            'yunobot what are you',
                            'yo what are you',
                            'hey what are you',
                            'sup what are you',
                            'ok what are you',
                            'so what are you',
                            'alright what are you',
                        ],
                        response: "I'm YunoBot, Yunus's AI assistant! I help visitors navigate his website and answer questions about his work, background, and projects. I use semantic embeddings to understand natural language, so I can handle questions even with typos or casual phrasing. How can I help you?",
                    },
                    {
                        intent: 'bot_internal',
                        sentences: [
                            'what are you internally',
                            'how are you built',
                            'what are you made of',
                            'internally what are you',
                            'how do you work internally',
                            'what is your internal structure',
                            'what is inside you',
                            'what is under the hood',
                            'under the hood',
                            'your internals',
                            'your internal workings',
                            'your inner workings',
                            'your architecture',
                            'your system architecture',
                            'your technical architecture',
                            'your code structure',
                            'your implementation',
                            'your design',
                            'your system design',
                            'how are you implemented',
                            'how are you designed',
                            'how are you structured',
                            'how are you constructed',
                            'what components',
                            'what modules',
                            'what layers',
                            'what technologies inside',
                            'what is your tech',
                            'your tech internals',
                            'break down your architecture',
                            'explain your architecture',
                            'describe your internals',
                        ],
                        response: "Internally, I'm a hybrid system: 1) Regex patterns for instant keyword matching, 2) Transformers.js running all-MiniLM-L6-v2 embedding model in a Web Worker, 3) Cosine similarity comparison between your question's vector and my target question vectors, 4) Predefined answer responses (I don't generate text). The model loads once (~25MB) and runs entirely in your browser. I'm essentially a semantic search engine with predefined answers!",
                    },
                    {
                        intent: 'bot_model_loading',
                        sentences: [
                            'do you load that l6 lm thing',
                            'do you load the model',
                            'how do you load the model',
                            'where do you load',
                            'load that l6',
                            'you load the model',
                            'you load minilm',
                            'you load transformers',
                            'model loading',
                            'model load time',
                            'how long to load',
                            'loading time',
                            'initialization time',
                            'startup time',
                            'cold start',
                            'first load',
                            'initial load',
                            'model download',
                            'model fetch',
                            'where is the model',
                            'where is model stored',
                            'model location',
                            'model path',
                            'model cache',
                            'model cached',
                            'is model cached',
                            'model storage',
                            'model size',
                            'how big is the model',
                            'model mb',
                            'model memory',
                            'model ram',
                            'model download size',
                        ],
                        response: "Yes! The all-MiniLM-L6-v2 model loads once when you first visit the page. Transformers.js is loaded locally from the server (with CDN fallback), and the model itself is downloaded from Hugging Face on first use. It runs in a Web Worker so it doesn't block the UI. The model is about 25MB and gets cached in your browser's IndexedDB. Once loaded, it converts text to embeddings instantly. Yunus integrated it using Transformers.js - it's all client-side, no server processing needed!",
                    },
                    {
                        intent: 'yunus_singing',
                        sentences: [
                            'can yunus sing',
                            'does yunus sing',
                            'yunus sing',
                            'can he sing',
                            'does he sing',
                            'yunus singing',
                            'yunus voice',
                            'yunus music',
                            'yunus guitar',
                            'yunus plays guitar',
                            'yunus musical',
                            'yunus musician',
                            'yunus musical ability',
                            'yunus can sing',
                            'yunus sings',
                            'he sings',
                            'he can sing',
                            'is yunus musical',
                            'is yunus a musician',
                            'is yunus a singer',
                            'does yunus play music',
                            'does yunus play guitar',
                            'does yunus play instruments',
                            'yunus instruments',
                            'yunus hobbies music',
                            'yunus guitar playing',
                            'yunus music taste',
                            'yunus favorite music',
                        ],
                        response: "Yunus plays guitar, but I'm not sure about his singing abilities! He enjoys playing guitar to unwind. He's more known for his coding and technical work than singing.",
                    },
                    {
                        intent: 'yunus_profession',
                        sentences: [
                            'is yunus a programmer',
                            'is he a programmer',
                            'is yunus a developer',
                            'is he a developer',
                            'is yunus a coder',
                            'does yunus program',
                            'does he code',
                            'yunus is a programmer',
                            'yunus is a developer',
                            'yunus is a coder',
                            'yunus programs',
                            'yunus codes',
                            'yunus writes code',
                            'yunus software engineer',
                            'yunus software developer',
                            'yunus is software engineer',
                            'yunus is software developer',
                            'yunus is an engineer',
                            'yunus engineer',
                            'yunus it specialist',
                            'yunus is it specialist',
                            'is yunus an engineer',
                            'is yunus an it specialist',
                            'is he an engineer',
                            'is he an it specialist',
                            'does yunus work in it',
                            'does he work in it',
                            'yunus works in tech',
                            'yunus works in it',
                            'yunus tech professional',
                        ],
                        response: "Yes! Yunus is a software developer and programmer. He creates projects using PHP, JavaScript, Python, Django, C++, and more. He's also an IT specialist focusing on computational intelligence and operational technology.",
                    },
                    {
                        intent: 'bot_language',
                        sentences: [
                            'can you speak turkish',
                            'can you speak english',
                            'do you speak turkish',
                            'do you know turkish',
                            'do you only know english',
                            'what languages do you speak',
                            'can you understand turkish',
                            'do you understand turkish',
                            'can you speak other languages',
                            'can you speak multiple languages',
                            'are you multilingual',
                            'do you know other languages',
                            'what languages can you understand',
                            'what languages do you know',
                            'can you speak german',
                            'can you speak french',
                            'can you speak spanish',
                            'can you speak chinese',
                            'can you speak japanese',
                            'can you speak korean',
                            'can you speak arabic',
                            'do you speak german',
                            'do you speak french',
                            'do you speak spanish',
                            'you speak turkish',
                            'you speak english',
                            'you understand turkish',
                            'turkish support',
                            'english only',
                            'language support',
                            'supported languages',
                            'what language',
                            'which languages',
                        ],
                        response: "I primarily understand English, but I can process questions in other languages too thanks to semantic embeddings! However, my responses are in English. The semantic model I use (all-MiniLM-L6-v2) is multilingual, so I can understand the meaning even if you ask in Turkish or other languages, but I'll respond in English.",
                    },
                    // NEW INTENTS
                    {
                        intent: 'yunus_open_source',
                        sentences: [
                            'is yunus open source',
                            'yunus open source',
                            'yunus github open source',
                            'is yunus work open source',
                            'does yunus contribute to open source',
                            'yunus open source contributions',
                            'yunus foss',
                            'yunus free software',
                            'yunus gnu',
                            'yunus mit license',
                            'yunus apache license',
                            'yunus gpl',
                            'yunus software license',
                            'what license does yunus use',
                            'is yunus code open',
                            'can i use yunus code',
                            'can i fork yunus code',
                            'yunus code available',
                            'yunus source code',
                            'yunus public code',
                        ],
                        response: "Yes! Most of Yunus's work is open-source and available on GitHub. He believes in sharing knowledge and contributing to the community. You can find his projects at github.com/yunusemrejr with various licenses.",
                    },
                    {
                        intent: 'yunus_contact_method',
                        sentences: [
                            'how can i contact yunus',
                            'how to contact yunus',
                            'contact yunus how',
                            'yunus contact method',
                            'best way to contact yunus',
                            'yunus preferred contact',
                            'how to reach yunus',
                            'reach yunus how',
                            'get in touch with yunus',
                            'yunus communication',
                            'yunus email address',
                            'yunus phone number',
                            'yunus linkedin',
                            'yunus twitter',
                            'yunus social media',
                            'yunus professional contact',
                            'yunus business contact',
                            'yunus work contact',
                            'yunus collaboration',
                            'yunus consulting',
                            'hire yunus',
                            'yunus available for hire',
                            'yunus freelance',
                            'yunus contract work',
                        ],
                        response: "The best way to reach Yunus is through the contact page on this website. He's open to collaboration, consulting, and interesting project opportunities. Check out the Contact section for direct communication!",
                    },
                    {
                        intent: 'yunus_availability',
                        sentences: [
                            'is yunus available',
                            'is yunus available for work',
                            'is yunus looking for work',
                            'yunus job search',
                            'yunus open to opportunities',
                            'yunus hiring',
                            'yunus available for projects',
                            'yunus freelance availability',
                            'yunus contract availability',
                            'yunus full time',
                            'yunus part time',
                            'yunus remote work',
                            'yunus relocation',
                            'yunus job status',
                            'yunus employment status',
                            'is yunus employed',
                            'yunus current job',
                            'yunus looking for new opportunities',
                            'yunus career opportunities',
                            'yunus work opportunities',
                        ],
                        response: "Yunus is always open to interesting opportunities and collaborations! He's particularly interested in computational intelligence, industrial automation, and innovative software projects. Reach out through the contact page to discuss possibilities.",
                    },
                    // Turkish Q&A targets
                    {
                        intent: 'kimsin',
                        sentences: [
                            'kimsin',
                            'kimsiniz',
                            'sen kimsin',
                            'kimsin sen',
                            'kim bu',
                            'kim bu bot',
                            'kim yunobot',
                            'kim bu yunobot',
                            'bu ne',
                            'sen ne',
                            'ne yapıyorsun',
                            'ne yapıyorsun burada',
                        ],
                        response: "Ben YunoBot, Yunus'un AI asistanıyım. Siteye hoş geldiniz! Size nasıl yardımcı olabilirim?",
                    },
                    {
                        intent: 'kim_yunus',
                        sentences: [
                            'kim yunus',
                            'kim yunus emre vurgun',
                            'kim bu yunus',
                            'kim bu adam',
                            'kim bu kişi',
                            'kim bu insan',
                            'kimdir yunus',
                            'kimdir bu',
                            'kimdir yunus emre vurgun',
                            'kimdir bu kişi',
                        ],
                        response: "Yunus Emre Vurgun, İstanbul'da yaşayan bir yazılım geliştirici ve BT uzmanıdır. Endüstriyel otomasyon, hesaplamalı zeka ve yapay zeka alanlarında çalışıyor. Detaylı bilgi için Hakkında sayfasına göz atabilirsiniz.",
                    },
                    {
                        intent: 'nerede_yunus',
                        sentences: [
                            'nerede yunus',
                            'nerede yaşıyor yunus',
                            'nerede yaşıyor',
                            'nerede buluşuruz',
                            'hangi şehir',
                            'hangi şehirde yaşıyor',
                            'hangi ülke',
                            'hangi ülkede yaşıyor',
                            'memleketi neresi',
                            'nereli yunus',
                            'nereli',
                        ],
                        response: "Yunus İstanbul, Türkiye'de yaşıyor.",
                    },
                    {
                        intent: 'ne_yapar_yunus',
                        sentences: [
                            'ne yapar yunus',
                            'ne yapıyor yunus',
                            'ne iş yapar',
                            'ne iş yapıyor',
                            'mesleği ne',
                            'uzmanlık ne',
                            'ne iş yapıyor bu adam',
                            'ne iş yapıyor yunus',
                            'yazılım geliştirici',
                            'yazılım mühendisi',
                            'programcı mı',
                            'geliştirici mi',
                        ],
                        response: "Yunus yazılım geliştirici ve BT uzmanıdır. Endüstriyel müşteriler için sistemler geliştiriyor, Django ile web uygulamaları yapıyor, saf C++ ile sinir ağları kuruyor ve hesaplamalı zeka/operasyon teknolojileri alanında çalışıyor.",
                    },
                    {
                        intent: 'proje_nerede',
                        sentences: [
                            'projeleri nerede',
                            'projeleri neler',
                            'projeleri ne',
                            'hangi projeler var',
                            'proje var mı',
                            'ne projeleri var',
                            'yaptığı projeler',
                            'yaptığı işler',
                            'çalışmaları',
                            'eserleri',
                            'portfolio nerede',
                            'projeleri gör',
                        ],
                        response: "Yunus'in endüstriyel müşteriler için PHP ve JavaScript ile hazırlanan bir çizelgeleme sistemi, Neo4j ve Plotly ile grafik verilerini görselleştiren bir Django uygulaması (Mr. Graphy), saf C++ ile sınıflandırma için bir sinir ağı ve daha fazlası gibi projeleri var. Detaylar için portfolyo sayfasına göz atabilirsiniz.",
                    },
                    {
                        intent: 'teknoloji_neler',
                        sentences: [
                            'hangi teknolojileri kullanıyor',
                            'teknolojileri neler',
                            'hangi dilleri biliyor',
                            'hangi programlama dili',
                            'php biliyor mu',
                            'python biliyor mu',
                            'javascript biliyor mu',
                            'c++ biliyor mu',
                            'django biliyor mu',
                            'react biliyor mu',
                            'node biliyor mu',
                            'neoj4 biliyor mu',
                            'neo4j biliyor mu',
                            'tek stack',
                        ],
                        response: "Yunus PHP, JavaScript, Python, Django, Neo4j, C++ ve daha birçok teknoloji ile çalışıyor. Endüstriyel müşteriler için PHP ve JavaScript kullanarak projeler geliştirdi, saf C++ ile sinir ağları kurdurdu ve Django uygulamaları geliştirdi. Detaylar için portfolyo sayfasına göz atabilirsiniz.",
                    },
                    {
                        intent: 'matematik_ilgi',
                        sentences: [
                            'matematik seviyor mu',
                            'matematik biliyor mu',
                            'matematik var mı',
                            'sayısal zeka',
                            'algoritma',
                            'sayısal yetenek',
                            'matematik yeteneği',
                            'matematik arka planı',
                            'hesaplamalı düşünme',
                            'algoritmik düşünme',
                        ],
                        response: "Yunus güçlü algoritmik temelleri ve bilgisayarın arkasındaki matematiği değerlendiriyor. Hesaplamalı zeka ve teknolojinin temelindeki matematiksel prensiplerle ilgileniyor. Çalışmaları sinir ağları, algoritmalar ve hesaplamalı sistemlerdeki matematiksel kavramları içeriyor.",
                    },
                    {
                        intent: 'github_nerede',
                        sentences: [
                            'github nerede',
                            'github var mı',
                            'github kullanıyor mu',
                            'açık kaynak',
                            'foss',
                            'repo var mı',
                            'depo var mı',
                            'kodları nerede',
                            'kaynak kodu nerede',
                            'github adresi',
                            'github linki',
                            'github profili',
                        ],
                        response: "Yunus GitHub'da 100'den fazla deposu var. Kullanıcı adı: github.com/yunusemrejr. Çoğu projesi açık kaynaklıdır ve çeşitli lisanslarla paylaşılıyor.",
                    },
                    {
                        intent: 'eğitim_nerede',
                        sentences: [
                            'nerede okudu',
                            'okul nerede',
                            'üniversite nerede',
                            'hangi üniversitede',
                            'bölüm ne',
                            'mezuniyet',
                            'derecesi var mı',
                            'eğitim durumu',
                            'eğitim geçmişi',
                            'çalıştığı okullar',
                            'beykoz üniversitesi',
                            'anadolu üniversitesi',
                            'illinois',
                        ],
                        response: "Yunus programlama alanında A.S. derecesine sahiptir ve Beykoz Üniversitesi, Illinois Institute of Technology ve Anadolu Üniversitesi'nde çalışmalar yapmıştır. Sürekli öğrenmeye önem verir ve Google Cloud, Cisco gibi kurumlardan kurslar almıştır.",
                    },
                    {
                        intent: 'yaş_yunus',
                        sentences: [
                            'yaşı kaç',
                            'kaç yaşında',
                            'yaş kaç',
                            'doğum tarihi',
                            'doğduğu yıl',
                            'doğum yeri',
                            '2000 doğumlu',
                            'şubat doğumlu',
                            'yaş sorusu',
                            'kaç yaşındasın',
                            'yaşın kaç',
                            'doğum günü',
                        ],
                        response: "Yunus Şubat 2000'de doğmuştur, bu yüzden 20'li yaşlarındadır. Evet, bir 2000'li çocuğudur!",
                    },
                    {
                        intent: 'türk_mü',
                        sentences: [
                            'türk mü',
                            'türkçe konuşuyor mu',
                            'türkiye\'den mi',
                            'nerede yaşıyor yunus',
                            'memleketi neresi',
                            'hangi ülke',
                            'istanbul\'da mı yaşıyor',
                            'türkiye\'de mi yaşıyor',
                        ],
                        response: "Evet, Yunus Türk'tür! İstanbul, Türkiye'de yaşıyor.",
                    },
                    {
                        intent: 'sertifika_sor',
                        sentences: [
                            'sertifika var mı',
                            'sertifikaları neler',
                            'hangi sertifikaları var',
                            'google cloud sertifikası var mı',
                            'cisco sertifikası var mı',
                            'kurslar var mı',
                            'online kurslar',
                            'eğitimleri neler',
                            'sertifika listesi',
                            'sertifika detayları',
                        ],
                        response: "Evet! Yunus Google Cloud, Cisco ve diğer kurumlardan çeşitli sertifikalar almıştır. Sürekli öğrenmeye önem verir. Detaylar için Hakkında sayfasına göz atabilirsiniz.",
                    },
                    {
                        intent: 'blog_neler',
                        sentences: [
                            'blogda neler yazıyor',
                            'blog konuları neler',
                            'ne yazıyor',
                            'hangi konularda yazıyor',
                            'yazı konuları',
                            'makaleleri neler',
                            'yazılım yazıyor mu',
                            'teknoloji yazıyor mu',
                            'makale konuları',
                            'blog içeriği',
                            'yazı içeriği',
                            'ne tür yazılar',
                            'yazı türü',
                        ],
                        response: "Yunus hesaplamalı zeka, operasyon teknolojisi, endüstriyel otomasyon, sinir ağları, grafik veritabanları (Neo4j), Django geliştirme ve yazılım mimarisi konularında yazıyor. Hem teorik temelleri hem de pratik uygulamaları kapsıyor.",
                    },
                    {
                        intent: 'söyleyebileceklerin',
                        sentences: [
                            'ne yapabilirsin',
                            'ne yapabiliyorsun',
                            'neler yapabilirsin',
                            'yeteneğin neler',
                            'gücün neler',
                            'sınırların neler',
                            'ne yapamazsın',
                            'yardımcı olabilir misin',
                            'nasıl yardımcı olursun',
                            'komutlar neler',
                            'aracın var mı',
                            'araçlar neler',
                        ],
                        response: "Site gezintisine yardımcı olabilirim, Yunus'un çalışmaları ve arka planı hakkında sorularınızı cevaplayabilirim, doğal dil sorgularını anlayabilirim (hatalara ve varyasyonlara karşı dayanıklı) ve bağlama duyarlı takip soruları sağlayabilirim. Tüm işlem tarayıcınızda yerel olarak gerçekleşir!",
                    },
                    {
                        intent: 'gizlilik',
                        sentences: [
                            'veri saklıyor musun',
                            'izliyor musun',
                            'veri topluyor musun',
                            'gizlilik var mı',
                            'veri gizliliği',
                            'çerez kullanıyor musun',
                            'takip ediyor musun',
                            'sunucuya veri gönderiyor musun',
                            'istemci tarafında mı çalışıyor',
                            'çevrimdışı mı çalışır',
                            'sunucu yok mu',
                            'veri gizli mi',
                        ],
                        response: "Tamamen tarayıcınızda çalışır, sunucu ile hiçbir iletişim olmaz. Tüm işlemler yerel olarak gerçekleşir. Sorularınız cihazınızdan hiç çıkmaz. Model bir kez indirilir ve önbelleğe alınır. Herhangi bir sunucuda konuşma geçmişi saklanmaz, sizi izlemez veya kişisel veri toplamaz.",
                    },
                    {
                        intent: 'endüstriyel_otomasyon',
                        sentences: [
                            'endüstriyel otomasyon',
                            'endüstriyel otomasyon deneyimi',
                            'plc',
                            'scada',
                            'hmi',
                            'opc ua',
                            'modbus',
                            'mqtt',
                            'endüstriyel iot',
                            'iot',
                            'operasyon teknolojisi',
                            'ot',
                            'it ot birleşimi',
                            'üretim otomasyonu',
                            'fabrika otomasyonu',
                            'süreç otomasyonu',
                            'kontrol sistemleri',
                            'kepserver',
                            'kepware',
                            'endüstriyel yazılım',
                            'endüstriyel proje',
                            'endüstriyel müşteri',
                            'çizelgeleme sistemi',
                            'php endüstriyel',
                        ],
                        response: "Yunus endüstriyel otomasyon ve operasyon teknolojisi (OT) alanında uzmanlaşmıştır. Endüstriyel müşteriler için PHP ve JavaScript ile çizelgeleme sistemleri geliştirdi, KepServerEX REST API istemcileri oluşturdu ve IT/OT birleşimi üzerinde çalışıyor. Odak noktası PLC iletişimi, SCADA sistemleri ve MQTT, OPC UA gibi endüstriyel IoT protokolleri.",
                    },
                    {
                        intent: 'blog_konuları',
                        sentences: [
                            'blogda ne yazıyor',
                            'blog konuları neler',
                            'ne tür yazılar var',
                            'hangi konularda yazıyor',
                            'yazılım yazıyor mu',
                            'teknoloji yazıyor mu',
                            'yapay zeka yazıyor mu',
                            'makale konuları',
                            'blog içeriği ne',
                            'yazı içerikleri',
                            'yazı türleri neler',
                        ],
                        response: "Yunus hesaplamalı zeka, operasyon teknolojisi, endüstriyel otomasyon, sinir ağları, grafik veritabanları (Neo4j), Django geliştirme ve yazılım mimarisi konularında yazıyor. Hem teorik temelleri hem de pratik uygulamaları kapsıyor.",
                    },
                    {
                        intent: 'öğrenme_kaynakları',
                        sentences: [
                            'nasıl öğrendi',
                            'öğrenme yolu',
                            'kendi kendine öğrendi mi',
                            'resmi eğitim',
                            'online kurslar',
                            'öğrenme kaynakları',
                            'çalışma yöntemleri',
                            'öğrenme tarzı',
                            'eğitim yolu',
                            'kariyer yolu',
                            'profesyonel gelişim',
                            'yetkinlik geliştirme',
                            'sürekli öğrenme',
                            'öğrenme felsefesi',
                            'öğretim tarzı',
                            'bilgi paylaşımı',
                            'odysee var mı',
                            'youtube var mı',
                            'içerik üretiyor mu',
                            'eğitici içerik',
                            'öğretici yazılar',
                            'rehberler',
                            'eğitim materyalleri',
                        ],
                        response: "Yunus büyük ölçüde kendi kendine öğrenmiştir ve çeşitli kurumlardan resmi eğitimi almıştır. Pratik öğrenmeyi, deneyimi ve güçlü algoritmik temelleri değerlendirir. Odysee'de sinir ağları hakkında eğitici içerikler üretir ve ayrıntılı teknik blog yazıları yazar.",
                    },
                    {
                        intent: 'iletişim_yöntemi',
                        sentences: [
                            'nasıl iletişime geçebilirim',
                            'iletişim nasıl',
                            'nasıl ulaşabilirim',
                            'en iyi iletişim',
                            'tercih edilen iletişim',
                            'nasıl yunusa ulaşırım',
                            'ulaşım yöntemi',
                            'iletişim bilgileri',
                            'e-posta adresi',
                            'telefon numarası',
                            'linkedin var mı',
                            'twitter var mı',
                            'sosyal medya',
                            'mesaj atabilir miyim',
                            'işbirliği yapabilir miyim',
                            'danışmanlık',
                            'iş teklifi',
                            'proje teklifi',
                            'freelance',
                            'sözleşmeli iş',
                            'full time',
                            'part time',
                            'uzaktan çalışma',
                            'yer değiştirme',
                            'iş durumu',
                            'çalışma durumu',
                        ],
                        response: "Yunus ile en iyi iletişim kurma yolu bu web sitesindeki İletişim sayfasıdır. İşbirliği, danışmanlık ve ilginç proje fırsatlarına açıktır. Doğrudan iletişim için İletişim bölümüne göz atabilirsiniz.",
                    },
                    {
                        intent: 'müsait_mi',
                        sentences: [
                            'müsait mi',
                            'iş arıyor mu',
                            'iş fırsatları',
                            'proje fırsatları',
                            'freelance müsait',
                            'sözleşmeli iş',
                            'full time mi',
                            'part time mi',
                            'remote çalışır mı',
                            'yer değişikliği',
                            'iş durumu ne',
                            'çalışıyor mu',
                            'yeni fırsatlar',
                            'kariyer fırsatları',
                            'iş fırsatları',
                            'proje fırsatları',
                            'çalışıyor mu şu anda',
                            'işi var mı',
                        ],
                        response: "Yunus ilginç fırsatlar ve işbirlikleri için her zaman açıktır! Özellikle hesaplamalı zeka, endüstriyel otomasyon ve yenilikçi yazılım projeleriyle ilgileniyor. Detaylar için İletişim sayfasından ulaşabilirsiniz.",
                    },
                    {
                        intent: 'gitar_calar_mı',
                        sentences: [
                            'gitar çalar mı',
                            'şarkı söyler mi',
                            'müzik yapar mı',
                            'gitar çalıyor mu',
                            'çalıyor mu gitar',
                            'enstrüman çalar mı',
                            'müzik hobisi',
                            'gitar yeteneği',
                            'şarkı söylüyor mu',
                            'müzisyen mi',
                            'gitarist mi',
                            'hobisi müzik',
                            'gitar çalışıyor mu',
                            'müzik zevki',
                            'favori müzik',
                        ],
                        response: "Yunus gitar çalar, ancak şarkı söyleme yeteneğinden emin değilim! Gitar çalmayı dinlenmek için tercih eder. Kodlama ve teknik çalışmalarıyla daha çok tanınır.",
                    },
                    {
                        intent: 'programcı_mı',
                        sentences: [
                            'programcı mı',
                            'geliştirici mi',
                            'kod yazar mı',
                            'yazılım mühendisi mi',
                            'yazılım geliştirici mi',
                            'yazılımcı mı',
                            'kodluyor mu',
                            'kod yazıyor mu',
                            'it uzmanı mı',
                            'mühendis mi',
                            'programcıdır',
                            'geliştiricidir',
                        ],
                        response: "Evet! Yunus yazılım geliştirici ve programcıdır. PHP, JavaScript, Python, Django, C++ ve daha birçok teknoloji ile projeler oluşturur. Ayrıca hesaplamalı zeka ve operasyon teknolojisi alanında bir BT uzmanıdır.",
                    },
                    {
                        intent: 'açık_kaynak',
                        sentences: [
                            'açık kaynak mı',
                            'açık kaynak projeleri var mı',
                            'açık kaynak katkısı',
                            'foss mu',
                            'özgür yazılım',
                            'gnu mu',
                            'lisans kullanıyor mu',
                            'hangi lisans',
                            'kodları açık mı',
                            'kodları kullanabilir miyim',
                            'fork edebilir miyim',
                            'kaynak kodu erişilebilir mi',
                            'herkese açık mı',
                        ],
                        response: "Evet! Yunus'ın çoğu çalışması açık kaynaklıdır ve GitHub'da bulunur. Bilgi paylaşımına ve topluluğa katkı sağlamaya inanır. Projelerini github.com/yunusemrejr adresinde çeşitli lisanslarla bulabilirsiniz.",
                    },
                ],
                        sentences: [
                            'what does yunus write about',
                            'yunus blog topics',
                            'yunus writing topics',
                            'yunus article topics',
                            'what topics does yunus cover',
                            'yunus blog subjects',
                            'yunus technical writing',
                            'yunus tech blog',
                            'yunus engineering blog',
                            'yunus code blog',
                            'yunus programming blog',
                            'yunus ai blog',
                            'yunus ml blog',
                            'yunus automation blog',
                            'yunus ot blog',
                            'yunus industrial blog',
                            'yunus django blog',
                            'yunus python blog',
                            'yunus javascript blog',
                            'yunus php blog',
                            'yunus cpp blog',
                            'yunus neo4j blog',
                            'yunus graph database blog',
                            'yunus neural network blog',
                        ],
                        response: "Yunus writes about computational intelligence, operational technology, industrial automation, neural networks, graph databases (Neo4j), Django development, and software architecture. His blog covers both theoretical foundations and practical implementations.",
                    },
                    {
                        intent: 'yunus_learning_resources',
                        sentences: [
                            'how did yunus learn',
                            'yunus learning path',
                            'yunus self taught',
                            'yunus formal education',
                            'yunus online courses',
                            'yunus learning resources',
                            'yunus study methods',
                            'yunus learning style',
                            'yunus education path',
                            'yunus career path',
                            'yunus professional development',
                            'yunus skill development',
                            'yunus continuous learning',
                            'yunus learning philosophy',
                            'yunus teaching style',
                            'yunus mentorship',
                            'yunus knowledge sharing',
                            'yunus odysee',
                            'yunus youtube',
                            'yunus content creation',
                            'yunus educational content',
                            'yunus tutorials',
                            'yunus guides',
                        ],
                        response: "Yunus is largely self-taught with formal education from multiple institutions. He values hands-on learning, experimentation, and strong algorithmic foundations. He creates educational content on Odysee about neural networks and writes detailed technical blog posts.",
                    },
                    {
                        intent: 'bot_capabilities',
                        sentences: [
                            'what can you do',
                            'what are you capable of',
                            'what are your capabilities',
                            'what can yunobot do',
                            'yunobot capabilities',
                            'yunobot features',
                            'yunobot functions',
                            'yunobot abilities',
                            'yunobot skills',
                            'yunobot what can you do',
                            'yunobot help',
                            'yunobot commands',
                            'yunobot tools',
                            'yunobot what do you offer',
                            'yunobot services',
                            'yunobot what are you good at',
                            'yunobot strengths',
                            'yunobot limitations',
                            'what are your limitations',
                            'what can you not do',
                            'what are you not good at',
                            'can you generate text',
                            'can you write code',
                            'can you solve problems',
                            'can you help me code',
                            'can you debug',
                            'can you explain code',
                        ],
                        response: "I can help you navigate this website, answer questions about Yunus's work and background, understand natural language queries (even with typos), and provide context-aware follow-up responses. I use semantic embeddings for understanding and run entirely in your browser!",
                    },
                    {
                        intent: 'bot_privacy',
                        sentences: [
                            'do you store my data',
                            'do you track me',
                            'do you collect data',
                            'yunobot privacy',
                            'yunobot data collection',
                            'yunobot tracking',
                            'yunobot analytics',
                            'yunobot cookies',
                            'yunobot local storage',
                            'yunobot browser storage',
                            'is yunobot private',
                            'is yunobot secure',
                            'does yunobot send data',
                            'does yunobot use api',
                            'does yunobot make requests',
                            'yunobot server requests',
                            'yunobot network calls',
                            'yunobot offline',
                            'yunobot client side',
                            'yunobot runs locally',
                            'yunobot browser only',
                            'yunobot no server',
                            'yunobot data privacy',
                            'yunobot user privacy',
                            'yunobot gdpr',
                            'yunobot compliance',
                        ],
                        response: "I run entirely in your browser with no server communication. All processing happens locally - your questions never leave your device. The embedding model is downloaded once and cached. I don't store conversation history on any server, track you, or collect any personal data.",
                    },
                    {
                        intent: 'yunus_industrial_automation',
                        sentences: [
                            'what is yunus experience with industrial automation',
                            'yunus industrial automation',
                            'yunus automation experience',
                            'yunus plc',
                            'yunus scada',
                            'yunus hmi',
                            'yunus opc ua',
                            'yunus modbus',
                            'yunus mqtt',
                            'yunus industrial iot',
                            'yunus iiot',
                            'yunus operational technology',
                            'yunus ot',
                            'yunus it ot convergence',
                            'yunus manufacturing',
                            'yunus factory automation',
                            'yunus process automation',
                            'yunus control systems',
                            'yunus KepServerEX',
                            'yunus kepware',
                            'yunus industrial software',
                            'yunus industrial projects',
                            'yunus industrial clients',
                            'yunus scheduling system',
                            'yunus php industrial',
                            'yunus javascript industrial',
                        ],
                        response: "Yunus specializes in industrial automation and operational technology (OT). He's built scheduling systems for industrial clients using PHP and JavaScript, developed KepServerEX REST API clients, and works on IT/OT convergence. His focus includes PLC communication, SCADA systems, and industrial IoT protocols like MQTT and OPC UA.",
                    },
                ],
            };
        }
        
        /**
         * Fuzzy matching for navigation keywords (handles typos like "protfo", "porfo lio")
         */
        fuzzyNavigationMatch(input) {
            const lowerInput = input.toLowerCase().trim();
            
            // Navigation keyword mappings with fuzzy matching
            const navKeywords = {
                'portfolio': ['portfolio', 'port', 'portf', 'portfo', 'protfo', 'porfo', 'folio', 'foli', 'folioı', 'work', 'projects', 'proj', 'prot', 'portfol'],
                'gallery': ['gallery', 'gall', 'galler', 'photo', 'photos', 'pics', 'picture', 'pictures', 'image', 'images'],
                'about': ['about', 'abou', 'abt', 'who', 'info', 'information'],
                'blog': ['blog', 'blo', 'post', 'posts', 'article', 'articles'],
                'contact': ['contact', 'contac', 'email', 'reach', 'message'],
                'travel': ['travel', 'trav', 'trip', 'trips', 'journey'],
                'updates': ['updates', 'update', 'news', 'changelog'],
                'post-code': ['post-code', 'postcode', 'code', 'snippet', 'snippets'],
            };
            
            // Check for navigation action words
            const hasNavAction = /(?:take|go|navigate|open|show|visit|see|view|to|me|the)/i.test(lowerInput);
            
            // Split input into words for better matching
            const words = lowerInput.split(/\s+/);
            
            for (const [target, keywords] of Object.entries(navKeywords)) {
                for (const keyword of keywords) {
                    // Check if keyword appears in input (exact or fuzzy)
                    if (lowerInput.includes(keyword)) {
                        // High confidence if keyword is exact match or has nav action
                        if (keyword.length >= 4 || hasNavAction) {
                            return {
                                target,
                                confidence: hasNavAction ? 0.9 : 0.75,
                            };
                        }
                    }
                    
                    // Check each word for fuzzy match
                    for (const word of words) {
                        if (word.length >= 3) {
                            const distance = this.levenshteinDistance(word, keyword);
                            // Allow 1-2 character difference for typos
                            if (distance <= 2 && (distance === 0 || word.length >= 4)) {
                                return {
                                    target,
                                    confidence: hasNavAction ? 0.85 : 0.7,
                                };
                            }
                        }
                    }
                }
            }
            
            return null;
        }
        
        /**
         * Calculate Levenshtein distance for fuzzy matching
         */
        levenshteinDistance(str1, str2) {
            const m = str1.length;
            const n = str2.length;
            const dp = Array(m + 1).fill(null).map(() => Array(n + 1).fill(0));
            
            for (let i = 0; i <= m; i++) dp[i][0] = i;
            for (let j = 0; j <= n; j++) dp[0][j] = j;
            
            for (let i = 1; i <= m; i++) {
                for (let j = 1; j <= n; j++) {
                    if (str1[i - 1] === str2[j - 1]) {
                        dp[i][j] = dp[i - 1][j - 1];
                    } else {
                        dp[i][j] = Math.min(
                            dp[i - 1][j] + 1,
                            dp[i][j - 1] + 1,
                            dp[i - 1][j - 1] + 1
                        );
                    }
                }
            }
            
            return dp[m][n];
        }
        
        /**
         * Initialize Web Worker for embeddings (with health monitoring)
         */
        initializeWorker() {
            // Check if Web Workers are supported
            if (typeof Worker === 'undefined') {
                console.warn('Web Workers not supported, semantic matching disabled');
                this.workerReady = false;
                return;
            }
            
            try {
                const basePath = window.FULL_BASE_PATH || '';
                const workerPath = basePath + 'assets/js/yunobot/embedding-worker.js';
                
                console.log('Initializing embedding worker from:', workerPath);
                this.embeddingWorker = new Worker(workerPath, { type: 'module' });
                
                // ============================================================
                // PERFORMANCE: Worker health monitoring
                // ============================================================
                let healthCheckInterval = null;
                let consecutiveFailures = 0;
                const MAX_CONSECUTIVE_FAILURES = 3;
                
                const startHealthCheck = () => {
                    healthCheckInterval = setInterval(() => {
                        if (!this.embeddingWorker) {
                            clearInterval(healthCheckInterval);
                            return;
                        }
                        
                        const pingId = `ping-${Date.now()}`;
                        const pongTimeout = setTimeout(() => {
                            consecutiveFailures++;
                            if (consecutiveFailures >= MAX_CONSECUTIVE_FAILURES) {
                                console.warn('[YunoBot] Worker health check failed, restarting...');
                                this.restartWorker();
                            }
                        }, 3000);
                        
                        this.embeddingWorker.postMessage({ type: 'ping', data: { id: pingId } });
                        
                        const checkPong = (event) => {
                            if (event.data?.id === pingId) {
                                clearTimeout(pongTimeout);
                                consecutiveFailures = 0;
                                this.embeddingWorker.removeEventListener('message', checkPong);
                            }
                        };
                        this.embeddingWorker.addEventListener('message', checkPong);
                    }, 15000); // Check every 15 seconds
                };
                
                this.embeddingWorker.addEventListener('message', (event) => {
                    const { type, id, error, embedding, results, stats } = event.data;
                    
                    if (type === 'ready') {
                        this.workerReady = true;
                        consecutiveFailures = 0;
                        startHealthCheck();
                        this.precomputeTargetEmbeddings();
                    } else if (type === 'embedding') {
                        const resolve = this.pendingRequests.get(id);
                        if (resolve) {
                            this.pendingRequests.delete(id);
                            resolve(embedding);
                        }
                    } else if (type === 'similarity') {
                        const resolve = this.pendingRequests.get(id);
                        if (resolve) {
                            this.pendingRequests.delete(id);
                            resolve(results);
                        }
                    } else if (type === 'stats') {
                        // Handle stats response
                        const resolve = this.pendingRequests.get(id);
                        if (resolve) {
                            this.pendingRequests.delete(id);
                            resolve(stats);
                        }
                    } else if (type === 'pong') {
                        // Health check response - handled by listener
                    } else if (type === 'error') {
                        console.warn('Embedding worker error:', error);
                        // If model failed to initialize, that's okay - we'll use regex-only mode
                        if (error && error.includes('Model config parse error')) {
                            console.info('Model initialization failed - continuing with regex-only mode. This is expected if Hugging Face blocks browser requests.');
                        }
                        const resolve = this.pendingRequests.get(id);
                        if (resolve) {
                            this.pendingRequests.delete(id);
                            resolve(null);
                        }
                    } else if (type === 'cache_cleared') {
                        // Cache was cleared by worker
                        const resolve = this.pendingRequests.get(id);
                        if (resolve) {
                            this.pendingRequests.delete(id);
                            resolve(true);
                        }
                    }
                });
                
                this.embeddingWorker.addEventListener('error', (error) => {
                    console.error('Worker initialization error (semantic matching disabled):', error);
                    console.error('Worker path attempted:', workerPath);
                    console.error('Full error details:', {
                        message: error.message,
                        filename: error.filename,
                        lineno: error.lineno,
                        colno: error.colno
                    });
                    this.workerReady = false;
                    // Clean up
                    if (this.embeddingWorker) {
                        this.embeddingWorker.terminate();
                        this.embeddingWorker = null;
                    }
                    if (healthCheckInterval) clearInterval(healthCheckInterval);
                });
                
                // Initialize the model with timeout
                this.embeddingWorker.postMessage({ type: 'init' });
                
                // Timeout after 10 seconds - if model doesn't load, continue without semantic matching
                setTimeout(() => {
                    if (!this.workerReady) {
                        console.warn('Model initialization timeout - continuing with regex-only mode');
                    }
                }, 10000);
            } catch (error) {
                console.warn('Failed to initialize embedding worker (semantic matching disabled):', error);
                this.workerReady = false;
            }
        }
        
        /**
         * PERFORMANCE: Restart worker on health check failure
         */
        restartWorker() {
            console.log('[YunoBot] Restarting embedding worker...');
            if (this.embeddingWorker) {
                this.embeddingWorker.terminate();
                this.embeddingWorker = null;
            }
            this.workerReady = false;
            this.workerInitiated = false;
            this.targetEmbeddings = null;
            this.pendingRequests.clear();
            
            // Re-initialize
            this.initializeWorker();
        }
        
        /**
         * PERFORMANCE: Reset inactivity timeout
         */
        resetInactivityTimeout() {
            if (this.workerInactivityTimeout) {
                clearTimeout(this.workerInactivityTimeout);
            }
            this.workerInactivityTimeout = setTimeout(() => {
                // Send terminate message to worker (it will clean up and close)
                if (this.embeddingWorker) {
                    this.embeddingWorker.postMessage({ type: 'terminate' });
                    this.embeddingWorker = null;
                    this.workerReady = false;
                    this.workerInitiated = false;
                    this.targetEmbeddings = null;
                    console.log('[YunoBot] Worker terminated due to inactivity');
                }
            }, this.WORKER_INACTIVITY_MS);
        }
        
        /**
         * Precompute embeddings for all semantic targets (navigation + Q&A)
         */
        async precomputeTargetEmbeddings() {
            if (!this.workerReady || !this.embeddingWorker) return;
            
            try {
                const navEmbeddings = [];
                const qaEmbeddings = [];
                
                // Precompute navigation embeddings
                for (const target of this.semanticTargets.navigation) {
                    const sentence = target.sentences[0];
                    const embedding = await this.generateEmbedding(sentence);
                    if (embedding) {
                        navEmbeddings.push({
                            target: target.target,
                            embedding,
                            type: 'navigation',
                        });
                    }
                }
                
                // Precompute Q&A embeddings
                for (const qa of this.semanticTargets.qa) {
                    const sentence = qa.sentences[0];
                    const embedding = await this.generateEmbedding(sentence);
                    if (embedding) {
                        qaEmbeddings.push({
                            intent: qa.intent,
                            response: qa.response,
                            embedding,
                            type: 'qa',
                        });
                    }
                }
                
                this.targetEmbeddings = {
                    navigation: navEmbeddings,
                    qa: qaEmbeddings,
                };
            } catch (error) {
                console.warn('Failed to precompute target embeddings:', error);
            }
        }
        
        /**
         * Generate embedding for text via worker
         */
        generateEmbedding(text) {
            return new Promise((resolve) => {
                if (!this.workerReady || !this.embeddingWorker) {
                    resolve(null);
                    return;
                }
                
                const id = `embed-${++this.requestCounter}`;
                this.pendingRequests.set(id, resolve);
                
                // Timeout after 5 seconds
                setTimeout(() => {
                    if (this.pendingRequests.has(id)) {
                        this.pendingRequests.delete(id);
                        resolve(null);
                    }
                }, 5000);
                
                this.embeddingWorker.postMessage({
                    type: 'embed',
                    data: { text, id },
                });
            });
        }
        
        /**
         * Find semantic match using embeddings (checks both navigation and Q&A)
         */
        async findSemanticMatch(input) {
            if (!this.workerReady || !this.targetEmbeddings) {
                return null;
            }
            
            try {
                // Generate embedding for user input
                const queryEmbedding = await this.generateEmbedding(input);
                if (!queryEmbedding) return null;
                
                const allMatches = [];
                
                // Check navigation targets (lower threshold for typos/partial words)
                if (this.targetEmbeddings.navigation && this.targetEmbeddings.navigation.length > 0) {
                    for (const target of this.targetEmbeddings.navigation) {
                        const similarity = this.cosineSimilarity(queryEmbedding, target.embedding);
                        if (similarity >= 0.4) { // Lowered from 0.5 to catch typos
                            allMatches.push({
                                type: 'navigation',
                                target: target.target,
                                confidence: similarity,
                            });
                        }
                    }
                }
                
                // Check Q&A targets (lower threshold for natural language variations)
                if (this.targetEmbeddings.qa && this.targetEmbeddings.qa.length > 0) {
                    for (const qa of this.targetEmbeddings.qa) {
                        const similarity = this.cosineSimilarity(queryEmbedding, qa.embedding);
                        if (similarity >= 0.4) { // Lowered from 0.5 to catch more variations
                            allMatches.push({
                                type: 'qa',
                                intent: qa.intent,
                                response: qa.response,
                                confidence: similarity,
                            });
                        }
                    }
                }
                
                if (allMatches.length === 0) return null;
                
                // Prioritize location/travel intents when input contains location keywords
                const hasLocationKeyword = /(?:where|which|how\s*many|countries|travel|visited|went|go)/i.test(input);
                if (hasLocationKeyword) {
                    // Boost confidence for location/travel intents
                    allMatches.forEach(match => {
                        if (match.type === 'qa' && (match.intent === 'where_is_yunus' || match.intent === 'yunus_travel')) {
                            match.confidence += 0.1; // Boost by 0.1
                        }
                    });
                }
                
                // Prioritize birth/age intents when input contains birth/age keywords
                const hasBirthKeyword = /(?:when\s*(?:was\s*)?(?:he|yunus)\s*(?:born|birth)|how\s*old|age|born|birth|2000s?\s*kid)/i.test(input);
                if (hasBirthKeyword) {
                    // Boost confidence for birth/age intents
                    allMatches.forEach(match => {
                        if (match.type === 'qa' && match.intent === 'yunus_birth_age') {
                            match.confidence += 0.15; // Boost by 0.15
                        }
                    });
                }
                
                // Prioritize nationality intents when input contains nationality keywords
                const hasNationalityKeyword = /(?:turkish|turkey|türkiye|nationality|from\s*turkey|from\s*türkiye)/i.test(input);
                if (hasNationalityKeyword) {
                    // Boost confidence for nationality intents
                    allMatches.forEach(match => {
                        if (match.type === 'qa' && match.intent === 'yunus_nationality') {
                            match.confidence += 0.15; // Boost by 0.15
                        }
                    });
                }
                
                // Prioritize certification intents when input contains certification keywords
                const hasCertKeyword = /(?:certification|certificate|cert|certs)/i.test(input);
                if (hasCertKeyword) {
                    // Boost confidence for certification intents
                    allMatches.forEach(match => {
                        if (match.type === 'qa' && match.intent === 'yunus_certifications') {
                            match.confidence += 0.15; // Boost by 0.15
                        }
                    });
                }
                
                // Sort by confidence (highest first)
                allMatches.sort((a, b) => b.confidence - a.confidence);
                
                // Return best match
                return allMatches[0];
            } catch (error) {
                console.warn('Semantic matching failed:', error);
                return null;
            }
        }
        
        /**
         * Calculate cosine similarity between two vectors
         */
        cosineSimilarity(vecA, vecB) {
            if (!vecA || !vecB || vecA.length !== vecB.length) return 0;
            
            let dotProduct = 0;
            let normA = 0;
            let normB = 0;
            
            for (let i = 0; i < vecA.length; i++) {
                dotProduct += vecA[i] * vecB[i];
                normA += vecA[i] * vecA[i];
                normB += vecB[i] * vecB[i];
            }
            
            if (normA === 0 || normB === 0) return 0;
            
            return dotProduct / (Math.sqrt(normA) * Math.sqrt(normB));
        }
        
        /**
         * Tokenize and normalize input text
         */
        tokenize(text) {
            return text.toLowerCase()
                .replace(/[^\w\s]/g, ' ')
                .split(/\s+/)
                .filter(word => word.length > 0);
        }
        
        /**
         * Apply typo corrections to input text
         * Handles common misspellings and transpositions
         */
        applyTypoCorrections(text) {
            let corrected = text.toLowerCase();
            
            // Remove extra whitespace
            corrected = corrected.replace(/\s+/g, ' ').trim();
            
            // Apply word-level typo corrections
            const words = corrected.split(' ');
            const correctedWords = words.map(word => {
                // Check exact match in typo dictionary
                if (this.typoCorrections[word]) {
                    return this.typoCorrections[word];
                }
                
                // Check if word contains a typo as substring
                for (const [typo, correction] of Object.entries(this.typoCorrections)) {
                    if (typo.length >= 3 && word.includes(typo) && typo !== correction) {
                        return word.replace(typo, correction);
                    }
                }
                
                return word;
            });
            
            corrected = correctedWords.join(' ');
            
            // Fix common phrase-level issues
            corrected = corrected
                .replace(/\btake\s+me\s+to\s+the\s+the\s+/gi, 'take me to the ')
                .replace(/\bto\s+to\s+/gi, 'to ')
                .replace(/\bthe\s+the\s+/gi, 'the ')
                .replace(/\bi\s+am\s+i\s+/gi, 'i ')
                .replace(/\bwhat\s+what\s+/gi, 'what ');
            
            return corrected;
        }
        
        /**
         * Advanced fuzzy word matching using multiple distance metrics
         */
        fuzzyWordMatch(word, target, maxDistance = 2) {
            if (word === target) return { match: true, distance: 0 };
            if (word.startsWith(target) || target.startsWith(word)) return { match: true, distance: Math.abs(word.length - target.length) };
            
            const levDist = this.levenshteinDistance(word, target);
            if (levDist <= maxDistance) return { match: true, distance: levDist };
            
            // Jaro-Winkler for prefix similarity
            const jaroDist = this.jaroWinklerDistance(word, target);
            if (jaroDist >= 0.85) return { match: true, distance: Math.round((1 - jaroDist) * 10) };
            
            return { match: false, distance: levDist };
        }
        
        /**
         * Calculate Jaro-Winkler distance (0 = completely different, 1 = identical)
         */
        jaroWinklerDistance(s1, s2) {
            if (s1 === s2) return 1;
            
            const matchWindow = Math.max(s1.length, s2.length) / 2 - 1;
            const s1Matches = new Array(s1.length).fill(0);
            const s2Matches = new Array(s2.length).fill(0);
            
            let matches = 0;
            for (let i = 0; i < s1.length; i++) {
                const start = Math.max(0, i - matchWindow);
                const end = Math.min(i + matchWindow + 1, s2.length);
                for (let j = start; j < end; j++) {
                    if (s2Matches[j] || s1[i] !== s2[j]) continue;
                    s1Matches[i] = s2Matches[j] = 1;
                    matches++;
                    break;
                }
            }
            
            if (matches === 0) return 0;
            
            let t = 0;
            let k = 0;
            for (let i = 0; i < s1.length; i++) {
                if (!s1Matches[i]) continue;
                while (!s2Matches[k]) k++;
                if (s1[i] !== s2[k]) t++;
                k++;
            }
            
            const m = matches;
            const jaro = (m / s1.length + m / s2.length + (m - t / 2) / m) / 3;
            
            // Winkler modification for common prefix
            let prefixLength = 0;
            for (let i = 0; i < Math.min(s1.length, s2.length, 4); i++) {
                if (s1[i] === s2[i]) prefixLength++;
                else break;
            }
            
            return jaro + prefixLength * 0.1 * (1 - jaro);
        }
        
        /**
         * Add exchange to conversation memory
         */
        addToHistory(userInput, response, intent, confidence) {
            this.conversationHistory.push({
                userInput: userInput.trim().toLowerCase(),
                response: response,
                intent: intent,
                confidence: confidence,
                timestamp: Date.now(),
            });
            
            // Keep only last N exchanges
            if (this.conversationHistory.length > this.maxHistoryLength) {
                this.conversationHistory.shift();
            }
        }
        
        /**
         * Get relevant context from conversation history
         */
        getHistoryContext() {
            if (this.conversationHistory.length === 0) return null;
            
            // Return the last exchange for immediate context
            const lastExchange = this.conversationHistory[this.conversationHistory.length - 1];
            
            // Also check for patterns in recent history
            const recentIntents = this.conversationHistory
                .slice(-3)
                .map(e => e.intent)
                .filter(Boolean);
            
            return {
                lastExchange,
                recentIntents,
                dominantIntent: recentIntents.length > 0 ? recentIntents[0] : null,
            };
        }
        
        /**
         * Calibrate confidence thresholds based on session performance
         */
        calibrateConfidence(confidence, intent) {
            // Track confidence history
            this.confidenceHistory.push({ confidence, intent, timestamp: Date.now() });
            if (this.confidenceHistory.length > this.maxConfidenceHistory) {
                this.confidenceHistory.shift();
            }
            
            // Update session stats
            this.sessionStats.totalQueries++;
            if (confidence >= 0.5) {
                this.sessionStats.successfulMatches++;
            }
            
            // Recalculate average confidence periodically
            if (this.sessionStats.totalQueries % 10 === 0) {
                const recent = this.confidenceHistory.slice(-10);
                this.sessionStats.avgConfidence = recent.reduce((sum, c) => sum + c.confidence, 0) / recent.length;
                this.sessionStats.lastRecalibration = Date.now();
            }
            
            // Apply dynamic threshold adjustment
            // If recent average confidence is low, lower the threshold slightly
            const sessionAvg = this.sessionStats.avgConfidence;
            if (sessionAvg < 0.4 && this.sessionStats.totalQueries > 5) {
                // User is having trouble - be more lenient
                confidence = Math.min(1.0, confidence + 0.05);
            } else if (sessionAvg > 0.7 && confidence < 0.5) {
                // Session is going well but this match is weak - be more conservative
                confidence = Math.max(0, confidence - 0.03);
            }
            
            return confidence;
        }
        
        /**
         * Pattern matching with probability scoring (regex-based)
         */
        matchPatterns(input) {
            const results = [];
            
            // Check all pattern categories
            for (const [category, patterns] of Object.entries(this.patterns)) {
                for (const patternData of patterns) {
                    const match = input.match(patternData.pattern);
                    if (match) {
                        results.push({
                            category,
                            pattern: patternData.pattern,
                            intent: patternData.intent || category,
                            target: patternData.target,
                            response: patternData.response,
                            confidence: patternData.confidence,
                            matchLength: match[0].length,
                            inputLength: input.length,
                        });
                    }
                }
            }
            
            // Calculate additional confidence based on match quality
            results.forEach(result => {
                const matchRatio = result.matchLength / result.inputLength;
                result.confidence = result.confidence * (0.7 + 0.3 * matchRatio);
            });
            
            // Sort by confidence (highest first)
            results.sort((a, b) => b.confidence - a.confidence);
            
            return results;
        }
        
        /**
         * Handle follow-up questions with context
         */
        handleFollowUp(input) {
            const lowerInput = input.toLowerCase().trim();
            
            // Follow-up patterns that reference previous question
            const followUpPatterns = [
                { pattern: /^(?:tell\s*me\s*(?:about\s*)?(?:the|it|them|those))|(?:what\s*(?:are|is)\s*(?:the|those|they))|(?:show\s*me\s*(?:the|them|those))|(?:give\s*me\s*(?:the|them|those))|(?:list\s*(?:the|them|those))|(?:name\s*(?:the|them|those))/i, intent: 'follow_up' },
                { pattern: /^(?:then|also|and|plus|what\s*about|how\s*about|so\s*)/i, intent: 'follow_up' },
                { pattern: /^(?:does\s*he\s*(?:like|know|use|work\s*with))|(?:he\s*(?:likes|knows|uses|works\s*with))|(?:i\s*saw\s*he)/i, intent: 'follow_up' },
                { pattern: /^(?:so\s*(?:he|yunus)\s*(?:does|likes|knows|uses|works))|(?:he\s*does)|(?:so\s*he)/i, intent: 'follow_up' },
            ];
            
            // Very short responses that are likely follow-ups
            if (lowerInput.length <= 15 && /^(?:so|yes|no|ok|sure|yeah|yep|he|does|likes|knows|uses|where|which|how\s*many|what|when|how\s*old)/i.test(lowerInput)) {
                return true;
            }
            
            // Single word questions that are likely follow-ups
            if (/^(?:where|which|how\s*many|what|who|when|how\s*old|age)$/i.test(lowerInput)) {
                return true;
            }
            
            // Correction patterns (user correcting the bot)
            if (/(?:no\s*)?(?:i\s*(?:asked|meant|want(?:ed)?)|you\s*(?:got|have|said)\s*(?:it|that)\s*(?:wrong|incorrect)|that\s*(?:is|was)\s*(?:not|wrong)|i\s*(?:understand|know)\s*(?:you|that)\s*(?:are|is)|but\s*i\s*(?:asked|am\s*asking)|no\s*i\s*mean|i\s*mean\s*(?:can\s*)?you)/i.test(lowerInput)) {
                return true;
            }
            
            // Emphasized questions (all caps or with emphasis words)
            if (/^(?:can\s*you|do\s*you|are\s*you|will\s*you|can\s*I)/i.test(lowerInput) && (lowerInput === lowerInput.toUpperCase() || /(?:can\s*you|do\s*you)\s*[A-Z]{3,}/i.test(lowerInput))) {
                return true;
            }
            
            for (const pattern of followUpPatterns) {
                if (pattern.pattern.test(lowerInput)) {
                    return true;
                }
            }
            
            return false;
        }
        
        /**
         * Execute tool calls (time, calculator, search)
         */
        executeTool(intent, input) {
            switch (intent) {
                case 'tool_time': {
                    const now = new Date();
                    const options = { 
                        weekday: 'long', 
                        year: 'numeric', 
                        month: 'long', 
                        day: 'numeric',
                        hour: '2-digit',
                        minute: '2-digit',
                        second: '2-digit',
                        timeZoneName: 'short'
                    };
                    const timeStr = now.toLocaleString('en-US', options);
                    const isTurkish = this.isTurkishInput(input);
                    return {
                        intent: 'tool_time',
                        response: isTurkish 
                            ? `Şu anda: ${now.toLocaleString('tr-TR', { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric', hour: '2-digit', minute: '2-digit', second: '2-digit' })}`
                            : `Current time: ${timeStr}`,
                        confidence: 1.0,
                        tool: 'time',
                    };
                }
                
                case 'tool_calculator': {
                    try {
                        // Extract math expression from input
                        let expr = input
                            .replace(/^(?:calculate|calc|compute|math\s*problem|solve|what\s*is|what's|equals?|equal)\s*/i, '')
                            .replace(/^(?:hesapla|matematik|topla|çıkar|çarp|böl|kaç\s*edir|hesap)\s*/i, '')
                            .trim();
                        
                        // Handle Turkish math words
                        expr = expr
                            .replace(/\bve\b/gi, '+')
                            .replace(/\beksi\b|\bçıkar\b|\bçıkarma\b/gi, '-')
                            .replace(/\bartı\b|\btopla\b|\btoplama\b/gi, '+')
                            .replace(/\bçarp\b|\bçarpma\b|\bkere\b|\bkez\b/gi, '*')
                            .replace(/\bböl\b|\bbölme\b/gi, '/')
                            .replace(/\büzeri\b|\bküvvet\b/gi, '**');
                        
                        // Only allow safe math characters
                        if (!/^[\d\s\+\-\*\/\(\)\.\%\**]+$/.test(expr)) {
                            throw new Error('Unsafe expression');
                        }
                        
                        // Evaluate safely (no eval of arbitrary code)
                        const result = Function('"use strict"; return (' + expr + ')')();
                        
                        if (!isFinite(result)) {
                            return {
                                intent: 'tool_calculator',
                                response: this.isTurkishInput(input)
                                    ? 'Hesaplama sonucu sonsuz veya tanımsız. Lütfen geçerli bir ifade girin.'
                                    : 'The calculation resulted in infinity or NaN. Please enter a valid expression.',
                                confidence: 0.9,
                                tool: 'calculator',
                            };
                        }
                        
                        const formatted = Number.isInteger(result) ? result : result.toFixed(6).replace(/\.?0+$/, '');
                        
                        return {
                            intent: 'tool_calculator',
                            response: this.isTurkishInput(input)
                                ? `Hesaplama sonucu: ${expr.replace(/\*/g, '×').replace(/\//g, '÷')} = ${formatted}`
                                : `Calculation result: ${expr.replace(/\*/g, '×').replace(/\//g, '÷')} = ${formatted}`,
                            confidence: 0.95,
                            tool: 'calculator',
                        };
                    } catch (e) {
                        return {
                            intent: 'tool_calculator',
                            response: this.isTurkishInput(input)
                                ? 'Hesaplama yapamadım. Örnek: "calculate 2+2" veya "12 * 5" gibi deneyin.'
                                : 'I could not calculate that. Try something like "calculate 2+2" or "12 * 5".',
                            confidence: 0.8,
                            tool: 'calculator',
                        };
                    }
                }
                
                case 'tool_search': {
                    const query = input
                        .replace(/^(?:search\s*(?:for|about)?|google|look\s*up|find\s*(?:info|information)?|web\s*search)\s*/i, '')
                        .trim();
                    
                    if (!query) {
                        return {
                            intent: 'tool_search',
                            response: this.isTurkishInput(input)
                                ? 'Ne aramak istediğinizi belirtin. Örnek: "search for quantum computing" veya "yapay zeka ara"'
                                : 'What would you like me to search for? Example: "search for quantum computing"',
                            confidence: 0.9,
                            tool: 'search',
                        };
                    }
                    
                    // Open search in new tab
                    const searchUrl = `https://www.google.com/search?q=${encodeURIComponent(query)}`;
                    setTimeout(() => window.open(searchUrl, '_blank'), 300);
                    
                    return {
                        intent: 'tool_search',
                        response: this.isTurkishInput(input)
                            ? `"${query}" için arama yeni sekmede açılıyor...`
                            : `Searching for "${query}" in a new tab...`,
                        confidence: 0.9,
                        tool: 'search',
                        url: searchUrl,
                    };
                }
                
                default:
                    return null;
            }
        }

        /**
         * Get Turkish response for detected Turkish input
         */
        getTurkishResponse(input) {
            const normalized = input.toLowerCase().trim();
            
            // Try Turkish pattern matching first
            const turkishPatterns = this.patterns.turkish_qa || [];
            for (const patternData of turkishPatterns) {
                if (patternData.pattern.test(normalized)) {
                    return {
                        intent: patternData.intent,
                        response: patternData.response,
                        confidence: patternData.confidence,
                        language: 'tr',
                    };
                }
            }
            
            // Try Turkish tools
            const turkishTools = this.patterns.turkish_tools || [];
            for (const toolData of turkishTools) {
                if (toolData.pattern.test(normalized)) {
                    const toolResult = this.executeTool(toolData.intent, input);
                    if (toolResult) return toolResult;
                }
            }
            
            // Try semantic matching with Turkish targets if available
            if (this.targetEmbeddings && this.targetEmbeddings.qa_tr) {
                // Future: add Turkish semantic targets
            }
            
            // Fallback: acknowledge Turkish and offer English help
            return {
                intent: 'language_tr',
                response: "Türkçe sorunuzu anladım, ancak şu anda yalnızca İngilizce yanıt verebiliyorum. Lütfen sorunuzu İngilizce sorun ya da site gezintisi için 'take me to about' gibi komutları deneyin. Size yardımcı olmaya çalışacağım!",
                confidence: 0.7,
                language: 'tr',
            };
        }
        
        /**
         * Process input and generate response (PERFORMANCE OPTIMIZED)
         * Features:
         * - Lazy worker initialization (on first interaction)
         * - Query debouncing for rapid-fire inputs
         * - Response caching for duplicate queries
         * - Early exit on high-confidence pattern matches
         * - Inactivity timeout for worker
         * 
         * Pipeline: Lazy init → Debounce → Cache check → Normalize → 
         *           Pattern matching (early exit) → Semantic matching → Fallback
         */
        async process(input) {
            // ============================================================
            // PERFORMANCE: Lazy worker initialization
            // ============================================================
            this.ensureWorkerInitialized();
            
            // Reset inactivity timeout
            this.resetInactivityTimeout();
            
            // ============================================================
            // PERFORMANCE: Query debouncing
            // ============================================================
            const now = Date.now();
            if (now - this.lastQueryTime < this.debounceMs) {
                // Debounce rapid-fire queries - wait for debounce period
                await new Promise(resolve => {
                    clearTimeout(this.debounceTimer);
                    this.debounceTimer = setTimeout(resolve, this.debounceMs);
                });
            }
            this.lastQueryTime = Date.now();
            
            // ============================================================
            // PERFORMANCE: Response cache check
            // ============================================================
            const cacheKey = input.toLowerCase().trim();
            const cached = this.responseCache.get(cacheKey);
            if (cached && (Date.now() - cached.timestamp < this.responseCacheMaxAge)) {
                return cached.response;
            }
            
            // Safety: ensure we always return a valid response object
            try {
                if (!input || input.trim().length === 0) {
                    return {
                        intent: 'unknown',
                        response: "I didn't catch that. Could you try rephrasing?",
                        examples: this.exampleQuestions,
                        confidence: 0,
                    };
                }
            
            // Normalize input: handle all-caps, extra spaces, etc.
            let normalizedInput = input.trim();
            
            // Detect Turkish input early
            const isTurkish = this.isTurkishInput(normalizedInput);
            
            // If input is all caps, convert to title case for better matching
            if (normalizedInput === normalizedInput.toUpperCase() && normalizedInput.length > 1) {
                normalizedInput = normalizedInput.charAt(0).toUpperCase() + normalizedInput.slice(1).toLowerCase();
            }
            
            // Apply comprehensive typo corrections
            normalizedInput = this.applyTypoCorrections(normalizedInput);
            
            // Additional real-time typo fixes (beyond dictionary)
            normalizedInput = normalizedInput
                .replace(/\bprogrmmer\b/gi, 'programmer')
                .replace(/\bprogamer\b/gi, 'programmer')
                .replace(/\bprogramer\b/gi, 'programmer')
                .replace(/\byun\b/gi, 'yunus')
                .replace(/\bwneglish\b/gi, 'english')
                .replace(/\benglsih\b/gi, 'english')
                .replace(/\bengilsh\b/gi, 'english')
                .replace(/\bwokr\b/gi, 'work')
                .replace(/\bwrok\b/gi, 'work')
                .replace(/\bwor\b/gi, 'work')
                .replace(/\bkock\b/gi, 'knock')
                .replace(/\bwhat\s*ru\b/gi, 'what r u')
                .replace(/\bwhat\s*are\s*u\b/gi, 'what are you');
            
            // If Turkish input, handle separately with Turkish response
            if (isTurkish) {
                const trResult = this.getTurkishResponse(normalizedInput);
                if (trResult && trResult.confidence >= 0.6) {
                    this.lastIntent = trResult.intent;
                    this.lastQuery = normalizedInput;
                    this.addToHistory(input, trResult.response, trResult.intent, trResult.confidence);
                    return trResult;
                }
            }
            
            // Handle single technology mentions (like "c++", "php")
            const singleTechMatch = normalizedInput.match(/^(c\+\+|php|javascript|python|django|java|react|node|math|mathematics)$/i);
            if (singleTechMatch) {
                const tech = singleTechMatch[0].toLowerCase();
                if (tech === 'math' || tech === 'mathematics') {
                    this.lastIntent = 'yunus_math';
                    this.lastQuery = normalizedInput;
                    return {
                        intent: 'yunus_math',
                        response: "Yunus values strong algorithmic foundations and the math behind computing. He focuses on computational intelligence and the mathematical principles underlying technology. His work involves mathematical concepts in neural networks, algorithms, and computational systems.",
                        confidence: 0.9,
                    };
                } else {
                    this.lastIntent = 'yunus_technologies';
                    this.lastQuery = normalizedInput;
                    return {
                        intent: 'yunus_technologies',
                        response: `Yes! Yunus works with ${tech}. He's created projects using PHP and JavaScript for industrial clients, built neural networks in pure C++, developed Django applications, and more. Check out his portfolio to see specific projects!`,
                        confidence: 0.9,
                    };
                }
            }
            
            // Handle single profession keywords (like "PROGRAMMER", "programmer")
            const professionMatch = normalizedInput.match(/^(programmer|programer|progrmmer|progamer|coder|developer)$/i);
            if (professionMatch) {
                this.lastIntent = 'yunus_profession';
                this.lastQuery = normalizedInput;
                return {
                    intent: 'yunus_profession',
                    response: "Yes! Yunus is a software developer and programmer. He creates projects using PHP, JavaScript, Python, Django, C++, and more. He's also an IT specialist focusing on computational intelligence and operational technology.",
                    confidence: 0.9,
                };
            }
            
            // Handle single LLM-related keywords
            const llmMatch = normalizedInput.match(/^(llm|large\s*language\s*model|chatgpt|gpt|grok|gemini|claude)$/i);
            if (llmMatch) {
                this.lastIntent = 'bot_llm';
                this.lastQuery = normalizedInput;
                return {
                    intent: 'bot_llm',
                    response: "No, I'm not ChatGPT, Grok, Gemini, or any other LLM. I'm YunoBot, a specialized AI assistant built specifically for Yunus's website. I use Transformers.js with semantic embeddings (all-MiniLM-L6-v2 model) for understanding, not a large language model. I'm designed to answer questions about Yunus and help navigate his site!",
                    confidence: 0.9,
                };
            }
            
            // Handle simple acknowledgments (like "good", "ok", "yes")
            const acknowledgmentMatch = normalizedInput.match(/^(good|great|nice|ok|okay|yes|yeah|yep|sure|cool|awesome|thanks|thank\s*you)$/i);
            if (acknowledgmentMatch && this.lastIntent) {
                // If we have context, acknowledge and ask if they need anything else
                return {
                    intent: 'acknowledgment',
                    response: "Great! Is there anything else you'd like to know about Yunus or his work?",
                    confidence: 0.8,
                };
            }
            
            // Check if this is a follow-up question
            const isFollowUp = this.handleFollowUp(normalizedInput);
            if (isFollowUp && this.lastIntent) {
                // Try to answer based on last intent context
                if (this.lastIntent === 'yunus_projects' || (this.lastQuery && /project/i.test(this.lastQuery))) {
                    return {
                        intent: 'yunus_projects',
                        response: "Yunus has created various projects including a scheduling system using PHP and JavaScript for industrial clients, Mr. Graphy (a Django app visualizing graph data with Neo4j and Plotly), a neural network for classification in pure C++, a KepServerEX REST API client, and many more. Check out his portfolio page to see more!",
                        confidence: 0.9,
                    };
                }
                
                // Handle technology follow-ups
                if (this.lastIntent === 'yunus_technologies' || this.lastIntent === 'yunus_skills' || 
                    (this.lastQuery && /(?:know|like|use|technolog|skill|php|javascript|python|c\+\+|django|c\+\+|c\+|c\s*or)/i.test(this.lastQuery))) {
                    // Extract technology from current question if present
                    const techMatch = normalizedInput.match(/(?:php|javascript|python|c\+\+|django|java|react|node|c\+|c\s*or\s*c\+)/i);
                    if (techMatch) {
                        const tech = techMatch[0].toLowerCase();
                        if (tech.includes('c++') || tech.includes('c+') || tech.includes('c or')) {
                            return {
                                intent: 'yunus_technologies',
                                response: "Yes! Yunus works with C++. He's built neural networks for classification in pure C++. He works with PHP, JavaScript, Python, Django, Neo4j, C++, and many other technologies. Check out his portfolio to see specific projects!",
                                confidence: 0.9,
                            };
                        }
                        return {
                            intent: 'yunus_technologies',
                            response: `Yes! Yunus works with ${tech}. He's created projects using PHP and JavaScript for industrial clients, built neural networks in pure C++, developed Django applications, and more. Check out his portfolio to see specific projects!`,
                            confidence: 0.9,
                        };
                    }
                    // Generic technology follow-up
                    return {
                        intent: 'yunus_technologies',
                        response: "Yes! Yunus works with PHP, JavaScript, Python, Django, Neo4j, C++, and many other technologies. He's created projects using PHP and JavaScript for industrial clients, built neural networks in pure C++, developed Django applications, and more. Check out his portfolio to see specific projects!",
                        confidence: 0.9,
                    };
                }
                
                // Handle math follow-ups
                if (this.lastIntent === 'yunus_interests' && this.lastQuery && /like/i.test(this.lastQuery)) {
                    return {
                        intent: 'yunus_math',
                        response: "Yunus values strong algorithmic foundations and the math behind computing. He focuses on computational intelligence and the mathematical principles underlying technology. His work involves mathematical concepts in neural networks, algorithms, and computational systems.",
                        confidence: 0.9,
                    };
                }
                
                // Handle location follow-ups
                if (this.lastIntent === 'where_is_yunus' || (this.lastQuery && /where\s*is/i.test(this.lastQuery))) {
                    if (/^(?:where|which|how\s*many)/i.test(normalizedInput)) {
                        // If asking "where" again, might be asking about travel
                        if (/^(?:where|which)/i.test(normalizedInput)) {
                            return {
                                intent: 'yunus_travel',
                                response: "Yunus has visited 21 countries, from Tanzania to Boston. He enjoys traveling and has explored many places around the world. Check out his travel page to see more about his journeys!",
                                confidence: 0.9,
                            };
                        }
                        // "how many" likely asking about countries
                        if (/^(?:how\s*many)/i.test(normalizedInput)) {
                            return {
                                intent: 'yunus_travel',
                                response: "Yunus has visited 21 countries, from Tanzania to Boston. He enjoys traveling and has explored many places around the world. Check out his travel page to see more about his journeys!",
                                confidence: 0.9,
                            };
                        }
                    }
                    // Default location follow-up
                    return {
                        intent: 'where_is_yunus',
                        response: "Yunus is based in Istanbul, Türkiye.",
                        confidence: 0.9,
                    };
                }
                
                // Handle travel follow-ups
                if (this.lastIntent === 'yunus_travel' || this.lastIntent === 'yunus_interests' || 
                    (this.lastQuery && /(?:travel|countries|visited|went|go)/i.test(this.lastQuery))) {
                    if (/^(?:which|how\s*many|where)/i.test(normalizedInput)) {
                        return {
                            intent: 'yunus_travel',
                            response: "Yunus has visited 21 countries, from Tanzania to Boston. He enjoys traveling and has explored many places around the world. Check out his travel page to see more about his journeys!",
                            confidence: 0.9,
                        };
                    }
                }
                
                // Handle birth/age follow-ups and corrections
                if (this.lastIntent === 'yunus_birth_age' || (this.lastQuery && /(?:born|birth|age|old|2000)/i.test(this.lastQuery)) ||
                    /(?:when\s*(?:was\s*)?(?:he|yunus)\s*(?:born|birth)|how\s*old|age|2000s?\s*kid)/i.test(normalizedInput)) {
                    if (/^(?:how\s*old|age|old|2000|when|no\s*i\s*asked|i\s*understand)/i.test(normalizedInput) || 
                        normalizedInput.length <= 15 || 
                        /(?:born|birth|age|old|2000)/i.test(normalizedInput)) {
                        return {
                            intent: 'yunus_birth_age',
                            response: "Yunus was born in February 2000, so he's in his mid-20s. Yes, he's a 2000s kid!",
                            confidence: 0.9,
                        };
                    }
                }
                
                // Handle nationality follow-ups
                if (this.lastIntent === 'yunus_nationality' || (this.lastQuery && /(?:turkish|turkey|nationality|from)/i.test(this.lastQuery))) {
                    if (/^(?:yes|no|ok|where|which)/i.test(normalizedInput) || normalizedInput.length <= 10) {
                        return {
                            intent: 'yunus_nationality',
                            response: "Yes, Yunus is Turkish! He's based in Istanbul, Türkiye.",
                            confidence: 0.9,
                        };
                    }
                }
                
                // Handle certification follow-ups
                if (this.lastIntent === 'yunus_certifications' || (this.lastQuery && /(?:certification|certificate|cert)/i.test(this.lastQuery))) {
                    if (/^(?:yes|no|ok|what|which|how\s*many)/i.test(normalizedInput) || normalizedInput.length <= 10) {
                        return {
                            intent: 'yunus_certifications',
                            response: "Yes! Yunus has taken courses and certifications from Google Cloud, Cisco, and other institutions. He's always learning and values continuous education. Check out his about page for more details on his education and certifications.",
                            confidence: 0.9,
                        };
                    }
                }
                
                // Handle AI identity follow-ups
                if (this.lastIntent === 'bot_identity' || this.lastIntent === 'bot_identity_casual' || this.lastIntent === 'bot_technology' || this.lastIntent === 'bot_llm' || 
                    this.lastIntent === 'bot_difference' || this.lastIntent === 'bot_model_clarification' || this.lastIntent === 'bot_minilm_explanation' || this.lastIntent === 'bot_embeddings_explanation' ||
                    this.lastIntent === 'bot_internal' || this.lastIntent === 'bot_model_loading' ||
                    (this.lastQuery && /(?:ai|bot|robot|what\s*kind|what\s*type|what\s*are\s*you|llm|chatgpt|grok|gemini|difference|different|compare|vs|versus|lm|copied|stole|minilm|embeddings|embedding|clone|load|internally|internal|how\s*do\s*you\s*work)/i.test(this.lastQuery))) {
                    // Check if asking about internal structure (specific - must come before "how do you work")
                    if (/^(?:what\s*are\s*you\s*internally|internally\s*what|what\s*are\s*u\s*internally)/i.test(normalizedInput) ||
                        /(?:internally|internal\s*structure)/i.test(normalizedInput)) {
                        return {
                            intent: 'bot_internal',
                            response: "Internally, I'm a hybrid system: 1) Regex patterns for instant keyword matching, 2) Transformers.js running all-MiniLM-L6-v2 embedding model in a Web Worker, 3) Cosine similarity comparison between your question's vector and my target question vectors, 4) Predefined answer responses (I don't generate text). The model loads once (~25MB) and runs entirely in your browser. I'm essentially a semantic search engine with predefined answers!",
                            confidence: 0.9,
                        };
                    }
                    // Check if asking about model loading
                    if (/^(?:do\s*you\s*load|you\s*load|load\s*that|load\s*the|how\s*do\s*you\s*load|where\s*do\s*you\s*load|ok\s*so\s*you\s*load)/i.test(normalizedInput) ||
                        /(?:load|loading|l6|lm\s*thing)/i.test(normalizedInput)) {
                        return {
                            intent: 'bot_model_loading',
                            response: "Yes! The all-MiniLM-L6-v2 model loads once when you first visit the page. Transformers.js is loaded locally from the server (with CDN fallback), and the model itself is downloaded from Hugging Face on first use. It runs in a Web Worker so it doesn't block the UI. The model is about 25MB and gets cached in your browser's IndexedDB. Once loaded, it converts text to embeddings instantly. Yunus integrated it using Transformers.js - it's all client-side, no server processing needed!",
                            confidence: 0.9,
                        };
                    }
                    // Check if asking about MiniLM specifically
                    if (/^(?:minilm|what\s*is\s*minilm|i\s*dont?\s*get\s*it|i\s*dont?\s*understand|explain\s*minilm)/i.test(normalizedInput) ||
                        /(?:minilm|dont?\s*get|dont?\s*understand)/i.test(normalizedInput)) {
                        return {
                            intent: 'bot_minilm_explanation',
                            response: "MiniLM (all-MiniLM-L6-v2) is a small embedding model from Microsoft. It converts text into 384-dimensional vectors (numbers). When you ask a question, I convert it to a vector, then compare it to vectors of my target questions using cosine similarity. The closest match wins! It's like finding the most similar question in meaning, not exact words. That's why I understand typos and variations - the semantic meaning is preserved in the vector space.",
                            confidence: 0.9,
                        };
                    }
                    // Check if asking about embeddings/how it works (general "how do you work")
                    if (/^(?:how\s*do\s*embeddings|if\s*you\s*are\s*not|how\s*does\s*embeddings|explain\s*embeddings|what\s*are\s*embeddings|how\s*do\s*u\s*work|how\s*do\s*you\s*work)/i.test(normalizedInput) ||
                        /(?:embeddings|embedding|how\s*does?\s*it\s*work|how\s*do\s*you\s*work)/i.test(normalizedInput)) {
                        return {
                            intent: 'bot_embeddings_explanation',
                            response: "Embeddings work like this: 1) Your question gets converted to a vector (list of numbers) by MiniLM, 2) I compare this vector to vectors of my target questions using cosine similarity (measures angle between vectors), 3) The closest match (highest similarity) determines my response. It's semantic matching - 'where is your work' and 'show me portfolio' have similar vectors even though words differ. I don't generate text like LLMs - I just find the best matching predefined answer based on meaning similarity!",
                            confidence: 0.9,
                        };
                    }
                    // Check if asking about differences
                    if (/^(?:what\s*(?:is|are)\s*the\s*difference|what\s*differences|how\s*(?:are|is)\s*you\s*different|compare|difference|different)/i.test(normalizedInput) ||
                        /(?:difference|different|compare|vs|versus)/i.test(normalizedInput)) {
                        return {
                            intent: 'bot_difference',
                            response: "The main difference: ChatGPT and other LLMs are large language models that generate text. I use semantic embeddings - I convert text into mathematical vectors and compare similarity. Key differences: 1) I don't generate text, I match to predefined answers, 2) I'm much smaller (~25MB vs billions of parameters), 3) I run entirely in your browser (no API calls), 4) I'm specialized for Yunus's site only, 5) I use cosine similarity between vectors, not next-token prediction. Think of me as a smart search engine, not a text generator!",
                            confidence: 0.9,
                        };
                    }
                    // Check if asking about model/clarification/clone
                    if (/^(?:so\s*you|you\s*(?:are|is)|lm|copied|stole|minilm|all[\s-]?minilm|so\s*r\s*u|r\s*u\s*(?:a\s*)?clone)/i.test(normalizedInput) ||
                        /(?:lm\s*called|yunus\s*(?:copied|stole)|all[\s-]?minilm|clone)/i.test(normalizedInput)) {
                        return {
                            intent: 'bot_model_clarification',
                            response: "No, I'm not an LLM or a clone! all-MiniLM-L6-v2 is an embedding model, not a language model. It converts text to vectors (embeddings) for similarity matching. Yunus didn't copy or steal anything - it's an open-source model from Hugging Face that he integrated using Transformers.js. The difference: LLMs generate text, embedding models convert text to numbers for comparison. I use embeddings to find similar questions, then return predefined answers - I don't generate responses like ChatGPT does.",
                            confidence: 0.9,
                        };
                    }
                    // Check if asking about LLM specifically
                    if (/^(?:are\s*you|you\s*are|is\s*you|llm|large\s*language|chatgpt|grok|gemini|claude)/i.test(normalizedInput) ||
                        /(?:llm|large\s*language\s*model|chatgpt|grok|gemini)/i.test(normalizedInput)) {
                        return {
                            intent: 'bot_llm',
                            response: "No, I'm not ChatGPT, Grok, Gemini, or any other LLM. I'm YunoBot, a specialized AI assistant built specifically for Yunus's website. I use Transformers.js with semantic embeddings (all-MiniLM-L6-v2 model) for understanding, not a large language model. I'm designed to answer questions about Yunus and help navigate his site!",
                            confidence: 0.9,
                        };
                    }
                    // Otherwise provide technology details
                    if (/^(?:what\s*kind|what\s*type|how|what\s*technology|what\s*model|i\s*asked)/i.test(normalizedInput) || 
                        normalizedInput.length <= 20) {
                        return {
                            intent: 'bot_technology',
                            response: "I'm YunoBot, powered by Transformers.js using the all-MiniLM-L6-v2 model for semantic understanding. I use a hybrid approach: regex patterns for instant responses and semantic embeddings (vector similarity) for natural language understanding. This lets me handle typos, variations, and conversational queries!",
                            confidence: 0.9,
                        };
                    }
                }
                
                // Handle singing follow-ups
                if (this.lastIntent === 'yunus_interests' && (this.lastQuery && /(?:sing|music|guitar)/i.test(this.lastQuery))) {
                    if (/^(?:can|does|ok|so)/i.test(normalizedInput) || normalizedInput.length <= 15) {
                        return {
                            intent: 'yunus_singing',
                            response: "Yunus plays guitar, but I'm not sure about his singing abilities! He enjoys playing guitar to unwind. He's more known for his coding and technical work than singing.",
                            confidence: 0.9,
                        };
                    }
                }
                
                // Handle profession follow-ups
                if (this.lastIntent === 'yunus_profession' || (this.lastQuery && /(?:programmer|developer|coder|code|program)/i.test(this.lastQuery))) {
                    if (/^(?:yes|no|ok|so|whatever)/i.test(normalizedInput) || normalizedInput.length <= 10) {
                        return {
                            intent: 'yunus_profession',
                            response: "Yes! Yunus is a software developer and programmer. He creates projects using PHP, JavaScript, Python, Django, C++, and more. He's also an IT specialist focusing on computational intelligence and operational technology.",
                            confidence: 0.9,
                        };
                    }
                }
                
                // Handle language capability follow-ups and corrections
                if (this.lastIntent === 'yunus_nationality' || this.lastIntent === 'bot_language' ||
                    (this.lastQuery && /(?:turkish|speak|language|english|wneglish|englsih|engilsh|can\s*you|do\s*you)/i.test(this.lastQuery))) {
                    // Check if user is correcting/clarifying about bot's capabilities
                    if (/^(?:no\s*i\s*mean|i\s*mean\s*(?:can\s*)?you|can\s*you|do\s*you|you\s*can|you\s*do)/i.test(normalizedInput) ||
                        /(?:can\s*you|do\s*you)\s*(?:speak|know|understand)/i.test(normalizedInput) ||
                        normalizedInput.length <= 25) {
                        return {
                            intent: 'bot_language',
                            response: "I primarily understand English, but I can process questions in other languages too thanks to semantic embeddings! However, my responses are in English. The semantic model I use (all-MiniLM-L6-v2) is multilingual, so I can understand the meaning even if you ask in Turkish or other languages, but I'll respond in English.",
                            confidence: 0.9,
                        };
                    }
                }
            }
            
            // STEP 1: Try high-confidence regex patterns first (instant, 100% accurate for direct hits)
            const matches = this.matchPatterns(normalizedInput);
            const highConfidenceMatch = matches.find(m => m.confidence >= 0.9);
            
            if (highConfidenceMatch) {
                // Handle navigation intent
                if (highConfidenceMatch.intent === 'navigate' && highConfidenceMatch.target !== undefined) {
                    return {
                        intent: 'navigate',
                        target: highConfidenceMatch.target,
                        url: this.navigationMap[highConfidenceMatch.target],
                        response: `Taking you to the ${highConfidenceMatch.target || 'home'} page...`,
                        confidence: highConfidenceMatch.confidence,
                    };
                }
                
                // Handle high-confidence Q&A
                if (highConfidenceMatch.intent === 'questions' || highConfidenceMatch.intent === 'greeting' || highConfidenceMatch.intent === 'general') {
                    // Store context for follow-ups
                    if (highConfidenceMatch.intent === 'questions') {
                        this.lastIntent = 'questions';
                        this.lastQuery = normalizedInput;
                    }
                    return {
                        intent: highConfidenceMatch.intent,
                        response: highConfidenceMatch.response,
                        confidence: highConfidenceMatch.confidence,
                    };
                }
            }
            
            // STEP 2: Try semantic ML matching (for Q&A and navigation variations)
            const semanticMatch = await this.findSemanticMatch(normalizedInput);
            
            if (semanticMatch && semanticMatch.confidence >= 0.4) { // Lowered threshold
                // Prioritize semantic Q&A over low-confidence regex
                if (semanticMatch.type === 'qa') {
                    // Store context for follow-ups
                    this.lastIntent = semanticMatch.intent;
                    this.lastQuery = normalizedInput;
                    
                    return {
                        intent: semanticMatch.intent,
                        response: semanticMatch.response,
                        confidence: semanticMatch.confidence,
                    };
                }
                
                // Handle navigation semantic match
                if (semanticMatch.type === 'navigation') {
                    return {
                        intent: 'navigate',
                        target: semanticMatch.target,
                        url: this.navigationMap[semanticMatch.target],
                        response: `Taking you to the ${semanticMatch.target || 'home'} page...`,
                        confidence: semanticMatch.confidence,
                    };
                }
            }
            
            // STEP 3: Fall back to lower-confidence regex patterns
            if (matches.length > 0 && matches[0].confidence >= 0.7) {
                const bestMatch = matches[0];
                
                // Handle navigation intent
                if (bestMatch.intent === 'navigate' && bestMatch.target !== undefined) {
                    return {
                        intent: 'navigate',
                        target: bestMatch.target,
                        url: this.navigationMap[bestMatch.target],
                        response: `Taking you to the ${bestMatch.target || 'home'} page...`,
                        confidence: bestMatch.confidence,
                    };
                }
                
                // Handle other intents - store context if it's a question
                if (bestMatch.intent === 'questions' || bestMatch.intent === 'greeting') {
                    this.lastIntent = bestMatch.intent;
                    this.lastQuery = normalizedInput;
                }
                
                return {
                    intent: bestMatch.intent,
                    response: bestMatch.response,
                    confidence: bestMatch.confidence,
                };
            }
            
            // Handle incomplete questions (like "does yunus like" without specifying what)
            if (/^(?:does\s*(?:he|yunus)\s*(?:like|know|use|work\s*with))$/i.test(normalizedInput)) {
                return {
                    intent: 'incomplete',
                    response: "I'd be happy to tell you about what Yunus likes or knows! Could you specify what you're interested in? For example, you could ask 'does Yunus like C++?' or 'does Yunus know PHP?'",
                    confidence: 0.8,
                };
            }
            
            // STEP 4: Intelligent fallback chain
            // Check if input resembles any known intent keywords
            const fallbackResult = this.intelligentFallback(normalizedInput);
            if (fallbackResult) {
                this.lastIntent = fallbackResult.intent;
                this.lastQuery = normalizedInput;
                
                const result = {
                    intent: fallbackResult.intent,
                    response: fallbackResult.response,
                    confidence: fallbackResult.confidence,
                };
                
                // Track in conversation history
                this.addToHistory(input, result.response, result.intent, result.confidence);
                return result;
            }
            
            // STEP 5: Check conversation history for related context
            const historyContext = this.getHistoryContext();
            if (historyContext && historyContext.dominantIntent) {
                // If user's input is very short and we have recent context, try to connect
                if (normalizedInput.split(' ').length <= 3 && historyContext.recentIntents.length > 0) {
                    const relatedResponse = this.getRelatedContextResponse(normalizedInput, historyContext);
                    if (relatedResponse) {
                        const result = {
                            intent: historyContext.dominantIntent,
                            response: relatedResponse,
                            confidence: 0.55,
                        };
                        this.addToHistory(input, result.response, result.intent, result.confidence);
                        return result;
                    }
                }
            }
            
            // STEP 6: Final fallback - provide helpful guidance
            const finalResponse = this.getFinalFallbackResponse(normalizedInput);
            const result = {
                intent: 'unknown',
                response: finalResponse,
                examples: this.exampleQuestions,
                confidence: 0,
            };
            
            // Track in conversation history
            this.addToHistory(input, result.response, result.intent, result.confidence);
            
            // ============================================================
            // PERFORMANCE: Cache the response
            // ============================================================
            this.responseCache.set(cacheKey, {
                response: result,
                timestamp: Date.now(),
            });
            
            // Prune old cache entries periodically
            if (this.responseCache.size > 200) {
                const now = Date.now();
                for (const [key, value] of this.responseCache.entries()) {
                    if (now - value.timestamp > this.responseCacheMaxAge) {
                        this.responseCache.delete(key);
                    }
                }
            }
            
            return result;
            } catch (error) {
                // Safety net: if anything fails, return a valid response
                console.error('Error in process():', error);
                return {
                    intent: 'error',
                    response: "I encountered an error processing your request. Please try again or rephrase your question.",
                    examples: this.exampleQuestions,
                    confidence: 0,
                };
            }
        }
        
        /**
         * Intelligent fallback - try to match based on keyword overlap with known intents
         */
        intelligentFallback(input) {
            const lowerInput = input.toLowerCase();
            const words = lowerInput.split(/\s+/).filter(w => w.length > 2);
            
            // Keyword-to-intent mapping for fallback
            const keywordMap = {
                'yunus': ['who_is_yunus', 'what_does_yunus_do'],
                'github': ['yunus_github', 'yunus_open_source'],
                'project': ['yunus_projects', 'portfolio'],
                'work': ['yunus_projects', 'what_does_yunus_do'],
                'skill': ['yunus_skills', 'yunus_technologies'],
                'technology': ['yunus_technologies', 'yunus_skills'],
                'language': ['yunus_technologies', 'bot_language'],
                'education': ['yunus_education', 'yunus_certifications'],
                'certification': ['yunus_certifications', 'yunus_education'],
                'cert': ['yunus_certifications', 'yunus_education'],
                'travel': ['yunus_travel', 'travel'],
                'country': ['yunus_travel', 'where_is_yunus'],
                'countries': ['yunus_travel'],
                'born': ['yunus_birth_age'],
                'age': ['yunus_birth_age'],
                'old': ['yunus_birth_age'],
                'turkish': ['yunus_nationality', 'yunus_birth_age'],
                'turkey': ['yunus_nationality', 'where_is_yunus'],
                'turkiye': ['yunus_nationality', 'where_is_yunus'],
                'istanbul': ['where_is_yunus', 'yunus_nationality'],
                'blog': ['blog', 'yunus_blog_topics'],
                'write': ['yunus_blog_topics', 'blog'],
                'article': ['blog', 'yunus_blog_topics'],
                'math': ['yunus_math', 'yunus_education'],
                'mathematics': ['yunus_math', 'yunus_education'],
                'algorithm': ['yunus_math', 'yunus_skills'],
                'industrial': ['yunus_industrial_automation', 'yunus_projects'],
                'automation': ['yunus_industrial_automation', 'yunus_skills'],
                'plc': ['yunus_industrial_automation'],
                'scada': ['yunus_industrial_automation'],
                'opc': ['yunus_industrial_automation'],
                'mqtt': ['yunus_industrial_automation'],
                'kepserver': ['yunus_industrial_automation', 'yunus_projects'],
                'contact': ['contact', 'yunus_contact_method'],
                'email': ['contact', 'yunus_contact_method'],
                'reach': ['contact', 'yunus_contact_method'],
                'available': ['yunus_availability', 'yunus_contact_method'],
                'hire': ['yunus_availability', 'yunus_contact_method'],
            };
            
            // Count keyword matches for each intent
            const intentScores = {};
            for (const word of words) {
                for (const [keyword, intents] of Object.entries(keywordMap)) {
                    if (word.includes(keyword) || keyword.includes(word)) {
                        for (const intent of intents) {
                            intentScores[intent] = (intentScores[intent] || 0) + 1;
                        }
                    }
                }
            }
            
            // Find best matching intent
            let bestIntent = null;
            let bestScore = 0;
            for (const [intent, score] of Object.entries(intentScores)) {
                if (score > bestScore) {
                    bestScore = score;
                    bestIntent = intent;
                }
            }
            
            if (bestIntent && bestScore >= 1) {
                // Find the response for this intent from semantic targets
                const qaTarget = this.semanticTargets.qa.find(q => q.intent === bestIntent);
                if (qaTarget) {
                    return {
                        intent: bestIntent,
                        response: qaTarget.response,
                        confidence: Math.min(0.45, 0.3 + bestScore * 0.05),
                    };
                }
            }
            
            return null;
        }
        
        /**
         * Get response based on conversation history context
         */
        getRelatedContextResponse(input, historyContext) {
            const lowerInput = input.toLowerCase();
            const dominantIntent = historyContext.dominantIntent;
            
            // If recent context was about a specific topic and user says something brief
            // try to provide a related follow-up
            if (dominantIntent === 'yunus_technologies' || dominantIntent === 'yunus_skills') {
                if (lowerInput.includes('more') || lowerInput.includes('else') || lowerInput.includes('other')) {
                    const qaTarget = this.semanticTargets.qa.find(q => q.intent === 'yunus_github');
                    if (qaTarget) return qaTarget.response;
                }
            }
            
            if (dominantIntent === 'yunus_projects') {
                if (lowerInput.includes('more') || lowerInput.includes('else')) {
                    const qaTarget = this.semanticTargets.qa.find(q => q.intent === 'yunus_skills');
                    if (qaTarget) return qaTarget.response;
                }
            }
            
            if (dominantIntent === 'where_is_yunus' || dominantIntent === 'yunus_nationality') {
                if (lowerInput.includes('more') || lowerInput.includes('why')) {
                    const qaTarget = this.semanticTargets.qa.find(q => q.intent === 'yunus_travel');
                    if (qaTarget) return qaTarget.response;
                }
            }
            
            if (dominantIntent === 'bot_llm' || dominantIntent === 'bot_difference') {
                if (lowerInput.includes('how') || lowerInput.includes('explain')) {
                    const qaTarget = this.semanticTargets.qa.find(q => q.intent === 'bot_embeddings_explanation');
                    if (qaTarget) return qaTarget.response;
                }
            }
            
            return null;
        }
        
        /**
         * Final fallback response with contextual suggestions
         */
        getFinalFallbackResponse(input) {
            const lowerInput = input.toLowerCase();
            
            // Check for frustration signals
            if (/(?:stupid|dumb|useless|crap|damn|fuck|shit|wtf|wth)/i.test(lowerInput)) {
                return "I'm sorry I'm not understanding you well. I'm still learning! You can try being more specific, or check out the navigation buttons above. Is there a particular section you're looking for?";
            }
            
            // Check for very short inputs
            if (input.trim().split(/\s+/).length <= 2) {
                return "I need a bit more context to help you. Try asking a complete question like 'who is yunus' or 'show me portfolio'. What would you like to know?";
            }
            
            // Check if input contains any Yunus-related keywords
            if (/(?:yunus|yun|github|code|project|work|blog|travel|portfolio)/i.test(lowerInput)) {
                return "I think you're asking about something related to Yunus, but I'm not sure exactly what. Could you try rephrasing? For example: 'what projects has yunus made?' or 'show me yunus github'.";
            }
            
            // Check if input seems like a navigation request
            if (/(?:go|show|open|take|navigate|visit|see|view)/i.test(lowerInput)) {
                return "I can help you navigate! Try saying 'take me to portfolio' or 'show me the about page'. Which section would you like to visit?";
            }
            
            // Generic helpful fallback
            return "I'm not sure I understand that question. I can help you with: questions about Yunus (background, skills, projects), navigation to different sections, or explaining how I work. What would you like to know?";
        }
    }
    
    // Export to global scope
    window.YunoBotMLEngine = YunoBotMLEngine;
})();
