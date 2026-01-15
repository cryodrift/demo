import {
   setQueryParam,
   getElements
} from '/system.js';

import {
   notify
} from "/infinityscroll/helper.js";

import {
   scrollData
} from "/infinityscroll/scroll.js";

/**
 * setup input type range as scrollbar
 * use with data-now-handler="minibar|scroll list selector|input selector"
 */
export function minibar(e, list, inputrange) {
   let lock = false;
   const content = getElements(e, list).shift().shift();
   const bar = getElements(e, inputrange).shift().shift();

   bar.addEventListener('input', () => {
      lock = true;
      content.scrollTop = (bar.value / 100) * (content.scrollHeight - content.clientHeight);
      setTimeout(() => lock = false, 100);
   });

   content.addEventListener('scroll', () => {
      if (!lock) {
         bar.value = (content.scrollTop / (content.scrollHeight - content.clientHeight)) * 100;
      }
   });
}

/**
 * change page via input
 */
export async function changepage(e, list, input) {
   const content = getElements(e, list).shift().shift();
   const el = getElements(e, input).shift().shift();
   const vpnr = parseInt(el.value);

   if (el._changeTimeout) {
      clearTimeout(el._changeTimeout);
   }
   el._changeTimeout = setTimeout(async () => {
      const sd = scrollData(content)
      setQueryParam(sd.queryvar, vpnr)
      notify(content, 'scrollhandlefree')
   }, 500);
}

/**
 * setup input type range as scrollbar
 * use with data-now-handler="pagechange|listselector|eventname"
 */
export function pagechange(e, list, eventname) {
   eventname = eventname || 'page-visible';
   const elem = getElements(e, list).shift().shift();

   elem.addEventListener(eventname, (ev) => {
      const page = ev.detail.page;
      const firstimg = page.querySelector('img');
      const alt = firstimg?.alt ?? '';
      // console.log('page-visible', alt, e.target)
      if (ev.detail.pnr) {
         e.target.dataset.htmlpnr = 'pageNr: ' + ev.detail.pnr;
      }

      if (ev.detail.maxpages) {
         e.target.dataset.htmlmax = 'from:' +ev.detail.maxpages;
      }

      if (alt) {
         e.target.dataset.htmlalt = alt;
      }

      e.target.innerHTML = Object.entries(e.target.dataset)
         .filter(([k]) => k.startsWith('html'))
         .map(([, v]) => v)
         .join(' / ');
   });
}
