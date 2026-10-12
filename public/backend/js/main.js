"use strict";

(function () {
  var sidebarStorageKey = "adminHMD.sidebarMini";
  var themeStorageKey = "adminHMD.colorTheme";
  var desktopMedia = "(min-width: 1024px)";

  function onReady(callback) {
    if (document.readyState === "loading") {
      document.addEventListener("DOMContentLoaded", callback);
      return;
    }

    callback();
  }

  function storageGet(key) {
    try {
      return window.localStorage.getItem(key);
    } catch (error) {
      return null;
    }
  }

  function storageSet(key, value) {
    try {
      window.localStorage.setItem(key, value);
    } catch (error) {
      return false;
    }

    return true;
  }

  function getPreferredTheme() {
    var savedTheme = storageGet(themeStorageKey);
    return savedTheme === "dark" ? "dark" : "light";
  }

  onReady(function () {
    var body = document.body;
    var root = document.documentElement;
    var sidebar = document.getElementById("adminSidebar");
    var sidebarToggle = document.querySelector("[data-sidebar-toggle]");
    var collapseToggle = document.querySelector("[data-sidebar-collapse-toggle]");
    var themeToggles = document.querySelectorAll("[data-theme-toggle]");
    var themeIcons = document.querySelectorAll("[data-theme-icon]");
    var sidebarLinks = document.querySelectorAll(".sidebar-nav a.nav-link");
    var sidebarItems = document.querySelectorAll(".sidebar-nav .nav-link");
    var submenuToggles = document.querySelectorAll("[data-sidebar-submenu-toggle]");
    var mediaQuery = window.matchMedia(desktopMedia);
    var flyout = null;
    var flyoutTrigger = null;
    var flyoutTimer = null;
    var storageAvailable = storageSet(sidebarStorageKey + ".test", "1");

    if (storageAvailable) {
      try {
        window.localStorage.removeItem(sidebarStorageKey + ".test");
      } catch (error) {
        storageAvailable = false;
      }
    }

    function initValidation() {
      var forms = document.querySelectorAll(".needs-validation");

      Array.prototype.forEach.call(forms, function (form) {
        form.addEventListener("submit", function (event) {
          if (!form.checkValidity()) {
            event.preventDefault();
            event.stopPropagation();
          }

          form.classList.add("was-validated");
        });
      });
    }

    function initTableSearch() {
      var searchInputs = document.querySelectorAll("[data-table-search]");

      Array.prototype.forEach.call(searchInputs, function (input) {
        var tableId = input.getAttribute("data-table-search");
        var table = document.getElementById(tableId);

        if (!table) {
          return;
        }

        input.addEventListener("input", function () {
          var query = input.value.trim().toLowerCase();
          var rows = table.querySelectorAll("tbody tr");

          Array.prototype.forEach.call(rows, function (row) {
            row.hidden = query !== "" && row.textContent.toLowerCase().indexOf(query) === -1;
          });
        });
      });
    }

    function updateThemeControls(theme) {
      var nextTheme = theme === "dark" ? "light" : "dark";
      var label = "Switch to " + nextTheme + " mode";
      var iconClass = theme === "dark" ? "bi bi-sun" : "bi bi-moon-stars";

      Array.prototype.forEach.call(themeToggles, function (button) {
        button.setAttribute("aria-label", label);
        button.setAttribute("title", label);
      });

      Array.prototype.forEach.call(themeIcons, function (icon) {
        icon.className = iconClass;
      });
    }

    function applyTheme(theme) {
      root.setAttribute("data-theme", theme);
      root.setAttribute("data-bs-theme", theme);
      storageSet(themeStorageKey, theme);
      updateThemeControls(theme);
    }

    function initThemeToggle() {
      applyTheme(getPreferredTheme());

      Array.prototype.forEach.call(themeToggles, function (button) {
        button.addEventListener("click", function () {
          var currentTheme = root.getAttribute("data-theme") === "dark" ? "dark" : "light";
          applyTheme(currentTheme === "dark" ? "light" : "dark");
        });
      });
    }

    function initUserProfile() {
      var user = window.adminHMDUser || {};
      var sidebarNameEl = document.querySelector(".sidebar-user strong");
      var sidebarWorkspaceEl = document.querySelector(".sidebar-user small");
      var profileNameEls = document.querySelectorAll(".profile-name");
      var profileAvatarEls = document.querySelectorAll(".profile-button .avatar-img");
      var sidebarAvatar = document.querySelector(".sidebar-user .avatar-img");

      if (sidebarNameEl && user.name) sidebarNameEl.textContent = user.name;
      if (sidebarWorkspaceEl && user.workspace) sidebarWorkspaceEl.textContent = user.workspace;
      if (sidebarAvatar && user.avatar) {
        sidebarAvatar.src = user.avatar;
        sidebarAvatar.alt = user.name;
      }

      Array.prototype.forEach.call(profileNameEls, function (el) {
        el.textContent = user.name;
      });
      Array.prototype.forEach.call(profileAvatarEls, function (img) {
        if (user.avatar) img.src = user.avatar;
        if (user.name) img.alt = user.name;
      });
    }

    function isDesktop() {
      return mediaQuery.matches;
    }

    function isMini() {
      return body.classList.contains("sidebar-mini");
    }

    function savedMiniState() {
      return storageAvailable && storageGet(sidebarStorageKey) === "true";
    }

    function updateToggleState() {
      if (!isDesktop()) {
        body.classList.remove("sidebar-mini");
        root.classList.remove("sidebar-mini");
      }

      var mobileExpanded = body.classList.contains("sidebar-open");
      var desktopExpanded = !isMini();

      if (sidebarToggle) {
        sidebarToggle.setAttribute("aria-expanded", String(isDesktop() ? desktopExpanded : mobileExpanded));
        sidebarToggle.setAttribute("aria-label", isDesktop()
          ? (desktopExpanded ? "Collapse sidebar" : "Expand sidebar")
          : (mobileExpanded ? "Close navigation" : "Open navigation"));
      }

      if (collapseToggle) {
        collapseToggle.setAttribute("aria-expanded", String(desktopExpanded));
        collapseToggle.setAttribute("aria-label", desktopExpanded ? "Collapse sidebar" : "Expand sidebar");
      }

      if (sidebar && !isDesktop()) {
        if (mobileExpanded) {
          sidebar.removeAttribute("aria-hidden");
          sidebar.removeAttribute("inert");
        } else {
          sidebar.setAttribute("aria-hidden", "true");
          sidebar.setAttribute("inert", "");
        }
      } else if (sidebar) {
        sidebar.removeAttribute("aria-hidden");
        sidebar.removeAttribute("inert");
      }
    }

    function closeFlyout() {
      if (flyoutTimer) {
        window.clearTimeout(flyoutTimer);
        flyoutTimer = null;
      }

      if (flyout && flyout.parentNode) {
        flyout.parentNode.removeChild(flyout);
      }

      if (flyoutTrigger) {
        if (!flyoutTrigger.hasAttribute("data-sidebar-submenu-toggle")) {
          flyoutTrigger.removeAttribute("aria-controls");
        }
        flyoutTrigger.removeAttribute("aria-describedby");
      }

      flyout = null;
      flyoutTrigger = null;
    }

    function scheduleFlyoutClose() {
      if (flyoutTimer) {
        window.clearTimeout(flyoutTimer);
      }

      flyoutTimer = window.setTimeout(closeFlyout, 250);
    }

    function positionFlyout(trigger) {
      var rect = trigger.getBoundingClientRect();
      var flyoutWidth = flyout.offsetWidth;
      var flyoutHeight = flyout.offsetHeight;
      var left = Math.min(rect.right + 8, window.innerWidth - flyoutWidth - 8);
      var top = Math.max(8, Math.min(rect.top, window.innerHeight - flyoutHeight - 8));

      flyout.style.left = Math.max(8, left) + "px";
      flyout.style.top = top + "px";
    }

    function copyLink(source, target) {
      target.href = source.href;
      target.innerHTML = source.innerHTML;
      target.className = "nav-link" + (source.classList.contains("active") ? " active" : "");
      target.setAttribute("role", "menuitem");
      target.addEventListener("click", closeFlyout);
    }

    function showFlyout(trigger) {
      if (!isDesktop() || !isMini() || !trigger.closest(".sidebar-nav")) {
        return;
      }

      closeFlyout();
      flyoutTrigger = trigger;
      flyout = document.createElement("div");
      flyout.id = "sidebarFlyout";
      flyout.className = "sidebar-flyout";
      flyout.setAttribute("role", "menu");
      flyout.setAttribute("aria-label", trigger.querySelector(".nav-text").textContent.trim());
      if (!flyoutTrigger.hasAttribute("data-sidebar-submenu-toggle")) {
        flyoutTrigger.setAttribute("aria-controls", flyout.id);
      }

      var submenu = trigger.parentElement.querySelector(":scope > .submenu");
      if (submenu) {
        var title = document.createElement("h2");
        title.className = "sidebar-flyout-title";
        title.textContent = trigger.querySelector(".nav-text").textContent.trim();
        flyout.appendChild(title);

        var links = document.createElement("ul");
        links.className = "nav";
        Array.prototype.forEach.call(submenu.querySelectorAll(":scope > .nav-item > .nav-link"), function (source) {
          var item = document.createElement("li");
          var link = document.createElement("a");
          copyLink(source, link);
          item.appendChild(link);
          links.appendChild(item);
        });
        flyout.appendChild(links);
      } else {
        flyout.classList.add("sidebar-flyout-single");
        flyout.textContent = trigger.querySelector(".nav-text").textContent.trim();
        flyout.setAttribute("role", "tooltip");
        flyoutTrigger.setAttribute("aria-describedby", flyout.id);
      }

      document.body.appendChild(flyout);
      positionFlyout(trigger);
      flyout.addEventListener("mouseenter", function () {
        if (flyoutTimer) window.clearTimeout(flyoutTimer);
      });
      flyout.addEventListener("mouseleave", scheduleFlyoutClose);
      flyout.addEventListener("focusout", function (event) {
        if (!flyout.contains(event.relatedTarget)) scheduleFlyoutClose();
      });
      flyout.addEventListener("keydown", function (event) {
        var items = Array.prototype.slice.call(flyout.querySelectorAll(".nav-link"));
        var index = items.indexOf(document.activeElement);
        if (!items.length) return;

        if (event.key === "ArrowDown" || event.key === "ArrowUp") {
          event.preventDefault();
          items[(index + (event.key === "ArrowDown" ? 1 : items.length - 1)) % items.length].focus();
        } else if (event.key === "Home" || event.key === "End") {
          event.preventDefault();
          items[event.key === "Home" ? 0 : items.length - 1].focus();
        }
      });

    }

    function initFlyouts() {
      Array.prototype.forEach.call(sidebarItems, function (link) {
        var label = link.querySelector(".nav-text");
        if (!label) return;

        if (!link.hasAttribute("aria-label")) {
          link.setAttribute("aria-label", label.textContent.trim());
        }

        link.addEventListener("mouseenter", function () {
          if (flyoutTimer) window.clearTimeout(flyoutTimer);
          if (isMini()) showFlyout(link);
        });
        link.addEventListener("mouseleave", scheduleFlyoutClose);
        link.addEventListener("focus", function () {
          if (isMini()) showFlyout(link);
        });

        link.addEventListener("keydown", function (event) {
          if (link.hasAttribute("data-sidebar-submenu-toggle") &&
              (event.key === "Enter" || event.key === " " || event.code === "Space")) {
            event.preventDefault();
            if (isDesktop() && isMini()) {
              showFlyout(link);
            } else {
              toggleSubmenu(link);
            }
            return;
          }

          if (!isMini()) return;
          if (event.key === "ArrowRight" || event.key === "ArrowDown") {
            event.preventDefault();
            showFlyout(link);
            var firstFlyoutLink = flyout && flyout.querySelector(".nav-link");
            if (firstFlyoutLink) firstFlyoutLink.focus();
          }
        });

        link.addEventListener("click", function (event) {
          if (link.hasAttribute("data-sidebar-submenu-toggle")) {
            event.preventDefault();
            if (isDesktop() && isMini()) {
              showFlyout(link);
            } else {
              toggleSubmenu(link);
            }
            return;
          }

          if (!isDesktop()) {
            closeMobileSidebar();
          }
        });
      });
    }

    function submenuFor(toggle) {
      return document.getElementById(toggle.getAttribute("aria-controls"));
    }

    function setSubmenuOpen(toggle, open, animate) {
      var submenu = submenuFor(toggle);
      if (!submenu) return;

      var animationId = Number(submenu.getAttribute("data-animation-id") || "0") + 1;
      submenu.setAttribute("data-animation-id", String(animationId));
      toggle.setAttribute("aria-expanded", String(open));
      submenu.setAttribute("aria-hidden", String(!open));
      submenu.toggleAttribute("inert", !open);

      if (!animate) {
        submenu.style.height = open ? "auto" : "0px";
        submenu.classList.toggle("is-open", open);
        return;
      }

      if (open) {
        submenu.style.height = "0px";
        submenu.classList.add("is-open");
        function finishOpen() {
          if (submenu.getAttribute("data-animation-id") === String(animationId) &&
              toggle.getAttribute("aria-expanded") === "true") {
            submenu.style.height = "auto";
          }
        }

        submenu.addEventListener("transitionend", function onOpenTransition(event) {
          if (event.propertyName !== "height") return;
          submenu.removeEventListener("transitionend", onOpenTransition);
          finishOpen();
        });
        void submenu.offsetHeight;
        submenu.style.height = submenu.scrollHeight + "px";
        window.setTimeout(finishOpen, 250);
        return;
      }

      submenu.style.height = submenu.getBoundingClientRect().height + "px";
      submenu.classList.remove("is-open");
      void submenu.offsetHeight;
      submenu.style.height = "0px";
    }

    function toggleSubmenu(toggle) {
      setSubmenuOpen(toggle, toggle.getAttribute("aria-expanded") !== "true", true);
    }

    function closeSubmenus() {
      Array.prototype.forEach.call(submenuToggles, function (toggle) {
        setSubmenuOpen(toggle, false, false);
      });
    }

    function setMiniState(mini) {
      body.classList.toggle("sidebar-mini", mini);
      root.classList.toggle("sidebar-mini", mini);
      if (storageAvailable) storageSet(sidebarStorageKey, String(mini));
      closeFlyout();
      if (mini) closeSubmenus();
      updateToggleState();
    }

    function closeMobileSidebar() {
      body.classList.remove("sidebar-open");
      updateToggleState();
    }

    function toggleSidebar() {
      if (isDesktop()) {
        setMiniState(!isMini());
      } else {
        body.classList.toggle("sidebar-open");
        updateToggleState();
      }
    }

    function setActiveLinks() {
      var current = new URL(window.location.href);
      var matches = [];

      Array.prototype.forEach.call(sidebarLinks, function (link) {
        var href = link.getAttribute("href");
        if (!href || href.charAt(0) === "#") return;

        var target;
        try {
          target = new URL(href, window.location.href);
        } catch (error) {
          return;
        }

        if (target.origin !== current.origin || target.pathname.replace(/\/$/, "") !== current.pathname.replace(/\/$/, "")) {
          return;
        }

        var queryMatches = true;
        target.searchParams.forEach(function (value, key) {
          if (current.searchParams.get(key) !== value) queryMatches = false;
        });

        if (queryMatches) matches.push(link);
      });

      var active = matches[0];
      if (!active) return;

      active.classList.add("active");
      active.setAttribute("aria-current", "page");

      var submenu = active.closest(".submenu");
      if (submenu) {
        var parent = submenu.parentElement.querySelector(":scope > [data-sidebar-submenu-toggle]");
        if (parent) {
          parent.classList.add("active");
          setSubmenuOpen(parent, true, false);
        }
      }
    }

    function handleBreakpointChange() {
      closeFlyout();
      body.classList.remove("sidebar-open");
      if (isDesktop()) {
        setMiniState(savedMiniState());
      } else {
        body.classList.remove("sidebar-mini");
        root.classList.remove("sidebar-mini");
        updateToggleState();
      }
    }

    initValidation();
    initTableSearch();
    initThemeToggle();
    initUserProfile();
    closeSubmenus();
    setActiveLinks();
    initFlyouts();

    if (savedMiniState() && isDesktop()) {
      body.classList.add("sidebar-mini");
      root.classList.add("sidebar-mini");
    } else {
      body.classList.remove("sidebar-mini");
      root.classList.remove("sidebar-mini");
    }
    if (isMini()) closeSubmenus();
    if (sidebarToggle) sidebarToggle.addEventListener("click", toggleSidebar);
    if (collapseToggle) collapseToggle.addEventListener("click", toggleSidebar);

    Array.prototype.forEach.call(document.querySelectorAll("[data-sidebar-close]"), function (element) {
      element.addEventListener("click", closeMobileSidebar);
    });

    document.addEventListener("keydown", function (event) {
      if (event.key === "Escape") {
        if (flyout) {
          var trigger = flyoutTrigger;
          closeFlyout();
          if (trigger) trigger.focus();
        }

        if (body.classList.contains("sidebar-open")) {
          closeMobileSidebar();
          if (sidebarToggle) sidebarToggle.focus();
        }
      }
    });

    document.addEventListener("pointerdown", function (event) {
      if (flyout && !flyout.contains(event.target) && !flyoutTrigger.contains(event.target)) {
        closeFlyout();
      }
    });

    if (mediaQuery.addEventListener) {
      mediaQuery.addEventListener("change", handleBreakpointChange);
    } else if (mediaQuery.addListener) {
      mediaQuery.addListener(handleBreakpointChange);
    }
    window.addEventListener("resize", handleBreakpointChange);

    updateToggleState();
  });
})();

