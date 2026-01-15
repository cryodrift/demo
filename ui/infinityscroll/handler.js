import {
   observePosition,
   observeVisibility,
   nodeisVisible,
   getScrollbarinfo,
   waitLayout,
   notify
} from "/infinityscroll/helper.js";

import {setQueryParam} from "/system.js";

import {
   createPlaceholders,
   getFirstPage,
   getLastPage,
   getPagenr,
   isPlaceholder,
   loadPage,
   pageisCached,
   stopNodeLoad
} from "/infinityscroll/pages.js";

import {
   loadImgs
} from "/infinityscroll/images.js";

import {
   scrollData
} from "/infinityscroll/scroll.js";


export async function handleScrolling(list, e) {
   const sd = scrollData(list);
   const fpn = getPagenr(getFirstPage(list));
   const lpn = getPagenr(getLastPage(list));
   const sbi = getScrollbarinfo(list);
   // dbg(list, 'poschanged', {dir: sd.dir, sbi})
   // Regel: abbruchbedingungen


   if (
      sd.init
      || sd.pauseall
      || sd.notscrolling
      || sd.running
      || (fpn === 0 && sd.dir === 'up')
      || (lpn === sd.maxPages && sd.dir === 'down')
      || (sbi.handlepos < sd.scrollbartriggerpos.startdwn && sd.dir === 'down')
      || (sbi.handlepos > sd.scrollbartriggerpos.stopdwn && sd.dir === 'down')
      || (sbi.handlepos > sd.scrollbartriggerpos.startup && sd.dir === 'up')
      || (sbi.handlepos < sd.scrollbartriggerpos.stopup && sd.dir === 'up')
   ) {
      // dbg(list, 'scrolling_done', {dir: sd.dir,running:sd.running, notscrolling: sd.notscrolling,hposraw:sbi.handlepos,hapos:sbi.handlepos + (sbi.handlesize / 2)})
      // const stop = [
      //    sd.init,
      //    sd.pauseall,
      //    sd.notscrolling,
      //    (sbi.handlepos < sd.scrollbartriggerpos.startdwn && sd.dir === 'down'),
      //    (sbi.handlepos > sd.scrollbartriggerpos.stopdwn && sd.dir === 'down'),
      //    (sbi.handlepos > sd.scrollbartriggerpos.startup && sd.dir === 'up'),
      //    (sbi.handlepos < sd.scrollbartriggerpos.stopup && sd.dir === 'up'),
      // ];
      // dbg(list, 'scrolling_stop', {
      //    stop
      // })
      // // dbg(list, 'scrolling_stop', {st: sd.scrollbartriggerpos, sbi: Math.round(sbi.handlepos), stop})
      return;
   }
   sd.running = true;
   // dbg(list, 'scrolling_add', {start: sd._startreached, dir: sd.dir, fp: getPagenr(getFirstPage(list)), lp: getPagenr(getLastPage(list))})

   switch (sd.dir) {
      case 'up':
         createPlaceholders(list, false, (p) => {
            observePosition(list, p)
            observeVisibility(list, p)
         })
         break;
      case 'down': {
         createPlaceholders(list, true, (p) => {
            observePosition(list, p)
            observeVisibility(list, p)
         })
      }
         break;
   }
   // dbg(list, 'scrolling_rem', {})


   await waitLayout()
   sd.running = false;
}

const PAGELOAD = Symbol('pageloading');

export async function handleIshidden(list, e) {
   e.detail.entries.forEach(entry => {
      const page = entry.target;
      if (page[PAGELOAD]) {
         clearTimeout(page[PAGELOAD])
      }
      stopNodeLoad(page)
   });
}

export async function handleIsvisible(list, e) {
   const sd = scrollData(list);
   e.detail.entries.forEach(entry => {
      const page = entry.target;
      if (page[PAGELOAD]) {
         clearTimeout(page[PAGELOAD])
      }
      if (pageisCached(list, page)) {
         fetchreplace(list, page)
      } else {
         page[PAGELOAD] = setTimeout(async () => {
            // dbg(list, 'nodeisvis', {vis: nodeisVisible(list, page), pnr: getPagenr(page), con: page.isConnected, top: getPos(list, page).t,siz:getNodeSize(list).h})
            await fetchreplace(list, page)
            delete page[PAGELOAD];
         }, sd.waitforfetch)
      }
   });
}

async function fetchreplace(list, page) {
   const sd = scrollData(list);
   if (!sd.pauseall && page.isConnected && nodeisVisible(list, page, sd.curplaceholderSize.h)) {
      if (isPlaceholder(list, page)) {
         await loadPage(list, page)
      } else {
         await loadImgs(list, page)
      }
   }
}

export function handlePoschanged(list, e) {
   const sd = scrollData(list);
   if (sd.init) {
      return;
   }

   let t;
   let targets = [];
   e.detail.entries.forEach(e => {
      const i = targets.indexOf(e.target);

      if (e.isIntersecting) {
         if (i === -1) {
            targets.push(e.target);
         }
      } else {
         if (i !== -1) {
            targets.splice(i, 1);
         }
      }
   });

   if (!targets.length) {
      return;
   }
   // sortieren nach pageNr (stabil!)
   targets.sort(
      (a, b) =>
         (+a.dataset.scrollablePage) - (+b.dataset.scrollablePage)
   );
   t = targets[0];
   //TODO when we are on the last pages we need to use the scrolltop and calculate
   // the page we are on because the observer cant be changed and if there is more than one page on the
   // last scrollpositions we dont get informed about them,
   // if (sd.dir === 'up') {
   //    t = e.detail.entries[0].target;
   // } else {
   //    t = e.detail.entries.pop().target;
   // }
   const pn = getPagenr(t);
   setQueryParam(sd.queryvar, pn)
   // dbg(list, 'poschanged', pn)
   notify(list, 'page-visible', {page: t, pnr: pn, maxpages: sd.maxPages})
}

//fick dich oida
export function installHumanScrollDetect(list, ms = 250) {
   const sd = scrollData(list);
   let humanUntil = 0;

   const markHuman = () => {
      humanUntil = performance.now() + ms;
   };

   // typische User-Scroll-Inputs
   list.addEventListener('wheel', markHuman, {passive: true});
   list.addEventListener('touchstart', markHuman, {passive: true});
   list.addEventListener('touchmove', markHuman, {passive: true});
   list.addEventListener('pointerdown', markHuman, {passive: true});

   // keyboard scroll (space, arrows, pgup/pgdn, home/end)
   window.addEventListener('keydown', (e) => {
      const keys = ['ArrowUp', 'ArrowDown', 'PageUp', 'PageDown', 'Home', 'End', ' '];
      if (keys.includes(e.key)) {
         markHuman();
      }
   }, {passive: true});

   list.addEventListener('scroll', (e) => {
      sd.isHuman = performance.now() <= humanUntil;
   }, {passive: true});

   return () => {
   }; // optional cleanup
}
