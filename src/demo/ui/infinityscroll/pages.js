import {
   dom,
   getQueryParam
} from '/system.js';

import {
   fetchcomponents
} from '/component.js';

import {
   withRequestSlot
} from "/infinityscroll/queue.js";

import {
   getNodeSize,
   getNodeSpace, getPos,
   getScrollbarinfo,
   notify,
   observePosition,
   observeVisibility,
   removeNode, replaceNode, scrollAnchorNone,
   setMinMax,
   sizeMax,
   sizeMin,
   waitTime
} from "/infinityscroll/helper.js";

import {
   loadImgs
} from "/infinityscroll/images.js";

import {
   dbg
} from "/infinityscroll/debug.js";

import {
   scrollData
} from "/infinityscroll/scroll.js";


const phwait = `<div class="kitt-spinner" style="position:absolute;top:50%;left:0;width:100%;height:4px;background:#fff;transform:translateY(-50%);overflow:hidden">
  <div style="position:absolute;top:0;left:-10%;width:10%;height:100%;background:#000;animation:kitt-move 2s infinite ease-in-out"></div>
  <style>
    @keyframes kitt-move{
      0%,100%{left:-10%}
      50%{left:100%}
    }
  </style>
</div>`;

const ABORTKEY = Symbol('abortcontroller')

export function stopNodeLoad(node) {
   node[ABORTKEY]?.abort?.();
}

export function pageisCached(list, page) {
   const sd = scrollData(list);
   const vpnr = getPagenr(page);
   return sd.cache[vpnr]
}

export async function loadPage(list, ph) {
   const sd = scrollData(list);
   const vpnr = getPagenr(ph);
   ph[ABORTKEY] = new AbortController();

   if (sd.fetchcomp) {
      notify(list, 'loadpage', ph)
      let data;
      if (sd.cache[vpnr]) {
         data = sd.cache[vpnr];
      } else {
         data = await withRequestSlot(list, ph, async (ph) => {
            return await fetchcomponents(sd.component, sd.url, sd.referer, {[sd.queryvar]: String(vpnr)}, true, ph[ABORTKEY].signal);
         }, () => waitTime(sd.waitforfetch))
      }

      if (data) {
         const comp = dom(data.components[sd.component]);

         // console.log(comp, data.components[sd.component])
         const page = getFirstPage(list, comp);

         if (page) {
            sd.cache[vpnr] = data;
         }
         if (!ph.isConnected) {
            return;
         }

         const psize = getNodeSize(ph)
         setMinMax(page, psize, psize)
         const cols = getColsCount(list)
         scrollAnchorNone(list, page)
         notify(list, 'pageloaded', page)
         await loadImgs(list, page)
         await replaceNode(list, ph, page)

         observeVisibility(list, page)
         observePosition(list, page)


         if (sd.fetchimg && cols === 1) {
            psize.h = null;
            psize.w = null;
         }
         setMinMax(page, psize, psize)

      }
   }
}


export function getPageNrFromUrl(list) {
   const sd = scrollData(list);
   return parseInt(getQueryParam(sd.queryvar) || 0)
}

export function getFirstPage(list, parentnode) {
   const sd = scrollData(list);
   const elem = parentnode || sd.iList;
   return elem.querySelector(sd.pageselector);
}

export function getLastPage(list, parentnode) {
   const sd = scrollData(list);
   const elem = parentnode || sd.iList;
   return [...elem.querySelectorAll(sd.pageselector)].pop();
}

export function getFirstRealPage(list, parentnode) {
   const sd = scrollData(list);
   const elem = parentnode || sd.iList;
   return elem.querySelector(':not(' + sd.placeholderselector + ')');
}

export function getLastRealPage(list, parentnode) {
   const sd = scrollData(list);
   const elem = parentnode || sd.iList;
   return [...elem.querySelectorAll(':not(' + sd.placeholderselector + ')')].pop();
}


export function getPage(list, nr) {
   const sd = scrollData(list);
   return list.querySelector(sd.pageselectorNr.replace('{nr}', nr));
}

export function getPages(list) {
   const sd = scrollData(list);
   return [...list.querySelectorAll(sd.pageselector)];
}

export function getPagenr(node) {
   return parseInt(node?.dataset?.scrollablePage || 0, 10);
}

export function getRowsCount(list) {
   const sd = scrollData(list);
   const pH = sd.curplaceholderSize.h;
   const h = getNodeSize(list).h;
   const out = Math.max(1, Math.floor(h / pH));
   // dbg(list,'rowcnt', {out})
   return out;
}

export function getRow(list, node) {
   const sd = scrollData(list);
   const h = sd.curplaceholderSize.h;
   const nT = getPos(list, node).t;
   const out = Math.ceil(h / nT);
   // dbg(list,'rowcnt', {out})
   return out;

}

export function isPage(list, pnr) {
   const sd = scrollData(list);
   return pnr >= 0 && pnr <= sd.maxPages;
}


export function getColsCount(list) {
   const sd = scrollData(list);
   const pW = sd.curplaceholderSize.w;
   const w = getNodeSpace(list).w;
   const out = Math.min(Math.max(1, Math.floor(w / pW)), sd.maxcols);
   // dbg(list, 'Columns', {rawcount: w / pW})
   return out;
}


