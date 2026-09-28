// Pure helpers (no DOM): shared by app.js and node tests.
// Fine-tuned prompt style ("compact"): the model has absorbed the task instruction, so
// only a short header is sent. Must match COMPACT_INSTRUCTION in scripts/train/common.py.
export const INSTRUCTION = 'Analyze this claim.';

export function buildPrompt(userInput) {
  return `${INSTRUCTION}\n\nINPUT:\n${userInput.trim()}\n\nANALYSIS:\n`;
}

const EN_STOP = new Set(('the be to of and a in that have i it for not on with he as you do at this but his by from they we say her she or an will my one all would there their what so up out if about who get which go me when make can like no just him know take into your some them').split(' '));
const FOREIGN_STOP = {
  de: ['der', 'die', 'das', 'und', 'ist', 'nicht', 'eine', 'einer', 'sind', 'wird', 'auch', 'oder'],
  fr: ['les', 'des', 'une', 'est', 'pas', 'pour', 'que', 'qui', 'dans', 'plus', 'comme', 'avec'],
  es: ['los', 'las', 'una', 'porque', 'como', 'pero', 'para', 'este', 'está', 'tiene', 'hace', 'muy'],
  it: ['che', 'una', 'sono', 'come', 'più', 'anche', 'della', 'questo', 'molto', 'solo'],
  pt: ['uma', 'como', 'para', 'este', 'muito', 'também', 'porque', 'sobre', 'entre'],
  nl: ['het', 'een', 'van', 'niet', 'ook', 'omdat', 'maar', 'deze', 'wordt', 'kunnen'],
  tr: ['bir', 'için', 'değil', 'çünkü', 'olarak', 'daha', 'gibi', 'kadar', 'ancak', 'hakkında'],
};
const NON_LATIN = /[\u0400-\u04FF\u0600-\u06FF\u0900-\u097F\u0E00-\u0E7F\u3040-\u30FF\u3400-\u4DBF\u4E00-\u9FFF\uAC00-\uD7AF]/g;

export function looksNonEnglish(text) {
  const letters = (text.match(/\p{L}/gu) || []).length;
  if (!letters) return false;
  const nonLatin = (text.match(NON_LATIN) || []).length;
  if (nonLatin / letters > 0.25) return true;
  const words = text.toLowerCase().match(/[a-zà-ÿäöüßçğıİöşü\u00e0-\u00ff']+/g) || [];
  if (words.length < 6) return false;
  let en = 0;
  for (const w of words) if (EN_STOP.has(w)) en++;
  const accented = (text.match(/[à-ÿā-ſ]/g) || []).length;
  if (accented >= 4 && en <= 1) return true; // e.g. Turkish, heavy French/Spanish
  let best = 0;
  for (const list of Object.values(FOREIGN_STOP)) {
    let hits = 0;
    for (const w of words) if (list.includes(w)) hits++;
    best = Math.max(best, hits);
  }
  return best >= 2 && best > en + 1;
}
