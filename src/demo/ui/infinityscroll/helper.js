import {
   createPlaceholder,
   createPlaceholders,
   getPageNrFromUrl,
   isPage,
   getPage
} from "/infinityscroll/pages.js";

import {
   scrollData
} from "/infinityscroll/scroll.js";


export function sizeisBigger(size1, size2, onlyone = false) {
   const w = size1.w > size2.w;
   const h = size1.h > size2.h;

   return onlyone ? (w || h) : (w && h);
}

export function sizeCombine(usemin, ...sizes) {
   if (!sizes.length) {
      return {w: 0, h: 0};
   }

   return {
      w: sizes.reduce(
         (v, s) => usemin ? Math.min(v, s.w) : Math.max(v, s.w),
         sizes[0].w
      ),
      h: sizes.reduce(
         (v, s) => usemin ? Math.min(v, s.h) : Math.max(v, s.h),
         sizes[0].h
      )
   };
}

export function sizeMax(size1, size2, compare = 'w or h') {
   switch (compare) {
      case'w':
         if (size1.w > size2.w) {
            return size1;
         } else {
            return size2
         }

      case'h':
         if (size1.h > size2.h) {
            return size1;
         } else {
            return size2
         }
      case'c':
         const w = Math.max(size1.w, size2.w);
         const h = Math.max(size1.h, size2.h);
         return {w, h};
      default:
         if (size1.w > size2.w && size1.h > size2.h) {
            return size1;
         } else {
            return size2;
         }
   }
}

export function sizeMin(size1, size2, compare = 'w | h | c') {
   switch (compare) {
      case'w':
         if (size1.w < size2.w) {
            return size1;
         } else {
            return size2
         }

      case'h':
         if (size1.h < size2.h) {
            return size1;
         } else {
            return size2
         }
      case'c':
         const w = Math.min(size1.w, size2.w);
         const h = Math.min(size1.h, size2.h);
         return {w, h};
      default:
         if (size1.w < size2.w && size1.h > size2.h) {
            return size1;
         } else {
            return size2;
         }
   }

}


export function getPos(list, node) {
   const sd = scrollData(list);
   const parent = list;

   const pr = parent.getBoundingClientRect();
   const nr = node.getBoundingClientRect();

   return {
      t: Math.round(nr.top - pr.top),
      l: Math.round(nr.left - pr.left)
   };
}


export function toRect(boundingClientRect) {
   const r = boundingClientRect;
   return {b: r.bottom, t: r.top, r: r.right, l: r.left};
}

export function nodeisVisible(list, node, tol = 2) {
   const sd = scrollData(list);
   const top = getPos(list, node).t + tol;
   const h = getNodeSize(list).h + tol + tol;
   return top < h && top >= 0;
}


export function removeNode(list, node) {
   const sd = scrollData(list);
   sd.posobserver.unobserve(node);
   sd.visobserver.observe(node);
   node.remove();
}

export function setMinMax(node, min, max) {
   if (min?.h !== undefined && min?.h !== null) {
      node.style.minHeight = (typeof min.h === 'number') ? `${min.h}px` : min.h;
   } else {
      node.style.minHeight = '';
   }
   if (max?.h !== undefined && max?.h !== null) {
      node.style.maxHeight = (typeof max.h === 'number') ? `${max.h}px` : max.h;
   } else {
      node.style.maxHeight = '';
   }
   if (min?.w !== undefined && min?.w !== null) {
      node.style.minWidth = (typeof min.w === 'number') ? `${min.w}px` : min.w;
   } else {
      node.style.minWidth = '';
   }
   if (max?.w !== undefined && max?.w !== null) {
      node.style.maxWidth = (typeof max.w === 'number') ? `${max.w}px` : max.w;
   } else {
      node.style.maxWidth = '';
   }
}


export function createiList(list) {
   const sd = scrollData(list);
   const classes = list.dataset?.scrollClasses || ''
   sd.mainclasses.split(' ').forEach(t => list.classList.add(t))

   let iList = document.createElement('div');
   classes.split(' ').forEach(t => {
      if (t) {
         iList.classList.add(t)
      }
   })
   while (list.firstChild) {
      iList.appendChild(list.firstChild);
   }
   iList.style.width = '100%';
   iList.style.position = 'relative';
   list.append(iList);
   sd.iList = iList;
}

export function notify(list, name, detail) {
   list.dispatchEvent(new CustomEvent(name, {detail}));
}

export function listen(list, name, fnk) {
   list.addEventListener(name, fnk)
}

export function createScrollbar(list) {
   const sd = scrollData(list);
   const iList = sd.iList;
   let vpnr = sd.cpnr;
   while (!getScrollbarinfo(list).exists) {
      vpnr++;
      if (!isPage(list, vpnr)) {
         return;
      }
      const ph = createPlaceholder(list, vpnr);
      iList.append(ph)
   }
}

