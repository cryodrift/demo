/**
 *
 */
import {runmodulefnk, dom, debounce, setQueryParam, getElements, runDataNowHandlers} from '/system.js';
import {getQuery} from "/modifyurls.js";
import {aktivateTemplate} from "/dataloader.js";
import {runCollection, pd, getSingleValue} from '/eventhandler.js';


let _translations = {};
const __name = 'component.js'
const debounceclick = 150;

let componentscache = {}


export * from '/eventhandler.js';
export * from '/demo/js/searchselect.js';
export * from '/infinityscroll/scroll.js';
export * from '/infinityscroll/tools.js';

export function setTranslations(translations) {
   // console.log(__name,_translations,translations)
   _translations = translations;
}

function cacheGetComponent(compname) {
   const out = componentscache[compname]
   // console.log('get cache comp string:', out)
   return out;
}

function cacheSetComponent(compname, value) {
   componentscache[compname] = value
   // console.log('set comp cache string:', value)
}

/**
 *
 * @param e
 * @param url
 * @param root url must start with this
 * @returns {Promise<void>}
 */
export async function hrefloader(e, url, root) {
   // allow links to be opened in other tab
   if (e.ctrlKey) {
      return;
   }
   const tag = e.target.tagName.toLowerCase();
   const el = tag === 'a' ? e.target : e.target.closest('a')
   if (el) {
      let href = el.getAttribute('href')
      const fullUrl = new URL(href, location.origin);
      const cur = new URL(location.href);
      // remove same Origin parts but keep params
      if (fullUrl.origin === location.origin) {
         cur.searchParams.forEach((v, k) => {
            if (!fullUrl.searchParams.has(k)) {
               fullUrl.searchParams.append(k, v);
            }
         });
         href = fullUrl.pathname + fullUrl.search;
      }

      if (!e.notconfirmed && href && href.startsWith(root)) {
         e.preventDefault()
         debounce([e, url, href], hrefloader, async (e, url, href) => {
            await fetchandreplace('', url, href, undefined)
         }, debounceclick)
      }
   }
}

export async function formloader(e, url) {
   const form = e.target
   const action = form.getAttribute('action')
   const hrefbase = action.split('/').slice(0, -1).join('/')
   const component = action?.split('/').pop()
   // console.log('formloader',component,action,hrefbase,url)
   if (component && action && url === hrefbase) {
      e.preventDefault()
      debounce([e, form, action, hrefbase, component, url], formloader, async (e, form, action, hrefbase, component, url) => {
         const cmdsbefore = form.getAttribute('data-formloader-before')
         if (cmdsbefore) {
            const commandlist = cmdsbefore.split(' ')
            const handlerfile = commandlist.shift()
            const eventhandler = await import(handlerfile);
            for (let a of commandlist) {
               if (!e.notconfirmed) {
                  await runmodulefnk(a, eventhandler, e)
               }
            }
         }
         if (!e.notconfirmed && action && component && url === hrefbase) {
            await fetchandreplace(component, url, undefined, form)
         }

         const cmdsafter = form.getAttribute('data-formloader-after')
         if (cmdsafter) {
            const commandlist = cmdsafter.split(' ')
            const handlerfile = commandlist.shift()
            const eventhandler = await import(handlerfile);
            for (let a of commandlist) {
               if (!e.notconfirmed) {
                  await runmodulefnk(a, eventhandler, e)
               }
            }
         }
      }, debounceclick)
   }

}


export async function fetchcomponents(component, url, route, optdata, nospinner, signal) {
   let origformdata = [], formdata;

   if (optdata instanceof HTMLFormElement) {
      origformdata = new FormData(optdata).entries();
      formdata = new FormData(optdata);
   } else {
      if (optdata) {
         origformdata = Object.entries(optdata);
      }
      formdata = new FormData();
   }
   for (const [key, value] of origformdata) {
      formdata.append(`data[${key}]`, value);
   }

   const spinner = document.getElementById('spinner-overlay')
   if (!nospinner) {
      spinner.classList.remove('g-dn')
   }
   try {
      if (component) {
         formdata.set('component', component);
      }

      const u = new URL(route, 'http://x');
      let params = u.searchParams.toString();
      params = params ? '?' + params : undefined;
      formdata.set('route', route || location.pathname);
      const token = document.cookie?.split('; ').find(r => r.startsWith('csrftoken='))?.split('=')[1] ?? '';

      // console.log(__name, 'params', params)

      const response = await fetch(url + (params || location.search), {
         method: 'POST',
         headers: {"X-CSRF-Token": token},
         body: formdata,
         redirect: 'manual',
         signal: signal
      })

      // manual redirect
      if (response.status === 0) {
         const url = new URL('/demo/login', location.origin);
         url.search = location.search;
         history.pushState(null, "", url.toString());
         window.dispatchEvent(new Event('pushstate'));
         return
      }
      if (!response.ok) {
         console.error('Network response was not ok ' + response.statusText)
         spinner.classList.add('g-dn')
         return
      }

      const data = await response.json()
      if (data.errors) {
         for (const a in data.errors) {
            const err = document.getElementById(a)
            err.innerHTML = data.errors[a]
            if (data.errors[a]) {
               err.classList.remove('g-dh')
            } else {
               err.classList.add('g-dh')
            }
         }
      }

      //add or replace queryparts
      data?.query && Object.entries(data.query).forEach(([name, value]) => {
         // console.log(__name, name, value)
         setQueryParam(name)
         setQueryParam(name, value)
         // window.dispatchEvent(new Event('pushstate'));
      });

      return data;

   } catch (error) {
      if (error.name === 'AbortError') {
         return;
      }
      console.error('There was a problem with the fetch operation:', error)
   } finally {
      spinner.classList.add('g-dn')

   }
}

