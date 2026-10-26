// Execute the production input listener without a browser or extra dependencies.
import { readFileSync } from 'node:fs';
import { runInNewContext } from 'node:vm';
import assert from 'node:assert/strict';

const source=readFileSync(new URL('../../public/js/petugas-operational.js',import.meta.url),'utf8');
const listener=source.slice(source.indexOf("    box.addEventListener('input'"),source.indexOf("    box.addEventListener('change'"));
assert(listener.length>0,'Production input listener must exist');
let handle;
let calculations=0;
runInNewContext(listener,{box:{addEventListener:(_,callback)=>handle=callback},calculate:()=>calculations++});
for(const [value,expected] of [['10.321','10.32'],['10.32999','10.32'],['0.019','0.01'],['10.32','10.32'],['10','10'],['',''],['10000.999','10000.99']]){
    const input={value,matches:()=>true};
    handle({target:input});
    assert.equal(input.value,expected);
}
const unrelated={value:'123.456',matches:()=>false};
handle({target:unrelated});
assert.equal(unrelated.value,'123.456');
assert.equal(calculations,8);
assert.equal(Math.floor((1032*1200+50)/100),12384,'HVS 10.32 kg must equal Rp12.384');
console.log('PASS: typed/pasted excess decimals truncated, existing values preserved, calculation triggered.');
