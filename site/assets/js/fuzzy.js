/* DS Fuzzy — búsqueda tolerante a acentos y errores de dedo, compartida por todos los
   buscadores del sitio (catálogo, marcas, admin). Expone global.DSFuzzy. */
(function (global) {
  // Normaliza texto: minúsculas, sin acentos, trim.
  function normalizeText(s) {
    return String(s == null ? "" : s)
      .toLowerCase()
      .normalize("NFD").replace(/[\u0300-\u036f]/g, "")
      .trim();
  }

  // Distancia de edición (Levenshtein) entre dos palabras cortas.
  function levenshtein(a, b) {
    if (a === b) return 0;
    var la = a.length, lb = b.length;
    if (!la) return lb;
    if (!lb) return la;
    var prev = [], i, j;
    for (j = 0; j <= lb; j++) prev[j] = j;
    for (i = 1; i <= la; i++) {
      var cur = [i], ca = a.charCodeAt(i - 1);
      for (j = 1; j <= lb; j++) {
        var cost = ca === b.charCodeAt(j - 1) ? 0 : 1;
        cur[j] = Math.min(prev[j] + 1, cur[j - 1] + 1, prev[j - 1] + cost);
      }
      prev = cur;
    }
    return prev[lb];
  }

  // Errores de dedo permitidos según el largo de la palabra buscada.
  function fuzzyTolerance(len) {
    if (len <= 2) return 0;
    if (len <= 6) return 1;
    return 2;
  }

  // Puntúa un token contra las palabras de un campo.
  // 0 = no casa; mayor = mejor (exacto > empieza-con > contiene > difuso).
  function tokenScore(token, words) {
    var best = 0, tol = fuzzyTolerance(token.length), i, w, d;
    for (i = 0; i < words.length; i++) {
      w = words[i];
      if (!w) continue;
      if (w === token) return 100;
      if (w.indexOf(token) === 0) { best = Math.max(best, 90); continue; }
      if (w.indexOf(token) > -1) { best = Math.max(best, 70); continue; }
      if (tol > 0) {
        d = levenshtein(token, w);
        if (d <= tol) best = Math.max(best, 60 - d * 10);
      }
    }
    return best;
  }

  // Puntúa una consulta contra varios campos con peso.
  // fields = [{ text: "...", weight: 1 }, ...]. Devuelve 0 si algún token no casa
  // en ningún campo (todos los tokens deben aparecer).
  function scoreItem(query, fields) {
    var qn = normalizeText(query);
    if (!qn) return 1; // sin consulta: todo pasa
    var tokens = qn.split(/\s+/).filter(Boolean);
    if (!tokens.length) return 1;

    var fieldWords = fields.map(function (f) {
      return { words: normalizeText(f.text).split(/\s+/).filter(Boolean), weight: f.weight || 1 };
    });

    var total = 0;
    for (var t = 0; t < tokens.length; t++) {
      var tok = tokens[t], best = 0;
      for (var f = 0; f < fieldWords.length; f++) {
        best = Math.max(best, tokenScore(tok, fieldWords[f].words) * fieldWords[f].weight);
      }
      if (best <= 0) return 0; // este token no aparece en ningún campo
      total += best;
    }
    return total;
  }

  // Filtra y ordena por relevancia. fieldsFn(item) -> [{text, weight}].
  function filterSort(items, query, fieldsFn) {
    if (!normalizeText(query)) return items.slice();
    var scored = [];
    for (var i = 0; i < items.length; i++) {
      var s = scoreItem(query, fieldsFn(items[i]));
      if (s > 0) scored.push({ item: items[i], score: s });
    }
    scored.sort(function (a, b) { return b.score - a.score; });
    return scored.map(function (x) { return x.item; });
  }

  global.DSFuzzy = {
    normalizeText: normalizeText,
    levenshtein: levenshtein,
    fuzzyTolerance: fuzzyTolerance,
    tokenScore: tokenScore,
    scoreItem: scoreItem,
    filterSort: filterSort,
  };
})(window);
