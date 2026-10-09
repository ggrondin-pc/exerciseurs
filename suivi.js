/* Mesure d'audience anonyme — scienceexotic.fr
 * Guillaume Grondin - Enseignement Physique Chimie - Collège · CC BY-NC-SA 4.0
 * Aucun cookie, aucun identifiant de visiteur, aucune donnée personnelle envoyée.
 * Le navigateur garde seulement deux dates (première et dernière visite) pour savoir si la visite
 * est nouvelle ou habituelle ; elles sont effacées au bout de 13 mois. Détails : confidentialite.html
 */
(function () {
  "use strict";
  var COLLECTEUR = "https://scienceexotic.fr/api/collecte.php";
  var POSTHOG_CLE = "";            // clé publique PostHog (phc_…) : vide = PostHog désactivé
  if (navigator.globalPrivacyControl || navigator.doNotTrack === "1" || window.doNotTrack === "1") return;

  var page = location.pathname.replace(/^\/exerciseurs\//, "/").replace(/^\/+/, "").replace(/\.html$/, "") || "accueil";
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

  if (POSTHOG_CLE) {   // PostHog UE, sans cookie ni identifiant persistant (chargé seulement si une clé est fournie)
    var s = document.createElement("script"); s.async = true; s.src = "https://eu-assets.i.posthog.com/static/array.js";
    s.onload = function () { try { window.posthog.init(POSTHOG_CLE, { api_host: "https://eu.i.posthog.com", persistence: "memory", disable_session_recording: true, autocapture: false, capture_pageview: false, ip: false }); } catch (e) {} };
    document.head.appendChild(s);
  }

  envoyer("vue", { page: page, tz: tz, retour: retour, ecart: ecart, appareil: appareil, source: source });

  var debut = maintenant, fini = false;
  function fin() {
    if (fini) return; fini = true;
    var s = (Date.now() - debut) / 1000;
    envoyer("duree", { page: page, tranche: s < 10 ? "0-10s" : s < 30 ? "10-30s" : s < 120 ? "30s-2min" : s < 600 ? "2-10min" : "10min+" });
  }
  addEventListener("pagehide", fin);
  document.addEventListener("visibilitychange", function () { if (document.visibilityState === "hidden") fin(); });

  // Pour plus tard : les exerciseurs appelleront window.suiviExo({chapitre, exercice, mode, essai, maitrise, niveau}, score sur 100)
  window.suiviExo = function (d, score) {
    var x = { page: page, retour: retour }; for (var k in d) x[k] = d[k];
    envoyer("exo", x, typeof score === "number" ? score : undefined);
  };
})();
