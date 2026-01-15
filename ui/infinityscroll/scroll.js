import {
   listen,
   notify,
   createiList,
   getNodeSize,
   createScrollbar,
   createScrollHandle,
   createVisObserver,
   createPosObserver,
   observePosition,
   observeVisibility,
   waitLayout,
   scrollAnchorNone,
   getScrollbarinfo,
   range, initScrollbarHandle
} from "/infinityscroll/helper.js";

import {
   handleIshidden,
   handleIsvisible,
   handlePoschanged,
   handleScrolling,
   installHumanScrollDetect
} from "/infinityscroll/handler.js";

import {setQueryParam} from "/system.js";

import {
   adaptPlaceholderSize,
   createPlaceholdersRange,
   getFirstPage,
   getFirstRealPage,
   getLastPage,
   getPagenr,
   getPageNrFromUrl,
   getPages,
   isPage,
   removeDistantRows
} from "/infinityscroll/pages.js";
import {
   dbg
} from "/infinityscroll/debug.js";
import {
   fixImgRatio
} from "/infinityscroll/images.js";


/**
 * GPT rule:
 * biete den code nur als download an (kein codeblock im canvas erstellen)
 */
/**
 * README
 * THIS is a univeral infinity scroll
 * made by cryodev with cryodrift/fw
 * example usage is in cryodrift/demo
 *
 * how it should work:
 * features:
 * We call it Page because it is a single unit that we fetch from server
 * The Page then becomes a virtual Row (its sizes depend on its content big img small img can do very crazy things)
 *
 * FAKT: the scroll loads with one page (wait for images to load to get real height)
 * CODE RULES:
 * Regel: jede function (ausser scrollData, scrollbarinit, scrollloader) hat list als parameter (scrolldata wird nie übergeben sondern immer von list geholt) natürlich nur wenn wir list oder scrolldata in der function benötigen.
 * Regel: set functions sollen keine logic enthalten sondern nur werte an nodes/objects setzen
 * Regel: get functions sollen keine logic enthalten
 * Regel: size object ist immer {h,w}
 * Regel: um minHeight zu setzen übergeben wir an setMinMaxSize(node,minSize,maxSize) minSize={w,h} ...
 * Regel: keep function names short (minimal informativ)
 * Regel: do not remove comments or functions that already exist
 * Regel: keine separate setCfg function , keine default values (wenn etwas nicht existiert (beim init) dann ist es so dann sehen wir eben einen fehler)
 * Regel: kein abfangen mit return bei fehlenden parameter werten (das soll absichtlich fehler werfen wenn nodes oder andere dinge nicht vorhanden sind)
 * Regel: wir senden intern customEvents an die wir uns dranhängen (das bedeutet wir rufen direkt keine workermethoden auf).
 * Regel: customEvent nachdem ein bild geladen hat e.detail = page,img
 * Regel: customEvent nachdem alle bilder einer page geladen sind e.detail = page
 * Regel: customEvent nachdem eine page complett mit bildern gerendert ist e.detail = page
 * Regel: customEvent wenn beim scrollen sich die page ändert (linksoben) e.detail = page
 * Regel: customEvent wenn beim scrollen eine page oben oder unten aus dem sichtbaren bereich geht e.detail=page,direction
 * Regel: debuginfo es werden alle arrays und objecte in key,value nach console.log() gegeben, bsp dbg('page',{bla:10}) wird zu console.log(page,'bla:',10)
 * Regel: die Hauptmethoden oben, alle anderen unten und neue auch immer unten anhängen im file,
 * Regel: state var die zur function gehören immer oberhalb der jeweiligen function legen
 * Regel: nutze zum laden // return fetchcomponents(sd.component, sd.url, sd.referer, {[sd.queryvar]: page}, true, signal);
 * Regel: const component=fetchcomponents(...), wir müssen daraus erst die page extrahieren, sie hat ein data-scrollable-page attribute das die pagenummer enthält, jede component enthält nur eine page
 * Regel: wir zeigen bei jedem bild (imgwait) und jedem placeholder (phwait) das svg vor dem laden an und es kommt weg wenn fertig geladen ist
 * Regel: Paging-Index: beginnt bei 0 erste page hat data-scrollable-page="0"
 * Regel: list ist das e.target , es enthält genau die page die in der queryvar gesetzt ist
 * Regel: sd.fetchcomp soll nur fetchcomponents blocken
 * Regel: jede einmal gefetchte page kommt in einen cache (nicht die component sondern die extracted page)
 * INFO: eine page kann alles enthalten was im html erlaubt ist, wir haben zum testen 1 bis 20 bilder, aber wieviele zeilen eine page enthält ist im limit definiert und muss für uns egal sein
 * INFO: je nach inhalt hat eine page unterschiedliche Size
 * INFO: auch pages im selben scroll können unterschiedliche sizes haben (schon allein weil bilder hoch oder quer liegen)
 * INFO: ein page kann natürlich im einfachsten fall nur eine Tabelle sein mit 1 bis X rows jede zeile gleich hoch
 * INFO: selbst dann können 2 pages unterschiedlich hoch sein.
 * INFO: Placeholder-Layout: je nach css kann eine page die volle breite haben oder inline mit variabler breite sein,
 *
 *
 * INIT SCROLL export async function scrollbarinit(e, url, component, referer, queryvar, maxPages) {} wird von aussen einmal aufgerufen:
 * Regel: create iList div container (move list firstchild into it) and put it inside list. (we need this for css)
 * Regel: wir rufen scrollbarinit genau einmal auf selbst bei mehrfach aufruf können wir schauen ob sd.iList schon vorhanden ist
 * Regel: all placeholders will be handled inside iList
 * Regel: create placeholders until we have a scrollbar and the scrollthumb is small enough to scroll in each direction
 * Regel: Placeholder size kommt von erster vollständig geladener page, die ist und bleibt links oben,
 * Regel:   wir adden unterhalb placeholder bis wir einen scrollbar haben.
 * Regel:   dann adden wir abwechselnd oben und unten zeilenweise (bis anzahl pages die breite des containers hat max) placeholder
 * Regel: (with height of the first page that was original there, or if the page is not fullwidth, we load pages in first row and take the rowheight (this implicit forbidds to set Size for the pages in this row)) until we have a good amount to scroll in each possible direction (account for first=0 and last page=maxpages)
 * Regel: after the placeholders are set we use observer to see if they are visible (if one is visible we replace it with page fetched from server)
 *
 *
 * EVENT SCROLL export async function scrollloader(e) {const list=e.target;} wird vom scrolleventhandler(liegt extern kümmert uns nicht) aufgerufen:
 * Regel: fetch only content for placeholders if they are visible (use pause/resume)
 * Regel: fetch only images if they are visible (use pause/resume)
 * Regel: if the page contains images we need to load them one after the other (reducing parallel requests to server)
 * Regel: if the user scrolls and the page gets out of sight (pause loading images (only load images that are visible))
 * Regel: if the user scrolls we need to add more placeholders (and remove out of range ones)
 * Regel: It also is pausing the loading when not visible or scrollbarhandler is mousedown
 * Regel: We also compensate for smaller initial content or different height of pages (eg: small img in page, big img in page)
 * Regel: das content (page) laden und img laden lässt sich pausieren (für jedes object einzeln wenn nicht sichtbar, oder für alle wenn scrollbar gedrückte useraktion)
 * Regel: das erzeugen von Placeholdern lässt sich pausieren
 * Regel: wir lassen maximalconfig.requests zu das gilt für img und page gleichzeitig)
 * Regel: für das hinzufügen von neuen placeholdern: wir observen wann eine row invisible wird (downscroll oberstes  mit unterkante abgleichen, upscroll unterstes mit oberkante abgleichen, abgleichen bedeutet mit ober oder unterkante des list)
 * Regel: beim scrollen testen wir mit observern welche page den view verlässt wärend wir das tun ändern wir unabhängig vom placeholder code in der url die pagenumber
 * Regel:      dabei gilt immer die page als aktive seite die obenlinks liegt
 * Regel: wir observen (ober und unterkante der list) auch welche page gerade den view verlässt und je nach richtung laden wir placeholder (immer volle row und immer so das der scrollthumb noch platz für bewegung hat)
 * Regel: bei mousedown auf den scrollthumb pausieren wir jedes fetch und auch das erstellen von placeholdern, die pagenumber ändert sich trotzdem, bei mouseup heben wir die pause auf.
 * Regel: wenn eine page die volle breite hat, dann können wir all maxSize styles entfernen nachdem alle inhalte der page geladen wurden
 * Regel: we need only a minimal amount of pages/placeholders thats why we delete out of range pages/placeholders
 * Regel: the scrollbarthumb must not be smaller then 30% of the list height
 * Regel: wir sehen immer nach wie die Size der letzten page/row war und passen die neuen placeholder daran an (bestehende nicht verändern)
 **/