/**
 * Standard DataTable "Action" cell: a cog button opening a dropdown menu.
 * PHP twin: action_dropdown() in app/Helpers/helpers.php - keep them in step.
 *
 * items: [{ label, icon, onclick | href, target, class, attrs: { 'data-id': 5 } } | { divider: true }]
 */
window.actionDropdown = function (items) {
  function esc(value) {
    return String(value == null ? "" : value)
      .replace(/&/g, "&amp;").replace(/</g, "&lt;").replace(/>/g, "&gt;")
      .replace(/"/g, "&quot;").replace(/'/g, "&#039;");
  }

  var html =
    '<div class="dropdown">' +
      '<button type="button" class="btn btn-sm btn-primary dropdown-toggle" data-bs-toggle="dropdown"' +
        " data-bs-popper-config='{\"strategy\":\"fixed\"}' aria-expanded=\"false\" title=\"Actions\">" +
        '<i class="fas fa-cog"></i>' +
      "</button>" +
      '<ul class="dropdown-menu dropdown-menu-end">';

  (items || []).forEach(function (item) {
    if (item.divider) {
      html += '<li><hr class="dropdown-divider"></li>';
      return;
    }

    var attrs = "";
    Object.keys(item.attrs || {}).forEach(function (name) {
      attrs += " " + name + '="' + esc(item.attrs[name]) + '"';
    });

    var link = item.href
      ? 'href="' + esc(item.href) + '"' + (item.target ? ' target="' + esc(item.target) + '"' : "")
      : 'href="javascript:void(0)"' + (item.onclick ? ' onclick="' + esc(item.onclick) + '"' : "");

    html +=
      "<li><a " + link + ' class="dropdown-item' + (item.class ? " " + esc(item.class) : "") + '"' + attrs + ">" +
        (item.icon ? '<i class="' + esc(item.icon) + ' me-2"></i>' : "") +
        esc(item.label) +
      "</a></li>";
  });

  return html + "</ul></div>";
};
