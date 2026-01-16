import {
   scrollData
} from "/infinityscroll/scroll.js";

export function dbg(list, k, obj, addtimediff) {
   const sd = scrollData(list);
   if (!sd?.debug) {
      return;
   }
   const currenttime = addtimediff ? performance.now() : '';
   const seen = new WeakSet();
   const out = [k];

   // avoid exploding logs
   const MAX_DEPTH = 4;
   const MAX_KEYS = 60;

   // dom + other complex objects we skip
   function isComplex(v) {
      if (!v || typeof v !== 'object') {
         return false;
      }

      // DOM / browser built-ins
      if (typeof Node !== 'undefined' && v instanceof Node) {
         return true;
      }
      if (typeof Window !== 'undefined' && v instanceof Window) {
         return true;
      }
      if (typeof Document !== 'undefined' && v instanceof Document) {
         return true;
      }
      if (typeof Event !== 'undefined' && v instanceof Event) {
         return true;
      }

      // non-plain objects
      const proto = Object.getPrototypeOf(v);
      if (proto !== Object.prototype && proto !== null && !Array.isArray(v)) {
         return true;
      }

      // very large structures (Map/Set/Date/etc) treated as complex
      if (v instanceof Map || v instanceof Set || v instanceof Date || v instanceof RegExp) {
         return true;
      }

      return false;
   }

   function pushKV(path, v) {
      out.push(`${path}:`, v);
   }

   function walk(v, path, depth) {
      if (v === null || v === undefined) {
         return pushKV(path, v);
      }

      const t = typeof v;

      if (t === 'string' || t === 'number' || t === 'boolean' || t === 'bigint') {
         return pushKV(path, v);
      }
      if (t === 'function') {
         return pushKV(path, '[fn]');
      }
      if (t === 'symbol') {
         return pushKV(path, v.toString());
      }

      if (isComplex(v)) {
         return pushKV(path, `[${v.constructor?.name ? 'y' : 'n'}]`);
      }

      if (depth >= MAX_DEPTH) {
         return pushKV(path, '[maxd]');
      }

      if (typeof v === 'object') {
         if (seen.has(v)) {
            return pushKV(path, '[cir]');
         }
         seen.add(v);

         if (Array.isArray(v)) {
            pushKV(path, `[(${v.length})]`);
            for (let i = 0; i < v.length && out.length < MAX_KEYS; i++) {
               walk(v[i], `${path}[${i}]`, depth + 1);
            }
            return;
         }

         const entries = Object.entries(v);
         // pushKV(path, `[obj(${entries.length})]`);
         for (let i = 0; i < entries.length && out.length < MAX_KEYS; i++) {
            const [kk, vv] = entries[i];
            walk(vv, path ? `${path}.${kk}` : kk, depth + 1);
         }
         return;
      }

      pushKV(path, v);
   }

   walk(obj, '', 0);
   if (currenttime) {
      console.log(currenttime, ...out);
   } else {
      console.log(...out);
   }
}