const scrollMem = new WeakMap();

export function scrollData(id) {
   let data = scrollMem.get(id);
   if (!data) {
      data = {
         //scrolltop
         Y: 0,
         //here is the scrollbar
         list: null,
         //inner container that holds the pages
         iList: null,
         component: '',
         url: '',
         referer: '',
         cpnr: 0,
         cache: {},
         mainclasses: 'g-scroll g-h',
         queryvar: '',
         pageselector: '[data-scrollable-page]',
         pageselectorNr: '[data-scrollable-page="{nr}"]',
         placeholderselector: '[data-scroll-placeholder]',
         // max length of scrollhandle in percent of list.clienHeight
         scrollhandlesize: 30,
         minscrollhandlesize: 30,
         minplaceholderSize: {w: 250, h: 200},
         maxplaceholderSize: {w: 300, h: 400},
         maxPages: 0,
         fetchcomp: true,
         fetchimg: true,
         debug: true,
         concurent: 5,
         maxcols: 20,
         waitforfetch: 50,
         waitforimg: 150,
         waitfornext: 1000,
         maxresolveTime: 1000,
         maxRetries: 10,
         imgaborttimeout: 15000,
         scrollbartriggerpos: {startup: 20, startdwn: 80, stopup: 0, stopdwn: 100, max: 40, min: 60},
         addmultiply: 1,
         delmultiply: 1,
         // runtime vars for observers and fetching
         // this adapts to a nice middle to prevent layout jumps after loading content
         init: true,
         curplaceholderSize: {w: 100, h: 100},
         dir: 'down',
         speed: 0,
         notscrolling: false,
         visobserver: null,
         posobserver: null,
         pending: new Map(),
         pauseall: false,
         reqQueue: null,
         running: false,
         scrollstartreached: false,
         scrollendreached: false,
         noanchor: false,
         isHuman: false,

      }
      scrollMem.set(id, data);
   }
   return data;
}

