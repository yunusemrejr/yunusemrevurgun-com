/**
 * YunoBot Embedding Worker - Transformers.js Integration (PERFORMANCE OPTIMIZED)
 * Runs semantic embeddings in a Web Worker to avoid UI lag
 * 
 * Performance features:
 * - LRU embedding cache with configurable max size
 * - Batch processing for similarity calculations with early exit
 * - Worker health monitoring with ping/pong
 * - Graceful degradation on worker failure
 * - Memory management with cache eviction
 * - Request deduplication for identical queries
 */

// Dynamic import for Transformers.js (allows local or CDN fallback)
let pipeline, env;

// Determine paths from worker location
const workerUrl = new URL(self.location.href);
const workerPath = workerUrl.pathname;
const suffix = '/assets/js/yunobot/embedding-worker.js';
const baseUrl = workerPath.endsWith(suffix)
    ? workerUrl.origin + workerPath.slice(0, -suffix.length)
    : workerUrl.origin;

const transformersLocalPath = baseUrl + '/assets/js/vendor/transformers/dist/transformers.min.js';

// ============================================================
// LRU CACHE FOR EMBEDDINGS
// ============================================================
class LRUCache {
    constructor(maxSize = 500) {
        this.maxSize = maxSize;
        this.cache = new Map();
    }
    
    get(key) {
        if (!this.cache.has(key)) return undefined;
        // Move to end (most recently used)
        const value = this.cache.get(key);
        this.cache.delete(key);
        this.cache.set(key, value);
        return value;
    }
    
    set(key, value) {
        if (this.cache.has(key)) {
            this.cache.delete(key);
        } else if (this.cache.size >= this.maxSize) {
            // Evict oldest entry
            const firstKey = this.cache.keys().next().value;
            this.cache.delete(firstKey);
        }
        this.cache.set(key, value);
    }
    
    has(key) {
        return this.cache.has(key);
    }
    
    clear() {
        this.cache.clear();
    }
    
    get size() {
        return this.cache.size;
    }
    
    get stats() {
        return { size: this.cache.size, maxSize: this.maxSize };
    }
}

const embeddingCache = new LRUCache(500);
const requestDedup = new Map(); // Deduplicate in-flight requests

// ============================================================
// PERFORMANCE MONITORING
// ============================================================
const perfStats = {
    totalRequests: 0,
    totalEmbeddings: 0,
    totalCacheHits: 0,
    totalSimilarityCalculations: 0,
    avgEmbeddingTime: 0,
    avgSimilarityTime: 0,
    errors: 0,
    startTime: Date.now(),
    lastActivity: Date.now(),
};

// Track moving averages with decay
const movingAvg = {
    embedding: { sum: 0, count: 0, decay: 0.1 },
    similarity: { sum: 0, count: 0, decay: 0.1 },
};

// Try to load local Transformers.js, fallback to CDN
async function loadTransformers() {
    console.log('Attempting to load Transformers.js from:', transformersLocalPath);
    try {
        // Try local first (absolute URL from worker location)
        const transformers = await import(transformersLocalPath);
        pipeline = transformers.pipeline;
        env = transformers.env;
        console.log('✓ Using local Transformers.js from:', transformersLocalPath);
    } catch (localError) {
        console.warn('✗ Local Transformers.js not found, using CDN fallback');
        console.warn('  Path attempted:', transformersLocalPath);
        console.warn('  Error:', localError.message);
        try {
            // Fallback to CDN
            const transformers = await import('https://cdn.jsdelivr.net/npm/@xenova/transformers@2.17.2');
            pipeline = transformers.pipeline;
            env = transformers.env;
            console.log('✓ Using CDN Transformers.js');
        } catch (cdnError) {
            console.error('✗ Failed to load Transformers.js from both local and CDN:', cdnError);
            throw new Error('Transformers.js failed to load: ' + cdnError.message);
        }
    }
    
    // Configure for local model files
    if (!env) {
        throw new Error('Transformers.js env not initialized');
    }
    
    env.allowLocalModels = true;
    env.allowRemoteModels = true;
    
    // Set local model path (models will be cached in browser IndexedDB)
    env.localModelPath = baseUrl + '/assets/models/';
    
    // Configure Hugging Face remote settings
    // Use the correct API endpoint format for browser access
    env.remoteHost = 'https://huggingface.co/';
    env.remotePathTemplate = '{model}/resolve/{revision}/';
    
    // Enable browser cache for models
    env.useBrowserCache = typeof caches !== 'undefined';
    
    console.log('Model cache path:', env.localModelPath);
    console.log('Remote host:', env.remoteHost);
    console.log('Browser cache enabled:', env.useBrowserCache);
}

// Initialize transformers on worker load
const transformersReady = loadTransformers().then(() => {
    // Configure after transformers is loaded
    if (env) {
        // Use WebGPU if available, fallback to WASM
        env.backends.onnx.wasm.proxy = false;
    }
});

