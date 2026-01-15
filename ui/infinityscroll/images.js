import {
   getNodeSize,
   notify,
   toRect,
   waitTime
} from "/infinityscroll/helper.js";

import {
   withRequestSlot
} from "/infinityscroll/queue.js";

import {
   scrollData
} from "/infinityscroll/scroll.js";

import {
   dbg
} from "/infinityscroll/debug.js";

const imgwait = `<svg class="worm-spinner" width="40" height="40" viewBox="0 0 50 50" style="position: absolute; top: 50%; left: 50%; transform: translate(-50%, -50%); z-index: 10;">
  <circle cx="25" cy="25" r="20" fill="none" stroke="currentColor" stroke-width="4" stroke-dasharray="31.4 31.4" stroke-linecap="round">
    <animateTransform attributeName="transform" type="rotate" from="0 25 25" to="360 25 25" dur="1s" repeatCount="indefinite" />
  </circle>
</svg>`;

// transparent 1x1 gif as placeholder src while loading
const spinnerDataUri = 'data:image/gif;base64,R0lGODlhAQABAIAAAAAAAP///yH5BAEAAAAALAAAAAABAAEAAAIBRAA7';

export async function loadImgs(list, page) {
   const sd = scrollData(list);
   const imgs = Array.from(page.querySelectorAll('img'));
   const preped = [];
   if (imgs.length) {

      for (const img of imgs) {
         if (!isImgLoaded(img)) {
            preped.push({spin: prepareImg(list, img, page), img});
         }
      }

      for (const prep of preped) {
         notify(list, 'imageloadstart', prep);
         await loadImg(list, prep.img, page);
         if (imgs.every(i => i?.dataset?.mainimgloaded === '1')) {
            notify(list, 'imagesloaded', page);
         }
      }
   } else {
      notify(list, 'imagesloaded', page);
   }
}

function isImgLoaded(img) {
   return img.isConnected && img.complete && img.naturalWidth > 0;
}

async function loadImg(list, img, page) {
   const sd = scrollData(list);
   if (!sd.fetchimg) {
      return;
   }

   return withRequestSlot(list, img, () => {
      removeImgSpinner(list, img, page);
   });
}

function prepareImg(list, img, page) {
   const sd = scrollData(list);
   let src = img.getAttribute('src');
   let datasrc = img.getAttribute('data-src') || '';
   if (!datasrc && src !== spinnerDataUri) {
      img.setAttribute('data-src', src)
   }
   fixImgRatio(list, img, page);
   // img.setAttribute('src', spinnerDataUri);
   return createImgSpinner(list, img, page);
}

function createImgSpinner(list, img, page) {
   const sd = scrollData(list);
   const wrap = img.parentElement;
   if (wrap && !wrap.querySelector('[data-scroll-spin="1"]')) {
      const s = document.createElement('div');
      wrap.style.position = 'relative';
      s.style.position = 'absolute';
      s.style.height = getNodeSize(page).h + 'px';
      s.dataset.scrollSpin = '1';
      s.innerHTML = imgwait;
      wrap.append(s);
      return s;
   }
   return null;
}

function removeImgSpinner(list, img, page) {
   page.querySelector('[data-scroll-spin="1"]')?.remove();
}


export function fixImgRatio(list, img, outer) {

      return new Promise((resolve) => {
         const a = img.parentElement;

         // never let the image expand the <a>
         a.style.overflow = 'hidden';

         // always fit image into parent <a>
         img.style.display = 'block';
         img.style.maxWidth = '100%';
         img.style.maxHeight = '100%';
         img.style.width = '100%';
         img.style.height = '100%';
         img.style.objectFit = 'contain';

         const done = () => {
            // use real image aspect ratio to size the <a>
            const w = img.naturalWidth || 1;
            const h = img.naturalHeight || 1;

            const aw = a.getBoundingClientRect().width || 1;
            a.style.height = Math.round(aw * (h / w)) + 'px';

            img.removeEventListener('load', done);
            img.removeEventListener('error', done);
            resolve();
         };

         img.addEventListener('load', done, { once: true });
         img.addEventListener('error', done, { once: true });
      });
}


function isImgVisible(list, img) {
   const sd = scrollData(list);
   const lr = toRect(list.getBoundingClientRect());
   lr.b += lr.b;
   lr.t = -500;
   lr.l = 0;
   lr.r += lr.r;
   const r = toRect(img.getBoundingClientRect());
   // dbg(list, 'imgvis', {lr, r})
   // dbg(list, 'imgvis', {
   //    a: [
   //       !(r.b < lr.t || r.t > lr.b || r.r < lr.l || r.l > lr.r),
   //       'r.b < lr.t', r.b < lr.t,
   //       'r.t > lr.b', r.t > lr.b,
   //       'r.r < lr.l', r.r < lr.l,
   //       'r.l > lr.r', r.l > lr.r
   //    ]
   // })
   return !(r.b < lr.t || r.t > lr.b || r.r < lr.l || r.l > lr.r);
}
