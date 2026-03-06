/**
 * VV ToolBox — JS partagé layout
 * Nav rétractable, thème, toast, unsaved, partage
 */
(function(){
  'use strict';

  /* ── THÈME ─────────────────────────────────────────────── */
  var theme = localStorage.getItem('vv_theme') || 'dark';
  document.documentElement.setAttribute('data-theme', theme);

  function applyThemeIcon(){
    var ico = document.getElementById('themeIco');
    if (ico) ico.className = theme === 'light' ? 'fa fa-moon' : 'fa fa-sun';
  }
  applyThemeIcon();

  window.toggleTheme = function(){
    theme = theme === 'dark' ? 'light' : 'dark';
    document.documentElement.setAttribute('data-theme', theme);
    localStorage.setItem('vv_theme', theme);
    applyThemeIcon();
  };

  /* ── NAV RÉTRACTABLE ───────────────────────────────────── */
  var navMini = localStorage.getItem('vv_nav') === 'mini';

  function applyNav(){
    document.body.classList.toggle('nav-mini', navMini);
    var lbl = document.getElementById('navToggleLbl');
    var ico = document.getElementById('navToggleIco');
    if (ico) ico.className = navMini ? 'fa fa-chevron-right' : 'fa fa-chevron-left';
    if (lbl) lbl.textContent = navMini ? '' : 'Réduire';
  }

  window.toggleNav = function(){
    navMini = !navMini;
    localStorage.setItem('vv_nav', navMini ? 'mini' : 'full');
    applyNav();
  };

  document.addEventListener('DOMContentLoaded', function(){
    applyNav();

    /* Close overlays on backdrop click */
    document.querySelectorAll('.ov').forEach(function(el){
      el.addEventListener('click', function(e){
        if (e.target === el) el.classList.remove('open');
      });
    });
  });

  /* ── TOAST ─────────────────────────────────────────────── */
  var ttimer = null;
  window.toast = function(msg, type){
    var el = document.getElementById('T');
    if (!el) return;
    var icons = { success:'fa-check-circle', error:'fa-circle-exclamation', info:'fa-circle-info' };
    el.className = 'toast ' + (type || 'success');
    el.querySelector('i').className = 'fa ' + (icons[type] || icons.success);
    document.getElementById('TM').textContent = msg;
    el.classList.add('show');
    clearTimeout(ttimer);
    ttimer = setTimeout(function(){ el.classList.remove('show'); }, 3200);
  };

  /* ── UNSAVED STATE ─────────────────────────────────────── */
  window.markUnsaved = function(){
    var bar = document.getElementById('actionBar');
    var btn = document.getElementById('btnSave');
    if (bar) bar.classList.add('unsaved');
    if (btn) btn.classList.add('unsaved');
  };

  window.markSaved = function(){
    var bar  = document.getElementById('actionBar');
    var btn  = document.getElementById('btnSave');
    var lbl  = document.getElementById('btnSaveLbl');
    if (bar) bar.classList.remove('unsaved');
    if (btn) btn.classList.remove('unsaved', 'saving');
    if (lbl){
      lbl.textContent = 'Sauvegardé \u2713';
      setTimeout(function(){
        var l = document.getElementById('btnSaveLbl');
        if (l) l.textContent = 'Sauvegarder';
      }, 2200);
    }
  };

  /* ── MODAL HELPERS ─────────────────────────────────────── */
  window.openOv  = function(id){ var el=document.getElementById(id); if(el) el.classList.add('open'); };
  window.closeOv = function(id){ var el=document.getElementById(id); if(el) el.classList.remove('open'); };

  /* ── ESCAPE KEY ────────────────────────────────────────── */
  document.addEventListener('keydown', function(e){
    if (e.key === 'Escape'){
      document.querySelectorAll('.ov.open').forEach(function(el){
        el.classList.remove('open');
      });
    }
  });

})();
