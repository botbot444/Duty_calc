/*
 * DutyCalc trader screens: search → suggestions (→ clarify) → confirm → trade details.
 * Plain JavaScript, no build step. Every calculation happens on the server; this file only
 * moves between screens, calls the JSON endpoints and shows their answers.
 *
 * State lives in sessionStorage for this tab, so the browser's Back button and a language
 * switch (a page reload) keep the trader's place. The screen is in the URL hash.
 */
(function () {
  'use strict';

  var DC = window.DutyCalc;
  var STORE = 'dutycalc.state';
  var SCREENS = ['search', 'suggestions', 'clarify', 'confirm', 'details'];
  var MAX_SUGGESTIONS = 5;
  var MAX_SIDE = 1600;

  var csrf = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
  var $ = function (id) { return document.getElementById(id); };

  // ---- strings -----------------------------------------------------------

  function t(key, params) {
    var s = (DC.strings && DC.strings[key]) || key;
    Object.keys(params || {}).forEach(function (k) { s = s.split(':' + k).join(params[k]); });
    return s;
  }

  function unitLabel(unit) {
    return { kg: t('kilograms'), l: t('litres'), u: t('units (pieces)') }[unit] || unit;
  }

  function hs(code) {
    return code && code.length === 8 ? code.slice(0, 4) + '.' + code.slice(4, 6) + '.' + code.slice(6) : (code || '');
  }

  // ---- state -------------------------------------------------------------

  var state = load();

  function blank() {
    return { query: '', outcome: null, answers: [], chosen: null, selection: null, form: {} };
  }

  function load() {
    try {
      var params = new URLSearchParams(window.location.search);
      if (params.has('new')) {
        sessionStorage.removeItem(STORE);
        history.replaceState(null, '', window.location.pathname);
        return blank();
      }
      var saved = JSON.parse(sessionStorage.getItem(STORE) || 'null');
      return saved && typeof saved === 'object' ? Object.assign(blank(), saved) : blank();
    } catch (e) {
      return blank();
    }
  }

  function save() {
    try { sessionStorage.setItem(STORE, JSON.stringify(state)); } catch (e) { /* private mode: fine */ }
  }

  // ---- talking to the server ----------------------------------------------

  /** Resolves {status, data}. Network failures and expired pages show the notice and reject. */
  function api(url, body, retry) {
    var isForm = body instanceof FormData;
    var headers = { 'Accept': 'application/json', 'X-CSRF-TOKEN': csrf, 'X-Requested-With': 'XMLHttpRequest' };
    if (!isForm) headers['Content-Type'] = 'application/json';

    hideNotice();
    return fetch(url, { method: 'POST', headers: headers, body: isForm ? body : JSON.stringify(body), credentials: 'same-origin' })
      .catch(function () {
        showNotice(t('No internet connection. Check your connection and try again.'), t('Try again'), retry);
        throw new Error('network');
      })
      .then(function (res) {
        return res.json().catch(function () { return {}; }).then(function (data) {
          if (res.status === 419) {
            showNotice(t('This page has expired. Reload the page and try again.'), t('Try again'), function () { window.location.reload(); });
            throw new Error('expired');
          }
          if (res.status >= 500) {
            showNotice(t('Something went wrong. Please try again.'), t('Try again'), retry);
            throw new Error('server');
          }
          return { status: res.status, data: data };
        });
      });
  }

  function firstError(data, field) {
    return data && data.errors && data.errors[field] ? data.errors[field][0] : null;
  }

  // ---- notice and busy buttons ---------------------------------------------

  var noticeAction = null;

  function showNotice(text, actionLabel, action) {
    $('notice-text').textContent = text;
    $('notice-action').textContent = actionLabel;
    $('notice-action').hidden = !action;
    noticeAction = action || null;
    $('notice').hidden = false;
    $('notice').scrollIntoView({ block: 'nearest' });
  }

  function hideNotice() { $('notice').hidden = true; }

  $('notice-action').addEventListener('click', function () {
    hideNotice();
    if (noticeAction) noticeAction();
  });

  function busy(button, label) {
    button.dataset.label = button.dataset.label || button.textContent;
    button.disabled = true;
    button.setAttribute('aria-busy', 'true');
    button.innerHTML = '';
    var spin = document.createElement('span');
    spin.className = 'spinner';
    spin.setAttribute('aria-hidden', 'true');
    button.appendChild(spin);
    button.appendChild(document.createTextNode(label));
  }

  function idle(button) {
    button.disabled = false;
    button.removeAttribute('aria-busy');
    if (button.dataset.label) button.textContent = button.dataset.label;
  }

  // ---- screens -----------------------------------------------------------

  function go(screen) {
    if (window.location.hash === '#' + screen) {
      render(screen);
    } else {
      window.location.hash = screen;   // hashchange renders it, and Back works
    }
  }

  function currentScreen() {
    var name = window.location.hash.replace('#', '');
    return SCREENS.indexOf(name) >= 0 ? name : 'search';
  }

  /** Screens that need earlier answers fall back to the search screen. */
  function usable(screen) {
    if (screen === 'suggestions') return !!state.outcome;
    if (screen === 'clarify') return !!(state.outcome && state.outcome.clarification_questions && state.outcome.clarification_questions.length);
    if (screen === 'confirm') return !!state.chosen;
    if (screen === 'details') return !!state.selection;
    return true;
  }

  function render(screen, initial) {
    if (!usable(screen)) screen = 'search';
    document.querySelectorAll('[data-screen]').forEach(function (s) { s.hidden = s.dataset.screen !== screen; });
    ({ search: renderSearch, suggestions: renderSuggestions, clarify: renderClarify, confirm: renderConfirm, details: renderDetails })[screen]();

    // Move focus to the new screen's heading so screen readers announce it (not on first load).
    var title = document.querySelector('[data-screen="' + screen + '"] h1');
    if (title && !initial) {
      if (!title.hasAttribute('tabindex')) title.setAttribute('tabindex', '-1');
      title.focus({ preventScroll: true });
      window.scrollTo(0, 0);
    }
  }

  window.addEventListener('hashchange', function () { render(currentScreen()); });

  document.querySelectorAll('[data-go]').forEach(function (el) {
    el.addEventListener('click', function () { go(el.dataset.go); });
  });

  document.querySelectorAll('[data-new-estimate]').forEach(function (el) {
    el.addEventListener('click', function () {
      try { sessionStorage.removeItem(STORE); } catch (e) { /* ignore */ }
    });
  });

  // ---- 1. search ---------------------------------------------------------

  function renderSearch() {
    $('q').value = state.query || '';
    $('photo-status').hidden = true;
  }

  function setSearchError(text) {
    $('q-error').textContent = text || '';
    $('q-error').hidden = !text;
    $('search-field').classList.toggle('invalid', !!text);
    $('q').setAttribute('aria-invalid', text ? 'true' : 'false');
  }

  $('search-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var q = $('q').value.trim();
    setSearchError(null);
    $('photo-error').hidden = true;
    if (!q) {
      setSearchError(t('Type a word to search.'));
      $('q').focus();
      return;
    }
    search(q);
  });

  function search(q) {
    var button = $('search-btn');
    busy(button, t('Searching…'));
    api(DC.urls.identifyText, { description: q, lang: DC.locale }, function () { search(q); })
      .then(function (res) {
        if (res.status === 422) {
          setSearchError(firstError(res.data, 'description') || t('Type a word to search.'));
          return;
        }
        state.query = q;
        state.answers = [];
        outcome(res.data);
      })
      .catch(function () { /* notice already shown */ })
      .finally(function () { idle(button); });
  }

  /** Where an identification answer leads: a question, the suggestions, or (photo) a message. */
  function outcome(data) {
    state.outcome = data;
    state.chosen = null;
    save();

    var photo = data.input_type === 'image';
    if (data.status === 'needs_clarification' && data.clarification_questions && data.clarification_questions.length) {
      go('clarify');
    } else if (photo && (!data.results || !data.results.length)) {
      showPhotoError(t("We couldn't identify this photo. Try typing the product name."));
      go('search');
    } else {
      go('suggestions');
    }
  }

  // ---- photo -------------------------------------------------------------

  function showPhotoError(text) {
    $('photo-error-text').textContent = text;
    $('photo-error').hidden = false;
  }

  document.querySelectorAll('[data-photo-input]').forEach(function (input) {
    input.addEventListener('change', function () {
      var file = input.files && input.files[0];
      input.value = '';
      if (file) sendPhoto(file);
    });
  });

  function sendPhoto(file) {
    $('photo-error').hidden = true;
    setSearchError(null);

    if (file.type && file.type.indexOf('image/') !== 0) {
      showPhotoError(t('This file is not a photo. Use a JPEG, PNG or WebP image.'));
      return;
    }

    $('photo-status').hidden = false;
    shrink(file).then(function (blob) {
      var upload = blob || file;
      if (upload.size > DC.photoMaxMb * 1024 * 1024) {
        $('photo-status').hidden = true;
        showPhotoError(t('This photo is too large. The limit is :size MB.', { size: DC.photoMaxMb }));
        return;
      }

      var form = new FormData();
      form.append('photo', upload, blob ? 'photo.jpg' : (file.name || 'photo'));
      form.append('lang', DC.locale);

      return api(DC.urls.identifyImage, form, function () { sendPhoto(file); }).then(function (res) {
        if (res.status === 422) {
          showPhotoError(firstError(res.data, 'photo') || t('This file is not a photo. Use a JPEG, PNG or WebP image.'));
          return;
        }
        state.query = '';
        state.answers = [];
        outcome(res.data);
      });
    })
      .catch(function () { /* notice already shown */ })
      .finally(function () { $('photo-status').hidden = true; });
  }

  /**
   * Redraws the photo as a JPEG of at most 1600 px on the long side. This turns iPhone HEIC
   * into JPEG and keeps uploads small on slow connections. Resolves null if the browser
   * cannot read the image (the original is then sent and the server decides).
   */
  function shrink(file) {
    return decode(file).then(function (img) {
      var w = img.width, h = img.height;
      var scale = Math.min(1, MAX_SIDE / Math.max(w, h));
      var canvas = document.createElement('canvas');
      canvas.width = Math.round(w * scale);
      canvas.height = Math.round(h * scale);
      canvas.getContext('2d').drawImage(img, 0, 0, canvas.width, canvas.height);
      return new Promise(function (resolve) { canvas.toBlob(function (b) { resolve(b); }, 'image/jpeg', 0.85); });
    }).catch(function () { return null; });
  }

  function decode(file) {
    if (window.createImageBitmap) {
      return createImageBitmap(file, { imageOrientation: 'from-image' }).catch(function () { return viaImg(file); });
    }
    return viaImg(file);
  }

  function viaImg(file) {
    return new Promise(function (resolve, reject) {
      var url = URL.createObjectURL(file);
      var img = new Image();
      img.onload = function () { URL.revokeObjectURL(url); resolve(img); };
      img.onerror = function () { URL.revokeObjectURL(url); reject(new Error('decode')); };
      img.src = url;
    });
  }

  // ---- 2. suggestions -----------------------------------------------------

  var MATCH = { High: ['chip-high', 'Good match'], Medium: ['chip-medium', 'Possible match'], Low: ['chip-low', 'Weak match'] };

  function el(tag, cls, text) {
    var node = document.createElement(tag);
    if (cls) node.className = cls;
    if (text !== undefined && text !== null) node.textContent = text;
    return node;
  }

  function renderSuggestions() {
    var data = state.outcome || {};
    var results = (data.results || []).slice(0, MAX_SUGGESTIONS);

    $('query-chip').hidden = !state.query;
    $('query-text').textContent = state.query ? '“' + state.query + '”' : '';
    $('fallback-note').hidden = !(data.status === 'fallback' && results.length);

    var list = $('suggestion-list');
    list.innerHTML = '';
    results.forEach(function (item, i) {
      var card = el('article', 'card' + (i === 0 && item.label === 'High' ? ' selected' : ''));
      var head = el('div', 'card-head');
      var title = el('h2', 'card-title', item.name);
      head.appendChild(title);
      if (MATCH[item.label]) head.appendChild(el('span', 'chip ' + MATCH[item.label][0], t(MATCH[item.label][1])));
      card.appendChild(head);

      var facts = el('dl', 'facts');
      facts.appendChild(el('dt', null, t('HS code')));
      facts.appendChild(el('dd', 'num strong', hs(item.hs_code)));
      facts.appendChild(el('dt', null, t('Official description')));
      facts.appendChild(el('dd', null, item.official_description));
      card.appendChild(facts);

      var choose = el('button', 'btn ' + (i === 0 ? 'btn-primary' : 'btn-outline') + ' btn-sm', t('Choose'));
      choose.type = 'button';
      choose.setAttribute('aria-label', t('Choose') + ': ' + item.name);
      choose.addEventListener('click', function () {
        state.chosen = item;
        save();
        go('confirm');
      });
      card.appendChild(choose);
      list.appendChild(card);
    });

    var empty = !results.length;
    $('no-match').hidden = !empty;
    $('none-of-these').hidden = empty;
    if (empty) {
      $('no-match-text').textContent = data.ai_used
        ? t("We couldn't find a reliable match. Try describing the product in a different way.")
        : t("We couldn't find a match. Try another word or check the spelling.");
    }
  }

  // ---- 2b. clarify ---------------------------------------------------------

  function renderClarify() {
    var box = $('clarify-questions');
    box.innerHTML = '';
    (state.outcome.clarification_questions || []).forEach(function (question, i) {
      var field = el('div', 'field');
      field.appendChild(el('p', 'strong', question));
      var label = el('label', null, t('Your answer'));
      label.htmlFor = 'answer-' + i;
      var input = el('input', 'input');
      input.id = 'answer-' + i;
      input.maxLength = 300;
      input.dataset.question = question;
      input.autocomplete = 'off';
      field.appendChild(label);
      field.appendChild(input);
      box.appendChild(field);
    });
  }

  $('clarify-form').addEventListener('submit', function (e) {
    e.preventDefault();
    var answers = [];
    var missing = null;
    $('clarify-questions').querySelectorAll('input').forEach(function (input) {
      var value = input.value.trim();
      input.classList.toggle('invalid', !value);
      if (!value && !missing) missing = input;
      answers.push({ question: input.dataset.question, answer: value });
    });
    if (missing) { missing.focus(); return; }
    clarify(answers);
  });

  function clarify(answers) {
    var button = $('clarify-btn');
    busy(button, t('Please wait…'));
    api(DC.urls.clarify, { analysis_id: state.outcome.analysis_id, answers: answers }, function () { clarify(answers); })
      .then(function (res) {
        if (res.status === 422) {
          showNotice(t('Something went wrong. Please try again.'), t('Start again'), function () { go('search'); });
          return;
        }
        state.answers = state.answers.concat(answers);
        outcome(res.data);
      })
      .catch(function () { /* notice already shown */ })
      .finally(function () { idle(button); });
  }

  // ---- 3. confirm ----------------------------------------------------------

  function renderConfirm() {
    var item = state.chosen;
    $('confirm-name').textContent = item.name;
    $('confirm-hs').textContent = hs(item.hs_code);
    $('confirm-desc').textContent = item.official_description;

    var list = $('confirm-answer-list');
    list.innerHTML = '';
    state.answers.forEach(function (a) {
      var row = el('div', 'stack');
      row.appendChild(el('span', 'muted', a.question));
      row.appendChild(el('span', 'strong', a.answer));
      list.appendChild(row);
    });
    $('confirm-answers').hidden = !state.answers.length;
  }

  $('confirm-yes').addEventListener('click', confirmProduct);

  function confirmProduct() {
    var button = $('confirm-yes');
    var body = { catalogue_item_id: state.chosen.id };
    if (state.outcome && state.outcome.analysis_id) body.ai_product_analysis_id = state.outcome.analysis_id;

    busy(button, t('Please wait…'));
    api(DC.urls.selection, body, confirmProduct)
      .then(function (res) {
        if (res.status === 422) {
          showNotice(firstError(res.data, 'catalogue_item_id') || firstError(res.data, 'ai_product_analysis_id')
            || t('Something went wrong. Please try again.'), t('Start again'), function () { go('search'); });
          return;
        }
        if (!state.selection || state.selection.selected.id !== res.data.selected.id) {
          state.form.quantity = '';
        }
        state.selection = res.data;
        save();
        go('details');
      })
      .catch(function () { /* notice already shown */ })
      .finally(function () { idle(button); });
  }

  // ---- 4. trade details ----------------------------------------------------

  function destinations() {
    var seen = {};
    return DC.routes.filter(function (r) {
      if (seen[r.destination.iso2]) return false;
      seen[r.destination.iso2] = true;
      return true;
    });
  }

  function fillRoutes(destination, routeId) {
    var select = $('f-route');
    select.innerHTML = '';
    DC.routes.filter(function (r) { return r.destination.iso2 === destination; }).forEach(function (r) {
      var option = el('option', null, r.border_post);
      option.value = r.id;
      select.appendChild(option);
    });
    if (routeId) select.value = String(routeId);
  }

  function renderDetails() {
    var sel = state.selection;
    var form = state.form || {};
    $('details-product').textContent = sel.selected.name + ' · HS ' + hs(sel.selected.hs_code);
    $('details-error').hidden = true;
    clearFieldErrors();

    var first = DC.routes[0];
    $('from-line').textContent = first ? t('Going from: :country', { country: first.origin.name }) : '';

    var dest = $('f-destination');
    dest.innerHTML = '';
    destinations().forEach(function (r) {
      var option = el('option', null, r.destination.name);
      option.value = r.destination.iso2;
      dest.appendChild(option);
    });
    var chosenRoute = DC.routes.filter(function (r) { return String(r.id) === String(form.trade_route_id); })[0] || first;
    if (chosenRoute) dest.value = chosenRoute.destination.iso2;
    fillRoutes(dest.value, chosenRoute && chosenRoute.id);

    $('f-value').value = form.declared_value || '';
    if (form.currency && DC.currencies.indexOf(form.currency) >= 0) $('f-currency').value = form.currency;

    var optional = sel.optional_inputs || [];
    $('freight-field').hidden = optional.indexOf('freight') < 0;
    $('insurance-field').hidden = optional.indexOf('insurance') < 0;
    $('cost-fields').hidden = !optional.length;
    $('f-freight').value = form.freight || '';
    $('f-insurance').value = form.insurance || '';

    var quantity = sel.required_inputs && sel.required_inputs.quantity;
    $('quantity-field').hidden = !quantity;
    if (quantity) {
      $('quantity-unit').textContent = quantity.unit;
      $('h-quantity').textContent = t('Amount in :unit_label', { unit_label: unitLabel(quantity.unit) });
      $('f-quantity').value = form.quantity || '';
    }
  }

  $('f-destination').addEventListener('change', function () { fillRoutes(this.value); });

  function clearFieldErrors() {
    document.querySelectorAll('#details-form [data-field]').forEach(function (f) { f.classList.remove('invalid'); });
    document.querySelectorAll('#details-form .field-error').forEach(function (p) { p.hidden = true; p.textContent = ''; });
    document.querySelectorAll('#details-form [aria-invalid]').forEach(function (i) { i.removeAttribute('aria-invalid'); });
  }

  /** "10,000.50 " → "10000.50": commas are thousands separators in Zambia. */
  function number(value) {
    return String(value || '').replace(/[\s,]/g, '');
  }

  function collect() {
    var sel = state.selection;
    var body = {
      catalogue_item_id: sel.selected.id,
      trade_route_id: $('f-route').value,
      declared_value: number($('f-value').value),
      currency: $('f-currency').value,
    };
    if (!$('freight-field').hidden && number($('f-freight').value) !== '') body.freight = number($('f-freight').value);
    if (!$('insurance-field').hidden && number($('f-insurance').value) !== '') body.insurance = number($('f-insurance').value);
    if (!$('quantity-field').hidden) body.quantity = number($('f-quantity').value);

    state.form = {
      trade_route_id: body.trade_route_id,
      declared_value: $('f-value').value,
      currency: body.currency,
      freight: $('f-freight').value,
      insurance: $('f-insurance').value,
      quantity: $('f-quantity').value,
    };
    save();
    return body;
  }

  $('details-form').addEventListener('submit', function (e) {
    e.preventDefault();
    estimate(collect());
  });

  function estimate(body) {
    var button = $('estimate-btn');
    clearFieldErrors();
    $('details-error').hidden = true;
    busy(button, t('Working out your charges…'));

    api(DC.urls.estimates, body, function () { estimate(body); })
      .then(function (res) {
        if (res.status === 201 && res.data.estimate_id) {
          window.location.href = '/estimates/' + encodeURIComponent(res.data.estimate_id);
          return;
        }
        if (res.status === 422) {
          showFieldErrors(res.data.errors || {});
          return;
        }
        showNotice(t('Something went wrong. Please try again.'), t('Try again'), function () { estimate(body); });
      })
      .catch(function () { /* notice already shown */ })
      .finally(function () { idle(button); });
  }

  function showFieldErrors(errors) {
    if (errors.catalogue_item_id) {
      $('details-error-text').textContent = t('Please choose and confirm a product first.');
      $('details-error').hidden = false;
      $('details-error').scrollIntoView({ block: 'nearest' });
      return;
    }

    var firstInput = null;
    Object.keys(errors).forEach(function (field) {
      var message = $('e-' + field);
      var wrapper = document.querySelector('#details-form [data-field="' + field + '"]');
      if (!message || !wrapper) return;
      message.textContent = errors[field][0];
      message.hidden = false;
      wrapper.classList.add('invalid');
      var input = wrapper.querySelector('input, select');
      if (input) {
        input.setAttribute('aria-invalid', 'true');
        firstInput = firstInput || input;
      }
    });

    $('details-error-text').textContent = t('Some details need fixing. Check the boxes marked in red.');
    $('details-error').hidden = false;
    if (firstInput) firstInput.focus();
  }

  // ---- start ---------------------------------------------------------------

  render(currentScreen(), true);
})();
