/* Mesure d'audience anonyme — scienceexotic.fr
 * Guillaume Grondin - Enseignement Physique Chimie - Collège · CC BY-NC-SA 4.0
 * Aucun cookie, aucun identifiant de visiteur, aucune donnée personnelle envoyée.
 * Le navigateur garde seulement deux dates (première et dernière visite) pour savoir si la visite
 * est nouvelle ou habituelle ; elles sont effacées au bout de 13 mois. Détails : confidentialite.html
 */
(function () {
  "use strict";
  var COLLECTEUR = "https://scienceexotic.fr/api/collecte.php";
  var POSTHOG_CLE = "phc_C3NrbK5C7n4Mc7F3d2JoQ8xcAroQff3LVGUFQJRLWiAS";   // clé publique PostHog UE (faite pour être visible) ; "" = désactivé
  // Décision de Guillaume (10/10/2026) : le signal GPC est respecté ; « Ne pas suivre » (DNT, souvent activé par défaut) ne l'est plus,
  // puisque la mesure est anonyme (exemption CNIL). Voir confidentialite.html.
  if (navigator.globalPrivacyControl) return;

  var page = (/github\.io$/.test(location.hostname) ? location.pathname.replace(/^\/exerciseurs\//, "/") : location.pathname).replace(/^\/+/, "").replace(/\.html$/, "") || "accueil";
  page = page.toLowerCase().replace(/[^a-z0-9_\/.-]/g, "").slice(0, 80) || "accueil";
  if (/^admin/.test(page)) return;   // l'espace professeur n'est pas mesuré

  var JOUR = 864e5, maintenant = Date.now(), etat = null;
  try { etat = JSON.parse(localStorage.getItem("se_suivi") || "null"); } catch (e) {}
  if (!etat || !etat.p || maintenant - etat.p > 395 * JOUR) etat = null;      // 13 mois maximum
  var retour = etat ? "habitue" : "nouveau", ecart = "premiere";
  if (etat && etat.d) {
    var j = Math.floor((maintenant - etat.d) / JOUR);
    ecart = j <= 0 ? "0j" : j === 1 ? "1j" : j <= 7 ? "2-7j" : j <= 30 ? "8-30j" : "30j+";
  }
  try { localStorage.setItem("se_suivi", JSON.stringify({ p: etat ? etat.p : maintenant, d: maintenant })); } catch (e) {}

  var tz = ""; try { tz = Intl.DateTimeFormat().resolvedOptions().timeZone || ""; } catch (e) {}
  var l = Math.min(screen.width, screen.height);
  var appareil = /Mobi|Android/i.test(navigator.userAgent) ? (l >= 600 ? "tablette" : "mobile") : "ordi";
  var ref = document.referrer || "", source = "direct";
  if (ref) source = ref.indexOf(location.host) >= 0 ? "interne" : /google\.|bing\.|qwant\.|duckduckgo\.|ecosia\.|yahoo\./.test(ref) ? "moteur" : "autre";

  function envoyer(e, d, v) {
    var corps = JSON.stringify({ e: e, d: d, v: v });
    try {
      if (navigator.sendBeacon) navigator.sendBeacon(COLLECTEUR, new Blob([corps], { type: "text/plain" }));
      else fetch(COLLECTEUR, { method: "POST", body: corps, keepalive: true, headers: { "Content-Type": "text/plain" } });
    } catch (err) {}
    if (POSTHOG_CLE && window.posthog) { try { window.posthog.capture(e, Object.assign({ valeur: v }, d)); } catch (err) {} }
  }

  if (POSTHOG_CLE) {   // PostHog UE : sans cookie (mémoire seulement), sans profil de personne, sans capture automatique ni enregistrement
    try {
      /* chargeur officiel PostHog (file d'attente en attendant le script) */
      !function(t,e){var o,n,p,r;e.__SV||(window.posthog=e,e._i=[],e.init=function(i,s,a){function g(t,e){var o=e.split(".");2==o.length&&(t=t[o[0]],e=o[1]),t[e]=function(){t.push([e].concat(Array.prototype.slice.call(arguments,0)))}}(p=t.createElement("script")).type="text/javascript",p.crossOrigin="anonymous",p.async=!0,p.src=s.api_host.replace(".i.posthog.com","-assets.i.posthog.com")+"/static/array.js",(r=t.getElementsByTagName("script")[0]).parentNode.insertBefore(p,r);var u=e;for(void 0!==a?u=e[a]=[]:a="posthog",u.people=u.people||[],u.toString=function(t){var e="posthog";return"posthog"!==a&&(e+="."+a),t||(e+=" (stub)"),e},u.people.toString=function(){return u.toString(1)+".people (stub)"},o="capture register register_once unregister opt_out_capturing has_opted_out_capturing opt_in_capturing reset".split(" "),n=0;n<o.length;n++)g(u,o[n]);e._i.push([i,s,a])},e.__SV=1)}(document,window.posthog||[]);
      window.posthog.init(POSTHOG_CLE, { api_host: "https://eu.i.posthog.com", persistence: "memory", person_profiles: "identified_only",
        autocapture: false, capture_pageview: false, capture_pageleave: false, disable_session_recording: true, disable_surveys: true,
        advanced_disable_feature_flags: true, ip: false,
        capture_dead_clicks: false, capture_performance: false, capture_heatmaps: false, capture_exceptions: false, enable_heatmaps: false });
    } catch (e) {}
  }

  // Niveau déclaré par le visiteur (facultatif), redemandé à chaque rentrée scolaire (après le 15 août).
  var NIVEAUX = [["6e", "6e"], ["5e", "5e"], ["4e", "4e"], ["3e", "3e"], ["lycee", "Lycée"], ["adulte", "Adulte"], ["enseignant", "Enseignant(e)"]];
  var niveau = "";
  try {
    var nv = JSON.parse(localStorage.getItem("se_niveau") || "null"), an = new Date(), rentree = new Date(an.getFullYear(), 7, 15);
    if (an < rentree) rentree = new Date(an.getFullYear() - 1, 7, 15);
    if (nv && nv.n && nv.t >= rentree.getTime()) niveau = nv.n;
  } catch (e) {}

  envoyer("vue", { page: page, tz: tz, retour: retour, ecart: ecart, appareil: appareil, source: source, niveau: niveau || "inconnu" });

  var debut = maintenant, fini = false;
  function fin() {
    if (fini) return; fini = true;
    var s = (Date.now() - debut) / 1000;
    envoyer("duree", { page: page, tranche: s < 10 ? "0-10s" : s < 30 ? "10-30s" : s < 120 ? "30s-2min" : s < 600 ? "2-10min" : "10min+" });
  }
  addEventListener("pagehide", fin);
  document.addEventListener("visibilitychange", function () { if (document.visibilityState === "hidden") fin(); });

  window.suiviExo = function (d, score) {
    var x = { page: page, retour: retour, niveau: niveau || "inconnu" }; for (var k in d) x[k] = d[k];
    envoyer("exo", x, typeof score === "number" ? Math.round(score) : undefined);
  };

  // ---- Question du niveau : sur les pages destinées aux élèves (exerciseurs, séances, labos, cours) ----
  if (!niveau && /^(exerciseurs|seances|simulations|cours)\//.test(page)) {
    if (document.readyState === "loading") document.addEventListener("DOMContentLoaded", function () { setTimeout(poser, 1500); });
    else setTimeout(poser, 1500);
  }

  // ---- Exerciseurs : on lit seulement le résultat affiché à l'élève (aucune réponse saisie n'est envoyée) ----
  if (!/^exerciseurs\//.test(page)) return;
  var chapitre = page.replace(/^exerciseurs\//, "").slice(0, 40), essais = {}, nbDefis = 0, modeDefi = "defi-classique";
  function tranche(n) { return n <= 3 ? String(n) : n <= 5 ? "4-5" : "6+"; }
  function onglet() { var b = document.querySelector('nav.tabs button[aria-selected="true"]'); return b ? (b.getAttribute("data-id") || "?") : "?"; }
  document.addEventListener("click", function (ev) {
    var b = ev.target && ev.target.closest ? ev.target.closest("button") : null;
    if (!b) return;
    try {
      if (b.id === "chalOk") {   // une question du défi : on retient seulement le parcours (classique ou expert)
        var h2 = document.querySelector(".ex-head h2");
        modeDefi = h2 && /^Expert/.test(h2.textContent) ? "defi-expert" : "defi-classique";
        return;
      }
      if (b.id === "chalNext") {  // dernière question validée -> le résultat sur 20 vient d'être affiché
        var note = document.getElementById("chalNote");
        if (!note) return;
        var m = /(\d+)\s*\/\s*20/.exec(note.textContent || "");
        if (!m) return;
        nbDefis++;
        window.suiviExo({ chapitre: chapitre, exercice: "defi", mode: modeDefi, essai: tranche(nbDefis), maitrise: +m[1] >= 15 ? "oui" : "non" }, +m[1] * 5);
        return;
      }
      if (b.classList.contains("primary") && /^V.rifier$/.test((b.textContent || "").trim())) {
        var sec = b.closest("section.ex"), sc = sec && sec.querySelector(".score");
        var r = sc && /(\d+)\s*\/\s*(\d+)/.exec(sc.textContent || "");
        if (!r || !+r[2]) return;
        var tous = Array.prototype.slice.call(document.querySelectorAll("section.ex")), ong = onglet();
        var id = ong + "-" + (tous.indexOf(sec) + 1);
        essais[id] = (essais[id] || 0) + 1;
        window.suiviExo({ chapitre: chapitre, exercice: id.slice(0, 40), mode: ong === "o0" ? "decouverte" : ong === "plus" ? "plus" : "outil",
                          essai: tranche(essais[id]), maitrise: +r[1] === +r[2] ? "oui" : "non" }, 100 * +r[1] / +r[2]);
      }
    } catch (e) {}
  });

  // ---- Bandeau de la question du niveau, une fois par année scolaire, sans bloquer la page ----
  function poser() {
    if (document.getElementById("se-niveau")) return;
    var st = document.createElement("style");
    st.textContent = "#se-niveau{position:fixed;left:12px;right:12px;bottom:12px;z-index:9999;max-width:560px;margin:0 auto;background:#fff;color:#16222E;border:1px solid #CCD6DD;border-radius:12px;box-shadow:0 6px 24px rgba(0,0,0,.18);padding:12px 14px;font:15px/1.4 system-ui,sans-serif}" +
      "#se-niveau p{margin:0 0 8px}#se-niveau small{color:#5A6B78}#se-niveau .se-b{display:flex;flex-wrap:wrap;gap:6px}" +
      "#se-niveau button{font:inherit;padding:7px 12px;border-radius:999px;border:1px solid #CCD6DD;background:#F4F7F9;color:inherit;cursor:pointer}" +
      "#se-niveau button.se-x{background:none;border-color:transparent;text-decoration:underline}" +
      "@media (prefers-color-scheme:dark){#se-niveau{background:#18232C;color:#E4ECF1;border-color:#2C3A45}#se-niveau small{color:#9AABB7}#se-niveau button{background:#1D2A34;border-color:#2C3A45}}" +
      "body.tableau #se-niveau{display:none}";
    var box = document.createElement("div");
    box.id = "se-niveau"; box.setAttribute("role", "dialog"); box.setAttribute("aria-label", "Ton niveau");
    box.innerHTML = "<p><b>Tu es en quelle classe ?</b><br><small>Réponse anonyme, pour améliorer les exercices. Rien d'autre n'est demandé.</small></p><div class=\"se-b\"></div>";
    var zone = box.querySelector(".se-b");
    function choisir(v) {
      niveau = v;
      try { localStorage.setItem("se_niveau", JSON.stringify({ n: v, t: Date.now() })); } catch (e) {}
      box.remove();
    }
    NIVEAUX.concat([["inconnu", "Je préfère ne pas dire"]]).forEach(function (n) {
      var bt = document.createElement("button"); bt.type = "button"; bt.textContent = n[1];
      if (n[0] === "inconnu") bt.className = "se-x";
      bt.onclick = function () { choisir(n[0]); }; zone.appendChild(bt);
    });
    document.head.appendChild(st); document.body.appendChild(box);
  }
})();
