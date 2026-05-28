// assets/js/catalog-table.js
(function () {
  // --- Export (server-side) ---
  const exportBtn = document.getElementById("toEXCEL");
  if (exportBtn) {
    exportBtn.addEventListener("click", function (e) {
      e.preventDefault();
      const input = document.querySelector('input[name="search"]');
      const typed = input ? input.value.trim() : '';
      const urlParam = new URLSearchParams(window.location.search).get('search') || '';
      const keyword = typed || urlParam;
      window.location.href = "/export?search=" + encodeURIComponent(keyword);
    });
  }
})();