/*
 * DutyCalc admin portal. The pages only read; every change is sent from here to the JSON
 * endpoints under /api/admin, so the admin pages share the API's validation, its rate rules
 * and its change log. Plain JavaScript, no build step.
 *
 *   <form data-api="/api/..." [data-method="PATCH"] [data-success="reload|/url"]>
 *   <button data-action data-api="/api/..." [data-method] [data-body='{"a":1}'] [data-confirm="…"]>
 *   <input type="checkbox" data-toggle-api="/api/..." data-field="supported">
 *
 * Field names ending in [] are sent as arrays; a single checkbox is sent as true/false;
 * inputs marked data-percent are typed as a percentage and sent as a fraction (6 → "0.06").
 */
(function () {
  'use strict';

  var tokenMeta = document.querySelector('meta[name="csrf-token"]');
  var csrf = tokenMeta ? tokenMeta.getAttribute('content') : '';
  var LOGIN = '/admin/login';

  // ---- notice --------------------------------------------------------------

  function notice(text) {
    var box = document.getElementById('admin-notice');
    if (!box) { window.alert(text); return; }
    document.getElementById('admin-notice-text').textContent = text;
    box.hidden = false;
    box.scrollIntoView({ block: 'nearest' });
  }

  var closeNotice = document.getElementById('admin-notice-close');
  if (closeNotice) closeNotice.addEventListener('click', function () { document.getElementById('admin-notice').hidden = true; });

  // ---- requests ------------------------------------------------------------

  /** Resolves {status, data}; handles the cases every form shares. */
  function send(url, method, body) {
    var isForm = body instanceof FormData;
    var headers = { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' };
    if (!isForm && body !== undefined) headers['Content-Type'] = 'application/json';

    return fetch(url, {
      method: method || 'POST',
      headers: headers,
      credentials: 'same-origin',
      body: body === undefined ? undefined : (isForm ? body : JSON.stringify(body)),
    }).catch(function () {
      notice('No internet connection. Check your connection and try again.');
      throw new Error('network');
    }).then(function (res) {
      return res.json().catch(function () { return {}; }).then(function (data) {
        if (res.status === 401 && url.indexOf('/api/admin/login') < 0) { window.location.href = LOGIN; throw new Error('auth'); }
        if (res.status === 419) { notice('This page has expired. Reload the page and try again.'); throw new Error('expired'); }
        if (res.status === 429) { notice('Too many tries. Wait a minute, then try again.'); throw new Error('throttled'); }
        if (res.status >= 500) { notice('Something went wrong on the server. Nothing was changed. Try again.'); throw new Error('server'); }
        return { status: res.status, data: data };
      });
    });
  }

  function done(target) {
    if (!target || target === 'reload') { window.location.reload(); return; }
    window.location.href = target;
  }

  // ---- numbers ---------------------------------------------------------------

  /** "6" → "0.06", "16.5" → "0.165", "0" → "0". String maths only, so no float rounding. */
  function percentToFraction(value) {
    var v = String(value).trim().replace(/,/g, '');
    if (!/^\d+(\.\d+)?$/.test(v)) return v;   // the server explains what is wrong
    var parts = v.split('.');
    var digits = parts[0] + (parts[1] || '');
    var scale = (parts[1] || '').length + 2;
    while (digits.length <= scale) digits = '0' + digits;
    var whole = digits.slice(0, digits.length - scale).replace(/^0+(?=\d)/, '');
    var frac = digits.slice(digits.length - scale).replace(/0+$/, '');
    return frac ? whole + '.' + frac : whole;
  }

  // ---- forms -----------------------------------------------------------------

  function payload(form) {
    if (form.hasAttribute('data-multipart')) return new FormData(form);

    var body = {};
    Array.prototype.forEach.call(form.elements, function (el) {
      if (!el.name || el.disabled || el.closest('[hidden]')) return;
      var name = el.name;
      if (name.slice(-2) === '[]') {
        name = name.slice(0, -2);
        body[name] = body[name] || [];
        if ((el.type === 'checkbox' || el.type === 'radio') ? el.checked : el.value !== '') body[name].push(el.value);
        return;
      }
      if (el.type === 'radio') { if (el.checked) body[name] = el.value; return; }
      if (el.type === 'checkbox') { body[name] = el.checked; return; }
      var value = el.value.trim();
      if (value === '') { if (el.hasAttribute('data-null')) body[name] = null; return; }
      body[name] = el.hasAttribute('data-percent') ? percentToFraction(value) : value;
    });
    return body;
  }

  function clearErrors(form) {
    form.querySelectorAll('[data-error-for]').forEach(function (p) { p.hidden = true; p.textContent = ''; });
    form.querySelectorAll('.invalid').forEach(function (f) { f.classList.remove('invalid'); });
    var list = form.querySelector('[data-form-errors]');
    if (list) { list.hidden = true; list.innerHTML = ''; }
  }

  function showErrors(form, errors) {
    var loose = [];
    var first = null;
    Object.keys(errors || {}).forEach(function (field) {
      var key = field.replace(/\.\d+$/, '');
      var slot = form.querySelector('[data-error-for="' + key + '"]');
      if (slot) {
        slot.textContent = errors[field].join(' ');
        slot.hidden = false;
        var wrap = form.querySelector('[data-field="' + key + '"]');
        if (wrap) wrap.classList.add('invalid');
        first = first || (wrap && wrap.querySelector('input, select, textarea'));
      } else {
        loose = loose.concat(errors[field]);
      }
    });
    var list = form.querySelector('[data-form-errors]');
    if (loose.length && list) {
      loose.forEach(function (m) { var li = document.createElement('li'); li.textContent = m; list.appendChild(li); });
      list.hidden = false;
      list.scrollIntoView({ block: 'nearest' });
    } else if (loose.length) {
      notice(loose.join(' '));
    }
    if (first) first.focus();
  }

  function busy(button, on) {
    if (!button) return;
    button.disabled = on;
    if (on) { button.dataset.label = button.textContent; button.textContent = 'Saving…'; }
    else if (button.dataset.label) { button.textContent = button.dataset.label; }
  }

  document.querySelectorAll('form[data-api]').forEach(function (form) {
    form.addEventListener('submit', function (e) {
      e.preventDefault();
      if (form.dataset.confirm && !window.confirm(form.dataset.confirm)) return;
      var button = form.querySelector('[type="submit"]');
      clearErrors(form);
      busy(button, true);

      send(form.dataset.api, form.dataset.method || 'POST', payload(form))
        .then(function (res) {
          if (res.status >= 200 && res.status < 300) {
            if (form.dataset.handler && handlers[form.dataset.handler]) return handlers[form.dataset.handler](form, res.data);
            return done(form.dataset.success);
          }
          if (res.status === 422 || res.status === 409) {
            // The CSV import answers with a list of {file, row, message}; everything else with field errors.
            if (Array.isArray(res.data.errors) && form.dataset.handler && handlers[form.dataset.handler + 'Error']) {
              return handlers[form.dataset.handler + 'Error'](form, res.data);
            }
            showErrors(form, res.data.errors || { row: [res.data.message || 'This change was refused.'] });
            return;
          }
          showErrors(form, { row: [res.data.message || 'This change was refused.'] });
        })
        .catch(function () { /* notice shown */ })
        .finally(function () { busy(button, false); });
    });
  });

  // ---- one-click actions -------------------------------------------------------

  document.querySelectorAll('[data-action]').forEach(function (button) {
    button.addEventListener('click', function () {
      if (button.dataset.confirm && !window.confirm(button.dataset.confirm)) return;
      button.disabled = true;
      var body = button.dataset.body ? JSON.parse(button.dataset.body) : undefined;
      send(button.dataset.api, button.dataset.method || 'POST', body)
        .then(function (res) {
          if (res.status >= 200 && res.status < 300) return done(button.dataset.success);
          notice(res.data.message || 'This change was refused.');
        })
        .catch(function () { /* notice shown */ })
        .finally(function () { button.disabled = false; });
    });
  });

  document.querySelectorAll('input[data-toggle-api]').forEach(function (input) {
    input.addEventListener('change', function () {
      var body = {};
      body[input.dataset.field] = input.checked;
      input.disabled = true;
      send(input.dataset.toggleApi, input.dataset.method || 'POST', body)
        .then(function (res) {
          if (res.status >= 200 && res.status < 300) return done('reload');
          input.checked = !input.checked;
          notice(res.data.message || 'This change was refused.');
        })
        .catch(function () { input.checked = !input.checked; })
        .finally(function () { input.disabled = false; });
    });
  });

  // ---- rate forms: show the fields the method needs, and a plain-words preview ---

  document.querySelectorAll('[data-rate-form]').forEach(function (form) {
    var unit = form.dataset.unit || 'unit';

    function method() {
      var checked = form.querySelector('input[name="method"]:checked');
      return checked ? checked.value : null;
    }

    function update() {
      var m = method();
      form.querySelectorAll('[data-show-for]').forEach(function (el) {
        el.hidden = m === null || el.dataset.showFor.split(' ').indexOf(m) < 0;
      });
      var preview = form.querySelector('[data-preview]');
      if (!preview) return;
      var rate = (form.querySelector('[name="rate"]') || {}).value;
      var amount = (form.querySelector('[name="specific_amount"]') || {}).value;
      var combine = (form.querySelector('[name="combine"]') || {}).value;
      var pct = rate ? rate.trim() + '%' : '[rate]%';
      var per = 'K' + (amount ? amount.trim() : '[amount]') + ' per ' + unit;
      var text = m === 'ad_valorem' ? pct
        : m === 'specific' ? per
        : m === 'compound' ? (combine === 'sum' ? pct + ' plus ' + per : pct + ' or ' + per + ', whichever is higher')
        : '';
      var on = Array.prototype.map.call(form.querySelectorAll('input[name="base_components[]"]:checked'), function (c) {
        return c.dataset.label;
      }).join(' + ');
      preview.textContent = text ? text + (on && m !== 'specific' ? ', on the ' + on : '') : 'Choose a method.';
    }

    form.addEventListener('input', update);
    form.addEventListener('change', update);
    update();
  });

  // ---- handlers for multi-step forms ------------------------------------------

  var handlers = {
    /** Import step 2: show what would be added, then confirm with the token. */
    importPreview: function (form, data) {
      var box = document.getElementById('import-result');
      var tbody = box.querySelector('tbody');
      tbody.innerHTML = '';
      var total = 0;
      Object.keys(data.counts || {}).forEach(function (file) {
        var tr = document.createElement('tr');
        var a = document.createElement('td'); a.textContent = file;
        var b = document.createElement('td'); b.className = 'num'; b.textContent = data.counts[file];
        tr.appendChild(a); tr.appendChild(b); tbody.appendChild(tr);
        total += Number(data.counts[file]) || 0;
      });
      var confirm = document.getElementById('import-confirm');
      confirm.textContent = 'Import ' + total + ' rows';
      confirm.dataset.api = '/api/admin/imports/' + encodeURIComponent(data.token) + '/confirm';
      document.getElementById('import-errors').hidden = true;
      box.hidden = false;
      document.querySelectorAll('[data-step]').forEach(function (s) {
        s.toggleAttribute('aria-current', s.dataset.step === '2');
        if (s.dataset.step === '1') s.classList.add('done');
        if (s.dataset.step === '1') s.removeAttribute('aria-current');
      });
      box.scrollIntoView({ block: 'nearest' });
    },

    importPreviewError: function (form, data) {
      document.getElementById('import-result').hidden = true;
      if (!data.errors || !Array.isArray(data.errors)) return;
      var box = document.getElementById('import-errors');
      var tbody = box.querySelector('tbody');
      tbody.innerHTML = '';
      data.errors.forEach(function (e) {
        var tr = document.createElement('tr');
        [e.file, e.row, e.message].forEach(function (v, i) {
          var td = document.createElement('td');
          td.textContent = v === null || v === undefined ? '–' : v;
          if (i === 1) td.className = 'num';
          tr.appendChild(td);
        });
        tbody.appendChild(tr);
      });
      box.querySelector('[data-error-count]').textContent = data.errors.length === 1 ? '1 row needs fixing' : data.errors.length + ' rows need fixing';
      box.hidden = false;
      // The validation list above the form is not needed: the table says it all.
      var list = form.querySelector('[data-form-errors]');
      if (list) list.hidden = true;
    },
  };

  var importConfirm = document.getElementById('import-confirm');
  if (importConfirm) {
    importConfirm.addEventListener('click', function () {
      importConfirm.disabled = true;
      importConfirm.textContent = 'Importing…';
      send(importConfirm.dataset.api, 'POST')
        .then(function (res) {
          if (res.status === 200) { window.location.href = '/admin/log?type=imports'; return; }
          handlers.importPreviewError(document.getElementById('import-form'), res.data);
          if (!Array.isArray(res.data.errors)) notice(res.data.message || 'The import was refused. Nothing was saved.');
        })
        .catch(function () { /* notice shown */ })
        .finally(function () { importConfirm.disabled = false; });
    });
  }
})();