export async function replacecomponents(callingcomponent, data, route, optdata) {
   // quick hide/show components before we load content
   setComponentVisibility(data)
   // insert components
   for (const compname in data?.components) {
      for (const compel of document.querySelectorAll('[data-component=' + compname + ']')) {
         const compdat = data.components[compname];
         const visible = data.visible[compname];
         const update = data.update[compname] !== false;
         if (compel) {
            if (visible) {
               // console.log(__name, compname, cacheGetComponent(compname) !== compdat || compel.innerHTML === '{{' + compname + '}}' || callingcomponent === compname ? 'UPDATE' : 'CACHE')
               const cachedcomp = cacheGetComponent(compname)
               const cached = cachedcomp === compdat;
               const init = compel.dataset?.componentInit;
               // compel.innerHTML === '{{' + compname + '}}' || compel.innerHTML === '';

               if (compdat) {
                  // console.log('replacecomponent', compname,!cached, update, !init)
                  if ((!cached && update) || (!cached || !init)) {
                     const child = dom(compdat)
                     await aktivateTemplate(child)
                     compel.innerHTML = '';
                     compel.appendChild(child)
                     compel.dataset.componentInit = '1';
                     cacheSetComponent(compname, compdat)
                     // console.log('refreshed:', compname)
                     const eventhandler = await import('/component.js');
                     await runDataNowHandlers(compel, eventhandler)
                     setComponentVisibility(data)
                  }
               }

            } else {
               if (compname === 'error_main') {
                  compel.innerHTML = '';
               }
            }
         } else {
            if (compdat) {
               console.log('Missing Component Placeholder for ', compname)
            }
         }
      }
      // console.log(compel, compdat)
   }

   runRefresh(data)

   // when called by hrefloader only
   if (!optdata && route && location.pathname !== route) {
      route = route.includes('?') ? route : route + getQueryString()
      history.pushState(null, "", route);
      window.dispatchEvent(new Event('pushstate'));
   }
   // when called by formloader only
   if (optdata && data?.route && location.pathname !== data?.route) {
      let route = data.route;
      route = route.includes('?') ? route : route + getQueryString()
      history.pushState(null, "", route);
      window.dispatchEvent(new Event('pushstate'));
   }
   setComponentVisibility(data)

}

export async function fetchandreplace(component, url, route, optdata) {
   const data = await fetchcomponents(component, url, route, optdata)
   await replacecomponents(component, data, route, optdata)
}

export function runRefresh(data) {
   for (const refresh in data?.refresh) {
      const id = data.refresh[refresh]?.id;
      const el = document.getElementById(id)
      const text = data.refresh[refresh]?.html;
      if (text && el) {
         const item = dom(text)
         el.replaceWith(item);
         // console.log('component:refresh start', id)
         document.dispatchEvent(new CustomEvent('component:refresh', {detail: {id: id, text: text}}));
      }
   }
}

/**
 * wir nutzen jetzt data-click weil sonst 2 events
 * @param e event
 * @param url
 */
export async function refreshpage(e, url) {
   debounce([e, url], refreshpage, async (e, url) => {
      // const href = document.location.href.replace(document.location.origin, '')
      const href = location.pathname + location.search
      await fetchandreplace('', url, href, undefined)
   }, debounceclick)
}

export async function fetchandcache(e, url, href) {
   debounce([href, url], fetchandcache, async (href, url) => {
      // console.log('hier', href, location.pathname + location.search)
      href = href || location.pathname + location.search
      const data = await fetchcomponents('', url, href, undefined)
      cachecomponents(data)
   }, debounceclick)
}

export function cachecomponents(data) {
   for (const compname in data?.components) {
      const compdat = data.components[compname];
      if (compdat) {
         cacheSetComponent(compname, compdat)
      }
   }
}

function setComponentVisibility(data) {
   for (const compname in data?.visible) {
      const visible = data.visible[compname];
      for (const compel of document.querySelectorAll('[data-component=' + compname + ']')) {
         if (visible) {
            compel.classList.remove('g-dh')
         } else {
            compel.classList.add('g-dh')
         }
      }
   }
}


export function replaceurl(e, selector, pos, urlpart) {
   const coll = getElements(e, selector);
   runCollection(coll, el => {
      if (pos === 'append') {
         history.pushState({}, '', urlpart + el.value + getQueryString());
      }
   })
}

