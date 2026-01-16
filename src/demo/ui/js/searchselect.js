// usage:
// enableSelectTypeFilter(document.querySelector('select'), 'highlight');
// enableSelectTypeFilter(document.querySelector('select'), 'hide');
// type-to-filter for native <select> while focused/open
// mode: "hide" = reduce list, "highlight" = only mark matches
// creates an inline search-input next to/over the select, focuses it, filters options (hide/highlight)
// behavior:
// - call on select-event (e.g. click/focus) -> it creates input + focuses it
// - click into the input -> removes input again and focuses the select
// - blur (input) -> removes input
export function searchselect(e, mode = 'hide') {
   const select = e.target;

   // avoid duplicates
   if (select._ss_input) {
      select._ss_input.focus();
      return;
   }

   const opts = Array.from(select.options).map(o => ({
      o,
      label: ((o.textContent || '').trim()).toLowerCase()
   }));

   const parent = select.parentElement;
   if (!parent) return;

   // create input
   const input = document.createElement('input');
   input.type = 'text';
   input.autocomplete = 'off';
   input.spellcheck = false;

   // minimal inline positioning: insert before select
   // (if you need overlay: set parent position:relative and input absolute)
   input.style.width = select.getBoundingClientRect().width + 'px';
   input.style.marginBottom = '4px';

   const apply = () => {
      const s = (input.value || '').trim().toLowerCase();

      for (const it of opts) {
         const hit = s === '' ? true : it.label.includes(s);

         if (mode === 'hide') it.o.hidden = !hit;
         if (mode === 'highlight') it.o.style.background = hit && s !== '' ? '#ffe08a' : '';
      }

      if (mode === 'hide' && select.selectedOptions[0]?.hidden) {
         const first = opts.find(it => !it.o.hidden && !it.o.disabled)?.o;
         if (first) select.value = first.value;
      }
   };

   const cleanup = () => {
      if (!select._ss_input) return;

      // reset option styles for highlight mode
      if (mode === 'highlight') {
         for (const it of opts) it.o.style.background = '';
      }

      input.removeEventListener('input', apply);
      input.removeEventListener('blur', onBlur);
      input.removeEventListener('click', onClickInput);
      input.removeEventListener('keydown', onKeyDown);

      select._ss_input = null;
      input.remove();
   };

   const onBlur = () => cleanup();

   const onClickInput = (ev) => {
      // requested: click into input removes it again
      ev.preventDefault();
      cleanup();
      select.focus();
   };

   const onKeyDown = (ev) => {
      if (ev.key === 'Escape') {
         ev.preventDefault();
         input.value = '';
         apply();
         cleanup();
         select.focus();
         return;
      }
      if (ev.key === 'Enter') {
         ev.preventDefault();
         cleanup();
         select.focus();
         return;
      }
   };

   // mount + wire
   parent.insertBefore(input, select);
   select._ss_input = input;

   input.addEventListener('input', apply);
   input.addEventListener('blur', onBlur);
   input.addEventListener('click', onClickInput);
   input.addEventListener('keydown', onKeyDown);

   // program sets focus
   input.focus();
   apply();
}
