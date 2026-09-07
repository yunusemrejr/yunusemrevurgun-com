/** Shared training/inference text features. Keep Unicode and C++/C# identities. */
((global) => {
    const normalize = value => String(value).normalize('NFKC').toLowerCase()
        .replace(/ı/g, 'i').normalize('NFD').replace(/\p{M}/gu, '');
    const tokenize = value => normalize(value).replace(/[^\p{L}\p{N}+#\s]/gu, ' ')
        .split(/\s+/).filter(Boolean).slice(0, 256);
    function charTrigrams(word) {
        const value = '<' + word + '>', grams = [];
        for (let i = 0; i <= value.length - 3; i++) grams.push(value.slice(i, i + 3));
        return grams;
    }
    function hash32(value) {
        let hash = 0x811c9dc5;
        for (let i = 0; i < value.length; i++) hash = Math.imul(hash ^ value.charCodeAt(i), 0x01000193);
        return hash >>> 0;
    }
    global.YunoBotText = { normalize, tokenize, charTrigrams, hash32 };
})(typeof window === 'undefined' ? globalThis : window);