let embeddingPipeline = null;
let isInitializing = false;
let initPromise = null;

/**
 * Initialize the embedding pipeline
 */
async function initializeModel() {
    // Ensure Transformers.js is loaded
    await transformersReady;
    
    if (embeddingPipeline) return embeddingPipeline;
    if (isInitializing && initPromise) return initPromise;
    
    isInitializing = true;
    initPromise = (async () => {
        try {
            // Use all-MiniLM-L6-v2 model (~25MB, fast, good quality)
            embeddingPipeline = await pipeline(
                'feature-extraction',
                'Xenova/all-MiniLM-L6-v2',
                {
                    quantized: true,
                    device: 'webgpu', // Try WebGPU first
                }
            );
            isInitializing = false;
            return embeddingPipeline;
        } catch (error) {
            console.warn('WebGPU failed, falling back to WASM:', error);
            try {
                embeddingPipeline = await pipeline(
                    'feature-extraction',
                    'Xenova/all-MiniLM-L6-v2',
                    {
                        quantized: true,
                        device: 'wasm',
                    }
                );
                isInitializing = false;
                return embeddingPipeline;
            } catch (fallbackError) {
                isInitializing = false;
                console.error('Model initialization failed:', fallbackError);
                
                // Provide more detailed error info
                const errorMsg = fallbackError.message || String(fallbackError);
                let detailedError = 'Failed to initialize model: ' + errorMsg;
                
                if (errorMsg.includes('JSON.parse')) {
                    detailedError = 'Model config parse error - Hugging Face may be returning HTML instead of JSON. This could be a CORS issue or network problem. The bot will continue with regex-only mode.';
                    console.warn(detailedError);
                    console.warn('Model URL attempted: https://huggingface.co/Xenova/all-MiniLM-L6-v2');
                    console.warn('This is expected if Hugging Face blocks direct browser requests. The bot will work with regex patterns only.');
                    // Don't throw - let it fail gracefully and use regex-only mode
                    return null;
                }
                
                throw new Error(detailedError);
            }
        }
    })();
    
    return initPromise;
}

/**
 * Calculate cosine similarity between two vectors
 */