export function createScrollHandle(list) {
   const sd = scrollData(list);
   let last = 0;

   let sbi = getScrollbarinfo(list);
   let hs = sbi.handlesize;
   // dbg(list, 'createScrollHandle', {hsMin: sd.scrollhandlesize, hs, hpos: sbi.handlepos})

   // abwechselnd placeholder adden prepend append
   while ((hs >= sd.scrollhandlesize) && last < 70) {
      const mode = (sbi.handlepos > 45);
      createPlaceholders(list, mode, () => {
         const mainpage = getPage(list, getPageNrFromUrl(list))
         mainpage.scrollIntoView();
      })
      sbi = getScrollbarinfo(list);
      hs = sbi.handlesize;
      last++;
      // dbg(list, 'createScrollHandle', {hsMin: sd.scrollhandlesize, hs, mode, hpos: sbi.handlepos})

      if (sbi.handlepos > 50 && hs <= sd.minscrollhandlesize) {
         return;
      }
   }
}

export function getScrollbarinfo(list, posinpercent) {
   const sd = scrollData(list);

   const scrollHeight = list.scrollHeight;
   const clientHeight = list.clientHeight;
   const scrollTop = list.scrollTop;

   const exists = scrollHeight > clientHeight;

   const handlesize = exists ? (clientHeight / scrollHeight) * 100 : 100;

   // const handlepos = exists ? ((scrollTop + clientHeight / 2) / scrollHeight) * 100 : 50;
   const maxScroll = Math.max(1, scrollHeight - clientHeight);
   const handlepos = exists ? (scrollTop / maxScroll) * 100 : 0;

   const newscrolltop = ((posinpercent || 0) / 100) * maxScroll;

   return {
      exists,
      handlesize,
      handlepos,
      maxScroll,
      newscrolltop
   };
}


export function observePosition(list, node) {
   const sd = scrollData(list);
   sd.posobserver.observe(node);
}

export function observeVisibility(list, node) {
   const sd = scrollData(list);
   sd.visobserver.observe(node);
}

export function createPosObserver(list) {
   const sd = scrollData(list);

   if (!sd.posobserver) {
      const w = list.clientWidth;
      const h = list.clientHeight;
      const right = w - 50;
      const bottom = h - 50;

      sd.posobserver = new IntersectionObserver((entries) => {
         const hit = entries.filter(e => e.isIntersecting);
         if (hit.length) {
            notify(list, 'poschanged', {entries: hit, list});
         }
      }, {
         root: list,
         threshold: 0,
         rootMargin: `0px -${right}px -${bottom}px 0px`
      });
   }
}

export function createVisObserver(list) {
   const sd = scrollData(list);
   if (!sd.visobserver) {
      sd.visobserver = new IntersectionObserver((entries) => {
            const visible = entries.filter(e => e.isIntersecting);
            if (visible.length) {
               notify(list, 'isvisible', {entries: visible, list});
            }
            const hidden = entries.filter(e => !e.isIntersecting);
            if (hidden.length) {
               notify(list, 'ishidden', {entries: hidden, list});
            }
         }
         , {
            root: list,
            threshold: 0,
         });
   }
}

export function getNodeSpace(node) {
   return {
      w: node.clientWidth,
      h: node.clientHeight
   };
}

export function getNodeSize(node, raw = false) {
   if (!raw) {
      const r = node.getBoundingClientRect();
      return {w: Math.ceil(r.width), h: Math.ceil(r.height)};
   }

   // save current styles
   const prevMaxW = node.style.maxWidth;
   const prevMaxH = node.style.maxHeight;

   // remove constraints
   node.style.maxWidth = '';
   node.style.maxHeight = '';

   // force layout read
   const r = node.getBoundingClientRect();
   const out = {w: Math.ceil(r.width), h: Math.ceil(r.height)};

   // restore styles
   node.style.maxWidth = prevMaxW;
   node.style.maxHeight = prevMaxH;

   return out;
}


export async function replaceNode(list, oldNode, newNode) {
   const h1 = oldNode.offsetHeight;
   oldNode.replaceWith(newNode);
   await waitLayout();
   const h2 = newNode.offsetHeight;
   // dbg(list, 'scrolltop', {old: list.scrollTop, nxt: list.scrollTop + (h2 - h1), diff: h2 - h1})
   // list.scrollTop += (h2 - h1);
}

export function scrollAnchorNone(list, node) {
   const sd = scrollData(list);
   if (sd.noanchor) {
      node.style.scrollAnchor = 'none';
   }
}


export function initScrollbarHandle(list) {
   const sd = scrollData(list);

   // Heuristic: mouse down near right edge => likely scrollbar thumb drag.
   list.addEventListener('pointerdown', (ev) => {
      const edge = 20;
      const rect = list.getBoundingClientRect();
      const nearScrollbar = (rect.right - ev.clientX) <= edge;
      // dbg(list,'pause',{nearScrollbar})
      if (nearScrollbar) {
         sd.pauseall = true;
      }
   });

   window.addEventListener('pointerup', () => {
      if (sd.pauseall) {
         sd.pauseall = false;
         notify(list, 'scrollhandlefree')
      }
   }, {passive: true});
}

export async function waitLayout() {
   return new Promise(r => requestAnimationFrame(() => r()));
}

export async function waitTime(timeout) {
   return new Promise(r => setTimeout(r, timeout));
}


export function range(percent, start, end) {
   return start + (end - start) * (percent / 100);
}