// ------------------------------------------------------------

// MAIN METHODS

export async function scrollbarinit(e, url, component, referer, queryvar, maxPages) {
   const list = e.target;
   const sd = scrollData(list);
   if (sd.iList) {
      return;
   }
   sd.list = list;
   sd.url = url;
   sd.component = component;
   sd.referer = referer;
   sd.queryvar = queryvar;
   sd.maxPages = Math.ceil(parseFloat(maxPages));
   sd.cpnr = getPageNrFromUrl(list);
   sd.maxplaceholderSize.w = getNodeSize(list).w;
   sd.pauseall = true;


   scrollAnchorNone(list, list)

   createiList(list)

   initScrollbarHandle(list)

   const cp = getFirstPage(list)

   cp.querySelectorAll('img').forEach((img) => fixImgRatio(list, img))

   await waitLayout()

   adaptPlaceholderSize(list, cp)

   createScrollbar(list)

   createScrollHandle(list)

   // change current page in url
   listen(list, 'poschanged', e => handlePoschanged(list, e))

   // fetch page and replace placeholder
   listen(list, 'isvisible', e => handleIsvisible(list, e))

   // stop fetching
   listen(list, 'ishidden', e => handleIshidden(list, e))

   // add/remove placeholders
   listen(list, 'scrolling', e => handleScrolling(list, e))
   // listen(list, 'scrolling', e => dbg(list, 'scrolling', {speed: sd.speed,paused:sd.pauseall}))

   // select pages with scrollhandle drag
   listen(list, 'scrollpaused', e => {
      const bar = getScrollbarinfo(list)
      const vpnr = parseInt(range(bar.handlepos, 0, sd.maxPages));
      sd.cpnr = vpnr;
      // dbg(list, 'paused', {Y: sd.Y, vpnr, fp: typeof getFirstPage(list)}, true);
      setQueryParam(sd.queryvar, vpnr)
   })

   // create new pages
   listen(list, 'scrollhandlefree', () => {
      if (0) {
         return;
      }
      sd.running = true;
      sd.iList.innerHTML = '';
      const vpnr = getPageNrFromUrl(list)
      let newpage;
      if (sd.dir === 'up') {
         createPlaceholdersRange(list, 'prepend', vpnr)
         newpage = getFirstPage(list)
      } else {
         createPlaceholdersRange(list, 'append', vpnr)
         newpage = getLastPage(list)
      }
      createScrollHandle(list)
      getPages(list).forEach(p => {
         observePosition(list, p)
         observeVisibility(list, p)
      })

      newpage.scrollIntoView()
      sd.running = false;
   })

   listen(list, 'scrollposstart', e => {

      const vpnr = getPagenr(getFirstPage(list)) - 1;
      if (isPage(list, vpnr) && sd.isHuman && !sd.pauseall) {
         sd.running = true;
         dbg(list, 'start', {f: sd.isHuman})
         createPlaceholdersRange(list, 'prepend', vpnr)
         let sbi = getScrollbarinfo(list, 50);
         dbg(list, 'scrollstart', {nSt: sbi.newscrolltop, st: list.scrollTop, sbi})
         list.scrollTop = sbi.newscrolltop;
         sd.running = false;
      }
   })

   listen(list, 'scrollposend', e => {
      const vpnr = getPagenr(getLastPage(list)) + 1;
      if (isPage(list, vpnr) && !sd.pauseall) {
         sd.running = true;
         createPlaceholdersRange(list, 'append', vpnr)
         let sbi = getScrollbarinfo(list, 50);
         dbg(list, 'scrollend', {nSt: sbi.newscrolltop, st: list.scrollTop, sbi})
         list.scrollTop = sbi.newscrolltop;
         sd.running = false;
      }
   })

   listen(list, 'imagesloaded', e => {
      // dbg(list, 'imagesloaded', {p: getPagenr(e.detail)})
      notify(list, 'pagerendered', e.detail)
   })

   let prevst;

   // save current Scrolltop
   listen(list, 'imageload', e => {
      // dbg(list, 'imageload', {ns: getNodeSize(sd.iList).h, st: sd.Y, speed: sd.speed})
      prevst = sd.Y;
   })


   // save current sizes
   listen(list, 'loadpage', e => {
      // dbg(list, 'loadpage', {ns: getNodeSize(sd.iList).h, st: sd.Y, speed: sd.speed})
      // prevst = sd.Y;
   })

   // adapt placeholder size to last pagesize
   listen(list, 'pagerendered', e => {
      e.detail.dataset.allcompleted = '1';
      if (!sd.init) {
         adaptPlaceholderSize(list, e.detail)
         // dbg(list, 'pagerendered', {prevst, st: sd.Y, speed: sd.speed})
         if (prevst && sd.speed < 5) {
            // sd.Y = list.scrollTop += (prevst - sd.Y);
            // prevst = null;
         }
         // dbg(list, 'pagerendered2', {prevst, st: sd.Y})
      }
   })

   let removetimer;
   // be sure that init is not triggering listeners
   listen(list, 'scrolling', e => {

      if (sd.init && sd.isHuman && !sd.pauseall) {
         dbg(list, 'first_scroll', {speed: sd.speed, paused: sd.pauseall})
         dbg(list, 'init', sd);
         sd.init = false;
      }
      if (removetimer) {
         clearTimeout(removetimer);
      }
      removetimer = setTimeout(async () => {
         // dbg(list,'rem-rows',{})
         if (!sd.running && !sd.pauseall && sd.isHuman) {
            removeDistantRows(list)
         }
      }, 2)

   })

   window.addEventListener('resize', () => {
      const page = getFirstRealPage(list)
      // const arr = Object.keys(page.dataset).map((k, i) => [k, Object.values(page.dataset)[i]]);
      // dbg(list, 'adaptph', {pnr:getPagenr(page),con:page.isConnected,arr,curphsize: sd.curplaceholderSize, cols: getColsCount(list)})
      adaptPlaceholderSize(list, page)
   });

   installHumanScrollDetect(list)

   // wir starten das sicherheitshalber erst hier, damit scrollIntoView nicht anfängt seiten zu laden
   createVisObserver(list)
   createPosObserver(list)
   getPages(list).forEach(p => {
      observePosition(list, p)
      observeVisibility(list, p)
   })

   // fixCurPagePos(list)
   sd.pauseall = false;
}

