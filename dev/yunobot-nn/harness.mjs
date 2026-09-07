import {readFileSync} from 'node:fs';
import vm from 'node:vm';
export function loadBot(options={}){
    const sandbox={console,setTimeout,clearTimeout,Float32Array,Int8Array,Uint8Array,TextEncoder,TextDecoder,URL,performance,atob:s=>Buffer.from(s,'base64').toString('binary')};
    Object.assign(sandbox,options);
    sandbox.window=sandbox;sandbox.FULL_BASE_PATH='/';
    vm.createContext(sandbox);
    for(const name of ['text','answers','nn-weights','nn-engine','knowledge-pack','kb','ml-engine'])vm.runInContext(readFileSync(new URL(`../../assets/js/yunobot/${name}.js`,import.meta.url),'utf8'),sandbox);
    return {engine:new sandbox.YunoBotMLEngine(),sandbox};
}
