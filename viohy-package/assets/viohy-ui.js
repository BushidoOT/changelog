(() => {
  'use strict';

  const BRAND_RE = /BioAkış|BioAkis|BİOAKIŞ/g;
  const BRAND_TEST_RE = /BioAkış|BioAkis|BİOAKIŞ/;
  const BRAND_LOWER_RE = /bioakis\.com|bioakış\.com/gi;
  const BRAND_LOWER_TEST_RE = /bioakis\.com|bioakış\.com/i;
  const logoUrl = '/assets/viohy/viohy-mark.svg';

  function ready(fn) {
    if (document.readyState === 'loading') document.addEventListener('DOMContentLoaded', fn, { once: true });
    else fn();
  }

  function replaceBrandText(root = document.body) {
    if (!root) return;
    const walker = document.createTreeWalker(root, NodeFilter.SHOW_TEXT, {
      acceptNode(node) {
        const parent = node.parentElement;
        if (!parent || ['SCRIPT', 'STYLE', 'TEXTAREA', 'CODE', 'PRE'].includes(parent.tagName)) return NodeFilter.FILTER_REJECT;
        const value = node.nodeValue || '';
        return BRAND_TEST_RE.test(value) || BRAND_LOWER_TEST_RE.test(value)
          ? NodeFilter.FILTER_ACCEPT
          : NodeFilter.FILTER_REJECT;
      }
    });
    const nodes = [];
    while (walker.nextNode()) nodes.push(walker.currentNode);
    nodes.forEach(node => {
      node.nodeValue = (node.nodeValue || '')
        .replace(BRAND_RE, 'VIOHY')
        .replace(BRAND_LOWER_RE, 'viohy.com');
    });
    if (document.title) {
      document.title = document.title.replace(BRAND_RE, 'VIOHY').replace(BRAND_LOWER_RE, 'viohy.com');
    }
  }

  function installBrandLockups() {
    const candidates = [...document.querySelectorAll('a, div, span')].filter(el => {
      if (el.children.length > 4) return false;
      const text = (el.textContent || '').trim();
      return /^(BioAkış|BioAkis|BİOAKIŞ)$/.test(text) ||
        ((el.className || '').toString().match(/brand|logo/i) && /BioAkış|BioAkis/.test(text));
    });

    candidates.slice(0, 12).forEach(el => {
      if (el.dataset.viohyBranded === '1') return;
      el.dataset.viohyBranded = '1';
      el.classList.add('viohy-brand-lockup');
      el.innerHTML = `<img src="${logoUrl}" alt="" width="34" height="34"><span>VIOHY</span>`;
      if (el.tagName === 'A' && !el.getAttribute('href')) el.setAttribute('href', '/');
    });
  }

  function wrapTables() {
    document.querySelectorAll('table').forEach(table => {
      if (table.parentElement?.classList.contains('viohy-table-wrap')) return;
      const wrap = document.createElement('div');
      wrap.className = 'viohy-table-wrap';
      table.parentNode.insertBefore(wrap, table);
      wrap.appendChild(table);
    });
  }

  function improveForms() {
    document.querySelectorAll('input[type="password"]').forEach(input => {
      if (input.closest('.viohy-password-wrap')) return;
      const wrap = document.createElement('div');
      wrap.className = 'viohy-password-wrap';
      input.parentNode.insertBefore(wrap, input);
      wrap.appendChild(input);
      const btn = document.createElement('button');
      btn.type = 'button';
      btn.className = 'viohy-password-toggle';
      btn.setAttribute('aria-label', 'Şifreyi göster');
      btn.innerHTML = '◉';
      btn.addEventListener('click', () => {
        const show = input.type === 'password';
        input.type = show ? 'text' : 'password';
        btn.setAttribute('aria-label', show ? 'Şifreyi gizle' : 'Şifreyi göster');
        btn.textContent = show ? '◌' : '◉';
      });
      wrap.appendChild(btn);
    });

    document.querySelectorAll('form').forEach(form => {
      if (form.dataset.viohyGuard === '1') return;
      form.dataset.viohyGuard = '1';
      form.addEventListener('submit', event => {
        if (form.dataset.submitting === '1') {
          event.preventDefault();
          return;
        }
        if (!form.checkValidity()) return;
        form.dataset.submitting = '1';
        const submitters = form.querySelectorAll('button[type="submit"], input[type="submit"]');
        submitters.forEach(btn => {
          btn.dataset.originalText = btn.tagName === 'INPUT' ? btn.value : btn.textContent;
          if (btn.tagName === 'INPUT') btn.value = 'İşleniyor…'; else btn.textContent = 'İşleniyor…';
          btn.disabled = true;
        });
        window.setTimeout(() => {
          form.dataset.submitting = '0';
          submitters.forEach(btn => {
            if (btn.tagName === 'INPUT') btn.value = btn.dataset.originalText || 'Gönder';
            else btn.textContent = btn.dataset.originalText || 'Gönder';
            btn.disabled = false;
          });
        }, 8000);
      });
    });
  }

  function markActiveNavigation() {
    const path = location.pathname.replace(/\/$/, '') || '/';
    document.querySelectorAll('nav a[href], .sidebar a[href], [class*="sidebar"] a[href]').forEach(link => {
      try {
        const url = new URL(link.href, location.origin);
        const linkPath = url.pathname.replace(/\/$/, '') || '/';
        if (linkPath === path || (linkPath !== '/' && path.startsWith(linkPath + '/'))) {
          link.classList.add('viohy-active-nav');
          link.setAttribute('aria-current', 'page');
        }
      } catch (_) {}
    });
  }

  function improveImagesAndLinks() {
    document.querySelectorAll('img').forEach(img => {
      if (!img.hasAttribute('loading')) img.loading = 'lazy';
      img.decoding = 'async';
      img.addEventListener('error', () => {
        img.classList.add('viohy-image-fallback');
        if (!img.alt) img.alt = 'Görsel yüklenemedi';
      }, { once: true });
    });
    document.querySelectorAll('a[target="_blank"]').forEach(link => {
      const rel = new Set((link.getAttribute('rel') || '').split(/\s+/).filter(Boolean));
      rel.add('noopener'); rel.add('noreferrer');
      link.setAttribute('rel', [...rel].join(' '));
    });
  }

  function labelIconButtons() {
    document.querySelectorAll('button, [role="button"]').forEach(button => {
      if ((button.textContent || '').trim() || button.getAttribute('aria-label')) return;
      const title = button.getAttribute('title') || 'İşlem';
      button.setAttribute('aria-label', title);
    });
  }

  function addToastHost() {
    if (document.querySelector('.viohy-toast')) return;
    const toast = document.createElement('div');
    toast.className = 'viohy-toast';
    toast.setAttribute('role', 'status');
    toast.setAttribute('aria-live', 'polite');
    document.body.appendChild(toast);
    window.VIOHYToast = (message, timeout = 3000) => {
      toast.textContent = message;
      toast.classList.add('show');
      clearTimeout(toast._timer);
      toast._timer = setTimeout(() => toast.classList.remove('show'), timeout);
    };
  }

  function watchDynamicContent() {
    const observer = new MutationObserver(mutations => {
      let needsRefresh = false;
      for (const mutation of mutations) {
        if (mutation.addedNodes.length) { needsRefresh = true; break; }
      }
      if (!needsRefresh) return;
      clearTimeout(observer._timer);
      observer._timer = setTimeout(() => {
        replaceBrandText();
        installBrandLockups();
        wrapTables();
        improveForms();
        labelIconButtons();
      }, 80);
    });
    observer.observe(document.body, { childList: true, subtree: true });
  }

  ready(() => {
    document.body.classList.add('viohy-modern');
    replaceBrandText();
    installBrandLockups();
    wrapTables();
    improveForms();
    markActiveNavigation();
    improveImagesAndLinks();
    labelIconButtons();
    addToastHost();
    watchDynamicContent();
  });
})();