export function fixCurPagePos(list) {
   const sd = scrollData(list);
   const mainpage = getPage(list, getPageNrFromUrl(list));

   const cc = getColsCount(list)
   if (cc < 2) {
      return;
   }
   let left = getPos(list, mainpage).l;
   while (left > 0 && getPagenr(mainpage) + 3 < sd.maxPages) {
      dbg(list, 'fixpos', {left, nr: getPagenr(mainpage) + 3, mp: sd.maxPages});
      getFirstPage(list).remove();
      left = getPos(list, mainpage).l;
   }
}


export function removeDistantRows(list) {
   const sd = scrollData(list);
   let last = 0;

   let sbi = getScrollbarinfo(list);
   let hs = sbi.handlesize;
   let iListH = getNodeSize(sd.iList).h
   const listH = getNodeSpace(list).h
   // dbg(list, 'removeDistantRows', {big: hs >= sd.minscrollhandlesize, hpos: sbi.handlepos, hsMin: sd.minscrollhandlesize})

   // abwechselnd placeholder adden prepend append
   while (iListH > (listH * 6) && hs < sd.minscrollhandlesize && !(hs > sd.scrollhandlesize) && last < 70) {
      for (let a = 0, b = getColsCount(list); a < b; a++) {
         if (sd.dir === 'up') {
            removeNode(list, getLastPage(list))
         } else {
            removeNode(list, getFirstPage(list))
         }
      }
      sbi = getScrollbarinfo(list);
      hs = sbi.handlesize;
      last++;
      iListH = getNodeSize(sd.iList).h
      // dbg(list, 'removeDistantRows', {hsMin: sd.minscrollhandlesize, hs, mode, hpos: sbi.handlepos, last})


      if (sbi.handlepos > 50) {
         return;
      }

   }
}

export function createPlaceholdersRange(list, mode, vpnr) {
   const sd = scrollData(list);
   const iList = sd.iList;

   for (let a = 0, b = getColsCount(list) * sd.addmultiply; a < b; a++) {
      if (isPage(list, vpnr) && !getPage(list, vpnr)) {
         const ph = createPlaceholder(list, vpnr)
         iList[mode](ph);
         observePosition(list, ph)
         observeVisibility(list, ph)
         if (mode === 'prepend') {
            vpnr--;
         } else {
            vpnr++;
         }
      }
   }
}

export function createPlaceholders(list, append, callback) {
   const sd = scrollData(list);
   const iList = sd.iList;
   const mode = append ? 'append' : 'prepend';
   let pnr = 0;
   for (let a = 0, b = getColsCount(list) * sd.addmultiply; a < b; a++) {
      switch (mode) {
         case'append':
            pnr = getPagenr(getLastPage(list)) + 1;
            break;
         case 'prepend':
            pnr = getPagenr(getFirstPage(list)) - 1;
            break;
      }

      if (isPage(list, pnr)) {
         const ph = createPlaceholder(list, pnr)
         iList[mode](ph);
         if (callback) {
            callback(ph)
         }
      }
   }

}


export function isPlaceholder(list, node) {
   return node.dataset.scrollPlaceholder === '1';
}

export function createPlaceholder(list, vpnr) {
   const sd = scrollData(list);
   const ph = document.createElement('div');
   ph.dataset.scrollablePage = String(vpnr);
   ph.dataset.scrollPlaceholder = '1';
   ph.style.position = 'relative';
   ph.style.scrollAnchor = 'none';
   ph.style.border = '1px solid black';
   ph.innerHTML = phwait;
   setMinMax(ph, sd.curplaceholderSize, sd.curplaceholderSize);
   return ph;
}


export function adaptPlaceholderSize(list, page) {
   const sd = scrollData(list);
   if (!page.isConnected) {
      return;
   }
   const pagesize = getNodeSize(page, false)
   const listsize = getNodeSpace(list)

   let colW = Math.ceil(listsize.w / getColsCount(list)) - 10;

   if (getColsCount(list) === 1) {
      // sd.curplaceholderSize = sizeMin(sd.maxplaceholderSize, sizeMax(pagesize, sd.minplaceholderSize,'c'),'c');
      sd.curplaceholderSize = pagesize;
      sd.curplaceholderSize.h = sizeMax(sd.maxplaceholderSize, sizeMax(pagesize, sd.minplaceholderSize, 'h'), 'c').h;
      // sd.curplaceholderSize.w = sizeMin(sd.maxplaceholderSize, sizeMax(pagesize, sd.minplaceholderSize,'w'),'w').w;

   } else {
      sd.curplaceholderSize = sizeMin(sd.maxplaceholderSize, sizeMax(pagesize, sd.minplaceholderSize));
      colW = Math.ceil(listsize.w / getColsCount(list)) - 10;
      sd.curplaceholderSize.w = colW;
      // next try
      // sd.curplaceholderSize = sizeMax(sd.maxplaceholderSize, sizeMax(pagesize, sd.minplaceholderSize,'c'));
      // colW = Math.ceil(listsize.w / getColsCount(list)) - 50;
      // sd.curplaceholderSize.w = colW;

   }

   if (getColsCount(list) > 1) {
      dbg(list, 'adaptph', {pnr: getPagenr(page), con: page.isConnected, curphsize: sd.curplaceholderSize, colW, cols: getColsCount(list)})
   }
}