export function btn(e, name, inputdest) {
   const form = e.target
   const btn = e.submitter
   // console.log('hier')
   if (btn && btn.name === name) {
      const el = form.querySelector('input[name="' + inputdest + '"]')
      if (el) {
         el.value = btn.value
      }
   }
}

function getVisibleHtml(el) {
   const clone = el.cloneNode(true);

   // aktuelle Werte zurückschreiben, damit outerHTML das anzeigt
   clone.querySelectorAll('input,textarea,select').forEach(i => {
      if (i.tagName === 'INPUT') {
         if (i.type === 'checkbox' || i.type === 'radio') {
            if (i.checked) {
               i.setAttribute('checked', '');
            } else {
               i.removeAttribute('checked');
            }
         } else {
            i.setAttribute('value', i.value);
         }
      } else if (i.tagName === 'TEXTAREA') {
         i.textContent = i.value;
      } else if (i.tagName === 'SELECT') {
         i.querySelectorAll('option').forEach(o =>
            o.toggleAttribute('selected', o.selected)
         );
      }
   });

   return clone.innerHTML;
}

//
function isSameHtml(a, b) {
   // console.log('comparehtml a1', a)
   // console.log('comparehtml b1', b)
   const decode = html => {
      const el = document.createElement('textarea');
      el.innerHTML = html;
      return el.value.replace(/\s([a-z-]+)(?=(\s|>|\/|$))/gi, ' $1=""');
   };
   console.log('comparehtml a1', a)
   console.log('comparehtml b1', b)

   a = String(decode(a)).toLocaleLowerCase().replaceAll(/[\s\r\n\t]+/g, '').replaceAll('/', '');
   b = String(decode(b)).toLocaleLowerCase().replaceAll(/[\s\r\n\t]+/g, '').replaceAll('/', '')
   console.log('comparehtml a2', a)
   console.log('comparehtml b2', b)
   return a === b
}

export function addFormValidationTitles(elem) {
   for (const el of elem.querySelectorAll("input, select, textarea")) {
      el.title = _translations[el.type + '_valueMissing'] || '';
   }
}

export function addCustomFormErrors() {

   function fill(ph, el) {
      return ph
         .replace('{{min}}', el.min || '')
         .replace('{{max}}', el.max || '')
         .replace('{{step}}', el.step || '')
         .replace('{{minLength}}', el.minLength || '')
         .replace('{{maxLength}}', el.maxLength || '')
         .replace('{{text}}', el.value || '');
   }

   const keys = [
      'valueMissing', 'typeMismatch', 'patternMismatch', 'tooShort', 'tooLong',
      'rangeUnderflow', 'rangeOverflow', 'stepMismatch', 'badInput'
   ];

   function setMsg(el) {
      const v = el.validity, t = el.type || el.tagName.toLowerCase();
      for (const k of keys) {
         if (v[k]) {
            let m = _translations[t + '_' + k] || _translations[k] || '';
            // console.log(__name, t, k, m,el.validationMessage)
            el.setCustomValidity(fill(m, el));
            return;
         }
      }
      el.setCustomValidity('');
   }

   document.addEventListener('invalid', e => setMsg(e.target), true);
   document.addEventListener('input', e => setMsg(e.target), true);
}

export function addredirect(e) {
   const el = e.target;
   el.setAttribute('action', new URL(el.action).pathname + '?redirect=' + location.pathname + location.search);
}

export function adddetail(e, selector) {
   const el = e.target;
   const coll = getElements(e, selector);
   const safe = el.value.replace(/[^a-zA-Z0-9äöüß]/g, '');
   if (e.code === 'Enter') {
      pd(e);
   }
   if (safe.length < 3) {
      runCollection(coll, (dest) => {
         dest.innerHTML = '';
      });
      return;
   }

   // template im label finden
   const t = el.closest('label')?.querySelector('template');
   if (!t) {
      return;
   }
   const html = t.innerHTML.replaceAll('{{key}}', safe);
   runCollection(coll, (dest) => {
      dest.innerHTML = html;
   });
}

export function changeinput(e) {
   const elem = e.target;
   const c = elem.closest('label');
   let el = c.querySelector(':scope > input, :scope > textarea');

   if (elem.value === 'remove') {
      if (confirm('Wollen Sie löschen?')) {
         c.remove();
      } else {
         elem.value = el.tagName === 'TEXTAREA' ? 'textarea' : el.type;
      }
      return;
   }

   if (elem.value === 'textarea') {
      if (el.tagName !== 'TEXTAREA') {
         const t = document.createElement('textarea');
         t.name = el.name;
         t.value = el.value;
         el.replaceWith(t);
      }
      return;
   }

   if (el.tagName === 'TEXTAREA') {
      const i = document.createElement('input');
      i.name = el.name;
      i.value = el.value;
      el.replaceWith(i);
      el = i;
   }

   el.type = elem.value;
   el.accept = elem.value === 'file' ? '.jpg,.png,.pdf,.zip' : '';

}

function getQueryString() {
   const query = getQuery()
   return (query ? '?' + query : '');
}

export function replacequery(e, name, value, isselector) {
   pd(e);
   if (isselector) {
      value = getSingleValue(e, value)
   }
   setQueryParam(name, value)
}