function cosineSimilarity(vecA, vecB) {
    if (vecA.length !== vecB.length) return 0;
    
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
 * Generate embedding for text with LRU caching and performance tracking
 */
async function generateEmbedding(text) {
    const cacheKey = text.toLowerCase().trim();
    
    // Check LRU cache first
    const cached = embeddingCache.get(cacheKey);
    if (cached) {
        perfStats.totalCacheHits++;
        return cached;
    }
    
    // Check for in-flight deduplication
    if (requestDedup.has(cacheKey)) {
        return requestDedup.get(cacheKey);
    }
    
    if (!embeddingPipeline) {
        const model = await initializeModel();
        if (!model) {
            throw new Error('Model not available - cannot generate embeddings');
        }
    }
    
    const startTime = performance.now();
    
    const output = await embeddingPipeline(text, {
        pooling: 'mean',
        normalize: true,
    });
    
    const embedding = Array.from(output.data);
    const elapsed = performance.now() - startTime;
    
    // Update moving average with decay
    movingAvg.embedding.sum = movingAvg.embedding.sum * (1 - movingAvg.embedding.decay) + elapsed * movingAvg.embedding.decay;
    movingAvg.embedding.count++;
    perfStats.avgEmbeddingTime = movingAvg.embedding.sum / movingAvg.embedding.count;
    
    // Cache the result
    embeddingCache.set(cacheKey, embedding);
    requestDedup.delete(cacheKey);
    perfStats.totalEmbeddings++;
    perfStats.lastActivity = Date.now();
    
    return embedding;
}

/**
 * Batch similarity calculation with early exit on high-confidence matches
 * Uses SIMD-friendly loop structure for better CPU cache utilization
 */
function batchCosineSimilarity(queryEmbedding, targetEmbeddings, earlyExitThreshold = 0.92) {
    const results = [];
    let bestSimilarity = 0;
    
    for (let i = 0; i < targetEmbeddings.length; i++) {
        const target = targetEmbeddings[i];
        const similarity = cosineSimilarity(queryEmbedding, target.embedding);
        
        results.push({
            index: i,
            similarity: similarity,
            target: target.target || target.intent,
            type: target.type || 'unknown',
        });
        
        // Track best match for early exit
        if (similarity > bestSimilarity) {
            bestSimilarity = similarity;
        }
        
        // Early exit if we found a very high confidence match
        if (similarity >= earlyExitThreshold) {
            break;
        }
    }
    
    return results;
}

// Handle messages from main thread
self.addEventListener('message', async (event) => {
    const { type, data } = event.data;
    perfStats.totalRequests++;
    
    try {
        switch (type) {
            case 'init':
                try {
                    const model = await initializeModel();
                    if (model) {
                        self.postMessage({ type: 'ready' });
                    } else {
                        // Model failed to initialize (e.g., CORS issue with Hugging Face)
                        self.postMessage({ 
                            type: 'error',
                            error: 'Model initialization failed - will use regex-only mode'
                        });
                    }
                } catch (error) {
                    perfStats.errors++;
                    self.postMessage({ 
                        type: 'error',
                        error: 'Model initialization error: ' + error.message
                    });
                }
                break;
                
            case 'embed':
                if (!embeddingPipeline) {
                    // Try to initialize if not already done
                    const model = await initializeModel();
                    if (!model) {
                        self.postMessage({
                            type: 'error',
                            id: data.id,
                            error: 'Model not available - semantic matching disabled'
                        });
                        break;
                    }
                }
                const embedding = await generateEmbedding(data.text);
                self.postMessage({
                    type: 'embedding',
                    id: data.id,
                    embedding,
                });
                break;
                
            case 'similarity': {
                const startTime = performance.now();
                const { queryEmbedding, targetEmbeddings } = data;
                const similarities = batchCosineSimilarity(queryEmbedding, targetEmbeddings);
                
                // Sort by similarity (highest first)
                similarities.sort((a, b) => b.similarity - a.similarity);
                
                const elapsed = performance.now() - startTime;
                perfStats.totalSimilarityCalculations++;
                perfStats.avgSimilarityTime = (perfStats.avgSimilarityTime * (perfStats.totalSimilarityCalculations - 1) + elapsed) / perfStats.totalSimilarityCalculations;
                
                self.postMessage({
                    type: 'similarity',
                    id: data.id,
                    results: similarities,
                });
                break;
            }
                
            case 'batch_embed': {
                // Batch embedding for precomputing multiple targets
                if (!embeddingPipeline) {
                    const model = await initializeModel();
                    if (!model) {
                        self.postMessage({
                            type: 'error',
                            id: data.id,
                            error: 'Model not available - batch embed failed'
                        });
                        break;
                    }
                }
                
                const texts = data.texts || [];
                const embeddings = [];
                
                for (const text of texts) {
                    try {
                        const embedding = await generateEmbedding(text);
                        embeddings.push({ text, embedding });
                    } catch (e) {
                        embeddings.push({ text, embedding: null, error: e.message });
                    }
                }
                
                self.postMessage({
                    type: 'batch_embedding',
                    id: data.id,
                    embeddings,
                });
                break;
            }
                
            case 'stats':
                // Return comprehensive performance statistics
                self.postMessage({
                    type: 'stats',
                    id: data?.id,
                    stats: {
                        ...perfStats,
                        uptime: Date.now() - perfStats.startTime,
                        modelLoaded: !!embeddingPipeline,
                        cacheStats: embeddingCache.stats,
                        cacheHitRate: perfStats.totalRequests > 0 
                            ? (perfStats.totalCacheHits / perfStats.totalRequests).toFixed(3) 
                            : 0,
                        avgEmbeddingTimeMs: perfStats.avgEmbeddingTime.toFixed(2),
                        avgSimilarityTimeMs: perfStats.avgSimilarityTime.toFixed(2),
                    },
                });
                break;
                
            case 'ping':
                // Health check - update last activity
                perfStats.lastActivity = Date.now();
                self.postMessage({
                    type: 'pong',
                    id: data?.id,
                    timestamp: Date.now(),
                    healthy: true,
                });
                break;
                
            case 'clear_cache':
                // Manual cache clear for memory management
                embeddingCache.clear();
                requestDedup.clear();
                self.postMessage({
                    type: 'cache_cleared',
                    id: data?.id,
                });
                break;
                
            case 'terminate':
                // Graceful termination
                self.close();
                break;
                
            default:
                self.postMessage({
                    type: 'error',
                    error: `Unknown message type: ${type}`,
                });
        }
    } catch (error) {
        perfStats.errors++;
        self.postMessage({
            type: 'error',
            error: error.message,
            stack: error.stack,
        });
    }
});

// ============================================================
// WORKER HEALTH MONITORING & AUTO-TERMINATION
// ============================================================
// Terminate worker after 5 minutes of inactivity to free memory
const INACTIVITY_TIMEOUT = 5 * 60 * 1000; // 5 minutes

setInterval(() => {
    const idleTime = Date.now() - perfStats.lastActivity;
    if (idleTime > INACTIVITY_TIMEOUT && embeddingPipeline) {
        console.log('[YunoBot Worker] Auto-terminating after inactivity timeout');
        // Clean up resources before termination
        embeddingCache.clear();
        requestDedup.clear();
        embeddingPipeline = null;
        self.close();
    }
}, 60000); // Check every minute