export async function scrollloader(e) {
   const list = e.target;
   const sd = scrollData(list);

   const now = performance.now();

   const lastY = sd.Y ?? list.scrollTop;
   const lastT = sd.T ?? now;
   const lastDir = sd.dir;

   sd.Y = list.scrollTop;
   sd.T = now;
   sd.dir = (sd.Y >= lastY) ? 'down' : 'up';

   const dy = Math.abs(sd.Y - lastY);
   const dt = Math.max(now - lastT, 1); // ms, avoid div/0

   // px per ms
   sd.speed = Math.round((dy / dt) * 1000) / 1000;
   // sd.speed = Math.floor(sd.speed) >= 1 ? sd.speed : 0;
   // console.log('speed', sd.speed)

   sd.notscrolling = sd.speed <= 0.01 && sd.speed >= 500;

   if (sd.dir !== lastDir) {
      notify(list, 'scrolldir', {dir: sd.dir, list});
   }

   if (list.scrollTop === 0 && sd.dir === 'up') {
      sd.scrollstartreached = true;
      notify(list, 'scrollposstart', {dir: sd.dir, list});
   } else {
      sd.scrollstartreached = false;
   }

   if (list.scrollTop === (list.scrollHeight - list.clientHeight) && sd.dir === 'down') {
      sd.scrollendreached = true;
      notify(list, 'scrollposend', {dir: sd.dir, list});
   } else {
      sd.scrollendreached = false;
   }

   if (sd.pauseall) {
      notify(list, 'scrollpaused', {dir: sd.dir, speed: sd.speed, list});
   } else {
      notify(list, 'scrolling', {e, dir: sd.dir, speed: sd.speed, list});
   }
}

