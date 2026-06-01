/**
 * Tenant public form helpers.
 *
 * Handles first-party CAPTCHA progressive enhancement, busy indicators,
 * same-page success/error messages, form clearing after success, and the
 * delayed tenant email-list prompt.
 */
(function () {
  'use strict';

  function ready(callback) {
    if (document.readyState === 'loading') {
      document.addEventListener('DOMContentLoaded', callback);
      return;
    }
    callback();
  }

  function textFromHtml(html) {
    var container = document.createElement('div');
    container.innerHTML = html || '';
    var text = (container.textContent || container.innerText || '').replace(/\s+/g, ' ').trim();
    return text || 'The form could not be submitted. Please try again.';
  }

  function initCaptcha() {
    document.querySelectorAll('[data-af-captcha]').forEach(function (captcha) {
      var checkbox = captcha.querySelector('input[name="af_captcha_confirm"]');
      var honeypot = captcha.querySelector('input[name="website_url"]');

      if (honeypot) {
        honeypot.setAttribute('aria-hidden', 'true');
        honeypot.setAttribute('tabindex', '-1');
        honeypot.style.position = 'absolute';
        honeypot.style.left = '-10000px';
        honeypot.style.top = 'auto';
        honeypot.style.width = '1px';
        honeypot.style.height = '1px';
        honeypot.style.overflow = 'hidden';
        honeypot.style.opacity = '0';
        honeypot.style.pointerEvents = 'none';
      }

      if (!checkbox) {
        return;
      }

      // Progressive enhancement only: the server enforces dwell time. We avoid
      // permanently disabling the checkbox because a cached/missing script would
      // otherwise make the form impossible to submit.
      checkbox.disabled = true;
      window.setTimeout(function () {
        checkbox.disabled = false;
      }, 2000);
    });
  }

  function resultBox(form) {
    var id = form.getAttribute('data-af-result');
    if (id) {
      return document.getElementById(id);
    }
    return form.querySelector('[data-af-form-result]');
  }

  function setResult(form, type, message) {
    var box = resultBox(form);
    if (!box) {
      return;
    }
    box.className = 'af-form-result ' + (type === 'success' ? 'success' : (type === 'info' ? 'info' : 'error'));
    box.textContent = message;
    box.hidden = false;
  }

  function clearCaptcha(form) {
    var checkbox = form.querySelector('input[name="af_captcha_confirm"]');
    if (checkbox) {
      checkbox.checked = false;
      checkbox.disabled = true;
      window.setTimeout(function () {
        checkbox.disabled = false;
      }, 2000);
    }
  }

  function decodeResponse(response) {
    var contentType = response.headers.get('content-type') || '';
    if (contentType.indexOf('application/json') !== -1) {
      return response.json().then(function (payload) {
        payload.httpOk = response.ok;
        payload.status = response.status;
        return payload;
      });
    }

    return response.text().then(function (body) {
      return {
        ok: false,
        httpOk: response.ok,
        status: response.status,
        message: textFromHtml(body)
      };
    });
  }

  function initAsyncForms() {
    document.querySelectorAll('form[data-af-async-form]').forEach(function (form) {
      form.addEventListener('submit', function (event) {
        event.preventDefault();

        var button = form.querySelector('button[type="submit"], input[type="submit"]');
        var originalLabel = button ? (button.tagName === 'INPUT' ? button.value : button.textContent) : '';
        var busyLabel = form.getAttribute('data-af-busy-label') || 'Sending...';

        if (button) {
          button.disabled = true;
          button.setAttribute('aria-busy', 'true');
          if (button.tagName === 'INPUT') {
            button.value = busyLabel;
          } else {
            button.textContent = busyLabel;
          }
        }
        setResult(form, 'info', form.getAttribute('data-af-busy-message') || busyLabel);

        fetch(form.action || window.location.href, {
          method: 'POST',
          body: new FormData(form),
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          credentials: 'same-origin'
        }).then(decodeResponse).then(function (payload) {
          if (payload.ok === true) {
            setResult(form, 'success', payload.message || 'Sent.');
            form.reset();
            clearCaptcha(form);
            return;
          }

          setResult(form, 'error', payload.message || 'Please check the form and try again.');
          clearCaptcha(form);
        }).catch(function (error) {
          setResult(form, 'error', error && error.message ? error.message : 'The form could not be submitted. Please try again.');
          clearCaptcha(form);
        }).finally(function () {
          if (button) {
            button.disabled = false;
            button.removeAttribute('aria-busy');
            if (button.tagName === 'INPUT') {
              button.value = originalLabel;
            } else {
              button.textContent = originalLabel;
            }
          }
        });
      });
    });
  }

  function initSignupPrompt() {
    // The old delayed modal/prompt was retired in favor of the always-visible footer signup form.
  }

  ready(function () {
    initCaptcha();
    initAsyncForms();
    initSignupPrompt();
  });
}());

// End of file.
