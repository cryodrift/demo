import {
   scrollData
} from "/infinityscroll/scroll.js";

export function createQueue(onError = null, resolveMaxTime = 250, maxRetries = 10) {
   // objKey -> {fn, resolve, promise, tries}
   const pending = new WeakMap();
   const q = [];
   let running = false;

   const runWithTimeout = (p, ms) =>
      Promise.race([
         p,
         new Promise((_, rej) => setTimeout(() => rej(new Error('timeout')), ms)),
      ]);

   async function runner() {
      if (running) return;
      running = true;

      while (q.length) {
         const objKey = q.pop();
         const t = pending.get(objKey);
         if (!t) continue;

         try {
            const value = await runWithTimeout(
               Promise.resolve().then(() => t.fn(objKey)),
               resolveMaxTime
            );

            pending.delete(objKey);
            t.resolve(value);

         } catch (err) {
            t.tries++;

            if (onError) {
               onError(
                  err.message === 'timeout' ? 'queuetimeout' : 'queueerror',
                  err,
                  objKey,
                  t.tries
               );
            }

            if (t.tries >= maxRetries) {
               // give up
               pending.delete(objKey);
               t.resolve(undefined);
            } else {
               // move to end, retry later
               q.push(objKey);
            }
         }
      }

      running = false;
   }

   return {
      add(objKey, asyncFn) {
         const existing = pending.get(objKey);
         if (existing) return existing.promise;

         let resolve;
         const promise = new Promise((res) => { resolve = res; });

         pending.set(objKey, {
            fn: asyncFn,
            resolve,
            promise,
            tries: 0,
         });

         q.push(objKey);
         runner();

         return promise;
      },

      size() {
         return q.length;
      },

      cancel(objKey) {
         const t = pending.get(objKey);
         if (!t) return false;
         pending.delete(objKey);
         t.resolve(undefined);
         return true;
      }
   };
}

export async function withRequestSlot(list, key, fn, waitforfn) {
   // return fn(key);
   const sd = scrollData(list);
   sd.reqQueue = sd.reqQueue || createQueue(console.log, sd.maxresolveTime,sd.maxRetries);
   return sd.reqQueue.add(key, fn, waitforfn);
   // return fn();
}
