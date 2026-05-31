/**
 * Tenant public form helpers: checkbox CAPTCHA unlock, busy state, AJAX result
 * display, contact-form clearing, and delayed email-list prompt.
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

  function initCaptcha() {
    document.querySelectorAll('[data-af-captcha]').forEach(function (captcha) {
      var checkbox = captcha.querySelector('input[name="af_captcha_confirm"]');
      if (!checkbox) {
        return;
      }
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
    box.className = 'af-form-result ' + (type === 'success' ? 'success' : 'error');
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

  function initAsyncForms() {
    document.querySelectorAll('form[data-af-async-form]').forEach(function (form) {
      form.addEventListener('submit', function (event) {
        event.preventDefault();
        var button = form.querySelector('button[type="submit"]');
        var originalLabel = button ? button.textContent : '';
        if (button) {
          button.disabled = true;
          button.setAttribute('aria-busy', 'true');
          button.textContent = form.getAttribute('data-af-busy-label') || 'Sending...';
        }
        setResult(form, 'info', form.getAttribute('data-af-busy-message') || 'Sending...');

        fetch(form.action, {
          method: 'POST',
          body: new FormData(form),
          headers: {
            'Accept': 'application/json',
            'X-Requested-With': 'XMLHttpRequest'
          },
          credentials: 'same-origin'
        }).then(function (response) {
          return response.json().catch(function () {
            return { ok: false, message: 'The form could not be submitted. Please try again.' };
          }).then(function (payload) {
            payload.httpOk = response.ok;
            return payload;
          });
        }).then(function (payload) {
          if (payload.ok) {
            setResult(form, 'success', payload.message || 'Sent.');
            form.reset();
            clearCaptcha(form);
            return;
          }
          setResult(form, 'error', payload.message || 'Please check the form and try again.');
          clearCaptcha(form);
        }).catch(function () {
          setResult(form, 'error', 'The form could not be submitted. Please try again.');
          clearCaptcha(form);
        }).finally(function () {
          if (button) {
            button.disabled = false;
            button.removeAttribute('aria-busy');
            button.textContent = originalLabel;
          }
        });
      });
    });
  }

  function initSignupPrompt() {
    var prompt = document.querySelector('[data-af-signup-prompt]');
    if (!prompt || window.localStorage.getItem('af_signup_prompt_dismissed') === '1') {
      return;
    }
    window.setTimeout(function () {
      prompt.hidden = false;
      prompt.setAttribute('aria-hidden', 'false');
    }, 60000);

    prompt.querySelectorAll('[data-af-signup-dismiss]').forEach(function (button) {
      button.addEventListener('click', function () {
        window.localStorage.setItem('af_signup_prompt_dismissed', '1');
        prompt.hidden = true;
        prompt.setAttribute('aria-hidden', 'true');
      });
    });
  }

  ready(function () {
    initCaptcha();
    initAsyncForms();
    initSignupPrompt();
  });
}());

// End of file.
