(function () {
  "use strict";

  var SCROLL_KEY = "admin_scroll";

  function readContext() {
    var el = document.getElementById("admin-dashboard-context");
    if (!el || !el.textContent) return {};
    try {
      return JSON.parse(el.textContent);
    } catch (_) {
      return {};
    }
  }

  function openModal(id) {
    var m = document.getElementById(id);
    if (m) m.classList.add("is-open");
  }

  function closeModal(modal) {
    if (modal) modal.classList.remove("is-open");
  }

  document.addEventListener("submit", function (e) {
    var f = e.target;
    if (
      f &&
      f.getAttribute("method") === "post" &&
      f.querySelector('[name="action"]') &&
      f.querySelector('[name="action"]').value !== "logout"
    ) {
      try {
        sessionStorage.setItem(SCROLL_KEY, String(window.scrollY));
      } catch (_) {}
    }
  }, true);

  document.querySelectorAll(".js-modal-close").forEach(function (btn) {
    btn.addEventListener("click", function () {
      var id = btn.getAttribute("data-modal");
      var m = id ? document.getElementById(id) : btn.closest(".modal");
      closeModal(m || btn.closest(".modal"));
    });
  });

  document.querySelectorAll(".modal").forEach(function (modal) {
    modal.addEventListener("click", function (e) {
      if (e.target === modal) closeModal(modal);
    });
  });

  window.editCat = function (id, title) {
    var idEl = document.getElementById("editCatId");
    var titleEl = document.getElementById("editCatTitle");
    if (idEl) idEl.value = id;
    if (titleEl) titleEl.value = title;
    openModal("editCatModal");
  };

  window.editGalleryItem = function (id, caption, img) {
    var idEl = document.getElementById("editGalleryId");
    var capEl = document.getElementById("editGalleryCaption");
    var fileEl = document.getElementById("editGalleryImage");
    var prev = document.getElementById("editGalleryPreview");
    if (idEl) idEl.value = id;
    if (capEl) capEl.value = caption;
    if (fileEl) fileEl.value = "";
    if (prev) {
      prev.innerHTML = "";
      if (img) {
        var i = document.createElement("img");
        i.src = "/" + img;
        i.alt = "";
        i.className = "admin-preview-thumb";
        prev.appendChild(i);
      }
    }
    openModal("editGalleryModal");
  };

  var ctx = readContext();
  var open = ctx.open || "";
  var scroll = ctx.scroll || "";

  function restoreScrollY() {
    try {
      var y = sessionStorage.getItem(SCROLL_KEY);
      if (y !== null) {
        sessionStorage.removeItem(SCROLL_KEY);
        requestAnimationFrame(function () {
          window.scrollTo(0, parseInt(y, 10) || 0);
        });
      }
    } catch (_) {}
  }

  if (open) {
    var itemEl = document.getElementById("item-" + open);
    if (itemEl) {
      itemEl.setAttribute("open", "");
      itemEl.scrollIntoView({ behavior: "smooth", block: "start" });
      try {
        sessionStorage.removeItem(SCROLL_KEY);
      } catch (_) {}
    } else {
      restoreScrollY();
    }
  } else if (scroll) {
    var catEl = document.getElementById("cat-" + scroll) || document.getElementById("catrow-" + scroll);
    if (catEl) {
      catEl.scrollIntoView({ behavior: "smooth", block: "start" });
      try {
        sessionStorage.removeItem(SCROLL_KEY);
      } catch (_) {}
    } else {
      restoreScrollY();
    }
  } else {
    restoreScrollY();
  }
})();
